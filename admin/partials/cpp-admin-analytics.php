<?php
/**
 * Provide a admin area view for analytics
 *
 * @package Content_Protect_Pro
 * @since   1.0.0
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

$analytics = class_exists('CPP_Analytics') ? new CPP_Analytics() : null;

// Get date range from request
$date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : date('Y-m-d', strtotime('-30 days'));
$date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : date('Y-m-d');

// Get analytics data
$analytics_data = array();
$summary_stats = array();

if ($analytics) {
    $analytics_data = $analytics->get_analytics(array(
        'date_from' => $date_from,
        'date_to' => $date_to,
        'limit' => 100
    ));
    
    $summary_stats = array(
        'total_events' => count($analytics_data['events'] ?? array()),
        'giftcode_validations' => 0,
        'video_accesses' => 0,
        'security_events' => 0
    );
    
    // Count event types
    if (!empty($analytics_data['events'])) {
        foreach ($analytics_data['events'] as $event) {
            switch ($event->event_type) {
                case 'giftcode_validated':
                case 'giftcode_validation_failed':
                    $summary_stats['giftcode_validations']++;
                    break;
                case 'video_access':
                case 'video_access_denied':
                    $summary_stats['video_accesses']++;
                    break;
                default:
                    if (strpos($event->object_type, 'security') !== false) {
                        $summary_stats['security_events']++;
                    }
            }
        }
    }
}
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="cpp-analytics-content">
        <!-- Date Range Filter -->
        <div class="cpp-analytics-filters">
            <form method="get" action="">
                <input type="hidden" name="page" value="content-protect-pro-analytics" />
                
                <label for="date_from"><?php _e('From:', 'content-protect-pro'); ?></label>
                <input type="date" id="date_from" name="date_from" value="<?php echo esc_attr($date_from); ?>" />
                
                <label for="date_to"><?php _e('To:', 'content-protect-pro'); ?></label>
                <input type="date" id="date_to" name="date_to" value="<?php echo esc_attr($date_to); ?>" />
                
                <input type="submit" class="button" value="<?php _e('Filter', 'content-protect-pro'); ?>" />
                
                <a href="?page=content-protect-pro-analytics" class="button button-secondary">
                    <?php _e('Reset', 'content-protect-pro'); ?>
                </a>
            </form>
        </div>
        
        <!-- Summary Statistics -->
        <div class="cpp-analytics-summary">
            <h2><?php _e('Analytics Summary', 'content-protect-pro'); ?></h2>
            <div class="cpp-stats-grid">
                <div class="cpp-stat-card">
                    <div class="cpp-stat-icon">
                        <span class="dashicons dashicons-chart-bar"></span>
                    </div>
                    <div class="cpp-stat-content">
                        <h3><?php echo intval($summary_stats['total_events']); ?></h3>
                        <p><?php _e('Total Events', 'content-protect-pro'); ?></p>
                        <span class="cpp-stat-period">
                            <?php echo sprintf(__('%s - %s', 'content-protect-pro'), $date_from, $date_to); ?>
                        </span>
                    </div>
                </div>
                
                <div class="cpp-stat-card">
                    <div class="cpp-stat-icon">
                        <span class="dashicons dashicons-tickets-alt"></span>
                    </div>
                    <div class="cpp-stat-content">
                        <h3><?php echo intval($summary_stats['giftcode_validations']); ?></h3>
                        <p><?php _e('Gift Code Events', 'content-protect-pro'); ?></p>
                        <span class="cpp-stat-period">
                            <?php _e('Validations & Failures', 'content-protect-pro'); ?>
                        </span>
                    </div>
                </div>
                
                <div class="cpp-stat-card">
                    <div class="cpp-stat-icon">
                        <span class="dashicons dashicons-video-alt3"></span>
                    </div>
                    <div class="cpp-stat-content">
                        <h3><?php echo intval($summary_stats['video_accesses']); ?></h3>
                        <p><?php _e('Video Access Events', 'content-protect-pro'); ?></p>
                        <span class="cpp-stat-period">
                            <?php _e('Access Attempts', 'content-protect-pro'); ?>
                        </span>
                    </div>
                </div>
                
                <div class="cpp-stat-card">
                    <div class="cpp-stat-icon">
                        <span class="dashicons dashicons-shield-alt"></span>
                    </div>
                    <div class="cpp-stat-content">
                        <h3><?php echo intval($summary_stats['security_events']); ?></h3>
                        <p><?php _e('Security Events', 'content-protect-pro'); ?></p>
                        <span class="cpp-stat-period">
                            <?php _e('Blocks & Warnings', 'content-protect-pro'); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Charts Section -->
        <div class="cpp-analytics-charts">
            <h2><?php _e('Activity Charts', 'content-protect-pro'); ?></h2>
            
            <div class="cpp-charts-grid">
                <div class="cpp-chart-container">
                    <h3><?php _e('Events Over Time', 'content-protect-pro'); ?></h3>
                    <div class="cpp-chart-placeholder">
                        <div class="cpp-chart-mock">
                            <div class="cpp-chart-bars">
                                <div class="cpp-chart-bar" style="height: 80%"></div>
                                <div class="cpp-chart-bar" style="height: 60%"></div>
                                <div class="cpp-chart-bar" style="height: 90%"></div>
                                <div class="cpp-chart-bar" style="height: 45%"></div>
                                <div class="cpp-chart-bar" style="height: 70%"></div>
                                <div class="cpp-chart-bar" style="height: 85%"></div>
                                <div class="cpp-chart-bar" style="height: 55%"></div>
                            </div>
                            <p class="cpp-chart-note"><?php _e('Chart visualization coming soon', 'content-protect-pro'); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="cpp-chart-container">
                    <h3><?php _e('Event Types Distribution', 'content-protect-pro'); ?></h3>
                    <div class="cpp-chart-placeholder">
                        <div class="cpp-pie-chart-mock">
                            <div class="cpp-pie-slice cpp-pie-giftcodes" data-percentage="45"></div>
                            <div class="cpp-pie-slice cpp-pie-videos" data-percentage="35"></div>
                            <div class="cpp-pie-slice cpp-pie-security" data-percentage="20"></div>
                        </div>
                        <div class="cpp-pie-legend">
                            <div class="cpp-legend-item">
                                <span class="cpp-legend-color cpp-giftcodes"></span>
                                <span><?php _e('Gift Codes', 'content-protect-pro'); ?></span>
                            </div>
                            <div class="cpp-legend-item">
                                <span class="cpp-legend-color cpp-videos"></span>
                                <span><?php _e('Videos', 'content-protect-pro'); ?></span>
                            </div>
                            <div class="cpp-legend-item">
                                <span class="cpp-legend-color cpp-security"></span>
                                <span><?php _e('Security', 'content-protect-pro'); ?></span>
                            </div>
                        </div>
                        <p class="cpp-chart-note"><?php _e('Interactive charts coming soon', 'content-protect-pro'); ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recent Events -->
        <div class="cpp-recent-events">
            <h2><?php _e('Recent Events', 'content-protect-pro'); ?></h2>
            
            <?php if ($analytics && !empty($analytics_data['events'])): ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Time', 'content-protect-pro'); ?></th>
                            <th><?php _e('Event Type', 'content-protect-pro'); ?></th>
                            <th><?php _e('Object', 'content-protect-pro'); ?></th>
                            <th><?php _e('IP Address', 'content-protect-pro'); ?></th>
                            <th><?php _e('Details', 'content-protect-pro'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($analytics_data['events'], 0, 20) as $event): ?>
                            <tr>
                                <td>
                                    <?php echo esc_html(date('M j, Y H:i', strtotime($event->created_at))); ?>
                                </td>
                                <td>
                                    <span class="cpp-event-type cpp-event-<?php echo esc_attr(str_replace('_', '-', $event->event_type)); ?>">
                                        <?php echo esc_html(ucwords(str_replace('_', ' ', $event->event_type))); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo esc_html($event->object_type); ?>
                                    <?php if (!empty($event->object_id)): ?>
                                        <br><small>ID: <?php echo esc_html($event->object_id); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($event->ip_address)): ?>
                                        <code><?php echo esc_html($event->ip_address); ?></code>
                                    <?php else: ?>
                                        <span class="cpp-no-data">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($event->metadata)): ?>
                                        <?php
                                        $metadata = maybe_unserialize($event->metadata);
                                        if (is_array($metadata)) {
                                            foreach ($metadata as $key => $value) {
                                                if (is_scalar($value)) {
                                                    echo '<small>' . esc_html($key) . ': ' . esc_html($value) . '</small><br>';
                                                }
                                            }
                                        }
                                        ?>
                                    <?php else: ?>
                                        <span class="cpp-no-data">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <div class="cpp-pagination">
                    <p>
                        <?php printf(__('Showing %d of %d events', 'content-protect-pro'), 
                            min(20, count($analytics_data['events'])), 
                            count($analytics_data['events'])
                        ); ?>
                    </p>
                </div>
                
            <?php elseif ($analytics): ?>
                <div class="cpp-no-events">
                    <div class="cpp-empty-state">
                        <div class="cpp-empty-icon">
                            <span class="dashicons dashicons-chart-line"></span>
                        </div>
                        <h3><?php _e('No Analytics Data', 'content-protect-pro'); ?></h3>
                        <p><?php _e('No events found for the selected date range. Try adjusting your filters or check back after some activity.', 'content-protect-pro'); ?></p>
                    </div>
                </div>
            <?php else: ?>
                <div class="notice notice-error">
                    <p><?php _e('Analytics system not available. Please check plugin installation.', 'content-protect-pro'); ?></p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Export Options -->
        <div class="cpp-analytics-export">
            <h2><?php _e('Export Analytics', 'content-protect-pro'); ?></h2>
            <p><?php _e('Export your analytics data for external analysis or reporting.', 'content-protect-pro'); ?></p>
            
            <div class="cpp-export-buttons">
                <button type="button" class="button button-secondary" onclick="exportCSV()">
                    <?php _e('Export to CSV', 'content-protect-pro'); ?>
                </button>
                <button type="button" class="button button-secondary" onclick="exportJSON()">
                    <?php _e('Export to JSON', 'content-protect-pro'); ?>
                </button>
                <button type="button" class="button button-secondary" onclick="emailReport()">
                    <?php _e('Email Report', 'content-protect-pro'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.cpp-analytics-content {
    max-width: 1200px;
}

.cpp-analytics-filters {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 15px;
    margin: 20px 0;
}

.cpp-analytics-filters form {
    display: flex;
    align-items: center;
    gap: 15px;
}

.cpp-analytics-filters label {
    font-weight: 600;
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
    font-size: 28px;
    font-weight: bold;
    margin: 0;
    color: #23282d;
}

.cpp-stat-content p {
    margin: 5px 0;
    color: #666;
    font-weight: 600;
}

.cpp-stat-period {
    font-size: 12px;
    color: #888;
}

.cpp-charts-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin: 20px 0;
}

.cpp-chart-container {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
}

.cpp-chart-placeholder {
    height: 200px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f9f9f9;
    border-radius: 4px;
    position: relative;
}

.cpp-chart-mock {
    text-align: center;
}

.cpp-chart-bars {
    display: flex;
    align-items: end;
    height: 120px;
    gap: 8px;
    margin-bottom: 10px;
}

.cpp-chart-bar {
    width: 20px;
    background: linear-gradient(to top, #0073aa, #2271b1);
    border-radius: 2px 2px 0 0;
}

.cpp-pie-chart-mock {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    background: conic-gradient(
        #0073aa 0deg 162deg,
        #00a32a 162deg 288deg,
        #d63638 288deg 360deg
    );
    margin: 0 auto 15px;
}

.cpp-pie-legend {
    display: flex;
    justify-content: center;
    gap: 15px;
    flex-wrap: wrap;
    margin-bottom: 10px;
}

.cpp-legend-item {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 12px;
}

.cpp-legend-color {
    width: 12px;
    height: 12px;
    border-radius: 2px;
}

.cpp-legend-color.cpp-giftcodes { background: #0073aa; }
.cpp-legend-color.cpp-videos { background: #00a32a; }
.cpp-legend-color.cpp-security { background: #d63638; }

.cpp-chart-note {
    color: #666;
    font-size: 12px;
    font-style: italic;
    margin: 0;
}

.cpp-event-type {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: bold;
    text-transform: uppercase;
}

.cpp-event-giftcode-validated { background: #d1e7dd; color: #0a3622; }
.cpp-event-giftcode-validation-failed { background: #f8d7da; color: #721c24; }
.cpp-event-video-access { background: #cff4fc; color: #055160; }
.cpp-event-video-access-denied { background: #f8d7da; color: #721c24; }
.cpp-event-security { background: #fff3cd; color: #664d03; }

.cpp-no-data {
    color: #c3c4c7;
    font-style: italic;
}

.cpp-no-events {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 40px;
    text-align: center;
    margin: 20px 0;
}

.cpp-empty-state {
    max-width: 400px;
    margin: 0 auto;
}

.cpp-empty-icon {
    font-size: 48px;
    color: #c3c4c7;
    margin-bottom: 20px;
}

.cpp-empty-state h3 {
    color: #23282d;
    margin-bottom: 10px;
}

.cpp-empty-state p {
    color: #646970;
}

.cpp-pagination {
    text-align: center;
    margin: 20px 0;
    color: #666;
}

.cpp-analytics-export {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    margin: 20px 0;
}

.cpp-export-buttons {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

@media (max-width: 768px) {
    .cpp-charts-grid {
        grid-template-columns: 1fr;
    }
    
    .cpp-stats-grid {
        grid-template-columns: 1fr;
    }
    
    .cpp-analytics-filters form {
        flex-direction: column;
        align-items: stretch;
    }
}
</style>

<script>
function exportCSV() {
    alert('<?php _e('CSV export functionality coming soon!', 'content-protect-pro'); ?>');
}

function exportJSON() {
    alert('<?php _e('JSON export functionality coming soon!', 'content-protect-pro'); ?>');
}

function emailReport() {
    alert('<?php _e('Email report functionality coming soon!', 'content-protect-pro'); ?>');
}
</script>