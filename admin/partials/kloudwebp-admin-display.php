<?php
// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <?php settings_errors('kloudwebp_messages'); ?>

    <form method="post" action="options.php">
        <?php
        settings_fields('kloudwebp_settings');
        do_settings_sections('kloudwebp_settings');
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
                            <th>Title</th>
                            <th>Type</th>
                            <th>Images</th>
                            <th>Conversion Status</th>
                            <th>Last Modified</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($posts as $post): 
                        $conversion_percentage = $post['total_images'] > 0 ? 
                            round(($post['converted'] / $post['total_images']) * 100) : 0;
                        
                        $status_class = $conversion_percentage == 100 ? 'converted' : 
                            ($conversion_percentage == 0 ? 'unconverted' : 'partial');
                        
                        $status_text = $conversion_percentage == 100 ? 'Fully Converted' : 
                            ($conversion_percentage == 0 ? 'Not Converted' : 'Partially Converted');
                    ?>
                        <tr class="post-row <?php echo esc_attr($post['type']); ?> <?php echo esc_attr($status_class); ?>">
                            <td>
                                <strong>
                                    <a href="<?php echo get_edit_post_link($post['id']); ?>" target="_blank">
                                        <?php echo esc_html($post['title']); ?>
                                    </a>
                                </strong>
                            </td>
                            <td><?php echo esc_html(ucfirst($post['type'])); ?></td>
                            <td>
                                <?php echo sprintf(
                                    '%d / %d converted',
                                    $post['converted'],
                                    $post['total_images']
                                ); ?>
                            </td>
                            <td>
                                <div class="conversion-status <?php echo esc_attr($status_class); ?>">
                                    <div class="progress-bar">
                                        <div class="progress" style="width: <?php echo esc_attr($conversion_percentage); ?>%"></div>
                                    </div>
                                    <span class="status-text"><?php echo esc_html($status_text); ?></span>
                                </div>
                            </td>
                            <td><?php echo get_the_modified_date('Y-m-d H:i', $post['id']); ?></td>
                            <td>
                                <?php if ($post['unconverted'] > 0): ?>
                                    <button class="button convert-single-post" data-post-id="<?php echo esc_attr($post['id']); ?>">
                                        Convert Images
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
        $('.convert-single-post').on('click', function() {
            var button = $(this);
            var postId = button.data('post-id');
            
            button.prop('disabled', true).text('Converting...');

            $.post(ajaxurl, {
                action: 'kloudwebp_convert_single_post',
                post_id: postId,
                nonce: '<?php echo wp_create_nonce('kloudwebp_convert_post'); ?>'
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    button.text('Error').removeClass('button-primary');
                }
            }).fail(function() {
                button.text('Error').removeClass('button-primary');
            });
        });
    });
    </script>

    <h3>Convert Post Images</h3>
    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
        <?php wp_nonce_field('kloudwebp_convert_posts'); ?>
        <input type="hidden" name="action" value="kloudwebp_convert_posts">
        <p>This will scan all your posts and pages for images and convert them to WebP format.</p>
        <p><button type="submit" class="button button-primary">Convert Post Images</button></p>
    </form>
</div>
