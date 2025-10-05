<?php
/**
 * Gift Code Security
 * 
 * Handles timing-safe comparisons, rate limiting, and IP validation.
 * Following copilot-instructions.md security patterns.
 *
 * @package Content_Protect_Pro
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class CPP_Giftcode_Security {
    
    /**
     * Rate limit settings
     *
     * @var array
     */
    private $rate_limits;
    
    /**
     * Constructor
     */
    public function __construct() {
        $security_settings = get_option('cpp_security_settings', []);
        
        $this->rate_limits = [
            'enabled' => $security_settings['rate_limit_enabled'] ?? true,
            'max_attempts' => $security_settings['rate_limit_attempts'] ?? 5,
            'time_window' => $security_settings['rate_limit_window'] ?? 300, // 5 minutes
        ];
    }
    
    /**
     * Timing-safe token comparison
     * CRITICAL: Prevents timing attacks on token validation
     *
     * @param string $expected_token Expected token
     * @param string $provided_token User-provided token
     * @return bool Tokens match
     */
    public function compare_tokens($expected_token, $provided_token) {
        if (empty($expected_token) || empty($provided_token)) {
            return false;
        }
        
        // Use hash_equals for timing-safe comparison
        return hash_equals(
            (string) $expected_token,
            (string) $provided_token
        );
    }
    
    /**
     * Check rate limiting for IP address
     * Per copilot-instructions: ALWAYS include rate limiting checks
     *
     * @param string $client_ip Client IP address
     * @param string $action_type Action type (e.g., 'redeem_code', 'request_playback')
     * @return bool Within rate limit
     */
    public function check_rate_limit($client_ip, $action_type = 'redeem_code') {
        if (!$this->rate_limits['enabled']) {
            return true;
        }
        
        global $wpdb;
        
        $time_window = time() - $this->rate_limits['time_window'];
        
        // Count recent attempts from this IP
        $attempt_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}cpp_rate_limits 
             WHERE client_ip = %s 
             AND action_type = %s 
             AND attempted_at > %s",
            sanitize_text_field($client_ip),
            sanitize_text_field($action_type),
            gmdate('Y-m-d H:i:s', $time_window)
        ));
        
        if ($attempt_count >= $this->rate_limits['max_attempts']) {
            // Log rate limit hit
            if (class_exists('CPP_Analytics')) {
                $analytics = new CPP_Analytics();
                $analytics->log_event('rate_limit_exceeded', 'security', $client_ip, [
                    'action_type' => $action_type,
                    'attempt_count' => $attempt_count,
                ]);
            }
            
            return false;
        }
        
        // Record this attempt
        $this->record_attempt($client_ip, $action_type);
        
        return true;
    }
    
    /**
     * Record rate limit attempt
     *
     * @param string $client_ip Client IP
     * @param string $action_type Action type
     */
    private function record_attempt($client_ip, $action_type) {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'cpp_rate_limits',
            [
                'client_ip' => sanitize_text_field($client_ip),
                'action_type' => sanitize_text_field($action_type),
                'attempted_at' => current_time('mysql'),
            ],
            ['%s', '%s', '%s']
        );
    }
    
    /**
     * Validate session IP binding
     * Checks if session IP matches current request IP
     *
     * @param int $session_id Session ID
     * @param string $current_ip Current request IP
     * @return bool IP validation passed
     */
    public function validate_session_ip($session_id, $current_ip) {
        $security_settings = get_option('cpp_security_settings', []);
        
        // Check if IP validation is enabled
        if (empty($security_settings['ip_validation_enabled'])) {
            return true;
        }
        
        global $wpdb;
        
        $stored_ip = $wpdb->get_var($wpdb->prepare(
            "SELECT client_ip FROM {$wpdb->prefix}cpp_sessions 
             WHERE session_id = %d LIMIT 1",
            absint($session_id)
        ));
        
        if (empty($stored_ip)) {
            return false;
        }
        
        // Compare IPs (timing-safe)
        return $this->compare_tokens($stored_ip, $current_ip);
    }
    
    /**
     * Generate cryptographically secure random token
     *
     * @param int $length Token length
     * @return string Random token
     */
    public function generate_secure_token($length = 32) {
        if (!class_exists('CPP_Encryption')) {
            require_once CPP_PLUGIN_DIR . 'includes/class-cpp-encryption.php';
        }
        
        return CPP_Encryption::generate_token($length);
    }
    
    /**
     * Sanitize and validate IP address
     *
     * @param string $ip IP address
     * @return string|false Validated IP or false
     */
    public function sanitize_ip($ip) {
        // Remove any whitespace
        $ip = trim($ip);
        
        // Validate IPv4
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return $ip;
        }
        
        // Validate IPv6
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return $ip;
        }
        
        return false;
    }
    
    /**
     * Get client IP address from request
     * Handles proxies and load balancers
     *
     * @return string Client IP
     */
    public function get_client_ip() {
        $ip = '';
        
        // Check for proxy headers (in order of preference)
        $headers = [
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_REAL_IP',        // Nginx proxy
            'HTTP_X_FORWARDED_FOR',  // Standard proxy header
            'REMOTE_ADDR',           // Direct connection
        ];
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                // X-Forwarded-For can contain multiple IPs
                if ($header === 'HTTP_X_FORWARDED_FOR') {
                    $ips = explode(',', $_SERVER[$header]);
                    $ip = trim($ips[0]);
                } else {
                    $ip = $_SERVER[$header];
                }
                
                // Validate and return first valid IP found
                $validated_ip = $this->sanitize_ip($ip);
                if ($validated_ip !== false) {
                    return $validated_ip;
                }
            }
        }
        
        // Fallback to 0.0.0.0 if no valid IP found
        return '0.0.0.0';
    }
    
    /**
     * Check if IP is blocked
     * Integrates with WordPress blacklist
     *
     * @param string $ip IP address
     * @return bool IP is blocked
     */
    public function is_ip_blocked($ip) {
        // Check WordPress comment blacklist
        $blacklist = get_option('disallowed_keys');
        
        if (empty($blacklist)) {
            return false;
        }
        
        $blacklist_array = explode("\n", $blacklist);
        
        foreach ($blacklist_array as $blocked_item) {
            $blocked_item = trim($blocked_item);
            if (empty($blocked_item)) {
                continue;
            }
            
            if (stripos($ip, $blocked_item) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Cleanup old rate limit records
     * Called by daily cron job
     *
     * @param int $days Keep records newer than X days
     * @return int Rows deleted
     */
    public function cleanup_rate_limits($days = 7) {
        global $wpdb;
        
        $date_threshold = gmdate('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $result = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}cpp_rate_limits 
             WHERE attempted_at < %s",
            $date_threshold
        ));
        
        return (int) $result;
    }
    
    /**
     * Validate nonce and rate limit in one call
     * Convenience method for AJAX endpoints
     *
     * @param string $nonce_action Nonce action name
     * @param string $nonce_value Nonce value
     * @param string $rate_limit_action Rate limit action type
     * @return array ['valid' => bool, 'message' => string]
     */
    public function validate_request($nonce_action, $nonce_value, $rate_limit_action = 'api_request') {
        // Check nonce (CSRF protection)
        if (!wp_verify_nonce($nonce_value, $nonce_action)) {
            return [
                'valid' => false,
                'message' => __('Security check failed. Please refresh and try again.', 'content-protect-pro'),
            ];
        }
        
        // Get client IP
        $client_ip = $this->get_client_ip();
        
        // Check if IP is blocked
        if ($this->is_ip_blocked($client_ip)) {
            return [
                'valid' => false,
                'message' => __(__('Access denied.', 'content-protect-pro'), 'content-protect-pro'),
            ];
        }
        
        // Check rate limiting
        if (!$this->check_rate_limit($client_ip, $rate_limit_action)) {
            return [
                'valid' => false,
                'message' => __('Too many attempts. Please wait a few minutes and try again.', 'content-protect-pro'),
            ];
        }
        
        return [
            'valid' => true,
            'message' => '',
        ];
    }
}