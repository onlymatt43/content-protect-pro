<?php
/**
 * Analytics and logging functionality
 *
 * @package Content_Protect_Pro
 * @since   1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class CPP_Analytics {

    /**
     * Log an event to the analytics table
     *
     * @param string $event_type Event type
     * @param string $object_type Object type (giftcode, video, etc.)
     * @param string $object_id Object identifier
     * @param array  $metadata Additional event metadata
     * @return bool True on success, false on failure
     * @since 1.0.0
     */
    public function log_event($event_type, $object_type, $object_id, $metadata = array()) {
        global $wpdb;
        
        $settings = get_option('cpp_analytics_settings', array());
        
        // Check if analytics is enabled
        if (empty($settings['enable_analytics'])) {
            return false;
        }
        
        // Check specific tracking settings
        if ($object_type === 'giftcode' && empty($settings['track_giftcode_usage'])) {
            return false;
        }
        
        if ($object_type === 'video' && empty($settings['track_video_views'])) {
            return false;
        }
        
        $table_name = $wpdb->prefix . 'cpp_analytics';
        
        // Prepare metadata
        $default_metadata = array(
            'user_id' => get_current_user_id(),
            'ip_address' => $this->get_client_ip(),
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '',
        );
        
        $metadata = wp_parse_args($metadata, $default_metadata);
        
        // Anonymize IP if enabled
        if (!empty($settings['anonymize_ip'])) {
            $metadata['ip_address'] = $this->anonymize_ip($metadata['ip_address']);
        }
        
        $result = $wpdb->insert(
            $table_name,
            array(
                'event_type' => $event_type,
                'object_type' => $object_type,
                'object_id' => $object_id,
                'user_id' => $metadata['user_id'],
                'ip_address' => $metadata['ip_address'],
                'user_agent' => $metadata['user_agent'],
                'metadata' => json_encode($metadata),
            ),
            array('%s', '%s', '%s', '%d', '%s', '%s', '%s')
        );
        
        return $result !== false;
    }

    /**
     * Get analytics data with filters
     *
     * @param array $args Query arguments
     * @return array Analytics data
     * @since 1.0.0
     */
    public function get_analytics($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'per_page' => 50,
            'page' => 1,
            'event_type' => '',
            'object_type' => '',
            'date_from' => '',
            'date_to' => '',
            'orderby' => 'created_at',
            'order' => 'DESC',
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $table_name = $wpdb->prefix . 'cpp_analytics';
        
        $where_clauses = array();
        $prepare_values = array();
        
        if (!empty($args['event_type'])) {
            $where_clauses[] = 'event_type = %s';
            $prepare_values[] = $args['event_type'];
        }
        
        if (!empty($args['object_type'])) {
            $where_clauses[] = 'object_type = %s';
            $prepare_values[] = $args['object_type'];
        }
        
        if (!empty($args['date_from'])) {
            $where_clauses[] = 'created_at >= %s';
            $prepare_values[] = $args['date_from'];
        }
        
        if (!empty($args['date_to'])) {
            $where_clauses[] = 'created_at <= %s';
            $prepare_values[] = $args['date_to'];
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
        
        $events = $wpdb->get_results($data_sql);
        
        return array(
            'events' => $events,
            'total' => $total,
            'pages' => ceil($total / $args['per_page']),
        );
    }

    /**
     * Get analytics summary statistics
     *
     * @param array $args Query arguments
     * @return array Summary statistics
     * @since 1.0.0
     */
    public function get_summary($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'date_from' => date('Y-m-d', strtotime('-30 days')),
            'date_to' => date('Y-m-d'),
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $table_name = $wpdb->prefix . 'cpp_analytics';
        
        $date_where = $wpdb->prepare(
            'WHERE created_at >= %s AND created_at <= %s',
            $args['date_from'] . ' 00:00:00',
            $args['date_to'] . ' 23:59:59'
        );
        
        // Total events
        $total_events = $wpdb->get_var("SELECT COUNT(*) FROM $table_name $date_where");
        
        // Gift code events
        $giftcode_validations = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name $date_where AND event_type = %s",
            'giftcode_validation_success'
        ));
        
        $giftcode_failures = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name $date_where AND event_type = %s",
            'giftcode_validation_failed'
        ));
        
        // Video events
        $video_views = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name $date_where AND event_type = %s",
            'video_view'
        ));
        
        $token_requests = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name $date_where AND event_type = %s",
            'video_token_request'
        ));
        
        // Daily breakdown
        $daily_stats = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(created_at) as date, COUNT(*) as count 
             FROM $table_name $date_where 
             GROUP BY DATE(created_at) 
             ORDER BY date ASC"
        ));
        
        return array(
            'total_events' => intval($total_events),
            'giftcode_validations' => intval($giftcode_validations),
            'giftcode_failures' => intval($giftcode_failures),
            'video_views' => intval($video_views),
            'token_requests' => intval($token_requests),
            'daily_stats' => $daily_stats,
        );
    }

    /**
     * Clean up old analytics data
     *
     * @param int $days Number of days to retain
     * @return int Number of deleted records
     * @since 1.0.0
     */
    public function cleanup_old_data($days = null) {
        global $wpdb;
        
        if ($days === null) {
            $settings = get_option('cpp_security_settings', array());
            $days = isset($settings['log_retention_days']) ? intval($settings['log_retention_days']) : 30;
        }
        
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-$days days"));
        
        $table_name = $wpdb->prefix . 'cpp_analytics';
        
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_name WHERE created_at < %s",
            $cutoff_date
        ));
        
        return $deleted;
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
     * Anonymize IP address by removing last octet
     *
     * @param string $ip IP address to anonymize
     * @return string Anonymized IP address
     * @since 1.0.0
     */
    private function anonymize_ip($ip) {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return preg_replace('/\.\d+$/', '.0', $ip);
        } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return preg_replace('/:[0-9a-fA-F]+:[0-9a-fA-F]+$/', ':0:0', $ip);
        }
        
        return $ip;
    }

    /**
     * Get summary statistics for dashboard
     *
     * @return array Summary statistics
     * @since 1.0.0
     */
    public function get_summary_stats() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cpp_analytics';
        
        // Get total events
        $total_events = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
        
        // Get events in last 24 hours
        $events_24h = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} WHERE created_at >= %s",
            date('Y-m-d H:i:s', strtotime('-24 hours'))
        ));
        
        // Get security events in last 30 days
        $security_events = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} WHERE object_type = 'security' AND created_at >= %s",
            date('Y-m-d H:i:s', strtotime('-30 days'))
        ));
        
        // Get gift code events
        $giftcode_events = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} WHERE object_type = 'giftcode' AND created_at >= %s",
            date('Y-m-d H:i:s', strtotime('-30 days'))
        ));
        
        // Get video events
        $video_events = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} WHERE object_type = 'video' AND created_at >= %s",
            date('Y-m-d H:i:s', strtotime('-30 days'))
        ));
        
        return array(
            'total_events' => intval($total_events) ?: 0,
            'events_24h' => intval($events_24h) ?: 0,
            'security_events' => intval($security_events) ?: 0,
            'giftcode_events' => intval($giftcode_events) ?: 0,
            'video_events' => intval($video_events) ?: 0,
        );
    }
}