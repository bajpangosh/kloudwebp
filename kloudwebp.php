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

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Define plugin constants
define('KLOUDWEBP_VERSION', '1.0.0');
define('KLOUDWEBP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('KLOUDWEBP_PLUGIN_URL', plugin_dir_url(__FILE__));

function kloudwebp_init() {
    // Register the admin settings page
    add_action('admin_menu', 'kloudwebp_admin_menu');
    add_action('admin_init', 'kloudwebp_register_settings');
}

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
add_action('admin_enqueue_scripts', 'kloudwebp_admin_enqueue_scripts');

// Add AJAX handlers for the new features
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
    check_ajax_referer('kloudwebp_convert', 'nonce');
    
    $batch_size = 5; // Number of images to process per batch
    $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
    
    $args = array(
        'post_type' => 'attachment',
        'post_mime_type' => array('image/jpeg', 'image/png'),
        'posts_per_page' => $batch_size,
        'offset' => $offset,
        'orderby' => 'ID',
        'order' => 'ASC',
        'meta_query' => array(
            'relation' => 'OR',
            array(
                'key' => '_webp_converted',
                'compare' => 'NOT EXISTS'
            ),
            array(
                'key' => '_webp_converted',
                'value' => '0'
            )
        )
    );
    
    $attachments = get_posts($args);
    
    // Get total count of unconverted images
    $total_args = array(
        'post_type' => 'attachment',
        'post_mime_type' => array('image/jpeg', 'image/png'),
        'posts_per_page' => -1,
        'fields' => 'ids',
        'meta_query' => array(
            'relation' => 'OR',
            array(
                'key' => '_webp_converted',
                'compare' => 'NOT EXISTS'
            ),
            array(
                'key' => '_webp_converted',
                'value' => '0'
            )
        )
    );
    $total_attachments = get_posts($total_args);
    $total = count($total_attachments);
    
    $results = array(
        'success' => 0,
        'failed' => 0,
        'skipped' => 0,
        'total_size_before' => 0,
        'total_size_after' => 0
    );
    
    foreach ($attachments as $attachment) {
        $file_path = get_attached_file($attachment->ID);
        
        // Skip if file doesn't exist or is already WebP
        if (!$file_path || !file_exists($file_path) || preg_match('/\.webp$/i', $file_path)) {
            $results['skipped']++;
            continue;
        }
        
        // Get mime type
        $mime_type = get_post_mime_type($attachment->ID);
        if (!in_array($mime_type, array('image/jpeg', 'image/png'))) {
            $results['skipped']++;
            continue;
        }
        
        $original_size = filesize($file_path);
        $results['total_size_before'] += $original_size;
        
        // Process image
        $webp_path = kloudwebp_convert_image($file_path);
        
        if ($webp_path && file_exists($webp_path)) {
            $new_size = filesize($webp_path);
            $results['total_size_after'] += $new_size;
            $results['success']++;
            
            // Update attachment metadata
            $metadata = wp_get_attachment_metadata($attachment->ID);
            if (!is_array($metadata)) {
                $metadata = array();
            }
            $metadata['webp_path'] = $webp_path;
            $metadata['original_size'] = $original_size;
            $metadata['webp_size'] = $new_size;
            wp_update_attachment_metadata($attachment->ID, $metadata);
            
            // Mark as converted
            update_post_meta($attachment->ID, '_webp_converted', '1');
            
            // Update conversion statistics
            $saved_space = get_option('kloudwebp_space_saved', 0);
            update_option('kloudwebp_space_saved', $saved_space + ($original_size - $new_size));
            
            // Update converted count
            $converted_count = get_option('kloudwebp_converted_count', 0);
            update_option('kloudwebp_converted_count', $converted_count + 1);
        } else {
            $results['failed']++;
            $results['total_size_after'] += $original_size;
            update_post_meta($attachment->ID, '_webp_converted', '0');
        }
    }
    
    // Update progress
    if ($total > 0) {
        $progress = array(
            'current' => $offset + count($attachments),
            'total' => $total,
            'status' => sprintf(
                __('Processed %1$d of %2$d images (%3$d%%) - Success: %4$d, Failed: %5$d, Skipped: %6$d', 'kloudwebp'),
                $offset + count($attachments),
                $total,
                round(($offset + count($attachments)) / $total * 100),
                $results['success'],
                $results['failed'],
                $results['skipped']
            )
        );
    } else {
        $progress = array(
            'current' => 0,
            'total' => 0,
            'status' => __('No images found to process', 'kloudwebp')
        );
    }
    update_option('kloudwebp_conversion_progress', $progress);
    
    // Calculate space savings
    $results['original_size'] = size_format($results['total_size_before'], 2);
    $results['converted_size'] = size_format($results['total_size_after'], 2);
    $results['space_saved'] = size_format($results['total_size_before'] - $results['total_size_after'], 2);
    $results['done'] = count($attachments) < $batch_size || ($offset + count($attachments)) >= $total;
    $results['next_offset'] = $offset + $batch_size;
    
    wp_send_json_success($results);
}

