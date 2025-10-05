<?php
/**
 * Plugin Activation Handler
 * 
 * Creates database tables following the schema from copilot-instructions.md
 * Safe to run multiple times (uses CREATE TABLE IF NOT EXISTS)
 *
 * @package Content_Protect_Pro
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class CPP_Activator {
    
    /**
     * Activate plugin
     * Creates tables, sets default options, schedules migrations
     *
     * @since 1.0.0
     */
    public static function activate() {
        self::create_tables();
        self::set_default_options();
        self::schedule_cleanup_cron();
        
        // Set activation flag for welcome screen
        set_transient('cpp_activation_redirect', true, 30);
        
        // Store activation version for future migrations
        update_option('cpp_version', CPP_VERSION);
    }
    
    /**
     * Create database tables
     * Following schema from copilot-instructions.md
     *
     * @since 1.0.0
     */
    private static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        // Gift codes table
        $sql_giftcodes = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}cpp_giftcodes (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            code varchar(50) NOT NULL UNIQUE,
            secure_token varchar(100) DEFAULT NULL,
            duration_minutes int unsigned NOT NULL DEFAULT 0,
            status enum('active','used','expired','disabled') DEFAULT 'active',
            max_uses int unsigned DEFAULT 1,
            current_uses int unsigned DEFAULT 0,
            expires_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            created_by bigint(20) unsigned DEFAULT NULL,
            metadata text DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_code (code),
            KEY idx_status (status),
            KEY idx_expires_at (expires_at)
        ) $charset_collate;";
        
        // Protected videos table
        $sql_videos = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}cpp_protected_videos (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            video_id varchar(255) NOT NULL,
            required_minutes int unsigned NOT NULL DEFAULT 0,
            integration_type enum('presto','bunny','direct') DEFAULT 'presto',
            presto_player_id bigint(20) unsigned DEFAULT NULL,
            direct_url varchar(500) DEFAULT NULL,
            thumbnail_url varchar(500) DEFAULT NULL,
            title varchar(255) DEFAULT NULL,
            description text DEFAULT NULL,
            status enum('active','inactive') DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            metadata text DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_video_id (video_id),
            KEY idx_integration_type (integration_type),
            KEY idx_presto_player_id (presto_player_id),
            KEY idx_status (status)
        ) $charset_collate;";
        
        // Sessions table
        $sql_sessions = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}cpp_sessions (
            session_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            code varchar(50) NOT NULL,
            secure_token varchar(100) NOT NULL UNIQUE,
            client_ip varchar(45) NOT NULL,
            user_agent varchar(255) DEFAULT NULL,
            expires_at datetime NOT NULL,
            status enum('active','expired','invalidated') DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            last_activity datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (session_id),
            KEY idx_secure_token (secure_token),
            KEY idx_code (code),
            KEY idx_status (status),
            KEY idx_expires_at (expires_at),
            KEY idx_client_ip (client_ip)
        ) $charset_collate;";
        
        // Analytics table
        $sql_analytics = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}cpp_analytics (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            event_type varchar(50) NOT NULL,
            object_type varchar(50) NOT NULL,
            object_id varchar(255) NOT NULL,
            user_id bigint(20) unsigned DEFAULT NULL,
            session_id bigint(20) unsigned DEFAULT NULL,
            metadata text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_event_type (event_type),
            KEY idx_object_type (object_type),
            KEY idx_object_id (object_id),
            KEY idx_created_at (created_at)
        ) $charset_collate;";
        
        // Tokens table (for playback authentication)
        $sql_tokens = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}cpp_tokens (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            token varchar(100) NOT NULL UNIQUE,
            user_id bigint(20) unsigned DEFAULT NULL,
            video_id varchar(255) NOT NULL,
            expires_at datetime NOT NULL,
            ip_address varchar(45) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_token (token),
            KEY idx_expires_at (expires_at),
            KEY idx_video_id (video_id)
        ) $charset_collate;";
        
        // Rate limiting table
        $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}cpp_rate_limits (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            client_ip varchar(45) NOT NULL,
            action_type varchar(50) NOT NULL,
            attempted_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY client_ip_action (client_ip, action_type),
            KEY attempted_at (attempted_at)
        ) $charset_collate;";
        
        // Execute table creation
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        foreach ($sql as $query) {
            dbDelta($query);
        }
    }
    
    /**
     * Set default plugin options
     *
     * @since 1.0.0
     */
    private static function set_default_options() {
        $defaults = [
            'cpp_integration_settings' => [
                'token_expiry' => 900, // 15 minutes
                'overlay_image' => '', // Attachment ID
                'purchase_url' => '',
                'default_required_minutes' => 10,
            ],
            'cpp_security_settings' => [
                'rate_limit_enabled' => true,
                'rate_limit_attempts' => 5,
                'rate_limit_window' => 300, // 5 minutes
                'ip_validation_enabled' => true,
                'session_encryption_enabled' => true,
            ],
            'cpp_ai_assistant_enabled' => true,
            'cpp_onlymatt_api_key' => '', // Will be encrypted on save
        ];
        
        foreach ($defaults as $option => $value) {
            if (false === get_option($option)) {
                add_option($option, $value);
            }
        }
    }
    
    /**
     * Schedule cleanup cron job
     * Runs daily to remove expired sessions and tokens
     *
     * @since 1.0.0
     */
    private static function schedule_cleanup_cron() {
        if (!wp_next_scheduled('cpp_daily_cleanup')) {
            wp_schedule_event(time(), 'daily', 'cpp_daily_cleanup');
        }
    }
}