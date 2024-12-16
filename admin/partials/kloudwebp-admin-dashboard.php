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
            <h3><?php _e('Total Images'); ?></h3>
            <div class="stat-value"><?php echo $this->get_total_images_count(); ?></div>
        </div>
        <div class="stats-card">
            <h3><?php _e('Converted Images'); ?></h3>
            <div class="stat-value"><?php echo $this->get_converted_images_count(); ?></div>
        </div>
        <div class="stats-card">
            <h3><?php _e('Space Saved'); ?></h3>
            <div class="stat-value"><?php echo size_format($this->get_total_space_saved()); ?></div>
        </div>
    </div>

    <!-- Posts/Pages Filter -->
    <div class="tablenav top">
        <div class="alignleft actions">
            <select id="post-type-filter">
                <option value=""><?php _e('All Content Types'); ?></option>
                <option value="post"><?php _e('Posts'); ?></option>
                <option value="page"><?php _e('Pages'); ?></option>
            </select>
            <select id="conversion-status-filter">
                <option value=""><?php _e('All Statuses'); ?></option>
                <option value="unconverted"><?php _e('Needs Conversion'); ?></option>
                <option value="partial"><?php _e('Partially Converted'); ?></option>
                <option value="converted"><?php _e('Fully Converted'); ?></option>
            </select>
        </div>
    </div>

    <!-- Posts Table -->
    <?php
    $posts = $this->get_posts_conversion_status();
    if (empty($posts)): ?>
        <p><?php _e('No posts or pages with images found.'); ?></p>
    <?php else: ?>
        <table class="wp-list-table widefat fixed striped kloudwebp-posts-table">
            <thead>
                <tr>
                    <th><?php _e('Title'); ?></th>
                    <th><?php _e('Type'); ?></th>
                    <th><?php _e('Images'); ?></th>
                    <th><?php _e('Conversion Progress'); ?></th>
                    <th><?php _e('Last Modified'); ?></th>
                    <th><?php _e('Actions'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($posts as $post) : 
                    $total_images = $this->get_total_images_count($post->ID);
                    $converted_images = $this->get_converted_images_count($post->ID);
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
                                <div class="conversion-progress" style="width: <?php echo $percentage; ?>%"></div>
                            </div>
                        </td>
                        <td><?php echo get_the_modified_date('Y-m-d H:i:s', $post->ID); ?></td>
                        <td>
                            <?php if ($total_images > $converted_images) : ?>
                                <button class="button button-primary kloudwebp-convert-post" 
                                        data-post-id="<?php echo $post->ID; ?>">
                                    <?php _e('Convert'); ?>
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
    margin-bottom: 40px;
}

.stats-card {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    width: calc(33.33% - 20px);
}

.stats-card h3 {
    margin-top: 0;
    margin-bottom: 20px;
    color: #1d2327;
}

.stat-value {
    font-size: 24px;
    font-weight: bold;
    color: #2271b1;
}

.tablenav.top {
    margin-bottom: 20px;
}

.kloudwebp-posts-table {
    margin-bottom: 40px;
}

.conversion-progress-bar {
    background: #f0f0f1;
    border-radius: 4px;
    height: 8px;
    margin-bottom: 15px;
    overflow: hidden;
}

.conversion-progress {
    background: #2271b1;
    height: 100%;
    transition: width 0.3s ease;
}

.column-images {
    text-align: center;
}

.button.button-primary.kloudwebp-convert-post {
    padding: 8px 16px;
    font-size: 14px;
    font-weight: bold;
    border-radius: 4px;
    cursor: pointer;
}

.button.button-primary.kloudwebp-convert-post:hover {
    background-color: #1d2327;
    color: #fff;
}
</style>
