<?php
/*
Plugin Name: KloudWebP
Plugin URI: https://github.com/bajpangosh/kloudwebp
Description: Converts images in the media library to WebP format with options to replace or keep originals.
Version: 1.0.0
Author: Bajpan Gosh
Author URI: https://github.com/bajpangosh
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: kloudwebp
Domain Path: /languages
*/

// Set memory limit for large image processing
@ini_set('memory_limit', '512M');

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Define plugin constants
if (!defined('KLOUDWEBP_VERSION')) {
    define('KLOUDWEBP_VERSION', '1.0.0');
}
if (!defined('KLOUDWEBP_PLUGIN_DIR')) {
    define('KLOUDWEBP_PLUGIN_DIR', plugin_dir_path(__FILE__));
}
if (!defined('KLOUDWEBP_PLUGIN_URL')) {
    define('KLOUDWEBP_PLUGIN_URL', plugin_dir_url(__FILE__));
}
if (!defined('KLOUDWEBP_MAX_IMAGE_SIZE')) {
    define('KLOUDWEBP_MAX_IMAGE_SIZE', 15 * 1024 * 1024); // 15MB
}
if (!defined('KLOUDWEBP_CHUNK_SIZE')) {
    define('KLOUDWEBP_CHUNK_SIZE', 20); // Process 20 images per batch
}

// Initialize error logging
if (!function_exists('kloudwebp_init_error_log')) {
    function kloudwebp_init_error_log() {
        $log_file = KLOUDWEBP_PLUGIN_DIR . 'debug.log';
        if (!file_exists($log_file)) {
            touch($log_file);
        }
        return $log_file;
    }
}

// Enhanced error logging
if (!function_exists('kloudwebp_log_error')) {
    function kloudwebp_log_error($message, $type = 'ERROR') {
        static $log_file = null;
        if ($log_file === null) {
            $log_file = KLOUDWEBP_PLUGIN_DIR . 'debug.log';
            if (!file_exists($log_file)) {
                touch($log_file);
            }
        }
        $timestamp = current_time('mysql');
        $log_message = sprintf("[%s] [%s] %s\n", $timestamp, $type, $message);
        error_log($log_message, 3, $log_file);
    }
}

// Add debug logging
function kloudwebp_log_debug($message) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        kloudwebp_log_error($message, 'DEBUG');
    }
}

// Core WebP Support Functions
if (!function_exists('kloudwebp_browser_supports_webp')) {
    function kloudwebp_browser_supports_webp() {
        if (!isset($_SERVER['HTTP_ACCEPT'])) {
            return false;
        }
        
        if (strpos($_SERVER['HTTP_ACCEPT'], 'image/webp') !== false) {
            return true;
        }
        
        if (isset($_SERVER['HTTP_USER_AGENT'])) {
            $ua = $_SERVER['HTTP_USER_AGENT'];
            if (preg_match('/(Chrome\/[3-9]\d|Chrome\/\d{3,}|OPR\/[2-9]\d|OPR\/\d{3,}|Firefox\/6[5-9]|Firefox\/[7-9]\d|Firefox\/\d{3,})/', $ua)) {
                return true;
            }
            if (preg_match('/Edge\/[1-9]\d|Edge\/\d{3,}/', $ua)) {
                return true;
            }
        }
        
        return false;
    }
}

function kloudwebp_get_webp_path($file_path) {
    $webp_path = preg_replace('/\.(jpe?g|png)$/i', '.webp', $file_path);
    return file_exists($webp_path) ? $webp_path : false;
}

function kloudwebp_get_webp_url($url) {
    $file_path = str_replace(wp_get_upload_dir()['baseurl'], wp_get_upload_dir()['basedir'], $url);
    $webp_path = kloudwebp_get_webp_path($file_path);
    return $webp_path ? preg_replace('/\.(jpe?g|png)$/i', '.webp', $url) : $url;
}

// Image Modification Functions
function kloudwebp_mime_types($mimes) {
    $mimes['webp'] = 'image/webp';
    return $mimes;
}

function kloudwebp_modify_image_url($url, $attachment_id) {
    if (!kloudwebp_browser_supports_webp()) {
        return $url;
    }
    
    $metadata = wp_get_attachment_metadata($attachment_id);
    if (!empty($metadata['webp_url'])) {
        return $metadata['webp_url'];
    }
    
    return $url;
}

function kloudwebp_modify_image_src($image, $attachment_id, $size, $icon) {
    if (!$image || !kloudwebp_browser_supports_webp()) {
        return $image;
    }
    
    $metadata = wp_get_attachment_metadata($attachment_id);
    
    if (!empty($metadata['webp_url'])) {
        $image[0] = $metadata['webp_url'];
    }
    
    if (!empty($size) && !empty($metadata['sizes'][$size]['webp_url'])) {
        $image[0] = $metadata['sizes'][$size]['webp_url'];
    }
    
    return $image;
}

