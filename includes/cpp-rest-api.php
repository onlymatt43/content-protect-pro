/**
 * REST API Endpoints
 * 
 * Implements token-based authentication flow from copilot-instructions.md:
 * 1. POST /redeem - Validates code, creates session, sets cookie
 * 2. POST /request-playback - Returns playback URL or embed HTML
 *
 * @package Content_Protect_Pro
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register REST API routes
 */
add_action('rest_api_init', function() {
    // Redeem gift code endpoint
    register_rest_route('smartvideo/v1', '/redeem', [
        'methods' => 'POST',
        'callback' => 'cpp_rest_redeem_code',
        'permission_callback' => '__return_true', // Public endpoint
        'args' => [
            'code' => [
                'required' => true,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'validate_callback' => function($value) {
                    return !empty($value) && strlen($value) <= 50;
                },
            ],
        ],
    ]);
    
    // Request video playback endpoint
    register_rest_route('smartvideo/v1', '/request-playback', [
        'methods' => 'POST',
        'callback' => 'cpp_rest_request_playback',
        'permission_callback' => 'cpp_rest_validate_session',
        'args' => [
            'video_id' => [
                'required' => true,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
        ],
    ]);
});

/**
 * Redeem gift code
 * Creates session and sets HttpOnly cookie
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response
 */
function cpp_rest_redeem_code($request) {
    $code = $request->get_param('code');
    $client_ip = cpp_get_client_ip();
    
    // Rate limiting check
    if (!cpp_check_rate_limit($client_ip, 'redeem')) {
        return new WP_REST_Response([
            'success' => false,
            'message' => __('Too many attempts. Please try again later.', 'content-protect-pro'),
        ], 429);
    }
    
    // Validate gift code
    if (!class_exists('CPP_Giftcode_Manager')) {
        require_once CPP_PLUGIN_DIR . 'includes/class-cpp-giftcode-manager.php';
    }
    
    $manager = new CPP_Giftcode_Manager();
    $validation = $manager->validate_code($code);
    
    if (!$validation['valid']) {
        // Log failed attempt
        cpp_log_analytics('giftcode_validation_failed', 'giftcode', $code, [
            'reason' => $validation['message'],
            'ip' => $client_ip,
        ]);
        
        return new WP_REST_Response([
            'success' => false,
            'message' => $validation['message'],
        ], 403);
    }
    
    // Create session
    global $wpdb;
    
    $session_token = cpp_generate_session_token();
    $expires_at = gmdate('Y-m-d H:i:s', time() + ($validation['duration_minutes'] * 60));
    
    $wpdb->insert(
        $wpdb->prefix . 'cpp_sessions',
        [
            'code' => $code,
            'secure_token' => $session_token,
            'client_ip' => $client_ip,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'expires_at' => $expires_at,
            'status' => 'active',
        ],
        ['%s', '%s', '%s', '%s', '%s', '%s']
    );
    
    $session_id = $wpdb->insert_id;
    
    // Set HttpOnly cookie
    $cookie_expires = time() + ($validation['duration_minutes'] * 60);
    cpp_set_secure_cookie('cpp_session_token', $session_token, $cookie_expires);
    
    // Log successful redemption
    cpp_log_analytics('giftcode_redeemed', 'giftcode', $code, [
        'session_id' => $session_id,
        'duration_minutes' => $validation['duration_minutes'],
        'ip' => $client_ip,
    ]);
    
    // Dispatch custom event for code validation success
    echo '<script>
            document.dispatchEvent(new CustomEvent("cpp-code-validated", {
                detail: { minutes: ' . intval($validation['duration_minutes']) . ', price: ' . intval($validation['price']) . ' }
            }));
          </script>';
    
    return new WP_REST_Response([
        'success' => true,
        'message' => __('Gift code validated successfully!', 'content-protect-pro'),
        'session' => [
            'duration_minutes' => $validation['duration_minutes'],
            'expires_at' => $expires_at,
        ],
    ], 200);
}

/**
 * Request video playback
 * Returns Presto Player embed HTML or Bunny CDN signed URL
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response
 */
function cpp_rest_request_playback($request) {
    $video_id = $request->get_param('video_id');
    $session_token = $_COOKIE['cpp_session_token'] ?? '';
    
    // Get session data
    global $wpdb;
    
    $session = $wpdb->get_row($wpdb->prepare(
        "SELECT s.*, g.duration_minutes 
         FROM {$wpdb->prefix}cpp_sessions s
         LEFT JOIN {$wpdb->prefix}cpp_giftcodes g ON s.code = g.code
         WHERE s.secure_token = %s 
         AND s.status = 'active' 
         AND s.expires_at > NOW()
         LIMIT 1",
        $session_token
    ));
    
    if (!$session) {
        return new WP_REST_Response([
            'success' => false,
            'message' => __(__('Invalid or expired session.', 'content-protect-pro'), 'content-protect-pro'),
        ], 401);
    }
    
    // Get video data
    $video = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}cpp_protected_videos 
         WHERE video_id = %s AND status = 'active' LIMIT 1",
        $video_id
    ));
    
    if (!$video) {
        return new WP_REST_Response([
            'success' => false,
            'message' => __(__('Video not found.', 'content-protect-pro'), 'content-protect-pro'),
        ], 404);
    }
    
    // Check access (required_minutes vs session duration)
    $session_minutes = (int) $session->duration_minutes;
    $required_minutes = (int) $video->required_minutes;
    
    if ($session_minutes < $required_minutes) {
        return new WP_REST_Response([
            'success' => false,
            'message' => sprintf(
                __('This video requires %d minutes of access. Your session only has %d minutes.', 'content-protect-pro'),
                $required_minutes,
                $session_minutes
            ),
            'required_minutes' => $required_minutes,
            'session_minutes' => $session_minutes,
        ], 403);
    }
    
    // Generate playback based on integration type
    $response_data = [];
    
    if ($video->integration_type === 'presto' && !empty($video->presto_player_id)) {
        // Presto Player embed
        $presto_id = absint($video->presto_player_id);
        $embed_html = do_shortcode('[presto_player id="' . $presto_id . '"]');
        
        if (!empty($embed_html)) {
            $response_data = [
                'type' => 'embed',
                'provider' => 'presto',
                'embed_html' => $embed_html,
                'presto_player_id' => $presto_id,
            ];
        }
    } elseif ($video->integration_type === 'bunny' && !empty($video->direct_url)) {
        // Bunny CDN signed URL (legacy support)
        if (!class_exists('CPP_Bunny_Integration')) {
            require_once CPP_PLUGIN_DIR . 'includes/class-cpp-bunny-integration.php';
        }
        
        $bunny = new CPP_Bunny_Integration();
        $signed_url = $bunny->generate_signed_url($video->direct_url, 3600);
        
        if ($signed_url) {
            $response_data = [
                'type' => 'url',
                'provider' => 'bunny',
                'playback_url' => $signed_url,
                'expires_in' => 3600,
            ];
        }
    } elseif (!empty($video->direct_url)) {
        // Direct URL fallback
        $response_data = [
            'type' => 'url',
            'provider' => 'direct',
            'playback_url' => esc_url($video->direct_url),
        ];
    }
    
    if (empty($response_data)) {
        return new WP_REST_Response([
            'success' => false,
            'message' => __(__('Unable to generate video access.', 'content-protect-pro'), 'content-protect-pro'),
        ], 500);
    }
    
    // Log playback request
    cpp_log_analytics('video_playback_requested', 'video', $video_id, [
        'session_id' => $session->session_id,
        'integration_type' => $video->integration_type,
    ]);
    
    return new WP_REST_Response([
        'success' => true,
        'data' => $response_data,
    ], 200);
}

