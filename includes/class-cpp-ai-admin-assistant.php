<?php
/**
 * AI Admin Assistant with OnlyMatt Avatar Integration
 * 
 * Integrates with existing OnlyMatt Gateway and avatar system
 * Provides intelligent troubleshooting with visual feedback
 * 
 * @package ContentProtectPro
 * @since 3.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class CPP_AI_Admin_Assistant {
    
    /**
     * OnlyMatt Gateway endpoint
     */
    private const GATEWAY_URL = 'https://api.onlymatt.ca';
    
    /**
     * Admin session prefix
     */
    private const ADMIN_SESSION_PREFIX = 'cpp_admin_';
    
    /**
     * Rate limit: 50 requests per hour
     */
    private const RATE_LIMIT = 50;
    private const RATE_WINDOW = 3600;
    
    /**
     * Initialize
     */
    public function __construct() {
        add_action('admin_menu', [$this, 'register_admin_page']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        
        // AJAX handlers (following WordPress standards)
        add_action('wp_ajax_cpp_admin_chat', [$this, 'handle_admin_chat']);
        add_action('wp_ajax_cpp_admin_clear_history', [$this, 'handle_clear_history']);
        add_action('wp_ajax_cpp_admin_get_context', [$this, 'handle_get_context']);
    }
    
    /**
     * Register admin page
     */
    public function register_admin_page() {
        add_submenu_page(
            'content-protect-pro',
            __('AI Assistant', 'content-protect-pro'),
            __('🤖 AI Assistant', 'content-protect-pro'),
            'manage_options',
            'cpp-ai-assistant',
            [$this, 'render_admin_page']
        );
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        if ($hook !== 'content-protect-pro_page_cpp-ai-assistant') {
            return;
        }
        
        // OnlyMatt avatar assets (reuse existing system)
        wp_enqueue_script(
            'onlymatt-avatar',
            plugins_url('ai/generate_matt_audio/avatar.js'),
            ['jquery'],
            '1.0.0',
            true
        );
        
        // AI Assistant styles
        wp_enqueue_style(
            'cpp-ai-assistant',
            plugin_dir_url(dirname(__FILE__)) . 'admin/css/cpp-ai-assistant.css',
            [],
            '3.1.0'
        );
        
        // AI Assistant scripts
        wp_enqueue_script(
            'cpp-ai-assistant',
            plugin_dir_url(dirname(__FILE__)) . 'admin/js/cpp-ai-assistant.js',
            ['jquery', 'onlymatt-avatar'],
            '3.1.0',
            true
        );
        
        // Localize with AJAX config
        wp_localize_script('cpp-ai-assistant', 'cppAiVars', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cpp_admin_ai_chat'),
            'user_id' => get_current_user_id(),
            'user_name' => wp_get_current_user()->display_name,
            'avatar_clips_url' => plugins_url('ai/generate_matt_audio/clips.json'),
            'avatar_base_url' => plugins_url('ai/generate_matt_audio/'),
            'strings' => [
                'sending' => __('Sending...', 'content-protect-pro'),
                'thinking' => __('Matt is thinking...', 'content-protect-pro'),
                'error' => __('Error communicating with AI', 'content-protect-pro'),
                'rate_limit' => __('Too many requests. Please wait.', 'content-protect-pro'),
                'cleared' => __('Chat history cleared', 'content-protect-pro')
            ]
        ]);
    }
    
    /**
     * Render admin page
     */
    public function render_admin_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__(__('You do not have sufficient permissions to access this page.', 'content-protect-pro'), 'content-protect-pro'));
        }
        
        include plugin_dir_path(dirname(__FILE__)) . 'admin/partials/cpp-admin-ai-assistant-display.php';
    }
    
    /**
     * Handle admin chat AJAX (following security patterns)
     */
    public function handle_admin_chat() {
        // CSRF protection (required by WordPress standards)
        if (!check_ajax_referer('cpp_admin_ai_chat', 'nonce', false)) {
            wp_send_json_error([
                'message' => __('Security check failed', 'content-protect-pro')
            ], 403);
        }
        
        // Capability check
        if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'message' => __('Unauthorized access', 'content-protect-pro')
            ], 403);
        }
        
        // Rate limiting
        $user_id = get_current_user_id();
        if (!$this->check_rate_limit($user_id)) {
            wp_send_json_error([
                'message' => __('Rate limit exceeded. Please wait before sending more messages.', 'content-protect-pro')
            ], 429);
        }
        
        // Sanitize input
        $message = isset($_POST['message']) ? sanitize_textarea_field($_POST['message']) : '';
        
        if (empty($message)) {
            wp_send_json_error([
                'message' => __('Message cannot be empty', 'content-protect-pro')
            ], 400);
        }
        
        // Build comprehensive system context
        $context = $this->build_admin_context();
        
        // Send to OnlyMatt Gateway
        $response = $this->send_to_gateway($message, $context, $user_id);
        
        if (!$response['success']) {
            wp_send_json_error([
                'message' => $response['error']
            ], 500);
        }
        
        // Log conversation to analytics
        $this->log_conversation($user_id, $message, $response['reply']);
        
        wp_send_json_success([
            'reply' => $response['reply'],
            'avatar_clip' => $this->get_avatar_clip_for_message($response['reply']),
            'metadata' => [
                'timestamp' => current_time('mysql'),
                'model' => $response['model'] ?? 'onlymatt',
                'tokens' => $response['tokens'] ?? null
            ]
        ]);
    }
    
    /**
     * Build comprehensive admin context with live system data
     */
    private function build_admin_context() {
        global $wpdb;
        
        $current_user = wp_get_current_user();
        $plugin_dir = plugin_dir_path(dirname(__FILE__));
        
        // Get real system statistics (using $wpdb->prepare for security)
        $stats = [
            'active_codes' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}cpp_giftcodes 
                 WHERE status IN (%s, %s) 
                 AND (expires_at IS NULL OR expires_at > NOW())",
                'unused', 'redeemed'
            )),
            'total_codes' => $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->prefix}cpp_giftcodes"
            ),
            'protected_videos' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}cpp_protected_videos 
                 WHERE status = %s",
                'active'
            )),
            'active_sessions' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}cpp_sessions 
                 WHERE status = %s AND expires_at > NOW()",
                'active'
            )),
            'total_redemptions' => (int) $wpdb->get_var(
                "SELECT SUM(redemption_count) FROM {$wpdb->prefix}cpp_giftcodes"
            )
        ];
        
        // Get recent errors
        $recent_errors = $wpdb->get_results(
            "SELECT event_type, metadata, created_at 
             FROM {$wpdb->prefix}cpp_analytics 
             WHERE (event_type LIKE '%error%' 
                OR event_type = 'session_ip_mismatch'
                OR event_type = 'validation_failed')
             ORDER BY created_at DESC 
             LIMIT 5",
            ARRAY_A
        );
        
        // Get recent gift codes
        $recent_codes = $wpdb->get_results(
            "SELECT code, duration_minutes, status, created_at, redemption_count 
             FROM {$wpdb->prefix}cpp_giftcodes 
             ORDER BY created_at DESC 
             LIMIT 5",
            ARRAY_A
        );
        
        // Get protected videos
        $protected_videos = $wpdb->get_results(
            "SELECT video_id, presto_player_id, required_minutes, integration_type, status 
             FROM {$wpdb->prefix}cpp_protected_videos 
             ORDER BY created_at DESC 
             LIMIT 5",
            ARRAY_A
        );
        
        // Check active integrations
        $integrations = [
            'presto_player' => class_exists('CPP_Presto_Integration'),
            'analytics' => class_exists('CPP_Analytics'),
            'onlymatt_gateway' => !empty(get_option('cpp_onlymatt_api_key'))
        ];
        
        // Scan file structure
        $file_structure = $this->scan_plugin_files($plugin_dir);
        
        return [
            'role' => 'admin',
            'user' => [
                'id' => $current_user->ID,
                'name' => $current_user->display_name,
                'email' => $current_user->user_email
            ],
            'site' => [
                'url' => home_url(),
                'name' => get_bloginfo('name'),
                'wp_version' => get_bloginfo('version'),
                'php_version' => PHP_VERSION
            ],
            'plugin' => [
                'version' => defined('CPP_VERSION') ? CPP_VERSION : '3.0.0',
                'path' => $plugin_dir,
                'active_integrations' => $integrations,
                'file_structure' => $file_structure,
                'stats' => $stats
            ],
            'database' => [
                'tables' => [
                    'giftcodes' => $wpdb->prefix . 'cpp_giftcodes',
                    'sessions' => $wpdb->prefix . 'cpp_sessions',
                    'protected_videos' => $wpdb->prefix . 'cpp_protected_videos',
                    'analytics' => $wpdb->prefix . 'cpp_analytics'
                ],
                'prefix' => $wpdb->prefix
            ],
            'recent_activity' => [
                'errors' => array_map(function($error) {
                    $metadata = json_decode($error['metadata'], true);
                    return [
                        'time' => human_time_diff(strtotime($error['created_at']), current_time('timestamp')) . ' ago',
                        'type' => $error['event_type'],
                        'details' => $metadata
                    ];
                }, $recent_errors ?: []),
                'recent_codes' => $recent_codes,
                'protected_videos' => $protected_videos
            ]
        ];
    }
    
    /**
     * Scan plugin files
     */
    private function scan_plugin_files($dir) {
        $structure = [
            'includes' => [],
            'admin' => [],
            'public' => []
        ];
        
        if (is_dir($dir . 'includes')) {
            $files = scandir($dir . 'includes');
            foreach ($files as $file) {
                if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
                    $structure['includes'][] = $file;
                }
            }
        }
        
        if (is_dir($dir . 'admin/partials')) {
            $files = scandir($dir . 'admin/partials');
            foreach ($files as $file) {
                if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
                    $structure['admin'][] = 'partials/' . $file;
                }
            }
        }
        
        return $structure;
    }
    
    /**
     * Send message to OnlyMatt Gateway
     */
    private function send_to_gateway($message, $context, $user_id) {
        // Use the static helper to get decrypted key
        $api_key = CPP_Settings_AI::get_api_key();
        
        if (empty($api_key)) {
            return [
                'success' => false,
                'error' => __(__('OnlyMatt Gateway API key not configured.', 'content-protect-pro'), 'content-protect-pro')
            ];
        }
        
        $session_id = self::ADMIN_SESSION_PREFIX . $user_id;
        
        $payload = [
            'session' => $session_id,
            'provider' => 'ollama',
            'model' => 'onlymatt',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $this->build_system_prompt($context)
                ],
                [
                    'role' => 'user',
                    'content' => $message
                ]
            ],
            'keep' => 30
        ];
        
        $response = wp_remote_post(self::GATEWAY_URL . '/chat', [
            'headers' => [
                'Content-Type' => 'application/json',
                'X-OM-KEY' => $api_key
            ],
            'body' => wp_json_encode($payload),
            'timeout' => 45
        ]);
        
        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => sprintf(__('Gateway error: %s', 'content-protect-pro'), $response->get_error_message())
            ];
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            return [
                'success' => false,
                'error' => sprintf(__('Gateway returned error code: %d', 'content-protect-pro'), $status_code)
            ];
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!isset($data['reply'])) {
            return [
                'success' => false,
                'error' => __('Invalid response from gateway', 'content-protect-pro')
            ];
        }
        
        return [
            'success' => true,
            'reply' => $data['reply'],
            'model' => $data['model'] ?? 'onlymatt',
            'tokens' => $data['tokens_used'] ?? null
        ];
    }
    
    /**
     * Build system prompt with full plugin knowledge
     */
    private function build_system_prompt($context) {
        // Load copilot instructions
        $instructions_file = plugin_dir_path(dirname(__FILE__)) . '.github/copilot-instructions.md';
        $copilot_instructions = file_exists($instructions_file) 
            ? file_get_contents($instructions_file) 
            : '';
        
        $prompt = "# Content Protect Pro - AI Admin Assistant\n\n";
        $prompt .= "## YOUR ROLE\n";
        $prompt .= "You are Matt's AI assistant helping manage Content Protect Pro.\n\n";
        
        $prompt .= "**ADMIN MODE**: Speaking to {$context['user']['name']} with FULL SYSTEM ACCESS.\n\n";
        
        $prompt .= "## LIVE SYSTEM STATE\n\n";
        $prompt .= "### Statistics\n";
        $prompt .= "- Active Codes: {$context['plugin']['stats']['active_codes']} / {$context['plugin']['stats']['total_codes']}\n";
        $prompt .= "- Protected Videos: {$context['plugin']['stats']['protected_videos']}\n";
        $prompt .= "- Active Sessions: {$context['plugin']['stats']['active_sessions']}\n";
        $prompt .= "- Total Redemptions: {$context['plugin']['stats']['total_redemptions']}\n\n";
        
        $prompt .= "### Integrations\n";
        foreach ($context['plugin']['active_integrations'] as $name => $active) {
            $icon = $active ? '✅' : '❌';
            $prompt .= "- {$icon} " . ucfirst(str_replace('_', ' ', $name)) . "\n";
        }
        $prompt .= "\n";
        
        $prompt .= "### Files (includes/)\n";
        foreach ($context['plugin']['file_structure']['includes'] as $file) {
            $prompt .= "- `{$file}`\n";
        }
        $prompt .= "\n";
        
        if (!empty($context['recent_activity']['errors'])) {
            $prompt .= "### ⚠️ RECENT ERRORS\n";
            foreach ($context['recent_activity']['errors'] as $error) {
                $prompt .= "- [{$error['time']}] {$error['type']}\n";
            }
            $prompt .= "\n";
        }
        
        $prompt .= "## YOUR CAPABILITIES\n";
        $prompt .= "- Write PHP code with proper WordPress standards\n";
        $prompt .= "- Generate SQL with \$wpdb->prepare()\n";
        $prompt .= "- Debug Presto Player integration\n";
        $prompt .= "- Analyze analytics data\n";
        $prompt .= "- Provide file paths and security checks\n\n";
        
        if (!empty($copilot_instructions)) {
            $prompt .= "## FULL ARCHITECTURE\n\n";
            $prompt .= $copilot_instructions . "\n\n";
        }
        
        $prompt .= "Now help with the admin's question using this context.\n";
        
        return $prompt;
    }
    
    /**
     * Get appropriate avatar clip for message type
     */
    private function get_avatar_clip_for_message($message) {
        // Map message content to OnlyMatt avatar clips
        $message_lower = strtolower($message);
        
        if (strpos($message_lower, 'error') !== false || strpos($message_lower, 'problem') !== false) {
            return 'icitupeuxdem_uetuveux.mp4'; // "Ici tu peux demander ce que tu veux"
        }
        
        if (strpos($message_lower, 'success') !== false || strpos($message_lower, 'works') !== false) {
            return 'fuckyeababy.mp4'; // Success celebration
        }
        
        if (strpos($message_lower, 'code') !== false || strpos($message_lower, 'query') !== false) {
            return 'tachetetonco_piscestca.mp4'; // Code explanation
        }
        
        // Default: casual talking
        return 'yocestonlymatt.mp4';
    }
    
    /**
     * Check rate limit (using transients)
     */
    private function check_rate_limit($user_id) {
        $transient_key = 'cpp_admin_ai_rate_' . $user_id;
        $attempts = get_transient($transient_key);
        
        if ($attempts === false) {
            set_transient($transient_key, 1, self::RATE_WINDOW);
            return true;
        }
        
        if ($attempts >= self::RATE_LIMIT) {
            return false;
        }
        
        set_transient($transient_key, $attempts + 1, self::RATE_WINDOW);
        return true;
    }
    
    /**
     * Log conversation to analytics
     */
    private function log_conversation($user_id, $message, $reply) {
        if (!class_exists('CPP_Analytics')) {
            return;
        }
        
        $analytics = new CPP_Analytics();
        $analytics->log_event(
            'admin_ai_chat',
            'admin',
            $user_id,
            [
                'message_length' => strlen($message),
                'reply_length' => strlen($reply),
                'timestamp' => current_time('mysql')
            ]
        );
    }
    
    /**
     * Handle clear history
     */
    public function handle_clear_history() {
        check_ajax_referer('cpp_admin_ai_chat', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'content-protect-pro')], 403);
        }
        
        $user_id = get_current_user_id();
        $session_id = self::ADMIN_SESSION_PREFIX . $user_id;
        $api_key = get_option('cpp_onlymatt_api_key');
        
        if (!empty($api_key)) {
            wp_remote_post(self::GATEWAY_URL . '/history', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-OM-KEY' => $api_key
                ],
                'body' => wp_json_encode([
                    'session' => $session_id,
                    'action' => 'clear'
                ])
            ]);
        }
        
        wp_send_json_success(['message' => __('Chat history cleared', 'content-protect-pro')]);
    }
    
    /**
     * Handle get context (for debugging)
     */
    public function handle_get_context() {
        check_ajax_referer('cpp_admin_ai_chat', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'content-protect-pro')], 403);
        }
        
        $context = $this->build_admin_context();
        wp_send_json_success($context);
    }
}