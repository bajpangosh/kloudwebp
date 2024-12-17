<?php
// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <?php settings_errors('kloudwebp_messages'); ?>

    <!-- Statistics Cards -->
    <div class="kloudwebp-stats-cards">
        <div class="stats-card">
            <h3><?php _e('Total Images', 'kloudwebp'); ?></h3>
            <div id="total-images" class="stat-value"><?php echo $this->get_total_images_count(); ?></div>
        </div>
        <div class="stats-card">
            <h3><?php _e('Converted Images', 'kloudwebp'); ?></h3>
            <div id="converted-images" class="stat-value"><?php echo $this->get_converted_images_count(); ?></div>
        </div>
        <div class="stats-card">
            <h3><?php _e('Space Saved', 'kloudwebp'); ?></h3>
            <div id="space-saved" class="stat-value"><?php echo size_format($this->get_total_space_saved()); ?></div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="kloudwebp-actions">
        <div class="action-buttons">
            <?php 
            $total_images = $this->get_total_images_count();
            $converted_images = $this->get_converted_images_count();
            $unconverted = $total_images - $converted_images;
            
            if ($unconverted > 0) : ?>
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" class="inline-form">
                    <?php wp_nonce_field('kloudwebp_bulk_convert', 'kloudwebp_nonce'); ?>
                    <input type="hidden" name="action" value="kloudwebp_bulk_convert">
                    <button type="submit" class="button button-primary">
                        <?php printf(__('Convert All Images (%d remaining)', 'kloudwebp'), $unconverted); ?>
                    </button>
                </form>
            <?php endif; ?>

            <a href="<?php echo admin_url('admin.php?page=' . $this->plugin_name . '-settings'); ?>" class="button">
                <?php _e('Settings', 'kloudwebp'); ?>
            </a>
        </div>
    </div>

    <!-- Posts/Pages Filter -->
    <div class="tablenav top">
        <div class="alignleft actions">
            <select id="post-type-filter">
                <option value=""><?php _e('All Content Types', 'kloudwebp'); ?></option>
                <option value="post"><?php _e('Posts', 'kloudwebp'); ?></option>
                <option value="page"><?php _e('Pages', 'kloudwebp'); ?></option>
            </select>
            <select id="conversion-status-filter">
                <option value=""><?php _e('All Statuses', 'kloudwebp'); ?></option>
                <option value="unconverted"><?php _e('Needs Conversion', 'kloudwebp'); ?></option>
                <option value="partial"><?php _e('Partially Converted', 'kloudwebp'); ?></option>
                <option value="converted"><?php _e('Fully Converted', 'kloudwebp'); ?></option>
            </select>
        </div>
    </div>

    <!-- Posts Table -->
    <?php
    $posts = $this->get_posts_conversion_status();
    if (empty($posts)): ?>
        <p><?php _e('No posts or pages with images found.', 'kloudwebp'); ?></p>
    <?php else: ?>
        <table class="wp-list-table widefat fixed striped kloudwebp-posts-table">
            <thead>
                <tr>
                    <th><?php _e('Title', 'kloudwebp'); ?></th>
                    <th><?php _e('Type', 'kloudwebp'); ?></th>
                    <th><?php _e('Images', 'kloudwebp'); ?></th>
                    <th><?php _e('Conversion Progress', 'kloudwebp'); ?></th>
                    <th><?php _e('Last Modified', 'kloudwebp'); ?></th>
                    <th><?php _e('Actions', 'kloudwebp'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($posts as $post) : 
                    $images = $this->get_post_images($post->ID);
                    $total_images = count($images['all']);
                    $converted_images = count($images['converted']);
                    $percentage = $total_images > 0 ? round(($converted_images / $total_images) * 100) : 0;
                ?>
                    <tr>
                        <td>
                            <a href="<?php echo get_edit_post_link($post->ID); ?>"><?php echo esc_html($post->post_title); ?></a>
                        </td>
                        <td><?php echo get_post_type_object($post->post_type)->labels->singular_name; ?></td>
                        <td class="column-images"><?php echo $converted_images . ' / ' . $total_images; ?></td>
                        <td>
                            <div class="conversion-progress-bar">
                                <div class="progress-fill" style="width: <?php echo $percentage; ?>%"></div>
                                <span class="progress-text"><?php echo $percentage; ?>%</span>
                            </div>
                        </td>
                        <td><?php echo get_the_modified_date('', $post->ID); ?></td>
                        <td>
                            <?php if ($converted_images < $total_images): ?>
                                <button class="button button-primary convert-post-button" 
                                        data-post-id="<?php echo $post->ID; ?>"
                                        data-progress="<?php echo $percentage; ?>">
                                    <?php _e('Convert', 'kloudwebp'); ?>
                                </button>
                            <?php else: ?>
                                <button class="button button-disabled" disabled>
                                    <?php _e('Converted', 'kloudwebp'); ?>
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<style>
.kloudwebp-stats-cards {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    margin-bottom: 20px;
}

.stats-card {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    width: calc(33.33% - 20px);
    text-align: center;
}

.stats-card h3 {
    margin: 0 0 10px;
    font-size: 14px;
    color: #646970;
}

.stat-value {
    font-size: 24px;
    font-weight: bold;
    color: #2271b1;
}

.kloudwebp-actions {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
}

.action-buttons {
    display: flex;
    gap: 10px;
    align-items: center;
}

.inline-form {
    margin: 0;
    padding: 0;
}

.tablenav.top {
    margin: 15px 0;
}

.conversion-progress-bar {
    width: 100%;
    height: 8px;
    background: #f0f0f1;
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 4px;
}

.progress-fill {
    height: 100%;
    background: #2271b1;
    transition: width 0.3s ease;
}

.progress-text {
    font-size: 12px;
    color: #646970;
}

.kloudwebp-posts-table {
    margin-top: 15px;
}

.kloudwebp-posts-table td {
    vertical-align: middle;
}

.column-images {
    text-align: center;
}

@media screen and (max-width: 782px) {
    .stats-card {
        width: 100%;
    }
    
    .action-buttons {
        flex-direction: column;
        align-items: stretch;
    }
    
    .action-buttons .button {
        width: 100%;
        text-align: center;
    }
}
</style>