/**
 * Validate session cookie (permission callback)
 *
 * @return bool
 */
function cpp_rest_validate_session() {
    if (empty($_COOKIE['cpp_session_token'])) {
        return false;
    }
    
    $token = sanitize_text_field($_COOKIE['cpp_session_token']);
    $client_ip = cpp_get_client_ip();
    
    global $wpdb;
    
    $session = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}cpp_sessions 
         WHERE secure_token = %s 
         AND status = 'active' 
         AND expires_at > NOW() 
         LIMIT 1",
        $token
    ));
    
    if (!$session) {
        return false;
    }
    
    // Verify IP binding (timing-safe comparison)
    return hash_equals($session->client_ip, $client_ip);
}

/**
 * Rate limiting helper
 *
 * @param string $ip Client IP
 * @param string $action Action type
 * @return bool Allowed
 */
function cpp_check_rate_limit($ip, $action) {
    $transient_key = 'cpp_rate_limit_' . md5($ip . $action);
    $attempts = get_transient($transient_key);
    
    if ($attempts === false) {
        set_transient($transient_key, 1, 5 * MINUTE_IN_SECONDS);
    set_transient($transient_key, $attempts + 1, 5 * MINUTE_IN_SECONDS);
    return true;
}   
    if ($attempts >= 50) {
        return false;
    }
    
    return true;
}

/**
 * Log analytics event helper
 *  
 * @param string $event_type Event type
 * @param string $object_type Object type
 * @param string $object_id Object ID
 * @param array $metadata Additional data
 */
function cpp_log_analytics($event_type, $object_type, $object_id, $metadata = []) {
    if (!class_exists('CPP_Analytics')) {
        return;
    }
    
    $analytics = new CPP_Analytics();
    $analytics->log_event($event_type, $object_type, $object_id, $metadata);
}

register_activation_hook(__FILE__, 'omc_activate');
function omc_activate() {e DB tables
    // Create tables if needed', '1.0.0');
    global $wpdb;ts::should_load()) return;    $table = $wpdb->prefix . 'omc_analytics'; $table = $wpdb->prefix . 'omc_analytics';        $sql = "CREATE TABLE IF NOT EXISTS $table ( $sql = "CREATE TABLE IF NOT EXISTS $table (        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,0) unsigned NOT NULL AUTO_INCREMENT,        merchant_label varchar(100),,        clicked_at datetime DEFAULT CURRENT_TIMESTAMP,        PRIMARY KEY (id)    ) {$wpdb->get_charset_collate()};";   ) {$wpdb->get_charset_collate()};";            require_once(ABSPATH . 'wpinc/admin/includes/upgrade.php');ABSPATH . 'wpinc/admin/includes/upgrade.php');    dbDelta($sql);}}* Script loading class *//** * Script loading class */class OMC_Scripts {    private static $loaded = false;    public static function needs_js() { self::$loaded = true; }    public static function should_load() { return self::$loaded; }}// In shortcodes:OMC_Scripts::needs_js();// In wp_footer:if (!OMC_Scripts::should_load()) return;/**ded = false; * Script loading class public static function needs_js() { self::$loaded = true; } */unction should_load() { return self::$loaded; }class OMC_Scripts {    private static $loaded = false;    public static function needs_js() { self::$loaded = true; }    public static function should_load() { return self::$loaded; }MC_Scripts::needs_js();}// In shortcodes:_load()) return;OMC_Scripts::needs_js();// In wp_footer:if (!OMC_Scripts::should_load()) return;