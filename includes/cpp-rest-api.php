<?php
/**
 * Minimal REST API for Content Protect Pro
 * Provides endpoints used by the front-end library: /redeem and /request-playback
 */

if (!defined('ABSPATH')) exit;

add_action('rest_api_init', function(){
    register_rest_route('smartvideo/v1', '/redeem', array(
        'methods' => 'POST',
        'callback' => 'cpp_rest_redeem',
        'permission_callback' => '__return_true',
    ));

    register_rest_route('smartvideo/v1', '/request-playback', array(
        'methods' => 'POST',
        'callback' => 'cpp_rest_request_playback',
        'permission_callback' => '__return_true',
    ));
});

function cpp_rest_redeem($request) {
    $params = $request->get_json_params();
    $code = isset($params['code']) ? sanitize_text_field($params['code']) : '';
    if (!$code) {
        return new WP_REST_Response(array('error' => 'code_missing'), 400);
    }

    // Use existing giftcode manager
    if (!class_exists('CPP_Giftcode_Manager')) {
        require_once CPP_PLUGIN_DIR . 'includes/class-cpp-giftcode-manager.php';
    }
    $mgr = new CPP_Giftcode_Manager();
    $res = $mgr->validate_code($code);
    if (!$res['valid']) {
        return new WP_REST_Response(array('error' => 'invalid_code'), 404);
    }

    // On success, create a short-lived server-side playback token (RESTful flow)
    global $wpdb;
    // Ensure migrations ran (creates tokens table if missing)
    if (!class_exists('CPP_Migrations')) {
        require_once CPP_PLUGIN_DIR . 'includes/class-cpp-migrations.php';
    }
    if (class_exists('CPP_Migrations')) {
        CPP_Migrations::maybe_migrate();
    }

    $integration_settings = get_option('cpp_integration_settings', array());
    $expiry_seconds = isset($integration_settings['token_expiry']) ? intval($integration_settings['token_expiry']) : 900;
    $expires_at = time() + max(60, $expiry_seconds);

    $token = bin2hex(random_bytes(32));
    $user_id = function_exists('get_current_user_id') ? get_current_user_id() : 0;
    $table = $wpdb->prefix . 'cpp_tokens';
    $rows = $wpdb->insert($table, array(
        'token' => $token,
        'user_id' => $user_id,
        'video_id' => '', // redeem is not video-specific; token later tied to video on request
        'expires_at' => date('Y-m-d H:i:s', $expires_at),
    ), array('%s','%d','%s','%s'));

    if ($rows === false) {
        return new WP_REST_Response(array('error' => 'token_create_failed'), 500);
    }

    // Set HttpOnly secure cookie for front-end to send with requests
    $secure = is_ssl();
    $cookie_path = defined('COOKIEPATH') ? COOKIEPATH : '/';
    $cookie_domain = defined('COOKIE_DOMAIN') ? COOKIE_DOMAIN : '';
    setcookie('cpp_playback_token', $token, $expires_at, $cookie_path, $cookie_domain, $secure, true);

    return array('status' => 'ok', 'token' => $token, 'expires_at' => date(DATE_ATOM, $expires_at));
}