function kloudwebp_modify_image_srcset($sources, $size_array, $image_src, $image_meta, $attachment_id) {
    if (!kloudwebp_browser_supports_webp()) {
        return $sources;
    }
    
    foreach ($sources as &$source) {
        $url = $source['url'];
        $webp_url = kloudwebp_get_webp_url($url);
        if ($webp_url !== $url) {
            $source['url'] = $webp_url;
        }
    }
    
    return $sources;
}

function kloudwebp_modify_image_attributes($attr, $attachment, $size) {
    if (!kloudwebp_browser_supports_webp()) {
        return $attr;
    }

    $metadata = wp_get_attachment_metadata($attachment->ID);
    
    if (!empty($metadata['webp_url'])) {
        $attr['src'] = $metadata['webp_url'];
    }

    if (!empty($attr['srcset'])) {
        $srcset_urls = explode(', ', $attr['srcset']);
        $new_srcset_urls = array();
        
        foreach ($srcset_urls as $srcset_url) {
            list($url, $descriptor) = explode(' ', $srcset_url);
            $webp_url = kloudwebp_get_webp_url($url);
            if ($webp_url !== $url) {
                $new_srcset_urls[] = $webp_url . ' ' . $descriptor;
                continue;
            }
            $new_srcset_urls[] = $srcset_url;
        }
        
        $attr['srcset'] = implode(', ', $new_srcset_urls);
    }

    return $attr;
}

// Image Processing Functions
function kloudwebp_convert_image($file_path) {
    try {
        // Skip if the file is already a WebP image
        if (preg_match('/\.webp$/i', $file_path)) {
            kloudwebp_log_debug("Skipping WebP image: " . $file_path);
            return true;
        }

        // Check if file exists
        if (!file_exists($file_path)) {
            throw new Exception("File does not exist: " . $file_path);
        }

        // Check file size
        $file_size = filesize($file_path);
        if ($file_size > KLOUDWEBP_MAX_IMAGE_SIZE) {
            throw new Exception("File size exceeds maximum limit: " . $file_size . " bytes");
        }

        // Check memory limit
        if (!kloudwebp_check_memory_limit($file_path)) {
            throw new Exception("Insufficient memory to process image: " . $file_path);
        }

        // Check if the file is a valid image
        $mime_type = wp_check_filetype($file_path)['type'];
        if (!in_array($mime_type, array('image/jpeg', 'image/png'))) {
            throw new Exception("Unsupported image type: " . $mime_type);
        }

        $webp_path = preg_replace('/\.(jpe?g|png)$/i', '.webp', $file_path);
        
        // Skip if WebP version already exists and is valid
        if (file_exists($webp_path) && filesize($webp_path) > 0) {
            kloudwebp_log_debug("WebP version already exists: " . $webp_path);
            return true;
        }

        $options = get_option('kloudwebp_options', array('conversion_quality' => 80));
        $quality = isset($options['conversion_quality']) ? intval($options['conversion_quality']) : 80;
        $quality = min(max($quality, 1), 100);

        $result = false;
        
        // Try Imagick first
        if (extension_loaded('imagick')) {
            kloudwebp_log_debug("Attempting Imagick conversion for: " . $file_path);
            $result = kloudwebp_convert_with_imagick($file_path, $webp_path, $quality);
        }
        
        // Fallback to GD if Imagick fails
        if (!$result && extension_loaded('gd')) {
            kloudwebp_log_debug("Attempting GD conversion for: " . $file_path);
            $result = kloudwebp_convert_with_gd($file_path, $webp_path, $quality);
        }

        if (!$result) {
            throw new Exception("All conversion methods failed for: " . $file_path);
        }

        return $result;

    } catch (Exception $e) {
        kloudwebp_log_error("Error converting image: " . $e->getMessage());
        return false;
    }
}

function kloudwebp_convert_with_imagick($file_path, $webp_path, $quality) {
    try {
        $image = new Imagick($file_path);
        $image->setImageFormat('webp');
        $image->setImageCompressionQuality($quality);
        $success = $image->writeImage($webp_path);
        $image->destroy();
        return $success;
    } catch (Exception $e) {
        kloudwebp_log_error("Imagick conversion error: " . $e->getMessage());
        return false;
    }
}

