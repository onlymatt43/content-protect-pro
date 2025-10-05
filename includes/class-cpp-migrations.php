<?php
/**
 * Database Migrations
 * 
 * Handles schema updates and data migrations safely.
 * Following copilot-instructions: throttled to 12h intervals, never drops data.
 *
 * @package Content_Protect_Pro
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class CPP_Migrations {
    
    /**
     * Run migrations if needed
     * Throttled to 12h intervals per copilot-instructions
     *
     * @return void
     */
    public static function maybe_migrate() {
        $last_migration = get_option('cpp_last_migration_check', 0);
        $current_time = time();
        
        // Throttle migrations to 12 hours
        if ($current_time - $last_migration < 12 * HOUR_IN_SECONDS) {
            return;
        }
        
        update_option('cpp_last_migration_check', $current_time);
        
        // Run all migrations in order
        self::migrate_overlay_urls_to_attachments();
        self::add_missing_columns();
        self::migrate_legacy_bunny_data();
        
        // Update version
        update_option('cpp_migration_version', CPP_VERSION);
    }
    
    /**
     * Migrate overlay URLs to attachment IDs
     * POST-MIGRATION RULE from copilot-instructions
     *
     * @return void
     */
    private static function migrate_overlay_urls_to_attachments() {
        $settings = get_option('cpp_integration_settings', []);
        
        // Check if overlay_image is a URL (legacy format)
        if (!empty($settings['overlay_image']) && filter_var($settings['overlay_image'], FILTER_VALIDATE_URL)) {
            $url = $settings['overlay_image'];
            
            // Try to find attachment by URL
            $attachment_id = attachment_url_to_postid($url);
            
            if ($attachment_id) {
                // Found in media library - update to ID
                $settings['overlay_image'] = $attachment_id;
                update_option('cpp_integration_settings', $settings);
                
                // Log migration
                if (class_exists('CPP_Analytics')) {
                    $analytics = new CPP_Analytics();
                    $analytics->log_event('migration_overlay_url_to_id', 'settings', 'overlay', [
                        'old_url' => $url,
                        'new_attachment_id' => $attachment_id,
                    ]);
                }
            } else {
                // Not found - import to media library
                $attachment_id = self::import_external_image($url);
                
                if ($attachment_id) {
                    $settings['overlay_image'] = $attachment_id;
                    update_option('cpp_integration_settings', $settings);
                }
            }
        }
    }
    
    /**
     * Import external image to media library
     *
     * @param string $url Image URL
     * @return int|false Attachment ID or false
     */
    private static function import_external_image($url) {
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        
        // Download image
        $tmp = download_url($url);
        
        if (is_wp_error($tmp)) {
            return false;
        }
        
        // Prepare file array
        $file_array = [
            'name' => basename($url),
            'tmp_name' => $tmp,
        ];
        
        // Sideload to media library
        $attachment_id = media_handle_sideload($file_array, 0, 'CPP Overlay Image');
        
        // Clean up temp file
        @unlink($tmp);
        
        return is_wp_error($attachment_id) ? false : $attachment_id;
    }
    
    /**
     * Add missing columns to tables
     * Safe to run multiple times (checks existing schema first)
     *
     * @return void
     */
    private static function add_missing_columns() {
        global $wpdb;
        
        $prefix = $wpdb->prefix;
        
        // Check if cpp_giftcodes has secure_token column
        $column_exists = $wpdb->get_results($wpdb->prepare(
            "SHOW COLUMNS FROM {$prefix}cpp_giftcodes LIKE %s",
            'secure_token'
        ));
        
        if (empty($column_exists)) {
            $wpdb->query(
                "ALTER TABLE {$prefix}cpp_giftcodes 
                 ADD COLUMN secure_token varchar(100) DEFAULT NULL AFTER code"
            );
            
            // Generate tokens for existing codes
            self::backfill_secure_tokens();
        }
        
        // Check if cpp_protected_videos has thumbnail_url
        $column_exists = $wpdb->get_results($wpdb->prepare(
            "SHOW COLUMNS FROM {$prefix}cpp_protected_videos LIKE %s",
            'thumbnail_url'
        ));
        
        if (empty($column_exists)) {
            $wpdb->query(
                "ALTER TABLE {$prefix}cpp_protected_videos 
                 ADD COLUMN thumbnail_url varchar(500) DEFAULT NULL AFTER direct_url"
            );
        }
        
        // Check if cpp_sessions has user_agent
        $column_exists = $wpdb->get_results($wpdb->prepare(
            "SHOW COLUMNS FROM {$prefix}cpp_sessions LIKE %s",
            'user_agent'
        ));
        
        if (empty($column_exists)) {
            $wpdb->query(
                "ALTER TABLE {$prefix}cpp_sessions 
                 ADD COLUMN user_agent varchar(255) DEFAULT NULL AFTER client_ip"
            );
        }
    }
    
    /**
     * Backfill secure tokens for existing gift codes
     *
     * @return void
     */
    private static function backfill_secure_tokens() {
        global $wpdb;
        
        $codes = $wpdb->get_results(
            "SELECT id FROM {$wpdb->prefix}cpp_giftcodes 
             WHERE secure_token IS NULL OR secure_token = ''"
        );
        
        foreach ($codes as $code) {
            $secure_token = CPP_Encryption::generate_token(32);
            
            $wpdb->update(
                $wpdb->prefix . 'cpp_giftcodes',
                ['secure_token' => $secure_token],
                ['id' => $code->id],
                ['%s'],
                ['%d']
            );
        }
    }
    
    /**
     * Migrate legacy Bunny CDN data
     * Converts old bunny_video_id to new structure
     *
     * @return void
     */
    private static function migrate_legacy_bunny_data() {
        global $wpdb;
        
        // Check for legacy bunny_video_id column
        $column_exists = $wpdb->get_results($wpdb->prepare(
            "SHOW COLUMNS FROM {$wpdb->prefix}cpp_protected_videos LIKE %s",
            'bunny_video_id'
        ));
        
        if (!empty($column_exists)) {
            // Migrate data to direct_url format
            $legacy_videos = $wpdb->get_results(
                "SELECT id, bunny_video_id 
                 FROM {$wpdb->prefix}cpp_protected_videos 
                 WHERE bunny_video_id IS NOT NULL 
                 AND (direct_url IS NULL OR direct_url = '')"
            );
            
            $settings = get_option('cpp_integration_settings', []);
            $bunny_hostname = $settings['bunny_cdn_hostname'] ?? '';
            
            foreach ($legacy_videos as $video) {
                if (!empty($bunny_hostname)) {
                    $direct_url = 'https://' . $bunny_hostname . '/' . $video->bunny_video_id . '/playlist.m3u8';
                    
                    $wpdb->update(
                        $wpdb->prefix . 'cpp_protected_videos',
                        [
                            'direct_url' => $direct_url,
                            'integration_type' => 'bunny',
                        ],
                        ['id' => $video->id],
                        ['%s', '%s'],
                        ['%d']
                    );
                }
            }
            
            // Drop legacy column after migration
            $wpdb->query(
                "ALTER TABLE {$wpdb->prefix}cpp_protected_videos 
                 DROP COLUMN bunny_video_id"
            );
        }
    }
    
    /**
     * Get migration status report
     *
     * @return array Status info
     */
    public static function get_migration_status() {
        global $wpdb;
        
        $status = [
            'last_check' => get_option('cpp_last_migration_check', 0),
            'version' => get_option('cpp_migration_version', '0.0.0'),
            'current_version' => CPP_VERSION,
        ];
        
        // Check overlay migration status
        $settings = get_option('cpp_integration_settings', []);
        $status['overlay_migrated'] = empty($settings['overlay_image']) || 
                                       ctype_digit((string) $settings['overlay_image']);
        
        // Check secure tokens
        $missing_tokens = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}cpp_giftcodes 
             WHERE secure_token IS NULL OR secure_token = ''"
        );
        $status['tokens_backfilled'] = ($missing_tokens == 0);
        
        return $status;
    }
}
