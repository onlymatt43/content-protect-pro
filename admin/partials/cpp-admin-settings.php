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
        // Basic sanitization for general settings
        $in = $_POST['cpp_general_settings'];
        $san = array();
        $san['enable_plugin'] = !empty($in['enable_plugin']) ? 1 : 0;
        $san['debug_mode'] = !empty($in['debug_mode']) ? 1 : 0;
        $san['default_protection_level'] = in_array($in['default_protection_level'] ?? '', array('low','medium','high')) ? $in['default_protection_level'] : 'medium';
        $san['code_length'] = isset($in['code_length']) ? intval($in['code_length']) : 8;
        $san['code_prefix'] = isset($in['code_prefix']) ? sanitize_text_field($in['code_prefix']) : '';
        $san['code_suffix'] = isset($in['code_suffix']) ? sanitize_text_field($in['code_suffix']) : '';
        update_option('cpp_general_settings', $san);
        echo '<div class="notice notice-success"><p>' . __('General settings saved successfully!', 'content-protect-pro') . '</p></div>';
    }
    
    // Save security settings
    if (isset($_POST['cpp_security_settings'])) {
        // Sanitized security settings
        $in = $_POST['cpp_security_settings'];
        $san = array();
        $san['enable_logging'] = !empty($in['enable_logging']) ? 1 : 0;
        $san['enable_rate_limiting'] = !empty($in['enable_rate_limiting']) ? 1 : 0;
        $san['rate_limit_requests'] = isset($in['rate_limit_requests']) ? intval($in['rate_limit_requests']) : 100;
        $san['rate_limit_window'] = isset($in['rate_limit_window']) ? intval($in['rate_limit_window']) : 3600;
        $san['log_retention_days'] = isset($in['log_retention_days']) ? intval($in['log_retention_days']) : 30;
        // IP binding may live under security settings as well
        $san['ip_binding'] = !empty($in['ip_binding']) ? 1 : 0;
        update_option('cpp_security_settings', $san);
        echo '<div class="notice notice-success"><p>' . __('Security settings saved successfully!', 'content-protect-pro') . '</p></div>';
    }
    
    // Save integration settings
    if (isset($_POST['cpp_integration_settings'])) {
        // Sanitized integration settings
        $in = $_POST['cpp_integration_settings'];
        $san = array();
        $san['presto_enabled'] = !empty($in['presto_enabled']) ? 1 : 0;
        $san['presto_license_key'] = isset($in['presto_license_key']) ? sanitize_text_field($in['presto_license_key']) : '';
        $san['bunny_enabled'] = !empty($in['bunny_enabled']) ? 1 : 0;
        $san['bunny_api_key'] = isset($in['bunny_api_key']) ? sanitize_text_field($in['bunny_api_key']) : '';
        $san['bunny_library_id'] = isset($in['bunny_library_id']) ? sanitize_text_field($in['bunny_library_id']) : '';
        $san['bunny_token_auth_key'] = isset($in['bunny_token_auth_key']) ? sanitize_text_field($in['bunny_token_auth_key']) : '';
        $san['signed_urls'] = !empty($in['signed_urls']) ? 1 : 0;
        $san['token_expiry'] = isset($in['token_expiry']) ? max(60, min(86400, intval($in['token_expiry']))) : 900;

        // Overlay defaults: only accept attachment ID (robust). Legacy URLs should be migrated.
        $overlay = isset($in['overlay_image']) ? trim($in['overlay_image']) : '';
        if (!empty($overlay) && ctype_digit($overlay)) {
            $san['overlay_image'] = intval($overlay);
        } else {
            $san['overlay_image'] = '';
        }

        $purchase = isset($in['purchase_url']) ? esc_url_raw($in['purchase_url']) : '';
        $san['purchase_url'] = $purchase;

        update_option('cpp_integration_settings', $san);
        echo '<div class="notice notice-success"><p>' . __('Integration settings saved successfully!', 'content-protect-pro') . '</p></div>';
    }

    // Save AI Gateway settings
    if (isset($_POST['cpp_ai_gateway_settings'])) {
        check_admin_referer('cpp_settings_nonce');
        $in = $_POST['cpp_ai_gateway_settings'];
        $san = array();
        $san['gateway_url'] = isset($in['gateway_url']) ? esc_url_raw($in['gateway_url']) : '';
        $san['api_key'] = isset($in['api_key']) ? sanitize_text_field($in['api_key']) : '';
        update_option('cpp_ai_gateway_settings', $san);
        echo '<div class="notice notice-success"><p>' . __('AI Gateway settings saved successfully!', 'content-protect-pro') . '</p></div>';
    }
}

// Get current settings
$general_settings = get_option('cpp_general_settings', array());
$security_settings = get_option('cpp_security_settings', array());
$integration_settings = get_option('cpp_integration_settings', array());
$ai_gateway_settings = get_option('cpp_ai_gateway_settings', array());

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

$integration_defaults = wp_parse_args($integration_defaults, array(
    'signed_urls' => 0,
    'token_expiry' => 900, // seconds
));