function kloudwebp_convert_with_gd($file_path, $webp_path, $quality) {
    try {
        $image = null;
        $mime_type = wp_check_filetype($file_path)['type'];

        // Suppress libpng warnings
        if (function_exists('error_clear_last')) {
            error_clear_last();
        }
        
        switch ($mime_type) {
            case 'image/jpeg':
                $image = @imagecreatefromjpeg($file_path);
                break;
            case 'image/png':
                // Handle PNG with proper color management
                $image = @imagecreatefrompng($file_path);
                if ($image) {
                    // Convert to true color if needed
                    if (!imageistruecolor($image)) {
                        imagepalettetotruecolor($image);
                    }
                    
                    // Handle transparency
                    imagealphablending($image, false);
                    imagesavealpha($image, true);
                    
                    // Remove color profile information
                    imagecolorstotal($image);
                }
                break;
            default:
                throw new Exception("Unsupported image type: " . $mime_type);
        }

        if (!$image) {
            $error = error_get_last();
            throw new Exception("Failed to create image resource. Error: " . ($error ? $error['message'] : 'Unknown error'));
        }

        // Set the background color to white for JPEGs
        if ($mime_type === 'image/jpeg') {
            $background = imagecolorallocate($image, 255, 255, 255);
            imagefill($image, 0, 0, $background);
        }

        // Create WebP with proper error handling
        if (!@imagewebp($image, $webp_path, $quality)) {
            $error = error_get_last();
            throw new Exception("Failed to save WebP image. Error: " . ($error ? $error['message'] : 'Unknown error'));
        }

        imagedestroy($image);
        
        // Verify the WebP file was created successfully
        if (!file_exists($webp_path) || filesize($webp_path) === 0) {
            throw new Exception("WebP file creation failed or file is empty");
        }

        return true;

    } catch (Exception $e) {
        kloudwebp_log_error("GD conversion error: " . $e->getMessage());
        if (isset($image) && is_resource($image)) {
            imagedestroy($image);
        }
        return false;
    }
}

// Admin Interface Functions
function kloudwebp_admin_menu() {
    add_options_page(
        __('KloudWebP Settings', 'kloudwebp'),
        __('KloudWebP', 'kloudwebp'),
        'manage_options',
        'kloudwebp',
        'kloudwebp_settings_page'
    );
}

function kloudwebp_register_settings() {
    register_setting('kloudwebp_options_group', 'kloudwebp_options', 'kloudwebp_sanitize_options');
    
    add_settings_section(
        'kloudwebp_main_section',
        __('Conversion Settings', 'kloudwebp'),
        null,
        'kloudwebp'
    );

    add_settings_field(
        'conversion_quality',
        __('WebP Quality', 'kloudwebp'),
        'kloudwebp_quality_callback',
        'kloudwebp',
        'kloudwebp_main_section'
    );

    add_settings_field(
        'replace_original',
        __('Replace Original Images', 'kloudwebp'),
        'kloudwebp_replace_callback',
        'kloudwebp',
        'kloudwebp_main_section'
    );

    // Add new optimization options
    add_settings_field(
        'auto_convert',
        __('Auto Convert New Uploads', 'kloudwebp'),
        'kloudwebp_auto_convert_callback',
        'kloudwebp',
        'kloudwebp_main_section'
    );

    add_settings_field(
        'optimize_original',
        __('Optimize Original Images', 'kloudwebp'),
        'kloudwebp_optimize_original_callback',
        'kloudwebp',
        'kloudwebp_main_section'
    );

    add_settings_field(
        'max_width',
        __('Maximum Image Width', 'kloudwebp'),
        'kloudwebp_max_width_callback',
        'kloudwebp',
        'kloudwebp_main_section'
    );

    // Display supported image processing libraries
    add_settings_section(
        'kloudwebp_server_support_section',
        __('Server Support', 'kloudwebp'),
        null,
        'kloudwebp'
    );

    add_settings_field(
        'server_support',
        __('Supported Image Processing Libraries', 'kloudwebp'),
        'kloudwebp_server_support_callback',
        'kloudwebp',
        'kloudwebp_server_support_section'
    );
}

function kloudwebp_sanitize_options($options) {
    if (!is_array($options)) {
        return array();
    }
    
    $sanitized = array();
    
    // Sanitize quality (0-100)
    $sanitized['conversion_quality'] = isset($options['conversion_quality']) 
        ? min(100, max(0, intval($options['conversion_quality'])))
        : 80;
    
    // Sanitize boolean options
    $sanitized['replace_original'] = isset($options['replace_original']) ? (bool)$options['replace_original'] : false;
    $sanitized['auto_convert'] = isset($options['auto_convert']) ? (bool)$options['auto_convert'] : false;
    $sanitized['optimize_original'] = isset($options['optimize_original']) ? (bool)$options['optimize_original'] : false;
    
    // Sanitize max width
    $sanitized['max_width'] = isset($options['max_width']) 
        ? max(0, intval($options['max_width']))
        : 2048;
    
    return $sanitized;
}

function kloudwebp_quality_callback() {
    $options = get_option('kloudwebp_options', array('conversion_quality' => 80));
    $quality = isset($options['conversion_quality']) ? $options['conversion_quality'] : 80;
    echo "<input type='number' name='kloudwebp_options[conversion_quality]' value='" . esc_attr($quality) . "' min='0' max='100' class='small-text' /> %";
    echo "<p class='description'>" . __('Set the quality of WebP conversion (0-100). Higher values mean better quality but larger file size.', 'kloudwebp') . "</p>";
}

