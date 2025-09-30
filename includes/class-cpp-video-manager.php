<?php
/**
 * Video management and protection functionality
 *
 * @package Content_Protect_Pro
 * @since   1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class CPP_Video_Manager {

    /**
     * Create a new protected video entry
     *
     * @param array $data Video data
     * @return int|false Video ID on success, false on failure
     * @since 1.0.0
     */
    public function create_protected_video($data) {
        global $wpdb;
        
        $defaults = array(
            'video_id' => '',
            'title' => '',
            'protection_type' => 'token',
            'bunny_library_id' => '',
            'presto_player_id' => '',
            'access_level' => 'public',
            'requires_giftcode' => 0,
            'allowed_giftcodes' => '',
        );
        
        $data = wp_parse_args($data, $defaults);
        
        // Validate required fields
        if (empty($data['video_id']) || empty($data['title'])) {
            return false;
        }
        
        $table_name = $wpdb->prefix . 'cpp_protected_videos';
        
        $result = $wpdb->insert(
            $table_name,
            array(
                'video_id' => $data['video_id'],
                'title' => $data['title'],
                'protection_type' => $data['protection_type'],
                'bunny_library_id' => $data['bunny_library_id'],
                'presto_player_id' => $data['presto_player_id'],
                'access_level' => $data['access_level'],
                'requires_giftcode' => intval($data['requires_giftcode']),
                'allowed_giftcodes' => $data['allowed_giftcodes'],
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s')
        );
        
        if ($result) {
            $this->log_event('video_created', $data['video_id'], 'created');
            return $wpdb->insert_id;
        }
        
        return false;
    }

    /**
     * Get protected videos with pagination
     *
     * @param array $args Query arguments
     * @return array Videos and total count
     * @since 1.0.0
     */
    public function get_protected_videos($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'per_page' => 20,
            'page' => 1,
            'access_level' => '',
            'protection_type' => '',
            'search' => '',
            'orderby' => 'created_at',
            'order' => 'DESC',
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $table_name = $wpdb->prefix . 'cpp_protected_videos';
        
        $where_clauses = array();
        $prepare_values = array();
        
        if (!empty($args['access_level'])) {
            $where_clauses[] = 'access_level = %s';
            $prepare_values[] = $args['access_level'];
        }
        
        if (!empty($args['protection_type'])) {
            $where_clauses[] = 'protection_type = %s';
            $prepare_values[] = $args['protection_type'];
        }
        
        if (!empty($args['search'])) {
            $where_clauses[] = '(title LIKE %s OR video_id LIKE %s)';
            $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
            $prepare_values[] = $search_term;
            $prepare_values[] = $search_term;
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
            $data_sql = $wpdb->prepare(
                "SELECT * FROM $table_name $where_sql $order_sql LIMIT %d, %d",
                array_merge($prepare_values, array($offset, $args['per_page']))
            );
        }
        
        $videos = $wpdb->get_results($data_sql);
        
        return array(
            'videos' => $videos,
            'total' => $total,
            'pages' => ceil($total / $args['per_page']),
        );
    }

    /**
     * Get a specific protected video by ID
     *
     * @param string $video_id Video identifier
     * @return object|false Video object or false
     * @since 1.0.0
     */
    public function get_protected_video($video_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cpp_protected_videos';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE video_id = %s",
            $video_id
        ));
    }

    /**
     * Update a protected video
     *
     * @param string $video_id Video identifier
     * @param array  $data     Updated data
     * @return bool True on success, false on failure
     * @since 1.0.0
     */
    public function update_protected_video($video_id, $data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cpp_protected_videos';
        
        $allowed_fields = array(
            'title', 'protection_type', 'bunny_library_id', 'presto_player_id',
            'access_level', 'requires_giftcode', 'allowed_giftcodes'
        );
        
        $update_data = array();
        $update_format = array();
        
        foreach ($data as $field => $value) {
            if (in_array($field, $allowed_fields)) {
                $update_data[$field] = $value;
                $update_format[] = is_numeric($value) ? '%d' : '%s';
            }
        }
        
        if (empty($update_data)) {
            return false;
        }
        
        $result = $wpdb->update(
            $table_name,
            $update_data,
            array('video_id' => $video_id),
            $update_format,
            array('%s')
        );
        
        if ($result !== false) {
            $this->log_event('video_updated', $video_id, 'updated');
            return true;
        }
        
        return false;
    }

    /**
     * Delete a protected video
     *
     * @param string $video_id Video identifier
     * @return bool True on success, false on failure
     * @since 1.0.0
     */
    public function delete_protected_video($video_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cpp_protected_videos';
        
        $result = $wpdb->delete(
            $table_name,
            array('video_id' => $video_id),
            array('%s')
        );
        
        if ($result) {
            $this->log_event('video_deleted', $video_id, 'deleted');
            return true;
        }
        
        return false;
    }

    /**
     * Generate access token for video
     *
     * @param string $video_id Video identifier
     * @param array  $options  Token options
     * @return string|false Access token or false on failure
     * @since 1.0.0
     */
    public function generate_access_token($video_id, $options = array()) {
        $video = $this->get_protected_video($video_id);
        
        if (!$video) {
            return false;
        }

        // Check if user has access
        if (!$this->check_video_access($video_id)) {
            return false;
        }

        $video_settings = get_option('cpp_video_settings', array());
        $expiry = isset($video_settings['token_expiry']) ? intval($video_settings['token_expiry']) : 3600;
        
        $defaults = array(
            'expiry' => time() + $expiry,
            'ip_restriction' => false,
            'user_id' => get_current_user_id(),
        );
        
        $options = wp_parse_args($options, $defaults);
        
        $payload = array(
            'video_id' => $video_id,
            'user_id' => $options['user_id'],
            'expires' => $options['expiry'],
            'issued_at' => time(),
        );

        if ($options['ip_restriction']) {
            $payload['ip'] = $this->get_client_ip();
        }

        // Use WordPress AUTH_KEY for token signing
        $secret_key = AUTH_KEY;
        
        // Create JWT-style token
        $header = json_encode(array('typ' => 'JWT', 'alg' => 'HS256'));
        $payload_json = json_encode($payload);
        
        $base64_header = $this->base64url_encode($header);
        $base64_payload = $this->base64url_encode($payload_json);
        
        $signature = hash_hmac('sha256', $base64_header . '.' . $base64_payload, $secret_key, true);
        $base64_signature = $this->base64url_encode($signature);
        
        $token = $base64_header . '.' . $base64_payload . '.' . $base64_signature;
        
        // Log token generation
        $this->log_event('token_generated', $video_id, 'token_created');
        
        return $token;
    }

    /**
     * Validate access token
     *
     * @param string $token    Access token
     * @param string $video_id Video identifier (optional, for validation)
     * @return array|false Token data or false on failure
     * @since 1.0.0
     */
    public function validate_access_token($token, $video_id = null) {
        $parts = explode('.', $token);
        
        if (count($parts) !== 3) {
            return false;
        }

        list($header, $payload, $signature) = $parts;
        
        // Verify signature
        $secret_key = AUTH_KEY;
        $expected_signature = hash_hmac('sha256', $header . '.' . $payload, $secret_key, true);
        $expected_base64 = $this->base64url_encode($expected_signature);
        
        if (!hash_equals($expected_base64, $signature)) {
            return false;
        }

        // Decode payload
        $payload_data = json_decode($this->base64url_decode($payload), true);
        
        if (!$payload_data) {
            return false;
        }

        // Check expiration
        if (isset($payload_data['expires']) && time() > $payload_data['expires']) {
            return false;
        }

        // Check video ID if provided
        if ($video_id && isset($payload_data['video_id']) && $payload_data['video_id'] !== $video_id) {
            return false;
        }

        // Check IP if restricted
        if (isset($payload_data['ip'])) {
            $current_ip = $this->get_client_ip();
            if ($payload_data['ip'] !== $current_ip) {
                return false;
            }
        }

        return $payload_data;
    }

    /**
     * Check if user has access to video
     *
     * @param string $video_id Video identifier
     * @return bool True if access granted
     * @since 1.0.0
     */
    public function check_video_access($video_id) {
        $video = $this->get_protected_video($video_id);
        
        if (!$video) {
            return false;
        }

        // Check access level
        if ($video->access_level === 'public') {
            return true;
        }

        // Check if user is logged in for private content
        if ($video->access_level === 'private' && !is_user_logged_in()) {
            return false;
        }

        // Check gift code requirement
        if ($video->requires_giftcode) {
            if (!session_id()) {
                session_start();
            }
            
            $validated_codes = isset($_SESSION['cpp_validated_codes']) ? $_SESSION['cpp_validated_codes'] : array();
            
            if (empty($validated_codes)) {
                return false;
            }

            // Check if any validated code is in allowed codes list
            if (!empty($video->allowed_giftcodes)) {
                $allowed_codes = explode(',', $video->allowed_giftcodes);
                $has_valid_code = false;
                
                foreach ($validated_codes as $code) {
                    if (in_array(trim($code), $allowed_codes)) {
                        $has_valid_code = true;
                        break;
                    }
                }
                
                return $has_valid_code;
            }
        }

        return true;
    }

    /**
     * Get video statistics
     *
     * @param string $video_id Video identifier
     * @param array  $params   Query parameters
     * @return array Video statistics
     * @since 1.0.0
     */
    public function get_video_statistics($video_id, $params = array()) {
        $defaults = array(
            'date_from' => date('Y-m-d', strtotime('-30 days')),
            'date_to' => date('Y-m-d'),
        );
        
        $params = wp_parse_args($params, $defaults);
        
        $analytics = new CPP_Analytics();
        
        // Get analytics data for this video
        $analytics_data = $analytics->get_analytics(array(
            'object_type' => 'video',
            'object_id' => $video_id,
            'date_from' => $params['date_from'],
            'date_to' => $params['date_to'],
        ));

        // Count different event types
        $stats = array(
            'total_views' => 0,
            'token_requests' => 0,
            'access_denied' => 0,
            'unique_viewers' => array(),
        );

        foreach ($analytics_data['events'] as $event) {
            $metadata = json_decode($event->metadata, true);
            
            switch ($event->event_type) {
                case 'video_view':
                case 'video_access':
                    $stats['total_views']++;
                    if ($event->user_id) {
                        $stats['unique_viewers'][$event->user_id] = true;
                    }
                    break;
                    
                case 'token_generated':
                    $stats['token_requests']++;
                    break;
                    
                case 'video_access_denied':
                    $stats['access_denied']++;
                    break;
            }
        }

        $stats['unique_viewers'] = count($stats['unique_viewers']);

        // Get Bunny statistics if enabled
        $video = $this->get_protected_video($video_id);
        if ($video && !empty($video->bunny_library_id)) {
            $bunny_integration = new CPP_Bunny_Integration();
            $bunny_stats = $bunny_integration->get_video_statistics($video_id, $params);
            
            if ($bunny_stats) {
                $stats['bunny_stats'] = $bunny_stats;
            }
        }

        return $stats;
    }

    /**
     * Base64URL encode
     *
     * @param string $data Data to encode
     * @return string Encoded data
     * @since 1.0.0
     */
    private function base64url_encode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Base64URL decode
     *
     * @param string $data Data to decode
     * @return string Decoded data
     * @since 1.0.0
     */
    private function base64url_decode($data) {
        return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
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
     * Log video events
     *
     * @param string $event_type Event type
     * @param string $video_id   Video identifier
     * @param string|array $details Details or metadata
     * @since 1.0.0
     */
    private function log_event($event_type, $video_id, $details = '') {
        if (!class_exists('CPP_Analytics')) {
            require_once CPP_PLUGIN_DIR . 'includes/class-cpp-analytics.php';
        }
        $analytics = new CPP_Analytics();
        $analytics->log_event($event_type, 'video', $video_id, array(
            'details' => $details,
            'user_id' => get_current_user_id(),
        ));
    }

    /**
     * Get video statistics
     *
     * @return array Statistics
     * @since 1.0.0
     */
    public function get_stats() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cpp_protected_videos';
        
        $stats = array();
        
        // Total videos
        $stats['total_videos'] = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}") ?: 0;
        
        // Active videos
        $stats['active_videos'] = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE status = 'active'") ?: 0;
        
        // Inactive videos
        $stats['inactive_videos'] = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE status = 'inactive'") ?: 0;
        
    // Videos with Bunny integration
    $stats['bunny_videos'] = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE bunny_library_id IS NOT NULL AND bunny_library_id != ''") ?: 0;
        
    // Videos with Presto integration
    $stats['presto_videos'] = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE presto_player_id IS NOT NULL AND presto_player_id != ''") ?: 0;
        
        // Total usage count
        $stats['total_usage'] = $wpdb->get_var("SELECT SUM(usage_count) FROM {$table_name}") ?: 0;
        
        // Videos with expiration (not tracked in current schema)
        $stats['expiring_videos'] = 0;
        
        return $stats;
    }
}