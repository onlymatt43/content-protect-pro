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
            $presto_enabled = !empty($integration_settings['presto_enabled']);
            ?>
            
            <div class="cpp-status-grid">
                <div class="cpp-status-card">
                    <h4><?php _e('Presto Player', 'content-protect-pro'); ?></h4>
                    <p class="<?php echo $presto_enabled && is_plugin_active('presto-player/presto-player.php') ? 'cpp-status-enabled' : 'cpp-status-disabled'; ?>">
                        <?php 
                        if ($presto_enabled && is_plugin_active('presto-player/presto-player.php')) {
                            _e('Enabled & Active', 'content-protect-pro');
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
            <h3><?php _e('Presto Player Videos', 'content-protect-pro'); ?></h3>
            <p><?php _e('Configure access requirements for your existing Presto Player videos. No duplication needed!', 'content-protect-pro'); ?></p>
            
            <?php
            // Get Presto Player videos directly
            if (function_exists('presto_player_get_videos')) {
                $presto_videos = presto_player_get_videos(array('posts_per_page' => 50));
                
                if ($presto_videos && !empty($presto_videos->posts)): ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('Video ID', 'content-protect-pro'); ?></th>
                                <th><?php _e('Title', 'content-protect-pro'); ?></th>
                                <th><?php _e('Required Minutes', 'content-protect-pro'); ?></th>
                                <th><?php _e('Protection Status', 'content-protect-pro'); ?></th>
                                <th><?php _e('Actions', 'content-protect-pro'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($presto_videos->posts as $video): 
                                $required_minutes = get_post_meta($video->ID, '_cpp_required_minutes', true) ?: 0;
                                $is_protected = $required_minutes > 0;
                            ?>
                                <tr>
                                    <td><?php echo esc_html($video->ID); ?></td>
                                    <td>
                                        <strong><?php echo esc_html($video->post_title); ?></strong>
                                        <?php if ($video->post_excerpt): ?>
                                            <br><small><?php echo esc_html(wp_trim_words($video->post_excerpt, 10)); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <input type="number" 
                                               value="<?php echo esc_attr($required_minutes); ?>" 
                                               min="0" 
                                               style="width: 80px;"
                                               onchange="updateVideoMinutes(<?php echo esc_js($video->ID); ?>, this.value)">
                                        <small><?php _e('minutes', 'content-protect-pro'); ?></small>
                                    </td>
                                    <td>
                                        <?php if ($is_protected): ?>
                                            <span class="cpp-status-active">✓ <?php _e('Protected', 'content-protect-pro'); ?></span>
                                        <?php else: ?>
                                            <span class="cpp-status-inactive"><?php _e('Unprotected', 'content-protect-pro'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="<?php echo get_edit_post_link($video->ID); ?>" target="_blank" class="button button-small">
                                            <?php _e('Edit in Presto', 'content-protect-pro'); ?>
                                        </a>
                                        <?php if ($is_protected): ?>
                                            <button class="button button-small" onclick="generateShortcode(<?php echo esc_js($video->ID); ?>)">
                                                <?php _e('Get Shortcode', 'content-protect-pro'); ?>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <div class="cpp-shortcode-info" style="margin-top: 20px; padding: 15px; background: #f1f1f1; border-radius: 4px;">
                        <h4><?php _e('How to Use Protected Videos', 'content-protect-pro'); ?></h4>
                        <p><?php _e('Once you set required minutes > 0, use this shortcode in your posts/pages:', 'content-protect-pro'); ?></p>
                        <code style="background: #fff; padding: 5px; display: block; margin: 10px 0;">[cpp_protected_video id="VIDEO_ID" code="GIFT_CODE"]</code>
                        <p><em><?php _e('Replace VIDEO_ID with the Presto Player video ID and GIFT_CODE with a valid gift code.', 'content-protect-pro'); ?></em></p>
                    </div>
                <?php else: ?>
                    <div class="cpp-no-videos">
                        <div class="cpp-empty-state">
                            <div class="cpp-empty-icon">
                                <span class="dashicons dashicons-video-alt3"></span>
                            </div>
                            <h3><?php _e('No Presto Player Videos Found', 'content-protect-pro'); ?></h3>
                            <p><?php _e('Create some videos in Presto Player first, then come back here to protect them.', 'content-protect-pro'); ?></p>
                            <a href="<?php echo admin_url('edit.php?post_type=pp_video_block'); ?>" class="button button-primary button-large" target="_blank">
                                <?php _e('Create Videos in Presto Player', 'content-protect-pro'); ?>
                            </a>
                        </div>
                    </div>
                <?php endif;
            } else { ?>
                <div class="notice notice-error">
                    <p><?php _e('Presto Player functions not available. Please ensure Presto Player is installed and active.', 'content-protect-pro'); ?></p>
                </div>
            <?php } ?>
        </div>
        
        <!-- Quick Setup Guide -->
        <div class="cpp-setup-guide">
            <h3><?php _e('Quick Setup Guide', 'content-protect-pro'); ?></h3>
            <ol class="cpp-setup-steps">
                <li>
                    <strong><?php _e('Configure Presto Player', 'content-protect-pro'); ?></strong>
                    <p><?php _e('Set up Presto Player integration in the settings.', 'content-protect-pro'); ?></p>
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
                    <p><?php _e('Add [cpp_video_player video_id="123"] to your posts.', 'content-protect-pro'); ?></p>
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
function updateVideoMinutes(videoId, minutes) {
    // Update the required minutes for a video via AJAX
    const formData = new FormData();
    formData.append('action', 'cpp_update_video_minutes');
    formData.append('video_id', videoId);
    formData.append('minutes', minutes);
    formData.append('nonce', '<?php echo wp_create_nonce("cpp_update_video_minutes"); ?>');
    
    fetch(ajaxurl, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update the protection status
            const row = event.target.closest('tr');
            const statusCell = row.querySelector('td:nth-child(4)');
            if (parseInt(minutes) > 0) {
                statusCell.innerHTML = '<span class="cpp-status-active">✓ <?php echo esc_js(__("Protected", "content-protect-pro")); ?></span>';
            } else {
                statusCell.innerHTML = '<span class="cpp-status-inactive"><?php echo esc_js(__("Unprotected", "content-protect-pro")); ?></span>';
            }
            
            // Show shortcode button if protected
            const actionsCell = row.querySelector('td:nth-child(5)');
            const shortcodeBtn = actionsCell.querySelector('button[onclick*="generateShortcode"]');
            if (parseInt(minutes) > 0 && !shortcodeBtn) {
                const newBtn = document.createElement('button');
                newBtn.className = 'button button-small';
                newBtn.onclick = () => generateShortcode(videoId);
                newBtn.innerHTML = '<?php echo esc_js(__("Get Shortcode", "content-protect-pro")); ?>';
                actionsCell.appendChild(document.createElement('br'));
                actionsCell.appendChild(newBtn);
            } else if (parseInt(minutes) === 0 && shortcodeBtn) {
                shortcodeBtn.remove();
            }
        } else {
            alert('<?php echo esc_js(__("Error updating video settings", "content-protect-pro")); ?>: ' + (data.message || 'Unknown error'));
            // Reset the input value
            event.target.value = event.target.defaultValue;
        }
    })
    .catch(error => {
        alert('<?php echo esc_js(__("Network error", "content-protect-pro")); ?>: ' + error.message);
        // Reset the input value
        event.target.value = event.target.defaultValue;
    });
}

function generateShortcode(videoId) {
    const shortcode = `[cpp_protected_video id="${videoId}" code="YOUR_GIFT_CODE"]`;
    
    // Copy to clipboard if possible
    if (navigator.clipboard) {
        navigator.clipboard.writeText(shortcode).then(() => {
            alert('<?php echo esc_js(__("Shortcode copied to clipboard!", "content-protect-pro")); ?>\n\n' + shortcode);
        });
    } else {
        // Fallback: show in prompt
        prompt('<?php echo esc_js(__("Copy this shortcode:", "content-protect-pro")); ?>', shortcode);
    }
}

// Remove old functions that are no longer needed
function addVideo() {
    alert('<?php echo esc_js(__("No longer needed! Just set minutes > 0 for any Presto Player video above.", "content-protect-pro")); ?>');
}

function editVideo(videoId) {
    alert('<?php echo esc_js(__("Use the Edit in Presto button to modify video details.", "content-protect-pro")); ?>');
}

function deleteVideo(videoId) {
    alert('<?php echo esc_js(__("Delete videos directly in Presto Player.", "content-protect-pro")); ?>');
}

function bulkImport() {
    alert('<?php echo esc_js(__("Not needed - all Presto Player videos are automatically available.", "content-protect-pro")); ?>');
}

function exportVideos() {
    alert('<?php echo esc_js(__("Export functionality coming soon!", "content-protect-pro")); ?>');
}
</script>