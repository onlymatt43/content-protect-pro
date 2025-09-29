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
$action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : '';
$giftcode_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($giftcode_manager && isset($_POST['submit'])) {
    if ($action === 'add' || $action === 'edit') {
        // Handle form submission
        check_admin_referer('cpp_giftcode_nonce');
        
        $code_data = array(
            'code' => sanitize_text_field($_POST['code']),
            'value' => floatval($_POST['value']),
            'max_uses' => intval($_POST['max_uses']),
            'expires_at' => sanitize_text_field($_POST['expires_at']),
            'status' => sanitize_text_field($_POST['status']),
            'description' => sanitize_textarea_field($_POST['description'])
        );
        
        if ($action === 'add') {
            $result = $giftcode_manager->create_code($code_data);
            if ($result) {
                echo '<div class="notice notice-success"><p>' . __('Gift code created successfully!', 'content-protect-pro') . '</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>' . __('Error creating gift code.', 'content-protect-pro') . '</p></div>';
            }
        } elseif ($action === 'edit' && $giftcode_id) {
            $result = $giftcode_manager->update_code($giftcode_id, $code_data);
            if ($result) {
                echo '<div class="notice notice-success"><p>' . __('Gift code updated successfully!', 'content-protect-pro') . '</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>' . __('Error updating gift code.', 'content-protect-pro') . '</p></div>';
            }
        }
    }
} elseif ($action === 'delete' && $giftcode_id && $giftcode_manager) {
    // Handle delete action
    if (wp_verify_nonce($_GET['_wpnonce'], 'delete_giftcode_' . $giftcode_id)) {
        $result = $giftcode_manager->delete_code($giftcode_id);
        if ($result) {
            echo '<div class="notice notice-success"><p>' . __('Gift code deleted successfully!', 'content-protect-pro') . '</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>' . __('Error deleting gift code.', 'content-protect-pro') . '</p></div>';
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
                        <p class="description"><?php _e('Unique alphanumeric code for access validation.', 'content-protect-pro'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="value"><?php _e('Value', 'content-protect-pro'); ?> *</label>
                    </th>
                    <td>
                        <input type="number" id="value" name="value" value="<?php echo $edit_code ? esc_attr($edit_code->value) : '0'; ?>" min="0" step="0.01" class="small-text" required />
                        <p class="description"><?php _e('Monetary or point value of this gift code.', 'content-protect-pro'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="max_uses"><?php _e('Maximum Uses', 'content-protect-pro'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="max_uses" name="max_uses" value="<?php echo $edit_code ? esc_attr($edit_code->max_uses) : '1'; ?>" min="0" class="small-text" />
                        <p class="description"><?php _e('Maximum number of times this code can be used. 0 = unlimited.', 'content-protect-pro'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="expires_at"><?php _e('Expiration Date', 'content-protect-pro'); ?></label>
                    </th>
                    <td>
                        <input type="datetime-local" id="expires_at" name="expires_at" value="<?php echo $edit_code && $edit_code->expires_at ? date('Y-m-d\TH:i', strtotime($edit_code->expires_at)) : ''; ?>" />
                        <p class="description"><?php _e('When this code expires. Leave empty for no expiration.', 'content-protect-pro'); ?></p>
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
                        <p class="description"><?php _e('Current status of this gift code.', 'content-protect-pro'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="description"><?php _e('Description', 'content-protect-pro'); ?></label>
                    </th>
                    <td>
                        <textarea id="description" name="description" rows="3" class="large-text"><?php echo $edit_code ? esc_textarea($edit_code->description) : ''; ?></textarea>
                        <p class="description"><?php _e('Optional description or notes about this gift code.', 'content-protect-pro'); ?></p>
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
                        <th class="manage-column column-value"><?php _e('Value', 'content-protect-pro'); ?></th>
                        <th class="manage-column column-uses"><?php _e('Uses', 'content-protect-pro'); ?></th>
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
                                <td class="column-value">
                                    <?php echo esc_html($code->value); ?>
                                </td>
                                <td class="column-uses">
                                    <?php echo esc_html($code->used_count); ?>
                                    <?php if ($code->max_uses > 0): ?>
                                        / <?php echo esc_html($code->max_uses); ?>
                                    <?php else: ?>
                                        / <?php _e('∞', 'content-protect-pro'); ?>
                                    <?php endif; ?>
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
                                <?php _e('No gift codes found.', 'content-protect-pro'); ?>
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
    const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    let code = '';
    for (let i = 0; i < 8; i++) {
        code += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    document.getElementById('code').value = code;
}

function generateBulkCodes() {
    const quantity = prompt('<?php _e('How many codes would you like to generate?', 'content-protect-pro'); ?>', '10');
    if (!quantity || isNaN(quantity) || quantity < 1 || quantity > 1000) {
        alert('<?php _e('Please enter a valid quantity (1-1000).', 'content-protect-pro'); ?>');
        return;
    }
    
    const value = prompt('<?php _e('What value should each code have?', 'content-protect-pro'); ?>', '10');
    if (!value || isNaN(value) || value < 0) {
        alert('<?php _e('Please enter a valid value.', 'content-protect-pro'); ?>');
        return;
    }
    
    // This would typically make an AJAX request to generate bulk codes
    alert('<?php _e('Bulk code generation feature coming soon!', 'content-protect-pro'); ?>');
}
</script>