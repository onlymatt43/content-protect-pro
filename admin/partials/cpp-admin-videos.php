<?php
/**
 * Provide a admin area view for protected videos management
 *
 * @package Content_Protect_Pro
 * @since   1.0.0
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

$video_manager = class_exists('CPP_Video_Manager') ? new CPP_Video_Manager() : null;
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="cpp-videos-content">
        <div class="notice notice-info">
            <p>
                <strong><?php _e('Protected Videos Management', 'content-protect-pro'); ?></strong><br>
                <?php _e('Manage your protected video content, set access requirements, and configure integrations with Bunny CDN and Presto Player.', 'content-protect-pro'); ?>
            </p>
        </div>
        
        <div class="cpp-videos-actions">
            <a href="#" class="button button-primary" onclick="addVideo()">
                <?php _e('Add Protected Video', 'content-protect-pro'); ?>
            </a>
            <a href="#" class="button button-secondary" onclick="bulkImport()">
                <?php _e('Bulk Import', 'content-protect-pro'); ?>
            </a>
            <a href="#" class="button button-secondary" onclick="exportVideos()">
                <?php _e('Export List', 'content-protect-pro'); ?>
            </a>
        </div>
        
        <!-- Integration Status -->
        <div class="cpp-integration-status">
            <h3><?php _e('Integration Status', 'content-protect-pro'); ?></h3>
            <?php
            $integration_settings = get_option('cpp_integration_settings', array());
            $bunny_enabled = !empty($integration_settings['bunny_enabled']);
            $presto_enabled = !empty($integration_settings['presto_enabled']);
            ?>
            
            <div class="cpp-status-grid">
                <div class="cpp-status-card">
                    <h4><?php _e('Bunny CDN', 'content-protect-pro'); ?></h4>
                    <p class="<?php echo $bunny_enabled ? 'cpp-status-enabled' : 'cpp-status-disabled'; ?>">
                        <?php echo $bunny_enabled ? __('Enabled', 'content-protect-pro') : __('Disabled', 'content-protect-pro'); ?>
                    </p>
                    <?php if (!$bunny_enabled): ?>
                        <a href="<?php echo admin_url('admin.php?page=content-protect-pro-settings&tab=integrations'); ?>" class="button button-small">
                            <?php _e('Configure', 'content-protect-pro'); ?>
                        </a>
                    <?php endif; ?>
                </div>
                
                <div class="cpp-status-card">
                    <h4><?php _e('Presto Player', 'content-protect-pro'); ?></h4>
                    <p class="<?php echo $presto_enabled && is_plugin_active('presto-player/presto-player.php') ? 'cpp-status-enabled' : 'cpp-status-disabled'; ?>">
                        <?php 
                        if ($presto_enabled && is_plugin_active('presto-player/presto-player.php')) {
                            _e('Enabled', 'content-protect-pro');
                        } elseif ($presto_enabled) {
                            _e('Configured but Plugin Inactive', 'content-protect-pro');
                        } else {
                            _e('Disabled', 'content-protect-pro');
                        }
                        ?>
                    </p>
                    <?php if (!$presto_enabled): ?>
                        <a href="<?php echo admin_url('admin.php?page=content-protect-pro-settings&tab=integrations'); ?>" class="button button-small">
                            <?php _e('Configure', 'content-protect-pro'); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Videos List -->
        <div class="cpp-videos-list">
            <h3><?php _e('Protected Videos', 'content-protect-pro'); ?></h3>
            
            <?php if ($video_manager): ?>
                <?php
                // Get videos from database
                global $wpdb;
                $table_name = $wpdb->prefix . 'cpp_protected_videos';
                $videos = $wpdb->get_results("SELECT * FROM {$table_name} ORDER BY created_at DESC LIMIT 20");
                ?>
                
                <?php if ($videos): ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('Video ID', 'content-protect-pro'); ?></th>
                                <th><?php _e('Title', 'content-protect-pro'); ?></th>
                                <th><?php _e('Required Value', 'content-protect-pro'); ?></th>
                                <th><?php _e('Integration', 'content-protect-pro'); ?></th>
                                <th><?php _e('Status', 'content-protect-pro'); ?></th>
                                <th><?php _e('Usage', 'content-protect-pro'); ?></th>
                                <th><?php _e('Actions', 'content-protect-pro'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($videos as $video): ?>
                                <tr>
                                    <td><?php echo esc_html($video->video_id); ?></td>
                                    <td>
                                        <strong><?php echo esc_html($video->title); ?></strong>
                                        <?php if ($video->description): ?>
                                            <br><small><?php echo esc_html(wp_trim_words($video->description, 10)); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo esc_html($video->required_value); ?></td>
                                    <td>
                                        <?php if ($video->bunny_video_id): ?>
                                            <span class="cpp-integration-badge cpp-bunny">Bunny CDN</span>
                                        <?php endif; ?>
                                        <?php if ($video->presto_video_id): ?>
                                            <span class="cpp-integration-badge cpp-presto">Presto Player</span>
                                        <?php endif; ?>
                                        <?php if (!$video->bunny_video_id && !$video->presto_video_id): ?>
                                            <span class="cpp-integration-badge cpp-direct">Direct URL</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="cpp-status-<?php echo esc_attr($video->status); ?>">
                                            <?php echo esc_html(ucfirst($video->status)); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo intval($video->usage_count); ?>
                                        <?php if ($video->max_uses): ?>
                                            / <?php echo intval($video->max_uses); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="#" onclick="editVideo(<?php echo esc_js($video->id); ?>)" class="button button-small">
                                            <?php _e('Edit', 'content-protect-pro'); ?>
                                        </a>
                                        <a href="#" onclick="deleteVideo(<?php echo esc_js($video->id); ?>)" class="button button-small cpp-delete">
                                            <?php _e('Delete', 'content-protect-pro'); ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="cpp-no-videos">
                        <div class="cpp-empty-state">
                            <div class="cpp-empty-icon">
                                <span class="dashicons dashicons-video-alt3"></span>
                            </div>
                            <h3><?php _e('No Protected Videos Yet', 'content-protect-pro'); ?></h3>
                            <p><?php _e('Start protecting your video content by adding your first protected video.', 'content-protect-pro'); ?></p>
                            <button class="button button-primary button-large" onclick="addVideo()">
                                <?php _e('Add Your First Video', 'content-protect-pro'); ?>
                            </button>
                        </div>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="notice notice-error">
                    <p><?php _e('Video manager not available. Please check plugin installation.', 'content-protect-pro'); ?></p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Quick Setup Guide -->
        <div class="cpp-setup-guide">
            <h3><?php _e('Quick Setup Guide', 'content-protect-pro'); ?></h3>
            <ol class="cpp-setup-steps">
                <li>
                    <strong><?php _e('Configure Integrations', 'content-protect-pro'); ?></strong>
                    <p><?php _e('Set up Bunny CDN or Presto Player integration in the settings.', 'content-protect-pro'); ?></p>
                </li>
                <li>
                    <strong><?php _e('Add Protected Videos', 'content-protect-pro'); ?></strong>
                    <p><?php _e('Create entries for videos you want to protect with gift codes.', 'content-protect-pro'); ?></p>
                </li>
                <li>
                    <strong><?php _e('Create Gift Codes', 'content-protect-pro'); ?></strong>
                    <p><?php _e('Generate gift codes with appropriate values for video access.', 'content-protect-pro'); ?></p>
                </li>
                <li>
                    <strong><?php _e('Use Shortcodes', 'content-protect-pro'); ?></strong>
                    <p><?php _e('Add [cpp_video_player video_id="123" required_value="10"] to your posts.', 'content-protect-pro'); ?></p>
                </li>
            </ol>
        </div>
    </div>
</div>

<style>
.cpp-videos-content {
    max-width: 1200px;
}

.cpp-videos-actions {
    margin: 20px 0;
}

.cpp-videos-actions .button {
    margin-right: 10px;
}

.cpp-status-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin: 20px 0;
}

.cpp-status-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 15px;
    text-align: center;
}

.cpp-status-card h4 {
    margin: 0 0 10px 0;
}

.cpp-status-enabled { color: #46b450; font-weight: bold; }
.cpp-status-disabled { color: #d63638; font-weight: bold; }

.cpp-integration-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: bold;
    text-transform: uppercase;
    margin: 2px;
}

.cpp-bunny { background: #ff6b35; color: white; }
.cpp-presto { background: #6c5ce7; color: white; }
.cpp-direct { background: #74b9ff; color: white; }

.cpp-status-active { color: #46b450; font-weight: bold; }
.cpp-status-inactive { color: #d63638; }

.cpp-no-videos {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 40px;
    text-align: center;
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
    margin-bottom: 20px;
}

.cpp-setup-guide {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    margin: 20px 0;
}

.cpp-setup-steps {
    list-style: none;
    padding: 0;
    counter-reset: step-counter;
}

.cpp-setup-steps li {
    counter-increment: step-counter;
    margin-bottom: 20px;
    padding-left: 40px;
    position: relative;
}

.cpp-setup-steps li::before {
    content: counter(step-counter);
    position: absolute;
    left: 0;
    top: 0;
    background: #2271b1;
    color: white;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: bold;
}

.cpp-setup-steps li strong {
    display: block;
    margin-bottom: 5px;
    color: #23282d;
}

.cpp-setup-steps li p {
    margin: 0;
    color: #646970;
}

.cpp-delete {
    color: #d63638 !important;
}

.cpp-delete:hover {
    color: #a00 !important;
}
</style>

<script>
function addVideo() {
    // This would open a modal or redirect to add video page
    alert('<?php _e('Add Video functionality coming soon!', 'content-protect-pro'); ?>');
}

function editVideo(videoId) {
    // This would open edit modal or redirect to edit page
    alert('<?php _e('Edit Video functionality coming soon!', 'content-protect-pro'); ?>\n<?php _e('Video ID:', 'content-protect-pro'); ?> ' + videoId);
}

function deleteVideo(videoId) {
    if (!confirm('<?php _e('Are you sure you want to delete this video?', 'content-protect-pro'); ?>')) {
        return;
    }
    
    // This would make AJAX call to delete video
    alert('<?php _e('Delete Video functionality coming soon!', 'content-protect-pro'); ?>\n<?php _e('Video ID:', 'content-protect-pro'); ?> ' + videoId);
}

function bulkImport() {
    alert('<?php _e('Bulk Import functionality coming soon!', 'content-protect-pro'); ?>');
}

function exportVideos() {
    alert('<?php _e('Export functionality coming soon!', 'content-protect-pro'); ?>');
}
</script>