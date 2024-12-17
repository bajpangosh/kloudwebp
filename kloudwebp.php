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

// Check for Imagick or GD extension
function kloudwebp_check_requirements() {
    if (!extension_loaded('imagick') && !extension_loaded('gd')) {
        add_action('admin_notices', function() {
            echo '<div class="error"><p>';
            echo __('KloudWebP requires either the Imagick or GD PHP extension to be installed. Please contact your hosting provider or server administrator to install and enable either Imagick or GD.', 'kloudwebp');
            echo '</p></div>';
        });
        return false;
    }
    return true;
}

// Initialize the plugin
function kloudwebp_init() {
    if (!kloudwebp_check_requirements()) {
        return;
    }

    // Include required files
    require_once KLOUDWEBP_PLUGIN_DIR . 'includes/class-kloudwebp-converter.php';
    require_once KLOUDWEBP_PLUGIN_DIR . 'admin/class-kloudwebp-admin.php';
    
    // Initialize admin
    if (is_admin()) {
        $admin = new KloudWebP_Admin();
        $admin->init();
    }
}
add_action('plugins_loaded', 'kloudwebp_init');

// Activation hook
function kloudwebp_activate() {
    global $wpdb;
    
    // Create necessary database tables
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

    // Clear any existing conversion data
    $wpdb->query("TRUNCATE TABLE $table_name");

    // Force refresh of permalinks
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'kloudwebp_activate');

// Deactivation hook
function kloudwebp_deactivate() {
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'kloudwebp_deactivate');

// Uninstall hook
function kloudwebp_uninstall() {
    global $wpdb;
    
    // Clean up database tables and options
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}kloudwebp_conversions");
    
    delete_option('kloudwebp_compression_quality');
    delete_option('kloudwebp_update_content');
}
register_uninstall_hook(__FILE__, 'kloudwebp_uninstall');

// Add WebP support hooks
add_action('init', 'kloudwebp_add_webp_support');

/**
 * Add WebP MIME type support
 */
function kloudwebp_add_webp_support() {
    add_filter('upload_mimes', function($mimes) {
        $mimes['webp'] = 'image/webp';
        return $mimes;
    });

    add_filter('file_is_displayable_image', function($result, $path) {
        if ($result === false && preg_match('/\.webp$/i', $path)) {
            $result = true;
        }
        return $result;
    }, 10, 2);
}

// Add filter for serving WebP images
add_filter('wp_get_attachment_image_src', 'kloudwebp_filter_attachment_image_src', 10, 4);
add_filter('wp_calculate_image_srcset', 'kloudwebp_filter_image_srcset', 10, 5);

/**
 * Filter attachment image source to use WebP
 */
function kloudwebp_filter_attachment_image_src($image, $attachment_id, $size, $icon) {
    if (!$image) {
        return $image;
    }

    // Check if WebP version exists
    $webp_path = kloudwebp_get_webp_path($image[0]);
    if (file_exists($webp_path)) {
        $image[0] = kloudwebp_get_webp_url($image[0]);
    }

    return $image;
}

/**
 * Filter image srcset to include WebP versions
 */
function kloudwebp_filter_image_srcset($sources, $size_array, $image_src, $image_meta, $attachment_id) {
    if (!is_array($sources)) {
        return $sources;
    }

    foreach ($sources as &$source) {
        $webp_path = kloudwebp_get_webp_path($source['url']);
        if (file_exists($webp_path)) {
            $source['url'] = kloudwebp_get_webp_url($source['url']);
        }
    }

    return $sources;
}

/**
 * Get WebP file path from URL
 */
function kloudwebp_get_webp_path($url) {
    $upload_dir = wp_upload_dir();
    $file_path = str_replace(
        $upload_dir['baseurl'],
        $upload_dir['basedir'],
        $url
    );
    
    return $file_path . '.webp';
}

/**
 * Get WebP URL from original URL
 */
function kloudwebp_get_webp_url($url) {
    return $url . '.webp';
}
