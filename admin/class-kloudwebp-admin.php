<?php
/**
 * KloudWebP Admin Class
 * Handles all admin functionality including dashboard and settings
 */
class KloudWebP_Admin {
    private $converter;

    /**
     * Initialize the admin class
     */
    public function init() {
        // Initialize converter
        $this->converter = new KloudWebP_Converter();

        // Add menu items
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Register settings
        add_action('admin_init', array($this, 'register_settings'));
        
        // Add admin scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // Add AJAX handlers
        add_action('wp_ajax_kloudwebp_convert_single', array($this, 'ajax_convert_single'));
        add_action('wp_ajax_kloudwebp_convert_bulk', array($this, 'ajax_convert_bulk'));
        add_action('wp_ajax_kloudwebp_get_conversion_status', array($this, 'ajax_get_conversion_status'));
        add_action('wp_ajax_kloudwebp_refresh_debug_log', array($this, 'ajax_refresh_debug_log'));
        add_action('wp_ajax_kloudwebp_clear_debug_log', array($this, 'ajax_clear_debug_log'));
    }

    /**
     * Add admin menu items
     */
    public function add_admin_menu() {
        add_menu_page(
            __('KloudWebP', 'kloudwebp'),
            __('KloudWebP', 'kloudwebp'),
            'manage_options',
            'kloudwebp',
            array($this, 'render_dashboard_page'),
            'dashicons-images-alt2'
        );

        add_submenu_page(
            'kloudwebp',
            __('Settings', 'kloudwebp'),
            __('Settings', 'kloudwebp'),
            'manage_options',
            'kloudwebp-settings',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Register plugin settings
     */
    public function register_settings() {
        register_setting('kloudwebp_settings', 'kloudwebp_compression_quality', array(
            'type' => 'integer',
            'default' => 80,
            'sanitize_callback' => function($value) {
                return min(100, max(1, intval($value)));
            }
        ));
        
        register_setting('kloudwebp_settings', 'kloudwebp_update_content', array(
            'type' => 'boolean',
            'default' => true
        ));

        register_setting('kloudwebp_settings', 'kloudwebp_debug_log', array(
            'type' => 'boolean',
            'default' => false
        ));
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'kloudwebp') === false) {
            return;
        }

        wp_enqueue_style(
            'kloudwebp-admin',
            KLOUDWEBP_PLUGIN_URL . 'admin/css/kloudwebp-admin.css',
            array(),
            KLOUDWEBP_VERSION
        );

        wp_enqueue_script(
            'kloudwebp-admin',
            KLOUDWEBP_PLUGIN_URL . 'admin/js/kloudwebp-admin.js',
            array('jquery'),
            KLOUDWEBP_VERSION,
            true
        );

        wp_localize_script('kloudwebp-admin', 'kloudwebpAjax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('kloudwebp_ajax'),
            'strings' => array(
                'converting' => __('Converting...', 'kloudwebp'),
                'converted' => __('Converted', 'kloudwebp'),
                'error' => __('Error', 'kloudwebp')
            )
        ));
    }

    /**
     * Render dashboard page
     */
    public function render_dashboard_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        ?>
        <div class="wrap">
            <h1><?php _e('KloudWebP Dashboard', 'kloudwebp'); ?></h1>
            
            <div class="kloudwebp-bulk-actions">
                <button id="kloudwebp-bulk-convert" class="button button-primary">
                    <?php _e('Bulk Convert All Images', 'kloudwebp'); ?>
                </button>
            </div>

            <div class="kloudwebp-log-container">
                <h3><?php _e('Conversion Log', 'kloudwebp'); ?></h3>
                <div id="kloudwebp-log" class="kloudwebp-log"></div>
            </div>

            <div class="kloudwebp-conversion-table">
                <h3><?php _e('Posts and Pages', 'kloudwebp'); ?></h3>
                <?php $this->render_conversion_table(); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        ?>
        <div class="wrap">
            <h1><?php _e('KloudWebP Settings', 'kloudwebp'); ?></h1>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('kloudwebp_settings');
                do_settings_sections('kloudwebp_settings');
                ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="kloudwebp_compression_quality">
                                <?php _e('WebP Compression Quality', 'kloudwebp'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="number" 
                                   id="kloudwebp_compression_quality" 
                                   name="kloudwebp_compression_quality" 
                                   value="<?php echo esc_attr(get_option('kloudwebp_compression_quality', 80)); ?>"
                                   min="1" 
                                   max="100" 
                                   class="small-text">
                            <p class="description">
                                <?php _e('Quality setting for WebP conversion (1-100). Higher values mean better quality but larger file size.', 'kloudwebp'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <?php _e('Update Post Content', 'kloudwebp'); ?>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       name="kloudwebp_update_content" 
                                       value="1" 
                                       <?php checked(get_option('kloudwebp_update_content', true)); ?>>
                                <?php _e('Replace original image URLs with WebP versions in post content', 'kloudwebp'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <?php _e('Debug Logging', 'kloudwebp'); ?>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       name="kloudwebp_debug_log" 
                                       value="1" 
                                       <?php checked(get_option('kloudwebp_debug_log', false)); ?>>
                                <?php _e('Enable debug logging', 'kloudwebp'); ?>
                            </label>
                            <p class="description">
                                <?php _e('Log conversion process details and errors for debugging purposes.', 'kloudwebp'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>

            <div class="kloudwebp-debug-section" style="margin-top: 30px;">
                <h2><?php _e('Debug Log', 'kloudwebp'); ?></h2>
                <div class="kloudwebp-debug-controls">
                    <button id="kloudwebp-refresh-log" class="button">
                        <?php _e('Refresh Log', 'kloudwebp'); ?>
                    </button>
                    <button id="kloudwebp-clear-log" class="button">
                        <?php _e('Clear Log', 'kloudwebp'); ?>
                    </button>
                </div>
                <div id="kloudwebp-debug-log" class="kloudwebp-log" style="margin-top: 10px; height: 300px;">
                    <?php echo nl2br(esc_html($this->get_debug_log())); ?>
                </div>
            </div>
        </div>

        <style>
            .kloudwebp-debug-section {
                background: #fff;
                padding: 20px;
                border: 1px solid #ccd0d4;
                box-shadow: 0 1px 1px rgba(0,0,0,.04);
                border-radius: 4px;
            }
            .kloudwebp-debug-controls {
                margin-bottom: 10px;
            }
            .kloudwebp-debug-controls .button {
                margin-right: 10px;
            }
            #kloudwebp-debug-log {
                background: #f8f9fa;
                border: 1px solid #e2e4e7;
                padding: 10px;
                overflow-y: auto;
                font-family: monospace;
                font-size: 12px;
                line-height: 1.5;
                white-space: pre-wrap;
            }
        </style>

        <script>
        jQuery(document).ready(function($) {
            $('#kloudwebp-refresh-log').on('click', function(e) {
                e.preventDefault();
                var $button = $(this);
                var $log = $('#kloudwebp-debug-log');

                $button.prop('disabled', true);

                $.post(ajaxurl, {
                    action: 'kloudwebp_refresh_debug_log',
                    nonce: kloudwebpAjax.nonce
                }, function(response) {
                    if (response.success) {
                        $log.html(response.data.content);
                    }
                }).always(function() {
                    $button.prop('disabled', false);
                });
            });

            $('#kloudwebp-clear-log').on('click', function(e) {
                e.preventDefault();
                var $button = $(this);
                var $log = $('#kloudwebp-debug-log');

                if (!confirm('<?php _e('Are you sure you want to clear the debug log?', 'kloudwebp'); ?>')) {
                    return;
                }

                $button.prop('disabled', true);

                $.post(ajaxurl, {
                    action: 'kloudwebp_clear_debug_log',
                    nonce: kloudwebpAjax.nonce
                }, function(response) {
                    if (response.success) {
                        $log.empty();
                    }
                }).always(function() {
                    $button.prop('disabled', false);
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Log debug message with proper formatting and error handling
     */
    public function log_debug($message, $type = 'info') {
        if (!get_option('kloudwebp_debug_log', false)) {
            return;
        }

        $log_file = KLOUDWEBP_PLUGIN_DIR . 'debug.log';
        $max_size = 5 * 1024 * 1024; // 5MB max size

        try {
            // Rotate log if too large
            if (file_exists($log_file) && filesize($log_file) > $max_size) {
                $backup_file = $log_file . '.1';
                if (file_exists($backup_file)) {
                    unlink($backup_file);
                }
                rename($log_file, $backup_file);
            }

            // Format the log entry
            $timestamp = current_time('mysql');
            $formatted_message = sprintf(
                "[%s] [%s] %s\n",
                $timestamp,
                strtoupper($type),
                sanitize_text_field($message)
            );

            // Append to log file
            $result = file_put_contents(
                $log_file,
                $formatted_message,
                FILE_APPEND | LOCK_EX
            );

            if ($result === false) {
                error_log('KloudWebP: Failed to write to debug log');
            }
        } catch (Exception $e) {
            error_log('KloudWebP: Error writing to debug log - ' . $e->getMessage());
        }
    }

    /**
     * Get debug log contents with proper error handling
     */
    public function get_debug_log() {
        if (!current_user_can('manage_options')) {
            return '';
        }

        $log_file = KLOUDWEBP_PLUGIN_DIR . 'debug.log';
        
        try {
            if (!file_exists($log_file)) {
                return __('Debug log is empty.', 'kloudwebp');
            }

            $contents = file_get_contents($log_file);
            if ($contents === false) {
                return __('Error reading debug log.', 'kloudwebp');
            }

            return $contents;
        } catch (Exception $e) {
            return __('Error accessing debug log.', 'kloudwebp');
        }
    }

    /**
     * Clear debug log with proper error handling and security
     */
    public function ajax_clear_debug_log() {
        // Verify nonce and capabilities
        if (!check_ajax_referer('kloudwebp_ajax', 'nonce', false) || 
            !current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('Unauthorized access.', 'kloudwebp')
            ));
            return;
        }

        $log_file = KLOUDWEBP_PLUGIN_DIR . 'debug.log';
        
        try {
            if (file_exists($log_file)) {
                if (!unlink($log_file)) {
                    throw new Exception(__('Failed to delete log file.', 'kloudwebp'));
                }
            }
            
            wp_send_json_success(array(
                'message' => __('Debug log cleared successfully.', 'kloudwebp')
            ));
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
    }

    /**
     * Refresh debug log with proper error handling and security
     */
    public function ajax_refresh_debug_log() {
        // Verify nonce and capabilities
        if (!check_ajax_referer('kloudwebp_ajax', 'nonce', false) || 
            !current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('Unauthorized access.', 'kloudwebp')
            ));
            return;
        }

        try {
            $log_content = $this->get_debug_log();
            wp_send_json_success(array(
                'content' => nl2br(esc_html($log_content))
            ));
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
    }

    /**
     * Render conversion table
     */
    private function render_conversion_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'kloudwebp_conversions';
        $per_page = 20;
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($current_page - 1) * $per_page;
        
        // Get total posts/pages
        $total_items = $wpdb->get_var("
            SELECT COUNT(ID) 
            FROM {$wpdb->posts} 
            WHERE post_type IN ('post', 'page') 
            AND post_status = 'publish'
        ");
        
        // Get posts with their conversion status
        $items = $wpdb->get_results($wpdb->prepare("
            SELECT p.ID, p.post_title, p.post_type, COALESCE(c.status, 'not_converted') as status
            FROM {$wpdb->posts} p
            LEFT JOIN {$table_name} c ON p.ID = c.post_id
            WHERE p.post_type IN ('post', 'page')
            AND p.post_status = 'publish'
            ORDER BY p.post_date DESC
            LIMIT %d OFFSET %d
        ", $per_page, $offset));
        
        if ($items) :
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('ID', 'kloudwebp'); ?></th>
                    <th><?php _e('Title', 'kloudwebp'); ?></th>
                    <th><?php _e('Type', 'kloudwebp'); ?></th>
                    <th><?php _e('Status', 'kloudwebp'); ?></th>
                    <th><?php _e('Actions', 'kloudwebp'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item) : ?>
                    <tr id="post-<?php echo $item->ID; ?>">
                        <td><?php echo $item->ID; ?></td>
                        <td>
                            <strong>
                                <a href="<?php echo get_edit_post_link($item->ID); ?>">
                                    <?php echo esc_html($item->post_title); ?>
                                </a>
                            </strong>
                        </td>
                        <td><?php echo esc_html($item->post_type); ?></td>
                        <td class="kloudwebp-status <?php echo $this->get_status_class($item->status); ?>">
                            <?php echo $this->get_status_label($item->status); ?>
                        </td>
                        <td>
                            <button class="button kloudwebp-convert-single" 
                                    data-post-id="<?php echo $item->ID; ?>">
                                <?php _e('Convert to WebP', 'kloudwebp'); ?>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <?php
        $total_pages = ceil($total_items / $per_page);
        
        echo '<div class="tablenav bottom">';
        echo '<div class="tablenav-pages">';
        echo paginate_links(array(
            'base' => add_query_arg('paged', '%#%'),
            'format' => '',
            'prev_text' => __('&laquo;'),
            'next_text' => __('&raquo;'),
            'total' => $total_pages,
            'current' => $current_page,
        ));
        echo '</div>';
        echo '</div>';
        
        else:
            echo '<p>' . __('No posts or pages found.', 'kloudwebp') . '</p>';
        endif;
    }

    /**
     * Get status class for CSS
     */
    private function get_status_class($status) {
        switch ($status) {
            case 'converted':
                return 'status-success';
            case 'partially_converted':
                return 'status-warning';
            case 'not_converted':
                return 'status-error';
            case 'no_images':
                return 'status-neutral';
            default:
                return '';
        }
    }

    /**
     * Get human-readable status label
     */
    private function get_status_label($status) {
        switch ($status) {
            case 'converted':
                return __('Converted', 'kloudwebp');
            case 'partially_converted':
                return __('Partially Converted', 'kloudwebp');
            case 'not_converted':
                return __('Not Converted', 'kloudwebp');
            case 'no_images':
                return __('No Images', 'kloudwebp');
            default:
                return __('Unknown', 'kloudwebp');
        }
    }

    /**
     * AJAX handler for single post conversion
     */
    public function ajax_convert_single() {
        if (!check_ajax_referer('kloudwebp_ajax', 'nonce', false) || 
            !current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('Unauthorized access.', 'kloudwebp')
            ));
            return;
        }

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        if (!$post_id) {
            wp_send_json_error(array(
                'message' => __('Invalid post ID.', 'kloudwebp')
            ));
            return;
        }

        try {
            $result = $this->converter->convert_post_images($post_id);
            if (is_wp_error($result)) {
                throw new Exception($result->get_error_message());
            }

            if ($result['success']) {
                wp_send_json_success(array(
                    'message' => sprintf(
                        __('Successfully converted images in post %d.', 'kloudwebp'),
                        $post_id
                    ),
                    'status' => $result['status'] ?? 'converted',
                    'details' => $result
                ));
            } else {
                wp_send_json_error(array(
                    'message' => $result['message'] ?? __('Failed to convert images.', 'kloudwebp'),
                    'details' => $result
                ));
            }
        } catch (Exception $e) {
            $this->log_debug('Error converting post ' . $post_id . ': ' . $e->getMessage(), 'error');
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
    }

    /**
     * AJAX handler for bulk conversion
     */
    public function ajax_convert_bulk() {
        if (!check_ajax_referer('kloudwebp_ajax', 'nonce', false) || 
            !current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('Unauthorized access.', 'kloudwebp')
            ));
            return;
        }

        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 10;
        
        // Sanitize and validate input
        $limit = min(max($limit, 1), 50); // Limit between 1 and 50

        try {
            $args = array(
                'post_type' => array('post', 'page'),
                'post_status' => 'publish',
                'posts_per_page' => $limit,
                'offset' => $offset,
                'orderby' => 'ID',
                'order' => 'ASC',
                'fields' => 'ids'
            );

            $query = new WP_Query($args);
            $total_posts = $query->found_posts;
            $converted = 0;
            $errors = array();

            foreach ($query->posts as $post_id) {
                try {
                    $result = $this->converter->convert_post_images($post_id);
                    if (is_wp_error($result)) {
                        throw new Exception($result->get_error_message());
                    }
                    if ($result['success']) {
                        $converted++;
                    } else {
                        $errors[] = sprintf(
                            __('Post %d: %s', 'kloudwebp'),
                            $post_id,
                            $result['message']
                        );
                    }
                } catch (Exception $e) {
                    $errors[] = sprintf(
                        __('Error converting post %d: %s', 'kloudwebp'),
                        $post_id,
                        $e->getMessage()
                    );
                    $this->log_debug('Error converting post ' . $post_id . ': ' . $e->getMessage(), 'error');
                }
            }

            wp_send_json_success(array(
                'converted' => $converted,
                'total' => $total_posts,
                'offset' => $offset + $limit,
                'hasMore' => ($offset + $limit) < $total_posts,
                'errors' => $errors
            ));
        } catch (Exception $e) {
            $this->log_debug('Bulk conversion error: ' . $e->getMessage(), 'error');
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
    }

    /**
     * AJAX handler for getting conversion status
     */
    public function ajax_get_conversion_status() {
        if (!check_ajax_referer('kloudwebp_ajax', 'nonce', false) || 
            !current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('Unauthorized access.', 'kloudwebp')
            ));
            return;
        }

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        if (!$post_id) {
            wp_send_json_error(array(
                'message' => __('Invalid post ID.', 'kloudwebp')
            ));
            return;
        }

        try {
            $status = get_post_meta($post_id, '_kloudwebp_status', true);
            wp_send_json_success(array(
                'status' => $status ? $status : 'pending'
            ));
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
    }
}
