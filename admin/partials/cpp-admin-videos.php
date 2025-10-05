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
            <!-- Fallback debug control (visible even when per-row buttons are hidden) -->
            <div style="margin:10px 0; padding:10px; border:1px dashed #e1e1e1; background:#fafafa;">
                <label style="margin-right:8px;"><?php _e('Quick Debug (enter video ID):', 'content-protect-pro'); ?></label>
                <input type="text" id="cpp-debug-video-id" style="width:120px; margin-right:8px;" placeholder="1211" />
                <button type="button" id="cpp-debug-run" class="button button-secondary"><?php _e('Debug', 'content-protect-pro'); ?></button>
                <span style="margin-left:12px; color:#666; font-size:12px;"><?php _e('Runs the admin debug for video_id and opens the debug modal.', 'content-protect-pro'); ?></span>
            </div>
            
            <?php
            // Get Presto Player videos directly. Use Presto helper when available, otherwise try a fallback query
            $presto_videos = null;
            if (function_exists('presto_player_get_videos')) {
                $presto_videos = presto_player_get_videos(array('posts_per_page' => 50));
            } else {
                // Fallback: try to query the Presto Player post type directly if it exists
                $args = array(
                    'post_type' => 'pp_video_block',
                    'posts_per_page' => 50,
                    'post_status' => 'publish',
                );
                $posts = get_posts($args);
                if ($posts) {
                    // Normalize to an object with ->posts for the template below
                    $presto_videos = new stdClass();
                    $presto_videos->posts = $posts;
                }
            }

            if ($presto_videos && !empty($presto_videos->posts)): ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('Video ID', 'content-protect-pro'); ?></th>
                                <th><?php _e('Title', 'content-protect-pro'); ?></th>
                                <th><?php _e('Required Minutes', 'content-protect-pro'); ?></th>
                                <th><?php _e('Requires Code', 'content-protect-pro'); ?></th>
                                <th><?php _e('Debug', 'content-protect-pro'); ?></th>
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
                                        <?php
                                        // Show assigned allowed gift codes if we have a protected_videos row
                                        $protected_row = null;
                                        if ($video_manager) {
                                            $protected_row = $video_manager->get_protected_video($video->ID);
                                        }

                                        if (!empty($protected_row) && !empty($protected_row->allowed_giftcodes)) :
                                            $codes_display = esc_html($protected_row->allowed_giftcodes);
                                        ?>
                                            <div class="cpp-allowed-codes" style="margin-top:6px; font-size:12px; color:#555;">
                                                <strong><?php _e('Allowed Codes:', 'content-protect-pro'); ?></strong>
                                                <span style="margin-left:6px;" data-codes="<?php echo esc_attr($protected_row->allowed_giftcodes); ?>"><?php echo esc_html($codes_display); ?></span>
                                            </div>
                                        <?php else: ?>
                                            <div class="cpp-allowed-codes" style="margin-top:6px; font-size:12px; color:#555; display:none;">
                                                <strong><?php _e('Allowed Codes:', 'content-protect-pro'); ?></strong>
                                                <span style="margin-left:6px;" data-codes=""></span>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <input type="number" 
                                               value="<?php echo esc_attr($required_minutes); ?>" 
                                               min="0" 
                                               style="width: 80px;"
                                               onchange="updateVideoMinutes(<?php echo esc_js($video->ID); ?>, this.value, this)">
                                        <small><?php _e('minutes', 'content-protect-pro'); ?></small>
                                    </td>
                                    <td>
                                        <label style="display:flex;align-items:center;gap:8px;">
                                            <input type="checkbox" 
                                                   onchange="toggleProtection(<?php echo esc_js($video->ID); ?>, this.checked, this)"
                                                   <?php echo $is_protected ? 'checked' : ''; ?> />
                                            <span><?php echo $is_protected ? esc_html__('Yes', 'content-protect-pro') : esc_html__('No', 'content-protect-pro'); ?></span>
                                        </label>
                                    </td>
                                    <td class="cpp-debug-cell">
                                        <!-- Debug button (admin-only) -->
                                        <button class="button button-small cpp-debug-btn" data-video-id="<?php echo esc_attr($video->ID); ?>">
                                            <?php _e('Debug', 'content-protect-pro'); ?>
                                        </button>
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
                                        <button class="button button-small" onclick="manageCodes(<?php echo esc_js($video->ID); ?>)">
                                            <?php _e('Manage Codes', 'content-protect-pro'); ?>
                                        </button>
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
                <?php endif; ?>
        </div>
        
        <!-- Quick Setup Guide -->
        <div class="cpp-setup-guide">
            <h3><?php _e('Quick Setup Guide', 'content-protect-pro'); ?></h3>
            <ol class="cpp-setup-steps">
                <li>
                    <strong><?php _e('Configure Presto Player', 'content-protect-pro'); ?></strong>
                    <p><?php _e(__('Set up Presto Player integration in the settings.', 'content-protect-pro'), 'content-protect-pro'); ?></p>
                </li>
                <li>
                    <strong><?php _e('Add Protected Videos', 'content-protect-pro'); ?></strong>
                    <p><?php _e('Create videos in Presto Player, then use the controls above to set the required minutes and enable "Requires Code" for any video that should be protected by gift codes.', 'content-protect-pro'); ?></p>
                </li>
                <li>
                    <strong><?php _e('Create Gift Codes', 'content-protect-pro'); ?></strong>
                    <p><?php _e(__('Generate gift codes with appropriate values for video access.', 'content-protect-pro'), 'content-protect-pro'); ?></p>
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

