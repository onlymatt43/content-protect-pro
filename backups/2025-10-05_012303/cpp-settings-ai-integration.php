<?php
<?php
/**
 * AI Integration Settings Template
 * 
 * Provides OnlyMatt Gateway API key configuration.
 * Follows WordPress Settings API patterns and security standards.
 * 
 * @package ContentProtectPro
 * @since 3.1.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Security check
if (!current_user_can('manage_options')) {
    wp_die(__('You do not have sufficient permissions to access this page.', 'content-protect-pro'));
}

// Get current settings
$api_key = get_option('cpp_onlymatt_api_key', '');
$ai_enabled = get_option('cpp_ai_assistant_enabled', true);

// Test connection status
$connection_status = null;
if (!empty($api_key)) {
    $connection_status = $this->test_gateway_connection($api_key);
}
?>

<div class="cpp-settings-section cpp-ai-integration-settings">
    <h2><?php echo esc_html__('AI Integration Settings', 'content-protect-pro'); ?></h2>
    
    <table class="form-table" role="presentation">
        <tbody>
            <!-- AI Assistant Toggle -->
            <tr>
                <th scope="row">
                    <label for="cpp_ai_assistant_enabled">
                        <?php echo esc_html__('Enable AI Assistant', 'content-protect-pro'); ?>
                    </label>
                </th>
                <td>
                    <label for="cpp_ai_assistant_enabled">
                        <input 
                            type="checkbox" 
                            name="cpp_ai_assistant_enabled" 
                            id="cpp_ai_assistant_enabled"
                            value="1"
                            <?php checked($ai_enabled, true); ?>
                        />
                        <?php echo esc_html__('Enable AI-powered admin assistant with Matt\'s avatar', 'content-protect-pro'); ?>
                    </label>
                    <p class="description">
                        <?php echo esc_html__('Provides intelligent troubleshooting and code generation assistance.', 'content-protect-pro'); ?>
                    </p>
                </td>
            </tr>
            
            <!-- OnlyMatt API Key -->
            <tr>
                <th scope="row">
                    <label for="cpp_onlymatt_api_key">
                        <?php echo esc_html__('OnlyMatt Gateway API Key', 'content-protect-pro'); ?>
                        <span class="required">*</span>
                    </label>
                </th>
                <td>
                    <input 
                        type="password" 
                        name="cpp_onlymatt_api_key" 
                        id="cpp_onlymatt_api_key"
                        value="<?php echo esc_attr($api_key); ?>"
                        class="regular-text"
                        placeholder="om-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
                        autocomplete="off"
                    />
                    <button 
                        type="button" 
                        class="button button-secondary cpp-toggle-visibility"
                        data-target="cpp_onlymatt_api_key"
                    >
                        <span class="dashicons dashicons-visibility"></span>
                        <?php echo esc_html__('Show', 'content-protect-pro'); ?>
                    </button>
                    
                    <?php if (!empty($api_key)): ?>
                        <button 
                            type="button" 
                            id="cpp-test-gateway-connection" 
                            class="button button-secondary"
                            data-nonce="<?php echo esc_attr(wp_create_nonce('cpp_test_gateway')); ?>"
                        >
                            <span class="dashicons dashicons-admin-plugins"></span>
                            <?php echo esc_html__('Test Connection', 'content-protect-pro'); ?>
                        </button>
                    <?php endif; ?>
                    
                    <p class="description">
                        <?php 
                        printf(
                            /* translators: %s: OnlyMatt Gateway URL */
                            esc_html__('Required for AI Assistant. Get your API key from %s', 'content-protect-pro'),
                            '<a href="https://api.onlymatt.ca" target="_blank" rel="noopener">' . 
                            esc_html__('OnlyMatt Gateway', 'content-protect-pro') . 
                            '</a>'
                        );
                        ?>
                    </p>
                    
                    <?php if ($connection_status): ?>
                        <div class="cpp-connection-status">
                            <?php if ($connection_status['success']): ?>
                                <div class="notice notice-success inline">
                                    <p>
                                        <span class="dashicons dashicons-yes-alt"></span>
                                        <?php 
                                        printf(
                                            /* translators: %s: AI model name */
                                            esc_html__('Connected successfully. Model: %s', 'content-protect-pro'),
                                            '<strong>' . esc_html($connection_status['model']) . '</strong>'
                                        );
                                        ?>
                                    </p>
                                </div>
                            <?php else: ?>
                                <div class="notice notice-error inline">
                                    <p>
                                        <span class="dashicons dashicons-warning"></span>
                                        <?php 
                                        printf(
                                            /* translators: %s: Error message */
                                            esc_html__('Connection failed: %s', 'content-protect-pro'),
                                            esc_html($connection_status['error'])
                                        );
                                        ?>
                                    </p>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </td>
            </tr>
            
            <!-- Gateway Configuration -->
            <tr>
                <th scope="row">
                    <?php echo esc_html__('Gateway Configuration', 'content-protect-pro'); ?>
                </th>
                <td>
                    <fieldset>
                        <legend class="screen-reader-text">
                            <span><?php echo esc_html__('Gateway Configuration', 'content-protect-pro'); ?></span>
                        </legend>
                        
                        <p class="cpp-info-box">
                            <strong><?php echo esc_html__('Endpoint:', 'content-protect-pro'); ?></strong>
                            <code>https://api.onlymatt.ca/chat</code>
                        </p>
                        
                        <p class="cpp-info-box">
                            <strong><?php echo esc_html__('Model:', 'content-protect-pro'); ?></strong>
                            <code>onlymatt</code> (Claude Sonnet 4.5 Preview via Ollama)
                        </p>
                        
                        <p class="cpp-info-box">
                            <strong><?php echo esc_html__('Rate Limits:', 'content-protect-pro'); ?></strong>
                            50 requests per hour per admin user
                        </p>
                        
                        <p class="cpp-info-box">
                            <strong><?php echo esc_html__('Session Persistence:', 'content-protect-pro'); ?></strong>
                            Last 30 messages kept for context
                        </p>
                    </fieldset>
                </td>
            </tr>
            
            <!-- Avatar Integration -->
            <tr>
                <th scope="row">
                    <?php echo esc_html__('Matt Avatar Integration', 'content-protect-pro'); ?>
                </th>
                <td>
                    <?php 
                    $avatar_plugin_active = is_plugin_active('ai/om-ai-coach.php');
                    $clips_path = WP_PLUGIN_DIR . '/ai/generate_matt_audio/clips.json';
                    $clips_exist = file_exists($clips_path);
                    ?>
                    
                    <div class="cpp-avatar-status">
                        <p>
                            <span class="dashicons dashicons-<?php echo $avatar_plugin_active ? 'yes-alt' : 'warning'; ?>"></span>
                            <?php 
                            echo $avatar_plugin_active 
                                ? esc_html__('OnlyMatt AI plugin detected', 'content-protect-pro')
                                : esc_html__('OnlyMatt AI plugin not found', 'content-protect-pro');
                            ?>
                        </p>
                        
                        <p>
                            <span class="dashicons dashicons-<?php echo $clips_exist ? 'yes-alt' : 'warning'; ?>"></span>
                            <?php 
                            echo $clips_exist
                                ? esc_html__('Avatar clips available', 'content-protect-pro')
                                : esc_html__('Avatar clips not found', 'content-protect-pro');
                            ?>
                        </p>
                        
                        <?php if ($clips_exist): ?>
                            <?php 
                            $clips_data = json_decode(file_get_contents($clips_path), true);
                            $clips_count = is_array($clips_data) ? count($clips_data) : 0;
                            ?>
                            <p class="description">
                                <?php 
                                printf(
                                    /* translators: %d: Number of avatar clips */
                                    esc_html__('%d avatar clips loaded from OnlyMatt system', 'content-protect-pro'),
                                    (int) $clips_count
                                );
                                ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            
            <!-- Quick Access -->
            <tr>
                <th scope="row">
                    <?php echo esc_html__('Quick Access', 'content-protect-pro'); ?>
                </th>
                <td>
                    <?php if (!empty($api_key) && $ai_enabled): ?>
                        <a 
                            href="<?php echo esc_url(admin_url('admin.php?page=cpp-ai-assistant')); ?>" 
                            class="button button-primary"
                        >
                            <span class="dashicons dashicons-admin-customizer"></span>
                            <?php echo esc_html__('Open AI Assistant', 'content-protect-pro'); ?>
                        </a>
                    <?php else: ?>
                        <p class="description">
                            <?php echo esc_html__('Configure API key and enable AI Assistant to access the assistant.', 'content-protect-pro'); ?>
                        </p>
                    <?php endif; ?>
                </td>
            </tr>
        </tbody>
    </table>
