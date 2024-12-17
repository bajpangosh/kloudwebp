<?php
/**
 * Plugin Name: KloudWebP
 * Plugin URI: https://github.com/bajpangosh/kloudwebp
 * Description: Convert and serve WebP images in WordPress with fallback support
 * Version: 1.0.0
 * Author: Bajpan Gosh
 * Author URI: https://github.com/bajpangosh
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: kloudwebp
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Define plugin constants
define('KLOUDWEBP_VERSION', '1.0.0');
define('KLOUDWEBP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('KLOUDWEBP_PLUGIN_URL', plugin_dir_url(__FILE__));

// Require the core plugin class
require_once KLOUDWEBP_PLUGIN_DIR . 'includes/class-kloudwebp.php';

// Initialize the plugin
function run_kloudwebp() {
    $plugin = new KloudWebP();
    $plugin->loader->add_action('admin_enqueue_scripts', $plugin->plugin_admin, 'enqueue_styles');
    $plugin->loader->add_action('admin_enqueue_scripts', $plugin->plugin_admin, 'enqueue_scripts');
    $plugin->loader->add_action('admin_menu', $plugin->plugin_admin, 'add_plugin_admin_menu');
    $plugin->loader->add_action('wp_ajax_convert_images', $plugin->plugin_admin, 'convert_images');
    $plugin->loader->add_action('wp_ajax_get_conversion_stats', $plugin->plugin_admin, 'get_conversion_stats');
    
    // Add settings link on plugin page
    $plugin_basename = plugin_basename(plugin_dir_path(__FILE__) . 'kloudwebp.php');
    $plugin->loader->add_filter('plugin_action_links_' . $plugin_basename, $plugin->plugin_admin, 'add_action_links');
    $plugin->run();
}
run_kloudwebp();
