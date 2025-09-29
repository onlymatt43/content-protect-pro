<?php
/**
 * Fired during plugin activation
 *
 * @package Content_Protect_Pro
 * @since   1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class CPP_Activator {

    /**
     * Plugin activation tasks
     *
     * @since 1.0.0
     */
    public static function activate() {
        // Create database tables
        self::create_tables();
        
        // Set default options
        self::set_default_options();
        
        // Schedule cron jobs
        self::schedule_cron_jobs();
        
        // Create upload directories
        self::create_directories();
        
        // Set version
        update_option('cpp_version', CPP_VERSION);
        
        // Set activation time
        update_option('cpp_activation_time', current_time('timestamp'));
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Create plugin database tables
     *
     * @since 1.0.0
     */
    private static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Gift codes table (updated for token-based system)
        $table_name = $wpdb->prefix . 'cpp_giftcodes';
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            code varchar(255) NOT NULL,
            secure_token varchar(255) NOT NULL,
            duration_minutes int(11) NOT NULL DEFAULT 60,
            duration_display varchar(50) DEFAULT NULL,
            status varchar(20) NOT NULL DEFAULT 'active',
            ip_restrictions text DEFAULT NULL,
            expires_at datetime DEFAULT NULL,
            description text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY code (code),
            UNIQUE KEY secure_token (secure_token),
            KEY status (status),
            KEY expires_at (expires_at),
            KEY duration_minutes (duration_minutes)
        ) $charset_collate;";

        // Protected videos table (updated schema)
        $table_name_videos = $wpdb->prefix . 'cpp_protected_videos';
        $sql_videos = "CREATE TABLE $table_name_videos (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            video_id varchar(255) NOT NULL,
            title varchar(255) NOT NULL,
            required_minutes int(11) NOT NULL DEFAULT 60,
            integration_type varchar(50) NOT NULL DEFAULT 'bunny',
            bunny_library_id varchar(255) DEFAULT NULL,
            presto_player_id varchar(255) DEFAULT NULL,
            direct_url text DEFAULT NULL,
            description text DEFAULT NULL,
            status varchar(20) NOT NULL DEFAULT 'active',
            usage_count int(11) NOT NULL DEFAULT 0,
            max_uses int(11) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY video_id (video_id),
            KEY integration_type (integration_type),
            KEY status (status),
            KEY required_minutes (required_minutes)
        ) $charset_collate;";

        // Analytics table
        $table_name_analytics = $wpdb->prefix . 'cpp_analytics';
        $sql_analytics = "CREATE TABLE $table_name_analytics (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            event_type varchar(50) NOT NULL,
            object_type varchar(50) NOT NULL,
            object_id varchar(255) NOT NULL,
            user_id bigint(20) DEFAULT NULL,
            ip_address varchar(45) DEFAULT NULL,
            user_agent text DEFAULT NULL,
            metadata text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY event_type (event_type),
            KEY object_type (object_type),
            KEY user_id (user_id),
            KEY created_at (created_at)
        ) $charset_collate;";

        // Sessions table for token-based authentication
        $table_name_sessions = $wpdb->prefix . 'cpp_sessions';
        $sql_sessions = "CREATE TABLE $table_name_sessions (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            session_id varchar(255) NOT NULL,
            code varchar(255) NOT NULL,
            secure_token varchar(255) NOT NULL,
            client_ip varchar(45) NOT NULL,
            user_agent text DEFAULT NULL,
            expires_at datetime NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            status varchar(20) NOT NULL DEFAULT 'active',
            PRIMARY KEY (id),
            UNIQUE KEY session_id (session_id),
            KEY code (code),
            KEY client_ip (client_ip),
            KEY expires_at (expires_at),
            KEY status (status),
            KEY secure_token (secure_token(16))
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        dbDelta($sql_videos);
        dbDelta($sql_analytics);
        dbDelta($sql_sessions);
    }

    /**
     * Set default plugin options
     *
     * @since 1.0.0
     */
    private static function set_default_options() {
        $default_options = array(
            'cpp_giftcode_settings' => array(
                'enable_giftcodes' => 1,
                'allow_partial_use' => 0,
                'case_sensitive' => 0,
                'auto_generate_codes' => 0,
                'code_length' => 8,
                'code_prefix' => '',
                'code_suffix' => '',
            ),
            'cpp_video_settings' => array(
                'enable_video_protection' => 1,
                'token_expiry' => 3600,
                'enable_bunny' => 0,
                'enable_presto' => 0,
                'default_access_level' => 'public',
            ),
            'cpp_security_settings' => array(
                'enable_logging' => 1,
                'log_retention_days' => 30,
                'enable_rate_limiting' => 1,
                'rate_limit_requests' => 100,
                'rate_limit_window' => 3600,
            ),
            'cpp_analytics_settings' => array(
                'enable_analytics' => 1,
                'track_giftcode_usage' => 1,
                'track_video_views' => 1,
                'anonymize_ip' => 1,
            ),
        );

        foreach ($default_options as $option_name => $option_value) {
            if (!get_option($option_name)) {
                update_option($option_name, $option_value);
            }
        }
    }

    /**
     * Schedule cron jobs
     *
     * @since 1.0.0
     */
    private static function schedule_cron_jobs() {
        if (!wp_next_scheduled('cpp_cleanup_expired_codes')) {
            wp_schedule_event(time(), 'daily', 'cpp_cleanup_expired_codes');
        }

        if (!wp_next_scheduled('cpp_cleanup_analytics')) {
            wp_schedule_event(time(), 'daily', 'cpp_cleanup_analytics');
        }
    }

    /**
     * Create necessary directories
     *
     * @since 1.0.0
     */
    private static function create_directories() {
        $upload_dir = wp_upload_dir();
        $cpp_dir = $upload_dir['basedir'] . '/content-protect-pro';

        if (!file_exists($cpp_dir)) {
            wp_mkdir_p($cpp_dir);
            
            // Create .htaccess for security
            $htaccess_content = "Options -Indexes\n";
            $htaccess_content .= "deny from all\n";
            file_put_contents($cpp_dir . '/.htaccess', $htaccess_content);
        }
    }
}