<!-- Add Protected Video Modal (manual) -->
<div id="cpp-add-video-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:99997; align-items:center; justify-content:center;">
    <div style="background:#fff; max-width:700px; width:90%; max-height:80%; overflow:auto; padding:20px; border-radius:6px; position:relative;">
        <button id="cpp-add-video-close" style="position:absolute; right:10px; top:10px;">Close</button>
        <h3><?php echo esc_html__('Add Protected Video', 'content-protect-pro'); ?></h3>
        <p><?php echo esc_html__('Create a protected video entry for a Presto Player video (manual).', 'content-protect-pro'); ?></p>
        <div style="display:flex; gap:8px; margin-top:8px;">
            <div style="flex:1;">
                <label><?php echo esc_html__('Presto Video ID', 'content-protect-pro'); ?></label>
                <input id="cpp-add-video-id" type="text" style="width:100%;" />
            </div>
            <div style="flex:2;">
                <label><?php echo esc_html__('Title', 'content-protect-pro'); ?></label>
                <input id="cpp-add-video-title" type="text" style="width:100%;" />
            </div>
        </div>
        <div style="margin-top:12px; display:flex; gap:8px; justify-content:flex-end;">
            <button id="cpp-add-video-save" class="button button-primary"><?php echo esc_html__('Create', 'content-protect-pro'); ?></button>
            <button id="cpp-add-video-cancel" class="button"><?php echo esc_html__('Cancel', 'content-protect-pro'); ?></button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
    var modal = document.getElementById('cpp-add-video-modal');
    var close = document.getElementById('cpp-add-video-close');
    var cancel = document.getElementById('cpp-add-video-cancel');
    var save = document.getElementById('cpp-add-video-save');

    if (close) close.addEventListener('click', function(){ modal.style.display = 'none'; });
    if (cancel) cancel.addEventListener('click', function(){ modal.style.display = 'none'; });

    if (save) save.addEventListener('click', function(){
        var vid = document.getElementById('cpp-add-video-id').value || '';
        var title = document.getElementById('cpp-add-video-title').value || '';
        if (!vid || !title) { alert('<?php echo esc_js(__('Please provide both Video ID and Title', 'content-protect-pro')); ?>'); return; }

        var payload = {
            video_id: vid,
            title: title,
            required_minutes: 60,
            integration_type: 'presto',
            presto_player_id: vid,
            bunny_library_id: '',
            direct_url: '',
            description: '',
            status: 'active'
        };

        var form = new FormData();
        form.append('action', 'cpp_create_video');
        form.append('nonce', '<?php echo wp_create_nonce("cpp_create_video"); ?>');
        form.append('video_data', JSON.stringify(payload));

        save.disabled = true;
        save.innerText = '<?php echo esc_js(__('Creating...', 'content-protect-pro')); ?>';

        fetch(ajaxurl, { method: 'POST', body: form }).then(function(r){ return r.json(); }).then(function(json){
            save.disabled = false;
            save.innerText = '<?php echo esc_js(__('Create', 'content-protect-pro')); ?>';
            if (json.success) {
                alert('<?php echo esc_js(__('Protected video created', 'content-protect-pro')); ?>');
                location.reload();
            } else {
                alert('<?php echo esc_js(__('Error creating video', 'content-protect-pro')); ?>: ' + (json.data || json));
            }
        }).catch(function(err){
            save.disabled = false;
            save.innerText = '<?php echo esc_js(__('Create', 'content-protect-pro')); ?>';
            alert('<?php echo esc_js(__('Network error', 'content-protect-pro')); ?>: ' + err);
        });
    });
});
</script>

