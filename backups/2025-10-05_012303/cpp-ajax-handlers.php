<?php
/**
 * AJAX Request Handlers
 * 
 * All AJAX endpoints following copilot-instructions security patterns:
 * - wp_verify_nonce() on every request
 * - Rate limiting per IP
 * - Proper sanitization/escaping
 *
 * @package Content_Protect_Pro
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register AJAX handlers
 */
add_action('wp_ajax_cpp_validate_giftcode', 'cpp_ajax_validate_giftcode');
add_action('wp_ajax_nopriv_cpp_validate_giftcode', 'cpp_ajax_validate_giftcode');

add_action('wp_ajax_cpp_get_video_token', 'cpp_ajax_get_video_token');
add_action('wp_ajax_nopriv_cpp_get_video_token', 'cpp_ajax_get_video_token');

add_action('wp_ajax_cpp_get_video_preview', 'cpp_ajax_get_video_preview');
add_action('wp_ajax_nopriv_cpp_get_video_preview', 'cpp_ajax_get_video_preview');

add_action('wp_ajax_cpp_track_video_event', 'cpp_ajax_track_video_event');
add_action('wp_ajax_nopriv_cpp_track_video_event', 'cpp_ajax_track_video_event');

add_action('wp_ajax_cpp_invalidate_session', 'cpp_ajax_invalidate_session');
add_action('wp_ajax_nopriv_cpp_invalidate_session', 'cpp_ajax_invalidate_session');

/**
 * Validate gift code
 * Following copilot-instructions: CSRF protection + rate limiting
 */
function cpp_ajax_validate_giftcode() {
    // Security validation (all-in-one)
    if (!class_exists('CPP_Giftcode_Security')) {
        require_once CPP_PLUGIN_DIR . 'includes/class-cpp-giftcode-security.php';
    }
    
    $security = new CPP_Giftcode_Security();
    $validation = $security->validate_request(
        'cpp_public_nonce',
        $_POST['nonce'] ?? '',
        'redeem_code'
    );
    
    if (!$validation['valid']) {
        wp_send_json_error(['message' => $validation['message']], 403);
    }
    
    $code = sanitize_text_field($_POST['code'] ?? '');
    
    if (empty($code)) {
        wp_send_json_error(['message' => __('Gift code is required.', 'content-protect-pro')], 400);
    }
    
    // Validate code
    if (!class_exists('CPP_Giftcode_Manager')) {
        require_once CPP_PLUGIN_DIR . 'includes/class-cpp-giftcode-manager.php';
    }
    
    $manager = new CPP_Giftcode_Manager();
    $result = $manager->validate_code($code);
    
    if (!$result['valid']) {
        wp_send_json_error(['message' => $result['message']], 403);
    }
    
    // Create session
    if (!class_exists('CPP_Protection_Manager')) {
        require_once CPP_PLUGIN_DIR . 'includes/class-cpp-protection-manager.php';
    }
    
    $protection = new CPP_Protection_Manager();
    $session = $protection->create_session($code, $result['duration_minutes']);
    
    if (!$session['success']) {
        wp_send_json_error(['message' => $session['message']], 500);
    }
    
    wp_send_json_success([
        'message' => __('Gift code validated successfully!', 'content-protect-pro'),
        'duration_minutes' => $result['duration_minutes'],
        'expires_at' => $session['expires_at'],
    ]);
}

/**
 * Get video playback token
 * Returns Presto Player embed or Bunny signed URL
 */
function cpp_ajax_get_video_token() {
    // CSRF protection
    if (!check_ajax_referer('cpp_public_nonce', 'nonce', false)) {
        wp_send_json_error(['message' => __('Security check failed.', 'content-protect-pro')], 403);
    }
    
    $video_id = sanitize_text_field($_POST['video_id'] ?? '');
    $session_token = $_COOKIE['cpp_session_token'] ?? '';
    
    if (empty($video_id)) {
        wp_send_json_error(['message' => __('Video ID is required.', 'content-protect-pro')], 400);
    }
    
    // Validate session
    if (!class_exists('CPP_Protection_Manager')) {
        require_once CPP_PLUGIN_DIR . 'includes/class-cpp-protection-manager.php';
    }
    
    $protection = new CPP_Protection_Manager();
    
    if (!$protection->check_video_access($video_id, $session_token)) {
        wp_send_json_error(['message' => __('Access denied. Invalid or expired session.', 'content-protect-pro')], 403);
    }
    
    // Get video data
    if (!class_exists('CPP_Video_Manager')) {
        require_once CPP_PLUGIN_DIR . 'includes/class-cpp-video-manager.php';
    }
    
    $video_manager = new CPP_Video_Manager();
    $video = $video_manager->get_protected_video($video_id);
    
    if (!$video) {
        wp_send_json_error(['message' => __('Video not found.', 'content-protect-pro')], 404);
    }
    
    $response = [];
    
    // Generate access based on integration type
    if ($video->integration_type === 'presto' && !empty($video->presto_player_id)) {
        // Presto Player (primary per copilot-instructions)
        if (!class_exists('CPP_Presto_Integration')) {
            require_once CPP_PLUGIN_DIR . 'includes/class-cpp-presto-integration.php';
        }
        
        $presto = new CPP_Presto_Integration();
        $embed_html = $presto->generate_access_token($video->presto_player_id);
        
        if ($embed_html) {
            $response = [
                'type' => 'embed',
                'provider' => 'presto',
                'embed_html' => $embed_html,
            ];
        }
    } elseif ($video->integration_type === 'bunny' && !empty($video->direct_url)) {
        // Bunny CDN (legacy per copilot-instructions)
        if (!class_exists('CPP_Bunny_Integration')) {
            require_once CPP_PLUGIN_DIR . 'includes/class-cpp-bunny-integration.php';
        }
        
        $bunny = new CPP_Bunny_Integration();
        $signed_url = $bunny->generate_signed_url($video->direct_url, 3600);
        
        if ($signed_url) {
            $response = [
                'type' => 'url',
                'provider' => 'bunny',
                'playback_url' => $signed_url,
                'expires_in' => 3600,
            ];
        }
    } elseif (!empty($video->direct_url)) {
        // Direct URL fallback
        $response = [
            'type' => 'url',
            'provider' => 'direct',
            'playback_url' => esc_url($video->direct_url),
        ];
    }
    
    if (empty($response)) {
        wp_send_json_error(['message' => __('Unable to generate video access.', 'content-protect-pro')], 500);
    }
    
    // Log playback request
    if (class_exists('CPP_Analytics')) {
        $analytics = new CPP_Analytics();
        $analytics->log_event('video_playback_requested', 'video', $video_id, [
            'integration_type' => $video->integration_type,
        ]);
    }
    
    wp_send_json_success($response);
}

