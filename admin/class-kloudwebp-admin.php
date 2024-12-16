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
        add_options_page(
            'KloudWebP Settings',
            'KloudWebP',
            'manage_options',
            $this->plugin_name,
            array($this, 'display_plugin_setup_page')
        );
    }

    public function add_action_links($links) {
        $settings_link = array(
            '<a href="' . admin_url('options-general.php?page=' . $this->plugin_name) . '">' . __('Settings', 'kloudwebp') . '</a>',
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
