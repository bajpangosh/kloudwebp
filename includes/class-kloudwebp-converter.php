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
            $this->log_debug("Post not found: $post_id", 'error');
            return array(
                'success' => false,
                'message' => 'Post not found'
            );
        }

        // Get all images from post content
        $images = $this->get_images_from_content($post->post_content);
        
        if (empty($images)) {
            $this->log_debug("No images found in post: $post_id");
            $this->update_conversion_status($post_id, 'no_images');
            return array(
                'success' => true,
                'message' => 'No images found in post',
                'status' => 'no_images'
            );
        }

        $this->log_debug("Found " . count($images) . " images in post: $post_id");
        
        $converted = 0;
        $failed = 0;
        $skipped = 0;
        $content = $post->post_content;
        $updated_urls = array();

        foreach ($images as $image) {
            $this->log_debug("Processing image: " . $image['url']);
            $result = $this->convert_image($image['url']);
            
            if ($result['success']) {
                $converted++;
                $this->log_debug("Successfully converted: " . $image['url'] . " to WebP");
                
                // Store original and WebP URLs for replacement
                $updated_urls[$image['url']] = $result['webp_url'];
                
                // Update image srcset if exists
                if (!empty($image['srcset'])) {
                    $this->log_debug("Processing srcset for: " . $image['url']);
                    $srcset_urls = explode(',', $image['srcset']);
                    foreach ($srcset_urls as $srcset_url) {
                        $parts = preg_split('/\s+/', trim($srcset_url));
                        if (count($parts) >= 1) {
                            $src_url = $this->clean_image_url($parts[0]);
                            $srcset_result = $this->convert_image($src_url);
                            if ($srcset_result['success']) {
                                $this->log_debug("Successfully converted srcset image: " . $src_url);
                                $updated_urls[$src_url] = $srcset_result['webp_url'];
                            } else {
                                $this->log_debug("Failed to convert srcset image: " . $src_url, 'error');
                            }
                        }
                    }
                }
            } elseif ($result['skipped']) {
                $skipped++;
                $this->log_debug("Skipped image: " . $image['url'] . " - " . $result['message']);
            } else {
                $failed++;
                $this->log_debug("Failed to convert image: " . $image['url'] . " - " . $result['message'], 'error');
            }
        }

        // Update post content if setting is enabled and we have conversions
        if ($converted > 0 && get_option('kloudwebp_update_content', true) && !empty($updated_urls)) {
            $this->log_debug("Updating post content with WebP URLs for post: $post_id");
            
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
                    $this->log_debug("Added picture tag for: " . $image['url']);
                }
            }

            // Update post content
            $update_result = wp_update_post(array(
                'ID' => $post_id,
                'post_content' => $content
            ));

            if (is_wp_error($update_result)) {
                $this->log_debug("Failed to update post content: " . $update_result->get_error_message(), 'error');
            } else {
                $this->log_debug("Successfully updated post content");
            }
        }

        // Determine overall status
        $status = $this->determine_status($converted, count($images));
        $this->update_conversion_status($post_id, $status);

        $this->log_debug("Conversion complete for post $post_id - Status: $status, Converted: $converted, Failed: $failed, Skipped: $skipped");

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
     * Analyze images in a post and categorize them
     */
    public function analyze_post_images($post_id, $content = null) {
        if ($content === null) {
            $content = get_post_field('post_content', $post_id);
        }

        $images = $this->get_images_from_content($content);
        $result = array(
            'webp' => array(),
            'non_webp' => array(),
            'skipped' => array()
        );

        foreach ($images as $image) {
            $url = $image['url'];
            $file_path = $this->url_to_path($url);

            // Skip if file doesn't exist
            if (!$file_path || !file_exists($file_path)) {
                $result['skipped'][] = array(
                    'url' => $url,
                    'reason' => 'File not found'
                );
                continue;
            }

            // Check if WebP version exists
            $webp_path = $file_path . '.webp';
            $webp_url = $url . '.webp';

            if (file_exists($webp_path)) {
                // Check if WebP is valid
                if (filesize($webp_path) > 0 && @getimagesize($webp_path)) {
                    $result['webp'][] = array(
                        'url' => $url,
                        'webp_url' => $webp_url,
                        'size_original' => filesize($file_path),
                        'size_webp' => filesize($webp_path)
                    );
                } else {
                    // Invalid WebP file
                    @unlink($webp_path); // Remove invalid file
                    $result['non_webp'][] = array(
                        'url' => $url,
                        'reason' => 'Invalid WebP version'
                    );
                }
            } else {
                // Check if file is a valid image
                $image_info = @getimagesize($file_path);
                if (!$image_info) {
                    $result['skipped'][] = array(
                        'url' => $url,
                        'reason' => 'Not a valid image'
                    );
                    continue;
                }

                // Check file size
                $file_size = filesize($file_path);
                $max_size = 10 * 1024 * 1024; // 10MB limit
                if ($file_size > $max_size) {
                    $result['skipped'][] = array(
                        'url' => $url,
                        'reason' => 'File too large (max 10MB)'
                    );
                    continue;
                }

                // Check image dimensions
                list($width, $height) = $image_info;
                $max_dimension = 5000; // 5000px limit
                if ($width > $max_dimension || $height > $max_dimension) {
                    $result['skipped'][] = array(
                        'url' => $url,
                        'reason' => 'Image dimensions too large (max 5000px)'
                    );
                    continue;
                }

                $result['non_webp'][] = array(
                    'url' => $url,
                    'mime' => $image_info['mime'],
                    'dimensions' => array(
                        'width' => $width,
                        'height' => $height
                    ),
                    'size' => $file_size
                );
            }
        }

        return $result;
    }

    /**
     * Check if URL is already a WebP image
     */
    private function is_webp($url) {
        return (bool) preg_match('/\.webp(\?.*)?$/i', $url);
    }

    /**
     * Get images from post content with improved detection
     */
    private function get_images_from_content($content) {
        $images = array();
        $processed_urls = array(); // Track processed URLs to avoid duplicates
        
        // Find all image tags including those in srcset
        if (preg_match_all('/<img[^>]+>/', $content, $img_tags)) {
            foreach ($img_tags[0] as $img_tag) {
                // Get src attribute
                if (preg_match('/src=[\'"]([^\'"]+)[\'"]/', $img_tag, $src_match)) {
                    $url = $this->clean_image_url($src_match[1]);
                    
                    // Skip if already processed
                    if (in_array($url, $processed_urls)) {
                        continue;
                    }

                    // Skip if already WebP but store original URL for reference
                    if ($this->is_webp($url)) {
                        $original_url = $this->get_original_image_url($url);
                        if ($original_url && !in_array($original_url, $processed_urls)) {
                            $this->log_debug("Found original image for WebP: " . $original_url);
                            $processed_urls[] = $original_url;
                            $images[] = array(
                                'tag' => $img_tag,
                                'url' => $original_url,
                                'webp_url' => $url,
                                'srcset' => ''
                            );
                        }
                        continue;
                    }

                    // Process if it's an internal image
                    if ($this->is_internal_image($url)) {
                        $processed_urls[] = $url;
                        $image_data = array(
                            'tag' => $img_tag,
                            'url' => $url,
                            'srcset' => ''
                        );

                        // Get srcset attribute
                        if (preg_match('/srcset=[\'"]([^\'"]+)[\'"]/', $img_tag, $srcset_match)) {
                            $srcset = $srcset_match[1];
                            $image_data['srcset'] = $srcset;

                            // Process srcset URLs
                            $srcset_urls = explode(',', $srcset);
                            foreach ($srcset_urls as $srcset_url) {
                                $parts = preg_split('/\s+/', trim($srcset_url));
                                if (count($parts) >= 1) {
                                    $src_url = $this->clean_image_url($parts[0]);
                                    if (!in_array($src_url, $processed_urls) && 
                                        !$this->is_webp($src_url) && 
                                        $this->is_internal_image($src_url)) {
                                        $processed_urls[] = $src_url;
                                        $images[] = array(
                                            'tag' => $img_tag,
                                            'url' => $src_url,
                                            'srcset' => ''
                                        );
                                    }
                                }
                            }
                        }

                        $images[] = $image_data;
                    }
                }
            }
        }

        // Find background images in inline styles
        if (preg_match_all('/background-image:\s*url\([\'"]?([^\'")\s]+)[\'"]?\)/i', $content, $bg_matches)) {
            foreach ($bg_matches[1] as $url) {
                $url = $this->clean_image_url($url);
                
                // Skip if already processed or is WebP
                if (in_array($url, $processed_urls) || $this->is_webp($url)) {
                    continue;
                }

                // Process if it's an internal image
                if ($this->is_internal_image($url)) {
                    $processed_urls[] = $url;
                    $images[] = array(
                        'tag' => 'background-image',
                        'url' => $url,
                        'srcset' => ''
                    );
                }
            }
        }

        // Find images in custom HTML blocks or shortcodes
        if (preg_match_all('/\[.*?url=[\'"]([^\'"]+)[\'"].*?\]/', $content, $shortcode_matches)) {
            foreach ($shortcode_matches[1] as $url) {
                $url = $this->clean_image_url($url);
                
                // Skip if already processed or is WebP
                if (in_array($url, $processed_urls) || $this->is_webp($url)) {
                    continue;
                }

                // Process if it's an internal image
                if ($this->is_internal_image($url)) {
                    $processed_urls[] = $url;
                    $images[] = array(
                        'tag' => 'shortcode',
                        'url' => $url,
                        'srcset' => ''
                    );
                }
            }
        }

        return $images;
    }

    /**
     * Clean and normalize image URL
     */
    private function clean_image_url($url) {
        // Remove query string and fragments
        $url = preg_replace('/[?#].*$/', '', $url);
        
        // Convert protocol-relative URLs
        if (strpos($url, '//') === 0) {
            $url = 'https:' . $url;
        }
        
        // Add site URL to relative URLs
        if (strpos($url, '/') === 0) {
            $url = get_site_url() . $url;
        }
        
        return $url;
    }

    /**
     * Get original image URL from WebP URL
     */
    private function get_original_image_url($webp_url) {
        // Remove .webp extension
        $original_url = preg_replace('/\.webp$/', '', $webp_url);
        
        // Check common image extensions
        $extensions = array('.jpg', '.jpeg', '.png');
        foreach ($extensions as $ext) {
            $test_url = $original_url . $ext;
            $test_path = $this->url_to_path($test_url);
            if ($test_path && file_exists($test_path)) {
                return $test_url;
            }
        }
        
        // If no match found, check if original URL exists
        $original_path = $this->url_to_path($original_url);
        if ($original_path && file_exists($original_path)) {
            return $original_url;
        }
        
        return false;
    }

    /**
     * Check if image URL is internal with improved validation
     */
    private function is_internal_image($url) {
        if (empty($url)) {
            return false;
        }

        // Get site URL components
        $site_url = get_site_url();
        $site_host = parse_url($site_url, PHP_URL_HOST);
        $site_path = parse_url($site_url, PHP_URL_PATH);
        
        // Get image URL components
        $url_parts = parse_url($url);
        
        // Handle relative URLs
        if (!isset($url_parts['host'])) {
            return true;
        }
        
        // Compare hosts
        if ($url_parts['host'] !== $site_host) {
            return false;
        }
        
        // Check if it's in uploads directory
        $upload_dir = wp_upload_dir();
        $upload_url = $upload_dir['baseurl'];
        $upload_path = parse_url($upload_url, PHP_URL_PATH);
        
        if (strpos($url, $upload_url) === 0 || 
            (isset($url_parts['path']) && strpos($url_parts['path'], $upload_path) === 0)) {
            return true;
        }
        
        // Check if it's in WordPress root
        if (isset($url_parts['path']) && strpos($url_parts['path'], $site_path) === 0) {
            return true;
        }
        
        return false;
    }

    /**
     * Convert single image to WebP
     */
    private function convert_image($url) {
        try {
            // Get the file path from URL
            $file_path = $this->url_to_path($url);
            if (!$file_path || !file_exists($file_path)) {
                return array(
                    'success' => false,
                    'message' => 'File not found',
                    'skipped' => true
                );
            }

            // Check file size
            $file_size = filesize($file_path);
            $max_size = 10 * 1024 * 1024; // 10MB limit
            if ($file_size > $max_size) {
                return array(
                    'success' => false,
                    'message' => 'File too large (max 10MB)',
                    'skipped' => true
                );
            }

            // Check if file is an image
            $image_info = @getimagesize($file_path);
            if (!$image_info) {
                return array(
                    'success' => false,
                    'message' => 'Not a valid image',
                    'skipped' => true
                );
            }

            // Check image dimensions
            list($width, $height) = $image_info;
            $max_dimension = 5000; // 5000px limit
            if ($width > $max_dimension || $height > $max_dimension) {
                return array(
                    'success' => false,
                    'message' => 'Image dimensions too large (max 5000px)',
                    'skipped' => true
                );
            }

            // Check if WebP version already exists and is newer
            $webp_path = $file_path . '.webp';
            if (file_exists($webp_path) && filemtime($webp_path) >= filemtime($file_path)) {
                return array(
                    'success' => true,
                    'message' => 'WebP version already exists and is up to date',
                    'webp_url' => $this->path_to_url($webp_path),
                    'skipped' => true
                );
            }

            // Get image type and quality settings
            $mime_type = $image_info['mime'];
            $quality = intval(get_option('kloudwebp_compression_quality', 80));
            $quality = min(max($quality, 1), 100); // Ensure quality is between 1 and 100

            // Try conversion with Imagick first if available
            if (extension_loaded('imagick')) {
                try {
                    // Increase memory limit temporarily
                    $mem_limit = ini_get('memory_limit');
                    ini_set('memory_limit', '256M');

                    $image = new Imagick($file_path);
                    
                    // Strip metadata to reduce file size
                    $image->stripImage();
                    
                    // Optimize for web
                    $image->setImageCompressionQuality($quality);
                    $image->setOption('webp:method', '6'); // Best compression
                    $image->setOption('webp:lossless', 'false');
                    $image->setOption('webp:low-memory', 'true');
                    
                    // Convert to WebP
                    $image->setImageFormat('webp');
                    
                    // Save optimized WebP
                    $success = $image->writeImage($webp_path);
                    $image->destroy();

                    // Restore memory limit
                    ini_set('memory_limit', $mem_limit);

                    if ($success) {
                        // Verify the WebP file is valid
                        if (filesize($webp_path) > 0 && @getimagesize($webp_path)) {
                            return array(
                                'success' => true,
                                'message' => 'Conversion successful with Imagick',
                                'webp_url' => $this->path_to_url($webp_path),
                                'skipped' => false
                            );
                        } else {
                            @unlink($webp_path); // Remove invalid WebP file
                            throw new Exception('Generated WebP file is invalid');
                        }
                    }
                } catch (Exception $e) {
                    $this->log_debug("Imagick conversion failed: " . $e->getMessage(), 'error');
                    // Clean up any failed WebP file
                    if (file_exists($webp_path)) {
                        @unlink($webp_path);
                    }
                    // Continue to try GD
                }
            }

            // Try GD if Imagick is not available or failed
            if (extension_loaded('gd')) {
                try {
                    // Increase memory limit temporarily
                    $mem_limit = ini_get('memory_limit');
                    ini_set('memory_limit', '256M');

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
                                'message' => 'Unsupported image type: ' . $mime_type,
                                'skipped' => true
                            );
                    }

                    if ($image) {
                        // Convert to WebP
                        $success = imagewebp($image, $webp_path, $quality);
                        imagedestroy($image);

                        // Restore memory limit
                        ini_set('memory_limit', $mem_limit);

                        if ($success) {
                            // Verify the WebP file is valid
                            if (filesize($webp_path) > 0 && @getimagesize($webp_path)) {
                                return array(
                                    'success' => true,
                                    'message' => 'Conversion successful with GD',
                                    'webp_url' => $this->path_to_url($webp_path),
                                    'skipped' => false
                                );
                            } else {
                                @unlink($webp_path); // Remove invalid WebP file
                                throw new Exception('Generated WebP file is invalid');
                            }
                        }
                    }
                } catch (Exception $e) {
                    $this->log_debug("GD conversion failed: " . $e->getMessage(), 'error');
                    // Clean up any failed WebP file
                    if (file_exists($webp_path)) {
                        @unlink($webp_path);
                    }
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
        } catch (Exception $e) {
            $this->log_debug("Conversion error: " . $e->getMessage(), 'error');
            return array(
                'success' => false,
                'message' => 'Conversion error: ' . $e->getMessage(),
                'skipped' => false
            );
        }
    }

    /**
     * Convert URL to file system path
     */
    private function url_to_path($url) {
        try {
            // Remove query string and decode URL
            $url = urldecode(preg_replace('/[?#].*/', '', $url));
            
            // Get the upload directory info
            $upload_dir = wp_upload_dir();
            $upload_dir_url = str_replace(['http:', 'https:'], '', $upload_dir['baseurl']);
            $upload_dir_path = $upload_dir['basedir'];
            
            // Convert protocol-relative URL to absolute path
            $url = str_replace(['http:', 'https:'], '', $url);
            
            // Try upload directory first
            if (strpos($url, $upload_dir_url) !== false) {
                return str_replace(
                    $upload_dir_url,
                    $upload_dir_path,
                    $url
                );
            }
            
            // Handle relative URLs
            if (strpos($url, '/') === 0) {
                $site_root = $_SERVER['DOCUMENT_ROOT'] ?? ABSPATH;
                return $site_root . $url;
            }
            
            // Handle absolute URLs within site
            $site_url = str_replace(['http:', 'https:'], '', get_site_url());
            if (strpos($url, $site_url) !== false) {
                return str_replace(
                    $site_url,
                    ABSPATH,
                    $url
                );
            }
            
            return false;
        } catch (Exception $e) {
            $this->log_debug("URL to path conversion error: " . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Convert file system path to URL
     */
    private function path_to_url($path) {
        try {
            $upload_dir = wp_upload_dir();
            
            // Convert upload directory path to URL
            if (strpos($path, $upload_dir['basedir']) !== false) {
                return str_replace(
                    $upload_dir['basedir'],
                    $upload_dir['baseurl'],
                    $path
                );
            }
            
            // Convert WordPress root path to URL
            if (strpos($path, ABSPATH) !== false) {
                return str_replace(
                    ABSPATH,
                    get_site_url() . '/',
                    $path
                );
            }
            
            // If path starts with document root
            $doc_root = $_SERVER['DOCUMENT_ROOT'] ?? ABSPATH;
            if (strpos($path, $doc_root) !== false) {
                $site_url = get_site_url();
                return str_replace(
                    $doc_root,
                    rtrim($site_url, '/'),
                    $path
                );
            }
            
            return false;
        } catch (Exception $e) {
            $this->log_debug("Path to URL conversion error: " . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Log debug message with proper error handling
     */
    private function log_debug($message, $type = 'info') {
        try {
            if (method_exists($this, 'log_message')) {
                $this->log_message($message, $type);
            } else {
                error_log("KloudWebP: [$type] $message");
            }
        } catch (Exception $e) {
            error_log("KloudWebP logging error: " . $e->getMessage());
        }
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
