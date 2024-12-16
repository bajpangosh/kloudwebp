<?php
// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

function format_size($bytes) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, 2) . ' ' . $units[$pow];
}
?>

<div class="wrap kloudwebp-dashboard">
    <h1>
        <span class="dashicons dashicons-images-alt2"></span>
        KloudWebP Dashboard
    </h1>

    <?php
    // Display conversion results if available
    if (isset($_GET['converted']) || isset($_GET['failed']) || isset($_GET['skipped'])) {
        $converted = intval($_GET['converted']);
        $failed = intval($_GET['failed']);
        $skipped = intval($_GET['skipped']);
        
        $message_type = ($failed === 0) ? 'success' : ($converted > 0 ? 'warning' : 'error');
        $message = sprintf(
            __('Media library conversion completed: %d images converted, %d failed, %d skipped', 'kloudwebp'),
            $converted,
            $failed,
            $skipped
        );
        ?>
        <div class="notice notice-<?php echo $message_type; ?> is-dismissible">
            <p><?php echo esc_html($message); ?></p>
        </div>
        <?php
    }

    // Display post conversion results if available
    if (isset($_GET['posts_converted']) || isset($_GET['posts_failed']) || isset($_GET['posts_skipped'])) {
        $converted = intval($_GET['posts_converted']);
        $failed = intval($_GET['posts_failed']);
        $skipped = intval($_GET['posts_skipped']);
        $updated_posts = intval($_GET['updated_posts']);
        
        $message_type = ($failed === 0) ? 'success' : ($converted > 0 ? 'warning' : 'error');
        $message = sprintf(
            __('Post image conversion completed: %d images converted, %d failed, %d skipped, %d posts updated', 'kloudwebp'),
            $converted,
            $failed,
            $skipped,
            $updated_posts
        );
        ?>
        <div class="notice notice-<?php echo $message_type; ?> is-dismissible">
            <p><?php echo esc_html($message); ?></p>
        </div>
        <?php
    }
    ?>

    <div class="kloudwebp-stats-grid">
        <!-- Conversion Progress -->
        <div class="kloudwebp-stat-card">
            <h3>Conversion Progress</h3>
            <div class="progress-bar">
                <div class="progress" style="width: <?php echo esc_attr($stats['conversion_rate']); ?>%"></div>
            </div>
            <div class="stat-numbers">
                <span class="stat-percentage"><?php echo round($stats['conversion_rate'], 1); ?>%</span>
                <span class="stat-detail">
                    <?php echo esc_html($stats['converted_images']); ?> / <?php echo esc_html($stats['total_images']); ?> images
                </span>
            </div>
        </div>

        <!-- Storage Savings -->
        <div class="kloudwebp-stat-card">
            <h3>Storage Savings</h3>
            <div class="storage-stats">
                <div class="stat-item">
                    <span class="stat-label">Original Size:</span>
                    <span class="stat-value"><?php echo format_size($stats['total_size_original']); ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">WebP Size:</span>
                    <span class="stat-value"><?php echo format_size($stats['total_size_webp']); ?></span>
                </div>
                <div class="stat-item savings">
                    <span class="stat-label">Total Saved:</span>
                    <span class="stat-value"><?php echo format_size($stats['size_saved']); ?></span>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="kloudwebp-stat-card">
            <h3>Quick Actions</h3>
            <div class="quick-actions">
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                    <?php wp_nonce_field('kloudwebp_bulk_convert'); ?>
                    <input type="hidden" name="action" value="kloudwebp_bulk_convert">
                    <?php 
                    if ($stats['unconverted_images'] > 0) {
                        submit_button(
                            sprintf('Convert Media Library Images (%d)', $stats['unconverted_images']),
                            'primary',
                            'bulk_convert',
                            false
                        );
                    } else {
                        echo '<p class="success-message">✓ All media library images are converted!</p>';
                    }
                    ?>
                </form>

                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                    <?php wp_nonce_field('kloudwebp_bulk_convert_posts'); ?>
                    <input type="hidden" name="action" value="kloudwebp_bulk_convert_posts">
                    <?php
                    $post_images = $this->get_post_image_count();
                    if ($post_images > 0) {
                        submit_button(
                            sprintf('Convert Post & Page Images (%d found)', $post_images),
                            'secondary',
                            'bulk_convert_posts',
                            false
                        );
                    } else {
                        echo '<p class="success-message">✓ No unconverted images found in posts!</p>';
                    }
                    ?>
                </form>

                <a href="<?php echo admin_url('admin.php?page=' . $this->plugin_name . '-settings'); ?>" class="button button-secondary">
                    Configure Settings
                </a>
            </div>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="kloudwebp-recent-activity">
        <h2>Recent Activity</h2>
        <?php
        $recent_images = get_posts(array(
            'post_type' => 'attachment',
            'post_mime_type' => array('image/jpeg', 'image/png'),
            'posts_per_page' => 5,
            'orderby' => 'date',
            'order' => 'DESC'
        ));

        if ($recent_images) : ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Image</th>
                        <th>Name</th>
                        <th>Original Size</th>
                        <th>WebP Size</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_images as $image) : 
                        $file = get_attached_file($image->ID);
                        $webp_path = preg_replace('/\.(jpe?g|png)$/i', '.webp', $file);
                        $original_size = file_exists($file) ? filesize($file) : 0;
                        $webp_size = file_exists($webp_path) ? filesize($webp_path) : 0;
                        $is_converted = file_exists($webp_path);
                    ?>
                    <tr>
                        <td>
                            <?php echo wp_get_attachment_image($image->ID, array(50, 50)); ?>
                        </td>
                        <td><?php echo esc_html($image->post_title); ?></td>
                        <td><?php echo format_size($original_size); ?></td>
                        <td><?php echo $is_converted ? format_size($webp_size) : '—'; ?></td>
                        <td>
                            <?php if ($is_converted) : ?>
                                <span class="status-converted">✓ Converted</span>
                            <?php else : ?>
                                <span class="status-pending">Pending</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else : ?>
            <p>No images found in the media library.</p>
        <?php endif; ?>
    </div>