function cpp_rest_request_playback($request) {
    $params = $request->get_json_params();
    $video_id = isset($params['video_id']) ? intval($params['video_id']) : 0;
    if (!$video_id) return new WP_REST_Response(array('error' => 'video_missing'), 400);

    // If video is marked public, return embed
    $is_public = get_post_meta($video_id, '_cpp_public', true) === '1';
    if ($is_public) {
        $presto_id = get_post_meta($video_id, '_cpp_presto_id', true) ?: get_post_meta($video_id, '_presto_id', true);
        if (!$presto_id) return new WP_REST_Response(array('error' => 'no_presto_id'), 500);
        return array('status' => 'ok', 'embed' => do_shortcode('[presto_player id="' . esc_attr($presto_id) . '"]'));
    }

    // Validate access: prefer server-side token; fallback to legacy PHP session codes
    global $wpdb;
    $token = '';
    // Token can be provided in JSON (for programmatic clients) or via cookie
    if (!empty($params['token'])) {
        $token = sanitize_text_field($params['token']);
    } elseif (isset($_COOKIE['cpp_playback_token'])) {
        $token = sanitize_text_field($_COOKIE['cpp_playback_token']);
    }

    $has_valid_token = false;
    if ($token) {
        $table = $wpdb->prefix . 'cpp_tokens';
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE token = %s LIMIT 1", $token));
        if ($row) {
            $now = current_time('timestamp');
            $expires_ts = strtotime($row->expires_at);
            if ($expires_ts >= time()) {
                // Optional IP binding
                $security_settings = get_option('cpp_security_settings', array());
                $ip_binding = !empty($security_settings['ip_binding']);
                if ($ip_binding && !empty($row->ip_address)) {
                    $client_ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
                    if ($client_ip !== $row->ip_address) {
                        // Token bound to different IP
                        $has_valid_token = false;
                    } else {
                        $has_valid_token = true;
                    }
                } else {
                    $has_valid_token = true;
                }
            }
        }
    }

    // Legacy session fallback (if tokens not used)
    if (!$has_valid_token) {
        if (!session_id()) session_start();
        $session_codes = isset($_SESSION['cpp_validated_codes']) ? $_SESSION['cpp_validated_codes'] : array();
        $required_minutes = intval(get_post_meta($video_id, '_cpp_required_minutes', true) ?: 0);
        if ($required_minutes > 0 && empty($session_codes)) {
            return new WP_REST_Response(array('error' => 'no_token'), 401);
        }
    }

    // By default return embed. If signed URLs are enabled, return playback_url.
    $integration_settings = get_option('cpp_integration_settings', array());
    $use_signed = isset($integration_settings['signed_urls']) && $integration_settings['signed_urls'] ? true : false;
    $presto_id = get_post_meta($video_id, '_cpp_presto_id', true) ?: get_post_meta($video_id, '_presto_id', true);
    if (!$presto_id) return new WP_REST_Response(array('error' => 'no_presto_id'), 500);

    if ($use_signed) {
        // Prefer Bunny signed URL if Bunny integration is configured for this video
        $integration_settings = get_option('cpp_integration_settings', array());
        $bunny_enabled = !empty($integration_settings['bunny_enabled']);

        if ($bunny_enabled && class_exists('CPP_Bunny_Integration')) {
            global $wpdb;
            $table = $wpdb->prefix . 'cpp_protected_videos';
            // Try to find a protected video entry by presto_player_id or video_id
            $row = $wpdb->get_row($wpdb->prepare("SELECT bunny_library_id FROM $table WHERE presto_player_id = %s OR video_id = %s LIMIT 1", $presto_id, $video_id));
            $bunny_vid = $row && !empty($row->bunny_library_id) ? $row->bunny_library_id : '';
            if ($bunny_vid) {
                // Use Bunny integration to get a signed URL
                if (!class_exists('CPP_Bunny_Integration')) {
                    require_once CPP_PLUGIN_DIR . 'includes/class-cpp-bunny-integration.php';
                }
                $bunny = new CPP_Bunny_Integration();
                // Determine expiry: use video settings token_expiry or default 900s
                $video_settings = get_option('cpp_video_settings', array());
                $expiry_seconds = isset($video_settings['token_expiry']) ? intval($video_settings['token_expiry']) : 900;
                $expires = time() + max(60, $expiry_seconds);

                // Security options (IP binding etc.)
                $security_settings = get_option('cpp_security_settings', array());
                $ip_binding = !empty($security_settings['ip_binding']);
                $opts = array(
                    'ip_restriction' => $ip_binding,
                );

                $signed = $bunny->generate_signed_url($bunny_vid, $expires, $opts);
                if ($signed) {
                    return array('status' => 'ok', 'playback_url' => $signed, 'expires_at' => date(DATE_ATOM, $expires), 'provider' => 'bunny');
                }
            }

        }

        // Fallback: create or reuse server-side token as a playback token and return a playback URL that includes the token
        if (empty($token)) {
            // create a short-lived token specifically for this video
            $integration_settings = get_option('cpp_integration_settings', array());
            $expiry_seconds = isset($integration_settings['token_expiry']) ? intval($integration_settings['token_expiry']) : 900;
            $expires_at = time() + max(60, $expiry_seconds);
            $token = bin2hex(random_bytes(32));
            $user_id = function_exists('get_current_user_id') ? get_current_user_id() : 0;
            $table = $wpdb->prefix . 'cpp_tokens';
            $ip_addr = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
            $wpdb->insert($table, array(
                'token' => $token,
                'user_id' => $user_id,
                'video_id' => $video_id,
                'expires_at' => date('Y-m-d H:i:s', $expires_at),
                'ip_address' => $ip_addr,
            ), array('%s','%d','%s','%s','%s'));
        } else {
            $expires_at = isset($row->expires_at) ? strtotime($row->expires_at) : (time() + 900);
        }

        // Return a playback URL that includes the token as a query param
        $playback_url = add_query_arg(array('token' => $token, 'vid' => $video_id), home_url('/_cpp_playback'));
        return array('status' => 'ok', 'playback_url' => $playback_url, 'expires_at' => date(DATE_ATOM, $expires_at), 'provider' => 'token');
    }

    // Fallback: return embed HTML (less secure but compatible)
    $embed = do_shortcode('[presto_player id="' . esc_attr($presto_id) . '"]');
    return array('status' => 'ok', 'embed' => $embed);
}

