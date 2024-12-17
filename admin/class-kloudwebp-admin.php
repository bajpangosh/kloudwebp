<?php
/**
 * KloudWebP Admin Class
 * Handles all admin functionality including dashboard and settings
 */
class KloudWebP_Admin {
    /**
     * Initialize the admin class
     */
    public function __construct() {
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
        register_setting('kloudwebp_settings', 'kloudwebp_compression_quality');
        register_setting('kloudwebp_settings', 'kloudwebp_update_content');
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
            'nonce' => wp_create_nonce('kloudwebp-ajax-nonce'),
            'strings' => array(
                'converting' => __('Converting...', 'kloudwebp'),
                'converted' => __('Converted', 'kloudwebp'),
                'error' => __('Error', 'kloudwebp'),
            )
        ));
    }

    /**
     * Render dashboard page
     */
    public function render_dashboard_page() {
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
                </table>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render conversion table
     */
    private function render_conversion_table() {
        $posts_per_page = 20;
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        
        $args = array(
            'post_type' => array('post', 'page'),
            'posts_per_page' => $posts_per_page,
            'paged' => $current_page,
            'orderby' => 'date',
            'order' => 'DESC',
        );
        
        $query = new WP_Query($args);
        
        if ($query->have_posts()) :
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
                <?php while ($query->have_posts()) : $query->the_post(); ?>
                    <?php
                    $post_id = get_the_ID();
                    $status = $this->get_conversion_status($post_id);
                    $status_class = $this->get_status_class($status);
                    ?>
                    <tr id="post-<?php echo $post_id; ?>">
                        <td><?php echo $post_id; ?></td>
                        <td>
                            <strong>
                                <a href="<?php echo get_edit_post_link($post_id); ?>">
                                    <?php echo get_the_title(); ?>
                                </a>
                            </strong>
                        </td>
                        <td><?php echo get_post_type(); ?></td>
                        <td class="kloudwebp-status <?php echo $status_class; ?>">
                            <?php echo $this->get_status_label($status); ?>
                        </td>
                        <td>
                            <button class="button kloudwebp-convert-single" 
                                    data-post-id="<?php echo $post_id; ?>">
                                <?php _e('Convert to WebP', 'kloudwebp'); ?>
                            </button>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        
        <?php
        echo '<div class="tablenav bottom">';
        echo '<div class="tablenav-pages">';
        echo paginate_links(array(
            'base' => add_query_arg('paged', '%#%'),
            'format' => '',
            'prev_text' => __('&laquo;'),
            'next_text' => __('&raquo;'),
            'total' => $query->max_num_pages,
            'current' => $current_page,
        ));
        echo '</div>';
        echo '</div>';
        
        wp_reset_postdata();
        
        else:
            echo '<p>' . __('No posts or pages found.', 'kloudwebp') . '</p>';
        endif;
    }

    /**
     * Get conversion status for a post
     */
    private function get_conversion_status($post_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'kloudwebp_conversions';
        
        $status = $wpdb->get_var($wpdb->prepare(
            "SELECT status FROM $table_name WHERE post_id = %d",
            $post_id
        ));
        
        return $status ? $status : 'not_converted';
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
        check_ajax_referer('kloudwebp-ajax-nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        if (!$post_id) {
            wp_send_json_error('Invalid post ID');
        }

        // Initialize the converter and process images
        require_once KLOUDWEBP_PLUGIN_DIR . 'includes/class-kloudwebp-converter.php';
        $converter = new KloudWebP_Converter();
        $result = $converter->convert_post_images($post_id);

        wp_send_json_success($result);
    }

    /**
     * AJAX handler for bulk conversion
     */
    public function ajax_convert_bulk() {
        check_ajax_referer('kloudwebp-ajax-nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        // Get posts/pages that need conversion
        $args = array(
            'post_type' => array('post', 'page'),
            'posts_per_page' => -1,
            'fields' => 'ids',
        );

        $post_ids = get_posts($args);
        
        wp_send_json_success(array(
            'total' => count($post_ids),
            'ids' => $post_ids,
        ));
    }

    /**
     * AJAX handler for getting conversion status
     */
    public function ajax_get_conversion_status() {
        check_ajax_referer('kloudwebp-ajax-nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        if (!$post_id) {
            wp_send_json_error('Invalid post ID');
        }

        $status = $this->get_conversion_status($post_id);
        wp_send_json_success(array(
            'status' => $status,
            'label' => $this->get_status_label($status),
            'class' => $this->get_status_class($status),
        ));
    }
}