</div>

<style>
.kloudwebp-dashboard {
    max-width: 1200px;
    margin: 20px auto;
}

.kloudwebp-dashboard h1 {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 30px;
}

.kloudwebp-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 40px;
}

.kloudwebp-stat-card {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.kloudwebp-stat-card h3 {
    margin-top: 0;
    margin-bottom: 20px;
    color: #1d2327;
}

/* Progress Bar Styles */
.progress-bar {
    background: #f0f0f1;
    border-radius: 4px;
    height: 8px;
    margin-bottom: 15px;
    overflow: hidden;
}

.progress-bar .progress {
    background: #2271b1;
    height: 100%;
    transition: width 0.3s ease;
}

.stat-numbers {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
}

.stat-percentage {
    font-size: 24px;
    font-weight: bold;
    color: #2271b1;
}

.stat-detail {
    color: #646970;
}

/* Storage Stats Styles */
.storage-stats {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.stat-item {
    display: flex;
    justify-content: space-between;
    padding: 5px 0;
}

.stat-item.savings {
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px solid #f0f0f1;
}

.stat-label {
    color: #646970;
}

.stat-value {
    font-weight: 500;
}

/* Quick Actions Styles */
.quick-actions {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.success-message {
    color: #00a32a;
    font-weight: 500;
    margin: 10px 0;
}

/* Recent Activity Styles */
.kloudwebp-recent-activity {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    margin-top: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.kloudwebp-recent-activity h2 {
    margin-top: 0;
    margin-bottom: 20px;
}

.status-converted {
    color: #00a32a;
    font-weight: 500;
}

.status-pending {
    color: #996800;
}

/* Responsive Design */
@media screen and (max-width: 782px) {
    .kloudwebp-stats-grid {
        grid-template-columns: 1fr;
    }
    
    .stat-numbers {
        flex-direction: column;
        align-items: flex-start;
        gap: 5px;
    }
}
</style>
