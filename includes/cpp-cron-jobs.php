<?php
/**
 * Cron Job Handlers
 * 
 * Scheduled tasks for cleanup and maintenance.
 * Following copilot-instructions.md patterns.
 *
 * @package Content_Protect_Pro
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register cron job hook
 */
add_action('cpp_daily_cleanup', 'cpp_run_daily_cleanup');

/**
 * Daily cleanup task
 * - Expires old sessions
 * - Cleans old analytics data (90+ days)
 * - Removes expired transients
 */
function cpp_run_daily_cleanup() {
    // Cleanup expired sessions
    if (!class_exists('CPP_Protection_Manager')) {
        require_once CPP_PLUGIN_DIR . 'includes/class-cpp-protection-manager.php';
    }
    
    $protection = new CPP_Protection_Manager();
    $sessions_cleaned = $protection->cleanup_expired_sessions();
    
    // Cleanup old analytics (90+ days)
    if (!class_exists('CPP_Analytics')) {
        require_once CPP_PLUGIN_DIR . 'includes/class-cpp-analytics.php';
    }
    
    $analytics = new CPP_Analytics();
    $analytics_cleaned = $analytics->cleanup_old_data(90);
    
    // Cleanup old playback tokens
    global $wpdb;
    
    $tokens_cleaned = $wpdb->query(
        $wpdb->prepare("DELETE FROM {$wpdb->prefix}cpp_tokens 
         WHERE expires_at < NOW()")
    );
    
    // Cleanup old rate limit records
    if (!class_exists('CPP_Giftcode_Security')) {
        require_once CPP_PLUGIN_DIR . 'includes/class-cpp-giftcode-security.php';
    }
    
    $security = new CPP_Giftcode_Security();
    $rate_limits_cleaned = $security->cleanup_rate_limits(7);
    
    // Log cleanup results
    $analytics->log_event('daily_cleanup_completed', 'system', 'cron', [
        'sessions_cleaned' => $sessions_cleaned,
        'analytics_cleaned' => $analytics_cleaned,
        'tokens_cleaned' => $tokens_cleaned,
        'rate_limits_cleaned' => $rate_limits_cleaned,
    ]);
    
    // Run migrations check (throttled to 12h per copilot-instructions)
    if (!class_exists('CPP_Migrations')) {
        require_once CPP_PLUGIN_DIR . 'includes/class-cpp-migrations.php';
    }
    
    CPP_Migrations::maybe_migrate();
}

/**
 * Schedule cron jobs on plugin activation
 * Called by CPP_Activator
 */
function cpp_schedule_cron_jobs() {
    if (!wp_next_scheduled('cpp_daily_cleanup')) {
        wp_schedule_event(time(), 'daily', 'cpp_daily_cleanup');
    }
}

/**
 * Unschedule cron jobs on plugin deactivation
 * Called by CPP_Deactivator
 */
function cpp_unschedule_cron_jobs() {
    $timestamp = wp_next_scheduled('cpp_daily_cleanup');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'cpp_daily_cleanup');
    }
}