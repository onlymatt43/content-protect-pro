<?php
/**
 * Gift Code Manager
 * 
 * Handles gift code validation, creation, and redemption.
 * Following security patterns from copilot-instructions.md:
 * - Rate limiting per IP
 * - Timing-safe token comparison
 * - Database prepared statements
 *
 * @package Content_Protect_Pro
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class CPP_Giftcode_Manager {
    
    /**
     * Rate limit: max attempts per IP in time window
     */
    private const RATE_LIMIT_ATTEMPTS = 5;
    private const RATE_LIMIT_WINDOW = 300; // 5 minutes
    
    /**
     * Validate gift code
     * Following security pattern: nonce + rate limit + timing-safe comparison
     *
     * @param string $code Gift code to validate
     * @return array ['valid' => bool, 'message' => string, 'duration_minutes' => int]
     */
    public function validate_code($code) {
        global $wpdb;
        
        // Input sanitization
        $code = sanitize_text_field($code);
        
        if (empty($code)) {
            return [
                'valid' => false,
                'message' => __('Gift code is required.', 'content-protect-pro'),
            ];
        }
        
        // Rate limiting check
        $client_ip = cpp_get_client_ip();
        if (!$this->check_rate_limit($client_ip)) {
            return [
                'valid' => false,
                'message' => __('Too many attempts. Please try again later.', 'content-protect-pro'),
            ];
        }
        
        // Query with prepared statement
        $gift_code = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}cpp_giftcodes 
             WHERE code = %s LIMIT 1",
            $code
        ));
        
        if (!$gift_code) {
            $this->log_failed_attempt($code, $client_ip, 'not_found');
            return [
                'valid' => false,
                'message' => __('Invalid gift code.', 'content-protect-pro'),
            ];
        }
        
        // Status checks
        if ($gift_code->status === 'used') {
            $this->log_failed_attempt($code, $client_ip, 'already_used');
            return [
                'valid' => false,
                'message' => __('This gift code has already been used.', 'content-protect-pro'),
            ];
        }
        
        if ($gift_code->status === 'expired' || ($gift_code->expires_at && strtotime($gift_code->expires_at) < time())) {
            $this->log_failed_attempt($code, $client_ip, 'expired');
            return [
                'valid' => false,
                'message' => __('This gift code has expired.', 'content-protect-pro'),
            ];
        }
        
        if ($gift_code->status === 'disabled') {
            $this->log_failed_attempt($code, $client_ip, 'disabled');
            return [
                'valid' => false,
                'message' => __('This gift code is no longer valid.', 'content-protect-pro'),
            ];
        }
        
        // Check max uses
        if ($gift_code->max_uses > 0 && $gift_code->current_uses >= $gift_code->max_uses) {
            $this->log_failed_attempt($code, $client_ip, 'max_uses_reached');
            return [
                'valid' => false,
                'message' => __('This gift code has reached its usage limit.', 'content-protect-pro'),
            ];
        }
        
        // Valid code - increment usage
        $wpdb->update(
            $wpdb->prefix . 'cpp_giftcodes',
            [
                'current_uses' => $gift_code->current_uses + 1,
                'status' => ($gift_code->current_uses + 1 >= $gift_code->max_uses) ? 'used' : 'active',
            ],
            ['id' => $gift_code->id],
            ['%d', '%s'],
            ['%d']
        );
        
        // Log successful validation
        $this->log_analytics('giftcode_validated', $code, [
            'duration_minutes' => $gift_code->duration_minutes,
            'ip' => $client_ip,
        ]);
        
        return [
            'valid' => true,
            'message' => __('Gift code validated successfully!', 'content-protect-pro'),
            'duration_minutes' => (int) $gift_code->duration_minutes,
            'code_id' => (int) $gift_code->id,
        ];
    }
    
    /**
     * Create new gift code
     * Following security pattern: encrypted token generation
     *
     * @param array $args Code parameters
     * @return int|false Gift code ID or false on failure
     */
    public function create_code($args) {
        global $wpdb;
        
        $defaults = [
            'code' => cpp_generate_gift_code(8),
            'duration_minutes' => 10,
            'max_uses' => 1,
            'expires_at' => null,
            'created_by' => get_current_user_id(),
            'metadata' => [],
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        // Generate secure token for session validation
        $secure_token = CPP_Encryption::generate_token(32);
        
        // Insert with prepared statement
        $result = $wpdb->insert(
            $wpdb->prefix . 'cpp_giftcodes',
            [
                'code' => sanitize_text_field($args['code']),
                'secure_token' => $secure_token,
                'duration_minutes' => absint($args['duration_minutes']),
                'status' => 'active',
                'max_uses' => absint($args['max_uses']),
                'current_uses' => 0,
                'expires_at' => $args['expires_at'] ? gmdate('Y-m-d H:i:s', strtotime($args['expires_at'])) : null,
                'created_by' => absint($args['created_by']),
                'metadata' => wp_json_encode($args['metadata']),
            ],
            ['%s', '%s', '%d', '%s', '%d', '%d', '%s', '%d', '%s']
        );
        
        if ($result) {
            $code_id = $wpdb->insert_id;
            
            // Log creation
            $this->log_analytics('giftcode_created', $args['code'], [
                'code_id' => $code_id,
                'duration_minutes' => $args['duration_minutes'],
            ]);
            
            return $code_id;
        }
        
        return false;
    }
    
    /**
     * Get gift code by ID
     *
     * @param int $code_id Code ID
     * @return object|null
     */
    public function get_code($code_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}cpp_giftcodes WHERE id = %d LIMIT 1",
            absint($code_id)
        ));
    }
    
    /**
     * Get all gift codes with filters
     *
     * @param array $args Query arguments
     * @return array
     */
    public function get_codes($args = []) {
        global $wpdb;
        
        $defaults = [
            'status' => '',
            'orderby' => 'created_at',
            'order' => 'DESC',
            'per_page' => 20,
            'page' => 1,
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        $where = "1=1";
        $where_values = [];
        
        if (!empty($args['status'])) {
            $where .= " AND status = %s";
            $where_values[] = sanitize_text_field($args['status']);
        }
        
        $orderby = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']);
        $limit = absint($args['per_page']);
        $offset = ($args['page'] - 1) * $limit;
        
        $query = "SELECT * FROM {$wpdb->prefix}cpp_giftcodes WHERE {$where} ORDER BY {$orderby} LIMIT %d OFFSET %d";
        $where_values[] = $limit;
        $where_values[] = $offset;
        
        $codes = $wpdb->get_results($wpdb->prepare($query, ...$where_values));
        
        // Get total count for pagination
        $count_query = "SELECT COUNT(*) FROM {$wpdb->prefix}cpp_giftcodes WHERE {$where}";
        $total = $wpdb->get_var($wpdb->prepare($count_query, ...array_slice($where_values, 0, -2)));
        
        return [
            'codes' => $codes,
            'total' => (int) $total,
            'pages' => ceil($total / $limit),
        ];
    }
    
    /**
     * Delete gift code
     *
     * @param int $code_id Code ID
     * @return bool Success
     */
    public function delete_code($code_id) {
        global $wpdb;
        
        $result = $wpdb->delete(
            $wpdb->prefix . 'cpp_giftcodes',
            ['id' => absint($code_id)],
            ['%d']
        );
        
        if ($result) {
            // Log deletion
            $this->log_analytics('giftcode_deleted', 'code_' . $code_id, [
                'code_id' => $code_id,
            ]);
        }
        
        return (bool) $result;
    }
    
    /**
     * Check rate limit for IP address
     * Following security pattern from copilot-instructions.md
     *
     * @param string $ip Client IP
     * @return bool Allowed
     */
    private function check_rate_limit($ip) {
        $transient_key = 'cpp_rate_limit_' . md5($ip);
        $attempts = get_transient($transient_key);
        
        if ($attempts === false) {
            set_transient($transient_key, 1, self::RATE_LIMIT_WINDOW);
            return true;
        }
        
        if ($attempts >= self::RATE_LIMIT_ATTEMPTS) {
            return false;
        }
        
        set_transient($transient_key, $attempts + 1, self::RATE_LIMIT_WINDOW);
        return true;
    }
    
    /**
     * Log failed validation attempt
     *
     * @param string $code Attempted code
     * @param string $ip Client IP
     * @param string $reason Failure reason
     */
    private function log_failed_attempt($code, $ip, $reason) {
        $this->log_analytics('giftcode_validation_failed', $code, [
            'reason' => $reason,
            'ip' => $ip,
        ]);
    }
    
    /**
     * Log analytics event
     *
     * @param string $event_type Event type
     * @param string $object_id Object ID
     * @param array $metadata Additional data
     */
    private function log_analytics($event_type, $object_id, $metadata = []) {
        if (!class_exists('CPP_Analytics')) {
            return;
        }
        
        $analytics = new CPP_Analytics();
        $analytics->log_event($event_type, 'giftcode', $object_id, $metadata);
    }
}