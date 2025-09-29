<?php
/**
 * Content protection and video access management
 *
 * @package Content_Protect_Pro
 * @since   1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class CPP_Protection_Manager {

    /**
     * Initialize protection manager
     *
     * @since 1.0.0
     */
    public function __construct() {
        add_action('init', array($this, 'init'));
    }

    /**
     * Initialize protection hooks
     *
     * @since 1.0.0
     */
    public function init() {
        // Content protection hooks
        add_action('wp_ajax_cpp_validate_video_access', array($this, 'validate_video_access'));
        add_action('wp_ajax_nopriv_cpp_validate_video_access', array($this, 'validate_video_access'));
        
        // Protection shortcodes
        add_shortcode('cpp_protected_content', array($this, 'protected_content_shortcode'));
        add_shortcode('cpp_video_player', array($this, 'video_player_shortcode'));
        
        // Content filtering
        add_filter('the_content', array($this, 'filter_protected_content'), 10);
    }

    /**
     * Validate video access via AJAX
     *
     * @since 1.0.0
     */
    public function validate_video_access() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'cpp_video_access')) {
            wp_send_json_error(array(
                'message' => __('Security verification failed.', 'content-protect-pro')
            ));
        }

        $video_id = sanitize_text_field($_POST['video_id']);
        $gift_code = sanitize_text_field($_POST['gift_code']);
        
        if (empty($video_id) || empty($gift_code)) {
            wp_send_json_error(array(
                'message' => __('Missing required parameters.', 'content-protect-pro')
            ));
        }

    // Validate gift code first
        $giftcode_manager = new CPP_Giftcode_Manager();
        $validation = $giftcode_manager->validate_code($gift_code);
        
        if (!$validation['valid']) {
            wp_send_json_error(array(
                'message' => $validation['message']
            ));
        }

    // Check video access permissions (pass full validation result for minutes/session)
    $access = $this->check_video_access($video_id, $gift_code, $validation);
        
        if ($access['allowed']) {
            // Generate access token
            $token = $this->generate_access_token($video_id, $gift_code);
            
            wp_send_json_success(array(
                'message' => __('Access granted.', 'content-protect-pro'),
                'token' => $token,
                'video_url' => $access['video_url'],
                'expires' => $access['expires']
            ));
        } else {
            wp_send_json_error(array(
                'message' => $access['message']
            ));
        }
    }

    /**
     * Check if user has access to specific video
     *
     * @param string $video_id  Video ID
     * @param string $gift_code Gift code
     * @param array  $code_data Gift code data
     * @return array Access result
     * @since 1.0.0
     */
    public function check_video_access($video_id, $gift_code, $code_data) {
        global $wpdb;
        
        // Get video details
        $table_name = $wpdb->prefix . 'cpp_protected_videos';
        $video = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE video_id = %s AND status = 'active'",
                $video_id
            )
        );
        
        if (!$video) {
            return array(
                'allowed' => false,
                'message' => __('Video not found or inactive.', 'content-protect-pro')
            );
        }

        // Current model: compare required minutes if set
        $required_minutes = isset($video->required_minutes) ? intval($video->required_minutes) : 0;
        $available_minutes = isset($code_data['access_duration_minutes']) ? intval($code_data['access_duration_minutes']) : 0;
        if ($required_minutes > 0 && ($available_minutes === 0 || $available_minutes < $required_minutes)) {
            return array(
                'allowed' => false,
                'message' => sprintf(
                    __('Gift code provides %s minutes, but %s minutes are required for this video.', 'content-protect-pro'),
                    $available_minutes,
                    $required_minutes
                )
            );
        }

        // Check usage limits
        if ($video->max_uses && $video->usage_count >= $video->max_uses) {
            return array(
                'allowed' => false,
                'message' => __('Video has reached maximum usage limit.', 'content-protect-pro')
            );
        }

        // Generate video URL based on integration
        $video_url = $this->get_protected_video_url($video);
        
        return array(
            'allowed' => true,
            'video_url' => $video_url,
            'expires' => time() + (24 * 3600),
            'message' => __('Access granted.', 'content-protect-pro')
        );
    }

    /**
     * Get protected video URL based on integration settings
     *
     * @param object $video Video object
     * @return string Protected video URL
     * @since 1.0.0
     */
    public function get_protected_video_url($video) {
        $settings = get_option('cpp_integration_settings', array());
        
        // Check for Bunny CDN integration (current schema)
        if (!empty($settings['bunny_enabled']) && !empty($video->bunny_library_id)) {
            if (!class_exists('CPP_Bunny_Integration')) {
                require_once CPP_PLUGIN_DIR . 'includes/class-cpp-bunny-integration.php';
            }
            $bunny = new CPP_Bunny_Integration();
            $signed = $bunny->generate_signed_url($video->video_id);
            if ($signed) {
                return $signed;
            }
        }
        
        // Check for Presto Player integration (current schema)
        if (!empty($settings['presto_enabled']) && !empty($video->presto_player_id)) {
            // Return Presto player page (embed handled elsewhere)
            return home_url("/presto-player/{$video->presto_player_id}/");
        }
        
        // Fallback to direct URL if available
        return !empty($video->direct_url) ? $video->direct_url : '';
    }

    /**
     * Generate access token for authenticated video access
     *
     * @param string $video_id  Video ID
     * @param string $gift_code Gift code
     * @return string Access token
     * @since 1.0.0
     */
    public function generate_access_token($video_id, $gift_code) {
        $payload = array(
            'video_id' => $video_id,
            'gift_code' => $gift_code,
            'ip' => $this->get_client_ip(),
            'issued_at' => time(),
            'expires_at' => time() + (2 * 3600), // 2 hour token
        );
        
        // Simple JWT-like token (you might want to use a proper JWT library)
        $header = base64_encode(json_encode(array('typ' => 'JWT', 'alg' => 'HS256')));
        $payload_encoded = base64_encode(json_encode($payload));
        
        // Create signature
        $secret = get_option('cpp_jwt_secret', wp_generate_password(32, false));
        if (get_option('cpp_jwt_secret') === false) {
            update_option('cpp_jwt_secret', $secret);
        }
        
        $signature = base64_encode(hash_hmac('sha256', $header . '.' . $payload_encoded, $secret, true));
        
        return $header . '.' . $payload_encoded . '.' . $signature;
    }

    /**
     * Validate access token
     *
     * @param string $token Access token
     * @return array Validation result
     * @since 1.0.0
     */
    public function validate_access_token($token) {
        $parts = explode('.', $token);
        
        if (count($parts) !== 3) {
            return array('valid' => false, 'message' => 'Invalid token format');
        }
        
        list($header, $payload, $signature) = $parts;
        
        // Verify signature
        $secret = get_option('cpp_jwt_secret');
        if (!$secret) {
            return array('valid' => false, 'message' => 'No secret key configured');
        }
        
        $expected_signature = base64_encode(hash_hmac('sha256', $header . '.' . $payload, $secret, true));
        
        if (!hash_equals($signature, $expected_signature)) {
            return array('valid' => false, 'message' => 'Invalid signature');
        }
        
        // Decode payload
        $payload_data = json_decode(base64_decode($payload), true);
        
        if (!$payload_data) {
            return array('valid' => false, 'message' => 'Invalid payload');
        }
        
        // Check expiration
        if ($payload_data['expires_at'] < time()) {
            return array('valid' => false, 'message' => 'Token expired');
        }
        
        // Optional: Check IP address
        if ($payload_data['ip'] !== $this->get_client_ip()) {
            // Log potential security issue but don't block (IP might change legitimately)
            do_action('cpp_token_ip_mismatch', $payload_data, $this->get_client_ip());
        }
        
        return array(
            'valid' => true,
            'data' => $payload_data
        );
    }

    /**
     * Protected content shortcode
     *
     * @param array  $atts    Shortcode attributes
     * @param string $content Shortcode content
     * @return string Rendered shortcode
     * @since 1.0.0
     */
    public function protected_content_shortcode($atts, $content = '') {
        $atts = shortcode_atts(array(
            'message' => __('Please enter a valid gift code to access this content.', 'content-protect-pro'),
        ), $atts);
        
        // Check if user has already validated access (session-based)
        if (!session_id()) { session_start(); }
        $session_codes = isset($_SESSION['cpp_validated_codes']) ? (array) $_SESSION['cpp_validated_codes'] : array();
        if (!empty($session_codes)) {
            return do_shortcode($content);
        }
        
        // Show access form
        ob_start();
        ?>
    <div class="cpp-protected-content">
            <div class="cpp-access-form">
                <p><?php echo esc_html($atts['message']); ?></p>
                <form class="cpp-giftcode-form">
                    <input type="text" name="gift_code" placeholder="<?php esc_attr_e('Enter gift code', 'content-protect-pro'); ?>" required>
                    <button type="submit"><?php esc_html_e('Access Content', 'content-protect-pro'); ?></button>
                </form>
                <div class="cpp-access-message"></div>
            </div>
            <div class="cpp-protected-content-inner" style="display: none;">
                <?php echo do_shortcode($content); ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Video player shortcode with protection
     *
     * @param array $atts Shortcode attributes
     * @return string Rendered video player
     * @since 1.0.0
     */
    public function video_player_shortcode($atts) {
        $atts = shortcode_atts(array(
            'video_id' => '',
            'width' => '100%',
            'height' => '400px',
            'poster' => '',
            'message' => __('Please enter a valid gift code to watch this video.', 'content-protect-pro'),
        ), $atts);
        
        if (empty($atts['video_id'])) {
            return '<p>' . __('No video ID specified.', 'content-protect-pro') . '</p>';
        }
        
        ob_start();
        ?>
       <div class="cpp-video-player" 
           data-video-id="<?php echo esc_attr($atts['video_id']); ?>">
            
            <div class="cpp-video-access-form">
                <?php if (!empty($atts['poster'])): ?>
                    <div class="cpp-video-poster">
                        <img src="<?php echo esc_url($atts['poster']); ?>" alt="<?php esc_attr_e('Video thumbnail', 'content-protect-pro'); ?>">
                    </div>
                <?php endif; ?>
                
                <div class="cpp-access-overlay">
                    <p><?php echo esc_html($atts['message']); ?></p>
                    <form class="cpp-video-giftcode-form">
                        <input type="text" name="gift_code" placeholder="<?php esc_attr_e('Enter gift code', 'content-protect-pro'); ?>" required>
                        <button type="submit"><?php esc_html_e('Watch Video', 'content-protect-pro'); ?></button>
                    </form>
                    <div class="cpp-video-message"></div>
                </div>
            </div>
            
            <div class="cpp-video-player-container" style="display: none;">
                <!-- Video player will be injected here after successful validation -->
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Filter content to protect based on post meta
     *
     * @param string $content Post content
     * @return string Filtered content
     * @since 1.0.0
     */
    public function filter_protected_content($content) {
        global $post;
        
        if (!$post || is_admin()) {
            return $content;
        }
        
        $is_protected = get_post_meta($post->ID, '_cpp_protected', true);
        if (!$is_protected) {
            return $content;
        }
        
        // Check if user has access (session-based)
        if (!session_id()) { session_start(); }
        $session_codes = isset($_SESSION['cpp_validated_codes']) ? (array) $_SESSION['cpp_validated_codes'] : array();
        if (!empty($session_codes)) {
            return $content;
        }
        
        // Replace content with access form
        $message = get_post_meta($post->ID, '_cpp_access_message', true);
        if (!$message) {
            $message = __('This content is protected. Please enter a valid gift code to access it.', 'content-protect-pro');
        }
        
        return sprintf('[cpp_protected_content message="%s"]%s[/cpp_protected_content]', esc_attr($message), $content);
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
     * Get protection statistics
     *
     * @return array Protection statistics
     * @since 1.0.0
     */
    public function get_protection_stats() {
        global $wpdb;
        
        $stats = array();
        
        // Get protected videos count
        $table_name = $wpdb->prefix . 'cpp_protected_videos';
        $stats['total_protected_videos'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table_name} WHERE status = 'active'"
        );
        
        // Get access attempts (from analytics if available)
        if (class_exists('CPP_Analytics')) {
            $analytics = new CPP_Analytics();
            $access_attempts = $analytics->get_analytics(array(
                'event_type' => 'video_access_attempt',
                'date_from' => date('Y-m-d', strtotime('-30 days')),
                'date_to' => date('Y-m-d'),
            ));
            
            $stats['access_attempts_30d'] = count($access_attempts['events']);
        } else {
            $stats['access_attempts_30d'] = 0;
        }
        
        return $stats;
    }
}