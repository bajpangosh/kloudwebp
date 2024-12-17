<?php
/**
 * Plugin Name: KloudWebP
 * Plugin URI: https://github.com/bajpangosh/kloudwebp
 * Description: Convert JPEG and PNG images to WebP format in WordPress posts and pages
 * Version: 1.0.0
 * Author: BajPanGosh
 * Author URI: https://github.com/bajpangosh
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: kloudwebp
 * Domain Path: /languages
 * Requires at least: 4.7
 * Requires PHP: 7.0
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Define plugin constants
define('KLOUDWEBP_VERSION', '1.0.0');
define('KLOUDWEBP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('KLOUDWEBP_PLUGIN_URL', plugin_dir_url(__FILE__));

// Check for Imagick extension
function kloudwebp_check_imagick() {
    if (!extension_loaded('imagick')) {
        add_action('admin_notices', function() {
            echo '<div class="error"><p>';
            echo __('KloudWebP requires the Imagick PHP extension to be installed. Please contact your hosting provider or server administrator to install and enable Imagick.', 'kloudwebp');
            echo '</p></div>';
        });
        return false;
    }
    return true;
}

// Initialize the plugin
function kloudwebp_init() {
    if (!kloudwebp_check_imagick()) {
        return;
    }

    // Include required files
    require_once KLOUDWEBP_PLUGIN_DIR . 'includes/class-kloudwebp-converter.php';
    require_once KLOUDWEBP_PLUGIN_DIR . 'admin/class-kloudwebp-admin.php';
    
    // Initialize admin
    if (is_admin()) {
        new KloudWebP_Admin();
    }
}
add_action('plugins_loaded', 'kloudwebp_init');

// Activation hook
function kloudwebp_activate() {
    // Create necessary database tables
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'kloudwebp_conversions';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        post_id bigint(20) NOT NULL,
        status varchar(20) NOT NULL DEFAULT 'not_converted',
        converted_count int(11) NOT NULL DEFAULT 0,
        total_images int(11) NOT NULL DEFAULT 0,
        last_converted datetime DEFAULT NULL,
        PRIMARY KEY  (id),
        KEY post_id (post_id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    // Set default options
    add_option('kloudwebp_compression_quality', 80);
    add_option('kloudwebp_update_content', true);
}
register_activation_hook(__FILE__, 'kloudwebp_activate');

// Deactivation hook
function kloudwebp_deactivate() {
    // Clean up if necessary
}
register_deactivation_hook(__FILE__, 'kloudwebp_deactivate');

// Uninstall hook
function kloudwebp_uninstall() {
    // Clean up database tables and options
    global $wpdb;
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}kloudwebp_conversions");
    
    delete_option('kloudwebp_compression_quality');
    delete_option('kloudwebp_update_content');
}
register_uninstall_hook(__FILE__, 'kloudwebp_uninstall');