function kloudwebp_convert_image($file_path) {
    // Skip if already WebP
    if (preg_match('/\.webp$/i', $file_path)) {
        return false;
    }
    
    $options = get_option('kloudwebp_options');
    $quality = isset($options['conversion_quality']) ? $options['conversion_quality'] : 80;
    $webp_path = preg_replace('/\.(jpe?g|png)$/i', '.webp', $file_path);
    
    // Check MIME type
    $mime_type = wp_check_filetype($file_path)['type'];
    if (!in_array($mime_type, array('image/jpeg', 'image/png'))) {
        kloudwebp_log_error("Unsupported image type: " . $mime_type);
        return false;
    }
    
    // Try Imagick first
    if (extension_loaded('imagick')) {
        try {
            $image = new Imagick($file_path);
            $image->setImageFormat('webp');
            $image->setOption('webp:quality', $quality);
            $success = $image->writeImage($webp_path);
            $image->clear();
            $image->destroy();
            
            if ($success) {
                return $webp_path;
            }
        } catch (Exception $e) {
            kloudwebp_log_error("Imagick error: " . $e->getMessage());
        }
    }
    
    // Fallback to GD if Imagick fails or is not available
    if (extension_loaded('gd')) {
        try {
            $image_info = getimagesize($file_path);
            if (!$image_info) {
                throw new Exception("Unable to get image information");
            }
            
            $source = null;
            switch ($image_info['mime']) {
                case 'image/jpeg':
                    $source = imagecreatefromjpeg($file_path);
                    break;
                case 'image/png':
                    // Suppress libpng warnings about sRGB profile
                    @$source = imagecreatefrompng($file_path);
                    if ($source) {
                        // Handle PNG transparency
                        imagepalettetotruecolor($source);
                        imagealphablending($source, true);
                        imagesavealpha($source, true);
                    }
                    break;
                default:
                    throw new Exception("Unsupported image type: " . $image_info['mime']);
            }
            
            if (!$source) {
                throw new Exception("Failed to create image resource");
            }
            
            $success = imagewebp($source, $webp_path, $quality);
            imagedestroy($source);
            
            if ($success) {
                return $webp_path;
            }
        } catch (Exception $e) {
            kloudwebp_log_error("GD error: " . $e->getMessage());
        }
    }
    
    return false;
}

function kloudwebp_cleanup_files() {
    check_ajax_referer('kloudwebp_convert', 'nonce');
    
    $upload_dir = wp_upload_dir();
    $webp_files = glob($upload_dir['basedir'] . '/**/*.webp');
    $kept_files = array();
    $removed_files = array();
    
    // Get all WebP files referenced in the media library
    $attachments = get_posts(array(
        'post_type' => 'attachment',
        'posts_per_page' => -1,
        'post_status' => 'any'
    ));
    
    foreach ($attachments as $attachment) {
        $metadata = wp_get_attachment_metadata($attachment->ID);
        if (!empty($metadata['webp_path']) && file_exists($metadata['webp_path'])) {
            $kept_files[] = $metadata['webp_path'];
        }
    }
    
    // Remove orphaned WebP files
    foreach ($webp_files as $webp_file) {
        if (!in_array($webp_file, $kept_files)) {
            if (unlink($webp_file)) {
                $removed_files[] = $webp_file;
            }
        }
    }
    
    wp_send_json_success(array(
        'removed' => count($removed_files),
        'message' => sprintf(
            __('Removed %d orphaned WebP files', 'kloudwebp'),
            count($removed_files)
        )
    ));
}

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

// Add browser compatibility check and image serving
function kloudwebp_check_webp_support() {
    $accept = isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : '';
    return strpos($accept, 'image/webp') !== false;
}

