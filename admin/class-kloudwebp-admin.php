<?php

class KloudWebP_Admin {
    private $plugin_name;
    private $version;
    private $converter;

    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->converter = new KloudWebP_Converter();

        // Add action for handling bulk conversion
        add_action('admin_post_kloudwebp_bulk_convert', array($this, 'handle_bulk_convert'));
        
        // Add filter for handling image uploads
        add_filter('wp_handle_upload', array($this, 'handle_upload'), 10, 2);
        
        // Add filter for attachment metadata
        add_filter('wp_generate_attachment_metadata', array($this, 'update_attachment_metadata'), 10, 2);

        // Add filter to update mime type
        add_filter('wp_update_attachment_metadata', array($this, 'after_attachment_metadata_update'), 10, 2);
        
        // Add filter for image editor save
        add_filter('wp_image_editors', array($this, 'customize_image_editors'));
    }

    public function add_plugin_admin_menu() {
        // Add main menu item
        add_menu_page(
            'KloudWebP Dashboard',
            'KloudWebP',
            'manage_options',
            $this->plugin_name,
            array($this, 'display_plugin_dashboard_page'),
            'dashicons-images-alt2',
            30
        );

        // Add submenu items
        add_submenu_page(
            $this->plugin_name,
            'KloudWebP Dashboard',
            'Dashboard',
            'manage_options',
            $this->plugin_name,
            array($this, 'display_plugin_dashboard_page')
        );

        add_submenu_page(
            $this->plugin_name,
            'KloudWebP Settings',
            'Settings',
            'manage_options',
            $this->plugin_name . '-settings',
            array($this, 'display_plugin_setup_page')
        );
    }

    public function add_action_links($links) {
        $settings_link = array(
            '<a href="' . admin_url('options-general.php?page=' . $this->plugin_name . '-settings') . '">' . __('Settings', 'kloudwebp') . '</a>',
        );
        return array_merge($settings_link, $links);
    }

    public function register_settings() {
        // Register settings
        register_setting($this->plugin_name, 'kloudwebp_quality');
        register_setting($this->plugin_name, 'kloudwebp_keep_original');
        register_setting($this->plugin_name, 'kloudwebp_auto_convert');
    }

    public function display_plugin_setup_page() {
        include_once KLOUDWEBP_PLUGIN_DIR . 'admin/partials/kloudwebp-admin-display.php';
    }

    public function display_plugin_dashboard_page() {
        // Get statistics
        $stats = $this->get_conversion_stats();
        include_once KLOUDWEBP_PLUGIN_DIR . 'admin/partials/kloudwebp-admin-dashboard.php';
    }

    private function get_conversion_stats() {
        $total_images = wp_count_attachments('image');
        $total_count = 0;
        foreach (['image/jpeg', 'image/png'] as $mime) {
            $total_count += isset($total_images->$mime) ? $total_images->$mime : 0;
        }

        $args = array(
            'post_type' => 'attachment',
            'post_mime_type' => array('image/jpeg', 'image/png'),
            'posts_per_page' => -1,
            'fields' => 'ids',
        );
        $attachments = get_posts($args);

        $converted_count = 0;
        $total_size_original = 0;
        $total_size_webp = 0;

        foreach ($attachments as $attachment_id) {
            $file = get_attached_file($attachment_id);
            if (!$file) continue;

            $webp_path = preg_replace('/\.(jpe?g|png)$/i', '.webp', $file);
            
            if (file_exists($file)) {
                $total_size_original += filesize($file);
            }
            
            if (file_exists($webp_path)) {
                $converted_count++;
                $total_size_webp += filesize($webp_path);
            }
        }

        return array(
            'total_images' => $total_count,
            'converted_images' => $converted_count,
            'unconverted_images' => $total_count - $converted_count,
            'total_size_original' => $total_size_original,
            'total_size_webp' => $total_size_webp,
            'size_saved' => $total_size_original - $total_size_webp,
            'conversion_rate' => $total_count > 0 ? ($converted_count / $total_count) * 100 : 0
        );
    }

    public function convert_uploaded_image($metadata, $attachment_id) {
        if (!get_option('kloudwebp_auto_convert', false)) {
            return $metadata;
        }

        $file = get_attached_file($attachment_id);
        $mime_type = get_post_mime_type($attachment_id);

        if (!in_array($mime_type, ['image/jpeg', 'image/png'])) {
            return $metadata;
        }

        $webp_path = $this->converter->convert_image($file);
        if ($webp_path) {
            // Update metadata if original was replaced
            if (!get_option('kloudwebp_keep_original', true)) {
                $metadata['file'] = wp_relative_upload_path($webp_path);
                $metadata['mime-type'] = 'image/webp';
            }
        }

        return $metadata;
    }

    public function handle_bulk_convert() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        check_admin_referer('kloudwebp_bulk_convert');
        
        $results = $this->converter->bulk_convert();
        
        // Redirect back to dashboard with results
        wp_redirect(add_query_arg(
            array(
                'page' => $this->plugin_name,
                'converted' => $results['success'],
                'failed' => $results['failed'],
                'skipped' => $results['skipped']
            ),
            admin_url('admin.php')
        ));
        exit;
    }

    public function handle_upload($upload, $context = 'upload') {
        // Only process image uploads
        if (strpos($upload['type'], 'image/') !== 0) {
            return $upload;
        }

        // Only process JPEG and PNG
        if (!in_array($upload['type'], ['image/jpeg', 'image/png'])) {
            return $upload;
        }

        // Convert the uploaded image
        $webp_path = $this->converter->convert_image($upload['file'], true);
        
        if ($webp_path && !get_option('kloudwebp_keep_original', true)) {
            // Update the upload array with WebP information
            $upload['file'] = $webp_path;
            $upload['url'] = str_replace(
                wp_get_upload_dir()['basedir'],
                wp_get_upload_dir()['baseurl'],
                $webp_path
            );
            $upload['type'] = 'image/webp';

            // If original file exists and we're not keeping it, delete it
            if (file_exists($upload['file']) && !get_option('kloudwebp_keep_original', true)) {
                @unlink($upload['file']);
            }
        }

        return $upload;
    }

    public function update_attachment_metadata($metadata, $attachment_id) {
        if (!is_array($metadata) || !isset($metadata['file'])) {
            return $metadata;
        }

        // Get the full path to the main image file
        $upload_dir = wp_upload_dir();
        $file_path = path_join($upload_dir['basedir'], $metadata['file']);
        
        // Check if this is a JPEG or PNG
        $mime_type = get_post_mime_type($attachment_id);
        if (!in_array($mime_type, ['image/jpeg', 'image/png'])) {
            return $metadata;
        }

        // Convert main image if not already converted
        if (!preg_match('/\.webp$/', $file_path)) {
            $webp_path = $this->converter->convert_image($file_path, true);
            if ($webp_path && !get_option('kloudwebp_keep_original', true)) {
                $metadata['file'] = str_replace($upload_dir['basedir'] . '/', '', $webp_path);
                $metadata['mime-type'] = 'image/webp';
                
                // Update post mime type
                wp_update_post(array(
                    'ID' => $attachment_id,
                    'post_mime_type' => 'image/webp'
                ));

                // Delete original if not keeping it
                if (file_exists($file_path) && !get_option('kloudwebp_keep_original', true)) {
                    @unlink($file_path);
                }
            }
        }

        // Convert all image sizes
        if (isset($metadata['sizes'])) {
            $base_dir = dirname($file_path);
            
            foreach ($metadata['sizes'] as $size => $size_info) {
                $size_file = path_join($base_dir, $size_info['file']);
                
                if (!preg_match('/\.webp$/', $size_file)) {
                    $size_webp = $this->converter->convert_image($size_file, false);
                    if ($size_webp && !get_option('kloudwebp_keep_original', true)) {
                        $metadata['sizes'][$size]['file'] = basename($size_webp);
                        $metadata['sizes'][$size]['mime-type'] = 'image/webp';
                        
                        // Delete original size if not keeping it
                        if (file_exists($size_file) && !get_option('kloudwebp_keep_original', true)) {
                            @unlink($size_file);
                        }
                    }
                }
            }
        }

        return $metadata;
    }

    public function after_attachment_metadata_update($metadata, $attachment_id) {
        if (!is_array($metadata) || !isset($metadata['file'])) {
            return $metadata;
        }

        // Check if this is a WebP image
        if (preg_match('/\.webp$/', $metadata['file'])) {
            // Update post mime type
            wp_update_post(array(
                'ID' => $attachment_id,
                'post_mime_type' => 'image/webp'
            ));

            // Update attachment metadata
            update_post_meta($attachment_id, '_wp_attachment_image_alt', 
                get_post_meta($attachment_id, '_wp_attachment_image_alt', true)
            );
        }

        return $metadata;
    }

    public function customize_image_editors($editors) {
        // Ensure WP_Image_Editor_GD is the first choice
        return array_merge(
            array('WP_Image_Editor_GD'),
            $editors
        );
    }
}
