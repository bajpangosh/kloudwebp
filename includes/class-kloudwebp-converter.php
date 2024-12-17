<?php

class KloudWebP_Converter {
    private $quality;
    private $keep_original;

    public function __construct() {
        $options = get_option('kloudwebp_settings', array());
        $this->quality = isset($options['quality']) ? $options['quality'] : 80;
        $this->keep_original = isset($options['keep_original']) ? $options['keep_original'] : true;
    }

    public function convert_image($file_path, $update_url = false) {
        if (!file_exists($file_path)) {
            error_log("KloudWebP: File not found: $file_path");
            return false;
        }

        $mime_type = wp_check_filetype($file_path)['type'];
        if (!in_array($mime_type, ['image/jpeg', 'image/png'])) {
            error_log("KloudWebP: Unsupported image type: $mime_type");
            return false;
        }

        try {
            $webp_path = preg_replace('/\.(jpe?g|png)$/i', '.webp', $file_path);
            
            // Create directory if it doesn't exist
            $webp_dir = dirname($webp_path);
            if (!file_exists($webp_dir)) {
                wp_mkdir_p($webp_dir);
            }

            // Check if we can write to the directory
            if (!is_writable($webp_dir)) {
                error_log("KloudWebP: Directory not writable: $webp_dir");
                return false;
            }

            $success = false;
            if (extension_loaded('imagick')) {
                $success = $this->convert_with_imagick($file_path, $webp_path);
            } elseif (function_exists('imagewebp')) {
                $success = $this->convert_with_gd($file_path, $webp_path);
            } else {
                error_log("KloudWebP: No suitable image conversion library found");
                return false;
            }

            if ($success) {
                // Verify the WebP file was created and is valid
                if (!file_exists($webp_path) || filesize($webp_path) === 0) {
                    error_log("KloudWebP: WebP file creation failed or file is empty: $webp_path");
                    @unlink($webp_path); // Clean up empty file
                    return false;
                }

                // Calculate space saved
                $original_size = filesize($file_path);
                $webp_size = filesize($webp_path);
                $saved = max(0, $original_size - $webp_size);

                // Store conversion status
                if ($update_url) {
                    $attachment_id = attachment_url_to_postid($file_path);
                    if ($attachment_id) {
                        update_post_meta($attachment_id, '_webp_converted', true);
                        update_post_meta($attachment_id, '_webp_size_saved', $saved);
                    }
                }

                return $webp_path;
            }

            return false;
        } catch (Exception $e) {
            error_log("KloudWebP: Conversion failed: " . $e->getMessage());
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
            
            $success = $image->writeImage($webp_path);
            $image->destroy();

            return $success;
        } catch (Exception $e) {
            error_log("KloudWebP: ImageMagick conversion failed: " . $e->getMessage());
            return false;
        }
    }

    private function convert_with_gd($file_path, $webp_path) {
        try {
            $mime_type = wp_check_filetype($file_path)['type'];
            
            switch ($mime_type) {
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
                error_log("KloudWebP: Failed to create image resource from: $file_path");
                return false;
            }

            $success = imagewebp($image, $webp_path, $this->quality);
            imagedestroy($image);

            return $success;
        } catch (Exception $e) {
            error_log("KloudWebP: GD conversion failed: " . $e->getMessage());
            return false;
        }
    }
}
