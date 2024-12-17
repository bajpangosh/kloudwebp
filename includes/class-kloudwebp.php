<?php

class KloudWebP {
    protected $loader;
    protected $plugin_name;
    protected $version;
    protected $plugin_admin;
    protected $converter;

    public function __construct() {
        $this->version = KLOUDWEBP_VERSION;
        $this->plugin_name = 'kloudwebp';
        
        $this->load_dependencies();
        $this->define_admin_hooks();
    }

    private function load_dependencies() {
        $this->loader = new KloudWebP_Loader();
        $this->converter = new KloudWebP_Converter();
        $this->plugin_admin = new KloudWebP_Admin($this->get_plugin_name(), $this->get_version(), $this->converter);
    }

    private function define_admin_hooks() {
        // Admin hooks
        $this->loader->add_action('admin_enqueue_scripts', $this->plugin_admin, 'enqueue_styles');
        $this->loader->add_action('admin_enqueue_scripts', $this->plugin_admin, 'enqueue_scripts');
        $this->loader->add_action('admin_menu', $this->plugin_admin, 'add_plugin_admin_menu');
        $this->loader->add_action('wp_ajax_convert_images', $this->plugin_admin, 'convert_images');
        $this->loader->add_action('wp_ajax_get_conversion_stats', $this->plugin_admin, 'get_conversion_stats');
        
        // Add settings link on plugin page
        $plugin_basename = plugin_basename(KLOUDWEBP_PLUGIN_DIR . 'kloudwebp.php');
        $this->loader->add_filter('plugin_action_links_' . $plugin_basename, $this->plugin_admin, 'add_action_links');
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

    public function get_loader() {
        return $this->loader;
    }
}
