<?php
/**
 * Provide a admin area view for gift codes management
 *
 * @package Content_Protect_Pro
 * @since   1.0.0
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Initialize gift code manager
$giftcode_manager = class_exists('CPP_Giftcode_Manager') ? new CPP_Giftcode_Manager() : null;

// Handle actions
$action = isset(sanitize_text_field($_GET['action'] ?? '')) ? sanitize_text_field(sanitize_text_field($_GET['action'] ?? '')) : '';
$giftcode_id = isset(sanitize_text_field($_GET['id'] ?? '')) ? intval(sanitize_text_field($_GET['id'] ?? '')) : 0;

if ($giftcode_manager && isset(sanitize_text_field($_POST['submit'] ?? ''))) {
    if ($action === 'add' || $action === 'edit') {
        // Handle form submission
        check_admin_referer('cpp_giftcode_nonce');
        
        // Generate secure token and simple code
        $secure_token = isset(sanitize_text_field($_POST['secure_token'] ?? '')) && !empty(sanitize_text_field($_POST['secure_token'] ?? '')) ? 
            sanitize_text_field(sanitize_text_field($_POST['secure_token'] ?? '')) : bin2hex(random_bytes(32));
        $simple_code = isset(sanitize_text_field($_POST['code'] ?? '')) && !empty(sanitize_text_field($_POST['code'] ?? '')) ? 
            sanitize_text_field(sanitize_text_field($_POST['code'] ?? '')) : 
            cpp_generate_simple_code_from_token($secure_token);
        
        // Calculate duration in minutes based on unit
        $duration_value = intval(sanitize_text_field($_POST['duration_value'] ?? ''));
        $duration_unit = sanitize_text_field(sanitize_text_field($_POST['duration_unit'] ?? ''));
        $duration_minutes = cpp_convert_to_minutes($duration_value, $duration_unit);
        
        $code_data = array(
            'code' => $simple_code,
            'secure_token' => $secure_token,
            'duration_minutes' => $duration_minutes,
            'duration_display' => $duration_value . ' ' . $duration_unit,
            'expires_at' => sanitize_text_field(sanitize_text_field($_POST['expires_at'] ?? '')),
            'status' => sanitize_text_field(sanitize_text_field($_POST['status'] ?? '')),
            'description' => sanitize_textarea_field(sanitize_text_field($_POST['description'] ?? '')),
            // Optional per-code overlay image (attachment ID only) and purchase link
            'overlay_image' => isset(sanitize_text_field($_POST['overlay_image'] ?? '')) ? intval(sanitize_text_field($_POST['overlay_image'] ?? '')) : 0,
            'purchase_url' => isset(sanitize_text_field($_POST['purchase_url'] ?? '')) ? esc_url_raw(sanitize_text_field($_POST['purchase_url'] ?? '')) : '',
            'ip_restrictions' => sanitize_textarea_field(sanitize_text_field($_POST['ip_restrictions'] ?? ''))
        );
        
        if ($action === 'add') {
            $result = $giftcode_manager->create_code($code_data);
            if ($result) {
                echo '<div class="notice notice-success"><p>' . __('Gift code created successfully!', 'content-protect-pro') . '</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>' . __(__('Error creating gift code.', 'content-protect-pro'), 'content-protect-pro') . '</p></div>';
            }
        } elseif ($action === 'edit' && $giftcode_id) {
            $result = $giftcode_manager->update_code($giftcode_id, $code_data);
            if ($result) {
                echo '<div class="notice notice-success"><p>' . __('Gift code updated successfully!', 'content-protect-pro') . '</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>' . __(__('Error updating gift code.', 'content-protect-pro'), 'content-protect-pro') . '</p></div>';
            }
        }
    }
} elseif ($action === 'delete' && $giftcode_id && $giftcode_manager) {
    // Handle delete action
    if (wp_verify_nonce(sanitize_text_field($_GET['_wpnonce'] ?? ''), 'delete_giftcode_' . $giftcode_id)) {
        $result = $giftcode_manager->delete_code($giftcode_id);
        if ($result) {
            echo '<div class="notice notice-success"><p>' . __('Gift code deleted successfully!', 'content-protect-pro') . '</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>' . __(__('Error deleting gift code.', 'content-protect-pro'), 'content-protect-pro') . '</p></div>';
        }
    }
    $action = ''; // Reset to list view
}

// Get gift codes for list view
$gift_codes = array();
if ($giftcode_manager && ($action === '' || $action === 'list')) {
    $gift_codes = $giftcode_manager->get_codes(array('limit' => 100));
}

// Get single gift code for edit view
$edit_code = null;
if ($giftcode_manager && $action === 'edit' && $giftcode_id) {
    $edit_code = $giftcode_manager->get_code($giftcode_id);
}
?>

<div class="wrap">
    <h1>
        <?php echo esc_html(get_admin_page_title()); ?>
        <?php if ($action === '' || $action === 'list'): ?>
            <a href="<?php echo admin_url('admin.php?page=content-protect-pro-giftcodes&action=add'); ?>" class="page-title-action">
                <?php _e('Add New', 'content-protect-pro'); ?>
            </a>
        <?php endif; ?>
    </h1>
    
    <?php if ($action === 'add' || $action === 'edit'): ?>
        <!-- Add/Edit Form -->
        <?php if (function_exists('wp_enqueue_media')) { wp_enqueue_media(); } ?>
        <form method="post" action="">
            <?php wp_nonce_field('cpp_giftcode_nonce'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="code"><?php _e('Gift Code', 'content-protect-pro'); ?> *</label>
                    </th>
                    <td>
                        <input type="text" id="code" name="code" value="<?php echo $edit_code ? esc_attr($edit_code->code) : ''; ?>" class="regular-text" required />
                        <button type="button" class="button button-secondary" onclick="generateCode()">
                            <?php _e('Generate Code', 'content-protect-pro'); ?>
                        </button>
                        <p class="description"><?php _e(__('Unique alphanumeric code for access validation.', 'content-protect-pro'), 'content-protect-pro'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="duration_value"><?php _e('Access Duration', 'content-protect-pro'); ?> *</label>
                    </th>
                    <td>
                        <input type="number" id="duration_value" name="duration_value" value="<?php echo $edit_code && $edit_code->duration_display ? intval(explode(' ', $edit_code->duration_display)[0]) : '1'; ?>" min="1" max="999" class="small-text" required />
                        <select id="duration_unit" name="duration_unit" class="regular-text">
                            <option value="minutes" <?php selected($edit_code && $edit_code->duration_display ? explode(' ', $edit_code->duration_display)[1] : 'hours', 'minutes'); ?>><?php _e('Minutes', 'content-protect-pro'); ?></option>
                            <option value="hours" <?php selected($edit_code && $edit_code->duration_display ? explode(' ', $edit_code->duration_display)[1] : 'hours', 'hours'); ?>><?php _e('Hours', 'content-protect-pro'); ?></option>
                            <option value="days" <?php selected($edit_code && $edit_code->duration_display ? explode(' ', $edit_code->duration_display)[1] : 'hours', 'days'); ?>><?php _e('Days', 'content-protect-pro'); ?></option>
                            <option value="months" <?php selected($edit_code && $edit_code->duration_display ? explode(' ', $edit_code->duration_display)[1] : 'hours', 'months'); ?>><?php _e('Months', 'content-protect-pro'); ?></option>
                            <option value="years" <?php selected($edit_code && $edit_code->duration_display ? explode(' ', $edit_code->duration_display)[1] : 'hours', 'years'); ?>><?php _e('Years', 'content-protect-pro'); ?></option>
                        </select>
                        <p class="description"><?php _e('Duration of access granted by this code. Each use creates a session tied to client IP.', 'content-protect-pro'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="secure_token"><?php _e('Secure Token', 'content-protect-pro'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="secure_token" name="secure_token" value="<?php echo $edit_code ? esc_attr($edit_code->secure_token) : ''; ?>" class="large-text" readonly />
                        <button type="button" class="button button-secondary" onclick="regenerateToken()">
                            <?php _e('Regenerate Token', 'content-protect-pro'); ?>
                        </button>
                        <p class="description"><?php _e('64-character secure token used for session validation and cookie signing.', 'content-protect-pro'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="ip_restrictions"><?php _e('IP Restrictions (Optional)', 'content-protect-pro'); ?></label>
                    </th>
                    <td>
                        <textarea id="ip_restrictions" name="ip_restrictions" rows="3" class="large-text" placeholder="192.168.1.0/24&#10;203.0.113.5&#10;2001:db8::/32"><?php echo $edit_code ? esc_textarea($edit_code->ip_restrictions) : ''; ?></textarea>
                        <p class="description"><?php _e('Optional IP restrictions. One per line. Supports CIDR notation (192.168.1.0/24) and IPv6.', 'content-protect-pro'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="expires_at"><?php _e('Code Validity Until', 'content-protect-pro'); ?></label>
                    </th>
                    <td>
                        <input type="datetime-local" id="expires_at" name="expires_at" value="<?php echo $edit_code && $edit_code->expires_at ? date('Y-m-d\TH:i', strtotime($edit_code->expires_at)) : ''; ?>" />
                        <p class="description"><?php _e('When this code becomes invalid for new sessions. Active sessions continue until their time expires.', 'content-protect-pro'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="status"><?php _e('Status', 'content-protect-pro'); ?></label>
                    </th>
                    <td>
                        <select id="status" name="status">
                            <option value="active" <?php selected($edit_code ? $edit_code->status : 'active', 'active'); ?>><?php _e('Active', 'content-protect-pro'); ?></option>
                            <option value="inactive" <?php selected($edit_code ? $edit_code->status : 'active', 'inactive'); ?>><?php _e('Inactive', 'content-protect-pro'); ?></option>
                            <option value="used" <?php selected($edit_code ? $edit_code->status : 'active', 'used'); ?>><?php _e('Used', 'content-protect-pro'); ?></option>
                            <option value="expired" <?php selected($edit_code ? $edit_code->status : 'active', 'expired'); ?>><?php _e('Expired', 'content-protect-pro'); ?></option>
                        </select>
                        <p class="description"><?php _e(__('Current status of this gift code.', 'content-protect-pro'), 'content-protect-pro'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="description"><?php _e('Description', 'content-protect-pro'); ?></label>
                    </th>
                    <td>
                        <textarea id="description" name="description" rows="3" class="large-text"><?php echo $edit_code ? esc_textarea($edit_code->description) : ''; ?></textarea>
                        <p class="description"><?php _e(__('Optional description or notes about this gift code.', 'content-protect-pro'), 'content-protect-pro'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="overlay_image"><?php _e('Overlay Image (Optional)', 'content-protect-pro'); ?></label>
                    </th>
                    <td>
                        <?php
                        // Enforce attachment ID only. Show preview URL if attachment exists.
                        $overlay_value = '';
                        $overlay_preview = '';
                        if ($edit_code && !empty($edit_code->overlay_image)) {
                            if (ctype_digit((string) $edit_code->overlay_image)) {
                                $overlay_value = intval($edit_code->overlay_image);
                                $overlay_preview = function_exists('wp_get_attachment_url') ? wp_get_attachment_url($overlay_value) : '';
                            } else {
                                // Legacy non-numeric values will be migrated/cleared by migrations; don't accept here
                                $overlay_value = '';
                            }
                        }
                        ?>
                        <!-- Store attachment ID only -->
                        <input type="hidden" id="overlay_image" name="overlay_image" value="<?php echo esc_attr($overlay_value); ?>" />
                        <button type="button" class="button" id="overlay_image_button"><?php _e('Upload/Select Image', 'content-protect-pro'); ?></button>
                        <p class="description"><?php _e('Select a media library image (attachment ID will be stored). Legacy external URLs are no longer supported.', 'content-protect-pro'); ?></p>
                        <div id="overlay_image_preview" style="margin-top:8px;">
                            <?php if (!empty($overlay_preview)): ?>
                                <img src="<?php echo esc_url($overlay_preview); ?>" alt="" style="max-width:200px; height:auto; border:1px solid #ddd; padding:4px; background:#fff;" />
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="purchase_url"><?php _e('Purchase URL (Optional)', 'content-protect-pro'); ?></label>
                    </th>
                    <td>
                        <input type="url" id="purchase_url" name="purchase_url" value="<?php echo $edit_code && !empty($edit_code->purchase_url) ? esc_attr($edit_code->purchase_url) : ''; ?>" class="regular-text" placeholder="https://example.com/buy" />
                        <p class="description"><?php _e('Optional link users will be sent to when they click the overlay purchase button. Defaults to site home if empty.', 'content-protect-pro'); ?></p>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" name="submit" class="button button-primary" value="<?php echo $action === 'edit' ? __('Update Gift Code', 'content-protect-pro') : __('Create Gift Code', 'content-protect-pro'); ?>" />
                <a href="<?php echo admin_url('admin.php?page=content-protect-pro-giftcodes'); ?>" class="button button-secondary">
                    <?php _e('Cancel', 'content-protect-pro'); ?>
                </a>
            </p>
        </form>
        
    <?php else: ?>
        <!-- List View -->
        <div class="cpp-giftcodes-list">
            <!-- Bulk Actions -->
            <div class="tablenav top">
                <div class="alignleft actions bulkactions">
                    <label for="bulk-action-selector-top" class="screen-reader-text"><?php _e('Select bulk action', 'content-protect-pro'); ?></label>
                    <select name="action" id="bulk-action-selector-top">
                        <option value="-1"><?php _e('Bulk actions', 'content-protect-pro'); ?></option>
                        <option value="activate"><?php _e('Activate', 'content-protect-pro'); ?></option>
                        <option value="deactivate"><?php _e('Deactivate', 'content-protect-pro'); ?></option>
                        <option value="delete"><?php _e('Delete', 'content-protect-pro'); ?></option>
                    </select>
                    <input type="submit" class="button action" value="<?php _e('Apply', 'content-protect-pro'); ?>" />
                </div>
                
                <div class="alignright actions">
                    <button type="button" class="button button-secondary" onclick="generateBulkCodes()">
                        <?php _e('Generate Bulk Codes', 'content-protect-pro'); ?>
                    </button>
                </div>
            </div>
            
            <!-- Gift Codes Table -->
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <td class="manage-column column-cb check-column">
                            <input type="checkbox" />
                        </td>
                        <th class="manage-column column-code"><?php _e('Code', 'content-protect-pro'); ?></th>
                        <th class="manage-column column-thumbnail"><?php _e('Thumbnail', 'content-protect-pro'); ?></th>
                        <th class="manage-column column-duration"><?php _e('Duration', 'content-protect-pro'); ?></th>
                        <th class="manage-column column-token"><?php _e('Token (Last 8)', 'content-protect-pro'); ?></th>
                        <th class="manage-column column-sessions"><?php _e('Active Sessions', 'content-protect-pro'); ?></th>
                        <th class="manage-column column-status"><?php _e('Status', 'content-protect-pro'); ?></th>
                        <th class="manage-column column-expires"><?php _e('Expires', 'content-protect-pro'); ?></th>
                        <th class="manage-column column-created"><?php _e('Created', 'content-protect-pro'); ?></th>
                        <th class="manage-column column-actions"><?php _e('Actions', 'content-protect-pro'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($gift_codes)): ?>
                        <?php foreach ($gift_codes as $code): ?>
                            <tr>
                                <th scope="row" class="check-column">
                                    <input type="checkbox" name="giftcode[]" value="<?php echo esc_attr($code->id); ?>" />
                                </th>
                                <td class="column-code">
                                    <strong><?php echo esc_html($code->code); ?></strong>
                                    <?php if (!empty($code->description)): ?>
                                        <div class="row-actions visible">
                                            <span class="description"><?php echo esc_html(wp_trim_words($code->description, 10)); ?></span>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="column-thumbnail">
                                    <?php
                                    $thumb_url = '';
                                    if (!empty($code->overlay_image)) {
                                        if (ctype_digit((string) $code->overlay_image) && function_exists('wp_get_attachment_url')) {
                                            $thumb_url = wp_get_attachment_url(intval($code->overlay_image));
                                        } else {
                                            // Defensive: if legacy URL still stored, use it (migration should clear most)
                                            $thumb_url = esc_url($code->overlay_image);
                                        }
                                    }
                                    if (!empty($thumb_url)): ?>
                                        <img src="<?php echo esc_url($thumb_url); ?>" alt="" style="max-width:80px; height:auto; border-radius:4px; border:1px solid #eee;" />
                                    <?php else: ?>
                                        <span class="cpp-no-thumb"></span>
                                    <?php endif; ?>
                                </td>

                                <td class="column-duration">
                                    <?php echo esc_html($code->duration_display ?: ($code->value . ' min')); ?>
                                </td>
                                <td class="column-token">
                                    <code><?php echo esc_html(substr($code->secure_token ?: 'N/A', -8)); ?></code>
                                </td>
                                <td class="column-sessions">
                                    <?php 
                                    // Count active sessions for this code (would need to implement session tracking)
                                    $active_sessions = 0; // Placeholder - implement session counting
                                    echo esc_html($active_sessions); 
                                    ?>
                                </td>
                                <td class="column-status">
                                    <?php
                                    $status_class = '';
                                    switch ($code->status) {
                                        case 'active':
                                            $status_class = 'cpp-status-active';
                                            $status_text = __('Active', 'content-protect-pro');
                                            break;
                                        case 'inactive':
                                            $status_class = 'cpp-status-inactive';
                                            $status_text = __('Inactive', 'content-protect-pro');
                                            break;
                                        case 'used':
                                            $status_class = 'cpp-status-used';
                                            $status_text = __('Used', 'content-protect-pro');
                                            break;
                                        case 'expired':
                                            $status_class = 'cpp-status-expired';
                                            $status_text = __('Expired', 'content-protect-pro');
                                            break;
                                        default:
                                            $status_class = 'cpp-status-unknown';
                                            $status_text = __('Unknown', 'content-protect-pro');
                                    }
                                    ?>
                                    <span class="<?php echo esc_attr($status_class); ?>">
                                        <?php echo esc_html($status_text); ?>
                                    </span>
                                </td>
                                <td class="column-expires">
                                    <?php if ($code->expires_at): ?>
                                        <?php echo esc_html(date('M j, Y', strtotime($code->expires_at))); ?>
                                    <?php else: ?>
                                        <span class="cpp-never"><?php _e('Never', 'content-protect-pro'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="column-created">
                                    <?php echo esc_html(date('M j, Y', strtotime($code->created_at))); ?>
                                </td>
                                <td class="column-actions">
                                    <div class="row-actions">
                                        <span class="edit">
                                            <a href="<?php echo admin_url('admin.php?page=content-protect-pro-giftcodes&action=edit&id=' . $code->id); ?>">
                                                <?php _e('Edit', 'content-protect-pro'); ?>
                                            </a> |
                                        </span>
                                        <span class="delete">
                                            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=content-protect-pro-giftcodes&action=delete&id=' . $code->id), 'delete_giftcode_' . $code->id); ?>" 
                                               onclick="return confirm('<?php _e('Are you sure you want to delete this gift code?', 'content-protect-pro'); ?>')">
                                                <?php _e('Delete', 'content-protect-pro'); ?>
                                            </a>
                                        </span>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="no-items">
                                <?php _e(__('No gift codes found.', 'content-protect-pro'), 'content-protect-pro'); ?>
                                <a href="<?php echo admin_url('admin.php?page=content-protect-pro-giftcodes&action=add'); ?>">
                                    <?php _e('Create your first gift code', 'content-protect-pro'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
    <?php endif; ?>
</div>

<style>
.cpp-giftcodes-list .wp-list-table {
    margin-top: 10px;
}

.cpp-status-active { color: #46b450; font-weight: bold; }
.cpp-status-inactive { color: #82878c; }
.cpp-status-used { color: #00a32a; }
.cpp-status-expired { color: #d63638; }
.cpp-status-unknown { color: #646970; }

.cpp-never { color: #82878c; font-style: italic; }

.column-code { width: 20%; }
.column-value { width: 10%; }
.column-uses { width: 10%; }
.column-status { width: 10%; }
.column-expires { width: 15%; }
.column-created { width: 15%; }
.column-actions { width: 15%; }

.no-items {
    text-align: center;
    padding: 40px;
    color: #646970;
}

.no-items a {
    color: #2271b1;
    text-decoration: none;
}

.no-items a:hover {
    text-decoration: underline;
}
</style>

<script>
function generateCode() {
    // Generate a simple 6-character code from current timestamp
    const timestamp = Date.now().toString();
    const hash = timestamp.substr(-6);
    const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    let code = '';
    
    // Convert hash to readable code
    for (let i = 0; i < 6; i++) {
        const index = parseInt(hash[i] || '0', 10) % chars.length;
        code += chars[index];
    }
    
    document.getElementById('code').value = code;
}

function regenerateToken() {
    // Generate 64-character secure token
    const chars = 'abcdef0123456789';
    let token = '';
    for (let i = 0; i < 64; i++) {
        token += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    document.getElementById('secure_token').value = token;
    
    // Update code based on new token if code field is empty
    if (!document.getElementById('code').value) {
        generateCodeFromToken(token);
    }
}

function generateCodeFromToken(token) {
    // Generate simple code from token hash
    const hash = token.substr(0, 8);
    const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    let code = '';
    
    for (let i = 0; i < 6; i++) {
        const charCode = hash.charCodeAt(i) || 48;
        code += chars[charCode % chars.length];
    }
    
    document.getElementById('code').value = code;
}

// Initialize token on page load for new codes
document.addEventListener('DOMContentLoaded', function() {
    const action = new URLSearchParams(window.location.search).get('action');
    if (action === 'add' && !document.getElementById('secure_token').value) {
        regenerateToken();
    }
});

function generateBulkCodes() {
    // Show bulk generation form
    const modal = document.createElement('div');
    modal.style.cssText = `
        position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
        background: rgba(0,0,0,0.5); z-index: 9999; display: flex; 
        align-items: center; justify-content: center;
    `;
    
    modal.innerHTML = `
        <div style="background: white; padding: 30px; border-radius: 8px; max-width: 500px; width: 90%;">
            <h3>Generate Bulk Gift Codes</h3>
            <form id="bulkForm">
                <table style="width: 100%;">
                    <tr>
                        <td><label>Base Code Prefix:</label></td>
                        <td><input type="text" id="bulk_prefix" value="PROMO" maxlength="10" style="width: 100%;"></td>
                    </tr>
                    <tr>
                        <td><label>Quantity:</label></td>
                        <td><input type="number" id="bulk_quantity" value="10" min="1" max="100" style="width: 100%;"></td>
                    </tr>
                    <tr>
                        <td><label>Duration Value:</label></td>
                        <td><input type="number" id="bulk_duration_value" value="2" min="1" max="999" style="width: 100%;"></td>
                    </tr>
                    <tr>
                        <td><label>Duration Unit:</label></td>
                        <td>
                            <select id="bulk_duration_unit" style="width: 100%;">
                                <option value="minutes">Minutes</option>
                                <option value="hours" selected>Hours</option>
                                <option value="days">Days</option>
                                <option value="months">Months</option>
                                <option value="years">Years</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <td><label>Description:</label></td>
                        <td><input type="text" id="bulk_description" value="Bulk generated codes" style="width: 100%;"></td>
                    </tr>
                </table>
                <div style="margin-top: 20px; text-align: right;">
                    <button type="button" onclick="closeBulkModal()" style="margin-right: 10px;">Cancel</button>
                    <button type="submit" style="background: #0073aa; color: white; border: none; padding: 8px 16px;">Generate</button>
                </div>
            </form>
        </div>
    `;
    
    document.body.appendChild(modal);
    window.bulkModal = modal;
    
    // Handle form submission
    document.getElementById('bulkForm').onsubmit = function(e) {
        e.preventDefault();
        processBulkGeneration();
    };
}

function closeBulkModal() {
    if (window.bulkModal) {
        document.body.removeChild(window.bulkModal);
        window.bulkModal = null;
    }
}

function processBulkGeneration() {
    const prefix = document.getElementById('bulk_prefix').value;
    const quantity = parseInt(document.getElementById('bulk_quantity').value);
    const durationValue = document.getElementById('bulk_duration_value').value;
    const durationUnit = document.getElementById('bulk_duration_unit').value;
    const description = document.getElementById('bulk_description').value;
    
    // Generate codes with unique tokens
    const codes = [];
    for (let i = 1; i <= quantity; i++) {
        const token = generateUniqueToken();
        const code = prefix + '-' + String(i).padStart(2, '0'); // PROMO-01, PROMO-02, etc.
        
        codes.push({
            code: code,
            secure_token: token,
            duration_value: durationValue,
            duration_unit: durationUnit,
            description: description + ' #' + i
        });
    }
    
    // Send AJAX request to create codes
    sendBulkCodes(codes);
}

function generateUniqueToken() {
    const chars = 'abcdef0123456789';
    let token = '';
    for (let i = 0; i < 64; i++) {
        token += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    return token;
}

function sendBulkCodes(codes) {
    const formData = new FormData();
    formData.append('action', 'cpp_bulk_create_codes');
    formData.append('codes', JSON.stringify(codes));
    formData.append('nonce', '<?php echo wp_create_nonce("cpp_bulk_codes"); ?>');
    
    fetch(ajaxurl, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Successfully created ' + data.created + ' codes!');
            location.reload();
        } else {
            alert('Error: ' + (data.message || 'Failed to create codes'));
        }
        closeBulkModal();
    })
    .catch(error => {
        alert('Network error: ' + error.message);
        closeBulkModal();
    });
}

// Media uploader for overlay image (uses wp.media when available)
document.addEventListener('DOMContentLoaded', function() {
    var overlayBtn = document.getElementById('overlay_image_button');
    if (!overlayBtn) return;

    overlayBtn.addEventListener('click', function(e) {
        e.preventDefault();

        // If wp.media is available, use the WordPress media frame
        if (typeof wp !== 'undefined' && typeof wp.media === 'function') {
            var frame = wp.media({
                title: '<?php echo addslashes(__("Select Overlay Image", "content-protect-pro")); ?>',
                button: { text: '<?php echo addslashes(__("Use Image", "content-protect-pro")); ?>' },
                multiple: false
            });

            frame.on('select', function() {
                var attachment = frame.state().get('selection').first().toJSON();
                if (attachment && attachment.url) {
                    var input = document.getElementById('overlay_image');
                    // store the attachment id if available, else URL
                    if (attachment.id) input.value = attachment.id; else input.value = attachment.url;
                    var preview = document.getElementById('overlay_image_preview');
                    preview.innerHTML = '<img src="' + attachment.url + '" alt="" style="max-width:200px; height:auto; border:1px solid #ddd; padding:4px; background:#fff;" />';
                }
            });

            frame.open();
            return;
        }

        // No fallback allowed: require selecting from media library (attachment ID). Show a small alert if media frame not available.
        alert('<?php echo addslashes(__('Please use the media library to select an image. External URLs are no longer supported.', 'content-protect-pro')); ?>');
    });
});
</script>