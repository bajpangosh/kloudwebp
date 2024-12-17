<?php

class KloudWebP_Admin {
    private $plugin_name;
    private $version;
    private $converter;

    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->converter = new KloudWebP_Converter();

        // Add menu
        add_action('admin_menu', array($this, 'add_plugin_menu'));
        
        // Register settings
        add_action('admin_init', array($this, 'register_settings'));
        
        // Add settings link
        add_filter('plugin_action_links_' . plugin_basename(KLOUDWEBP_PLUGIN_DIR . $this->plugin_name . '.php'),
            array($this, 'add_action_links')
        );

        // Handle AJAX actions
        add_action('wp_ajax_kloudwebp_convert_post', array($this, 'handle_post_conversion'));
        add_action('wp_ajax_kloudwebp_get_stats', array($this, 'handle_get_stats'));
        
        // Enqueue scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    public function add_plugin_menu() {
        add_menu_page(
            __('KloudWebP', 'kloudwebp'),
            __('KloudWebP', 'kloudwebp'),
            'manage_options',
            $this->plugin_name,
            array($this, 'display_dashboard_page'),
            'dashicons-images-alt2'
        );

        add_submenu_page(
            $this->plugin_name,
            __('Settings', 'kloudwebp'),
            __('Settings', 'kloudwebp'),
            'manage_options',
            $this->plugin_name . '-settings',
            array($this, 'display_settings_page')
        );
    }

    public function register_settings() {
        register_setting(
            'kloudwebp_settings',
            'kloudwebp_settings',
            array($this, 'sanitize_settings')
        );

        add_settings_section(
            'kloudwebp_general_section',
            __('General Settings', 'kloudwebp'),
            array($this, 'render_settings_section'),
            'kloudwebp_settings'
        );

        add_settings_field(
            'kloudwebp_quality',
            __('WebP Quality', 'kloudwebp'),
            array($this, 'render_quality_field'),
            'kloudwebp_settings',
            'kloudwebp_general_section'
        );

        add_settings_field(
            'kloudwebp_keep_original',
            __('Keep Original Images', 'kloudwebp'),
            array($this, 'render_keep_original_field'),
            'kloudwebp_settings',
            'kloudwebp_general_section'
        );
    }

    public function sanitize_settings($input) {
        $sanitized = array();
        
        if (isset($input['quality'])) {
            $sanitized['quality'] = intval($input['quality']);
            if ($sanitized['quality'] < 1 || $sanitized['quality'] > 100) {
                $sanitized['quality'] = 80;
            }
        }
        
        if (isset($input['keep_original'])) {
            $sanitized['keep_original'] = (bool) $input['keep_original'];
        }
        
        return $sanitized;
    }

    public function enqueue_scripts($hook) {
        // Only load on plugin pages
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
                'nonce' => wp_create_nonce('kloudwebp_convert_nonce'),
                'converting' => __('Converting...', 'kloudwebp'),
                'convert' => __('Convert', 'kloudwebp'),
                'error' => __('Error', 'kloudwebp'),
                'success' => __('Converted', 'kloudwebp')
            )
        );
    }

    public function display_dashboard_page() {
        require_once plugin_dir_path(__FILE__) . 'partials/kloudwebp-admin-dashboard.php';
    }

    public function display_settings_page() {
        require_once plugin_dir_path(__FILE__) . 'partials/kloudwebp-admin-settings.php';
    }

    public function get_posts_conversion_status() {
        $args = array(
            'post_type' => array('post', 'page'),
            'post_status' => 'publish',
            'posts_per_page' => -1
        );

        $posts = get_posts($args);
        return $posts;
    }

    public function get_post_images($post_id) {
        $post = get_post($post_id);
        $images = array(
            'all' => array(),
            'converted' => array(),
            'unconverted' => array()
        );
        
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
                    $images['all'][] = $src_match[1];
                }
            }
        }
        
        // Get featured image
        if (has_post_thumbnail($post_id)) {
            $featured_image_id = get_post_thumbnail_id($post_id);
            $featured_image = wp_get_attachment_image_src($featured_image_id, 'full');
            if ($featured_image) {
                $images['all'][] = $featured_image[0];
            }
        }
        
        // Process images
        foreach ($images['all'] as $image_url) {
            $file_path = $this->url_to_path($image_url);
            if ($file_path) {
                $webp_path = preg_replace('/\.(jpe?g|png)$/i', '.webp', $file_path);
                if (file_exists($webp_path)) {
                    $images['converted'][] = array(
                        'url' => $image_url,
                        'path' => $file_path
                    );
                } else {
                    $images['unconverted'][] = array(
                        'url' => $image_url,
                        'path' => $file_path
                    );
                }
            }
        }

        return $images;
    }

    private function extract_images_from_blocks($blocks, &$images) {
        foreach ($blocks as $block) {
            if ($block['blockName'] === 'core/image') {
                if (!empty($block['attrs']['url'])) {
                    $images['all'][] = $block['attrs']['url'];
                }
            }
            if (!empty($block['innerBlocks'])) {
                $this->extract_images_from_blocks($block['innerBlocks'], $images);
            }
        }
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

        try {
            $images = $this->get_post_images($post_id);
            $converted = 0;
            $errors = array();
            $total = count($images['unconverted']);
            
            foreach ($images['unconverted'] as $image) {
                try {
                    $result = $this->converter->convert_image($image['path'], false);
                    if ($result) {
                        $converted++;
                    }
                } catch (Exception $e) {
                    $errors[] = $e->getMessage();
                }
            }
            
            wp_send_json_success(array(
                'message' => sprintf(__('%d out of %d images converted successfully', 'kloudwebp'), $converted, $total),
                'converted' => $converted,
                'total' => $total,
                'errors' => $errors
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage(),
                'errors' => array($e->getMessage())
            ));
        }
    }

    public function handle_get_stats() {
        check_ajax_referer('kloudwebp_convert_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        wp_send_json_success(array(
            'total_images' => $this->get_total_images_count(),
            'converted_images' => $this->get_converted_images_count(),
            'space_saved' => size_format($this->get_total_space_saved())
        ));
    }

    public function get_total_images_count() {
        $args = array(
            'post_type' => 'attachment',
            'post_mime_type' => array('image/jpeg', 'image/png'),
            'posts_per_page' => -1,
            'fields' => 'ids'
        );
        
        $query = new WP_Query($args);
        return $query->found_posts;
    }

    public function get_converted_images_count() {
        $args = array(
            'post_type' => 'attachment',
            'post_mime_type' => array('image/jpeg', 'image/png'),
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => '_webp_converted',
                    'compare' => 'EXISTS'
                )
            ),
            'fields' => 'ids'
        );
        
        $query = new WP_Query($args);
        return $query->found_posts;
    }

    public function get_total_space_saved() {
        $args = array(
            'post_type' => 'attachment',
            'post_mime_type' => array('image/jpeg', 'image/png'),
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => '_webp_size_saved',
                    'compare' => 'EXISTS'
                )
            )
        );
        
        $query = new WP_Query($args);
        $total_saved = 0;
        
        foreach ($query->posts as $post) {
            $saved = get_post_meta($post->ID, '_webp_size_saved', true);
            if ($saved) {
                $total_saved += intval($saved);
            }
        }
        
        return $total_saved;
    }
}
