<?php

class KloudWebP_Admin {
    private $plugin_name;
    private $version;
    private $converter;

    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->converter = new KloudWebP_Converter();

        // Register settings
        add_action('admin_init', array($this, 'register_settings'));
        
        // Add menu
        add_action('admin_menu', array($this, 'add_plugin_menu'));
        
        // Add settings link
        add_filter('plugin_action_links_' . plugin_basename(plugin_dir_path(__DIR__) . $this->plugin_name . '.php'),
            array($this, 'add_action_links')
        );

        // Handle post conversion
        add_action('admin_post_kloudwebp_convert_posts', array($this, 'handle_convert_posts'));
        add_action('wp_ajax_kloudwebp_convert_single_post', array($this, 'ajax_convert_single_post'));
        
        // Add admin notices
        add_action('admin_notices', array($this, 'admin_notices'));

        // Handle image uploads
        add_filter('wp_handle_upload_prefilter', array($this, 'pre_upload'));
        add_filter('wp_handle_upload', array($this, 'handle_upload'));
        add_filter('wp_update_attachment_metadata', array($this, 'update_attachment_metadata'), 10, 2);
        
        // Filter image URLs
        add_filter('wp_get_attachment_url', array($this, 'filter_attachment_url'), 10, 2);
        add_filter('wp_get_attachment_image_src', array($this, 'filter_attachment_image_src'), 10, 4);
        
        // Enqueue scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));

        // Register AJAX handlers
        add_action('wp_ajax_kloudwebp_convert_post', array($this, 'handle_post_conversion'));
        