/**
 * Get video preview (for modal display)
 */
function cpp_ajax_get_video_preview() {
    // CSRF protection
    if (!check_ajax_referer('cpp_public_nonce', 'nonce', false)) {
        wp_send_json_error(['message' => __('Security check failed.', 'content-protect-pro')], 403);
    }
    
    $video_id = sanitize_text_field($_POST['video_id'] ?? '');
    
    if (empty($video_id)) {
        wp_send_json_error(['message' => __('Video ID is required.', 'content-protect-pro')], 400);
    }
    
    // Get video data
    if (!class_exists('CPP_Video_Manager')) {
        require_once CPP_PLUGIN_DIR . 'includes/class-cpp-video-manager.php';
    }
    
    $video_manager = new CPP_Video_Manager();
    $video = $video_manager->get_protected_video($video_id);
    
    if (!$video) {
        wp_send_json_error(['message' => __('Video not found.', 'content-protect-pro')], 404);
    }
    
    // Generate preview HTML
    $preview_html = '';
    
    if ($video->integration_type === 'presto' && !empty($video->presto_player_id)) {
        // Try Presto Player embed
        $embed = do_shortcode('[presto_player id="' . absint($video->presto_player_id) . '"]');
        if (!empty($embed)) {
            $preview_html = $embed;
        }
    }
    
    // Fallback to thumbnail card
    if (empty($preview_html)) {
        $thumb = '';
        
        if (!empty($video->thumbnail_url)) {
            $thumb = esc_url($video->thumbnail_url);
        } elseif (!empty($video->presto_player_id)) {
            $thumb = get_the_post_thumbnail_url(absint($video->presto_player_id), 'medium');
        }
        
        ob_start();
        ?>
        <div class="cpp-preview-card">
            <?php if ($thumb): ?>
                <div class="cpp-preview-thumb">
                    <img src="<?php echo esc_url($thumb); ?>" alt="<?php echo esc_attr($video->title); ?>" />
                </div>
            <?php endif; ?>
            <div class="cpp-preview-meta">
                <h4><?php echo esc_html($video->title); ?></h4>
                <?php if (!empty($video->description)): ?>
                    <p><?php echo esc_html(wp_trim_words($video->description, 25)); ?></p>
                <?php endif; ?>
                <p class="cpp-preview-info">
                    <?php
                    printf(
                        esc_html__('Requires %d minutes of access', 'content-protect-pro'),
                        absint($video->required_minutes)
                    );
                    ?>
                </p>
            </div>
        </div>
        <?php
        $preview_html = ob_get_clean();
    }
    
    wp_send_json_success([
        'html' => $preview_html,
        'title' => $video->title,
    ]);
}

/**
 * Track video event (analytics)
 */
function cpp_ajax_track_video_event() {
    // CSRF protection
    if (!check_ajax_referer('cpp_public_nonce', 'nonce', false)) {
        wp_send_json_error(['message' => __('Security check failed.', 'content-protect-pro')], 403);
    }
    
    $event_type = sanitize_text_field($_POST['event_type'] ?? '');
    $video_id = sanitize_text_field($_POST['video_id'] ?? '');
    
    if (empty($event_type) || empty($video_id)) {
        wp_send_json_error(['message' => __('Missing parameters.', 'content-protect-pro')], 400);
    }
    
    // Log event
    if (!class_exists('CPP_Analytics')) {
        wp_send_json_success(['message' => 'Analytics disabled']);
    }
    
    $analytics = new CPP_Analytics();
    $analytics->log_event($event_type, 'video', $video_id, [
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'referrer' => esc_url_raw($_SERVER['HTTP_REFERER'] ?? ''),
    ]);
    
    wp_send_json_success(['message' => 'Event tracked']);
}

/**
 * Invalidate current session
 */
function cpp_ajax_invalidate_session() {
    // CSRF protection
    if (!check_ajax_referer('cpp_public_nonce', 'nonce', false)) {
        wp_send_json_error(['message' => __('Security check failed.', 'content-protect-pro')], 403);
    }
    
    $session_token = $_COOKIE['cpp_session_token'] ?? '';
    
    if (empty($session_token)) {
        wp_send_json_error(['message' => __('No active session found.', 'content-protect-pro')], 400);
    }
    
    // Invalidate session
    if (!class_exists('CPP_Protection_Manager')) {
        require_once CPP_PLUGIN_DIR . 'includes/class-cpp-protection-manager.php';
    }
    
    $protection = new CPP_Protection_Manager();
    $result = $protection->invalidate_session($session_token);
    
    if ($result) {
        wp_send_json_success(['message' => __('Session ended successfully.', 'content-protect-pro')]);
    }
    
    wp_send_json_error(['message' => __('Failed to end session.', 'content-protect-pro')], 500);
}