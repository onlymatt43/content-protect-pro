<?php
/**
 * Gift code management functionality
 *
 * @package Content_Protect_Pro
 * @since   1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class CPP_Giftcode_Manager {

    /**
     * Validate a gift code with CSRF protection
     *
     * @param string $code The gift code to validate
     * @param string $nonce CSRF nonce (optional for backward compatibility)
     * @return array Validation result
     * @since 1.0.0
     */
    public function validate_code($code, $nonce = '') {
        global $wpdb;
        
        // CSRF Protection - verify nonce if provided
        if (!empty($nonce) && defined('DOING_AJAX') && DOING_AJAX) {
            if (!wp_verify_nonce($nonce, 'cpp_validate_code')) {
                return array(
                    'valid' => false,
                    'message' => 'Security check failed. Please refresh and try again.'
                );
            }
        }
        
        // Rate limiting check
        $client_ip = cpp_get_client_ip();
        if (!$this->check_rate_limit($client_ip)) {
            return array(
                'valid' => false,
                'message' => 'Too many attempts. Please wait before trying again.'
            );
        }
        
        $settings = get_option('cpp_giftcode_settings', array());
        $case_sensitive = isset($settings['case_sensitive']) ? $settings['case_sensitive'] : 0;
        
        // Normalize code based on case sensitivity setting
        if (!$case_sensitive) {
            $code = strtoupper($code);
        }
        
        $table_name = $wpdb->prefix . 'cpp_giftcodes';
        
        $query = $wpdb->prepare(
            "SELECT * FROM $table_name WHERE code = %s",
            $code
        );
        
        $giftcode = $wpdb->get_row($query);
        
        if (!$giftcode) {
            $this->log_event('giftcode_validation_failed', $code, 'invalid_code');
            return array(
                'valid' => false,
                'message' => __('Invalid gift code.', 'content-protect-pro')
            );
        }
        
        // Decrypt secure token if encrypted
        if (!empty($giftcode->secure_token) && class_exists('CPP_Encryption')) {
            $giftcode->secure_token = CPP_Encryption::decrypt($giftcode->secure_token);
        }
        
        // Check if expired
        if ($giftcode->expires_at && strtotime($giftcode->expires_at) < time()) {
            $this->log_event('giftcode_validation_failed', $code, 'expired');
            return array(
                'valid' => false,
                'message' => __('This gift code has expired.', 'content-protect-pro')
            );
        }
        
        // Check IP restrictions if any
        if (!empty($giftcode->ip_restrictions)) {
            $client_ip = cpp_get_client_ip();
            if (!cpp_validate_client_ip($client_ip, $giftcode->ip_restrictions)) {
                $this->log_event('giftcode_validation_failed', $code, 'ip_restricted');
                return array(
                    'valid' => false,
                    'message' => __('This gift code is not available from your location.', 'content-protect-pro')
                );
            }
        }
        
        // Check status
        if ($giftcode->status !== 'active') {
            $this->log_event('giftcode_validation_failed', $code, 'inactive');
            return array(
                'valid' => false,
                'message' => __('This gift code is not active.', 'content-protect-pro')
            );
        }
        
        // Create secure session for this client
        $session_result = cpp_create_session(
            $giftcode->code,
            $giftcode->duration_minutes,
            $giftcode->secure_token
        );
        
        if (!$session_result['success']) {
            $this->log_event('giftcode_validation_failed', $code, 'session_creation_failed');
            return array(
                'valid' => false,
                'message' => 'Failed to create session. Please try again.'
            );
        }
        
        $this->log_event('giftcode_validation_success', $code, 'validated');
        
        return array(
            'valid' => true,
            'message' => 'Access granted! Your session is now active.',
            'access_duration_minutes' => intval($giftcode->duration_minutes),
            'session_expires_at' => date('Y-m-d H:i:s', $session_result['expires_at']),
            'session_id' => $session_result['session_id'],
            'redirect_url' => ''
        );
    }

    /**
     * Create a new gift code
     *
     * @param array $data Gift code data
     * @return int|false Gift code ID on success, false on failure
     * @since 1.0.0
     */
    public function create_giftcode($data) {
        global $wpdb;
        
        $defaults = array(
            'code' => $this->generate_code(),
            'value' => 0.00,
            'status' => 'active',
            'usage_limit' => 1,
            'usage_count' => 0,
            'expires_at' => null,
        );
        
        $data = wp_parse_args($data, $defaults);
        
        // Validate required fields
        if (empty($data['code'])) {
            return false;
        }
        
        $table_name = $wpdb->prefix . 'cpp_giftcodes';
        
        $result = $wpdb->insert(
            $table_name,
            array(
                'code' => $data['code'],
                'value' => floatval($data['value']),
                'status' => $data['status'],
                'usage_limit' => intval($data['usage_limit']),
                'usage_count' => intval($data['usage_count']),
                'expires_at' => $data['expires_at'],
            ),
            array('%s', '%f', '%s', '%d', '%d', '%s')
        );
        
        if ($result) {
            $this->log_event('giftcode_created', $data['code'], 'created');
            return $wpdb->insert_id;
        }
        
        return false;
    }

    /**
     * Generate a random gift code
     *
     * @return string Generated gift code
     * @since 1.0.0
     */
    public function generate_code() {
        $settings = get_option('cpp_giftcode_settings', array());
        
        $length = isset($settings['code_length']) ? intval($settings['code_length']) : 8;
        $prefix = isset($settings['code_prefix']) ? $settings['code_prefix'] : '';
        $suffix = isset($settings['code_suffix']) ? $settings['code_suffix'] : '';
        
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $code = '';
        
        for ($i = 0; $i < $length; $i++) {
            $code .= $characters[wp_rand(0, strlen($characters) - 1)];
        }
        
        return $prefix . $code . $suffix;
    }

    /**
     * Get gift codes with pagination
     *
     * @param array $args Query arguments
     * @return array Gift codes and total count
     * @since 1.0.0
     */
    public function get_giftcodes($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'per_page' => 20,
            'page' => 1,
            'status' => '',
            'search' => '',
            'orderby' => 'created_at',
            'order' => 'DESC',
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $table_name = $wpdb->prefix . 'cpp_giftcodes';
        
        $where_clauses = array();
        $prepare_values = array();
        
        if (!empty($args['status'])) {
            $where_clauses[] = 'status = %s';
            $prepare_values[] = $args['status'];
        }
        
        if (!empty($args['search'])) {
            $where_clauses[] = 'code LIKE %s';
            $prepare_values[] = '%' . $wpdb->esc_like($args['search']) . '%';
        }
        
        $where_sql = '';
        if (!empty($where_clauses)) {
            $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
        }
        
        // Get total count
        $count_sql = "SELECT COUNT(*) FROM $table_name $where_sql";
        if (!empty($prepare_values)) {
            $count_sql = $wpdb->prepare($count_sql, $prepare_values);
        }
        $total = $wpdb->get_var($count_sql);
        
        // Get data
        $offset = ($args['page'] - 1) * $args['per_page'];
        $order_sql = sprintf('ORDER BY %s %s', esc_sql($args['orderby']), esc_sql($args['order']));
        $limit_sql = $wpdb->prepare('LIMIT %d, %d', $offset, $args['per_page']);
        
        $data_sql = "SELECT * FROM $table_name $where_sql $order_sql $limit_sql";
        if (!empty($prepare_values)) {
            $data_values = array_merge($prepare_values, array($offset, $args['per_page']));
            $data_sql = $wpdb->prepare(
                "SELECT * FROM $table_name $where_sql $order_sql LIMIT %d, %d",
                ...$data_values
            );
        }
        
        $giftcodes = $wpdb->get_results($data_sql);
        
        // Decrypt secure tokens for display
        if (class_exists('CPP_Encryption')) {
            foreach ($giftcodes as $giftcode) {
                if (!empty($giftcode->secure_token)) {
                    $giftcode->secure_token = CPP_Encryption::decrypt($giftcode->secure_token);
                }
            }
        }
        
        return array(
            'giftcodes' => $giftcodes,
            'total' => $total,
            'pages' => ceil($total / $args['per_page']),
        );
    }

    /**
     * Delete a gift code
     *
     * @param int $id Gift code ID
     * @return bool True on success, false on failure
     * @since 1.0.0
     */
    public function delete_giftcode($id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cpp_giftcodes';
        
        // Get the code for logging
        $code = $wpdb->get_var($wpdb->prepare(
            "SELECT code FROM $table_name WHERE id = %d",
            $id
        ));
        
        $result = $wpdb->delete(
            $table_name,
            array('id' => $id),
            array('%d')
        );
        
        if ($result) {
            $this->log_event('giftcode_deleted', $code, 'deleted');
            return true;
        }
        
        return false;
    }

    /**
     * Log gift code events
     *
     * @param string $event_type Event type
     * @param string $code Gift code
     * @param string $details Event details
     * @since 1.0.0
     */
    private function log_event($event_type, $code, $details) {
        // Load analytics and token helper classes
        require_once CPP_PLUGIN_DIR . 'includes/class-cpp-analytics.php';
        require_once CPP_PLUGIN_DIR . 'includes/cpp-token-helpers.php';
        $analytics = new CPP_Analytics();
        
        $analytics->log_event($event_type, 'giftcode', $code, array(
            'details' => $details,
            'user_id' => get_current_user_id(),
            'ip_address' => $this->get_client_ip(),
        ));
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
        
    }

    /**
     * Get gift code statistics
     *
     * @return array Statistics
     * @since 1.0.0
     */
    public function get_stats() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cpp_giftcodes';
        
        $stats = array();
        
        $stats['total_codes'] = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
        $stats['active_codes'] = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE status = 'active'");
        $stats['used_codes'] = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE status = 'used'");
        $stats['expired_codes'] = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE status = 'expired' OR (expires_at IS NOT NULL AND expires_at < NOW())");
        // Not tracked in current schema
        $stats['total_value'] = 0;
        
        return $stats;
    }

    /**
     * Get gift codes with optional filtering
     *
     * @param array $args Query arguments
     * @return array Gift codes
     * @since 1.0.0
     */
    public function get_codes($args = array()) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cpp_giftcodes';
        
        $defaults = array(
            'limit' => 50,
            'offset' => 0,
            'status' => '',
            'search' => '',
            'order_by' => 'created_at',
            'order' => 'DESC'
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where_clauses = array();
        $where_values = array();
        
        if (!empty($args['status'])) {
            $where_clauses[] = "status = %s";
            $where_values[] = $args['status'];
        }
        
        if (!empty($args['search'])) {
            $where_clauses[] = "(code LIKE %s OR description LIKE %s)";
            $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
            $where_values[] = $search_term;
            $where_values[] = $search_term;
        }
        
        $where_sql = '';
        if (!empty($where_clauses)) {
            $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
        }
        
        $allowed_order_by = array('id', 'code', 'status', 'created_at', 'expires_at');
        $order_by = in_array($args['order_by'], $allowed_order_by) ? $args['order_by'] : 'created_at';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
        
        $limit_sql = '';
        if ($args['limit'] > 0) {
            $limit_sql = $wpdb->prepare("LIMIT %d OFFSET %d", $args['limit'], $args['offset']);
        }
        
        $sql = "SELECT * FROM {$table_name} {$where_sql} ORDER BY {$order_by} {$order} {$limit_sql}";
        
        if (!empty($where_values)) {
            $sql = $wpdb->prepare($sql, $where_values);
        }
        
        return $wpdb->get_results($sql);
    }

    /**
     * Get a single gift code by ID
     */
    public function get_code($id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cpp_giftcodes';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_name} WHERE id = %d", $id));
    }

    /**
     * Create a new gift code (aligned with current schema)
     */
    public function create_code($data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cpp_giftcodes';
        
        $defaults = array(
            'code' => '',
            'secure_token' => '',
            'duration_minutes' => 60,
            'duration_display' => '60 minutes',
            'status' => 'active',
            'expires_at' => null,
            'description' => '',
            'ip_restrictions' => '',
            'created_at' => current_time('mysql')
        );
        $data = wp_parse_args($data, $defaults);
        
        if (empty($data['code'])) {
            return false;
        }
        
        $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table_name} WHERE code = %s", $data['code']));
        if ($existing) {
            return false;
        }
        
        $storage_data = $data;
        if (!empty($data['secure_token']) && class_exists('CPP_Encryption')) {
            $storage_data['secure_token'] = CPP_Encryption::encrypt($data['secure_token']);
        }
        
        $allowed = array('code','secure_token','duration_minutes','duration_display','status','expires_at','description','ip_restrictions','created_at');
        $filtered = array();
        foreach ($storage_data as $k => $v) {
            if (in_array($k, $allowed, true)) {
                $filtered[$k] = $v;
            }
        }
        
        $result = $wpdb->insert($table_name, $filtered);
        if ($result) {
            $this->log_event('giftcode_created', $data['code'], 'created');
            return $wpdb->insert_id;
        }
        return false;
    }

    /** Update a gift code */
    public function update_code($id, $data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cpp_giftcodes';
        unset($data['id'], $data['created_at']);
        $result = $wpdb->update($table_name, $data, array('id' => $id), null, array('%d'));
        if ($result !== false) {
            $code = $wpdb->get_var($wpdb->prepare("SELECT code FROM {$table_name} WHERE id = %d", $id));
            $this->log_event('giftcode_updated', $code, 'updated');
            return true;
        }
        return false;
    }

    /** Delete a gift code */
    public function delete_code($id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cpp_giftcodes';
        $code = $wpdb->get_var($wpdb->prepare("SELECT code FROM {$table_name} WHERE id = %d", $id));
        $result = $wpdb->delete($table_name, array('id' => $id), array('%d'));
        if ($result) {
            $this->log_event('giftcode_deleted', $code, 'deleted');
            return true;
        }
        return false;
    }

    /** Rate limiting for validation attempts */
    private function check_rate_limit($client_ip) {
        $transient_key = 'cpp_rate_limit_' . md5($client_ip);
        $attempts = get_transient($transient_key);
        if ($attempts === false) {
            set_transient($transient_key, 1, 60);
            return true;
        }
        if ($attempts >= 10) {
            return false;
        }
        set_transient($transient_key, $attempts + 1, 60);
        return true;
    }

    /** Timing-safe token compare */
    private function secure_compare($token1, $token2) {
        if (function_exists('hash_equals')) {
            return hash_equals($token1, $token2);
        }
        if (strlen($token1) !== strlen($token2)) {
            return false;
        }
        $result = 0;
        for ($i = 0; $i < strlen($token1); $i++) {
            $result |= ord($token1[$i]) ^ ord($token2[$i]);
        }
        return $result === 0;
    }
}