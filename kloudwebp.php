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
    $plugin->run();
}
run_kloudwebp();
