<?php
/**
 * KloudWebP Converter Class
 * Handles the actual image conversion process
 */
class KloudWebP_Converter {
    /**
     * Convert images in a post to WebP format
     */
    public function convert_post_images($post_id) {
        $post = get_post($post_id);
        if (!$post) {
            return array(
                'success' => false,
                'message' => 'Post not found'
            );
        }

        // Get all images from post content
        $images = $this->get_images_from_content($post->post_content);
        
        if (empty($images)) {
            $this->update_conversion_status($post_id, 'no_images');
            return array(
                'success' => true,
                'message' => 'No images found in post',
                'status' => 'no_images'
            );
        }

        $converted = 0;
        $failed = 0;
        $skipped = 0;
        $content = $post->post_content;
        $updated_urls = array();

        foreach ($images as $image) {
            $result = $this->convert_image($image['url']);
            
            if ($result['success']) {
                $converted++;
                
                // Store original and WebP URLs for replacement
                $updated_urls[$image['url']] = $result['webp_url'];
                
                // Update image srcset if exists
                if (!empty($image['srcset'])) {
                    $srcset_urls = explode(',', $image['srcset']);
                    foreach ($srcset_urls as $srcset_url) {
                        $parts = preg_split('/\s+/', trim($srcset_url));
                        if (count($parts) >= 1) {
                            $src_url = $parts[0];
                            $srcset_result = $this->convert_image($src_url);
                            if ($srcset_result['success']) {
                                $updated_urls[$src_url] = $srcset_result['webp_url'];
                            }
                        }
                    }
                }
            } elseif ($result['skipped']) {
                $skipped++;
            } else {
                $failed++;
            }
        }

        // Update post content if setting is enabled and we have conversions
        if ($converted > 0 && get_option('kloudwebp_update_content', true) && !empty($updated_urls)) {
            // Update image tags with WebP sources
            foreach ($images as $image) {
                $original_tag = $image['tag'];
                $new_tag = $original_tag;

                // Update src attribute
                if (isset($updated_urls[$image['url']])) {
                    $new_tag = str_replace(
                        'src="' . $image['url'] . '"',
                        'src="' . $updated_urls[$image['url']] . '"',
                        $new_tag
                    );
                }

                // Update srcset attribute if exists
                if (!empty($image['srcset'])) {
                    $new_srcset = $image['srcset'];
                    foreach ($updated_urls as $original => $webp) {
                        $new_srcset = str_replace($original, $webp, $new_srcset);
                    }
                    $new_tag = str_replace(
                        'srcset="' . $image['srcset'] . '"',
                        'srcset="' . $new_srcset . '"',
                        $new_tag
                    );
                }

                // Add picture tag for better browser support
                if ($new_tag !== $original_tag) {
                    $picture_tag = '<picture>';
                    $picture_tag .= '<source srcset="' . $updated_urls[$image['url']] . '" type="image/webp">';
                    $picture_tag .= $original_tag; // Original img tag as fallback
                    $picture_tag .= '</picture>';
                    
                    $content = str_replace($original_tag, $picture_tag, $content);
                }
            }

            // Update post content
            wp_update_post(array(
                'ID' => $post_id,
                'post_content' => $content
            ));
        }

        // Determine overall status
        $status = $this->determine_status($converted, count($images));
        $this->update_conversion_status($post_id, $status);

        return array(
            'success' => true,
            'converted' => $converted,
            'failed' => $failed,
            'skipped' => $skipped,
            'total' => count($images),
            'status' => $status
        );
    }

    /**
     * Get all images from post content
     */
    private function get_images_from_content($content) {
        $images = array();
        
        // Regular expression to find image tags with optional srcset
        if (preg_match_all('/<img[^>]+>/', $content, $img_tags)) {
            foreach ($img_tags[0] as $img_tag) {
                $image_data = array(
                    'tag' => $img_tag,
                    'url' => '',
                    'srcset' => ''
                );

                // Get src attribute
                if (preg_match('/src=[\'"]([^\'"]+)[\'"]/', $img_tag, $match)) {
                    $url = $match[1];
                    if ($this->is_internal_image($url)) {
                        $image_data['url'] = $url;
                    }
                }

                // Get srcset attribute
                if (preg_match('/srcset=[\'"]([^\'"]+)[\'"]/', $img_tag, $match)) {
                    $image_data['srcset'] = $match[1];
                }

                if (!empty($image_data['url'])) {
                    $images[] = $image_data;
                }
            }
        }

        return $images;
    }

    /**
     * Check if image URL is internal
     */
    private function is_internal_image($url) {
        $site_url = get_site_url();
        return strpos($url, $site_url) === 0 || strpos($url, '/') === 0;
    }

