<?php
// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <?php settings_errors('kloudwebp_messages'); ?>

    <?php
    $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'dashboard';
    ?>

    <h2 class="nav-tab-wrapper">
        <a href="?page=<?php echo $this->plugin_name; ?>&tab=dashboard" 
           class="nav-tab <?php echo $active_tab == 'dashboard' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Dashboard'); ?>
        </a>
        <a href="?page=<?php echo $this->plugin_name; ?>&tab=settings" 
           class="nav-tab <?php echo $active_tab == 'settings' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Settings'); ?>
        </a>
    </h2>

    <?php if ($active_tab == 'dashboard'): ?>
        <!-- Dashboard Tab -->
        <div class="kloudwebp-dashboard">
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

            <div class="kloudwebp-card">
                <div class="kloudwebp-card-header">
                    <h2>Posts and Pages Image Status</h2>
                </div>
                <div class="kloudwebp-card-body">
                    <?php
                    $posts = $this->get_posts_conversion_status();
                    if (empty($posts)): ?>
                        <p>No posts or pages with images found.</p>
                    <?php else: ?>
                        <div class="kloudwebp-posts-filter">
                            <select id="post-type-filter">
                                <option value="all">All Content Types</option>
                                <option value="post">Posts Only</option>
                                <option value="page">Pages Only</option>
                            </select>
                            <select id="conversion-status-filter">
                                <option value="all">All Conversion Status</option>
                                <option value="unconverted">Needs Conversion</option>
                                <option value="converted">Fully Converted</option>
                                <option value="partial">Partially Converted</option>
                            </select>
                        </div>

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
                            <?php foreach ($posts as $post): 
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
            </div>
        </div>

    <?php else: ?>
        <!-- Settings Tab -->
        <div class="kloudwebp-settings">
            <form method="post" action="options.php">
                <?php
                settings_fields('kloudwebp_settings');
                do_settings_sections('kloudwebp_settings');
                submit_button('Save Settings');
                ?>
            </form>
        </div>
    <?php endif; ?>

    <script>
    jQuery(document).ready(function($) {
        // Filters
        function filterPosts() {
            var postType = $('#post-type-filter').val();
            var conversionStatus = $('#conversion-status-filter').val();
            
            $('.post-row').each(function() {
                var $row = $(this);
                var showPostType = postType === 'all' || $row.hasClass(postType);
                var showStatus = conversionStatus === 'all' || $row.hasClass(conversionStatus);
                
                $row.toggle(showPostType && showStatus);
            });
        }

        $('#post-type-filter, #conversion-status-filter').on('change', filterPosts);

        // Convert single post
        $('.kloudwebp-convert-post').on('click', function() {
            var button = $(this);
            var postId = button.data('post-id');
            
            button.prop('disabled', true).text('<?php _e('Converting...'); ?>');

            $.post(ajaxurl, {
                action: 'kloudwebp_convert_single_post',
                post_id: postId,
                nonce: '<?php echo wp_create_nonce('kloudwebp_convert_post'); ?>'
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    button.text('<?php _e('Error'); ?>').removeClass('button-primary');
                }
            }).fail(function() {
                button.text('<?php _e('Error'); ?>').removeClass('button-primary');
            });
        });
    });
    </script>
</div>
