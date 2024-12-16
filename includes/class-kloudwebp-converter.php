<?php

class KloudWebP_Converter {
    private $quality;
    private $keep_original;
    private $logger;

    public function __construct() {
        $this->quality = get_option('kloudwebp_quality', 80);
        $this->keep_original = get_option('kloudwebp_keep_original', true);
    }

    public function convert_image($file_path) {
        if (!file_exists($file_path)) {
            $this->log_error("File not found: $file_path");
            return false;
        }

        $image_type = wp_check_filetype($file_path)['type'];
        if (!in_array($image_type, ['image/jpeg', 'image/png'])) {
            $this->log_error("Unsupported image type: $image_type");
            return false;
        }

        try {
            if (extension_loaded('imagick')) {
                return $this->convert_with_imagick($file_path);
            } elseif (function_exists('imagewebp')) {
                return $this->convert_with_gd($file_path);
            } else {
                $this->log_error("No suitable image conversion library found");
                return false;
            }
        } catch (Exception $e) {
            $this->log_error("Conversion failed: " . $e->getMessage());
            return false;
        }
    }

    private function convert_with_imagick($file_path) {
        $image = new Imagick($file_path);
        $image->setImageFormat('webp');
        $image->setImageCompressionQuality($this->quality);
        
        $webp_path = $this->get_webp_path($file_path);
        $success = $image->writeImage($webp_path);
        $image->destroy();

        if ($success) {
            if (!$this->keep_original) {
                unlink($file_path);
            }
            return $webp_path;
        }
        return false;
    }

    private function convert_with_gd($file_path) {
        $image_type = wp_check_filetype($file_path)['type'];
        
        switch ($image_type) {
            case 'image/jpeg':
                $image = imagecreatefromjpeg($file_path);
                break;
            case 'image/png':
                $image = imagecreatefrompng($file_path);
                imagepalettetotruecolor($image);
                imagealphablending($image, true);
                imagesavealpha($image, true);
                break;
            default:
                return false;
        }

        $webp_path = $this->get_webp_path($file_path);
        $success = imagewebp($image, $webp_path, $this->quality);
        imagedestroy($image);

        if ($success) {
            if (!$this->keep_original) {
                unlink($file_path);
            }
            return $webp_path;
        }
        return false;
    }

    private function get_webp_path($file_path) {
        return preg_replace('/\.(jpe?g|png)$/i', '.webp', $file_path);
    }

    private function log_error($message) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("[KloudWebP] $message");
        }
    }

    public function bulk_convert() {
        $args = array(
            'post_type' => 'attachment',
            'post_mime_type' => array('image/jpeg', 'image/png'),
            'posts_per_page' => -1,
            'fields' => 'ids',
        );

        $attachments = get_posts($args);
        $results = array(
            'success' => 0,
            'failed' => 0,
            'skipped' => 0
        );

        foreach ($attachments as $attachment_id) {
            $file = get_attached_file($attachment_id);
            if ($this->convert_image($file)) {
                $results['success']++;
            } else {
                $results['failed']++;
            }
        }

        return $results;
    }
}