function kloudwebp_replace_callback() {
    $options = get_option('kloudwebp_options', array('replace_original' => false));
    $checked = isset($options['replace_original']) && $options['replace_original'] ? 'checked' : '';
    echo "<input type='checkbox' name='kloudwebp_options[replace_original]' " . $checked . " />";
    echo "<p class='description'>" . __('If checked, original images will be replaced with WebP versions. Otherwise, both versions will be kept.', 'kloudwebp') . "</p>";
}

function kloudwebp_auto_convert_callback() {
    $options = get_option('kloudwebp_options');
    $checked = isset($options['auto_convert']) && $options['auto_convert'] ? 'checked' : '';
    echo "<input type='checkbox' name='kloudwebp_options[auto_convert]' {$checked} />";
    echo "<p class='description'>" . __('Automatically convert new image uploads to WebP format.', 'kloudwebp') . "</p>";
}

function kloudwebp_optimize_original_callback() {
    $options = get_option('kloudwebp_options');
    $checked = isset($options['optimize_original']) && $options['optimize_original'] ? 'checked' : '';
    echo "<input type='checkbox' name='kloudwebp_options[optimize_original]' {$checked} />";
    echo "<p class='description'>" . __('Apply lossless optimization to original images before conversion.', 'kloudwebp') . "</p>";
}

function kloudwebp_max_width_callback() {
    $options = get_option('kloudwebp_options');
    $max_width = isset($options['max_width']) ? intval($options['max_width']) : 2048;
    echo "<input type='number' name='kloudwebp_options[max_width]' value='{$max_width}' min='0' step='1' class='small-text' /> px";
    echo "<p class='description'>" . __('Resize large images to this maximum width. Set to 0 to disable resizing.', 'kloudwebp') . "</p>";
}

function kloudwebp_server_support_callback() {
    $support = kloudwebp_check_server_support();
    if (empty($support)) {
        echo '<p>No supported image processing libraries (Imagick or GD) are available on this server.</p>';
    } else {
        echo '<p>Supported image processing libraries on this server: ' . implode(', ', $support) . '.</p>';
    }
}

function kloudwebp_settings_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    $stats = kloudwebp_get_stats();
    ?>
    <div class="wrap kloudwebp-wrap">
        <div class="kloudwebp-header">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        </div>

        <div class="kloudwebp-stats">
            <div class="kloudwebp-stat-card">
                <h3><?php _e('Total Images', 'kloudwebp'); ?></h3>
                <div class="number"><?php echo esc_html($stats['total']); ?></div>
            </div>
            <div class="kloudwebp-stat-card">
                <h3><?php _e('Converted', 'kloudwebp'); ?></h3>
                <div class="number"><?php echo esc_html($stats['converted']); ?></div>
            </div>
            <div class="kloudwebp-stat-card">
                <h3><?php _e('Space Saved', 'kloudwebp'); ?></h3>
                <div class="number"><?php echo esc_html($stats['saved']); ?></div>
            </div>
        </div>

        <div class="kloudwebp-settings">
            <?php settings_errors('kloudwebp_messages'); ?>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('kloudwebp_options_group');
                do_settings_sections('kloudwebp');
                submit_button(__('Save Settings', 'kloudwebp'));
                ?>
            </form>

            <hr>

            <h2><?php _e('Bulk Convert Images', 'kloudwebp'); ?></h2>
            <p><?php _e('Convert all existing images in your media library to WebP format.', 'kloudwebp'); ?></p>
            
            <div class="kloudwebp-actions">
                <button type="button" id="convert-images" class="button button-primary kloudwebp-button">
                    <?php _e('Start Conversion', 'kloudwebp'); ?>
                </button>
                <button type="button" id="regenerate-thumbnails" class="button kloudwebp-button">
                    <?php _e('Regenerate Thumbnails', 'kloudwebp'); ?>
                </button>
                <button type="button" id="clear-cache" class="button kloudwebp-button">
                    <?php _e('Clear Cache', 'kloudwebp'); ?>
                </button>
                <button type="button" id="cleanup-files" class="button kloudwebp-button">
                    <?php _e('Cleanup Files', 'kloudwebp'); ?>
                </button>
            </div>

            <div id="conversion-progress" style="display: none;">
                <div class="kloudwebp-progress">
                    <div class="kloudwebp-progress-bar"></div>
                </div>
                <div class="kloudwebp-status"></div>
            </div>

            <div id="conversion-log" class="kloudwebp-log" style="display: none;"></div>

            <div class="kloudwebp-optimization-summary">
                <h3><?php _e('Optimization Summary', 'kloudwebp'); ?></h3>
                <table>
                    <tr>
                        <th><?php _e('Original Size', 'kloudwebp'); ?></th>
                        <td id="original-size">-</td>
                    </tr>
                    <tr>
                        <th><?php _e('Converted Size', 'kloudwebp'); ?></th>
                        <td id="converted-size">-</td>
                    </tr>
                    <tr>
                        <th><?php _e('Space Saved', 'kloudwebp'); ?></th>
                        <td id="space-saved">-</td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
    <?php
}