    /**
     * Convert single image to WebP
     */
    private function convert_image($url) {
        // Get the file path from URL
        $file_path = $this->url_to_path($url);
        if (!$file_path || !file_exists($file_path)) {
            return array(
                'success' => false,
                'message' => 'File not found',
                'skipped' => true
            );
        }

        // Check if file is an image
        $image_info = getimagesize($file_path);
        if (!$image_info) {
            return array(
                'success' => false,
                'message' => 'Not a valid image',
                'skipped' => true
            );
        }

        // Check if WebP version already exists
        $webp_path = $file_path . '.webp';
        if (file_exists($webp_path)) {
            return array(
                'success' => true,
                'message' => 'WebP version already exists',
                'webp_url' => $this->path_to_url($webp_path),
                'skipped' => true
            );
        }

        // Get image type
        $mime_type = $image_info['mime'];
        $quality = get_option('kloudwebp_compression_quality', 80);

        // Try conversion with Imagick first if available
        if (extension_loaded('imagick')) {
            try {
                $image = new Imagick($file_path);
                $image->setImageFormat('webp');
                $image->setImageCompressionQuality($quality);
                $image->stripImage();
                $success = $image->writeImage($webp_path);
                $image->destroy();

                if ($success) {
                    return array(
                        'success' => true,
                        'message' => 'Conversion successful with Imagick',
                        'webp_url' => $this->path_to_url($webp_path),
                        'skipped' => false
                    );
                }
            } catch (Exception $e) {
                // If Imagick fails, we'll try GD below
            }
        }

        // Try GD if Imagick is not available or failed
        if (extension_loaded('gd')) {
            try {
                // Create image resource based on type
                switch ($mime_type) {
                    case 'image/jpeg':
                        $image = imagecreatefromjpeg($file_path);
                        break;
                    case 'image/png':
                        $image = imagecreatefrompng($file_path);
                        // Handle transparency
                        imagepalettetotruecolor($image);
                        imagealphablending($image, true);
                        imagesavealpha($image, true);
                        break;
                    default:
                        return array(
                            'success' => false,
                            'message' => 'Unsupported image type',
                            'skipped' => true
                        );
                }

                if ($image) {
                    // Convert to WebP
                    $success = imagewebp($image, $webp_path, $quality);
                    imagedestroy($image);

                    if ($success) {
                        return array(
                            'success' => true,
                            'message' => 'Conversion successful with GD',
                            'webp_url' => $this->path_to_url($webp_path),
                            'skipped' => false
                        );
                    }
                }
            } catch (Exception $e) {
                return array(
                    'success' => false,
                    'message' => 'GD conversion failed: ' . $e->getMessage(),
                    'skipped' => false
                );
            }
        }

        return array(
            'success' => false,
            'message' => 'No suitable image conversion method available',
            'skipped' => false
        );
    }

    /**
     * Convert URL to file system path
     */
    private function url_to_path($url) {
        // Remove query string
        $url = preg_replace('/\?.*/', '', $url);
        
        // Get the upload directory info
        $upload_dir = wp_upload_dir();
        
        // Convert URL to path
        if (strpos($url, $upload_dir['baseurl']) === 0) {
            return str_replace(
                $upload_dir['baseurl'],
                $upload_dir['basedir'],
                $url
            );
        }
        
        // Handle relative URLs
        if (strpos($url, '/') === 0) {
            return ABSPATH . ltrim($url, '/');
        }
        
        return false;
    }

    /**
     * Convert file system path to URL
     */
    private function path_to_url($path) {
        $upload_dir = wp_upload_dir();
        return str_replace(
            $upload_dir['basedir'],
            $upload_dir['baseurl'],
            $path
        );
    }

    /**
     * Determine conversion status based on results
     */
    private function determine_status($converted, $total) {
        if ($total === 0) {
            return 'no_images';
        } elseif ($converted === $total) {
            return 'converted';
        } elseif ($converted > 0) {
            return 'partially_converted';
        } else {
            return 'not_converted';
        }
    }

    /**
     * Update conversion status in database
     */
    private function update_conversion_status($post_id, $status) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'kloudwebp_conversions';
        
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_name WHERE post_id = %d",
            $post_id
        ));

        if ($existing) {
            $wpdb->update(
                $table_name,
                array(
                    'status' => $status,
                    'last_converted' => current_time('mysql')
                ),
                array('post_id' => $post_id)
            );
        } else {
            $wpdb->insert(
                $table_name,
                array(
                    'post_id' => $post_id,
                    'status' => $status,
                    'last_converted' => current_time('mysql')
                )
            );
        }
    }
}
