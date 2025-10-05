<?php
/**
 * Analytics System
 * 
 * Logs and reports on plugin events following copilot-instructions.md patterns.
 * Stores in cpp_analytics table with event_type, object_type, object_id, metadata.
 *
 * @package Content_Protect_Pro
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class CPP_Analytics {
    
    /**
     * Log event to analytics table
     * Following copilot-instructions pattern
     *
     * @param string $event_type Event type (e.g., 'giftcode_redeemed', 'video_playback_requested')
     * @param string $object_type Object type (e.g., 'giftcode', 'video', 'session')
     * @param string $object_id Object identifier
     * @param array $metadata Additional event data
     * @return int|false Event ID or false on failure
     */
    public function log_event($event_type, $object_type, $object_id, $metadata = []) {
        global $wpdb;
        
        $user_id = get_current_user_id();
        $session_id = $this->get_current_session_id();
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'cpp_analytics',
            [
                'event_type' => sanitize_text_field($event_type),
                'object_type' => sanitize_text_field($object_type),
                'object_id' => sanitize_text_field($object_id),
                'user_id' => $user_id > 0 ? $user_id : null,
                'session_id' => $session_id,
                'metadata' => wp_json_encode($metadata),
            ],
            ['%s', '%s', '%s', '%d', '%d', '%s']
        );
        
        return $result ? $wpdb->insert_id : false;
    }
    
    /**
     * Get current session ID from cookie
     *
     * @return int|null
     */
    private function get_current_session_id() {
        if (empty($_COOKIE['cpp_session_token'])) {
            return null;
        }
        
        global $wpdb;
        
        $token = sanitize_text_field($_COOKIE['cpp_session_token']);
        
        $session = $wpdb->get_row($wpdb->prepare(
            "SELECT session_id FROM {$wpdb->prefix}cpp_sessions 
             WHERE secure_token = %s AND status = 'active' LIMIT 1",
            $token
        ));
        
        return $session ? (int) $session->session_id : null;
    }
    
    /**
     * Get events by type with date range
     *
     * @param string $event_type Event type
     * @param string $start_date Start date (Y-m-d)
     * @param string $end_date End date (Y-m-d)
     * @return array Events
     */
    public function get_events_by_type($event_type, $start_date = null, $end_date = null) {
        global $wpdb;
        
        $where = ["event_type = %s"];
        $where_values = [sanitize_text_field($event_type)];
        
        if ($start_date) {
            $where[] = "created_at >= %s";
            $where_values[] = sanitize_text_field($start_date) . ' 00:00:00';
        }
        
        if ($end_date) {
            $where[] = "created_at <= %s";
            $where_values[] = sanitize_text_field($end_date) . ' 23:59:59';
        }
        
        $where_clause = implode(' AND ', $where);
        
        $query = "SELECT * FROM {$wpdb->prefix}cpp_analytics 
                  WHERE {$where_clause} 
                  ORDER BY created_at DESC 
                  LIMIT 1000";
        
        return $wpdb->get_results($wpdb->prepare($query, ...$where_values));
    }
    
    /**
     * Get event counts by type
     *
     * @param array $event_types Event types to count
     * @param int $days Look back days
     * @return array ['event_type' => count]
     */
    public function get_event_counts($event_types = [], $days = 30) {
        global $wpdb;
        
        $counts = [];
        $date_threshold = gmdate('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        if (empty($event_types)) {
            // Get all event types
            $query = "SELECT event_type, COUNT(*) as count 
                      FROM {$wpdb->prefix}cpp_analytics 
                      WHERE created_at >= %s 
                      GROUP BY event_type";
            
            $results = $wpdb->get_results($wpdb->prepare($query, $date_threshold));
            
            foreach ($results as $row) {
                $counts[$row->event_type] = (int) $row->count;
            }
        } else {
            // Get specific event types
            foreach ($event_types as $type) {
                $count = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}cpp_analytics 
                     WHERE event_type = %s AND created_at >= %s",
                    sanitize_text_field($type),
                    $date_threshold
                ));
                
                $counts[$type] = (int) $count;
            }
        }
        
        return $counts;
    }
    
    /**
     * Get top videos by views
     *
     * @param int $limit Number of results
     * @param int $days Look back days
     * @return array Video stats
     */
    public function get_top_videos($limit = 10, $days = 30) {
        global $wpdb;
        
        $date_threshold = gmdate('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $query = "SELECT 
                    object_id as video_id,
                    COUNT(*) as views,
                    COUNT(DISTINCT session_id) as unique_sessions
                  FROM {$wpdb->prefix}cpp_analytics 
                  WHERE event_type = 'video_playback_requested' 
                  AND created_at >= %s 
                  GROUP BY object_id 
                  ORDER BY views DESC 
                  LIMIT %d";
        
        return $wpdb->get_results($wpdb->prepare($query, $date_threshold, absint($limit)));
    }
    
    /**
     * Get dashboard statistics
     *
     * @return array Stats
     */
    public function get_dashboard_stats() {
        global $wpdb;
        
        $today = gmdate('Y-m-d');
        $week_ago = gmdate('Y-m-d', strtotime('-7 days'));
        
        // Gift codes redeemed (last 7 days)
        $codes_redeemed = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}cpp_analytics 
             WHERE event_type = 'giftcode_redeemed' 
             AND created_at >= %s",
            $week_ago . ' 00:00:00'
        ));
        
        // Active sessions
        $active_sessions = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}cpp_sessions 
             WHERE status = 'active' AND expires_at > NOW()"
        );
        
        // Total videos
        $total_videos = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}cpp_protected_videos 
             WHERE status = 'active'"
        );
        
        // Video views today
        $views_today = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}cpp_analytics 
             WHERE event_type = 'video_playback_requested' 
             AND created_at >= %s",
            $today . ' 00:00:00'
        ));
        
        return [
            'codes_redeemed_week' => (int) $codes_redeemed,
            'active_sessions' => (int) $active_sessions,
            'total_videos' => (int) $total_videos,
            'views_today' => (int) $views_today,
        ];
    }
    
    /**
     * Clean old analytics data
     * Called by daily cron job
     *
     * @param int $days Keep data newer than X days
     * @return int Rows deleted
     */
    public function cleanup_old_data($days = 90) {
        global $wpdb;
        
        $date_threshold = gmdate('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $result = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}cpp_analytics 
             WHERE created_at < %s",
            $date_threshold
        ));
        
        return (int) $result;
    }
}