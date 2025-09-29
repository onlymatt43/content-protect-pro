<?php
/**
 * Presto Player Pro integration functionality
 *
 * @package Content_Protect_Pro
 * @since   1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class CPP_Presto_Integration {

    /**
     * Presto Player Pro plugin instance
     *
     * @var object
     */
    private $presto_player;

    /**
     * Initialize the integration
     *
     * @since 1.0.0
     */
    public function __construct() {
        add_action('plugins_loaded', array($this, 'init'), 20);
    }

    /**
     * Initialize after plugins are loaded
     *
     * @since 1.0.0
     */
    public function init() {
        if ($this->is_presto_pro_active()) {
            $this->setup_hooks();
        }
    }

    /**
     * Check if Presto Player Pro is active
     *
     * @return bool
     * @since 1.0.0
     */
    public function is_presto_pro_active() {
        return class_exists('PrestoPlayer\\Pro\\Plugin') || 
               (function_exists('is_plugin_active') && is_plugin_active('presto-player-pro/presto-player-pro.php'));
    }

    /**
     * Setup WordPress hooks
     *
     * @since 1.0.0
     */
    private function setup_hooks() {
        // Filter Presto Player settings for protection
        add_filter('presto_player_web_player_config', array($this, 'filter_player_config'), 10, 2);
        
        // Add custom video sources
        add_filter('presto_player_video_src', array($this, 'filter_video_src'), 10, 3);
        
        // Handle video access control
        add_action('presto_player_before_video_load', array($this, 'check_video_access'), 10, 2);
        
        // Add protection overlay
        add_filter('presto_player_video_html', array($this, 'add_protection_overlay'), 10, 2);
        
        // AJAX handlers for Presto integration
        add_action('wp_ajax_cpp_presto_validate_access', array($this, 'ajax_validate_access'));
        add_action('wp_ajax_nopriv_cpp_presto_validate_access', array($this, 'ajax_validate_access'));
    }

    /**
     * Filter Presto Player configuration for protected videos
     *
     * @param array $config Player configuration
     * @param int   $id     Player/video ID
     * @return array Modified configuration
     * @since 1.0.0
     */
    public function filter_player_config($config, $id) {
        $protection_settings = $this->get_video_protection_settings($id);
        
        if (!$protection_settings || !$protection_settings['enabled']) {
            return $config;
        }

        // Check if user has access
        $has_access = $this->check_user_access($id, $protection_settings);
        
        if (!$has_access) {
            // Replace video source with protection message
            $config['src'] = array();
            $config['protected'] = true;
            $config['protection_message'] = $this->get_protection_message($protection_settings);
            
            // Disable controls for protected content
            $config['controls'] = false;
            $config['autoplay'] = false;
        } else {
            // User has access, apply token protection if enabled
            if ($protection_settings['token_auth']) {
                $config['src'] = $this->get_token_protected_sources($id, $config['src']);
            }
            
            // Add tracking for authorized views
            $this->track_video_access($id);
        }

        return $config;
    }

    /**
     * Filter video source URLs for token protection
     *
     * @param string $src Video source URL
     * @param int    $id  Video ID
     * @param array  $attrs Video attributes
     * @return string Modified source URL
     * @since 1.0.0
     */
    public function filter_video_src($src, $id, $attrs) {
        $protection_settings = $this->get_video_protection_settings($id);
        
        if (!$protection_settings || !$protection_settings['token_auth']) {
            return $src;
        }

        // Check access first
        if (!$this->check_user_access($id, $protection_settings)) {
            return '';
        }

        // Generate signed URL if using Bunny integration
        if ($protection_settings['bunny_enabled']) {
            $bunny_integration = new CPP_Bunny_Integration();
            $signed_url = $bunny_integration->generate_signed_url($id);
            
            if ($signed_url) {
                return $signed_url;
            }
        }

        // Add token to existing URL
        $token = $this->generate_access_token($id);
        if ($token) {
            $separator = strpos($src, '?') !== false ? '&' : '?';
            $src .= $separator . 'token=' . $token;
        }

        return $src;
    }

    /**
     * Check video access before loading
     *
     * @param int   $id   Video ID
     * @param array $args Video arguments
     * @since 1.0.0
     */
    public function check_video_access($id, $args) {
        $protection_settings = $this->get_video_protection_settings($id);
        
        if (!$protection_settings || !$protection_settings['enabled']) {
            return;
        }

        $has_access = $this->check_user_access($id, $protection_settings);
        
        if (!$has_access) {
            // Log unauthorized access attempt
            $this->log_access_attempt($id, false);
            
            // Stop video loading
            wp_die($this->get_protection_message($protection_settings), 'Access Denied', array('response' => 403));
        }

        // Log successful access
        $this->log_access_attempt($id, true);
    }

    /**
     * Add protection overlay to video HTML
     *
     * @param string $html Video HTML
     * @param int    $id   Video ID
     * @return string Modified HTML
     * @since 1.0.0
     */
    public function add_protection_overlay($html, $id) {
        $protection_settings = $this->get_video_protection_settings($id);
        
        if (!$protection_settings || !$protection_settings['enabled']) {
            return $html;
        }

        $has_access = $this->check_user_access($id, $protection_settings);
        
        if (!$has_access) {
            $overlay_html = $this->generate_protection_overlay($id, $protection_settings);
            return $overlay_html;
        }

        return $html;
    }

    /**
     * AJAX handler for access validation
     *
     * @since 1.0.0
     */
    public function ajax_validate_access() {
        check_ajax_referer('cpp_presto_nonce', 'nonce');
        
        $video_id = sanitize_text_field($_POST['video_id']);
        $access_code = sanitize_text_field($_POST['access_code']);
        
        if (empty($video_id)) {
            wp_send_json_error(array('message' => __('Video ID is required.', 'content-protect-pro')));
        }

        $protection_settings = $this->get_video_protection_settings($video_id);
        
        if (!$protection_settings) {
            wp_send_json_error(array('message' => __('Video not found.', 'content-protect-pro')));
        }

        // Validate access code if required
        if ($protection_settings['require_giftcode'] && !empty($access_code)) {
            $giftcode_manager = new CPP_Giftcode_Manager();
            $validation_result = $giftcode_manager->validate_code($access_code);
            
            if ($validation_result['valid']) {
                // Store validated code in session
                if (!session_id()) {
                    session_start();
                }
                
                if (!isset($_SESSION['cpp_validated_codes'])) {
                    $_SESSION['cpp_validated_codes'] = array();
                }
                $_SESSION['cpp_validated_codes'][] = $access_code;
                
                wp_send_json_success(array(
                    'message' => __('Access granted!', 'content-protect-pro'),
                    'reload_player' => true
                ));
            } else {
                wp_send_json_error(array('message' => $validation_result['message']));
            }
        }

        // Check current access
        $has_access = $this->check_user_access($video_id, $protection_settings);
        
        if ($has_access) {
            wp_send_json_success(array(
                'message' => __('Access granted!', 'content-protect-pro'),
                'reload_player' => true
            ));
        } else {
            wp_send_json_error(array('message' => __('Access denied.', 'content-protect-pro')));
        }
    }

    /**
     * Get video protection settings
     *
     * @param int $video_id Video ID
     * @return array|false Protection settings or false
     * @since 1.0.0
     */
    private function get_video_protection_settings($video_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cpp_protected_videos';
        
        $settings = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE video_id = %s OR presto_player_id = %s",
            $video_id,
            $video_id
        ), ARRAY_A);
        
        if (!$settings) {
            return false;
        }

        return array(
            'enabled' => true,
            'require_giftcode' => (bool) $settings['requires_giftcode'],
            'allowed_giftcodes' => !empty($settings['allowed_giftcodes']) ? explode(',', $settings['allowed_giftcodes']) : array(),
            'access_level' => $settings['access_level'],
            'protection_type' => $settings['protection_type'],
            'bunny_enabled' => !empty($settings['bunny_library_id']),
            'bunny_library_id' => $settings['bunny_library_id'],
            'token_auth' => $settings['protection_type'] === 'token',
            // DRM disabled for now
        );
    }

    /**
     * Check if user has access to video
     *
     * @param int   $video_id Video ID
     * @param array $settings Protection settings
     * @return bool True if access granted
     * @since 1.0.0
     */
    private function check_user_access($video_id, $settings) {
        // Check access level
        if ($settings['access_level'] === 'public') {
            return true;
        }

        // Check if user is logged in for private content
        if ($settings['access_level'] === 'private' && !is_user_logged_in()) {
            return false;
        }

        // Check gift code requirement
        if ($settings['require_giftcode']) {
            if (!session_id()) {
                session_start();
            }
            
            $validated_codes = isset($_SESSION['cpp_validated_codes']) ? $_SESSION['cpp_validated_codes'] : array();
            
            if (empty($validated_codes)) {
                return false;
            }

            // Check if any validated code is in allowed codes list
            if (!empty($settings['allowed_giftcodes'])) {
                $has_valid_code = false;
                foreach ($validated_codes as $code) {
                    if (in_array($code, $settings['allowed_giftcodes'])) {
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
     * Generate access token for video
     *
     * @param int $video_id Video ID
     * @return string|false Access token or false
     * @since 1.0.0
     */
    private function generate_access_token($video_id) {
        $video_settings = get_option('cpp_video_settings', array());
        $expiry = isset($video_settings['token_expiry']) ? intval($video_settings['token_expiry']) : 3600;
        
        $payload = array(
            'video_id' => $video_id,
            'user_id' => get_current_user_id(),
            'ip' => $this->get_client_ip(),
            'expires' => time() + $expiry,
        );

        // Simple JWT-like token (in production, use proper JWT library)
        $header = json_encode(array('typ' => 'JWT', 'alg' => 'HS256'));
        $payload_json = json_encode($payload);
        
        $base64_header = base64_encode($header);
        $base64_payload = base64_encode($payload_json);
        
        $signature = hash_hmac('sha256', $base64_header . '.' . $base64_payload, AUTH_KEY, true);
        $base64_signature = base64_encode($signature);
        
        return $base64_header . '.' . $base64_payload . '.' . $base64_signature;
    }

    /**
     * Apply DRM configuration to player
     *
     * @param array $config Player configuration
     * @param int   $id     Video ID
     * @return array Modified configuration
     * @since 1.0.0
     */
    private function apply_drm_config($config, $id) {
        // Add DRM configuration for supported players
        $config['drm'] = array(
            'widevine' => array(
                'url' => $this->get_drm_license_url($id, 'widevine'),
            ),
            'playready' => array(
                'url' => $this->get_drm_license_url($id, 'playready'),
            ),
        );

        return $config;
    }

    /**
     * Get DRM license URL
     *
     * @param int    $video_id Video ID
     * @param string $drm_type DRM type (widevine, playready)
     * @return string License URL
     * @since 1.0.0
     */
    private function get_drm_license_url($video_id, $drm_type) {
        // This would integrate with your DRM provider
        // For Bunny DRM, this would be their license server URL
        return admin_url('admin-ajax.php?action=cpp_drm_license&video_id=' . $video_id . '&type=' . $drm_type);
    }

    /**
     * Get token protected video sources
     *
     * @param int   $video_id Video ID
     * @param array $sources  Original sources
     * @return array Modified sources with tokens
     * @since 1.0.0
     */
    private function get_token_protected_sources($video_id, $sources) {
        $token = $this->generate_access_token($video_id);
        
        if (!$token) {
            return $sources;
        }

        foreach ($sources as &$source) {
            if (isset($source['src'])) {
                $separator = strpos($source['src'], '?') !== false ? '&' : '?';
                $source['src'] .= $separator . 'token=' . $token;
            }
        }

        return $sources;
    }

    /**
     * Generate protection overlay HTML
     *
     * @param int   $video_id Video ID
     * @param array $settings Protection settings
     * @return string Overlay HTML
     * @since 1.0.0
     */
    private function generate_protection_overlay($video_id, $settings) {
        ob_start();
        ?>
        <div class="cpp-presto-protection-overlay" data-video-id="<?php echo esc_attr($video_id); ?>">
            <div class="cpp-protection-content">
                <h3><?php _e('Protected Content', 'content-protect-pro'); ?></h3>
                <p><?php echo esc_html($this->get_protection_message($settings)); ?></p>
                
                <?php if ($settings['require_giftcode']): ?>
                <form class="cpp-presto-access-form">
                    <div class="cpp-form-group">
                        <label for="cpp-presto-code"><?php _e('Enter Access Code:', 'content-protect-pro'); ?></label>
                        <input type="text" id="cpp-presto-code" name="access_code" required>
                    </div>
                    <div class="cpp-form-group">
                        <button type="submit"><?php _e('Unlock Video', 'content-protect-pro'); ?></button>
                    </div>
                    <div class="cpp-presto-message" style="display: none;"></div>
                </form>
                <?php endif; ?>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('.cpp-presto-access-form').on('submit', function(e) {
                e.preventDefault();
                
                var $form = $(this);
                var $message = $form.find('.cpp-presto-message');
                var videoId = $form.closest('.cpp-presto-protection-overlay').data('video-id');
                var accessCode = $form.find('input[name="access_code"]').val();
                
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    method: 'POST',
                    data: {
                        action: 'cpp_presto_validate_access',
                        video_id: videoId,
                        access_code: accessCode,
                        nonce: '<?php echo wp_create_nonce('cpp_presto_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $message.removeClass('error').addClass('success')
                                   .text(response.data.message).show();
                            
                            if (response.data.reload_player) {
                                setTimeout(function() {
                                    location.reload();
                                }, 1500);
                            }
                        } else {
                            $message.removeClass('success').addClass('error')
                                   .text(response.data.message).show();
                        }
                    }
                });
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }

    /**
     * Get protection message based on settings
     *
     * @param array $settings Protection settings
     * @return string Protection message
     * @since 1.0.0
     */
    private function get_protection_message($settings) {
        if ($settings['require_giftcode']) {
            return __('This video requires a valid access code to view.', 'content-protect-pro');
        }

        if ($settings['access_level'] === 'private') {
            return __('This video is only available to logged-in users.', 'content-protect-pro');
        }

        return __('This video is protected and requires authorization to view.', 'content-protect-pro');
    }

    /**
     * Track video access for analytics
     *
     * @param int $video_id Video ID
     * @since 1.0.0
     */
    private function track_video_access($video_id) {
        $analytics = new CPP_Analytics();
        $analytics->log_event('video_access', 'video', $video_id, array(
            'player_type' => 'presto',
            'user_id' => get_current_user_id(),
        ));
    }

    /**
     * Log access attempts for security monitoring
     *
     * @param int  $video_id Video ID
     * @param bool $success  Whether access was granted
     * @since 1.0.0
     */
    private function log_access_attempt($video_id, $success) {
        $event_type = $success ? 'video_access_granted' : 'video_access_denied';
        
        $analytics = new CPP_Analytics();
        $analytics->log_event($event_type, 'video', $video_id, array(
            'player_type' => 'presto',
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
}