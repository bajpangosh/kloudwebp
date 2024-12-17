<?php

class KloudWebP_Converter {
    private $quality;
    private $keep_original;
    private $logger;

    public function __construct() {
        $this->quality = get_option('kloudwebp_quality', 80);
        $this->keep_original = get_option('kloudwebp_keep_original', true);
    }

    public function convert_image($file_path, $update_url = false) {
        if (!file_exists($file_path)) {
            $this->log_error("File not found: $file_path");
            return false;
        }

        $image_info = wp_check_filetype($file_path);
        $image_type = $image_info['type'];
        
        if (!in_array($image_type, ['image/jpeg', 'image/png'])) {
            $this->log_error("Unsupported image type: $image_type");
            return false;
        }

        try {
            $webp_path = $this->get_webp_path($file_path);
            
            // Create directory if it doesn't exist
            $webp_dir = dirname($webp_path);
            if (!file_exists($webp_dir)) {
                wp_mkdir_p($webp_dir);
            }

            // Check if we can write to the directory
            if (!is_writable($webp_dir)) {
                $this->log_error("Directory not writable: $webp_dir");
                return false;
            }

            $success = false;
            if (extension_loaded('imagick')) {
                $success = $this->convert_with_imagick($file_path, $webp_path);
            } elseif (function_exists('imagewebp')) {
                $success = $this->convert_with_gd($file_path, $webp_path);
            } else {
                $this->log_error("No suitable image conversion library found");
                return false;
            }

            if ($success) {
                // Verify the WebP file was created and is valid
                if (!file_exists($webp_path) || filesize($webp_path) === 0) {
                    $this->log_error("WebP file creation failed or file is empty: $webp_path");
                    @unlink($webp_path); // Clean up empty file
                    return false;
                }

                // Update attachment URL if requested
                if ($update_url) {
                    $this->update_attachment_url($file_path, $webp_path);
                }

                return $webp_path;
            }

            return false;
        } catch (Exception $e) {
            $this->log_error("Conversion failed: " . $e->getMessage());
            return false;
        }
    }

    private function convert_with_imagick($file_path, $webp_path) {
        try {
            $image = new Imagick($file_path);
            $image->setImageFormat('webp');
            $image->setImageCompressionQuality($this->quality);
            
            // Strip metadata to reduce file size
            $image->stripImage();
            
            // Optimize for web
            $image->setImageInterlaceScheme(Imagick::INTERLACE_NO);
            $image->setOption('webp:method', '6'); // Best compression
            
            $success = $image->writeImage($webp_path);
            $image->destroy();

            if ($success && !$this->keep_original) {
                @unlink($file_path);
            }

            return $success;
        } catch (Exception $e) {
            $this->log_error("ImageMagick conversion failed: " . $e->getMessage());
            return false;
        }
    }

    private function convert_with_gd($file_path, $webp_path) {
        try {
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

            if (!$image) {
                $this->log_error("Failed to create image resource from: $file_path");
                return false;
            }

            // Set the background color to white for PNGs with alpha channel
            if ($image_type === 'image/png') {
                $background = imagecreatetruecolor(imagesx($image), imagesy($image));
                $white = imagecolorallocate($background, 255, 255, 255);
                imagefill($background, 0, 0, $white);
                imagealphablending($background, true);
                imagesavealpha($background, true);
                imagecopy($background, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));
                imagedestroy($image);
                $image = $background;
            }

            $success = imagewebp($image, $webp_path, $this->quality);
            imagedestroy($image);

            if ($success && !$this->keep_original) {
                @unlink($file_path);
            }

            return $success;
        } catch (Exception $e) {
            $this->log_error("GD conversion failed: " . $e->getMessage());
            return false;
        }
    }

    private function get_webp_path($file_path) {
        return preg_replace('/\.(jpe?g|png)$/i', '.webp', $file_path);
    }

    private function log_error($message) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("[KloudWebP] $message");
        }
    }

    private function update_attachment_url($original_path, $webp_path) {
        // Check if auto-convert is enabled
        if (!get_option('kloudwebp_auto_convert', false)) {
            return false;
        }

        global $wpdb;
        
        // Get the attachment ID based on the original file path
        $upload_dir = wp_upload_dir();
        $relative_path = str_replace($upload_dir['basedir'] . '/', '', $original_path);
        
        $attachment_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_wp_attached_file' AND meta_value = %s",
            $relative_path
        ));

        if (!$attachment_id) {
            return false;
        }

        // Update the attachment metadata
        $webp_url = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $webp_path);
        $original_url = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $original_path);

        // Update guid and post mime type if we're replacing the original
        if (!$this->keep_original) {
            $wpdb->update(
                $wpdb->posts,
                array(
                    'guid' => $webp_url,
                    'post_mime_type' => 'image/webp'
                ),
                array('ID' => $attachment_id)
            );

            // Update _wp_attached_file
            update_post_meta($attachment_id, '_wp_attached_file', str_replace($upload_dir['basedir'] . '/', '', $webp_path));
        }

        // Update attachment metadata
        $metadata = wp_get_attachment_metadata($attachment_id);
        if (!empty($metadata)) {
            $old_file = basename($original_path);
            $new_file = basename($webp_path);
            
            // Update the main file
            $metadata['file'] = str_replace($old_file, $new_file, $metadata['file']);
            $metadata['mime-type'] = 'image/webp';
            
            // Update sizes if they exist
            if (!empty($metadata['sizes'])) {
                foreach ($metadata['sizes'] as $size => $size_info) {
                    $size_file = $size_info['file'];
                    $size_webp = preg_replace('/\.(jpe?g|png)$/i', '.webp', $size_file);
                    $metadata['sizes'][$size]['file'] = $size_webp;
                    $metadata['sizes'][$size]['mime-type'] = 'image/webp';
                }
            }
            
            wp_update_attachment_metadata($attachment_id, $metadata);
        }

        // Clear any caches
        clean_post_cache($attachment_id);
        
        return true;
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
            if ($this->convert_image($file, true)) {
                $results['success']++;
            } else {
                $results['failed']++;
            }
        }

        return $results;
    }
}
