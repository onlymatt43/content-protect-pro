<?php
/**
 * Provide a admin area view for the plugin settings
 *
 * @package Content_Protect_Pro
 * @since   1.0.0
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Handle form submission
if (isset($_POST['submit'])) {
    check_admin_referer('cpp_settings_nonce');
    
    // Save general settings
    if (isset($_POST['cpp_general_settings'])) {
        update_option('cpp_general_settings', $_POST['cpp_general_settings']);
        echo '<div class="notice notice-success"><p>' . __('General settings saved successfully!', 'content-protect-pro') . '</p></div>';
    }
    
    // Save security settings
    if (isset($_POST['cpp_security_settings'])) {
        update_option('cpp_security_settings', $_POST['cpp_security_settings']);
        echo '<div class="notice notice-success"><p>' . __('Security settings saved successfully!', 'content-protect-pro') . '</p></div>';
    }
    
    // Save integration settings
    if (isset($_POST['cpp_integration_settings'])) {
        update_option('cpp_integration_settings', $_POST['cpp_integration_settings']);
        echo '<div class="notice notice-success"><p>' . __('Integration settings saved successfully!', 'content-protect-pro') . '</p></div>';
    }
}

// Get current settings
$general_settings = get_option('cpp_general_settings', array());
$security_settings = get_option('cpp_security_settings', array());
$integration_settings = get_option('cpp_integration_settings', array());

// Default values
$general_defaults = array(
    'enable_plugin' => 1,
    'debug_mode' => 0,
    'default_protection_level' => 'medium'
);

$security_defaults = array(
    'enable_logging' => 1,
    'enable_rate_limiting' => 1,
    'rate_limit_requests' => 100,
    'rate_limit_window' => 3600,
    'log_retention_days' => 30
);

$integration_defaults = array(
    'presto_enabled' => 1,
    'presto_license_key' => ''
);

$general_settings = wp_parse_args($general_settings, $general_defaults);
$security_settings = wp_parse_args($security_settings, $security_defaults);
$integration_settings = wp_parse_args($integration_settings, $integration_defaults);

// Get active tab
$active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <nav class="nav-tab-wrapper">
        <a href="?page=content-protect-pro-settings&tab=general" class="nav-tab <?php echo $active_tab == 'general' ? 'nav-tab-active' : ''; ?>">
            <?php _e('General', 'content-protect-pro'); ?>
        </a>
        <a href="?page=content-protect-pro-settings&tab=security" class="nav-tab <?php echo $active_tab == 'security' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Security', 'content-protect-pro'); ?>
        </a>
        <a href="?page=content-protect-pro-settings&tab=integrations" class="nav-tab <?php echo $active_tab == 'integrations' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Integrations', 'content-protect-pro'); ?>
        </a>
        <a href="?page=content-protect-pro-settings&tab=advanced" class="nav-tab <?php echo $active_tab == 'advanced' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Advanced', 'content-protect-pro'); ?>
        </a>
    </nav>
    
    <form method="post" action="">
        <?php wp_nonce_field('cpp_settings_nonce'); ?>
        
        <div class="cpp-settings-content">
            <?php if ($active_tab == 'general'): ?>
                <div class="cpp-settings-section">
                    <h2><?php _e('General Settings', 'content-protect-pro'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Enable Plugin', 'content-protect-pro'); ?></th>
                            <td>
                                <fieldset>
                                    <label>
                                        <input type="checkbox" name="cpp_general_settings[enable_plugin]" value="1" <?php checked(1, $general_settings['enable_plugin']); ?> />
                                        <?php _e('Enable Content Protect Pro functionality', 'content-protect-pro'); ?>
                                    </label>
                                    <p class="description"><?php _e('Uncheck to temporarily disable all plugin functionality without deactivating.', 'content-protect-pro'); ?></p>
                                </fieldset>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><?php _e('Debug Mode', 'content-protect-pro'); ?></th>
                            <td>
                                <fieldset>
                                    <label>
                                        <input type="checkbox" name="cpp_general_settings[debug_mode]" value="1" <?php checked(1, $general_settings['debug_mode']); ?> />
                                        <?php _e('Enable debug logging', 'content-protect-pro'); ?>
                                    </label>
                                    <p class="description"><?php _e('Enable detailed logging for troubleshooting. Disable on production sites.', 'content-protect-pro'); ?></p>
                                </fieldset>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><?php _e('Default Protection Level', 'content-protect-pro'); ?></th>
                            <td>
                                <select name="cpp_general_settings[default_protection_level]">
                                    <option value="low" <?php selected('low', $general_settings['default_protection_level']); ?>><?php _e('Low', 'content-protect-pro'); ?></option>
                                    <option value="medium" <?php selected('medium', $general_settings['default_protection_level']); ?>><?php _e('Medium', 'content-protect-pro'); ?></option>
                                    <option value="high" <?php selected('high', $general_settings['default_protection_level']); ?>><?php _e('High', 'content-protect-pro'); ?></option>
                                </select>
                                <p class="description"><?php _e('Default protection level for new content.', 'content-protect-pro'); ?></p>
                            </td>
                        </tr>
                    </table>
                    
                    <h3><?php _e('Gift Code Settings', 'content-protect-pro'); ?></h3>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Default Code Length', 'content-protect-pro'); ?></th>
                            <td>
                                <input type="number" name="cpp_general_settings[code_length]" value="<?php echo isset($general_settings['code_length']) ? intval($general_settings['code_length']) : 8; ?>" min="4" max="32" />
                                <p class="description"><?php _e('Default length for auto-generated gift codes (4-32 characters).', 'content-protect-pro'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><?php _e('Code Prefix', 'content-protect-pro'); ?></th>
                            <td>
                                <input type="text" name="cpp_general_settings[code_prefix]" value="<?php echo isset($general_settings['code_prefix']) ? esc_attr($general_settings['code_prefix']) : ''; ?>" maxlength="10" />
                                <p class="description"><?php _e('Optional prefix for all gift codes (e.g., "VIP-").', 'content-protect-pro'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><?php _e('Code Suffix', 'content-protect-pro'); ?></th>
                            <td>
                                <input type="text" name="cpp_general_settings[code_suffix]" value="<?php echo isset($general_settings['code_suffix']) ? esc_attr($general_settings['code_suffix']) : ''; ?>" maxlength="10" />
                                <p class="description"><?php _e('Optional suffix for all gift codes (e.g., "-2024").', 'content-protect-pro'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>
                
            <?php elseif ($active_tab == 'security'): ?>
                <div class="cpp-settings-section">
                    <h2><?php _e('Security Settings', 'content-protect-pro'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Enable Logging', 'content-protect-pro'); ?></th>
                            <td>
                                <fieldset>
                                    <label>
                                        <input type="checkbox" name="cpp_security_settings[enable_logging]" value="1" <?php checked(1, $security_settings['enable_logging']); ?> />
                                        <?php _e('Log security events and access attempts', 'content-protect-pro'); ?>
                                    </label>
                                    <p class="description"><?php _e('Track gift code validations, failed attempts, and security events.', 'content-protect-pro'); ?></p>
                                </fieldset>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><?php _e('Enable Rate Limiting', 'content-protect-pro'); ?></th>
                            <td>
                                <fieldset>
                                    <label>
                                        <input type="checkbox" name="cpp_security_settings[enable_rate_limiting]" value="1" <?php checked(1, $security_settings['enable_rate_limiting']); ?> />
                                        <?php _e('Limit validation attempts per IP address', 'content-protect-pro'); ?>
                                    </label>
                                    <p class="description"><?php _e('Prevent brute force attacks by limiting validation attempts.', 'content-protect-pro'); ?></p>
                                </fieldset>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><?php _e('Rate Limit Requests', 'content-protect-pro'); ?></th>
                            <td>
                                <input type="number" name="cpp_security_settings[rate_limit_requests]" value="<?php echo intval($security_settings['rate_limit_requests']); ?>" min="1" max="1000" />
                                <p class="description"><?php _e('Maximum validation attempts per time window.', 'content-protect-pro'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><?php _e('Time Window (seconds)', 'content-protect-pro'); ?></th>
                            <td>
                                <input type="number" name="cpp_security_settings[rate_limit_window]" value="<?php echo intval($security_settings['rate_limit_window']); ?>" min="60" max="86400" />
                                <p class="description"><?php _e('Time window for rate limiting (60-86400 seconds).', 'content-protect-pro'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><?php _e('Log Retention', 'content-protect-pro'); ?></th>
                            <td>
                                <input type="number" name="cpp_security_settings[log_retention_days]" value="<?php echo intval($security_settings['log_retention_days']); ?>" min="1" max="365" />
                                <p class="description"><?php _e('Days to keep log entries before automatic cleanup.', 'content-protect-pro'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>
                
            <?php elseif ($active_tab == 'integrations'): ?>
                <div class="cpp-settings-section">
                    <h2><?php _e('Presto Player Integration', 'content-protect-pro'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Enable Presto Player', 'content-protect-pro'); ?></th>
                            <td>
                                <fieldset>
                                    <label>
                                        <input type="checkbox" name="cpp_integration_settings[presto_enabled]" value="1" <?php checked(1, $integration_settings['presto_enabled']); ?> />
                                        <?php _e('Enable Presto Player integration', 'content-protect-pro'); ?>
                                    </label>
                                    <p class="description"><?php _e('Integrate with Presto Player for video protection and playback.', 'content-protect-pro'); ?></p>
                                </fieldset>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><?php _e('License Key', 'content-protect-pro'); ?></th>
                            <td>
                                <input type="password" name="cpp_integration_settings[presto_license_key]" value="<?php echo esc_attr($integration_settings['presto_license_key']); ?>" class="regular-text" />
                                <p class="description"><?php _e('Your Presto Player Pro license key (if applicable).', 'content-protect-pro'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><?php _e('Integration Status', 'content-protect-pro'); ?></th>
                            <td>
                                <?php if (is_plugin_active('presto-player/presto-player.php')): ?>
                                    <span class="cpp-status-ok">✓ <?php _e('Presto Player is active', 'content-protect-pro'); ?></span>
                                <?php else: ?>
                                    <span class="cpp-status-warning">⚠ <?php _e('Presto Player plugin not found or inactive', 'content-protect-pro'); ?></span>
                                    <p class="description">
                                        <?php _e('Please install and activate Presto Player to use this integration.', 'content-protect-pro'); ?>
                                        <a href="<?php echo admin_url('plugin-install.php?s=presto+player&tab=search&type=term'); ?>" target="_blank">
                                            <?php _e('Install Presto Player', 'content-protect-pro'); ?>
                                        </a>
                                    </p>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                    
                    <h3><?php _e('Video Protection Settings', 'content-protect-pro'); ?></h3>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('How it works', 'content-protect-pro'); ?></th>
                            <td>
                                <p class="description">
                                    <?php _e('This plugin integrates with Presto Player to protect videos with gift codes. Simply:', 'content-protect-pro'); ?>
                                </p>
                                <ol>
                                    <li><?php _e('Create videos in Presto Player with password protection', 'content-protect-pro'); ?></li>
                                    <li><?php _e('Add gift codes in Content Protect Pro', 'content-protect-pro'); ?></li>
                                    <li><?php _e('Use the shortcode <code>[cpp_protected_video id="VIDEO_ID" code="GIFT_CODE"]</code> on your pages', 'content-protect-pro'); ?></li>
                                </ol>
                                <p class="description">
                                    <?php _e('The plugin will automatically validate the gift code and display the Presto Player video if valid.', 'content-protect-pro'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>

            <?php elseif ($active_tab == 'advanced'): ?>
                <div class="cpp-settings-section">
                    <h2><?php _e('Advanced Settings', 'content-protect-pro'); ?></h2>
                    
                    <div class="cpp-advanced-section">
                        <h3><?php _e('System Diagnostics', 'content-protect-pro'); ?></h3>
                        <p><?php _e('Run system diagnostics to check plugin configuration and performance.', 'content-protect-pro'); ?></p>
                        <button type="button" class="button button-secondary" onclick="runDiagnostics()">
                            <?php _e('Run Diagnostics', 'content-protect-pro'); ?>
                        </button>
                        <div id="cpp-diagnostics-results" style="margin-top: 15px;"></div>
                    </div>
                    
                    <div class="cpp-advanced-section">
                        <h3><?php _e('Database Management', 'content-protect-pro'); ?></h3>
                        <p><?php _e('Manage plugin database tables and data.', 'content-protect-pro'); ?></p>
                        
                        <button type="button" class="button button-secondary" onclick="recreateTables()">
                            <?php _e('Recreate Database Tables', 'content-protect-pro'); ?>
                        </button>
                        <p class="description"><?php _e('Recreate plugin database tables. Use if tables are missing or corrupted.', 'content-protect-pro'); ?></p>
                        
                        <button type="button" class="button button-secondary cpp-warning" onclick="cleanupLogs()">
                            <?php _e('Cleanup Old Logs', 'content-protect-pro'); ?>
                        </button>
                        <p class="description"><?php _e('Remove old analytics and security logs based on retention settings.', 'content-protect-pro'); ?></p>
                        
                        <button type="button" class="button button-secondary cpp-danger" onclick="resetPlugin()">
                            <?php _e('Reset Plugin Data', 'content-protect-pro'); ?>
                        </button>
                        <p class="description"><?php _e('⚠️ WARNING: This will delete ALL plugin data including gift codes, videos, and analytics. This cannot be undone!', 'content-protect-pro'); ?></p>
                    </div>
                    
                    <div class="cpp-advanced-section">
                        <h3><?php _e('Import/Export', 'content-protect-pro'); ?></h3>
                        <p><?php _e('Export plugin settings and data or import from backup.', 'content-protect-pro'); ?></p>
                        
                        <button type="button" class="button button-secondary" onclick="exportSettings()">
                            <?php _e('Export Settings', 'content-protect-pro'); ?>
                        </button>
                        
                        <button type="button" class="button button-secondary" onclick="exportData()">
                            <?php _e('Export All Data', 'content-protect-pro'); ?>
                        </button>
                        
                        <div style="margin-top: 15px;">
                            <label for="cpp-import-file"><?php _e('Import Settings:', 'content-protect-pro'); ?></label>
                            <input type="file" id="cpp-import-file" accept=".json" />
                            <button type="button" class="button button-secondary" onclick="importSettings()">
                                <?php _e('Import', 'content-protect-pro'); ?>
                            </button>
                        </div>
                    </div>
                </div>
                
            <?php endif; ?>
        </div>
        
        <p class="submit">
            <input type="submit" name="submit" id="submit" class="button button-primary" value="<?php _e('Save Settings', 'content-protect-pro'); ?>" />
        </p>
    </form>
</div>

<style>
.cpp-settings-content {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    margin: 20px 0;
    padding: 20px;
}

.cpp-settings-section h3 {
    border-top: 1px solid #eee;
    padding-top: 20px;
    margin-top: 30px;
}

.cpp-settings-section h3:first-child {
    border-top: none;
    padding-top: 0;
    margin-top: 0;
}

.cpp-advanced-section {
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 15px;
    margin: 15px 0;
}

.cpp-status-ok { color: #46b450; }
.cpp-status-warning { color: #ffb900; }
.cpp-status-error { color: #dc3232; }

.cpp-warning { border-color: #ffb900 !important; }
.cpp-danger { border-color: #dc3232 !important; color: #dc3232 !important; }

#cpp-diagnostics-results {
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 15px;
    display: none;
}

.cpp-diagnostic-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 5px 0;
    border-bottom: 1px solid #eee;
}

.cpp-diagnostic-item:last-child {
    border-bottom: none;
}
</style>

<script>
function runDiagnostics() {
    const resultsDiv = document.getElementById('cpp-diagnostics-results');
    resultsDiv.style.display = 'block';
    resultsDiv.innerHTML = '<p><?php _e('Running diagnostics...', 'content-protect-pro'); ?></p>';
    
    // Make AJAX request to run diagnostics
    fetch(ajaxurl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=cpp_run_diagnostics&nonce=<?php echo wp_create_nonce('cpp_diagnostics'); ?>'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayDiagnosticResults(data.data);
        } else {
            resultsDiv.innerHTML = '<p style="color: #dc3232;"><?php _e('Error running diagnostics:', 'content-protect-pro'); ?> ' + data.data + '</p>';
        }
    })
    .catch(error => {
        resultsDiv.innerHTML = '<p style="color: #dc3232;"><?php _e('Error running diagnostics. Please try again.', 'content-protect-pro'); ?></p>';
    });
}

function displayDiagnosticResults(results) {
    let html = '<h4><?php _e('Diagnostic Results', 'content-protect-pro'); ?></h4>';
    
    Object.keys(results).forEach(category => {
        html += '<h5>' + category.charAt(0).toUpperCase() + category.slice(1) + '</h5>';
        
        Object.keys(results[category]).forEach(check => {
            const item = results[category][check];
            const statusIcon = item.status === 'pass' ? '✓' : (item.status === 'warning' ? '⚠' : '✗');
            const statusClass = 'cpp-status-' + (item.status === 'pass' ? 'ok' : (item.status === 'warning' ? 'warning' : 'error'));
            
            html += '<div class="cpp-diagnostic-item">';
            html += '<span>' + item.label + ': ' + item.value + '</span>';
            html += '<span class="' + statusClass + '">' + statusIcon + '</span>';
            html += '</div>';
        });
    });
    
    document.getElementById('cpp-diagnostics-results').innerHTML = html;
}

function recreateTables() {
    if (!confirm('<?php _e('Are you sure you want to recreate database tables? This may cause data loss.', 'content-protect-pro'); ?>')) {
        return;
    }
    
    // Implementation would go here
    alert('<?php _e('Feature coming soon', 'content-protect-pro'); ?>');
}

function cleanupLogs() {
    if (!confirm('<?php _e('Are you sure you want to cleanup old logs?', 'content-protect-pro'); ?>')) {
        return;
    }
    
    // Implementation would go here
    alert('<?php _e('Feature coming soon', 'content-protect-pro'); ?>');
}

function resetPlugin() {
    if (!confirm('<?php _e('⚠️ WARNING: This will delete ALL plugin data! Are you absolutely sure?', 'content-protect-pro'); ?>')) {
        return;
    }
    
    if (!confirm('<?php _e('This cannot be undone. Type "RESET" to confirm:', 'content-protect-pro'); ?>')) {
        return;
    }
    
    // Implementation would go here
    alert('<?php _e('Feature coming soon', 'content-protect-pro'); ?>');
}

function exportSettings() {
    // Implementation would go here
    alert('<?php _e('Feature coming soon', 'content-protect-pro'); ?>');
}

function exportData() {
    // Implementation would go here
    alert('<?php _e('Feature coming soon', 'content-protect-pro'); ?>');
}

function importSettings() {
    // Implementation would go here
    alert('<?php _e('Feature coming soon', 'content-protect-pro'); ?>');
}
</script>