function kloudwebp_get_stats() {
    $attachments = wp_count_attachments();
    $total = 0;
    
    // Count total images
    foreach (array('image/jpeg', 'image/png') as $mime_type) {
        if (isset($attachments->{$mime_type})) {
            $total += (int)$attachments->{$mime_type};
        }
    }
    
    $converted = get_option('kloudwebp_converted_count', 0);
    $saved = size_format(get_option('kloudwebp_space_saved', 0), 2);

    return array(
        'total' => $total,
        'converted' => $converted,
        'saved' => $saved
    );
}

function kloudwebp_admin_enqueue_scripts($hook) {
    if ('settings_page_kloudwebp' !== $hook) {
        return;
    }

    wp_enqueue_style('kloudwebp-admin', plugins_url('css/admin.css', __FILE__), array(), KLOUDWEBP_VERSION);
    wp_enqueue_script('kloudwebp-admin', plugins_url('js/admin.js', __FILE__), array('jquery'), KLOUDWEBP_VERSION, true);
    
    wp_localize_script('kloudwebp-admin', 'kloudwebpAjax', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('kloudwebp_convert'),
        'converting' => __('Converting...', 'kloudwebp'),
        'complete' => __('Conversion complete!', 'kloudwebp'),
        'error' => __('Error occurred during conversion.', 'kloudwebp')
    ));
}

// AJAX Handlers
add_action('wp_ajax_kloudwebp_get_progress', 'kloudwebp_ajax_get_progress');
add_action('wp_ajax_kloudwebp_clear_cache', 'kloudwebp_ajax_clear_cache');
add_action('wp_ajax_kloudwebp_regenerate_thumbnails', 'kloudwebp_ajax_regenerate_thumbnails');
add_action('wp_ajax_kloudwebp_process_batch', 'kloudwebp_process_batch');
add_action('wp_ajax_kloudwebp_cleanup_files', 'kloudwebp_cleanup_files');

function kloudwebp_ajax_get_progress() {
    check_ajax_referer('kloudwebp_convert', 'nonce');
    
    $progress = get_option('kloudwebp_conversion_progress', array(
        'current' => 0,
        'total' => 0,
        'status' => ''
    ));
    
    wp_send_json_success($progress);
}

function kloudwebp_ajax_clear_cache() {
    check_ajax_referer('kloudwebp_convert', 'nonce');
    
    // Clear plugin-specific cache
    delete_option('kloudwebp_converted_count');
    delete_option('kloudwebp_space_saved');
    delete_option('kloudwebp_conversion_progress');
    
    // Clear WordPress cache
    wp_cache_flush();
    
    wp_send_json_success(array(
        'message' => __('Cache cleared successfully', 'kloudwebp')
    ));
}

function kloudwebp_ajax_regenerate_thumbnails() {
    check_ajax_referer('kloudwebp_convert', 'nonce');
    
    $attachments = get_posts(array(
        'post_type' => 'attachment',
        'post_mime_type' => array('image/jpeg', 'image/png', 'image/webp'),
        'posts_per_page' => -1,
        'fields' => 'ids'
    ));
    
    foreach ($attachments as $attachment_id) {
        wp_update_attachment_metadata($attachment_id, wp_generate_attachment_metadata($attachment_id, get_attached_file($attachment_id)));
    }
    
    wp_send_json_success(array(
        'message' => __('Thumbnails regenerated successfully', 'kloudwebp')
    ));
}

function kloudwebp_process_batch() {
    try {
        check_ajax_referer('kloudwebp_convert', 'nonce');
        
        if (!current_user_can('manage_options')) {
            throw new Exception("Unauthorized access");
        }

        $options = get_option('kloudwebp_options');
        $batch_size = KLOUDWEBP_CHUNK_SIZE;
        $processed = 0;
        $success = 0;
        $errors = 0;

        // Get images to process
        $images = kloudwebp_get_unprocessed_images($batch_size);
        
        foreach ($images as $image) {
            $processed++;
            
            try {
                // Process main image
                $file_path = get_attached_file($image->ID);
                if (kloudwebp_convert_image($file_path)) {
                    $success++;
                    
                    // Process thumbnails if enabled
                    if (!empty($options['convert_thumbnails']) && $options['convert_thumbnails'] === 'yes') {
                        $metadata = wp_get_attachment_metadata($image->ID);
                        if (!empty($metadata['sizes'])) {
                            $upload_dir = wp_upload_dir();
                            $base_dir = dirname($file_path);
                            
                            foreach ($metadata['sizes'] as $size => $size_data) {
                                $thumb_path = $base_dir . '/' . $size_data['file'];
                                kloudwebp_convert_image($thumb_path);
                            }
                        }
                    }
                } else {
                    $errors++;
                }
            } catch (Exception $e) {
                $errors++;
                kloudwebp_log_error("Error processing image ID {$image->ID}: " . $e->getMessage());
                continue;
            }
        }

        // Update progress
        $total = wp_count_posts('attachment')->inherit;
        $progress = ($processed / $total) * 100;

        wp_send_json_success(array(
            'processed' => $processed,
            'success' => $success,
            'errors' => $errors,
            'progress' => round($progress, 2),
            'total' => $total
        ));

    } catch (Exception $e) {
        kloudwebp_log_error("Batch processing error: " . $e->getMessage());
        wp_send_json_error($e->getMessage());
    }
}