<script>
function updateVideoMinutes(videoId, minutes, el) {
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
        const row = el ? el.closest('tr') : null;
        if (data.success) {
            // Update the protection status
            if (row) {
                const statusCell = row.querySelector('td:nth-child(4)');
                if (parseInt(minutes) > 0) {
                    statusCell.innerHTML = '<span class="cpp-status-active">✓ <?php echo esc_js(__("Protected", "content-protect-pro")); ?></span>';
                } else {
                    statusCell.innerHTML = '<span class="cpp-status-inactive"><?php echo esc_js(__("Unprotected", "content-protect-pro")); ?></span>';
                }

                // Show or hide shortcode button if protected
                const actionsCell = row.querySelector('td:nth-child(5)');
                let shortcodeBtn = actionsCell ? actionsCell.querySelector('button[onclick*="generateShortcode"]') : null;
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
            }
        } else {
            alert('<?php echo esc_js(__("Error updating video settings", "content-protect-pro")); ?>: ' + (data.message || 'Unknown error'));
            // Reset the input value
            if (el) el.value = el.defaultValue;
        }
    })
    .catch(error => {
        alert('<?php echo esc_js(__("Network error", "content-protect-pro")); ?>: ' + error.message);
        // Reset the input value
        if (el) el.value = el.defaultValue;
    });
}

function toggleProtection(videoId, checked, el) {
    // Toggle protection by setting minutes to >0 or 0
    // Find the minutes input in the same row
    const row = el.closest('tr');
    const minutesInput = row ? row.querySelector('input[type="number"]') : null;
    let minutes = 0;
    if (checked) {
        // If there is already a minutes value > 0, keep it; otherwise default to 60
        minutes = minutesInput && parseInt(minutesInput.value) > 0 ? parseInt(minutesInput.value) : 60;
    } else {
        minutes = 0;
    }

    // Update the numeric input UI immediately
    if (minutesInput) minutesInput.value = minutes;

    // Call updateVideoMinutes to persist and update UI
    updateVideoMinutes(videoId, minutes, minutesInput);

    // Update the checkbox label text next to the checkbox
    const labelSpan = row ? row.querySelector('td:nth-child(4) span') : null;
    if (labelSpan) {
        labelSpan.textContent = checked ? '<?php echo esc_js(__('Yes', 'content-protect-pro')); ?>' : '<?php echo esc_js(__('No', 'content-protect-pro')); ?>';
    }
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
    // Open the Manual Add Protected Video modal
    document.getElementById('cpp-add-video-modal').style.display = 'flex';
}

function editVideo(videoId) {
    alert('<?php echo esc_js(__(__("Use the Edit in Presto button to modify video details.", 'content-protect-pro'), "content-protect-pro")); ?>');
}

function deleteVideo(videoId) {
    alert('<?php echo esc_js(__(__("Delete videos directly in Presto Player.", 'content-protect-pro'), "content-protect-pro")); ?>');
}

function bulkImport() {
    alert('<?php echo esc_js(__("Not needed - all Presto Player videos are automatically available.", "content-protect-pro")); ?>');
}

function exportVideos() {
    alert('<?php echo esc_js(__("Export functionality coming soon!", "content-protect-pro")); ?>');
}
</script>