function kloudwebp_modify_image_url($url, $attachment_id) {
    // Check if browser supports WebP
    if (!kloudwebp_browser_supports_webp()) {
        return $url;
    }
    
    // Check if image has been converted to WebP
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
    
    // Handle the main image URL
    if (!empty($metadata['webp_url'])) {
        $image[0] = $metadata['webp_url'];
    }
    
    // Handle specific size
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

function kloudwebp_browser_supports_webp() {
    if (!isset($_SERVER['HTTP_ACCEPT'])) {
        return false;
    }
    
    // Check Accept header for image/webp
    if (strpos($_SERVER['HTTP_ACCEPT'], 'image/webp') !== false) {
        return true;
    }
    
    // Check common WebP-supporting browsers
    if (isset($_SERVER['HTTP_USER_AGENT'])) {
        $ua = $_SERVER['HTTP_USER_AGENT'];
        
        // Chrome 32+, Opera 19+, Firefox 65+
        if (preg_match('/(Chrome\/[3-9]\d|Chrome\/\d{3,}|OPR\/[2-9]\d|OPR\/\d{3,}|Firefox\/6[5-9]|Firefox\/[7-9]\d|Firefox\/\d{3,})/', $ua)) {
            return true;
        }
        
        // Edge 18+
        if (preg_match('/Edge\/[1-9]\d|Edge\/\d{3,}/', $ua)) {
            return true;
        }
    }
    
    return false;
}

function kloudwebp_mime_types($mimes) {
    $mimes['webp'] = 'image/webp';
    return $mimes;
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

// Add WebP version information to attachment details
add_filter('wp_prepare_attachment_for_js', 'kloudwebp_attachment_for_js', 10, 3);
function kloudwebp_attachment_for_js($response, $attachment, $meta) {
    if (!empty($meta['webp_path'])) {
        $response['webp'] = array(
            'path' => $meta['webp_path'],
            'url' => $meta['webp_url'],
            'size' => $meta['webp_size']
        );
        
        if (!empty($meta['sizes'])) {
            foreach ($meta['sizes'] as $size => $size_data) {
                if (!empty($size_data['webp_path'])) {
                    $response['sizes'][$size]['webp'] = array(
                        'path' => $size_data['webp_path'],
                        'url' => $size_data['webp_url']
                    );
                }
            }
        }
    }
    return $response;
}

// Ensure WebP images are properly handled in the media library
add_filter('ajax_query_attachments_args', 'kloudwebp_ajax_query_attachments_args');
function kloudwebp_ajax_query_attachments_args($query) {
    if (!empty($query['post_mime_type']) && is_array($query['post_mime_type'])) {
        if (in_array('image/jpeg', $query['post_mime_type']) || in_array('image/png', $query['post_mime_type'])) {
            $query['post_mime_type'][] = 'image/webp';
        }
    }
    return $query;
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

function kloudwebp_replace_content_images($content) {
    if (!kloudwebp_browser_supports_webp()) {
        return $content;
    }

    // Use DOMDocument to properly parse and modify HTML
    if (!empty($content)) {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML(mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8'), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $images = $dom->getElementsByTagName('img');
        foreach ($images as $img) {
            $src = $img->getAttribute('src');
            if (preg_match('/\.(jpe?g|png)$/i', $src)) {
                $webp_src = preg_replace('/\.(jpe?g|png)$/i', '.webp', $src);
                $webp_path = str_replace(wp_get_upload_dir()['baseurl'], wp_get_upload_dir()['basedir'], $webp_src);
                
                if (file_exists($webp_path)) {
                    // Update src attribute
                    $img->setAttribute('src', $webp_src);
                    
                    // Update srcset if it exists
                    if ($img->hasAttribute('srcset')) {
                        $srcset = $img->getAttribute('srcset');
                        $new_srcset = preg_replace('/\.(jpe?g|png)\s/i', '.webp ', $srcset);
                        $new_srcset = preg_replace('/\.(jpe?g|png)$/i', '.webp', $new_srcset);
                        $img->setAttribute('srcset', $new_srcset);
                    }
                }
            }
        }

        $content = $dom->saveHTML();
    }

    return $content;
}

function kloudwebp_modify_image_attributes($attr, $attachment, $size) {
    if (!kloudwebp_browser_supports_webp()) {
        return $attr;
    }

    $metadata = wp_get_attachment_metadata($attachment->ID);
    
    // Modify src attribute
    if (!empty($metadata['webp_url'])) {
        $attr['src'] = $metadata['webp_url'];
    }

    // Modify srcset attribute
    if (!empty($attr['srcset'])) {
        $srcset_urls = explode(', ', $attr['srcset']);
        $new_srcset_urls = array();
        
        foreach ($srcset_urls as $srcset_url) {
            list($url, $descriptor) = explode(' ', $srcset_url);
            if (preg_match('/\.(jpe?g|png)$/i', $url)) {
                $webp_url = preg_replace('/\.(jpe?g|png)$/i', '.webp', $url);
                $webp_path = str_replace(wp_get_upload_dir()['baseurl'], wp_get_upload_dir()['basedir'], $webp_url);
                if (file_exists($webp_path)) {
                    $new_srcset_urls[] = $webp_url . ' ' . $descriptor;
                    continue;
                }
            }
            $new_srcset_urls[] = $srcset_url;
        }
        
        $attr['srcset'] = implode(', ', $new_srcset_urls);
    }

    return $attr;
}

function kloudwebp_displayable_image($result, $path) {
    if (preg_match('/\.webp$/i', $path)) {
        return true;
    }
    return $result;
}

// Add filters only once
add_filter('wp_get_attachment_url', 'kloudwebp_modify_image_url', 10, 2);
add_filter('wp_get_attachment_image_src', 'kloudwebp_modify_image_src', 10, 4);
add_filter('wp_calculate_image_srcset', 'kloudwebp_modify_image_srcset', 10, 5);
add_filter('wp_get_attachment_image_attributes', 'kloudwebp_modify_image_attributes', 10, 3);
add_filter('upload_mimes', 'kloudwebp_mime_types');
add_filter('file_is_displayable_image', 'kloudwebp_displayable_image', 10, 2);
add_filter('the_content', 'kloudwebp_replace_content_images', 999);
add_filter('wp_get_attachment_metadata', 'kloudwebp_update_attachment_metadata', 10, 2);

// Add WebP MIME type support
add_filter('upload_mimes', 'kloudwebp_mime_types');
function kloudwebp_mime_types($mimes) {
    $mimes['webp'] = 'image/webp';
    return $mimes;
}

// Handle WebP uploads
add_filter('wp_handle_upload_prefilter', function($file) {
    if ($file['type'] === 'image/webp') {
        add_filter('upload_dir', function($upload) {
            $upload['subdir'] = '/webp' . $upload['subdir'];
            $upload['path'] = $upload['basedir'] . $upload['subdir'];
            $upload['url'] = $upload['baseurl'] . $upload['subdir'];
            return $upload;
        });
    }
    return $file;
});

// Auto-convert new uploads
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

function kloudwebp_optimize_image_advanced($file_path) {
    if (!file_exists($file_path)) {
        return false;
    }
    
    $type = wp_check_filetype($file_path)['type'];
    
    if ($type === 'image/jpeg') {
        if (extension_loaded('imagick')) {
            try {
                $image = new Imagick($file_path);
                $image->stripImage();
                $image->writeImage($file_path);
                $image->clear();
                $image->destroy();
                return true;
            } catch (Exception $e) {
                kloudwebp_log_error("Optimization error: " . $e->getMessage());
            }
        }
    } elseif ($type === 'image/png') {
        if (extension_loaded('imagick')) {
            try {
                $image = new Imagick($file_path);
                $image->stripImage();
                $image->optimizeImageLayers();
                $image->writeImage($file_path);
                $image->clear();
                $image->destroy();
                return true;
            } catch (Exception $e) {
                kloudwebp_log_error("Optimization error: " . $e->getMessage());
            }
        }
    }
    
    return false;
}

function kloudwebp_resize_image($file_path, $max_width) {
    if (!file_exists($file_path)) {
        return false;
    }
    
    list($width, $height) = getimagesize($file_path);
    
    if ($width <= $max_width) {
        return true;
    }
    
    $new_height = round(($max_width / $width) * $height);
    
    if (extension_loaded('imagick')) {
        try {
            $image = new Imagick($file_path);
            $image->resizeImage($max_width, $new_height, Imagick::FILTER_LANCZOS, 1);
            $image->writeImage($file_path);
            $image->clear();
            $image->destroy();
            return true;
        } catch (Exception $e) {
            kloudwebp_log_error("Resize error: " . $e->getMessage());
        }
    } elseif (extension_loaded('gd')) {
        $source = imagecreatefromstring(file_get_contents($file_path));
        $destination = imagecreatetruecolor($max_width, $new_height);
        
        if (imagecopyresampled($destination, $source, 0, 0, 0, 0, $max_width, $new_height, $width, $height)) {
            $type = wp_check_filetype($file_path)['type'];
            switch ($type) {
                case 'image/jpeg':
                    imagejpeg($destination, $file_path, 90);
                    break;
                case 'image/png':
                    imagealphablending($destination, false);
                    imagesavealpha($destination, true);
                    imagepng($destination, $file_path, 9);
                    break;
            }
            imagedestroy($source);
            imagedestroy($destination);
            return true;
        }
    }
    
    return false;
}

add_filter('wp_handle_upload', 'kloudwebp_handle_upload');

// Initialize the plugin
add_action('init', 'kloudwebp_init');

// Register activation hook
register_activation_hook(__FILE__, 'kloudwebp_activate');

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

// Register deactivation hook
register_deactivation_hook(__FILE__, 'kloudwebp_deactivate');

function kloudwebp_deactivate() {
    // Cleanup if needed
}

// Add AJAX handlers for image conversion
add_action('wp_ajax_kloudwebp_convert_images', 'kloudwebp_ajax_convert_images');

function kloudwebp_ajax_convert_images() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

    $results = kloudwebp_process_images();
    wp_send_json_success($results);
}