// Helper function to get unprocessed images
function kloudwebp_get_unprocessed_images($limit) {
    global $wpdb;
    
    $processed_ids = get_option('kloudwebp_processed_images', array());
    $processed_ids = array_map('absint', $processed_ids);
    
    if (empty($processed_ids)) {
        // If no processed IDs, do not include the NOT IN clause
        $query = $wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} 
            WHERE post_type = 'attachment' 
            AND post_mime_type IN ('image/jpeg', 'image/png') 
            LIMIT %d",
            $limit
        );
    } else {
        // Include NOT IN clause if there are processed IDs
        $query = $wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} 
            WHERE post_type = 'attachment' 
            AND post_mime_type IN ('image/jpeg', 'image/png') 
            AND ID NOT IN (" . implode(',', array_fill(0, count($processed_ids), '%d')) . ")
            LIMIT %d",
            array_merge($processed_ids, array($limit))
        );
    }
    
    return $wpdb->get_results($query);
}

function kloudwebp_cleanup_files() {
    try {
        check_ajax_referer('kloudwebp_convert', 'nonce');
        
        if (!current_user_can('manage_options')) {
            throw new Exception("Unauthorized access");
        }

        $upload_dir = wp_upload_dir();
        $base_dir = $upload_dir['basedir'];
        
        // Get all WebP files
        $webp_files = glob($base_dir . '/**/*.webp', GLOB_NOSORT);
        $deleted = 0;
        $errors = 0;
        
        foreach ($webp_files as $webp_file) {
            // Get original file path
            $original_file = preg_replace('/\.webp$/', '', $webp_file);
            $original_file = preg_replace('/(\.(jpe?g|png))\.webp$/', '$1', $original_file);
            
            // Check if original exists
            if (!file_exists($original_file)) {
                if (@unlink($webp_file)) {
                    $deleted++;
                } else {
                    $errors++;
                    kloudwebp_log_error("Failed to delete orphaned WebP file: " . $webp_file);
                }
            }
        }
        
        wp_send_json_success(array(
            'message' => sprintf(
                __('Cleanup completed. Deleted %d orphaned WebP files. %d errors occurred.', 'kloudwebp'),
                $deleted,
                $errors
            )
        ));
        
    } catch (Exception $e) {
        kloudwebp_log_error("Cleanup error: " . $e->getMessage());
        wp_send_json_error($e->getMessage());
    }
}

// Utility Functions
function kloudwebp_scan_media_library() {
    $args = array(
        'post_type' => 'attachment',
        'post_mime_type' => array('image/jpeg', 'image/png'),
        'post_status' => 'inherit',
        'posts_per_page' => -1,
    );
    return get_posts($args);
}

function kloudwebp_process_images() {
    $images = kloudwebp_scan_media_library();
    $results = array(
        'success' => 0,
        'failed' => 0,
        'total' => count($images)
    );

    foreach ($images as $image) {
        $image_path = get_attached_file($image->ID);
        $webp_path = kloudwebp_convert_image($image_path);
        
        if ($webp_path) {
            $options = get_option('kloudwebp_options');
            if (isset($options['replace_original']) && $options['replace_original']) {
                if (update_attached_file($image->ID, $webp_path)) {
                    $results['success']++;
                } else {
                    $results['failed']++;
                }
            } else {
                $results['success']++;
            }
        } else {
            $results['failed']++;
            kloudwebp_log_error("Failed to convert image ID {$image->ID}");
        }
    }

    return $results;
}

function kloudwebp_log_error($message) {
    if (WP_DEBUG) {
        error_log('[KloudWebP] ' . $message);
    }
}

function kloudwebp_displayable_image($result, $path) {
    if (preg_match('/\.webp$/i', $path)) {
        return true;
    }
    return $result;
}