// Simple front controller for playback URLs generated by the signed stub
add_action('init', function(){
    $path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
    if ($path !== '_cpp_playback') return;

    // Extract query params
    // Support token-based playback (preferred) or fallback to HMAC stub for backward compatibility
    $token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';
    $vid = isset($_GET['vid']) ? intval($_GET['vid']) : 0;

    // If token provided, validate against tokens table
    if ($token) {
        global $wpdb;
        $table = $wpdb->prefix . 'cpp_tokens';
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE token = %s LIMIT 1", $token));
        if (!$row) { status_header(403); echo 'Invalid token'; exit; }
        if (strtotime($row->expires_at) < time()) { status_header(403); echo 'Token expired'; exit; }
        // Optional IP binding
        $security_settings = get_option('cpp_security_settings', array());
        $ip_binding = !empty($security_settings['ip_binding']);
        if ($ip_binding && !empty($row->ip_address)) {
            $client_ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
            if ($client_ip !== $row->ip_address) { status_header(403); echo 'IP mismatch'; exit; }
        }
        // Determine presto_id: if token row includes video_id, fetch presto id from videos table
        $presto_id = '';
        if (!empty($row->video_id)) {
            $videos_table = $wpdb->prefix . 'cpp_protected_videos';
            $vrow = $wpdb->get_row($wpdb->prepare("SELECT presto_player_id FROM {$videos_table} WHERE id = %s LIMIT 1", $row->video_id));
            if ($vrow && !empty($vrow->presto_player_id)) $presto_id = $vrow->presto_player_id;
        }
        // Fallback to vid param mapping
        if (empty($presto_id) && $vid) {
            $presto_id = get_post_meta($vid, '_cpp_presto_id', true) ?: get_post_meta($vid, '_presto_id', true);
        }
        if (empty($presto_id)) { status_header(500); echo 'No presto id'; exit; }

        status_header(200);
        echo '<!doctype html><html><head><meta charset="utf-8"><title>Playback</title></head><body>';
        echo do_shortcode('[presto_player id="' . esc_attr($presto_id) . '"]');
        echo '</body></html>';
        exit;
    }

    // Backward compatible HMAC stub handling
    $presto_id = isset($_GET['presto_id']) ? sanitize_text_field($_GET['presto_id']) : '';
    $exp = isset($_GET['exp']) ? intval($_GET['exp']) : 0;
    $sig = isset($_GET['sig']) ? sanitize_text_field($_GET['sig']) : '';

    if (!$presto_id || !$vid || !$exp || !$sig) {
        status_header(400);
        echo 'Invalid playback URL';
        exit;
    }

    if ($exp < time()) {
        status_header(403);
        echo 'Playback URL expired';
        exit;
    }

    $secret = get_option('cpp_signed_url_secret');
    if (empty($secret)) { status_header(403); echo 'Invalid signature'; exit; }

    $payload = $presto_id . '|' . $vid . '|' . $exp;
    $expected = hash_hmac('sha256', $payload, $secret);
    if (!hash_equals($expected, $sig)) {
        status_header(403);
        echo 'Invalid signature';
        exit;
    }

    // Signature valid — render a minimal page with the Presto embed (server-side)
    status_header(200);
    echo '<!doctype html><html><head><meta charset="utf-8"><title>Playback</title></head><body>';
    echo do_shortcode('[presto_player id="' . esc_attr($presto_id) . '"]');
    echo '</body></html>';
    exit;
});