<!-- Debug Modal -->
<div id="cpp-debug-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:99999; align-items:center; justify-content:center;">
    <div style="background:#fff; max-width:900px; width:90%; max-height:80%; overflow:auto; padding:20px; border-radius:6px; position:relative;">
        <button id="cpp-debug-close" style="position:absolute; right:10px; top:10px;">Close</button>
        <div style="display:flex; align-items:center; justify-content:space-between; gap:12px;">
            <h3 style="margin:0;"><?php echo esc_html__('Debug Video Row', 'content-protect-pro'); ?></h3>
            <div style="display:flex; gap:8px; align-items:center;">
                <span id="cpp-debug-status" style="padding:6px 10px; border-radius:4px; font-weight:bold; display:inline-block;">Status</span>
                <button id="cpp-debug-copy" class="button"><?php echo esc_html__('Copy JSON', 'content-protect-pro'); ?></button>
            </div>
        </div>
        <div style="margin-top:12px;">
            <pre id="cpp-debug-output" style="white-space:pre-wrap; word-break:break-word; background:#f7f7f7; padding:10px; border-radius:4px; max-height:40vh; overflow:auto;"></pre>
        </div>
        <div style="margin-top:12px; display:none;" id="cpp-debug-presto-container">
            <h4><?php echo esc_html__('Presto Embed HTML', 'content-protect-pro'); ?></h4>
            <div id="cpp-debug-presto" style="background:#fff; border:1px solid #eee; padding:10px; border-radius:4px; max-height:20vh; overflow:auto;"></div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
    // Attach click handlers to debug buttons
    document.querySelectorAll('.cpp-debug-btn').forEach(function(btn){
        btn.addEventListener('click', function(){
            var vid = this.getAttribute('data-video-id');
            var output = document.getElementById('cpp-debug-output');
            output.textContent = 'Loading...';
            document.getElementById('cpp-debug-modal').style.display = 'flex';

            var data = new FormData();
            data.append('action', 'cpp_debug_video_row');
            data.append('video_id', vid);

            fetch(ajaxurl, { method: 'POST', body: data }).then(function(r){
                return r.json();
            }).then(function(json){
                var statusEl = document.getElementById('cpp-debug-status');
                var prestoContainer = document.getElementById('cpp-debug-presto-container');
                var prestoEl = document.getElementById('cpp-debug-presto');

                if (json.success) {
                    output.textContent = JSON.stringify(json.data, null, 2);
                    // Determine status: success if video_row present
                    if (json.data && json.data.video_row) {
                        statusEl.textContent = 'OK';
                        statusEl.style.background = '#46b450';
                        statusEl.style.color = '#fff';
                    } else {
                        statusEl.textContent = 'Missing video_row';
                        statusEl.style.background = '#dc3232';
                        statusEl.style.color = '#fff';
                    }

                    // Show presto embed if present
                    if (json.data && json.data.presto_embed) {
                        prestoContainer.style.display = 'block';
                        prestoEl.innerHTML = json.data.presto_embed;
                    } else {
                        prestoContainer.style.display = 'none';
                        prestoEl.innerHTML = '';
                    }
                } else {
                    output.textContent = 'Error: ' + JSON.stringify(json.data || json, null, 2);
                    statusEl.textContent = 'Error';
                    statusEl.style.background = '#dc3232';
                    statusEl.style.color = '#fff';
                    prestoContainer.style.display = 'none';
                }
            }).catch(function(err){
                output.textContent = 'Fetch error: ' + err;
            });
        });
    });

    var close = document.getElementById('cpp-debug-close');
    if (close) close.addEventListener('click', function(){ document.getElementById('cpp-debug-modal').style.display = 'none'; });

    var copyBtn = document.getElementById('cpp-debug-copy');
    if (copyBtn) copyBtn.addEventListener('click', function(){
        var text = document.getElementById('cpp-debug-output').textContent || '';
        if (!text) return;
        navigator.clipboard.writeText(text).then(function(){
            copyBtn.textContent = 'Copied';
            setTimeout(function(){ copyBtn.textContent = 'Copy JSON'; }, 1500);
        }).catch(function(){
            alert('Copy failed');
        });
    });
});
</script>

