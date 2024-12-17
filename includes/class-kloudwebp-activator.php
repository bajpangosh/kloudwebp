<?php

/**
 * Fired during plugin activation
 */
class KloudWebP_Activator {

    /**
     * Initialize plugin settings and create necessary database tables
     */
    public static function activate() {
        // Set default options if they don't exist
        if (!get_option('kloudwebp_settings')) {
            $default_settings = array(
                'quality' => 80,
                'keep_original' => true,
                'convert_existing' => false
            );
            update_option('kloudwebp_settings', $default_settings);
        }

        // Create upload directory for WebP images if it doesn't exist
        $upload_dir = wp_upload_dir();
        $webp_dir = $upload_dir['basedir'] . '/webp';
        if (!file_exists($webp_dir)) {
            wp_mkdir_p($webp_dir);
        }

        // Create an index.php file in the webp directory for security
        if (!file_exists($webp_dir . '/index.php')) {
            $index_content = "<?php\n// Silence is golden.";
            file_put_contents($webp_dir . '/index.php', $index_content);
        }

        // Add a .htaccess file to protect the directory
        if (!file_exists($webp_dir . '/.htaccess')) {
            $htaccess_content = "Options -Indexes\n";
            $htaccess_content .= "<IfModule mod_mime.c>\n";
            $htaccess_content .= "    AddType image/webp .webp\n";
            $htaccess_content .= "</IfModule>";
            file_put_contents($webp_dir . '/.htaccess', $htaccess_content);
        }

        // Flush rewrite rules
        flush_rewrite_rules();
    }
}
