<?php

class KloudWebP {
    protected $loader;
    protected $plugin_name;
    protected $version;
    protected $settings;

    public function __construct() {
        $this->version = KLOUDWEBP_VERSION;
        $this->plugin_name = 'kloudwebp';
        $this->load_dependencies();
        $this->define_admin_hooks();
        $this->define_public_hooks();
    }

    private function load_dependencies() {
        require_once KLOUDWEBP_PLUGIN_DIR . 'includes/class-kloudwebp-loader.php';
        require_once KLOUDWEBP_PLUGIN_DIR . 'includes/class-kloudwebp-converter.php';
        require_once KLOUDWEBP_PLUGIN_DIR . 'admin/class-kloudwebp-admin.php';
        require_once KLOUDWEBP_PLUGIN_DIR . 'public/class-kloudwebp-public.php';
        
        $this->loader = new KloudWebP_Loader();
    }

    private function define_admin_hooks() {
        $plugin_admin = new KloudWebP_Admin($this->get_plugin_name(), $this->get_version());
        
        // Add menu items
        $this->loader->add_action('admin_menu', $plugin_admin, 'add_plugin_menu');
        
        // Register settings
        $this->loader->add_action('admin_init', $plugin_admin, 'register_settings');
        
        // Add settings link to plugins page
        $this->loader->add_filter('plugin_action_links_' . plugin_basename(KLOUDWEBP_PLUGIN_DIR . 'kloudwebp.php'), 
            $plugin_admin, 'add_action_links');
            
        // Add media library integration
        $this->loader->add_filter('wp_generate_attachment_metadata', $plugin_admin, 'convert_uploaded_image', 10, 2);
    }

    private function define_public_hooks() {
        $plugin_public = new KloudWebP_Public($this->get_plugin_name(), $this->get_version());
        
        // Filter image output
        $this->loader->add_filter('wp_get_attachment_image_src', $plugin_public, 'filter_image_src', 10, 4);
        $this->loader->add_filter('wp_calculate_image_srcset', $plugin_public, 'filter_image_srcset', 10, 5);
    }

    public function run() {
        $this->loader->run();
    }

    public function get_plugin_name() {
        return $this->plugin_name;
    }

    public function get_version() {
        return $this->version;
    }
}