<!-- Manage Codes Modal -->
<div id="cpp-manage-codes-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:99998; align-items:center; justify-content:center;">
    <div style="background:#fff; max-width:800px; width:96%; max-height:80%; overflow:auto; padding:20px; border-radius:6px; position:relative;">
        <button id="cpp-manage-codes-close" style="position:absolute; right:10px; top:10px;">Close</button>
        <h3><?php echo esc_html__('Manage Allowed Gift Codes', 'content-protect-pro'); ?></h3>
        <p><?php echo esc_html__('Add or remove gift codes that grant access. You can type a code and press Enter to add it. Set the required minutes for access below.', 'content-protect-pro'); ?></p>
        <div style="display:flex; gap:12px; align-items:flex-start; margin-top:8px;">
            <div style="flex:0 0 160px;">
                <label><?php echo esc_html__('Required Minutes', 'content-protect-pro'); ?></label>
                <input id="cpp-manage-codes-minutes" type="number" min="0" style="width:100%; padding:6px; margin-top:6px;" />
            </div>
            <div style="flex:1;">
                <label><?php echo esc_html__('Codes', 'content-protect-pro'); ?></label>
                <div id="cpp-manage-codes-chips" style="min-height:48px; border:1px solid #e1e1e1; padding:8px; border-radius:6px; display:flex; gap:6px; flex-wrap:wrap; align-items:center; background:#fff; margin-top:6px;"></div>
                <input id="cpp-manage-codes-input" type="text" placeholder="Add code and press Enter" style="width:100%; margin-top:8px; padding:8px;" />
            </div>
        </div>
        <div style="margin-top:12px; display:flex; gap:8px; justify-content:flex-end;">
            <button id="cpp-manage-codes-save" class="button button-primary"><?php echo esc_html__('Save', 'content-protect-pro'); ?></button>
            <button id="cpp-manage-codes-cancel" class="button"><?php echo esc_html__('Cancel', 'content-protect-pro'); ?></button>
        </div>
    </div>
</div>

<script>
var cpp_manage_codes_current_video = null;
function manageCodes(videoId) {
    cpp_manage_codes_current_video = videoId;
    // Open modal and fetch existing codes via debug endpoint
    document.getElementById('cpp-manage-codes-minutes').value = '';
    document.getElementById('cpp-manage-codes-chips').innerHTML = '';
    document.getElementById('cpp-manage-codes-input').value = '';
    document.getElementById('cpp-manage-codes-modal').style.display = 'flex';

    var data = new FormData();
    data.append('action', 'cpp_debug_video_row');
    data.append('video_id', videoId);

    fetch(ajaxurl, { method: 'POST', body: data }).then(function(r){ return r.json(); }).then(function(json){
        if (json.success && json.data && json.data.video_row) {
            var codes = json.data.video_row.allowed_giftcodes || '';
            var minutes = json.data.video_row.required_minutes || 0;
            document.getElementById('cpp-manage-codes-minutes').value = minutes;
            var chips = document.getElementById('cpp-manage-codes-chips');
            chips.innerHTML = '';
            if (codes) {
                codes.split(',').map(function(c){ return c.trim(); }).filter(Boolean).forEach(function(code){
                    addCodeChip(code);
                });
            }
        }
    }).catch(function(){ /* ignore */ });
}

function addCodeChip(code) {
    var chips = document.getElementById('cpp-manage-codes-chips');
    // avoid duplicate
    var exists = Array.from(chips.querySelectorAll('.cpp-code-chip')).some(function(ch){ return ch.getAttribute('data-code') === code; });
    if (exists) return;
    var chip = document.createElement('span');
    chip.className = 'cpp-code-chip';
    chip.textContent = code;
    chip.setAttribute('data-code', code);
    chip.style.padding = '6px 8px';
    chip.style.background = '#f6f7f8';
    chip.style.borderRadius = '16px';
    chip.style.display = 'inline-flex';
    chip.style.alignItems = 'center';
    chip.style.gap = '8px';
    var close = document.createElement('button');
    close.type = 'button';
    close.textContent = '×';
    close.style.border = 'none';
    close.style.background = 'transparent';
    close.style.cursor = 'pointer';
    close.addEventListener('click', function(){ chip.remove(); });
    chip.appendChild(close);
    chips.appendChild(chip);
}

