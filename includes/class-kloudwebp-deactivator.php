<?php

/**
 * Fired during plugin deactivation
 */
class KloudWebP_Deactivator {

    /**
     * Clean up plugin data if necessary
     */
    public static function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();

        // We don't delete the settings or converted images on deactivation
        // This ensures users don't lose their settings or images if they 
        // temporarily deactivate the plugin
        
        // If you want to clean everything on deactivation, uncomment these lines:
        /*
        delete_option('kloudwebp_settings');
        
        // Optional: Remove converted images
        $upload_dir = wp_upload_dir();
        $webp_dir = $upload_dir['basedir'] . '/webp';
        if (file_exists($webp_dir)) {
            self::remove_directory($webp_dir);
        }
        */
    }

    /**
     * Helper function to recursively remove a directory
     */
    private static function remove_directory($dir) {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (is_dir($dir . "/" . $object)) {
                        self::remove_directory($dir . "/" . $object);
                    } else {
                        unlink($dir . "/" . $object);
                    }
                }
            }
            rmdir($dir);
        }
    }
}
