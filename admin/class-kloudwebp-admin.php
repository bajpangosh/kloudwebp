<?php

class KloudWebP_Admin {
    private $plugin_name;
    private $version;
    private $converter;

    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->converter = new KloudWebP_Converter();
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
        
        add_settings_error(
            'kloudwebp_messages',
            'kloudwebp_bulk_convert',
            sprintf(
                __('Conversion completed. Success: %d, Failed: %d, Skipped: %d', 'kloudwebp'),
                $results['success'],
                $results['failed'],
                $results['skipped']
            ),
            'updated'
        );
    }
}
