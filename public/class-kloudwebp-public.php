<?php

class KloudWebP_Public {
    private $plugin_name;
    private $version;

    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    public function filter_image_src($image, $attachment_id, $size, $icon) {
        if (!$this->should_serve_webp()) {
            return $image;
        }

        if (!$image) {
            return $image;
        }

        $webp_url = $this->get_webp_url($image[0]);
        if ($webp_url) {
            $image[0] = $webp_url;
        }

        return $image;
    }

    public function filter_image_srcset($sources, $size_array, $image_src, $image_meta, $attachment_id) {
        if (!$this->should_serve_webp()) {
            return $sources;
        }

        foreach ($sources as &$source) {
            $webp_url = $this->get_webp_url($source['url']);
            if ($webp_url) {
                $source['url'] = $webp_url;
            }
        }

        return $sources;
    }

    private function should_serve_webp() {
        // Check if browser supports WebP
        if (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'image/webp') !== false) {
            return true;
        }

        // Check for WebP support in User Agent (for older browsers)
        if (isset($_SERVER['HTTP_USER_AGENT'])) {
            if (strpos($_SERVER['HTTP_USER_AGENT'], 'Chrome/') !== false) {
                return true;
            }
        }

        return false;
    }

    private function get_webp_url($url) {
        // Get the file path from URL
        $file_path = $this->get_file_path_from_url($url);
        if (!$file_path) {
            return false;
        }

        // Check if WebP version exists
        $webp_path = preg_replace('/\.(jpe?g|png)$/i', '.webp', $file_path);
        if (!file_exists($webp_path)) {
            return false;
        }

        // Convert file path back to URL
        return preg_replace('/\.(jpe?g|png)$/i', '.webp', $url);
    }

    private function get_file_path_from_url($url) {
        // Get upload directory info
        $upload_dir = wp_upload_dir();
        
        // Convert URL to file path
        $file_path = str_replace(
            $upload_dir['baseurl'],
            $upload_dir['basedir'],
            $url
        );

        return $file_path;
    }
}
