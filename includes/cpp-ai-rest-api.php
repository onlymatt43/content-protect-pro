<?php
/**
 * AI Assistant REST API Endpoints
 * 
 * Registers routes for OnlyMatt AI integration.
 * Following copilot-instructions.md security patterns.
 *
 * @package Content_Protect_Pro
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register AI Assistant REST routes
 */
add_action('rest_api_init', 'cpp_register_ai_rest_routes');

function cpp_register_ai_rest_routes() {
    // Chat endpoint
    register_rest_route('cpp/v1', '/ai/chat', [
        'methods' => 'POST',
        'callback' => 'cpp_ai_chat_handler',
        'permission_callback' => function() {
            return current_user_can('manage_options');
        },
        'args' => [
            'message' => [
                'required' => true,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'conversation_id' => [
                'required' => false,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
        ],
    ]);
    
    // Ask endpoint (quick questions)
    register_rest_route('cpp/v1', '/ai/ask', [
        'methods' => 'POST',
        'callback' => 'cpp_ai_ask_handler',
        'permission_callback' => function() {
            return current_user_can('manage_options');
        },
        'args' => [
            'question' => [
                'required' => true,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
        ],
    ]);
    
    // Suggestions endpoint
    register_rest_route('cpp/v1', '/ai/suggest', [
        'methods' => 'POST',
        'callback' => 'cpp_ai_suggest_handler',
        'permission_callback' => function() {
            return current_user_can('manage_options');
        },
        'args' => [
            'context' => [
                'required' => true,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
        ],
    ]);
    
    // Test connection endpoint
    register_rest_route('cpp/v1', '/ai/test-connection', [
        'methods' => 'GET',
        'callback' => 'cpp_ai_test_connection_handler',
        'permission_callback' => function() {
            return current_user_can('manage_options');
        },
    ]);
}

/**
 * Handle AI chat request
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response
 */
function cpp_ai_chat_handler($request) {
    $message = sanitize_text_field($request->get_param('message'));
    $conversation_id = sanitize_text_field($request->get_param('conversation_id') ?? '');
    
    if (empty($message)) {
        return new WP_REST_Response([
            'success' => false,
            'message' => __(__('Message is required.', 'content-protect-pro'), 'content-protect-pro'),
        ], 400);
    }
    
    // Check if AI is enabled
    if (!get_option('cpp_ai_assistant_enabled')) {
        return new WP_REST_Response([
            'success' => false,
            'message' => __(__('AI Assistant is disabled.', 'content-protect-pro'), 'content-protect-pro'),
        ], 403);
    }
    
    // Load AI Assistant class
    if (!class_exists('CPP_AI_Admin_Assistant')) {
        require_once CPP_PLUGIN_DIR . 'includes/class-cpp-ai-admin-assistant.php';
    }
    
    $assistant = new CPP_AI_Admin_Assistant();
    
    // Get WordPress context
    $context = cpp_get_wordpress_context();
    
    // Send to OnlyMatt API
    $response = $assistant->chat($message, $context, $conversation_id);
    
    if (is_wp_error($response)) {
        return new WP_REST_Response([
            'success' => false,
            'message' => $response->get_error_message(),
        ], 500);
    }
    
    // Log analytics
    if (class_exists('CPP_Analytics')) {
        $analytics = new CPP_Analytics();
        $analytics->log_event('ai_chat_request', 'ai', 'chat', [
            'message_length' => strlen($message),
            'has_context' => !empty($context),
        ]);
    }
    
    return new WP_REST_Response([
        'success' => true,
        'response' => $response['response'],
        'conversation_id' => $response['conversation_id'],
        'tokens_used' => $response['tokens_used'] ?? null,
    ], 200);
}

/**
 * Handle AI quick question
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response
 */
function cpp_ai_ask_handler($request) {
    $question = sanitize_text_field($request->get_param('question'));
    
    if (empty($question)) {
        return new WP_REST_Response([
            'success' => false,
            'message' => __(__('Question is required.', 'content-protect-pro'), 'content-protect-pro'),
        ], 400);
    }
    
    if (!get_option('cpp_ai_assistant_enabled')) {
        return new WP_REST_Response([
            'success' => false,
            'message' => __(__('AI Assistant is disabled.', 'content-protect-pro'), 'content-protect-pro'),
        ], 403);
    }
    
    if (!class_exists('CPP_AI_Admin_Assistant')) {
        require_once CPP_PLUGIN_DIR . 'includes/class-cpp-ai-admin-assistant.php';
    }
    
    $assistant = new CPP_AI_Admin_Assistant();
    
    // Quick ask (no context, single-shot)
    $response = $assistant->ask($question);
    
    if (is_wp_error($response)) {
        return new WP_REST_Response([
            'success' => false,
            'message' => $response->get_error_message(),
        ], 500);
    }
    
    return new WP_REST_Response([
        'success' => true,
        'answer' => $response['response'],
    ], 200);
}

/**
 * Handle AI suggestions request
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response
 */
function cpp_ai_suggest_handler($request) {
    $context = sanitize_text_field($request->get_param('context'));
    
    if (empty($context)) {
        return new WP_REST_Response([
            'success' => false,
            'message' => __(__('Context is required.', 'content-protect-pro'), 'content-protect-pro'),
        ], 400);
    }
    
    if (!get_option('cpp_ai_assistant_enabled')) {
        return new WP_REST_Response([
            'success' => false,
            'message' => __(__('AI Assistant is disabled.', 'content-protect-pro'), 'content-protect-pro'),
        ], 403);
    }
    
    if (!class_exists('CPP_AI_Admin_Assistant')) {
        require_once CPP_PLUGIN_DIR . 'includes/class-cpp-ai-admin-assistant.php';
    }
    
    $assistant = new CPP_AI_Admin_Assistant();
    
    // Get contextual suggestions
    $suggestions = $assistant->get_suggestions($context);
    
    if (is_wp_error($suggestions)) {
        return new WP_REST_Response([
            'success' => false,
            'message' => $suggestions->get_error_message(),
        ], 500);
    }
    
    return new WP_REST_Response([
        'success' => true,
        'suggestions' => $suggestions,
    ], 200);
}

/**
 * Test OnlyMatt API connection
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response
 */
function cpp_ai_test_connection_handler($request) {
    if (!get_option('cpp_ai_assistant_enabled')) {
        return new WP_REST_Response([
            'success' => false,
            'message' => __(__('AI Assistant is disabled.', 'content-protect-pro'), 'content-protect-pro'),
        ], 403);
    }
    
    // Get encrypted API key
    $api_key_encrypted = get_option('cpp_onlymatt_api_key');
    
    if (empty($api_key_encrypted)) {
        return new WP_REST_Response([
            'success' => false,
            'message' => __(__('API key not configured.', 'content-protect-pro'), 'content-protect-pro'),
        ], 400);
    }
    
    // Decrypt API key
    if (!class_exists('CPP_Encryption')) {
        require_once CPP_PLUGIN_DIR . 'includes/class-cpp-encryption.php';
    }
    
    $api_key = CPP_Encryption::decrypt($api_key_encrypted);
    
    if (empty($api_key)) {
        return new WP_REST_Response([
            'success' => false,
            'message' => __(__('Failed to decrypt API key.', 'content-protect-pro'), 'content-protect-pro'),
        ], 500);
    }
    
    // Test API connection
    $response = wp_remote_post('https://onlymatt.com/api/v1/test', [
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json',
        ],
        'body' => wp_json_encode(['test' => true]),
        'timeout' => 10,
    ]);
    
    if (is_wp_error($response)) {
        return new WP_REST_Response([
            'success' => false,
            'message' => $response->get_error_message(),
        ], 500);
    }
    
    $status_code = wp_remote_retrieve_response_code($response);
    
    if ($status_code === 200) {
        return new WP_REST_Response([
            'success' => true,
            'message' => __('Connection successful!', 'content-protect-pro'),
        ], 200);
    }
    
    $body = wp_remote_retrieve_body($response);
    
    return new WP_REST_Response([
        'success' => false,
        'message' => sprintf(__('API returned status %d: %s', 'content-protect-pro'), $status_code, $body),
    ], 500);
}

/**
 * Get WordPress context for AI
 *
 * @return array Context data
 */
function cpp_get_wordpress_context() {
    $context = [];
    
    // Active theme
    $theme = wp_get_theme();
    $context['theme'] = [
        'name' => $theme->get('Name'),
        'version' => $theme->get('Version'),
    ];
    
    // Active plugins
    $active_plugins = get_option('active_plugins');
    $context['plugins'] = [];
    
    foreach ($active_plugins as $plugin) {
        $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin);
        $context['plugins'][] = [
            'name' => $plugin_data['Name'],
            'version' => $plugin_data['Version'],
        ];
    }
    
    // WordPress version
    $context['wordpress_version'] = get_bloginfo('version');
    
    // CPP statistics
    if (class_exists('CPP_Analytics')) {
        $analytics = new CPP_Analytics();
        $stats = $analytics->get_dashboard_stats();
        
        $context['cpp_stats'] = [
            'active_sessions' => $stats['active_sessions'],
            'total_videos' => $stats['total_videos'],
            'codes_redeemed_week' => $stats['codes_redeemed_week'],
        ];
    }
    
    // Current screen (if admin)
    if (is_admin()) {
        $screen = get_current_screen();
        $context['current_screen'] = $screen ? $screen->id : null;
    }
    
    return $context;
}