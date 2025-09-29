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
     * Validate a gift code
     *
     * @param string $code The gift code to validate
     * @return array Validation result
     * @since 1.0.0
     */
    public function validate_code($code) {
        global $wpdb;
        
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
        
        // Check if expired
        if ($giftcode->expires_at && strtotime($giftcode->expires_at) < current_time('timestamp')) {
            $this->log_event('giftcode_validation_failed', $code, 'expired');
            return array(
                'valid' => false,
                'message' => __('This gift code has expired.', 'content-protect-pro')
            );
        }
        
        // Check usage limit
        if ($giftcode->usage_count >= $giftcode->usage_limit) {
            $this->log_event('giftcode_validation_failed', $code, 'usage_exceeded');
            return array(
                'valid' => false,
                'message' => __('This gift code has already been used.', 'content-protect-pro')
            );
        }
        
        // Check status
        if ($giftcode->status !== 'active') {
            $this->log_event('giftcode_validation_failed', $code, 'inactive');
            return array(
                'valid' => false,
                'message' => __('This gift code is not active.', 'content-protect-pro')
            );
        }
        
        // Update usage count
        $wpdb->update(
            $table_name,
            array('usage_count' => $giftcode->usage_count + 1),
            array('id' => $giftcode->id),
            array('%d'),
            array('%d')
        );
        
        $this->log_event('giftcode_validation_success', $code, 'validated');
        
        return array(
            'valid' => true,
            'message' => __('Gift code validated successfully!', 'content-protect-pro'),
            'value' => $giftcode->value,
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
        // Load analytics class
        require_once CPP_PLUGIN_DIR . 'includes/class-cpp-analytics.php';
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
        
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
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
        
        // Total codes
        $stats['total_codes'] = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
        
        // Active codes
        $stats['active_codes'] = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE status = 'active'");
        
        // Used codes
        $stats['used_codes'] = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE status = 'used'");
        
        // Expired codes
        $stats['expired_codes'] = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE status = 'expired' OR (expires_at IS NOT NULL AND expires_at < NOW())");
        
        // Total value
        $stats['total_value'] = $wpdb->get_var("SELECT SUM(value) FROM {$table_name} WHERE status = 'active'") ?: 0;
        
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
        
        // Status filter
        if (!empty($args['status'])) {
            $where_clauses[] = "status = %s";
            $where_values[] = $args['status'];
        }
        
        // Search filter
        if (!empty($args['search'])) {
            $where_clauses[] = "(code LIKE %s OR description LIKE %s)";
            $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
            $where_values[] = $search_term;
            $where_values[] = $search_term;
        }
        
        // Build WHERE clause
        $where_sql = '';
        if (!empty($where_clauses)) {
            $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
        }
        
        // Build ORDER BY clause
        $allowed_order_by = array('id', 'code', 'value', 'status', 'created_at', 'expires_at');
        $order_by = in_array($args['order_by'], $allowed_order_by) ? $args['order_by'] : 'created_at';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
        
        // Build LIMIT clause
        $limit_sql = '';
        if ($args['limit'] > 0) {
            $limit_sql = $wpdb->prepare("LIMIT %d OFFSET %d", $args['limit'], $args['offset']);
        }
        
        // Execute query
        $sql = "SELECT * FROM {$table_name} {$where_sql} ORDER BY {$order_by} {$order} {$limit_sql}";
        
        if (!empty($where_values)) {
            $sql = $wpdb->prepare($sql, $where_values);
        }
        
        return $wpdb->get_results($sql);
    }

    /**
     * Get a single gift code by ID
     *
     * @param int $id Gift code ID
     * @return object|null Gift code object or null
     * @since 1.0.0
     */
    public function get_code($id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cpp_giftcodes';
        
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_name} WHERE id = %d", $id));
    }

    /**
     * Create a new gift code
     *
     * @param array $data Gift code data
     * @return int|false Gift code ID or false on failure
     * @since 1.0.0
     */
    public function create_code($data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cpp_giftcodes';
        
        $defaults = array(
            'code' => '',
            'value' => 0,
            'max_uses' => 1,
            'used_count' => 0,
            'status' => 'active',
            'expires_at' => null,
            'description' => '',
            'created_at' => current_time('mysql')
        );
        
        $data = wp_parse_args($data, $defaults);
        
        // Validate required fields
        if (empty($data['code'])) {
            return false;
        }
        
        // Check if code already exists
        $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table_name} WHERE code = %s", $data['code']));
        if ($existing) {
            return false;
        }
        
        $result = $wpdb->insert($table_name, $data);
        
        if ($result) {
            $this->log_event('giftcode_created', $data['code'], 'created');
            return $wpdb->insert_id;
        }
        
        return false;
    }

    /**
     * Update an existing gift code
     *
     * @param int $id Gift code ID
     * @param array $data Updated data
     * @return bool Success
     * @since 1.0.0
     */
    public function update_code($id, $data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cpp_giftcodes';
        
        // Remove fields that shouldn't be updated
        unset($data['id'], $data['created_at'], $data['used_count']);
        
        $result = $wpdb->update(
            $table_name,
            $data,
            array('id' => $id),
            null,
            array('%d')
        );
        
        if ($result !== false) {
            $code = $wpdb->get_var($wpdb->prepare("SELECT code FROM {$table_name} WHERE id = %d", $id));
            $this->log_event('giftcode_updated', $code, 'updated');
            return true;
        }
        
        return false;
    }

    /**
     * Delete a gift code
     *
     * @param int $id Gift code ID
     * @return bool Success
     * @since 1.0.0
     */
    public function delete_code($id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cpp_giftcodes';
        
        $code = $wpdb->get_var($wpdb->prepare("SELECT code FROM {$table_name} WHERE id = %d", $id));
        
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
}