function kloudwebp_replace_content_images($content) {
    if (empty($content)) {
        return $content;
    }

    // Use proper HTML encoding
    $content = mb_encode_numericentity(htmlspecialchars_decode($content), [0x80, 0x10FFFF, 0, ~0], 'UTF-8');
    
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML($content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();

    $images = $dom->getElementsByTagName('img');
    $modified = false;

    foreach ($images as $img) {
        $src = $img->getAttribute('src');
        if (preg_match('/\.(jpe?g|png)$/i', $src)) {
            $webp_src = preg_replace('/\.(jpe?g|png)$/i', '.webp', $src);
            $webp_path = str_replace(wp_get_upload_dir()['baseurl'], wp_get_upload_dir()['basedir'], $webp_src);
            
            if (file_exists($webp_path)) {
                $img->setAttribute('src', $webp_src);
                $modified = true;
            }
        }
    }

    if ($modified) {
        $content = $dom->saveHTML();
    }

    return $content;
}

function kloudwebp_update_attachment_metadata($metadata, $attachment_id) {
    if (!is_array($metadata)) {
        return $metadata;
    }

    $file = get_attached_file($attachment_id);
    if (!$file) {
        return $metadata;
    }

    $webp_path = preg_replace('/\.(jpe?g|png)$/i', '.webp', $file);
    if (file_exists($webp_path)) {
        $metadata['webp_path'] = $webp_path;
        $metadata['webp_url'] = preg_replace('/\.(jpe?g|png)$/i', '.webp', wp_get_attachment_url($attachment_id));
        $metadata['webp_size'] = filesize($webp_path);
        
        // Add WebP versions for all image sizes
        if (!empty($metadata['sizes'])) {
            foreach ($metadata['sizes'] as $size => $size_data) {
                $size_file = path_join(dirname($file), $size_data['file']);
                $size_webp = preg_replace('/\.(jpe?g|png)$/i', '.webp', $size_file);
                if (file_exists($size_webp)) {
                    $metadata['sizes'][$size]['webp_path'] = $size_webp;
                    $metadata['sizes'][$size]['webp_url'] = preg_replace('/\.(jpe?g|png)$/i', '.webp', dirname($metadata['webp_url']) . '/' . basename($size_data['file']));
                }
            }
        }
    }

    return $metadata;
}

function kloudwebp_handle_upload($file) {
    $options = get_option('kloudwebp_options');
    
    if (!isset($options['auto_convert']) || !$options['auto_convert']) {
        return $file;
    }
    
    if (!in_array($file['type'], array('image/jpeg', 'image/png'))) {
        return $file;
    }
    
    // Optimize original if enabled
    if (isset($options['optimize_original']) && $options['optimize_original']) {
        kloudwebp_optimize_image_advanced($file['file']);
    }
    
    // Resize if needed
    if (isset($options['max_width']) && $options['max_width'] > 0) {
        kloudwebp_resize_image($file['file'], $options['max_width']);
    }
    
    // Convert to WebP
    $webp_path = kloudwebp_convert_image($file['file']);
    
    if ($webp_path && isset($options['replace_original']) && $options['replace_original']) {
        unlink($file['file']);
        $file['file'] = $webp_path;
        $file['type'] = 'image/webp';
    }
    
    return $file;
}

// Utility functions for memory management
function kloudwebp_check_memory_limit($file_path) {
    if (!file_exists($file_path)) {
        return false;
    }

    // Get memory limit in bytes
    $memory_limit = wp_convert_hr_to_bytes(@ini_get('memory_limit'));
    
    // Get image size
    list($width, $height) = getimagesize($file_path);
    
    // Calculate required memory (width * height * channels * bits per channel)
    $required_memory = $width * $height * 4 * 1.5; // 4 channels (RGBA), 1.5 safety factor
    
    // Get available memory
    $available_memory = $memory_limit - memory_get_usage();
    
    return $required_memory < $available_memory;
}

// Helper function to convert PHP memory limit to bytes
if (!function_exists('wp_convert_hr_to_bytes')) {
    function wp_convert_hr_to_bytes($size) {
        $value = trim($size);
        $last = strtolower($value[strlen($value)-1]);
        switch($last) {
            case 'g':
                $value *= 1024;
            case 'm':
                $value *= 1024;
            case 'k':
                $value *= 1024;
        }
        return $value;
    }
}

// Hook to automatically convert new image uploads to WebP format
add_action('add_attachment', 'kloudwebp_convert_new_upload');

if (!function_exists('kloudwebp_convert_new_upload')) {
    function kloudwebp_convert_new_upload($post_ID) {
        $file_path = get_attached_file($post_ID);
        $mime_type = get_post_mime_type($post_ID);

        // Only process JPEG and PNG images
        if (in_array($mime_type, ['image/jpeg', 'image/png'])) {
            kloudwebp_convert_image($file_path);
        }
    }
}

// Function to check available image processing libraries
if (!function_exists('kloudwebp_check_server_support')) {
    function kloudwebp_check_server_support() {
        $support = [];

        // Check Imagick support
        if (extension_loaded('imagick')) {
            $imagick = new Imagick();
            if (in_array('WEBP', $imagick->queryFormats('WEBP'))) {
                $support[] = 'Imagick';
            }
        }

        // Check GD support
        if (extension_loaded('gd') && function_exists('imagewebp')) {
            $support[] = 'GD';
        }

        return $support;
    }
}

// Display server support information in the admin area
add_action('admin_notices', 'kloudwebp_display_server_support');

if (!function_exists('kloudwebp_display_server_support')) {
    function kloudwebp_display_server_support() {
        $support = kloudwebp_check_server_support();
        if (empty($support)) {
            echo '<div class="notice notice-error"><p>KloudWebP: No supported image processing libraries (Imagick or GD) are available on this server.</p></div>';
        } else {
            echo '<div class="notice notice-success"><p>KloudWebP: Supported image processing libraries on this server: ' . implode(', ', $support) . '.</p></div>';
        }
    }
}

// Initialize Plugin
if (!function_exists('kloudwebp_init')) {
    function kloudwebp_init() {
        // Remove all existing filters to prevent duplicates
        remove_all_filters('upload_mimes');
        remove_all_filters('wp_handle_upload');
        
        // Add filters only once
        add_filter('upload_mimes', 'kloudwebp_mime_types');
        add_filter('wp_handle_upload', 'kloudwebp_handle_upload');
        
        // Register the admin menu and settings
        add_action('admin_menu', 'kloudwebp_admin_menu');
        add_action('admin_init', 'kloudwebp_register_settings');
        
        // Register AJAX handlers
        add_action('wp_ajax_kloudwebp_convert_images', 'kloudwebp_ajax_convert_images');
        add_action('wp_ajax_kloudwebp_get_progress', 'kloudwebp_ajax_get_progress');
        add_action('wp_ajax_kloudwebp_clear_cache', 'kloudwebp_ajax_clear_cache');
        
        // Hook for new uploads
        add_action('add_attachment', 'kloudwebp_convert_new_upload');
    }
}

// Function to handle new uploads
if (!function_exists('kloudwebp_handle_upload')) {
    function kloudwebp_handle_upload($file) {
        try {
            if (!isset($file['type']) || !in_array($file['type'], array('image/jpeg', 'image/png'))) {
                return $file;
            }

            $options = get_option('kloudwebp_options', array(
                'conversion_quality' => 80,
                'auto_convert' => 'yes'
            ));

            if ($options['auto_convert'] !== 'yes') {
                return $file;
            }

            kloudwebp_log_debug("Processing new upload: " . $file['file']);
            
            if (kloudwebp_convert_image($file['file'])) {
                kloudwebp_log_debug("Successfully converted: " . $file['file']);
            } else {
                kloudwebp_log_error("Failed to convert: " . $file['file']);
            }

            return $file;
        } catch (Exception $e) {
            kloudwebp_log_error("Error in upload handler: " . $e->getMessage());
            return $file;
        }
    }
}

// Function to convert new uploads
if (!function_exists('kloudwebp_convert_new_upload')) {
    function kloudwebp_convert_new_upload($post_ID) {
        try {
            $file = get_attached_file($post_ID);
            $mime_type = get_post_mime_type($post_ID);

            if (!in_array($mime_type, array('image/jpeg', 'image/png'))) {
                return;
            }

            $options = get_option('kloudwebp_options', array(
                'conversion_quality' => 80,
                'auto_convert' => 'yes'
            ));

            if ($options['auto_convert'] !== 'yes') {
                return;
            }

            kloudwebp_log_debug("Processing new attachment: " . $file);
            
            if (kloudwebp_convert_image($file)) {
                kloudwebp_log_debug("Successfully converted attachment: " . $file);
                
                // Update attachment metadata
                $metadata = wp_get_attachment_metadata($post_ID);
                if ($metadata) {
                    $metadata['webp_converted'] = true;
                    wp_update_attachment_metadata($post_ID, $metadata);
                }
            } else {
                kloudwebp_log_error("Failed to convert attachment: " . $file);
            }
        } catch (Exception $e) {
            kloudwebp_log_error("Error in new upload conversion: " . $e->getMessage());
        }
    }
}

// Plugin Lifecycle Hooks
register_activation_hook(__FILE__, 'kloudwebp_activate');
register_deactivation_hook(__FILE__, 'kloudwebp_deactivate');
add_action('init', 'kloudwebp_init');
add_action('admin_enqueue_scripts', 'kloudwebp_admin_enqueue_scripts');

// Register AJAX handlers
add_action('wp_ajax_kloudwebp_convert_images', 'kloudwebp_ajax_convert_images');
add_action('wp_ajax_kloudwebp_get_progress', 'kloudwebp_ajax_get_progress');
add_action('wp_ajax_kloudwebp_clear_cache', 'kloudwebp_ajax_clear_cache');
add_action('wp_ajax_kloudwebp_regenerate_thumbnails', 'kloudwebp_ajax_regenerate_thumbnails');

function kloudwebp_activate() {
    // Set default options
    $default_options = array(
        'conversion_quality' => 80,
        'replace_original' => false,
        'auto_convert' => false,
        'optimize_original' => false,
        'max_width' => 2048
    );
    
    add_option('kloudwebp_options', $default_options);
}

function kloudwebp_deactivate() {
    // Cleanup if needed
}

function kloudwebp_ajax_convert_images() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

    $results = kloudwebp_process_images();
    wp_send_json_success($results);
}