        // Register admin-post handler for bulk conversion
        add_action('admin_post_kloudwebp_bulk_convert', array($this, 'handle_bulk_conversion'));
    }

    public function register_settings() {
        register_setting(
            'kloudwebp_settings',
            'kloudwebp_settings',
            array($this, 'sanitize_settings')
        );

        add_settings_section(
            'kloudwebp_general_settings',
            __('General Settings', 'kloudwebp'),
            array($this, 'render_settings_section'),
            'kloudwebp_settings'
        );

        add_settings_field(
            'auto_convert',
            __('Auto Convert Images', 'kloudwebp'),
            array($this, 'render_auto_convert_field'),
            'kloudwebp_settings',
            'kloudwebp_general_settings'
        );

        add_settings_field(
            'keep_original',
            __('Keep Original Images', 'kloudwebp'),
            array($this, 'render_keep_original_field'),
            'kloudwebp_settings',
            'kloudwebp_general_settings'
        );

        add_settings_field(
            'quality',
            __('WebP Quality', 'kloudwebp'),
            array($this, 'render_quality_field'),
            'kloudwebp_settings',
            'kloudwebp_general_settings'
        );
    }

    public function render_settings_section() {
        echo '<p>' . __('Configure how KloudWebP handles image conversion.', 'kloudwebp') . '</p>';
    }

    public function render_auto_convert_field() {
        $options = get_option('kloudwebp_settings');
        $auto_convert = isset($options['auto_convert']) ? $options['auto_convert'] : 1;
        ?>
        <label>
            <input type="checkbox" name="kloudwebp_settings[auto_convert]" value="1" <?php checked(1, $auto_convert); ?>>
            <?php _e('Automatically convert images to WebP upon upload', 'kloudwebp'); ?>
        </label>
        <p class="description">
            <?php _e('When enabled, images will be automatically converted to WebP format when uploaded to the media library.', 'kloudwebp'); ?>
        </p>
        <?php
    }

    public function render_keep_original_field() {
        $options = get_option('kloudwebp_settings');
        $keep_original = isset($options['keep_original']) ? $options['keep_original'] : 1;
        ?>
        <label>
            <input type="checkbox" name="kloudwebp_settings[keep_original]" value="1" <?php checked(1, $keep_original); ?>>
            <?php _e('Keep original images after conversion', 'kloudwebp'); ?>
        </label>
        <p class="description">
            <?php _e('When enabled, original JPEG/PNG files will be kept after converting to WebP. Recommended for compatibility.', 'kloudwebp'); ?>
        </p>
        <?php
    }

    public function render_quality_field() {
        $options = get_option('kloudwebp_settings');
        $quality = isset($options['quality']) ? $options['quality'] : 80;
        ?>
        <input type="number" 
               name="kloudwebp_settings[quality]" 
               value="<?php echo esc_attr($quality); ?>"
               min="1" 
               max="100" 
               step="1">
        <p class="description">
            <?php _e('WebP conversion quality (1-100). Higher values mean better quality but larger file size. Default is 80.', 'kloudwebp'); ?>
        </p>
        <?php
    }

    public function sanitize_settings($input) {
        $sanitized = array();
        
        // Auto convert setting
        $sanitized['auto_convert'] = isset($input['auto_convert']) ? 1 : 0;
        
        // Keep original setting
        $sanitized['keep_original'] = isset($input['keep_original']) ? 1 : 0;
        
        // Quality setting
        $quality = isset($input['quality']) ? intval($input['quality']) : 80;
        $sanitized['quality'] = min(max($quality, 1), 100); // Ensure between 1 and 100
        
        return $sanitized;
    }

    public function add_plugin_menu() {
        // Add main menu item
        add_menu_page(
            'KloudWebP',
            'KloudWebP',
            'manage_options',
            $this->plugin_name,
            array($this, 'display_dashboard_page'),
            'dashicons-images-alt2'
        );

        // Add submenu items
        add_submenu_page(
            $this->plugin_name,
            'Dashboard',
            'Dashboard',
            'manage_options',
            $this->plugin_name,
            array($this, 'display_dashboard_page')
        );

        add_submenu_page(
            $this->plugin_name,
            'Settings',
            'Settings',
            'manage_options',
            $this->plugin_name . '-settings',
            array($this, 'display_settings_page')
        );
    }

    public function add_action_links($links) {
        $settings_link = array(
            '<a href="' . admin_url('options-general.php?page=' . $this->plugin_name . '-settings') . '">' . __('Settings', 'kloudwebp') . '</a>',
        );
        return array_merge($settings_link, $links);
    }

    public function display_dashboard_page() {
        require_once plugin_dir_path(dirname(__FILE__)) . 'admin/partials/kloudwebp-admin-dashboard.php';
    }

    public function display_settings_page() {
        require_once plugin_dir_path(dirname(__FILE__)) . 'admin/partials/kloudwebp-admin-settings.php';
    }

    private function get_conversion_stats() {
        $total_images = wp_count_attachments('image');
        $total_count = 0;
        foreach (['image/jpeg', 'image/png'] as $mime) {
            $total_count += isset($total_images->$mime) ? $total_images->$mime : 0;
        }

        $args = array(
            'post_type' => 'attachment',
            'post_mime_type' => array('image/jpeg', 'image/png'),
            'posts_per_page' => -1,
            'fields' => 'ids',
        );
        $attachments = get_posts($args);

        $converted_count = 0;
        $total_size_original = 0;
        $total_size_webp = 0;

        foreach ($attachments as $attachment_id) {
            $file = get_attached_file($attachment_id);
            if (!$file) continue;

            $webp_path = preg_replace('/\.(jpe?g|png)$/i', '.webp', $file);
            
            if (file_exists($file)) {
                $total_size_original += filesize($file);
            }
            
            if (file_exists($webp_path)) {
                $converted_count++;
                $total_size_webp += filesize($webp_path);
            }
        }

        return array(
            'total_images' => $total_count,
            'converted_images' => $converted_count,
            'unconverted_images' => $total_count - $converted_count,
            'total_size_original' => $total_size_original,
            'total_size_webp' => $total_size_webp,
            'size_saved' => $total_size_original - $total_size_webp,
            'conversion_rate' => $total_count > 0 ? ($converted_count / $total_count) * 100 : 0
        );
    }

    public function convert_uploaded_image($metadata, $attachment_id) {
        if (!get_option('kloudwebp_settings', array())['auto_convert']) {
            return $metadata;
        }

        $file = get_attached_file($attachment_id);
        $mime_type = get_post_mime_type($attachment_id);

        if (!in_array($mime_type, ['image/jpeg', 'image/png'])) {
            return $metadata;
        }

        $webp_path = $this->converter->convert_image($file);
        if ($webp_path) {
            // Update metadata if original was replaced
            if (!get_option('kloudwebp_settings', array())['keep_original']) {
                $metadata['file'] = wp_relative_upload_path($webp_path);
                $metadata['mime-type'] = 'image/webp';
            }
        }

        return $metadata;
    }

    public function handle_convert_posts() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        check_admin_referer('kloudwebp_convert_posts');

        global $wpdb;
        
        // Get all posts and pages
        $posts = $wpdb->get_results("
            SELECT ID, post_content 
            FROM {$wpdb->posts} 
            WHERE post_type IN ('post', 'page') 
            AND post_status = 'publish'
        ");

        $results = array(
            'success' => 0,
            'failed' => 0,
            'skipped' => 0,
            'updated_posts' => 0
        );

        $processed_urls = array();

        foreach ($posts as $post) {
            $content_updated = false;
            $content = $post->post_content;

            // Find all img tags in the content
            preg_match_all('/<img[^>]+src=([\'"])?([^\'">]+)/', $content, $matches);
            
            if (!empty($matches[2])) {
                foreach ($matches[2] as $url) {
                    // Skip if already processed this URL
                    if (in_array($url, $processed_urls)) {
                        continue;
                    }
                    
                    // Convert URL to file path
                    $file_path = $this->url_to_path($url);
                    if (!$file_path) {
                        $results['skipped']++;
                        continue;
                    }

                    // Check if it's a JPEG or PNG
                    if (preg_match('/\.(jpe?g|png)$/i', $file_path) && file_exists($file_path)) {
                        $webp_path = $this->converter->convert_image($file_path, false);
                        
                        if ($webp_path) {
                            // Get the WebP URL
                            $webp_url = str_replace(
                                wp_get_upload_dir()['basedir'],
                                wp_get_upload_dir()['baseurl'],
                                $webp_path
                            );

                            // Update image src in content
                            $content = str_replace($url, $webp_url, $content);
                            $content_updated = true;
                            $results['success']++;
                        } else {
                            $results['failed']++;
                        }
                    } else {
                        $results['skipped']++;
                    }

                    $processed_urls[] = $url;
                }
            }

            // Update post content if changed
            if ($content_updated) {
                wp_update_post(array(
                    'ID' => $post->ID,
                    'post_content' => $content
                ));
                $results['updated_posts']++;
            }
        }

        // Redirect back to dashboard with results
        set_transient('kloudwebp_conversion_results', $results);
        wp_redirect(add_query_arg(
            array(
                'page' => $this->plugin_name,
                'conversion_results' => true
            ),
            admin_url('admin.php')
        ));
        exit;
    }

    public function pre_upload($file) {
        // Check if auto-convert is enabled
        if (!get_option('kloudwebp_settings', array())['auto_convert']) {
            return $file;
        }

        if (!in_array($file['type'], ['image/jpeg', 'image/png'])) {
            return $file;
        }

        // Change the filename to .webp before upload
        $file['name'] = preg_replace('/\.(jpe?g|png)$/i', '.webp', $file['name']);
        return $file;
    }

    public function handle_upload($upload, $context = 'upload') {
        // Check if auto-convert is enabled
        if (!get_option('kloudwebp_settings', array())['auto_convert']) {
            return $upload;
        }

        // Only process image uploads
        if (!in_array($upload['type'], ['image/jpeg', 'image/png'])) {
            return $upload;
        }

        // Get file extension
        $ext = pathinfo($upload['file'], PATHINFO_EXTENSION);
        if (!in_array(strtolower($ext), ['jpg', 'jpeg', 'png'])) {
            return $upload;
        }

        // Generate WebP filename
        $webp_file = preg_replace('/\.(jpe?g|png)$/i', '.webp', $upload['file']);
        $webp_url = preg_replace('/\.(jpe?g|png)$/i', '.webp', $upload['url']);

        // Convert the uploaded image
        if ($this->converter->convert_image($upload['file'], false)) {
            // If conversion successful and not keeping original
            if (!get_option('kloudwebp_settings', array())['keep_original']) {
                // Rename the file to .webp
                rename($upload['file'], $webp_file);
                
                // Update the upload array
                $upload['file'] = $webp_file;
                $upload['url'] = $webp_url;
                $upload['type'] = 'image/webp';
            }
        }

        return $upload;
    }

    public function update_attachment_metadata($metadata, $attachment_id) {
        // Check if auto-convert is enabled
        if (!get_option('kloudwebp_settings', array())['auto_convert']) {
            return $metadata;
        }

        if (!is_array($metadata) || !isset($metadata['file'])) {
            return $metadata;
        }

        // Get the full path to the main image file
        $upload_dir = wp_upload_dir();
        $file_path = path_join($upload_dir['basedir'], $metadata['file']);
        
        // Check if this is a JPEG or PNG
        $mime_type = get_post_mime_type($attachment_id);
        if (!in_array($mime_type, ['image/jpeg', 'image/png'])) {
            return $metadata;
        }

        // Convert main image if not already converted
        if (!preg_match('/\.webp$/', $file_path)) {
            $webp_path = $this->converter->convert_image($file_path, true);
            if ($webp_path && !get_option('kloudwebp_settings', array())['keep_original']) {
                $metadata['file'] = str_replace($upload_dir['basedir'] . '/', '', $webp_path);
                $metadata['mime-type'] = 'image/webp';
                
                // Update post mime type
                wp_update_post(array(
                    'ID' => $attachment_id,
                    'post_mime_type' => 'image/webp'
                ));

                // Delete original if not keeping it
                if (file_exists($file_path) && !get_option('kloudwebp_settings', array())['keep_original']) {
                    @unlink($file_path);
                }
            }
        }

        // Convert all image sizes
        if (isset($metadata['sizes'])) {
            $base_dir = dirname($file_path);
            
            foreach ($metadata['sizes'] as $size => $size_info) {
                $size_file = path_join($base_dir, $size_info['file']);
                
                if (!preg_match('/\.webp$/', $size_file)) {
                    $size_webp = $this->converter->convert_image($size_file, false);
                    if ($size_webp && !get_option('kloudwebp_settings', array())['keep_original']) {
                        $metadata['sizes'][$size]['file'] = basename($size_webp);
                        $metadata['sizes'][$size]['mime-type'] = 'image/webp';
                        
                        // Delete original size if not keeping it
                        if (file_exists($size_file) && !get_option('kloudwebp_settings', array())['keep_original']) {
                            @unlink($size_file);
                        }
                    }
                }
            }
        }

        return $metadata;
    }

    public function filter_attachment_url($url, $attachment_id) {
        // Check if auto-convert is enabled
        if (!get_option('kloudwebp_settings', array())['auto_convert']) {
            return $url;
        }

        // Check if this is an image
        if (!wp_attachment_is_image($attachment_id)) {
            return $url;
        }

        // Get the WebP version URL if it exists
        $webp_url = preg_replace('/\.(jpe?g|png)$/i', '.webp', $url);
        $file_path = str_replace(wp_get_upload_dir()['basedir'], wp_get_upload_dir()['baseurl'], $webp_url);

        if (file_exists($file_path)) {
            return $webp_url;
        }

        return $url;
    }

    public function filter_attachment_image_src($image, $attachment_id, $size, $icon) {
        // Check if auto-convert is enabled
        if (!get_option('kloudwebp_settings', array())['auto_convert']) {
            return $image;
        }

        if (!$image) {
            return $image;
        }

        // Check if this is an image
        if (!wp_attachment_is_image($attachment_id)) {
            return $image;
        }

        // Get the WebP version URL if it exists
        $webp_url = preg_replace('/\.(jpe?g|png)$/i', '.webp', $image[0]);
        $file_path = str_replace(wp_get_upload_dir()['basedir'], wp_get_upload_dir()['baseurl'], $webp_url);

        if (file_exists($file_path)) {
            $image[0] = $webp_url;
        }

        return $image;
    }

    private function url_to_path($url) {
        // Remove query strings
        $url = preg_replace('/\?.*/', '', $url);
        
        // Get upload directory info
        $upload_dir = wp_upload_dir();
        
        // Convert URL to path
        $path = str_replace(
            array($upload_dir['baseurl'], site_url()),
            array($upload_dir['basedir'], ABSPATH),
            $url
        );
        
        return file_exists($path) ? $path : false;
    }

    /**
     * Get posts and pages with their conversion status
     */
    public function get_posts_conversion_status() {
        global $wpdb;
        
        // Get all published posts and pages
        $posts = $wpdb->get_results("
            SELECT ID, post_title, post_type, post_modified 
            FROM {$wpdb->posts} 
            WHERE post_type IN ('post', 'page') 
            AND post_status = 'publish'
            ORDER BY post_modified DESC
        ");

        return $posts;
    }

    /**
     * Get total images count for a specific post or all posts
     */
    public function get_total_images_count($post_id = null) {
        global $wpdb;

        if ($post_id) {
            // Get images from post content
            $post = get_post($post_id);
            if (!$post) return 0;

            $content = $post->post_content;
            preg_match_all('/<img[^>]+src=([\'"])?([^\'">]+)/', $content, $matches);
            return count($matches[2]);
        } else {
            // Get all images from media library
            $query = "SELECT COUNT(*) FROM $wpdb->posts WHERE post_type = 'attachment' 
                     AND (post_mime_type LIKE 'image/jpeg' OR post_mime_type LIKE 'image/png')";
            return (int) $wpdb->get_var($query);
        }
    }

    /**
     * Get converted images count for a specific post or all posts
     */
    public function get_converted_images_count($post_id = null) {
        global $wpdb;

        if ($post_id) {
            // Get converted images from post content
            $post = get_post($post_id);
            if (!$post) return 0;

            $content = $post->post_content;
            preg_match_all('/<img[^>]+src=([\'"])?([^\'">]+\.webp)/', $content, $matches);
            return count($matches[2]);
        } else {
            // Get all converted images
            $upload_dir = wp_upload_dir();
            $webp_files = glob($upload_dir['basedir'] . '/**/*.webp');
            return count($webp_files);
        }
    }

    /**
     * Get total space saved by WebP conversion
     */
    public function get_total_space_saved() {
        $upload_dir = wp_upload_dir();
        $webp_files = glob($upload_dir['basedir'] . '/**/*.webp');
        $total_saved = 0;

        foreach ($webp_files as $webp_file) {
            // Get original file path
            $original_file = preg_replace('/\.webp$/', '', $webp_file);
            if (file_exists($original_file)) {
                $original_size = filesize($original_file);
                $webp_size = filesize($webp_file);
                $total_saved += max(0, $original_size - $webp_size);
            }
        }

        return $total_saved;
    }

    public function convert_single_post($post_id) {
        if (!current_user_can('manage_options')) {
            return false;
        }

        $images = $this->get_post_images($post_id);
        $content = get_post_field('post_content', $post_id);
        $content_updated = false;
        $results = array(
            'success' => 0,
            'failed' => 0,
            'skipped' => 0
        );

        foreach ($images['unconverted'] as $image) {
            $webp_path = $this->converter->convert_image($image['path'], false);
            
            if ($webp_path) {
                // Get the WebP URL
                $webp_url = str_replace(
                    wp_get_upload_dir()['basedir'],
                    wp_get_upload_dir()['baseurl'],
                    $webp_path
                );

                // Update image src in content
                $content = str_replace($image['url'], $webp_url, $content);
                $content_updated = true;
                $results['success']++;
            } else {
                $results['failed']++;
            }
        }

        // Update post content if changed
        if ($content_updated) {
            wp_update_post(array(
                'ID' => $post_id,
                'post_content' => $content
            ));
        }

        return $results;
    }

    /**
     * AJAX handler for converting single post images
     */
    public function ajax_convert_single_post() {
        check_ajax_referer('kloudwebp_convert_post', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        if (!$post_id) {
            wp_send_json_error('Invalid post ID');
            return;
        }

        $content = get_post_field('post_content', $post_id);
        $content_updated = false;
        $results = array(
            'success' => 0,
            'failed' => 0,
            'skipped' => 0
        );

        // Find all img tags in the content
        preg_match_all('/<img[^>]+src=([\'"])?([^\'">]+)/', $content, $matches);
        
        if (!empty($matches[2])) {
            foreach ($matches[2] as $url) {
                // Convert URL to file path
                $file_path = $this->url_to_path($url);
                if (!$file_path) {
                    $results['skipped']++;
                    continue;
                }

                // Check if it's a JPEG or PNG
                if (preg_match('/\.(jpe?g|png)$/i', $file_path) && file_exists($file_path)) {
                    $webp_path = $this->converter->convert_image($file_path, false);
                    
                    if ($webp_path) {
                        // Get the WebP URL
                        $webp_url = str_replace(
                            wp_get_upload_dir()['basedir'],
                            wp_get_upload_dir()['baseurl'],
                            $webp_path
                        );

                        // Update image src in content
                        $content = str_replace($url, $webp_url, $content);
                        $content_updated = true;
                        $results['success']++;
                    } else {
                        $results['failed']++;
                    }
                } else {
                    $results['skipped']++;
                }
            }
        }

        // Update post content if changed
        if ($content_updated) {
            wp_update_post(array(
                'ID' => $post_id,
                'post_content' => $content
            ));
        }

        wp_send_json_success($results);
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_scripts($hook) {
        if (strpos($hook, $this->plugin_name) === false) {
            return;
        }

        wp_enqueue_style(
            $this->plugin_name,
            plugin_dir_url(__FILE__) . 'css/kloudwebp-admin.css',
            array(),
            $this->version,
            'all'
        );

        wp_enqueue_script(
            $this->plugin_name,
            plugin_dir_url(__FILE__) . 'js/kloudwebp-admin.js',
            array('jquery'),
            $this->version,
            true
        );

        wp_localize_script(
            $this->plugin_name,
            'kloudwebpAjax',
            array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('kloudwebp_convert_post'),
                'converting' => __('Converting...'),
                'error' => __('Error'),
                'success' => __('Converted')
            )
        );
    }

    /**
     * Display admin notices for conversion results and settings updates
     */
    public function admin_notices() {
        if (!isset($_GET['page']) || $_GET['page'] !== $this->plugin_name) {
            return;
        }

        // Display settings updated message
        if (isset($_GET['settings-updated']) && $_GET['settings-updated']) {
            ?>
            <div class="notice notice-success is-dismissible">
                <p><?php _e('Settings saved successfully.', 'kloudwebp'); ?></p>
            </div>
            <?php
        }

        // Display conversion results if available
        if (isset($_GET['conversion_results'])) {
            $results = get_transient('kloudwebp_conversion_results');
            if ($results) {
                $message = sprintf(
                    __('Images converted: %d successful, %d failed, %d skipped.', 'kloudwebp'),
                    intval($results['success']),
                    intval($results['failed']),
                    intval($results['skipped'])
                );
                ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php echo esc_html($message); ?></p>
                </div>
                <?php
                delete_transient('kloudwebp_conversion_results');
            }
        }
    }

    private function get_post_images($post_id) {
        $content = get_post_field('post_content', $post_id);
        $images = array(
            'all' => array(),
            'converted' => array(),
            'unconverted' => array()
        );

        // Find all img tags in the content
        preg_match_all('/<img[^>]+src=([\'"])?([^\'">]+)/', $content, $matches);
        
        if (!empty($matches[2])) {
            foreach ($matches[2] as $url) {
                $file_path = $this->url_to_path($url);
                if (!$file_path) {
                    continue;
                }

                $image_info = array(
                    'url' => $url,
                    'path' => $file_path
                );

                $images['all'][] = $image_info;

                // Check if WebP version exists
                $webp_path = preg_replace('/\.(jpe?g|png)$/i', '.webp', $file_path);
                if (file_exists($webp_path)) {
                    $images['converted'][] = $image_info;
                } else if (preg_match('/\.(jpe?g|png)$/i', $file_path)) {
                    $images['unconverted'][] = $image_info;
                }
            }
        }

        return $images;
    }

    /**
     * Register the JavaScript for the admin area.
     */
    // Removed duplicate enqueue_scripts() method

    /**
     * Handle single post conversion via AJAX
     */
    public function handle_post_conversion() {
        check_ajax_referer('kloudwebp_convert_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        if (!$post_id) {
            wp_send_json_error('Invalid post ID');
            return;
        }

        // Get all images in the post
        $images = $this->get_post_images($post_id);
        $converted = 0;
        $errors = array();
        
        foreach ($images as $image) {
            try {
                $result = $this->convert_image($image);
                if ($result) {
                    $converted++;
                }
            } catch (Exception $e) {
                $errors[] = $e->getMessage();
            }
        }
        
        wp_send_json_success(array(
            'message' => sprintf(__('%d images converted successfully'), $converted),
            'converted' => $converted,
            'total' => count($images),
            'errors' => $errors
        ));
    }

    /**
     * Handle bulk conversion of all images
     */
    public function handle_bulk_conversion() {
        if (!isset($_POST['kloudwebp_nonce']) || !wp_verify_nonce($_POST['kloudwebp_nonce'], 'kloudwebp_bulk_convert')) {
            wp_die(__('Security check failed'));
        }

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions'));
        }

        // Get all unconverted images
        $unconverted_images = $this->get_unconverted_images();
        $converted = 0;
        $errors = array();

        foreach ($unconverted_images as $image) {
            try {
                $result = $this->convert_image($image);
                if ($result) {
                    $converted++;
                }
            } catch (Exception $e) {
                $errors[] = $e->getMessage();
            }
        }

        // Add admin notice
        add_settings_error(
            'kloudwebp_messages',
            'kloudwebp_bulk_convert',
            sprintf(__('%d images converted successfully. %d errors occurred.'), $converted, count($errors)),
            $converted > 0 ? 'updated' : 'error'
        );

        // Redirect back to dashboard
        wp_redirect(admin_url('admin.php?page=' . $this->plugin_name));
        exit;
    }

    /**
     * Get all images in a post
     */
    private function get_post_images($post_id) {
        $post = get_post($post_id);
        $images = array();
        
        if (!$post) {
            return $images;
        }
        
        // Get images from post content
        if (has_blocks($post->post_content)) {
            $blocks = parse_blocks($post->post_content);
            $this->extract_images_from_blocks($blocks, $images);
        } else {
            preg_match_all('/<img[^>]+>/i', $post->post_content, $img_matches);
            foreach ($img_matches[0] as $img) {
                preg_match('/src=[\'"]([^\'"]+)/i', $img, $src_match);
                if (isset($src_match[1])) {
                    $images[] = $src_match[1];
                }
            }
        }
        
        // Get featured image
        if (has_post_thumbnail($post_id)) {
            $featured_image_id = get_post_thumbnail_id($post_id);
            $featured_image = wp_get_attachment_image_src($featured_image_id, 'full');
            if ($featured_image) {
                $images[] = $featured_image[0];
            }
        }
        
        return array_unique($images);
    }

    /**
     * Extract images from Gutenberg blocks recursively
     */
    private function extract_images_from_blocks($blocks, &$images) {
        foreach ($blocks as $block) {
            if ($block['blockName'] === 'core/image') {
                if (!empty($block['attrs']['url'])) {
                    $images[] = $block['attrs']['url'];
                }
            }
            if (!empty($block['innerBlocks'])) {
                $this->extract_images_from_blocks($block['innerBlocks'], $images);
            }
        }
    }

    /**
     * Get all unconverted images from the media library
     */
    private function get_unconverted_images() {
        $args = array(
            'post_type' => 'attachment',
            'post_mime_type' => array('image/jpeg', 'image/png'),
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => '_webp_converted',
                    'compare' => 'NOT EXISTS'
                )
            )
        );
        
        $query = new WP_Query($args);
        $images = array();
        
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $images[] = wp_get_attachment_url(get_the_ID());
            }
        }
        
        wp_reset_postdata();
        return $images;
    }
}