$ai_gateway_defaults = array(
    'gateway_url' => '',
    'api_key' => ''
);

$general_settings = wp_parse_args($general_settings, $general_defaults);
$security_settings = wp_parse_args($security_settings, $security_defaults);
$integration_settings = wp_parse_args($integration_settings, $integration_defaults);
$ai_gateway_settings = wp_parse_args($ai_gateway_settings, $ai_gateway_defaults);

// Get active tab
$active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
        <nav class="nav-tab-wrapper">
        <a href="?page=content-protect-pro-settings&tab=general" class="nav-tab <?php echo $active_tab == 'general' ? 'nav-tab-active' : ''; ?>"><?php _e('General', 'content-protect-pro'); ?></a>
        <a href="?page=content-protect-pro-settings&tab=security" class="nav-tab <?php echo $active_tab == 'security' ? 'nav-tab-active' : ''; ?>"><?php _e('Security', 'content-protect-pro'); ?></a>
        <a href="?page=content-protect-pro-settings&tab=integrations" class="nav-tab <?php echo $active_tab == 'integrations' ? 'nav-tab-active' : ''; ?>"><?php _e('Integrations', 'content-protect-pro'); ?></a>
        <a href="?page=content-protect-pro-settings&tab=ai_gateway" class="nav-tab <?php echo $active_tab == 'ai_gateway' ? 'nav-tab-active' : ''; ?>"><?php _e('AI Gateway', 'content-protect-pro'); ?></a>
        <a href="?page=content-protect-pro-settings&tab=advanced" class="nav-tab <?php echo $active_tab == 'advanced' ? 'nav-tab-active' : ''; ?>"><?php _e('Advanced', 'content-protect-pro'); ?></a>
    </nav>">
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
        
        <?php if ($active_tab == 'general'): ?>
            
            <div class="cpp-settings-content">
                <div class="cpp-settings-section">
                    <h3><?php _e('General Plugin Settings', 'content-protect-pro'); ?></h3>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Enable Plugin', 'content-protect-pro'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="cpp_general_settings[enable_plugin]" value="1" <?php checked($general_settings['enable_plugin'], 1); ?>>
                                    <?php _e('Enable all plugin functionality', 'content-protect-pro'); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Debug Mode', 'content-protect-pro'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="cpp_general_settings[debug_mode]" value="1" <?php checked($general_settings['debug_mode'], 1); ?>>
                                    <?php _e('Enable detailed logging for troubleshooting', 'content-protect-pro'); ?>
                                </label>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

        <?php elseif ($active_tab == 'security'): ?>

            <div class="cpp-settings-content">
                <div class="cpp-settings-section">
                    <h3><?php _e('Logging & Rate Limiting', 'content-protect-pro'); ?></h3>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Enable Logging', 'content-protect-pro'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="cpp_security_settings[enable_logging]" value="1" <?php checked($security_settings['enable_logging'], 1); ?>>
                                    <?php _e('Log security events (e.g., failed attempts, token usage)', 'content-protect-pro'); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Log Retention', 'content-protect-pro'); ?></th>
                            <td>
                                <input type="number" name="cpp_security_settings[log_retention_days]" value="<?php echo esc_attr($security_settings['log_retention_days']); ?>" min="1" max="365">
                                <p class="description"><?php _e('Number of days to keep logs before deleting.', 'content-protect-pro'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Enable Rate Limiting', 'content-protect-pro'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="cpp_security_settings[enable_rate_limiting]" value="1" <?php checked($security_settings['enable_rate_limiting'], 1); ?>>
                                    <?php _e('Protect against brute-force attacks by limiting request rates.', 'content-protect-pro'); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Rate Limit Threshold', 'content-protect-pro'); ?></th>
                            <td>
                                <input type="number" name="cpp_security_settings[rate_limit_requests]" value="<?php echo esc_attr($security_settings['rate_limit_requests']); ?>" min="1">
                                <span><?php _e('requests per', 'content-protect-pro'); ?></span>
                                <input type="number" name="cpp_security_settings[rate_limit_window]" value="<?php echo esc_attr($security_settings['rate_limit_window']); ?>" min="60">
                                <span><?php _e('seconds.', 'content-protect-pro'); ?></span>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

        <?php elseif ($active_tab == 'integrations'): ?>

            <div class="cpp-settings-content">
                <div class="cpp-settings-section">
                    <h3><?php _e('Presto Player', 'content-protect-pro'); ?></h3>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Enable Presto Player Integration', 'content-protect-pro'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="cpp_integration_settings[presto_enabled]" value="1" <?php checked($integration_settings['presto_enabled'], 1); ?>>
                                    <?php _e('Automatically protect Presto Player videos.', 'content-protect-pro'); ?>
                                </label>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="cpp-settings-section">
                    <h3><?php _e('Bunny.net', 'content-protect-pro'); ?></h3>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Enable Bunny.net Integration', 'content-protect-pro'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="cpp_integration_settings[bunny_enabled]" value="1" <?php checked($integration_settings['bunny_enabled'], 1); ?>>
                                    <?php _e('Enable secure token authentication for Bunny.net videos.', 'content-protect-pro'); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Bunny.net API Key', 'content-protect-pro'); ?></th>
                            <td>
                                <input type="password" name="cpp_integration_settings[bunny_api_key]" value="<?php echo esc_attr($integration_settings['bunny_api_key']); ?>" class="regular-text">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Video Library ID', 'content-protect-pro'); ?></th>
                            <td>
                                <input type="text" name="cpp_integration_settings[bunny_library_id]" value="<?php echo esc_attr($integration_settings['bunny_library_id']); ?>" class="regular-text">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Token Authentication Key', 'content-protect-pro'); ?></th>
                            <td>
                                <input type="password" name="cpp_integration_settings[bunny_token_auth_key]" value="<?php echo esc_attr($integration_settings['bunny_token_auth_key']); ?>" class="regular-text">
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

        <?php elseif ($active_tab == 'ai_gateway'): ?>

            <div class="cpp-settings-content">
                <div class="cpp-settings-section">
                    <h3><?php _e('AI Gateway Configuration', 'content-protect-pro'); ?></h3>
                    <p><?php _e('Configure the connection to your OnlyMatt AI Gateway for avatar and admin assistant features.', 'content-protect-pro'); ?></p>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="ai_gateway_url"><?php _e('Gateway URL', 'content-protect-pro'); ?></label>
                            </th>
                            <td>
                                <input type="url" id="ai_gateway_url" name="cpp_ai_gateway_settings[gateway_url]" value="<?php echo esc_attr($ai_gateway_settings['gateway_url']); ?>" class="regular-text" placeholder="https://api.onlymatt.ca">
                                <p class="description"><?php _e('The full URL to your AI Gateway endpoint (e.g., https://api.onlymatt.ca).', 'content-protect-pro'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="ai_api_key"><?php _e('API Key', 'content-protect-pro'); ?></label>
                            </th>
                            <td>
                                <input type="password" id="ai_api_key" name="cpp_ai_gateway_settings[api_key]" value="<?php echo esc_attr($ai_gateway_settings['api_key']); ?>" class="regular-text">
                                <p class="description"><?php _e('Your secret API key (X-OM-KEY) to authenticate with the gateway.', 'content-protect-pro'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>
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

// Bunny test button handler
document.addEventListener('DOMContentLoaded', function(){
    var btn = document.getElementById('cpp-test-bunny');
    if (!btn) return;
    btn.addEventListener('click', function(){
        var resultEl = document.getElementById('cpp-test-bunny-result');
        if (resultEl) resultEl.textContent = '<?php echo esc_js(__('Testing...', 'content-protect-pro')); ?>';

        var data = new FormData();
        data.append('action', 'cpp_test_bunny_connection');
        data.append('nonce', '<?php echo wp_create_nonce('cpp_test_bunny'); ?>');

        fetch(ajaxurl, {
            method: 'POST',
            body: data
        }).then(function(resp){
            return resp.json();
        }).then(function(json){
            if (json.success) {
                if (resultEl) resultEl.textContent = 'OK: ' + (json.data && json.data.message ? json.data.message : '<?php echo esc_js(__('Connection successful', 'content-protect-pro')); ?>');
            } else {
                if (resultEl) resultEl.textContent = 'Error: ' + (json.data && json.data.message ? json.data.message : JSON.stringify(json.data));
            }
        }).catch(function(err){
            if (resultEl) resultEl.textContent = 'Error: ' + err;
        });
    });
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function(){
    var btn = document.getElementById('cpp_default_overlay_button');
    if (!btn) return;

    btn.addEventListener('click', function(e){
        e.preventDefault();
        if (typeof wp !== 'undefined' && typeof wp.media === 'function') {
            var frame = wp.media({
                title: '<?php echo addslashes(__('Select Default Overlay Image', 'content-protect-pro')); ?>',
                button: { text: '<?php echo addslashes(__('Use Image', 'content-protect-pro')); ?>' },
                multiple: false
            });

            frame.on('select', function(){
                var attachment = frame.state().get('selection').first().toJSON();
                if (attachment) {
                    // Store attachment ID for robustness; fallback to URL if no ID
                    var input = document.getElementById('cpp_default_overlay_image');
                    if (attachment.id) input.value = attachment.id;
                    else if (attachment.url) input.value = attachment.url;
                    var preview = document.getElementById('cpp_default_overlay_preview');
                    var url = attachment.url || '';
                    preview.innerHTML = '<img src="' + url + '" style="max-width:200px; height:auto; border:1px solid #ddd; padding:4px; background:#fff;" />';
                }
            });

            frame.open();
            return;
        }

        // No fallback: require selecting from media library. Alert the user if wp.media isn't available.
        alert('<?php echo addslashes(__('Please use the media library to select an image. External URLs are no longer supported.', 'content-protect-pro')); ?>');
    });
});
</script>