<?php
<?php
/**
 * Admin AI Assistant Display Template
 * 
 * Provides AI chat interface with OnlyMatt avatar integration.
 * Follows WordPress admin UI patterns and security standards.
 * 
 * @package ContentProtectPro
 * @since 3.1.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Security check (capability already verified in render_admin_page)
if (!current_user_can('manage_options')) {
    wp_die(__('You do not have sufficient permissions to access this page.', 'content-protect-pro'));
}

// Get current user for personalization
$current_user = wp_get_current_user();

// Check if OnlyMatt API key is configured
$api_key_configured = !empty(get_option('cpp_onlymatt_api_key'));

// Get system stats for dashboard
global $wpdb;
$stats = [
    'active_codes' => $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->prefix}cpp_giftcodes 
         WHERE status IN ('unused', 'redeemed') 
         AND (expires_at IS NULL OR expires_at > NOW())"
    ),
    'protected_videos' => $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}cpp_protected_videos WHERE status = %s",
        'active'
    )),
    'active_sessions' => $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}cpp_sessions 
         WHERE status = %s AND expires_at > NOW()",
        'active'
    ))
];
?>

<div class="wrap cpp-ai-assistant-page">
    <h1 class="wp-heading-inline">
        <?php echo esc_html__('AI Admin Assistant', 'content-protect-pro'); ?>
    </h1>
    
    <?php if (!$api_key_configured): ?>
        <div class="notice notice-error">
            <p>
                <strong><?php echo esc_html__('API Key Required', 'content-protect-pro'); ?></strong>
                <?php 
                printf(
                    /* translators: %s: Settings page URL */
                    esc_html__('Please configure your OnlyMatt API key in %s to use the AI Assistant.', 'content-protect-pro'),
                    '<a href="' . esc_url(admin_url('admin.php?page=content-protect-pro-settings')) . '">' . esc_html__('Settings', 'content-protect-pro') . '</a>'
                );
                ?>
            </p>
        </div>
    <?php endif; ?>
    
    <div class="cpp-ai-container">
        <!-- Left Sidebar: System Overview -->
        <div class="cpp-ai-sidebar">
            <div class="cpp-ai-stats-card">
                <h3><?php echo esc_html__('System Overview', 'content-protect-pro'); ?></h3>
                <ul class="cpp-ai-stats-list">
                    <li>
                        <span class="dashicons dashicons-tickets-alt"></span>
                        <strong><?php echo esc_html(number_format_i18n($stats['active_codes'])); ?></strong>
                        <?php echo esc_html__('Active Gift Codes', 'content-protect-pro'); ?>
                    </li>
                    <li>
                        <span class="dashicons dashicons-video-alt3"></span>
                        <strong><?php echo esc_html(number_format_i18n($stats['protected_videos'])); ?></strong>
                        <?php echo esc_html__('Protected Videos', 'content-protect-pro'); ?>
                    </li>
                    <li>
                        <span class="dashicons dashicons-admin-users"></span>
                        <strong><?php echo esc_html(number_format_i18n($stats['active_sessions'])); ?></strong>
                        <?php echo esc_html__('Active Sessions', 'content-protect-pro'); ?>
                    </li>
                </ul>
            </div>
            
            <div class="cpp-ai-quick-actions">
                <h3><?php echo esc_html__('Quick Actions', 'content-protect-pro'); ?></h3>
                <button type="button" class="button cpp-ai-action-btn" data-action="diagnose-videos">
                    <span class="dashicons dashicons-search"></span>
                    <?php echo esc_html__('Diagnose Video Library', 'content-protect-pro'); ?>
                </button>
                <button type="button" class="button cpp-ai-action-btn" data-action="check-sessions">
                    <span class="dashicons dashicons-admin-network"></span>
                    <?php echo esc_html__('Check Active Sessions', 'content-protect-pro'); ?>
                </button>
                <button type="button" class="button cpp-ai-action-btn" data-action="review-errors">
                    <span class="dashicons dashicons-warning"></span>
                    <?php echo esc_html__('Review Recent Errors', 'content-protect-pro'); ?>
                </button>
                <button type="button" class="button cpp-ai-action-btn" data-action="generate-code">
                    <span class="dashicons dashicons-editor-code"></span>
                    <?php echo esc_html__('Generate Custom Code', 'content-protect-pro'); ?>
                </button>
            </div>
            
            <div class="cpp-ai-help-topics">
                <h3><?php echo esc_html__('Help Topics', 'content-protect-pro'); ?></h3>
                <ul>
                    <li><a href="#" class="cpp-ai-topic" data-topic="presto-integration"><?php echo esc_html__('Presto Player Integration', 'content-protect-pro'); ?></a></li>
                    <li><a href="#" class="cpp-ai-topic" data-topic="session-management"><?php echo esc_html__('Session Management', 'content-protect-pro'); ?></a></li>
                    <li><a href="#" class="cpp-ai-topic" data-topic="gift-codes"><?php echo esc_html__('Gift Code System', 'content-protect-pro'); ?></a></li>
                    <li><a href="#" class="cpp-ai-topic" data-topic="security"><?php echo esc_html__('Security Best Practices', 'content-protect-pro'); ?></a></li>
                </ul>
            </div>
        </div>
        
        <!-- Main Chat Area -->
        <div class="cpp-ai-main">
            <!-- Avatar Container -->
            <div class="cpp-ai-avatar-container">
                <div id="cpp-matt-avatar" class="cpp-matt-avatar">
                    <!-- Avatar video will be injected here by avatar.js -->
                    <video id="cpp-avatar-video" preload="auto" playsinline muted>
                        <source src="" type="video/mp4">
                    </video>
                    <div class="cpp-avatar-status">
                        <span class="cpp-status-indicator"></span>
                        <span class="cpp-status-text"><?php echo esc_html__('Ready', 'content-protect-pro'); ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Chat Messages -->
            <div id="cpp-chat-messages" class="cpp-chat-messages">
                <div class="cpp-chat-message cpp-message-assistant cpp-welcome-message">
                    <div class="cpp-message-avatar">
                        <img src="<?php echo esc_url(plugins_url('ai/generate_matt_audio/posters/yocestonlymatt.jpg', dirname(dirname(__FILE__)))); ?>" 
                             alt="Matt" 
                             class="cpp-avatar-thumb">
                    </div>
                    <div class="cpp-message-content">
                        <div class="cpp-message-header">
                            <strong><?php echo esc_html__('Matt', 'content-protect-pro'); ?></strong>
                            <span class="cpp-message-time"><?php echo esc_html(current_time('g:i A')); ?></span>
                        </div>
                        <div class="cpp-message-text">
                            <?php
                            printf(
                                /* translators: %s: User's display name */
                                esc_html__('Hey %s! 👋 I\'m here to help you with Content Protect Pro. Ask me anything about:', 'content-protect-pro'),
                                '<strong>' . esc_html($current_user->display_name) . '</strong>'
                            );
                            ?>
                            <ul class="cpp-help-list">
                                <li><?php echo esc_html__('Debugging video library issues', 'content-protect-pro'); ?></li>
                                <li><?php echo esc_html__('Writing PHP code for custom features', 'content-protect-pro'); ?></li>
                                <li><?php echo esc_html__('Analyzing session and analytics data', 'content-protect-pro'); ?></li>
                                <li><?php echo esc_html__('Fixing Presto Player integration problems', 'content-protect-pro'); ?></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Chat Input -->
            <div class="cpp-chat-input-container">
                <form id="cpp-chat-form" class="cpp-chat-form">
                    <?php wp_nonce_field('cpp_admin_ai_chat', 'cpp_ai_nonce'); ?>
                    
                    <div class="cpp-input-wrapper">
                        <textarea 
                            id="cpp-chat-input" 
                            name="message"
                            class="cpp-chat-input" 
                            placeholder="<?php echo esc_attr__('Ask Matt anything...', 'content-protect-pro'); ?>"
                            rows="1"
                            maxlength="2000"
                            <?php echo !$api_key_configured ? 'disabled' : ''; ?>
                        ></textarea>
                        
                        <div class="cpp-input-actions">
                            <button 
                                type="button" 
                                id="cpp-clear-history" 
                                class="button button-secondary cpp-btn-clear"
                                title="<?php echo esc_attr__('Clear chat history', 'content-protect-pro'); ?>"
                                <?php echo !$api_key_configured ? 'disabled' : ''; ?>
                            >
                                <span class="dashicons dashicons-trash"></span>
                            </button>
                            
                            <button 
                                type="submit" 
                                id="cpp-send-message" 
                                class="button button-primary cpp-btn-send"
                                <?php echo !$api_key_configured ? 'disabled' : ''; ?>
                            >
                                <span class="dashicons dashicons-arrow-up-alt2"></span>
                                <?php echo esc_html__('Send', 'content-protect-pro'); ?>
                            </button>
                        </div>
                    </div>
                    
                    <div class="cpp-input-info">
                        <span class="cpp-char-count">
                            <span id="cpp-char-current">0</span> / 2000
                        </span>
                        <span class="cpp-powered-by">
                            <?php echo esc_html__('Powered by OnlyMatt', 'content-protect-pro'); ?>
                            <span class="cpp-model-badge" title="<?php echo esc_attr__('Using Claude Sonnet 4.5 Preview', 'content-protect-pro'); ?>">
                                🧠 Claude Sonnet 4.5
                            </span>
                        </span>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Loading Overlay -->
