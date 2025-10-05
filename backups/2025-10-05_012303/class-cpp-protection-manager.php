<?php
/**
 * Protection Manager
 * 
 * Manages video access control and session validation.
 * Following token-based auth pattern from copilot-instructions.md.
 *
 * @package Content_Protect_Pro
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class CPP_Protection_Manager {
    
    /**
     * Check if user has access to video
     * Following security pattern: session token validation + IP binding
     *
     * @param string $video_id Video ID
     * @param string $session_token Session token from cookie
     * @return bool Has access
     */
    public function check_video_access($video_id, $session_token) {
        global $wpdb;
        
        // Validate session token
        $session = $this->get_active_session($session_token);
        
        if (!$session) {
            return false;
        }
        
        // Get video requirements
        $video = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}cpp_protected_videos 
             WHERE video_id = %s AND status = 'active' LIMIT 1",
            sanitize_text_field($video_id)
        ));
        
        if (!$video) {
            return false;
        }
        
        // Check duration requirements
        $session_minutes = (int) $session->duration_minutes;
        $required_minutes = (int) $video->required_minutes;
        
        return $session_minutes >= $required_minutes;
    }
    
    /**
     * Get active session by token
     * Following security pattern: timing-safe comparison + IP binding
     *
     * @param string $token Session token
     * @return object|null Session data or null
     */
    public function get_active_session($token) {
        global $wpdb;
        
        if (empty($token)) {
            return null;
        }
        
        $token = sanitize_text_field($token);
        $client_ip = cpp_get_client_ip();
        
        // Get session with gift code duration
        $session = $wpdb->get_row($wpdb->prepare(
            "SELECT s.*, g.duration_minutes 
             FROM {$wpdb->prefix}cpp_sessions s
             LEFT JOIN {$wpdb->prefix}cpp_giftcodes g ON s.code = g.code
             WHERE s.secure_token = %s 
             AND s.status = 'active' 
             AND s.expires_at > NOW()
             LIMIT 1",
            $token
        ));
        
        if (!$session) {
            return null;
        }
        
        // IP binding validation (timing-safe comparison)
        if (!hash_equals($session->client_ip, $client_ip)) {
            $this->log_security_event('ip_mismatch', [
                'session_id' => $session->session_id,
                'expected_ip' => $session->client_ip,
                'provided_ip' => $client_ip,
            ]);
            return null;
        }
        
        return $session;
    }
    
    /**
     * Create session from validated gift code
     * Following security pattern: secure token generation + HttpOnly cookie
     *
     * @param string $code Gift code
     * @param int $duration_minutes Session duration
     * @return array Session data or error
     */
    public function create_session($code, $duration_minutes) {
        global $wpdb;
        
        $session_token = cpp_generate_session_token();
        $client_ip = cpp_get_client_ip();
        $expires_at = gmdate('Y-m-d H:i:s', time() + ($duration_minutes * 60));
        
        // Insert session with prepared statement
        $result = $wpdb->insert(
            $wpdb->prefix . 'cpp_sessions',
            [
                'code' => sanitize_text_field($code),
                'secure_token' => $session_token,
                'client_ip' => $client_ip,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'expires_at' => $expires_at,
                'status' => 'active',
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s']
        );
        
        if (!$result) {
            return [
                'success' => false,
                'message' => __('Failed to create session.', 'content-protect-pro'),
            ];
        }
        
        $session_id = $wpdb->insert_id;
        
        // Set HttpOnly cookie (CSRF protection)
        $cookie_expires = time() + ($duration_minutes * 60);
        cpp_set_secure_cookie('cpp_session_token', $session_token, $cookie_expires);
        
        // Log session creation
        $this->log_analytics('session_created', [
            'session_id' => $session_id,
            'code' => $code,
            'duration_minutes' => $duration_minutes,
        ]);
        
        return [
            'success' => true,
            'session_id' => $session_id,
            'session_token' => $session_token,
            'expires_at' => $expires_at,
        ];
    }
    
    /**
     * Invalidate session
     *
     * @param string $token Session token
     * @return bool Success
     */
    public function invalidate_session($token) {
        global $wpdb;
        
        $result = $wpdb->update(
            $wpdb->prefix . 'cpp_sessions',
            ['status' => 'invalidated'],
            ['secure_token' => sanitize_text_field($token)],
            ['%s'],
            ['%s']
        );
        
        if ($result) {
            // Clear cookie
            cpp_set_secure_cookie('cpp_session_token', '', time() - 3600);
            
            $this->log_analytics('session_invalidated', ['token' => $token]);
        }
        
        return (bool) $result;
    }
    
    /**
     * Clean expired sessions (called by daily cron)
     *
     * @return int Number of sessions cleaned
     */
    public function cleanup_expired_sessions() {
        global $wpdb;
        
        $result = $wpdb->query(
            "UPDATE {$wpdb->prefix}cpp_sessions 
             SET status = 'expired' 
             WHERE status = 'active' 
             AND expires_at < NOW()"
        );
        
        if ($result) {
            $this->log_analytics('sessions_cleaned', ['count' => $result]);
        }
        
        return (int) $result;
    }
    
    /**
     * Log security event
     *
     * @param string $event_type Event type
     * @param array $metadata Event data
     */
    private function log_security_event($event_type, $metadata = []) {
        if (!class_exists('CPP_Analytics')) {
            return;
        }
        
        $analytics = new CPP_Analytics();
        $analytics->log_event('security_' . $event_type, 'session', '', $metadata);
    }
    
    /**
     * Log analytics event
     *
     * @param string $event_type Event type
     * @param array $metadata Event data
     */
    private function log_analytics($event_type, $metadata = []) {
        if (!class_exists('CPP_Analytics')) {
            return;
        }
        
        $analytics = new CPP_Analytics();
        $analytics->log_event($event_type, 'session', '', $metadata);
    }
}