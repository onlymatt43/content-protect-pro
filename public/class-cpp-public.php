<?php
/**
 * The public-facing functionality of the plugin
 *
 * @package Content_Protect_Pro
 * @since   1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class CPP_Public {

    /**
     * The ID of this plugin
     *
     * @var string
     */
    private $plugin_name;

    /**
     * The version of this plugin
     *
     * @var string
     */
    private $version;

    /**
     * Initialize the class and set its properties
     *
     * @param string $plugin_name The name of the plugin
     * @param string $version     The version of this plugin
     * @since 1.0.0
     */
    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    /**
     * Register the stylesheets for the public-facing side of the site
     *
     * @since 1.0.0
     */
    public function enqueue_styles() {
        wp_enqueue_style(
            $this->plugin_name,
            CPP_PLUGIN_URL . 'public/css/cpp-public.css',
            array(),
            $this->version,
            'all'
        );
    }

    /**
     * Register the JavaScript for the public-facing side of the site
     *
     * @since 1.0.0
     */
    public function enqueue_scripts() {
        // Determine integration settings first to set script dependencies/order
        $integration_settings = get_option('cpp_integration_settings', array());
        $enable_hls = !empty($integration_settings['enable_hls_polyfill']);
        $pref = isset($integration_settings['provider_preference']) ? $integration_settings['provider_preference'] : 'auto';
        $deps = array('jquery');
        if ($enable_hls) {
            wp_enqueue_script(
                'hls-js',
                'https://cdn.jsdelivr.net/npm/hls.js@1.5.7/dist/hls.min.js',
                array(),
                '1.5.7',
                true
            );
            $deps[] = 'hls-js';
        }

        // Enqueue main public script after its dependencies, in footer to avoid race conditions
        wp_enqueue_script(
            $this->plugin_name,
            CPP_PLUGIN_URL . 'public/js/cpp-public.js',
            $deps,
            $this->version,
            true
        );

        wp_localize_script(
            $this->plugin_name,
            'cpp_public_ajax',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('cpp_public_nonce'),
                'strings' => array(
                    'invalid_code' => __('Invalid gift code.', 'content-protect-pro'),
                    'expired_code' => __('This gift code has expired.', 'content-protect-pro'),
                    'used_code' => __('This gift code has already been used.', 'content-protect-pro'),
                    'loading' => __('Loading...', 'content-protect-pro'),
                    'error' => __('An error occurred. Please try again.', 'content-protect-pro'),
                ),
                'hls_enabled' => (bool) $enable_hls
            )
        );
    }

    /**
     * Initialize shortcodes
     *
     * @since 1.0.0
     */
    public function init_shortcodes() {
        add_shortcode('cpp_giftcode_form', array($this, 'giftcode_form_shortcode'));
        add_shortcode('cpp_protected_video', array($this, 'protected_video_shortcode'));
        add_shortcode('cpp_giftcode_check', array($this, 'giftcode_check_shortcode'));
    }

    /**
     * Gift code form shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string
     * @since 1.0.0
     */
    public function giftcode_form_shortcode($atts) {
        $atts = shortcode_atts(array(
            'redirect_url' => '',
            'success_message' => __('Gift code validated successfully!', 'content-protect-pro'),
            'class' => 'cpp-giftcode-form',
        ), $atts, 'cpp_giftcode_form');

        ob_start();
        ?>
        <div class="<?php echo esc_attr($atts['class']); ?>">
            <form id="cpp-giftcode-form" method="post">
                <div class="cpp-form-group">
                    <label for="cpp-giftcode"><?php _e('Enter Gift Code:', 'content-protect-pro'); ?></label>
                    <input type="text" id="cpp-giftcode" name="giftcode" required>
                </div>
                <div class="cpp-form-group">
                    <button type="submit"><?php _e('Validate Code', 'content-protect-pro'); ?></button>
                </div>
                <div id="cpp-giftcode-message" class="cpp-message" style="display: none;"></div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Protected video shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string
     * @since 1.0.0
     */
    public function protected_video_shortcode($atts) {
        $atts = shortcode_atts(array(
            'video_id' => '',
            'require_giftcode' => false,
            'allowed_codes' => '',
            'player_type' => 'bunny',
            'width' => '100%',
            'height' => '400px',
        ), $atts, 'cpp_protected_video');

        if (empty($atts['video_id'])) {
            return '<p>' . __('Video ID is required.', 'content-protect-pro') . '</p>';
        }

        // Check if gift code is required and validate
        if ($atts['require_giftcode']) {
            if (!session_id()) {
                session_start();
            }
            $session_codes = isset($_SESSION['cpp_validated_codes']) ? $_SESSION['cpp_validated_codes'] : array();
            $allowed_codes = !empty($atts['allowed_codes']) ? explode(',', $atts['allowed_codes']) : array();
            
            $has_valid_code = false;
            if (!empty($allowed_codes)) {
                foreach ($session_codes as $code) {
                    if (in_array($code, $allowed_codes)) {
                        $has_valid_code = true;
                        break;
                    }
                }
            } else {
                $has_valid_code = !empty($session_codes);
            }

            if (!$has_valid_code) {
                return '<div class="cpp-protected-content">' . 
                       __('A valid gift code is required to access this video.', 'content-protect-pro') . 
                       '</div>';
            }
        }

        // Generate video player based on type
        ob_start();
        ?>
        <div class="cpp-video-container" data-video-id="<?php echo esc_attr($atts['video_id']); ?>">
            <div id="cpp-video-<?php echo esc_attr($atts['video_id']); ?>" 
                 style="width: <?php echo esc_attr($atts['width']); ?>; height: <?php echo esc_attr($atts['height']); ?>;">
                <?php _e('Loading video...', 'content-protect-pro'); ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Gift code check shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string
     * @since 1.0.0
     */
    public function giftcode_check_shortcode($atts) {
        $atts = shortcode_atts(array(
            'required_codes' => '',
            'success_content' => '',
            'failure_content' => __('Please enter a valid gift code to access this content.', 'content-protect-pro'),
        ), $atts, 'cpp_giftcode_check');

        if (!session_id()) {
            session_start();
        }
        $session_codes = isset($_SESSION['cpp_validated_codes']) ? $_SESSION['cpp_validated_codes'] : array();
        $required_codes = !empty($atts['required_codes']) ? explode(',', $atts['required_codes']) : array();
        
        $has_valid_code = false;
        if (!empty($required_codes)) {
            foreach ($session_codes as $code) {
                if (in_array($code, $required_codes)) {
                    $has_valid_code = true;
                    break;
                }
            }
        } else {
            $has_valid_code = !empty($session_codes);
        }

        if ($has_valid_code) {
            return do_shortcode($atts['success_content']);
        } else {
            return '<div class="cpp-protected-content">' . $atts['failure_content'] . '</div>';
        }
    }

    /**
     * AJAX handler for gift code validation
     *
     * @since 1.0.0
     */
    public function validate_giftcode() {
        check_ajax_referer('cpp_public_nonce', 'nonce');

        $code = sanitize_text_field($_POST['code']);
        
        if (empty($code)) {
            wp_send_json_error(array('message' => __('Gift code is required.', 'content-protect-pro')));
        }

        // Load gift code manager
        require_once CPP_PLUGIN_DIR . 'includes/class-cpp-giftcode-manager.php';
        $giftcode_manager = new CPP_Giftcode_Manager();
        
        $result = $giftcode_manager->validate_code($code);
        
        if ($result['valid']) {
            // Start session if not already started
            if (!session_id()) {
                session_start();
            }
            
            // Store validated code in session
            if (!isset($_SESSION['cpp_validated_codes'])) {
                $_SESSION['cpp_validated_codes'] = array();
            }
            $_SESSION['cpp_validated_codes'][] = $code;
            
            wp_send_json_success(array(
                'message' => __('Gift code validated successfully!', 'content-protect-pro'),
                'redirect_url' => $result['redirect_url']
            ));
        } else {
            wp_send_json_error(array('message' => $result['message']));
        }
    }

    /**
     * AJAX handler for video token generation
     *
     * @since 1.0.0
     */
    public function get_video_token() {
        check_ajax_referer('cpp_public_nonce', 'nonce');

        $video_id = sanitize_text_field($_POST['video_id']);
        
        if (empty($video_id)) {
            wp_send_json_error(array('message' => __('Video ID is required.', 'content-protect-pro')));
        }

        // Load video manager
        require_once CPP_PLUGIN_DIR . 'includes/class-cpp-video-manager.php';
        $video_manager = new CPP_Video_Manager();
        
        // Fetch video entry for provider-specific rendering
        $video_row = $video_manager->get_protected_video($video_id);

        $response = array();
        
        // Legacy token (may be used by custom players)
        $token = $video_manager->generate_access_token($video_id);
        if ($token) {
            $response['token'] = $token;
        }

        // Provider-specific info with preference
        if ($video_row) {
            $integration_settings = get_option('cpp_integration_settings', array());
            $pref = isset($integration_settings['provider_preference']) ? $integration_settings['provider_preference'] : 'auto';
            // Per-video override via integration_type
            $forced = isset($video_row->integration_type) ? strtolower($video_row->integration_type) : '';
            // Decide order: forced overrides, else auto/preference
            if ($forced === 'direct') {
                if (!empty($video_row->direct_url)) {
                    $response['provider'] = 'direct';
                    $response['url'] = esc_url($video_row->direct_url);
                }
                $provider_set = !empty($response['provider']);
                // Skip other providers when direct is forced
            } elseif ($forced === 'presto') {
                $try_presto_first = true;
            } elseif ($forced === 'bunny') {
                $try_presto_first = false;
            } else {
                $try_presto_first = ($pref === 'presto' || $pref === 'auto');
            }

            if (!isset($provider_set)) {
                $provider_set = false;
            }
            if ($try_presto_first) {
                // Try Presto first
                if (!empty($video_row->presto_player_id)) {
                    $presto_id = intval($video_row->presto_player_id);
                    $embed_html = do_shortcode('[presto_player id="' . $presto_id . '"]');
                    if (!empty($embed_html)) {
                        $response['provider'] = 'presto';
                        $response['presto_embed'] = $embed_html;
                        $provider_set = true;
                    }
                }
                // Fallback to Bunny
                if (!$provider_set && !empty($video_row->bunny_library_id)) {
                    if (!class_exists('CPP_Bunny_Integration')) {
                        require_once CPP_PLUGIN_DIR . 'includes/class-cpp-bunny-integration.php';
                    }
                    $bunny = new CPP_Bunny_Integration();
                    $signed_url = $bunny->generate_signed_url($video_id);
                    if ($signed_url) {
                        $response['provider'] = 'bunny';
                        $response['signed_url'] = $signed_url;
                        $response['mime'] = 'application/x-mpegURL';
                        $provider_set = true;
                    }
                }
            } else {
                // Try Bunny first
                if (!empty($video_row->bunny_library_id)) {
                    if (!class_exists('CPP_Bunny_Integration')) {
                        require_once CPP_PLUGIN_DIR . 'includes/class-cpp-bunny-integration.php';
                    }
                    $bunny = new CPP_Bunny_Integration();
                    $signed_url = $bunny->generate_signed_url($video_id);
                    if ($signed_url) {
                        $response['provider'] = 'bunny';
                        $response['signed_url'] = $signed_url;
                        $response['mime'] = 'application/x-mpegURL';
                        $provider_set = true;
                    }
                }
                // Fallback to Presto
                if (!$provider_set && !empty($video_row->presto_player_id)) {
                    $presto_id = intval($video_row->presto_player_id);
                    $embed_html = do_shortcode('[presto_player id="' . $presto_id . '"]');
                    if (!empty($embed_html)) {
                        $response['provider'] = 'presto';
                        $response['presto_embed'] = $embed_html;
                        $provider_set = true;
                    }
                }
            }
            if (!$provider_set) {
                $response['provider'] = 'custom';
            }
        }

        if (!empty($response)) {
            wp_send_json_success($response);
        }

        wp_send_json_error(array('message' => __('Unable to generate video access details.', 'content-protect-pro')));
    }

    /**
     * AJAX handler for tracking video events
     *
     * @since 1.0.0
     */
    public function track_video_event() {
        check_ajax_referer('cpp_public_nonce', 'nonce');

        $event_type = isset($_POST['event_type']) ? sanitize_text_field($_POST['event_type']) : '';
        $video_id   = isset($_POST['video_id']) ? sanitize_text_field($_POST['video_id']) : '';

        if (empty($event_type) || empty($video_id)) {
            wp_send_json_error(array('message' => __('Missing event parameters.', 'content-protect-pro')));
        }

        if (!class_exists('CPP_Analytics')) {
            wp_send_json_success(array('message' => 'Analytics disabled'));
        }

        $analytics = new CPP_Analytics();
        $logged = $analytics->log_event($event_type, 'video', $video_id, array(
            'referrer' => isset($_SERVER['HTTP_REFERER']) ? esc_url_raw($_SERVER['HTTP_REFERER']) : '',
        ));

        if ($logged) {
            wp_send_json_success(array('message' => 'ok'));
        } else {
            // Still return success to avoid client retries/noise if analytics disabled
            wp_send_json_success(array('message' => 'noop'));
        }
    }
}