<div id="cpp-ai-loading" class="cpp-ai-loading" style="display:none;">
    <div class="cpp-loading-spinner">
        <div class="spinner is-active"></div>
        <p><?php echo esc_html__('Matt is thinking...', 'content-protect-pro'); ?></p>
    </div>
</div>

<!-- Template for AI message (hidden) -->
<template id="cpp-message-template-assistant">
    <div class="cpp-chat-message cpp-message-assistant">
        <div class="cpp-message-avatar">
            <img src="<?php echo esc_url(plugins_url('ai/generate_matt_audio/posters/yocestonlymatt.jpg', dirname(dirname(__FILE__)))); ?>" 
                 alt="Matt" 
                 class="cpp-avatar-thumb">
        </div>
        <div class="cpp-message-content">
            <div class="cpp-message-header">
                <strong><?php echo esc_html__('Matt', 'content-protect-pro'); ?></strong>
                <span class="cpp-message-time"></span>
            </div>
            <div class="cpp-message-text"></div>
        </div>
    </div>
</template>

<!-- Template for user message (hidden) -->
<template id="cpp-message-template-user">
    <div class="cpp-chat-message cpp-message-user">
        <div class="cpp-message-content">
            <div class="cpp-message-header">
                <strong><?php echo esc_html($current_user->display_name); ?></strong>
                <span class="cpp-message-time"></span>
            </div>
            <div class="cpp-message-text"></div>
        </div>
        <div class="cpp-message-avatar">
            <?php echo get_avatar($current_user->ID, 40, '', '', ['class' => 'cpp-avatar-thumb']); ?>
        </div>
    </div>
