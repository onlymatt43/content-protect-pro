<?php
/**
 * Enhanced Video Library Manager
 * 
 * Handles video gallery with filtering, search, and proper Presto/Bunny integration.
 * Follows token-based authentication flow from copilot-instructions.
 * 
 * @package ContentProtectPro
 * @since 3.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class CPP_Video_Library {
    
    /**
     * Get videos for library display
     * Respects session authentication and required_minutes
     * 
     * @param array $args Query arguments (category, search, limit, offset)
     * @return array Videos with access status
     */
    public static function get_library_videos($args = []) {
        global $wpdb;
        
        $defaults = [
            'category' => '',
            'search' => '',
            'limit' => 20,
            'offset' => 0,
            'integration_type' => '', // 'presto' or 'bunny' (empty = all)
            'order_by' => 'created_at',
            'order' => 'DESC'
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        // Get session info for access control
        $session = self::get_current_session();
        $session_minutes = $session ? (int) $session->duration_minutes : 0;
        
        // Build query
        $table = $wpdb->prefix . 'cpp_protected_videos';
        $where_clauses = ["status = 'active'"];
        $query_params = [];
        
        // Filter by integration type
        if (!empty($args['integration_type'])) {
            $where_clauses[] = "integration_type = %s";
            $query_params[] = $args['integration_type'];
        }
        
        // Search in video_id and metadata
        if (!empty($args['search'])) {
            $where_clauses[] = "(video_id LIKE %s OR metadata LIKE %s)";
            $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
            $query_params[] = $search_term;
            $query_params[] = $search_term;
        }
        
        // Category filter (stored in metadata JSON)
        if (!empty($args['category'])) {
            $where_clauses[] = "metadata LIKE %s";
            $query_params[] = '%"category":"' . $wpdb->esc_like($args['category']) . '"%';
        }
        
        $where_sql = implode(' AND ', $where_clauses);
        $order_by = sanitize_sql_orderby($args['order_by'] . ' ' . $args['order']);
        
        // Count total (for pagination)
        $count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
        if (!empty($query_params)) {
            $count_sql = $wpdb->prepare($count_sql, $query_params);
        }
        $total_videos = (int) $wpdb->get_var($count_sql);
        
        // Get videos
        $sql = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY {$order_by} LIMIT %d OFFSET %d";
        $query_params[] = (int) $args['limit'];
        $query_params[] = (int) $args['offset'];
        
        $videos = $wpdb->get_results($wpdb->prepare($sql, $query_params));
        
        // Enrich each video with access status and integration data
        $enriched_videos = array_map(function($video) use ($session_minutes) {
            return self::enrich_video_data($video, $session_minutes);
        }, $videos);
        
        return [
            'videos' => $enriched_videos,
            'total' => $total_videos,
            'has_session' => !empty($session),
            'session_minutes' => $session_minutes,
            'page' => floor($args['offset'] / $args['limit']) + 1,
            'per_page' => $args['limit']
        ];
    }
    
    /**
     * Enrich video data with access status and integration-specific info
     * 
     * @param object $video Raw video from database
     * @param int $session_minutes Minutes from current session
     * @return array Enriched video data
     */
    private static function enrich_video_data($video, $session_minutes) {
        $metadata = !empty($video->metadata) ? json_decode($video->metadata, true) : [];
        
        // Check access
        $required_minutes = (int) $video->required_minutes;
        $has_access = $session_minutes >= $required_minutes;
        
        $enriched = [
            'id' => $video->id,
            'video_id' => $video->video_id,
            'integration_type' => $video->integration_type,
            'required_minutes' => $required_minutes,
            'has_access' => $has_access,
            'title' => $metadata['title'] ?? 'Video #' . $video->video_id,
            'description' => $metadata['description'] ?? '',
            'thumbnail' => $metadata['thumbnail'] ?? '',
            'category' => $metadata['category'] ?? 'uncategorized',
            'duration' => $metadata['duration'] ?? null,
            'created_at' => $video->created_at
        ];
        
        // Add integration-specific data
        if ($video->integration_type === 'presto') {
            $enriched['presto_player_id'] = $video->presto_player_id;
            
            // Get Presto Player post data if available
            if (!empty($video->presto_player_id)) {
                $presto_post = get_post($video->presto_player_id);
                if ($presto_post) {
                    $enriched['title'] = $presto_post->post_title ?: $enriched['title'];
                    
                    // Get Presto Player thumbnail
                    $presto_thumb = get_post_meta($video->presto_player_id, '_presto_thumbnail', true);
                    if ($presto_thumb) {
                        $enriched['thumbnail'] = $presto_thumb;
                    }
                }
            }
        } elseif ($video->integration_type === 'bunny') {
            // Bunny CDN video data
            $enriched['bunny_video_id'] = $metadata['bunny_video_id'] ?? null;
            $enriched['bunny_library_id'] = $metadata['bunny_library_id'] ?? null;
        }
        
        return $enriched;
    }
    
    /**
     * Get current session from cookie
     * Follows authentication flow from copilot-instructions
     * 
     * @return object|null Session data or null
     */
    private static function get_current_session() {
        if (!isset($_COOKIE['cpp_session_token'])) {
            return null;
        }
        
        global $wpdb;
        $token = sanitize_text_field($_COOKIE['cpp_session_token']);
        
        $session = $wpdb->get_row($wpdb->prepare(
            "SELECT s.*, g.duration_minutes 
             FROM {$wpdb->prefix}cpp_sessions s
             LEFT JOIN {$wpdb->prefix}cpp_giftcodes g ON s.code = g.code
             WHERE s.secure_token = %s 
             AND s.status = 'active' 
             AND s.expires_at > NOW()",
            $token
        ));
        
        // Verify IP binding (security requirement from copilot-instructions)
        if ($session) {
            $client_ip = self::get_client_ip();
            if (!hash_equals($session->client_ip, $client_ip)) {
                // Log IP mismatch
                if (class_exists('CPP_Analytics')) {
                    $analytics = new CPP_Analytics();
                    $analytics->log_event('session_ip_mismatch', 'session', $session->session_id, [
                        'expected_ip' => $session->client_ip,
                        'actual_ip' => $client_ip
                    ]);
                }
                return null;
            }
        }
        
        return $session;
    }
    
    /**
     * Get video playback data
     * Returns either Presto embed HTML or Bunny signed URL
     * 
     * @param int $video_id Video ID
     * @return array ['success' => bool, 'data' => mixed, 'message' => string]
     */
    public static function get_video_playback($video_id) {
        // Validate session
        $session = self::get_current_session();
        if (!$session) {
            return [
                'success' => false,
                'message' => __('No active session. Please redeem a gift code first.', 'content-protect-pro')
            ];
        }
        
        global $wpdb;
        
        // Get video data
        $video = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}cpp_protected_videos 
             WHERE video_id = %s AND status = 'active'",
            $video_id
        ));
        
        if (!$video) {
            return [
                'success' => false,
                'message' => __('Video not found', 'content-protect-pro')
            ];
        }
        
        // Check access (required_minutes vs session duration)
        $session_minutes = (int) $session->duration_minutes;
        $required_minutes = (int) $video->required_minutes;
        
        if ($session_minutes < $required_minutes) {
            return [
                'success' => false,
                'message' => sprintf(
                    /* translators: %d: required minutes */
                    __('This video requires %d minutes of access. Your current session only has %d minutes.', 'content-protect-pro'),
                    $required_minutes,
                    $session_minutes
                ),
                'required_minutes' => $required_minutes,
                'session_minutes' => $session_minutes
            ];
        }
        
        // Generate playback based on integration type
        if ($video->integration_type === 'presto') {
            return self::get_presto_playback($video);
        } elseif ($video->integration_type === 'bunny') {
            return self::get_bunny_playback($video);
        }
        
        return [
            'success' => false,
            'message' => __('Unknown integration type', 'content-protect-pro')
        ];
    }
    
    /**
     * Get Presto Player embed HTML
     * Uses CPP_Presto_Integration following copilot pattern
     * 
     * @param object $video Video data
     * @return array
     */
    private static function get_presto_playback($video) {
        if (!class_exists('CPP_Presto_Integration')) {
            return [
                'success' => false,
                'message' => __('Presto Player integration not available', 'content-protect-pro')
            ];
        }
        
        $presto = new CPP_Presto_Integration();
        
        // Generate access token (following security pattern)
        $access_token = $presto->generate_access_token($video->presto_player_id);
        
        if (!$access_token) {
            return [
                'success' => false,
                'message' => __('Failed to generate Presto Player access token', 'content-protect-pro')
            ];
        }
        
        // Get embed HTML
        $embed_html = do_shortcode('[presto_player id="' . absint($video->presto_player_id) . '"]');
        
        // Track analytics
        if (class_exists('CPP_Analytics')) {
            $analytics = new CPP_Analytics();
            $analytics->log_event('video_playback_requested', 'video', $video->video_id, [
                'integration_type' => 'presto',
                'presto_player_id' => $video->presto_player_id
            ]);
        }
        
        return [
            'success' => true,
            'type' => 'embed',
            'data' => [
                'embed_html' => $embed_html,
                'presto_player_id' => $video->presto_player_id,
                'access_token' => $access_token
            ]
        ];
    }
    
    /**
     * Get Bunny CDN signed playback URL
     * Legacy support - follows original architecture
     * 
     * @param object $video Video data
     * @return array
     */
    private static function get_bunny_playback($video) {
        if (!class_exists('CPP_Bunny_Integration')) {
            return [
                'success' => false,
                'message' => __('Bunny CDN integration not available', 'content-protect-pro')
            ];
        }
        
        $metadata = json_decode($video->metadata, true);
        $bunny_video_id = $metadata['bunny_video_id'] ?? null;
        $bunny_library_id = $metadata['bunny_library_id'] ?? null;
        
        if (!$bunny_video_id || !$bunny_library_id) {
            return [
                'success' => false,
                'message' => __('Bunny video configuration incomplete', 'content-protect-pro')
            ];
        }
        
        $bunny = new CPP_Bunny_Integration();
        
        // Generate signed URL with expiration
        $signed_url = $bunny->generate_signed_url(
            $bunny_video_id,
            $bunny_library_id,
            3600 // 1 hour expiration
        );
        
        if (!$signed_url) {
            return [
                'success' => false,
                'message' => __('Failed to generate Bunny CDN signed URL', 'content-protect-pro')
            ];
        }
        
        // Track analytics
        if (class_exists('CPP_Analytics')) {
            $analytics = new CPP_Analytics();
            $analytics->log_event('video_playback_requested', 'video', $video->video_id, [
                'integration_type' => 'bunny',
                'bunny_video_id' => $bunny_video_id
            ]);
        }
        
        return [
            'success' => true,
            'type' => 'url',
            'data' => [
                'playback_url' => $signed_url,
                'bunny_video_id' => $bunny_video_id,
                'expires_in' => 3600
            ]
        ];
    }
    
    /**
     * Get client IP (following security pattern from copilot-instructions)
     * 
     * @return string
     */
    private static function get_client_ip() {
        if (function_exists('cpp_get_client_ip')) {
            return cpp_get_client_ip();
        }
        
        // Fallback implementation
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        
        // Check for proxy headers (in order of reliability)
        $proxy_headers = [
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_REAL_IP',
            'HTTP_X_FORWARDED_FOR'
        ];
        
        foreach ($proxy_headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                // Handle comma-separated IPs (take first one)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                break;
            }
        }
        
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }
    
    /**
     * Get available categories from videos
     * 
     * @return array Categories with counts
     */
    public static function get_categories() {
        global $wpdb;
        
        $videos = $wpdb->get_results(
            "SELECT metadata FROM {$wpdb->prefix}cpp_protected_videos 
             WHERE status = 'active' AND metadata IS NOT NULL"
        );
        
        $categories = [];
        
        foreach ($videos as $video) {
            $metadata = json_decode($video->metadata, true);
            $category = $metadata['category'] ?? 'uncategorized';
            
            if (!isset($categories[$category])) {
                $categories[$category] = 0;
            }
            $categories[$category]++;
        }
        
        // Sort by count descending
        arsort($categories);
        
        return $categories;
    }
}