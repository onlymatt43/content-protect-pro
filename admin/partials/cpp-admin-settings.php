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
        $in = sanitize_text_field($_POST['cpp_general_settings'] ?? '');
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

$integration_defaults = wp_parse_args($integration_defaults, array(
    'signed_urls' => 0,
    'token_expiry' => 900, // seconds
));

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
                                    <p class="description"><?php _e(__('Uncheck to temporarily disable all plugin functionality without deactivating.', 'content-protect-pro'), 'content-protect-pro'); ?></p>
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
                                <p class="description"><?php _e(__('Default protection level for new content.', 'content-protect-pro'), 'content-protect-pro'); ?></p>
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
                                    <p class="description"><?php _e(__('Prevent brute force attacks by limiting validation attempts.', 'content-protect-pro'), 'content-protect-pro'); ?></p>
                                </fieldset>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><?php _e('Rate Limit Requests', 'content-protect-pro'); ?></th>
                            <td>
                                <input type="number" name="cpp_security_settings[rate_limit_requests]" value="<?php echo intval($security_settings['rate_limit_requests']); ?>" min="1" max="1000" />
                                <p class="description"><?php _e(__('Maximum validation attempts per time window.', 'content-protect-pro'), 'content-protect-pro'); ?></p>
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
                                <p class="description"><?php _e(__('Days to keep log entries before automatic cleanup.', 'content-protect-pro'), 'content-protect-pro'); ?></p>
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
                                    <p class="description"><?php _e(__('Integrate with Presto Player for video protection and playback.', 'content-protect-pro'), 'content-protect-pro'); ?></p>
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
                                        <?php _e(__('Please install and activate Presto Player to use this integration.', 'content-protect-pro'), 'content-protect-pro'); ?>
                                        <a href="<?php echo admin_url('plugin-install.php?s=presto+player&tab=search&type=term'); ?>" target="_blank">
                                            <?php _e('Install Presto Player', 'content-protect-pro'); ?>
                                        </a>
                                    </p>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Bunny CDN', 'content-protect-pro'); ?></th>
                            <td>
                                <fieldset>
                                    <label>
                                        <input type="checkbox" name="cpp_integration_settings[bunny_enabled]" value="1" <?php checked(1, isset($integration_settings['bunny_enabled']) ? $integration_settings['bunny_enabled'] : 0); ?> />
                                        <?php _e('Enable Bunny CDN integration', 'content-protect-pro'); ?>
                                    </label>
                                    <p class="description"><?php _e(__('Enable Bunny Stream integration for signed playback URLs and CDN storage.', 'content-protect-pro'), 'content-protect-pro'); ?></p>
                                </fieldset>
                                <p>
                                    <label><?php _e('Bunny API Key', 'content-protect-pro'); ?></label><br />
                                    <input type="password" name="cpp_integration_settings[bunny_api_key]" value="<?php echo esc_attr(isset($integration_settings['bunny_api_key']) ? $integration_settings['bunny_api_key'] : ''); ?>" class="regular-text" />
                                </p>
                                <p>
                                    <label><?php _e('Bunny Library ID', 'content-protect-pro'); ?></label><br />
                                    <input type="text" name="cpp_integration_settings[bunny_library_id]" value="<?php echo esc_attr(isset($integration_settings['bunny_library_id']) ? $integration_settings['bunny_library_id'] : ''); ?>" class="regular-text" />
                                </p>
                                <p>
                                    <label><?php _e('Bunny Token Auth Key', 'content-protect-pro'); ?></label><br />
                                    <input type="password" name="cpp_integration_settings[bunny_token_auth_key]" value="<?php echo esc_attr(isset($integration_settings['bunny_token_auth_key']) ? $integration_settings['bunny_token_auth_key'] : ''); ?>" class="regular-text" />
                                </p>
                                <p>
                                    <button type="button" class="button" id="cpp-test-bunny"><?php _e('Test Bunny Connection', 'content-protect-pro'); ?></button>
                                    <span id="cpp-test-bunny-result" style="margin-left:10px"></span>
                                </p>
                                <div style="margin-top:12px;">
                                    <button type="button" class="button" id="cpp-refresh-bunny-tests"><?php _e('Refresh recent tests', 'content-protect-pro'); ?></button>
                                    <div id="cpp-bunny-tests-list" style="margin-top:10px; max-height:240px; overflow:auto; border:1px solid #eee; padding:8px; background:#fff;"></div>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Enable signed playback URLs', 'content-protect-pro'); ?></th>
                            <td>
                                <fieldset>
                                    <label>
                                        <input type="checkbox" name="cpp_integration_settings[signed_urls]" value="1" <?php checked(1, isset($integration_settings['signed_urls']) ? $integration_settings['signed_urls'] : 0); ?> />
                                        <?php _e('Return a short-lived signed playback URL from the request-playback endpoint (stub signing).', 'content-protect-pro'); ?>
                                    </label>
                                    <p class="description"><?php _e('When enabled, the front-end will receive a playback_url instead of embed HTML. This uses a local HMAC-based signing stub unless you configure a CDN integration.', 'content-protect-pro'); ?></p>
                                </fieldset>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Token expiry (seconds)', 'content-protect-pro'); ?></th>
                            <td>
                                <input type="number" name="cpp_integration_settings[token_expiry]" value="<?php echo intval(isset($integration_settings['token_expiry']) ? $integration_settings['token_expiry'] : $integration_defaults['token_expiry']); ?>" min="60" max="86400" />
                                <p class="description"><?php _e('Duration in seconds for local playback tokens (used when Bunny is not available).', 'content-protect-pro'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Default Overlay Image', 'content-protect-pro'); ?></th>
                            <td>
                                <!-- Store attachment ID only -->
                                <input type="hidden" id="cpp_default_overlay_image" name="cpp_integration_settings[overlay_image]" value="<?php echo isset($integration_settings['overlay_image']) ? esc_attr($integration_settings['overlay_image']) : ''; ?>" />
                                <button type="button" class="button" id="cpp_default_overlay_button"><?php _e('Upload/Select Image', 'content-protect-pro'); ?></button>
                                <p class="description"><?php _e('A site-wide default overlay image used when a gift code does not specify its own. Please select an image from the Media Library; external URLs are no longer supported.', 'content-protect-pro'); ?></p>
                                <div id="cpp_default_overlay_preview" style="margin-top:8px;">
                                    <?php if (!empty($integration_settings['overlay_image'])): ?>
                                        <?php $ov = $integration_settings['overlay_image'];
                                        $ov_url = (ctype_digit((string)$ov) && function_exists('wp_get_attachment_url')) ? wp_get_attachment_url(intval($ov)) : '' ; ?>
                                        <?php if ($ov_url): ?>
                                            <img src="<?php echo esc_url($ov_url); ?>" style="max-width:200px; height:auto; border:1px solid #ddd; padding:4px; background:#fff;" />
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><?php _e('Default Purchase URL', 'content-protect-pro'); ?></th>
                            <td>
                                <input type="url" name="cpp_integration_settings[purchase_url]" value="<?php echo isset($integration_settings['purchase_url']) ? esc_attr($integration_settings['purchase_url']) : ''; ?>" class="regular-text" placeholder="https://example.com/buy" />
                                <p class="description"><?php _e('Site-wide fallback purchase link when a gift code does not provide one (defaults to site home if empty).', 'content-protect-pro'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('IP binding for tokens', 'content-protect-pro'); ?></th>
                            <td>
                                <fieldset>
                                    <label>
                                        <input type="checkbox" name="cpp_security_settings[ip_binding]" value="1" <?php checked(1, isset($security_settings['ip_binding']) ? $security_settings['ip_binding'] : 0); ?> />
                                        <?php _e('Bind local playback tokens to the requestor IP address (stronger anti-sharing).', 'content-protect-pro'); ?>
                                    </label>
                                    <p class="description"><?php _e('When enabled, local playback tokens will only work from the IP that requested the token.', 'content-protect-pro'); ?></p>
                                </fieldset>
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
                                    <?php _e(__('The plugin will automatically validate the gift code and display the Presto Player video if valid.', 'content-protect-pro'), 'content-protect-pro'); ?>
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
                        <p><?php _e(__('Run system diagnostics to check plugin configuration and performance.', 'content-protect-pro'), 'content-protect-pro'); ?></p>
                        <button type="button" class="button button-secondary" onclick="runDiagnostics()">
                            <?php _e('Run Diagnostics', 'content-protect-pro'); ?>
                        </button>
                        <div id="cpp-diagnostics-results" style="margin-top: 15px;"></div>
                    </div>
                    
                    <div class="cpp-advanced-section">
                        <h3><?php _e('Database Management', 'content-protect-pro'); ?></h3>
                        <p><?php _e(__('Manage plugin database tables and data.', 'content-protect-pro'), 'content-protect-pro'); ?></p>
                        
                        <button type="button" class="button button-secondary" onclick="recreateTables()">
                            <?php _e('Recreate Database Tables', 'content-protect-pro'); ?>
                        </button>
                        <p class="description"><?php _e('Recreate plugin database tables. Use if tables are missing or corrupted.', 'content-protect-pro'); ?></p>
                        
                        <button type="button" class="button button-secondary cpp-warning" onclick="cleanupLogs()">
                            <?php _e('Cleanup Old Logs', 'content-protect-pro'); ?>
                        </button>
                        <p class="description"><?php _e(__('Remove old analytics and security logs based on retention settings.', 'content-protect-pro'), 'content-protect-pro'); ?></p>
                        
                        <button type="button" class="button button-secondary cpp-danger" onclick="resetPlugin()">
                            <?php _e('Reset Plugin Data', 'content-protect-pro'); ?>
                        </button>
                        <p class="description"><?php _e('⚠️ WARNING: This will delete ALL plugin data including gift codes, videos, and analytics. This cannot be undone!', 'content-protect-pro'); ?></p>
                    </div>
                    
                    <div class="cpp-advanced-section">
                        <h3><?php _e('Import/Export', 'content-protect-pro'); ?></h3>
                        <p><?php _e(__('Export plugin settings and data or import from backup.', 'content-protect-pro'), 'content-protect-pro'); ?></p>
                        
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