</template>

<style>
/* Critical inline styles for layout (full styles in cpp-ai-assistant.css) */
.cpp-ai-container {
    display: grid;
    grid-template-columns: 300px 1fr;
    gap: 20px;
    margin-top: 20px;
    max-width: 1400px;
}

.cpp-ai-sidebar {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
}

.cpp-ai-main {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    display: flex;
    flex-direction: column;
    height: calc(100vh - 200px);
    min-height: 600px;
}

.cpp-ai-avatar-container {
    padding: 15px;
    border-bottom: 1px solid #dcdcde;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.cpp-matt-avatar {
    position: relative;
    max-width: 200px;
    margin: 0 auto;
}

#cpp-avatar-video {
    width: 100%;
    height: auto;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.3);
}

.cpp-chat-messages {
    flex: 1;
    overflow-y: auto;
    padding: 20px;
    background: #f6f7f7;
}

.cpp-chat-input-container {
    border-top: 1px solid #dcdcde;
    padding: 15px;
    background: #fff;
}

@media (max-width: 960px) {
    .cpp-ai-container {
        grid-template-columns: 1fr;
    }
    .cpp-ai-sidebar {
        order: 2;
    }
}
</style>

<script>
// Initialize avatar system when page loads
jQuery(document).ready(function($) {
    // Load OnlyMatt clips configuration
    if (typeof cppAiVars !== 'undefined' && cppAiVars.avatar_clips_url) {
        $.getJSON(cppAiVars.avatar_clips_url, function(clips) {
            window.cppMattClips = clips;
            console.log('Matt avatar clips loaded:', Object.keys(clips).length);
            
            // Play greeting clip
            if (clips.yocestonlymatt) {
                $('#cpp-avatar-video source').attr('src', cppAiVars.avatar_base_url + 'clips_muted/' + clips.yocestonlymatt);
                $('#cpp-avatar-video')[0].load();
                $('#cpp-avatar-video')[0].play();
            }
        });
    }
});
</script>