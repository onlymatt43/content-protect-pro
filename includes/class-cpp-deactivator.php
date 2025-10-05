<?php
/**
 * Plugin Deactivation Handler
 * 
 * Cleans up transients, scheduled events, and temporary data.
 * Does NOT drop database tables (user data preserved).
 *
 * @package Content_Protect_Pro
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class CPP_Deactivator {
    
    /**
     * Deactivate plugin
     * Cleans up transients and cron jobs
     *
     * @since 1.0.0
     */
    public static function deactivate() {
        self::clear_transients();
        self::unschedule_cron_jobs();
        self::flush_rewrite_rules();
    }
    
    /**
     * Clear plugin transients
     *
     * @since 1.0.0
     */
    private static function clear_transients() {
        global $wpdb;
        
        // Delete all transients starting with cpp_
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_cpp_%' 
             OR option_name LIKE '_transient_timeout_cpp_%'"
        );
    }
    
    /**
     * Unschedule all cron jobs
     *
     * @since 1.0.0
     */
    private static function unschedule_cron_jobs() {
        $timestamp = wp_next_scheduled('cpp_daily_cleanup');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'cpp_daily_cleanup');
        }
    }
    
    /**
     * Flush rewrite rules
     * Ensures REST API endpoints are removed
     *
     * @since 1.0.0
     */
    private static function flush_rewrite_rules() {
        flush_rewrite_rules();
    }
}