<?php

class KloudWebP_Admin {
    private $plugin_name;
    private $version;
    private $converter;

    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->converter = new KloudWebP_Converter();

        // Register settings
        add_action('admin_init', array($this, 'register_settings'));
        
        // Add menu
        add_action('admin_menu', array($this, 'add_plugin_menu'));
        
        // Add settings link
        add_filter('plugin_action_links_' . plugin_basename(plugin_dir_path(__DIR__) . $this->plugin_name . '.php'),
            array($this, 'add_action_links')
        );

        // Handle post conversion
        add_action('admin_post_kloudwebp_convert_posts', array($this, 'handle_convert_posts'));
        add_action('wp_ajax_kloudwebp_convert_single_post', array($this, 'ajax_convert_single_post'));
        
        // Add admin notices
        add_action('admin_notices', array($this, 'admin_notices'));

        // Handle image uploads
        add_filter('wp_handle_upload_prefilter', array($this, 'pre_upload'));
        add_filter('wp_handle_upload', array($this, 'handle_upload'));
        add_filter('wp_update_attachment_metadata', array($this, 'update_attachment_metadata'), 10, 2);
        
        // Filter image URLs
        add_filter('wp_get_attachment_url', array($this, 'filter_attachment_url'), 10, 2);
        add_filter('wp_get_attachment_image_src', array($this, 'filter_attachment_image_src'), 10, 4);
        
        // Enqueue scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    public function register_settings() {
        register_setting(
            'kloudwebp_settings',
            'kloudwebp_settings',
            array($this, 'sanitize_settings')
        );

        add_settings_section(
            'kloudwebp_main_section',
            'General Settings',
            null,
            'kloudwebp_settings'
        );

        add_settings_field(
            'kloudwebp_auto_convert',
            'Auto Convert',
            array($this, 'render_auto_convert_field'),
            'kloudwebp_settings',
            'kloudwebp_main_section'
        );

