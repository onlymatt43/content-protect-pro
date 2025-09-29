<?php
/**
 * Gift code security and validation functionality
 *
 * @package Content_Protect_Pro
 * @since   1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class CPP_Giftcode_Security {

    /**
     * Initialize security features
     *
     * @since 1.0.0
     */
    public function __construct() {
        add_action('init', array($this, 'init'));
    }

    /**
     * Initialize security hooks
     *
     * @since 1.0.0
     */
    public function init() {
        // Rate limiting for gift code validation
        add_action('wp_ajax_cpp_validate_giftcode', array($this, 'check_rate_limit'), 5);
        add_action('wp_ajax_nopriv_cpp_validate_giftcode', array($this, 'check_rate_limit'), 5);
        
        // Security logging
        add_action('cpp_giftcode_validated', array($this, 'log_validation'), 10, 2);
        add_action('cpp_giftcode_validation_failed', array($this, 'log_failed_validation'), 10, 2);
    }

    /**
     * Check rate limiting for gift code validation attempts
     *
     * @since 1.0.0
     */
    public function check_rate_limit() {
        $settings = get_option('cpp_security_settings', array());
        
        if (empty($settings['enable_rate_limiting'])) {
            return;
        }

        $ip = $this->get_client_ip();
        $rate_limit = isset($settings['rate_limit_requests']) ? intval($settings['rate_limit_requests']) : 100;
        $time_window = isset($settings['rate_limit_window']) ? intval($settings['rate_limit_window']) : 3600;
        
        $transient_key = 'cpp_rate_limit_' . md5($ip);
        $attempts = get_transient($transient_key);
        
        if ($attempts === false) {
            $attempts = 0;
        }
        
        if ($attempts >= $rate_limit) {
            $this->log_security_event('rate_limit_exceeded', $ip);
            wp_send_json_error(array(
                'message' => __('Too many attempts. Please try again later.', 'content-protect-pro')
            ));
        }
        
        // Increment attempt counter
        set_transient($transient_key, $attempts + 1, $time_window);
    }

    /**
     * Log successful gift code validation
     *
     * @param string $code Gift code
     * @param array  $data Validation data
     * @since 1.0.0
     */
    public function log_validation($code, $data) {
        $this->log_security_event('giftcode_validated', $this->get_client_ip(), array(
            'code' => $code,
            'user_id' => get_current_user_id(),
            'value' => isset($data['value']) ? $data['value'] : 0,
        ));
    }

    /**
     * Log failed gift code validation attempts
     *
     * @param string $code Gift code
     * @param string $reason Failure reason
     * @since 1.0.0
     */
    public function log_failed_validation($code, $reason) {
        $this->log_security_event('giftcode_validation_failed', $this->get_client_ip(), array(
            'code' => $code,
            'reason' => $reason,
            'user_id' => get_current_user_id(),
        ));
    }

    /**
     * Validate gift code format and security
     *
     * @param string $code Gift code to validate
     * @return array Validation result
     * @since 1.0.0
     */
    public function validate_code_security($code) {
        // Basic format validation
        if (empty($code)) {
            return array(
                'valid' => false,
                'message' => __('Gift code cannot be empty.', 'content-protect-pro')
            );
        }

        // Length validation
        if (strlen($code) < 4) {
            return array(
                'valid' => false,
                'message' => __('Gift code must be at least 4 characters long.', 'content-protect-pro')
            );
        }

        // Character validation (alphanumeric only)
        if (!preg_match('/^[A-Za-z0-9]+$/', $code)) {
            return array(
                'valid' => false,
                'message' => __('Gift code can only contain letters and numbers.', 'content-protect-pro')
            );
        }

        // Check for suspicious patterns
        if ($this->is_suspicious_code($code)) {
            $this->log_security_event('suspicious_giftcode_attempt', $this->get_client_ip(), array(
                'code' => $code,
                'user_id' => get_current_user_id(),
            ));
            
            return array(
                'valid' => false,
                'message' => __('Invalid gift code format.', 'content-protect-pro')
            );
        }

        return array('valid' => true);
    }

    /**
     * Check if gift code matches suspicious patterns
     *
     * @param string $code Gift code
     * @return bool True if suspicious
     * @since 1.0.0
     */
    private function is_suspicious_code($code) {
        $suspicious_patterns = array(
            '/^(admin|test|demo|sample)$/i',
            '/^(1234|0000|9999|abcd)$/i',
            '/^(.)\1{3,}$/', // Repeated characters (aaaa, 1111, etc.)
            '/(drop|select|union|insert|delete|script)/i', // SQL injection attempts
        );

        foreach ($suspicious_patterns as $pattern) {
            if (preg_match($pattern, $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate secure random gift code
     *
     * @param int    $length Code length
     * @param string $prefix Optional prefix
     * @param string $suffix Optional suffix
     * @return string Generated code
     * @since 1.0.0
     */
    public function generate_secure_code($length = 8, $prefix = '', $suffix = '') {
        // Use cryptographically secure random generation
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $code = '';
        
        // Remove potentially confusing characters
        $characters = str_replace(array('0', 'O', '1', 'I'), '', $characters);
        
        for ($i = 0; $i < $length; $i++) {
            $code .= $characters[random_int(0, strlen($characters) - 1)];
        }
        
        // Ensure code doesn't match suspicious patterns
        if ($this->is_suspicious_code($code)) {
            return $this->generate_secure_code($length, $prefix, $suffix); // Regenerate
        }
        
        return $prefix . $code . $suffix;
    }

    /**
     * Check for brute force attacks
     *
     * @param string $ip IP address
     * @return bool True if blocked
     * @since 1.0.0
     */
    public function is_brute_force_attack($ip) {
        $transient_key = 'cpp_failed_attempts_' . md5($ip);
        $failed_attempts = get_transient($transient_key);
        
        if ($failed_attempts && $failed_attempts >= 10) {
            return true;
        }
        
        return false;
    }

    /**
     * Record failed attempt for brute force detection
     *
     * @param string $ip IP address
     * @since 1.0.0
     */
    public function record_failed_attempt($ip) {
        $transient_key = 'cpp_failed_attempts_' . md5($ip);
        $failed_attempts = get_transient($transient_key);
        
        if ($failed_attempts === false) {
            $failed_attempts = 0;
        }
        
        $failed_attempts++;
        set_transient($transient_key, $failed_attempts, HOUR_IN_SECONDS);
        
        // Log potential attack
        if ($failed_attempts >= 5) {
            $this->log_security_event('potential_brute_force', $ip, array(
                'failed_attempts' => $failed_attempts,
            ));
        }
    }

    /**
     * Clear failed attempts for IP (after successful validation)
     *
     * @param string $ip IP address
     * @since 1.0.0
     */
    public function clear_failed_attempts($ip) {
        $transient_key = 'cpp_failed_attempts_' . md5($ip);
        delete_transient($transient_key);
    }

    /**
     * Get client IP address
     *
     * @return string IP address
     * @since 1.0.0
     */
    private function get_client_ip() {
        $ip_keys = array('HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
    }

    /**
     * Log security events
     *
     * @param string $event_type Event type
     * @param string $ip         IP address
     * @param array  $metadata   Additional data
     * @since 1.0.0
     */
    private function log_security_event($event_type, $ip, $metadata = array()) {
        $settings = get_option('cpp_security_settings', array());
        
        if (empty($settings['enable_logging'])) {
            return;
        }

        // Use analytics class for logging
        if (class_exists('CPP_Analytics')) {
            $analytics = new CPP_Analytics();
            $analytics->log_event($event_type, 'security', $ip, array_merge($metadata, array(
                'timestamp' => current_time('mysql'),
                'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '',
            )));
        }
    }

    /**
     * Get security statistics
     *
     * @param array $params Query parameters
     * @return array Security statistics
     * @since 1.0.0
     */
    public function get_security_stats($params = array()) {
        $defaults = array(
            'date_from' => date('Y-m-d', strtotime('-30 days')),
            'date_to' => date('Y-m-d'),
        );
        
        $params = wp_parse_args($params, $defaults);
        
        if (class_exists('CPP_Analytics')) {
            $analytics = new CPP_Analytics();
            
            // Get security-related events
            $security_events = $analytics->get_analytics(array(
                'object_type' => 'security',
                'date_from' => $params['date_from'],
                'date_to' => $params['date_to'],
            ));
            
            $stats = array(
                'total_attempts' => 0,
                'failed_attempts' => 0,
                'rate_limit_hits' => 0,
                'brute_force_attempts' => 0,
                'suspicious_codes' => 0,
            );
            
            foreach ($security_events['events'] as $event) {
                switch ($event->event_type) {
                    case 'giftcode_validation_failed':
                        $stats['failed_attempts']++;
                        break;
                    case 'rate_limit_exceeded':
                        $stats['rate_limit_hits']++;
                        break;
                    case 'potential_brute_force':
                        $stats['brute_force_attempts']++;
                        break;
                    case 'suspicious_giftcode_attempt':
                        $stats['suspicious_codes']++;
                        break;
                }
                $stats['total_attempts']++;
            }
            
            return $stats;
        }
        
        return array();
    }
}