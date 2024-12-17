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

        foreach ($images as $image) {
            $result = $this->convert_image($image['url']);
            
            if ($result['success']) {
                $converted++;
                
                // Update post content if setting is enabled
                if (get_option('kloudwebp_update_content', true)) {
                    $content = str_replace(
                        $image['url'],
                        $result['webp_url'],
                        $content
                    );
                }
            } elseif ($result['skipped']) {
                $skipped++;
            } else {
                $failed++;
            }
        }

        // Update post content if any conversions were successful
        if ($converted > 0 && get_option('kloudwebp_update_content', true)) {
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
        
        // Regular expression to find image tags
        if (preg_match_all('/<img[^>]+>/i', $content, $img_tags)) {
            foreach ($img_tags[0] as $img_tag) {
                // Get src attribute
                if (preg_match('/src=[\'"]([^\'"]+)[\'"]/', $img_tag, $match)) {
                    $url = $match[1];
                    
                    // Only process internal images
                    if ($this->is_internal_image($url)) {
                        $images[] = array(
                            'tag' => $img_tag,
                            'url' => $url
                        );
                    }
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

        try {
            // Create Imagick instance
            $image = new Imagick($file_path);
            
            // Set compression quality
            $quality = get_option('kloudwebp_compression_quality', 80);
            
            // Convert to WebP
            $image->setImageFormat('webp');
            $image->setImageCompressionQuality($quality);
            
            // Strip metadata to reduce file size
            $image->stripImage();
            
            // Write WebP file
            $image->writeImage($webp_path);
            $image->destroy();

            return array(
                'success' => true,
                'message' => 'Conversion successful',
                'webp_url' => $this->path_to_url($webp_path),
                'skipped' => false
            );
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => $e->getMessage(),
                'skipped' => false
            );
        }
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