document.addEventListener('DOMContentLoaded', function(){
    var modal = document.getElementById('cpp-manage-codes-modal');
    var close = document.getElementById('cpp-manage-codes-close');
    var cancel = document.getElementById('cpp-manage-codes-cancel');
    var save = document.getElementById('cpp-manage-codes-save');
    var input = document.getElementById('cpp-manage-codes-input');

    if (close) close.addEventListener('click', function(){ modal.style.display = 'none'; });
    if (cancel) cancel.addEventListener('click', function(){ modal.style.display = 'none'; });

    if (input) input.addEventListener('keydown', function(e){
        if (e.key === 'Enter') {
            e.preventDefault();
            var v = input.value.trim();
            if (v) {
                addCodeChip(v);
                input.value = '';
            }
        }
    });

    if (save) save.addEventListener('click', function(){
        var codes = [];
        document.querySelectorAll('#cpp-manage-codes-chips .cpp-code-chip').forEach(function(ch){ codes.push(ch.getAttribute('data-code')); });
        var minutesVal = parseInt(document.getElementById('cpp-manage-codes-minutes').value || 0, 10);
        var form = new FormData();
        form.append('action', 'cpp_update_video_allowed_codes');
        form.append('video_id', cpp_manage_codes_current_video);
        form.append('codes', codes.join(','));
        form.append('minutes', minutesVal);
        form.append('nonce', '<?php echo wp_create_nonce("cpp_update_video_allowed_codes"); ?>');

        save.disabled = true;
        save.innerText = '<?php echo esc_js(__('Saving...', 'content-protect-pro')); ?>';

        fetch(ajaxurl, { method: 'POST', body: form }).then(function(r){ return r.json(); }).then(function(json){
            save.disabled = false;
            save.innerText = '<?php echo esc_js(__('Save', 'content-protect-pro')); ?>';
            if (json.success) {
                // Update the table row display for this video
                var codesSaved = (json.data && json.data.allowed_giftcodes) ? json.data.allowed_giftcodes : '';
                var rows = document.querySelectorAll('table.wp-list-table tbody tr');
                for (var i = 0; i < rows.length; i++) {
                    var firstCell = rows[i].querySelector('td');
                    if (firstCell && firstCell.textContent.trim() == cpp_manage_codes_current_video.toString()) {
                        var codesDiv = rows[i].querySelector('.cpp-allowed-codes');
                        if (codesDiv) {
                            var span = codesDiv.querySelector('span[data-codes]');
                            if (codesSaved) {
                                codesDiv.style.display = '';
                                span.textContent = codesSaved;
                                span.setAttribute('data-codes', codesSaved);
                            } else {
                                codesDiv.style.display = 'none';
                                span.textContent = '';
                                span.setAttribute('data-codes', '');
                            }
                        }
                        // also update the minutes input in the table row
                        var minutesInput = rows[i].querySelector('input[type="number"]');
                        if (minutesInput) minutesInput.value = minutesVal;
                        break;
                    }
                }

                modal.style.display = 'none';
                alert('<?php echo esc_js(__('Codes saved', 'content-protect-pro')); ?>');
            } else {
                // If server returned structured validation info, show details and allow force-save
                var data = json.data || {};
                if (data.missing_codes || data.insufficient_codes) {
                    var msg = 'Some codes are missing or have insufficient duration:\n\n';
                    if (data.missing_codes && data.missing_codes.length) {
                        msg += 'Missing codes: ' + data.missing_codes.join(', ') + '\n';
                    }
                    if (data.insufficient_codes && data.insufficient_codes.length) {
                        msg += 'Insufficient codes (code:duration): ' + data.insufficient_codes.map(function(i){ return i.code + ':' + i.duration; }).join(', ') + '\n';
                    }
                    msg += '\nDo you want to force-save these codes anyway? (This may cause access to be denied for users using these codes)';

                    if (confirm(msg)) {
                        // resend with force flag
                        form.append('force', '1');
                        save.disabled = true;
                        save.innerText = '<?php echo esc_js(__('Saving...', 'content-protect-pro')); ?>';
                        fetch(ajaxurl, { method: 'POST', body: form }).then(function(r2){ return r2.json(); }).then(function(json2){
                            save.disabled = false;
                            save.innerText = '<?php echo esc_js(__('Save', 'content-protect-pro')); ?>';
                            if (json2.success) {
                                modal.style.display = 'none';
                                alert('<?php echo esc_js(__('Codes saved (forced)', 'content-protect-pro')); ?>');
                                location.reload();
                            } else {
                                alert('<?php echo esc_js(__('Error saving codes', 'content-protect-pro')); ?>: ' + (json2.data || json2));
                            }
                        }).catch(function(err){
                            save.disabled = false;
                            save.innerText = '<?php echo esc_js(__('Save', 'content-protect-pro')); ?>';
                            alert('<?php echo esc_js(__('Network error', 'content-protect-pro')); ?>: ' + err);
                        });
                    }
                } else {
                    alert('<?php echo esc_js(__('Error saving codes', 'content-protect-pro')); ?>: ' + (json.data || json));
                }
            }
        }).catch(function(err){
            save.disabled = false;
            save.innerText = '<?php echo esc_js(__('Save', 'content-protect-pro')); ?>';
            alert('<?php echo esc_js(__('Network error', 'content-protect-pro')); ?>: ' + err);
        });
    });
});
</script>