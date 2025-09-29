<?php
/**
 * Fired during plugin deactivation
 *
 * @package Content_Protect_Pro
 * @since   1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class CPP_Deactivator {

    /**
     * Plugin deactivation tasks
     *
     * @since 1.0.0
     */
    public static function deactivate() {
        // Clear scheduled cron jobs
        self::clear_cron_jobs();
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Log deactivation
        if (function_exists('error_log')) {
            error_log('Content Protect Pro plugin deactivated at ' . current_time('mysql'));
        }
    }

    /**
     * Clear scheduled cron jobs
     *
     * @since 1.0.0
     */
    private static function clear_cron_jobs() {
        wp_clear_scheduled_hook('cpp_cleanup_expired_codes');
        wp_clear_scheduled_hook('cpp_cleanup_analytics');
    }
}