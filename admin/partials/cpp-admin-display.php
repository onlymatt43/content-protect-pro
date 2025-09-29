<?php
/**
 * Provide a admin area view for the plugin
 *
 * This file is used to markup the admin-facing aspects of the plugin.
 *
 * @package Content_Protect_Pro
 * @since   1.0.0
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Get system info for dashboard
if (class_exists('CPP_Diagnostic')) {
    $diagnostic = new CPP_Diagnostic();
    $system_info = $diagnostic->get_system_info();
}

// Get statistics
$giftcode_stats = array();
$video_stats = array();
$analytics_stats = array();

if (class_exists('CPP_Giftcode_Manager')) {
    $giftcode_manager = new CPP_Giftcode_Manager();
    $giftcode_stats = $giftcode_manager->get_stats();
}

if (class_exists('CPP_Video_Manager')) {
    $video_manager = new CPP_Video_Manager();
    $video_stats = $video_manager->get_stats();
}

if (class_exists('CPP_Analytics')) {
    $analytics = new CPP_Analytics();
    $analytics_stats = $analytics->get_summary_stats();
}
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="cpp-dashboard">
        <div class="cpp-welcome-panel">
            <div class="welcome-panel-content">
                <h2><?php _e('Welcome to Content Protect Pro!', 'content-protect-pro'); ?></h2>
                <p class="about-description">
                    <?php _e('A unified protection system for gift codes and video content with advanced security features, analytics, and third-party integrations.', 'content-protect-pro'); ?>
                </p>
                
                <div class="welcome-panel-column-container">
                    <div class="welcome-panel-column">
                        <h3><?php _e('Get Started', 'content-protect-pro'); ?></h3>
                        <a class="button button-primary button-hero" href="<?php echo admin_url('admin.php?page=content-protect-pro-settings'); ?>">
                            <?php _e('Configure Settings', 'content-protect-pro'); ?>
                        </a>
                        <p><?php _e('Set up your protection preferences, integrations, and security options.', 'content-protect-pro'); ?></p>
                    </div>
                    
                    <div class="welcome-panel-column">
                        <h3><?php _e('Manage Content', 'content-protect-pro'); ?></h3>
                        <a class="button button-secondary" href="<?php echo admin_url('admin.php?page=content-protect-pro-giftcodes'); ?>">
                            <?php _e('Manage Gift Codes', 'content-protect-pro'); ?>
                        </a>
                        <a class="button button-secondary" href="<?php echo admin_url('admin.php?page=content-protect-pro-videos'); ?>">
                            <?php _e('Protect Videos', 'content-protect-pro'); ?>
                        </a>
                        <p><?php _e('Create and manage gift codes, set up video protection rules.', 'content-protect-pro'); ?></p>
                    </div>
                    
                    <div class="welcome-panel-column welcome-panel-last">
                        <h3><?php _e('Monitor & Analyze', 'content-protect-pro'); ?></h3>
                        <a class="button button-secondary" href="<?php echo admin_url('admin.php?page=content-protect-pro-analytics'); ?>">
                            <?php _e('View Analytics', 'content-protect-pro'); ?>
                        </a>
                        <p><?php _e('Track usage, monitor security events, and analyze performance.', 'content-protect-pro'); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics Overview -->
        <div class="cpp-stats-grid">
            <div class="cpp-stat-card">
                <div class="cpp-stat-icon">
                    <span class="dashicons dashicons-tickets-alt"></span>
                </div>
                <div class="cpp-stat-content">
                    <h3><?php echo isset($giftcode_stats['total_codes']) ? intval($giftcode_stats['total_codes']) : 0; ?></h3>
                    <p><?php _e('Total Gift Codes', 'content-protect-pro'); ?></p>
                    <span class="cpp-stat-change">
                        <?php 
                        $active_codes = isset($giftcode_stats['active_codes']) ? intval($giftcode_stats['active_codes']) : 0;
                        echo sprintf(__('%d Active', 'content-protect-pro'), $active_codes);
                        ?>
                    </span>
                </div>
            </div>

            <div class="cpp-stat-card">
                <div class="cpp-stat-icon">
                    <span class="dashicons dashicons-video-alt3"></span>
                </div>
                <div class="cpp-stat-content">
                    <h3><?php echo isset($video_stats['total_videos']) ? intval($video_stats['total_videos']) : 0; ?></h3>
                    <p><?php _e('Protected Videos', 'content-protect-pro'); ?></p>
                    <span class="cpp-stat-change">
                        <?php 
                        $active_videos = isset($video_stats['active_videos']) ? intval($video_stats['active_videos']) : 0;
                        echo sprintf(__('%d Active', 'content-protect-pro'), $active_videos);
                        ?>
                    </span>
                </div>
            </div>

            <div class="cpp-stat-card">
                <div class="cpp-stat-icon">
                    <span class="dashicons dashicons-chart-line"></span>
                </div>
                <div class="cpp-stat-content">
                    <h3><?php echo isset($analytics_stats['total_events']) ? intval($analytics_stats['total_events']) : 0; ?></h3>
                    <p><?php _e('Analytics Events', 'content-protect-pro'); ?></p>
                    <span class="cpp-stat-change">
                        <?php 
                        $recent_events = isset($analytics_stats['events_24h']) ? intval($analytics_stats['events_24h']) : 0;
                        echo sprintf(__('%d Last 24h', 'content-protect-pro'), $recent_events);
                        ?>
                    </span>
                </div>
            </div>

            <div class="cpp-stat-card">
                <div class="cpp-stat-icon">
                    <span class="dashicons dashicons-shield-alt"></span>
                </div>
                <div class="cpp-stat-content">
                    <h3><?php echo isset($analytics_stats['security_events']) ? intval($analytics_stats['security_events']) : 0; ?></h3>
                    <p><?php _e('Security Events', 'content-protect-pro'); ?></p>
                    <span class="cpp-stat-change">
                        <?php _e('Last 30 days', 'content-protect-pro'); ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="cpp-quick-actions">
            <h2><?php _e('Quick Actions', 'content-protect-pro'); ?></h2>
            <div class="cpp-actions-grid">
                <div class="cpp-action-card">
                    <h3><?php _e('Create Gift Code', 'content-protect-pro'); ?></h3>
                    <p><?php _e('Generate a new gift code for content access.', 'content-protect-pro'); ?></p>
                    <a href="<?php echo admin_url('admin.php?page=content-protect-pro-giftcodes&action=add'); ?>" class="button button-primary">
                        <?php _e('Create Code', 'content-protect-pro'); ?>
                    </a>
                </div>

                <div class="cpp-action-card">
                    <h3><?php _e('Protect Video', 'content-protect-pro'); ?></h3>
                    <p><?php _e('Add a new video to the protection system.', 'content-protect-pro'); ?></p>
                    <a href="<?php echo admin_url('admin.php?page=content-protect-pro-videos&action=add'); ?>" class="button button-primary">
                        <?php _e('Protect Video', 'content-protect-pro'); ?>
                    </a>
                </div>

                <div class="cpp-action-card">
                    <h3><?php _e('Integration Setup', 'content-protect-pro'); ?></h3>
                    <p><?php _e('Configure Bunny CDN or Presto Player integration.', 'content-protect-pro'); ?></p>
                    <a href="<?php echo admin_url('admin.php?page=content-protect-pro-settings&tab=integrations'); ?>" class="button button-secondary">
                        <?php _e('Setup Integrations', 'content-protect-pro'); ?>
                    </a>
                </div>
            </div>
        </div>

        <!-- System Status -->
        <div class="cpp-system-status">
            <h2><?php _e('System Status', 'content-protect-pro'); ?></h2>
            <div class="cpp-status-grid">
                <div class="cpp-status-item">
                    <span class="cpp-status-label"><?php _e('WordPress Version:', 'content-protect-pro'); ?></span>
                    <span class="cpp-status-value">
                        <?php echo isset($system_info['wordpress']['version']) ? esc_html($system_info['wordpress']['version']) : get_bloginfo('version'); ?>
                        <?php if (version_compare(get_bloginfo('version'), '5.0', '>=')): ?>
                            <span class="cpp-status-ok">✓</span>
                        <?php else: ?>
                            <span class="cpp-status-warning">⚠</span>
                        <?php endif; ?>
                    </span>
                </div>

                <div class="cpp-status-item">
                    <span class="cpp-status-label"><?php _e('PHP Version:', 'content-protect-pro'); ?></span>
                    <span class="cpp-status-value">
                        <?php echo isset($system_info['server']['php_version']) ? esc_html($system_info['server']['php_version']) : phpversion(); ?>
                        <?php if (version_compare(phpversion(), '7.4', '>=')): ?>
                            <span class="cpp-status-ok">✓</span>
                        <?php else: ?>
                            <span class="cpp-status-error">✗</span>
                        <?php endif; ?>
                    </span>
                </div>

                <div class="cpp-status-item">
                    <span class="cpp-status-label"><?php _e('SSL/HTTPS:', 'content-protect-pro'); ?></span>
                    <span class="cpp-status-value">
                        <?php if (is_ssl()): ?>
                            <?php _e('Enabled', 'content-protect-pro'); ?> <span class="cpp-status-ok">✓</span>
                        <?php else: ?>
                            <?php _e('Disabled', 'content-protect-pro'); ?> <span class="cpp-status-warning">⚠</span>
                        <?php endif; ?>
                    </span>
                </div>

                <div class="cpp-status-item">
                    <span class="cpp-status-label"><?php _e('Database:', 'content-protect-pro'); ?></span>
                    <span class="cpp-status-value">
                        <?php
                        global $wpdb;
                        $tables_exist = true;
                        $required_tables = array(
                            $wpdb->prefix . 'cpp_giftcodes',
                            $wpdb->prefix . 'cpp_protected_videos',
                            $wpdb->prefix . 'cpp_analytics'
                        );
                        
                        foreach ($required_tables as $table) {
                            if (!$wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table))) {
                                $tables_exist = false;
                                break;
                            }
                        }
                        
                        if ($tables_exist): ?>
                            <?php _e('Connected', 'content-protect-pro'); ?> <span class="cpp-status-ok">✓</span>
                        <?php else: ?>
                            <?php _e('Tables Missing', 'content-protect-pro'); ?> <span class="cpp-status-error">✗</span>
                        <?php endif; ?>
                    </span>
                </div>
            </div>

            <?php if (!$tables_exist): ?>
                <div class="notice notice-error">
                    <p>
                        <?php _e('Some database tables are missing. Try deactivating and reactivating the plugin.', 'content-protect-pro'); ?>
                        <a href="<?php echo wp_nonce_url(admin_url('plugins.php?action=deactivate&plugin=' . CPP_PLUGIN_BASENAME), 'deactivate-plugin_' . CPP_PLUGIN_BASENAME); ?>" class="button button-small">
                            <?php _e('Deactivate Plugin', 'content-protect-pro'); ?>
                        </a>
                    </p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Recent Activity -->
        <?php if (class_exists('CPP_Analytics') && !empty($analytics_stats)): ?>
        <div class="cpp-recent-activity">
            <h2><?php _e('Recent Activity', 'content-protect-pro'); ?></h2>
            <div class="cpp-activity-list">
                <?php
                $analytics = new CPP_Analytics();
                $recent_events = $analytics->get_analytics(array(
                    'limit' => 10,
                    'order' => 'DESC'
                ));
                
                if (!empty($recent_events['events'])):
                    foreach ($recent_events['events'] as $event):
                ?>
                    <div class="cpp-activity-item">
                        <div class="cpp-activity-icon">
                            <?php
                            switch ($event->event_type) {
                                case 'giftcode_validated':
                                    echo '<span class="dashicons dashicons-tickets-alt"></span>';
                                    break;
                                case 'video_access':
                                    echo '<span class="dashicons dashicons-video-alt3"></span>';
                                    break;
                                case 'security':
                                    echo '<span class="dashicons dashicons-shield-alt"></span>';
                                    break;
                                default:
                                    echo '<span class="dashicons dashicons-admin-generic"></span>';
                            }
                            ?>
                        </div>
                        <div class="cpp-activity-content">
                            <div class="cpp-activity-title">
                                <?php echo esc_html(ucwords(str_replace('_', ' ', $event->event_type))); ?>
                            </div>
                            <div class="cpp-activity-meta">
                                <?php echo esc_html($event->object_type); ?> • 
                                <?php echo esc_html(human_time_diff(strtotime($event->created_at))); ?> ago
                            </div>
                        </div>
                    </div>
                <?php
                    endforeach;
                else:
                ?>
                    <div class="cpp-no-activity">
                        <p><?php _e('No recent activity found.', 'content-protect-pro'); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
.cpp-dashboard {
    max-width: 1200px;
}

.cpp-welcome-panel {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    margin: 20px 0;
    padding: 20px;
}

.cpp-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.cpp-stat-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    display: flex;
    align-items: center;
}

.cpp-stat-icon {
    margin-right: 15px;
    font-size: 24px;
    color: #0073aa;
}

.cpp-stat-content h3 {
    font-size: 24px;
    font-weight: bold;
    margin: 0;
    color: #23282d;
}

.cpp-stat-content p {
    margin: 5px 0;
    color: #666;
}

.cpp-stat-change {
    font-size: 12px;
    color: #888;
}

.cpp-actions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.cpp-action-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    text-align: center;
}

.cpp-status-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 15px;
    margin: 20px 0;
}

.cpp-status-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px;
    background: #f9f9f9;
    border-radius: 4px;
}

.cpp-status-ok { color: #46b450; }
.cpp-status-warning { color: #ffb900; }
.cpp-status-error { color: #dc3232; }

.cpp-activity-list {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    max-height: 400px;
    overflow-y: auto;
}

.cpp-activity-item {
    display: flex;
    align-items: center;
    padding: 15px;
    border-bottom: 1px solid #f0f0f1;
}

.cpp-activity-item:last-child {
    border-bottom: none;
}

.cpp-activity-icon {
    margin-right: 15px;
    color: #666;
}

.cpp-activity-title {
    font-weight: 600;
    margin-bottom: 5px;
}

.cpp-activity-meta {
    font-size: 12px;
    color: #666;
}

.cpp-no-activity {
    padding: 40px;
    text-align: center;
    color: #666;
}
</style>