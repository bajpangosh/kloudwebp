<?php
// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}
?>

<div class="wrap">
    <h2>KloudWebP Settings</h2>
    <?php settings_errors(); ?>

    <form method="post" action="options.php">
        <?php
        settings_fields('kloudwebp');
        do_settings_sections('kloudwebp');
        ?>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="kloudwebp_quality">WebP Quality</label>
                </th>
                <td>
                    <input type="number" 
                           id="kloudwebp_quality" 
                           name="kloudwebp_quality" 
                           min="1" 
                           max="100" 
                           value="<?php echo esc_attr(get_option('kloudwebp_quality', 80)); ?>" />
                    <p class="description">Quality setting for WebP conversion (1-100)</p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="kloudwebp_keep_original">Keep Original Images</label>
                </th>
                <td>
                    <input type="checkbox" 
                           id="kloudwebp_keep_original" 
                           name="kloudwebp_keep_original" 
                           value="1" 
                           <?php checked(1, get_option('kloudwebp_keep_original', true), true); ?> />
                    <p class="description">Keep original images as fallback for browsers that don't support WebP</p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="kloudwebp_auto_convert">Auto-Convert Uploads</label>
                </th>
                <td>
                    <input type="checkbox" 
                           id="kloudwebp_auto_convert" 
                           name="kloudwebp_auto_convert" 
                           value="1" 
                           <?php checked(1, get_option('kloudwebp_auto_convert', false), true); ?> />
                    <p class="description">Automatically convert new image uploads to WebP</p>
                </td>
            </tr>
        </table>
        
        <?php submit_button('Save Settings'); ?>
    </form>

    <hr>

    <div class="kloudwebp-card">
        <div class="kloudwebp-card-header">
            <h2>WebP Conversion Statistics</h2>
        </div>
        <div class="kloudwebp-card-body">
            <?php
            // Get total images count
            $total_images = $this->get_total_images_count();
            
            // Get converted images count
            $converted_images = $this->get_converted_images_count();
            
            // Calculate percentage
            $conversion_percentage = ($total_images > 0) ? round(($converted_images / $total_images) * 100) : 0;
            
            // Get saved space
            $saved_space = $this->get_total_space_saved();
            $saved_space_formatted = size_format($saved_space, 2);
            ?>
            
            <div class="kloudwebp-stats-grid">
                <div class="kloudwebp-stat-item">
                    <span class="stat-label">Total Images</span>
                    <span class="stat-value"><?php echo esc_html($total_images); ?></span>
                </div>
                
                <div class="kloudwebp-stat-item">
                    <span class="stat-label">Converted to WebP</span>
                    <span class="stat-value"><?php echo esc_html($converted_images); ?></span>
                </div>
                
                <div class="kloudwebp-stat-item">
                    <span class="stat-label">Conversion Rate</span>
                    <span class="stat-value"><?php echo esc_html($conversion_percentage); ?>%</span>
                </div>
                
                <div class="kloudwebp-stat-item">
                    <span class="stat-label">Space Saved</span>
                    <span class="stat-value"><?php echo esc_html($saved_space_formatted); ?></span>
                </div>
            </div>
            
            <div class="kloudwebp-progress-bar">
                <div class="progress" style="width: <?php echo esc_attr($conversion_percentage); ?>%"></div>
            </div>
        </div>
    </div>

    <h3>Bulk Convert Existing Images</h3>
    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
        <?php wp_nonce_field('kloudwebp_bulk_convert'); ?>
        <input type="hidden" name="action" value="kloudwebp_bulk_convert">
        <?php submit_button('Convert All Images', 'primary', 'bulk_convert', false); ?>
        <p class="description">Convert all existing JPEG and PNG images in your media library to WebP format.</p>
    </form>
</div>