</div>

<style>
.cpp-ai-integration-settings .required {
    color: #d63638;
}

.cpp-toggle-visibility {
    margin-left: 8px;
}

.cpp-connection-status {
    margin-top: 12px;
}

.cpp-connection-status .notice {
    margin: 0;
    padding: 8px 12px;
}

.cpp-connection-status .dashicons {
    vertical-align: middle;
    margin-right: 4px;
}

.cpp-info-box {
    background: #f6f7f7;
    border-left: 4px solid #2271b1;
    padding: 8px 12px;
    margin: 8px 0;
}

.cpp-info-box code {
    background: #fff;
    padding: 2px 6px;
    border-radius: 3px;
}

.cpp-avatar-status p {
    margin: 8px 0;
}

.cpp-avatar-status .dashicons {
    vertical-align: middle;
    margin-right: 6px;
}

.cpp-avatar-status .dashicons-yes-alt {
    color: #00a32a;
}

.cpp-avatar-status .dashicons-warning {
    color: #dba617;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Toggle password visibility
    $('.cpp-toggle-visibility').on('click', function() {
        var $btn = $(this);
        var target = $btn.data('target');
        var $input = $('#' + target);
        
        if ($input.attr('type') === 'password') {
            $input.attr('type', 'text');
            $btn.find('.dashicons').removeClass('dashicons-visibility').addClass('dashicons-hidden');
            $btn.find('span:not(.dashicons)').text('<?php echo esc_js(__('Hide', 'content-protect-pro')); ?>');
        } else {
            $input.attr('type', 'password');
            $btn.find('.dashicons').removeClass('dashicons-hidden').addClass('dashicons-visibility');
            $btn.find('span:not(.dashicons)').text('<?php echo esc_js(__('Show', 'content-protect-pro')); ?>');
        }
    });
    
    // Test gateway connection
    $('#cpp-test-gateway-connection').on('click', function() {
        var $btn = $(this);
        var apiKey = $('#cpp_onlymatt_api_key').val();
        
        if (!apiKey) {
            alert('<?php echo esc_js(__('Please enter an API key first', 'content-protect-pro')); ?>');
            return;
        }
        
        $btn.prop('disabled', true);
        $btn.find('.dashicons').addClass('cpp-spin');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'cpp_test_gateway_connection',
                nonce: $btn.data('nonce'),
                api_key: apiKey
            },
            success: function(response) {
                if (response.success) {
                    var $notice = $('<div class="notice notice-success inline"><p>' +
                        '<span class="dashicons dashicons-yes-alt"></span> ' +
                        '<?php echo esc_js(__('Connected successfully. Model:', 'content-protect-pro')); ?> ' +
                        '<strong>' + response.data.model + '</strong>' +
                        '</p></div>');
                    $('.cpp-connection-status').remove();
                    $('#cpp_onlymatt_api_key').closest('td').append('<div class="cpp-connection-status"></div>');
                    $('.cpp-connection-status').html($notice);
                } else {
                    var $notice = $('<div class="notice notice-error inline"><p>' +
                        '<span class="dashicons dashicons-warning"></span> ' +
                        '<?php echo esc_js(__('Connection failed:', 'content-protect-pro')); ?> ' +
                        response.data.message +
                        '</p></div>');
                    $('.cpp-connection-status').remove();
                    $('#cpp_onlymatt_api_key').closest('td').append('<div class="cpp-connection-status"></div>');
                    $('.cpp-connection-status').html($notice);
                }
            },
            error: function() {
                alert('<?php echo esc_js(__('Failed to test connection', 'content-protect-pro')); ?>');
            },
            complete: function() {
                $btn.prop('disabled', false);
                $btn.find('.dashicons').removeClass('cpp-spin');
            }
        });
    });
});
</script>

<style>
@keyframes cpp-spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.cpp-spin {
    animation: cpp-spin 1s linear infinite;
}
</style>