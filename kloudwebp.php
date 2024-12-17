<?php
/**
 * Plugin Name: KloudWebP
 * Plugin URI: https://github.com/bajpangosh/kloudwebp
 * Description: Convert and serve WebP images automatically
 * Version: 1.0.0
 * Author: Bajpan Gosh
 * Author URI: https://github.com/bajpangosh
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: kloudwebp
 * Domain Path: /languages
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Define plugin constants
define('KLOUDWEBP_VERSION', '1.0.0');
define('KLOUDWEBP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('KLOUDWEBP_PLUGIN_URL', plugin_dir_url(__FILE__));

// Require dependencies first
require_once KLOUDWEBP_PLUGIN_DIR . 'includes/class-kloudwebp-loader.php';
require_once KLOUDWEBP_PLUGIN_DIR . 'includes/class-kloudwebp-activator.php';
require_once KLOUDWEBP_PLUGIN_DIR . 'includes/class-kloudwebp-deactivator.php';
require_once KLOUDWEBP_PLUGIN_DIR . 'includes/class-kloudwebp-converter.php';
require_once KLOUDWEBP_PLUGIN_DIR . 'admin/class-kloudwebp-admin.php';
require_once KLOUDWEBP_PLUGIN_DIR . 'includes/class-kloudwebp.php';

/**
 * Activation hook
 */
function activate_kloudwebp() {
    KloudWebP_Activator::activate();
}

/**
 * Deactivation hook
 */
function deactivate_kloudwebp() {
    KloudWebP_Deactivator::deactivate();
}

register_activation_hook(__FILE__, 'activate_kloudwebp');
register_deactivation_hook(__FILE__, 'deactivate_kloudwebp');

/**
 * Initialize the plugin
 */
function run_kloudwebp() {
    $plugin = new KloudWebP();
    $plugin->run();
}

run_kloudwebp();
