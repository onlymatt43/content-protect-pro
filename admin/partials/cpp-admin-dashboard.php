<?php
/**
 * Security check - verify user has required capability
 */
if (!current_user_can('manage_options')) {
    wp_die(
        __('You do not have sufficient permissions to access this page.', 'content-protect-pro'),
        __('Unauthorized', 'content-protect-pro'),
        array('response' => 403)
    );
}
/**
 * Admin Dashboard Page
 * 
 * Shows plugin statistics and quick actions.
 * Following copilot-instructions.md admin patterns.
 *
 * @package Content_Protect_Pro
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get dashboard statistics
if (!class_exists('CPP_Analytics')) {
    require_once CPP_PLUGIN_DIR . 'includes/class-cpp-analytics.php';
}

$analytics = new CPP_Analytics();
$stats = $analytics->get_dashboard_stats();

// Get recent events
$recent_events = $analytics->get_events_by_type('giftcode_redeemed', null, null);
$recent_events = array_slice($recent_events, 0, 10);

// Get top videos
$top_videos = $analytics->get_top_videos(5, 7);
?>

<div class="wrap cpp-dashboard">
    <h1><?php esc_html_e('Content Protect Pro Dashboard', 'content-protect-pro'); ?></h1>
    
    <!-- Stats Cards -->
    <div class="cpp-stats-grid">
        <div class="cpp-stat-card">
            <div class="cpp-stat-icon">🎫</div>
            <div class="cpp-stat-content">
                <h3><?php echo esc_html(number_format($stats['codes_redeemed_week'])); ?></h3>
                <p><?php esc_html_e('Codes Redeemed (7 days)', 'content-protect-pro'); ?></p>
            </div>
        </div>
        
        <div class="cpp-stat-card">
            <div class="cpp-stat-icon">👥</div>
            <div class="cpp-stat-content">
                <h3><?php echo esc_html(number_format($stats['active_sessions'])); ?></h3>
                <p><?php esc_html_e('Active Sessions', 'content-protect-pro'); ?></p>
            </div>
        </div>
        
        <div class="cpp-stat-card">
            <div class="cpp-stat-icon">🎬</div>
            <div class="cpp-stat-content">
                <h3><?php echo esc_html(number_format($stats['total_videos'])); ?></h3>
                <p><?php esc_html_e('Protected Videos', 'content-protect-pro'); ?></p>
            </div>
        </div>
        
        <div class="cpp-stat-card">
            <div class="cpp-stat-icon">▶️</div>
            <div class="cpp-stat-content">
                <h3><?php echo esc_html(number_format($stats['views_today'])); ?></h3>
                <p><?php esc_html_e('Views Today', 'content-protect-pro'); ?></p>
            </div>
        </div>
    </div>
    
    <div class="cpp-dashboard-row">
        <!-- Recent Activity -->
        <div class="cpp-dashboard-col">
            <div class="cpp-card">
                <h2><?php esc_html_e('Recent Gift Code Redemptions', 'content-protect-pro'); ?></h2>
                
                <?php if (!empty($recent_events)): ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Code', 'content-protect-pro'); ?></th>
                                <th><?php esc_html_e('Duration', 'content-protect-pro'); ?></th>
                                <th><?php esc_html_e('Time', 'content-protect-pro'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_events as $event): ?>
                                <?php
                                $metadata = json_decode($event->metadata, true);
                                ?>
                                <tr>
                                    <td><code><?php echo esc_html($event->object_id); ?></code></td>
                                    <td>
                                        <?php
                                        printf(
                                            esc_html__('%d minutes', 'content-protect-pro'),
                                            absint($metadata['duration_minutes'] ?? 0)
                                        );
                                        ?>
                                    </td>
                                    <td><?php echo esc_html(human_time_diff(strtotime($event->created_at), time()) . ' ago'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p><?php esc_html_e(__('No recent redemptions.', 'content-protect-pro'), 'content-protect-pro'); ?></p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Top Videos -->
        <div class="cpp-dashboard-col">
            <div class="cpp-card">
                <h2><?php esc_html_e('Top Videos (7 days)', 'content-protect-pro'); ?></h2>
                
                <?php if (!empty($top_videos)): ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Video ID', 'content-protect-pro'); ?></th>
                                <th><?php esc_html_e('Views', 'content-protect-pro'); ?></th>
                                <th><?php esc_html_e('Unique Sessions', 'content-protect-pro'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($top_videos as $video): ?>
                                <tr>
                                    <td><code><?php echo esc_html($video->video_id); ?></code></td>
                                    <td><?php echo esc_html(number_format($video->views)); ?></td>
                                    <td><?php echo esc_html(number_format($video->unique_sessions)); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p><?php esc_html_e(__('No video data available.', 'content-protect-pro'), 'content-protect-pro'); ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="cpp-card">
        <h2><?php esc_html_e('Quick Actions', 'content-protect-pro'); ?></h2>
        <div class="cpp-quick-actions">
            <a href="<?php echo esc_url(admin_url('admin.php?page=content-protect-pro-giftcodes&action=new')); ?>" class="button button-primary">
                <?php esc_html_e('+ Create Gift Code', 'content-protect-pro'); ?>
            </a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=content-protect-pro-videos&action=new')); ?>" class="button button-primary">
                <?php esc_html_e('+ Add Protected Video', 'content-protect-pro'); ?>
            </a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=content-protect-pro-analytics')); ?>" class="button">
                <?php esc_html_e('View Full Analytics', 'content-protect-pro'); ?>
            </a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=content-protect-pro-ai-assistant')); ?>" class="button">
                <?php esc_html_e('🤖 Ask AI Assistant', 'content-protect-pro'); ?>
            </a>
        </div>
    </div>
</div>

<style>
.cpp-dashboard {
    max-width: 1400px;
}

.cpp-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin: 2rem 0;
}

.cpp-stat-card {
    background: #fff;
    padding: 1.5rem;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    display: flex;
    align-items: center;
    gap: 1rem;
}

.cpp-stat-icon {
    font-size: 2.5rem;
}

.cpp-stat-content h3 {
    margin: 0;
    font-size: 2rem;
    color: #0073aa;
}

.cpp-stat-content p {
    margin: 0.25rem 0 0;
    color: #666;
    font-size: 0.9rem;
}

.cpp-dashboard-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
    margin: 2rem 0;
}

.cpp-card {
    background: #fff;
    padding: 1.5rem;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.cpp-card h2 {
    margin-top: 0;
    font-size: 1.3rem;
}

.cpp-quick-actions {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
}

@media (max-width: 782px) {
    .cpp-dashboard-row {
        grid-template-columns: 1fr;
    }
    
    .cpp-stats-grid {
        grid-template-columns: 1fr;
    }
}
</style>