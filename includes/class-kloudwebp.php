<?php

/**
 * The core plugin class
 */
class KloudWebP {

    /**
     * The loader that's responsible for maintaining and registering all hooks that power
     * the plugin.
     */
    protected $loader;

    /**
     * The unique identifier of this plugin.
     */
    protected $plugin_name;

    /**
     * The current version of the plugin.
     */
    protected $version;

    /**
     * The admin-specific functionality of the plugin.
     */
    protected $plugin_admin;

    /**
     * The converter instance.
     */
    protected $converter;

    /**
     * Initialize the class and set its properties.
     */
    public function __construct() {
        if (defined('KLOUDWEBP_VERSION')) {
            $this->version = KLOUDWEBP_VERSION;
        } else {
            $this->version = '1.0.0';
        }
        $this->plugin_name = 'kloudwebp';
        
        $this->load_dependencies();
        $this->define_admin_hooks();
    }

    /**
     * Load the required dependencies for this plugin.
     */
    private function load_dependencies() {
        $this->loader = new KloudWebP_Loader();
        $this->converter = new KloudWebP_Converter();
        $this->plugin_admin = new KloudWebP_Admin($this->get_plugin_name(), $this->get_version(), $this->converter);
    }

    /**
     * Register all of the hooks related to the admin area functionality
     * of the plugin.
     */
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

        // Handle image upload
        $this->loader->add_filter('wp_handle_upload', $this->plugin_admin, 'handle_upload', 10, 2);
    }

    /**
     * Run the loader to execute all of the hooks with WordPress.
     */
    public function run() {
        $this->loader->run();
    }

    /**
     * The name of the plugin used to uniquely identify it within the context of
     * WordPress and to define internationalization functionality.
     */
    public function get_plugin_name() {
        return $this->plugin_name;
    }

    /**
     * Retrieve the version number of the plugin.
     */
    public function get_version() {
        return $this->version;
    }

    /**
     * The reference to the class that orchestrates the hooks with the plugin.
     */
    public function get_loader() {
        return $this->loader;
    }
}