        add_settings_field(
            'kloudwebp_keep_original',
            'Keep Original',
            array($this, 'render_keep_original_field'),
            'kloudwebp_settings',
            'kloudwebp_main_section'
        );
    }

    public function add_plugin_menu() {
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

    public function handle_convert_posts() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        check_admin_referer('kloudwebp_convert_posts');

        global $wpdb;
        
        // Get all posts and pages
        $posts = $wpdb->get_results("
            SELECT ID, post_content 
            FROM {$wpdb->posts} 
            WHERE post_type IN ('post', 'page') 
            AND post_status = 'publish'
        ");

        $results = array(
            'success' => 0,
            'failed' => 0,
            'skipped' => 0,
            'updated_posts' => 0
        );

        $processed_urls = array();

        foreach ($posts as $post) {
            $content_updated = false;
            $content = $post->post_content;

            // Find all img tags in the content
            preg_match_all('/<img[^>]+src=([\'"])?([^\'">]+)/', $content, $matches);
            
            if (!empty($matches[2])) {
                foreach ($matches[2] as $url) {
                    // Skip if already processed this URL
                    if (in_array($url, $processed_urls)) {
                        continue;
                    }
                    
                    // Convert URL to file path
                    $file_path = $this->url_to_path($url);
                    if (!$file_path) {
                        $results['skipped']++;
                        continue;
                    }

                    // Check if it's a JPEG or PNG
                    if (preg_match('/\.(jpe?g|png)$/i', $file_path) && file_exists($file_path)) {
                        $webp_path = $this->converter->convert_image($file_path, false);
                        
                        if ($webp_path) {
                            // Get the WebP URL
                            $webp_url = str_replace(
                                wp_get_upload_dir()['basedir'],
                                wp_get_upload_dir()['baseurl'],
                                $webp_path
                            );

                            // Update image src in content
                            $content = str_replace($url, $webp_url, $content);
                            $content_updated = true;
                            $results['success']++;
                        } else {
                            $results['failed']++;
                        }
                    } else {
                        $results['skipped']++;
                    }

                    $processed_urls[] = $url;
                }
            }

            // Update post content if changed
            if ($content_updated) {
                wp_update_post(array(
                    'ID' => $post->ID,
                    'post_content' => $content
                ));
                $results['updated_posts']++;
            }
        }

        // Redirect back to dashboard with results
        wp_redirect(add_query_arg(
            array(
                'page' => $this->plugin_name,
                'posts_converted' => $results['success'],
                'posts_failed' => $results['failed'],
                'posts_skipped' => $results['skipped'],
                'updated_posts' => $results['updated_posts']
            ),
            admin_url('admin.php')
        ));
        exit;
    }

    public function pre_upload($file) {
        // Check if auto-convert is enabled
        if (!get_option('kloudwebp_auto_convert', false)) {
            return $file;
        }

        if (!in_array($file['type'], ['image/jpeg', 'image/png'])) {
            return $file;
        }

        // Change the filename to .webp before upload
        $file['name'] = preg_replace('/\.(jpe?g|png)$/i', '.webp', $file['name']);
        return $file;
    }

    public function handle_upload($upload, $context = 'upload') {
        // Check if auto-convert is enabled
        if (!get_option('kloudwebp_auto_convert', false)) {
            return $upload;
        }

        // Only process image uploads
        if (!in_array($upload['type'], ['image/jpeg', 'image/png'])) {
            return $upload;
        }

        // Get file extension
        $ext = pathinfo($upload['file'], PATHINFO_EXTENSION);
        if (!in_array(strtolower($ext), ['jpg', 'jpeg', 'png'])) {
            return $upload;
        }

        // Generate WebP filename
        $webp_file = preg_replace('/\.(jpe?g|png)$/i', '.webp', $upload['file']);
        $webp_url = preg_replace('/\.(jpe?g|png)$/i', '.webp', $upload['url']);

        // Convert the uploaded image
        if ($this->converter->convert_image($upload['file'], false)) {
            // If conversion successful and not keeping original
            if (!get_option('kloudwebp_keep_original', true)) {
                // Rename the file to .webp
                rename($upload['file'], $webp_file);
                
                // Update the upload array
                $upload['file'] = $webp_file;
                $upload['url'] = $webp_url;
                $upload['type'] = 'image/webp';
            }
        }

        return $upload;
    }

    public function update_attachment_metadata($metadata, $attachment_id) {
        // Check if auto-convert is enabled
        if (!get_option('kloudwebp_auto_convert', false)) {
            return $metadata;
        }

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

    public function filter_attachment_url($url, $attachment_id) {
        // Check if auto-convert is enabled
        if (!get_option('kloudwebp_auto_convert', false)) {
            return $url;
        }

        // Check if this is an image
        if (!wp_attachment_is_image($attachment_id)) {
            return $url;
        }

        // Get the WebP version URL if it exists
        $webp_url = preg_replace('/\.(jpe?g|png)$/i', '.webp', $url);
        $file_path = str_replace(wp_get_upload_dir()['basedir'], wp_get_upload_dir()['baseurl'], $webp_url);

        if (file_exists($file_path)) {
            return $webp_url;
        }

        return $url;
    }

    public function filter_attachment_image_src($image, $attachment_id, $size, $icon) {
        // Check if auto-convert is enabled
        if (!get_option('kloudwebp_auto_convert', false)) {
            return $image;
        }

        if (!$image) {
            return $image;
        }

        // Check if this is an image
        if (!wp_attachment_is_image($attachment_id)) {
            return $image;
        }

        // Get the WebP version URL if it exists
        $webp_url = preg_replace('/\.(jpe?g|png)$/i', '.webp', $image[0]);
        $file_path = str_replace(wp_get_upload_dir()['basedir'], wp_get_upload_dir()['baseurl'], $webp_url);

        if (file_exists($file_path)) {
            $image[0] = $webp_url;
        }

        return $image;
    }

    private function url_to_path($url) {
        // Remove query strings
        $url = preg_replace('/\?.*/', '', $url);
        
        // Get upload directory info
        $upload_dir = wp_upload_dir();
        
        // Convert URL to path
        $path = str_replace(
            array($upload_dir['baseurl'], site_url()),
            array($upload_dir['basedir'], ABSPATH),
            $url
        );
        
        return file_exists($path) ? $path : false;
    }

    /**
     * Get posts and pages with their image conversion status
     */
    private function get_posts_conversion_status() {
        global $wpdb;
        
        $posts = $wpdb->get_results("
            SELECT ID, post_title, post_type, post_status, post_modified 
            FROM {$wpdb->posts} 
            WHERE post_type IN ('post', 'page') 
            AND post_status = 'publish'
            ORDER BY post_modified DESC
        ");

        $results = array();
        foreach ($posts as $post) {
            $images = $this->get_post_images($post->ID);
            if (!empty($images)) {
                $results[] = array(
                    'id' => $post->ID,
                    'title' => $post->post_title,
                    'type' => $post->post_type,
                    'modified' => $post->post_modified,
                    'total_images' => count($images['all']),
                    'converted' => count($images['converted']),
                    'unconverted' => count($images['unconverted']),
                    'images' => $images
                );
            }
        }

        return $results;
    }

    /**
     * Get images from a specific post
     */
    private function get_post_images($post_id) {
        $content = get_post_field('post_content', $post_id);
        $images = array(
            'all' => array(),
            'converted' => array(),
            'unconverted' => array()
        );

        // Find all img tags in the content
        preg_match_all('/<img[^>]+src=([\'"])?([^\'">]+)/', $content, $matches);
        
        if (!empty($matches[2])) {
            foreach ($matches[2] as $url) {
                $file_path = $this->url_to_path($url);
                if (!$file_path) {
                    continue;
                }

                $image_info = array(
                    'url' => $url,
                    'path' => $file_path
                );

                $images['all'][] = $image_info;

                // Check if WebP version exists
                $webp_path = preg_replace('/\.(jpe?g|png)$/i', '.webp', $file_path);
                if (file_exists($webp_path)) {
                    $images['converted'][] = $image_info;
                } else if (preg_match('/\.(jpe?g|png)$/i', $file_path)) {
                    $images['unconverted'][] = $image_info;
                }
            }
        }

        return $images;
    }

    public function convert_single_post($post_id) {
        if (!current_user_can('manage_options')) {
            return false;
        }

        $images = $this->get_post_images($post_id);
        $content = get_post_field('post_content', $post_id);
        $content_updated = false;
        $results = array(
            'success' => 0,
            'failed' => 0,
            'skipped' => 0
        );

        foreach ($images['unconverted'] as $image) {
            $webp_path = $this->converter->convert_image($image['path'], false);
            
            if ($webp_path) {
                // Get the WebP URL
                $webp_url = str_replace(
                    wp_get_upload_dir()['basedir'],
                    wp_get_upload_dir()['baseurl'],
                    $webp_path
                );

                // Update image src in content
                $content = str_replace($image['url'], $webp_url, $content);
                $content_updated = true;
                $results['success']++;
            } else {
                $results['failed']++;
            }
        }

        // Update post content if changed
        if ($content_updated) {
            wp_update_post(array(
                'ID' => $post_id,
                'post_content' => $content
            ));
        }

        return $results;
    }

    /**
     * AJAX handler for converting single post images
     */
    public function ajax_convert_single_post() {
        check_ajax_referer('kloudwebp_convert_post', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        if (!$post_id) {
            wp_send_json_error('Invalid post ID');
            return;
        }

        $content = get_post_field('post_content', $post_id);
        $content_updated = false;
        $results = array(
            'success' => 0,
            'failed' => 0,
            'skipped' => 0
        );

        // Find all img tags in the content
        preg_match_all('/<img[^>]+src=([\'"])?([^\'">]+)/', $content, $matches);
        
        if (!empty($matches[2])) {
            foreach ($matches[2] as $url) {
                // Convert URL to file path
                $file_path = $this->url_to_path($url);
                if (!$file_path) {
                    $results['skipped']++;
                    continue;
                }

                // Check if it's a JPEG or PNG
                if (preg_match('/\.(jpe?g|png)$/i', $file_path) && file_exists($file_path)) {
                    $webp_path = $this->converter->convert_image($file_path, false);
                    
                    if ($webp_path) {
                        // Get the WebP URL
                        $webp_url = str_replace(
                            wp_get_upload_dir()['basedir'],
                            wp_get_upload_dir()['baseurl'],
                            $webp_path
                        );

                        // Update image src in content
                        $content = str_replace($url, $webp_url, $content);
                        $content_updated = true;
                        $results['success']++;
                    } else {
                        $results['failed']++;
                    }
                } else {
                    $results['skipped']++;
                }
            }
        }

        // Update post content if changed
        if ($content_updated) {
            wp_update_post(array(
                'ID' => $post_id,
                'post_content' => $content
            ));
        }

        wp_send_json_success($results);
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_scripts($hook) {
        if (strpos($hook, $this->plugin_name) === false) {
            return;
        }

        wp_enqueue_style(
            $this->plugin_name,
            plugin_dir_url(__FILE__) . 'css/kloudwebp-admin.css',
            array(),
            $this->version,
            'all'
        );

        wp_enqueue_script(
            $this->plugin_name,
            plugin_dir_url(__FILE__) . 'js/kloudwebp-admin.js',
            array('jquery'),
            $this->version,
            true
        );

        wp_localize_script(
            $this->plugin_name,
            'kloudwebpAjax',
            array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('kloudwebp_convert_post'),
                'converting' => __('Converting...'),
                'error' => __('Error'),
                'success' => __('Converted')
            )
        );
    }

    /**
     * Render the auto convert field
     */
    public function render_auto_convert_field() {
        $options = get_option('kloudwebp_settings', array());
        $auto_convert = isset($options['auto_convert']) ? $options['auto_convert'] : false;
        ?>
        <label>
            <input type="checkbox" name="kloudwebp_settings[auto_convert]" value="1" <?php checked(1, $auto_convert); ?>>
            Automatically convert new image uploads to WebP
        </label>
        <p class="description">When enabled, JPEG and PNG images will be automatically converted to WebP format upon upload.</p>
        <?php
    }

    /**
     * Render the keep original field
     */
    public function render_keep_original_field() {
        $options = get_option('kloudwebp_settings', array());
        $keep_original = isset($options['keep_original']) ? $options['keep_original'] : true;
        ?>
        <label>
            <input type="checkbox" name="kloudwebp_settings[keep_original]" value="1" <?php checked(1, $keep_original); ?>>
            Keep original images after conversion
        </label>
        <p class="description">If enabled, original JPEG and PNG files will be kept after converting to WebP.</p>
        <?php
    }

    /**
     * Sanitize settings
     */
    public function sanitize_settings($input) {
        $sanitized = array();
        
        $sanitized['auto_convert'] = isset($input['auto_convert']) ? 1 : 0;
        $sanitized['keep_original'] = isset($input['keep_original']) ? 1 : 0;
        
        return $sanitized;
    }

    /**
     * Get total count of images in media library
     */
    private function get_total_images_count() {
        global $wpdb;
        
        $count = $wpdb->get_var(
            "SELECT COUNT(*) FROM $wpdb->posts 
            WHERE post_type = 'attachment' 
            AND post_mime_type IN ('image/jpeg', 'image/png', 'image/webp')"
        );
        
        return (int) $count;
    }

    /**
     * Get count of images converted to WebP
     */
    private function get_converted_images_count() {
        global $wpdb;
        
        $count = $wpdb->get_var(
            "SELECT COUNT(*) FROM $wpdb->posts 
            WHERE post_type = 'attachment' 
            AND post_mime_type = 'image/webp'"
        );
        
        return (int) $count;
    }

    /**
     * Calculate total space saved by WebP conversion
     */
    private function get_total_space_saved() {
        global $wpdb;
        
        $attachments = $wpdb->get_results(
            "SELECT ID, meta_value FROM $wpdb->posts p
            JOIN $wpdb->postmeta pm ON p.ID = pm.post_id
            WHERE post_type = 'attachment'
            AND post_mime_type = 'image/webp'
            AND meta_key = '_wp_attachment_metadata'"
        );

        $total_saved = 0;
        
        foreach ($attachments as $attachment) {
            $metadata = maybe_unserialize($attachment->meta_value);
            if (!empty($metadata['original_size']) && !empty($metadata['filesize'])) {
                $total_saved += ($metadata['original_size'] - $metadata['filesize']);
            }
        }
        
        return $total_saved;
    }
}
