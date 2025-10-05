<?php
/**
 * AI Integration Settings Handler
 *
 * Manages OnlyMatt Gateway API key and AI assistant configuration.
 *
 * @package ContentProtectPro
 * @since   3.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('add_action')) {
    require_once __DIR__ . '/wp-stubs.php';
}

class CPP_Settings_AI {

    private const OPTION_GROUP = 'ai_onlymatt_settings';

    /**
     * Register WordPress hooks
     */
    public static function init(): void {
        add_action('admin_init', [__CLASS__, 'register_settings']);
        add_action('wp_ajax_cpp_test_gateway_connection', [__CLASS__, 'handle_test_connection']);
    }

    /**
     * Register settings with WordPress Settings API
     */
    public static function register_settings(): void {
        register_setting(
            self::OPTION_GROUP,
            'cpp_ai_assistant_enabled',
            [
                'type' => 'boolean',
                'description' => __('Enable AI-powered admin assistant', 'content-protect-pro'),
                'sanitize_callback' => 'rest_sanitize_boolean',
                'default' => true,
            ]
        );

        register_setting(
            self::OPTION_GROUP,
            'cpp_onlymatt_api_key',
            [
                'type' => 'string',
                'description' => __('OnlyMatt Gateway API Key', 'content-protect-pro'),
                'sanitize_callback' => [__CLASS__, 'sanitize_api_key'],
                'default' => '',
            ]
        );
    }

    public static function get_option_group(): string {
        return self::OPTION_GROUP;
    }

    /**
     * Sanitize and encrypt API key before saving
     */
    public static function sanitize_api_key($input): string {
        if (empty($input)) {
            return '';
        }

        $input = sanitize_text_field($input);

        if (!preg_match('/^om-[a-zA-Z0-9]{32,}$/', $input)) {
            add_settings_error(
                'cpp_onlymatt_api_key',
                'invalid_api_key',
                __('Invalid API key format. Should start with "om-" followed by 32+ alphanumeric characters.', 'content-protect-pro'),
                'error'
            );

            return get_option('cpp_onlymatt_api_key', '');
        }

        if (class_exists('CPP_Encryption')) {
            try {
                $encrypted = CPP_Encryption::encrypt($input);
                $test_result = self::test_gateway_connection($input);

                if (!$test_result['success']) {
                    add_settings_error(
                        'cpp_onlymatt_api_key',
                        'connection_failed',
                        sprintf(
                            __('API key saved but connection test failed: %s', 'content-protect-pro'),
                            $test_result['error']
                        ),
                        'warning'
                    );
                } else {
                    add_settings_error(
                        'cpp_onlymatt_api_key',
                        'api_key_saved',
                        __('API key saved and verified successfully!', 'content-protect-pro'),
                        'success'
                    );
                }

                return $encrypted;
            } catch (Exception $e) {
                add_settings_error(
                    'cpp_onlymatt_api_key',
                    'encryption_failed',
                    __('Failed to encrypt API key. Please try again.', 'content-protect-pro'),
                    'error'
                );

                return get_option('cpp_onlymatt_api_key', '');
            }
        }

        return $input;
    }

    /**
     * Test connection to OnlyMatt Gateway
     */
    public static function test_gateway_connection($api_key = null): array {
        if (empty($api_key)) {
            $api_key = get_option('cpp_onlymatt_api_key', '');

            if (class_exists('CPP_Encryption') && !empty($api_key)) {
                try {
                    $api_key = CPP_Encryption::decrypt($api_key);
                } catch (Exception $e) {
                    return [
                        'success' => false,
                        'error' => __('Failed to decrypt stored API key', 'content-protect-pro'),
                    ];
                }
            }
        }

        if (empty($api_key)) {
            return [
                'success' => false,
                'error' => __('No API key configured', 'content-protect-pro'),
            ];
        }

        if (!function_exists('wp_remote_post') || !function_exists('wp_json_encode')) {
            return [
                'success' => false,
                'error' => __('WordPress HTTP API unavailable', 'content-protect-pro'),
            ];
        }

        $response = wp_remote_post('https://api.onlymatt.ca/chat', [
            'headers' => [
                'Content-Type' => 'application/json',
                'X-OM-KEY' => $api_key,
            ],
            'body' => wp_json_encode([
                'session' => 'cpp_connection_test_' . time(),
                'provider' => 'ollama',
                'model' => 'onlymatt',
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => 'Connection test',
                    ],
                ],
            ]),
            'timeout' => 10,
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => $response->get_error_message(),
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);

        if ($status_code === 401 || $status_code === 403) {
            return [
                'success' => false,
                'error' => __('Invalid API key', 'content-protect-pro'),
            ];
        }

        if ($status_code !== 200) {
            return [
                'success' => false,
                'error' => sprintf(
                    __('Gateway returned error code: %d', 'content-protect-pro'),
                    $status_code
                ),
            ];
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!isset($data['reply'])) {
            return [
                'success' => false,
                'error' => __('Invalid response from gateway', 'content-protect-pro'),
            ];
        }

        return [
            'success' => true,
            'model' => $data['model'] ?? 'onlymatt',
            'error' => null,
        ];
    }

    /**
     * Handle AJAX test connection request
     */
    public static function handle_test_connection(): void {
        if (!function_exists('check_ajax_referer') || !function_exists('wp_send_json_error') || !function_exists('wp_send_json_success')) {
            return;
        }

        if (!check_ajax_referer('cpp_test_gateway', 'nonce', false)) {
            wp_send_json_error([
                'message' => __('Security check failed', 'content-protect-pro'),
            ], 403);
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'message' => __('Unauthorized', 'content-protect-pro'),
            ], 403);
        }

        $raw_api_key = $_POST['api_key'] ?? '';
        $api_key = function_exists('wp_unslash') ? wp_unslash($raw_api_key) : $raw_api_key;
        $api_key = sanitize_text_field($api_key);

        if (empty($api_key)) {
            wp_send_json_error([
                'message' => __('No API key provided', 'content-protect-pro'),
            ], 400);
        }

        $result = self::test_gateway_connection($api_key);

        if ($result['success']) {
            wp_send_json_success([
                'model' => $result['model'],
                'message' => __('Connection successful', 'content-protect-pro'),
            ]);
        }

        wp_send_json_error([
            'message' => $result['error'],
        ], 500);
    }

    /**
     * Get decrypted API key
     */
    public static function get_api_key(): ?string {
        $encrypted_key = get_option('cpp_onlymatt_api_key', '');

        if (empty($encrypted_key)) {
            return null;
        }

        if (class_exists('CPP_Encryption')) {
            try {
                return CPP_Encryption::decrypt($encrypted_key);
            } catch (Exception $e) {
                error_log('CPP: Failed to decrypt API key - ' . $e->getMessage());

                return null;
            }
        }

        return $encrypted_key;
    }
}