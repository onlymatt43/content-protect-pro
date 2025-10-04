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
     * Ensure modal markup is only printed once per page.
     *
     * @var bool
     */
    private static $modal_printed = false;

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
        // Enqueue main public script
        wp_enqueue_script(
            $this->plugin_name,
            CPP_PLUGIN_URL . 'public/js/cpp-public.js',
            array('jquery'),
            $this->version,
            true
        );

        // Enqueue gallery styles
        wp_enqueue_style(
            $this->plugin_name . '-gallery',
            CPP_PLUGIN_URL . 'public/css/cpp-public-gallery.css',
            array(),
            $this->version
        );

        // Load integration settings so we can provide a default overlay image URL
        $integration_settings = get_option('cpp_integration_settings', array());

        // Resolve integration overlay attachment ID to URL if needed
        $default_overlay = '';
        if (!empty($integration_settings['overlay_image'])) {
            if (ctype_digit((string) $integration_settings['overlay_image']) && function_exists('wp_get_attachment_url')) {
                $default_overlay = wp_get_attachment_url(intval($integration_settings['overlay_image']));
            } else {
                // Defensive fallback: empty - we no longer accept external URLs
                $default_overlay = '';
            }
        }

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
                    'overlay_expired' => __('Session expired', 'content-protect-pro'),
                    'overlay_prompt' => __('Your session has ended. Purchase more minutes to continue watching.', 'content-protect-pro'),
                    'overlay_buy' => __('Buy more minutes', 'content-protect-pro'),
                ),
                // global overlay image (attachment ID resolved to URL)
                'overlay_image' => $default_overlay
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
        add_shortcode('cpp_video_library', array($this, 'video_library_shortcode'));
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
     * Protected video shortcode - displays Presto Player video with gift code protection
     *
     * @param array $atts Shortcode attributes
     * @return string
     * @since 1.0.0
     */
    public function protected_video_shortcode($atts) {
        $atts = shortcode_atts(array(
            'id' => '', // Presto Player video ID
            'code' => '', // Required gift code
            'width' => '100%',
            'height' => '400px',
        ), $atts, 'cpp_protected_video');

        if (empty($atts['id'])) {
            return '<p>' . __('Presto Player video ID is required.', 'content-protect-pro') . '</p>';
        }

        $video_id = intval($atts['id']);
        
        // Check if this is a valid Presto Player video
        $post = get_post($video_id);
        if (!$post || $post->post_type !== 'pp_video_block') {
            return '<p>' . __('Invalid video ID. Please check that this is a valid Presto Player video.', 'content-protect-pro') . '</p>';
        }
        
        // Check if video requires gift code protection
        $required_minutes = get_post_meta($video_id, '_cpp_required_minutes', true) ?: 0;
        $requires_code = $required_minutes > 0;
        
        // If video requires gift code, validate it
        if ($requires_code) {
            if (empty($atts['code'])) {
                return '<div class="cpp-protected-content">' .
                       sprintf(__('This video requires a gift code with at least %d minutes of access.', 'content-protect-pro'), $required_minutes) .
                       '<br>' . __('Use: [cpp_protected_video id="' . $video_id . '" code="YOUR_CODE"]', 'content-protect-pro') .
                       '</div>';
            }
            
            // Validate the gift code (server-side token flow)
            // Load gift code manager to validate
            require_once CPP_PLUGIN_DIR . 'includes/class-cpp-giftcode-manager.php';
            $giftcode_manager = new CPP_Giftcode_Manager();
            $validation = $giftcode_manager->validate_code($atts['code']);
            
            if (!$validation['valid']) {
                return '<div class="cpp-protected-content">' .
                       __('Invalid or expired gift code.', 'content-protect-pro') .
                       '</div>';
            }

            // Create a short-lived server-side token tied to this video and set cookie
            global $wpdb;
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
            $ip_addr = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
            $wpdb->insert($table, array(
                'token' => $token,
                'user_id' => $user_id,
                'video_id' => $video_id,
                'expires_at' => date('Y-m-d H:i:s', $expires_at),
                'ip_address' => $ip_addr,
            ), array('%s','%d','%s','%s','%s'));

            // Set HttpOnly secure cookie for front-end to send with requests
            $secure = is_ssl();
            $cookie_path = defined('COOKIEPATH') ? COOKIEPATH : '/';
            $cookie_domain = defined('COOKIE_DOMAIN') ? COOKIE_DOMAIN : '';
            setcookie('cpp_playback_token', $token, $expires_at, $cookie_path, $cookie_domain, $secure, true);
        }

        // Video is accessible, return Presto Player shortcode
        return do_shortcode('[presto_player id="' . $video_id . '"]');
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

        // Check for server-side playback token cookie
        $has_valid_token = false;
        if (!empty($_COOKIE['cpp_playback_token'])) {
            $token = sanitize_text_field($_COOKIE['cpp_playback_token']);
            global $wpdb;
            $table = $wpdb->prefix . 'cpp_tokens';
            $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE token = %s LIMIT 1", $token));
            if ($row && strtotime($row->expires_at) >= time()) {
                $has_valid_token = true;
            }
        }
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

        if ($has_valid_code || $has_valid_token) {
            return do_shortcode($atts['success_content']);
        } else {
            return '<div class="cpp-protected-content">' . $atts['failure_content'] . '</div>';
        }
    }

    /**
     * Video library shortcode - displays all videos in a grid
     *
     * @param array $atts Shortcode attributes
     * @return string
     * @since 1.0.0
     */
    public function video_library_shortcode($atts) {
        $atts = shortcode_atts(array(
            'per_page' => 12,
            'columns' => 3,
            'show_filters' => 'true',
            'show_search' => 'true',
            'access_level' => '',
            'require_giftcode' => 'false',
            'class' => 'cpp-video-library',
        ), $atts, 'cpp_video_library');

        // Load video manager
        if (!class_exists('CPP_Video_Manager')) {
            require_once CPP_PLUGIN_DIR . 'includes/class-cpp-video-manager.php';
        }
        $video_manager = new CPP_Video_Manager();

        // Get all videos
        $videos_data = $video_manager->get_protected_videos(array(
            'per_page' => intval($atts['per_page']),
            'page' => 1,
            'access_level' => $atts['access_level'],
            'orderby' => 'created_at',
            'order' => 'DESC',
        ));

        $videos = $videos_data['videos'];

        ob_start();
        ?>
        <div class="<?php echo esc_attr($atts['class']); ?>">
            <?php if ($atts['show_search'] === 'true'): ?>
                <div class="cpp-library-search">
                    <input type="text" id="cpp-video-search" placeholder="<?php _e('Rechercher des vidéos...', 'content-protect-pro'); ?>" />
                </div>
            <?php endif; ?>

            <?php if ($atts['show_filters'] === 'true'): ?>
                <div class="cpp-library-filters">
                    <button class="cpp-filter-btn active" data-filter="all"><?php _e('All', 'content-protect-pro'); ?></button>
                    <?php
                    $cats = get_terms(['taxonomy' => 'category', 'hide_empty' => true]);
                    $tags = get_terms(['taxonomy' => 'post_tag', 'hide_empty' => true]);
                    $terms = array_merge(is_array($cats) ? $cats : [], is_array($tags) ? $tags : []);
                    shuffle($terms);
                    foreach ($terms as $term) {
                        $filter_val = $term->taxonomy . ':' . $term->slug;
                        echo '<button class="cpp-filter-btn" data-filter="' . esc_attr($filter_val) . '">' . esc_html($term->name) . '</button>';
                    }
                    ?>
                </div>
            <?php endif; ?>

            <div class="cpp-video-grid" data-columns="<?php echo esc_attr($atts['columns']); ?>">
                <?php if (!empty($videos)): ?>
                    <?php foreach ($videos as $video): ?>
                        <?php
                        // Determine thumbnail (prefer featured image of the presto post)
                        $thumb_url = '';
                        $link_url = '';
                        $post_obj = null;
                        if (!empty($video->video_id)) {
                            $post_obj = get_post(intval($video->video_id));
                        }
                        if ($post_obj) {
                            $thumb_url = get_the_post_thumbnail_url($post_obj, 'medium');
                            $link_url = get_permalink($post_obj);
                        }
                        // Fallback: try direct thumbnail meta or placeholder
                        if (empty($thumb_url) && !empty($video->thumbnail_url)) {
                            $thumb_url = esc_url($video->thumbnail_url);
                        }
                        if (empty($link_url)) {
                            $link_url = esc_url(add_query_arg('cpp_video', $video->video_id, home_url('/')));
                        }
                        ?>

                        <div class="cpp-video-item" data-video-id="<?php echo esc_attr($video->video_id); ?>" data-integration="<?php echo esc_attr(!empty($video->presto_player_id) ? 'presto' : 'direct'); ?>">
                            <!-- Use a button (non-navigating) and explicit data-video-id to avoid accidental navigation to login pages -->
                            <button class="cpp-video-link" type="button" role="button" data-video-id="<?php echo esc_attr($video->video_id); ?>" data-link="<?php echo esc_attr($link_url); ?>">
                                <div class="cpp-video-thumbnail">
                                    <?php if (!empty($thumb_url)): ?>
                                        <img src="<?php echo esc_url($thumb_url); ?>" alt="<?php echo esc_attr($video->title); ?>" />
                                    <?php else: ?>
                                        <div class="cpp-video-placeholder">
                                            <span class="dashicons dashicons-video-alt3"></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="cpp-video-info">
                                    <h4><?php echo esc_html($video->title); ?></h4>
                                    <?php if (!empty($video->description)): ?>
                                        <p><?php echo esc_html(wp_trim_words($video->description, 15)); ?></p>
                                    <?php endif; ?>
                                    <div class="cpp-video-meta">
                                        <?php if (!empty($video->presto_player_id)): ?>
                                            <span class="cpp-integration-badge cpp-presto"><?php _e('Presto Player', 'content-protect-pro'); ?></span>
                                        <?php else: ?>
                                            <span class="cpp-integration-badge cpp-direct"><?php _e('Direct URL', 'content-protect-pro'); ?></span>
                                        <?php endif; ?>
                                        <?php /* Removed visual badge for "Code requis" per user request; access control still enforced server-side */ ?>
                                    </div>
                                </div>
                            </button>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="cpp-no-videos">
                        <div class="cpp-empty-state">
                            <div class="cpp-empty-icon">
                                <span class="dashicons dashicons-video-alt3"></span>
                            </div>
                            <h3><?php _e('Aucune vidéo disponible', 'content-protect-pro'); ?></h3>
                            <p><?php _e('Il n\'y a pas encore de vidéos dans votre bibliothèque.', 'content-protect-pro'); ?></p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!self::$modal_printed): ?>
                <!-- Video Modal (styling-friendly for page builders like Breakdance) - printed once -->
                <div id="cpp-video-modal" class="cpp-modal" aria-hidden="true">
                    <div class="cpp-modal-overlay"></div>
                    <div class="cpp-modal-content" role="dialog" aria-modal="true">
                        <div class="cpp-modal-header">
                            <h3 id="cpp-modal-title"></h3>
                            <button class="cpp-modal-close" type="button" aria-label="Close">&times;</button>
                        </div>
                        <div class="cpp-modal-body">
                            <div id="cpp-modal-video-container" class="cpp-modal-inner">
                                <!-- Video player will be loaded here -->
                            </div>
                        </div>
                    </div>
                </div>
                <?php self::$modal_printed = true; ?>
            <?php endif; ?>

            <!-- Inline scripts removed: handled centrally by public/js/cpp-public.js -->
        <?php
        return ob_get_clean();
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
            // Create server-side playback token and set cookie
            global $wpdb;
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
            $ip_addr = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
            $wpdb->insert($table, array(
                'token' => $token,
                'user_id' => $user_id,
                'video_id' => '',
                'expires_at' => date('Y-m-d H:i:s', $expires_at),
                'ip_address' => $ip_addr,
            ), array('%s','%d','%s','%s','%s'));

            // Set cookie
            $secure = is_ssl();
            $cookie_path = defined('COOKIEPATH') ? COOKIEPATH : '/';
            $cookie_domain = defined('COOKIE_DOMAIN') ? COOKIE_DOMAIN : '';
            setcookie('cpp_playback_token', $token, $expires_at, $cookie_path, $cookie_domain, $secure, true);

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
            $provider_set = false;

            // Handle forced provider types
            if ($forced === 'direct') {
                if (!empty($video_row->direct_url)) {
                    $response['provider'] = 'direct';
                    $response['url'] = esc_url($video_row->direct_url);
                    $provider_set = true;
                }
            } elseif ($forced === 'presto') {
                if (!empty($video_row->presto_player_id)) {
                    $presto_id = intval($video_row->presto_player_id);
                    $embed_html = do_shortcode('[presto_player id="' . $presto_id . '"]');
                    if (!empty($embed_html)) {
                        $response['provider'] = 'presto';
                        $response['presto_embed'] = $embed_html;
                        $provider_set = true;
                    }
                }
            } else {
                // Auto mode - try Presto first, then direct
                if (!empty($video_row->presto_player_id)) {
                    $presto_id = intval($video_row->presto_player_id);
                    $embed_html = do_shortcode('[presto_player id="' . $presto_id . '"]');
                    if (!empty($embed_html)) {
                        $response['provider'] = 'presto';
                        $response['presto_embed'] = $embed_html;
                        $provider_set = true;
                    }
                }
            }

            // Final fallback to direct URL if available
            if (!$provider_set && !empty($video_row->direct_url)) {
                $response['provider'] = 'direct';
                $response['url'] = esc_url($video_row->direct_url);
                $provider_set = true;
            }

            if (!$provider_set) {
                $response['provider'] = 'custom';
            }
        }

        if (!empty($response)) {
            // Try to include per-session overlay/purchase info if available
            $overlay_image = '';
            $purchase_url = '';

            // Look for legacy session cookie names starting with cpp_session_
            foreach ($_COOKIE as $k => $v) {
                if (strpos($k, 'cpp_session_') === 0) {
                    $cookie_data = json_decode(base64_decode($v), true);
                    if ($cookie_data && !empty($cookie_data['session_id'])) {
                        global $wpdb;
                        $sess = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}cpp_sessions WHERE session_id = %s LIMIT 1", $cookie_data['session_id']));
                        if ($sess && !empty($sess->code)) {
                            $code = $sess->code;
                            $gift_table = $wpdb->prefix . 'cpp_giftcodes';
                            $gc = $wpdb->get_row($wpdb->prepare("SELECT overlay_image, purchase_url FROM {$gift_table} WHERE code = %s LIMIT 1", $code));
                            if ($gc) {
                                $overlay_image = $gc->overlay_image;
                                $purchase_url = $gc->purchase_url;
                            }
                            break;
                        }
                    }
                }
            }

            if (!$overlay_image && !empty($integration_settings['overlay_image'])) {
                $overlay_image = $integration_settings['overlay_image'];
            }
            if (!$purchase_url && !empty($integration_settings['purchase_url'])) {
                $purchase_url = $integration_settings['purchase_url'];
            }

            // If overlay_image is an attachment ID, convert to URL
            if ($overlay_image) {
                if (ctype_digit((string) $overlay_image) && function_exists('wp_get_attachment_url')) {
                    $ov = wp_get_attachment_url(intval($overlay_image));
                    if ($ov) $response['overlay_image'] = esc_url($ov);
                } else {
                    $response['overlay_image'] = esc_url($overlay_image);
                }
            }
            if ($purchase_url) $response['purchase_url'] = esc_url($purchase_url);

            wp_send_json_success($response);
        }

        $error_payload = array('message' => __('Unable to generate video access details.', 'content-protect-pro'));

        // Optional, opt-in debug information for administrators/testing. Enable by setting
        // the `cpp_enable_debug_ajax` option to 1 (use WP-CLI: `wp option update cpp_enable_debug_ajax 1`).
        // This is intentionally opt-in to avoid exposing internal state by default.
        $debug_enabled = get_option('cpp_enable_debug_ajax', 0);
        if ($debug_enabled) {
            $debug = array();
            $debug['video_row'] = isset($video_row) && $video_row ? (array) $video_row : null;
            $debug['has_token'] = !empty($token) ? true : false;
            $debug['cookie_cpp_playback_token'] = isset($_COOKIE['cpp_playback_token']) ? sanitize_text_field($_COOKIE['cpp_playback_token']) : null;
            $error_payload['debug'] = $debug;
        }

        wp_send_json_error($error_payload);
    }

    /**
     * AJAX handler to return a small preview HTML for the requested video.
     * This is used by the public gallery modal to show a quick preview (Presto embed if available,
     * otherwise a thumbnail + title/description).
     *
     * @since 1.0.0
     */
    public function get_video_preview() {
        check_ajax_referer('cpp_public_nonce', 'nonce');

        $video_id = sanitize_text_field($_POST['video_id'] ?? '');
        if (empty($video_id)) {
            wp_send_json_error(array('message' => __('Video ID is required.', 'content-protect-pro')));
        }

        if (!class_exists('CPP_Video_Manager')) {
            require_once CPP_PLUGIN_DIR . 'includes/class-cpp-video-manager.php';
        }
        $video_manager = new CPP_Video_Manager();
        $video_row = $video_manager->get_protected_video($video_id);

        if (!$video_row) {
            wp_send_json_error(array('message' => __('Video not found.', 'content-protect-pro')));
        }

        // Try to build a small preview: prefer Presto embed (allow it to be interactive but short-lived)
        $preview_html = '';
        if (!empty($video_row->presto_player_id)) {
            $presto_id = intval($video_row->presto_player_id);
            $embed_html = do_shortcode('[presto_player id="' . $presto_id . '" preview="true"]');
            if (!empty($embed_html)) {
                $preview_html = $embed_html;
            }
        }

        // Fallback: thumbnail + title/description
        if (empty($preview_html)) {
            $thumb = '';
            if (!empty($video_row->thumbnail_url)) {
                $thumb = esc_url($video_row->thumbnail_url);
            } elseif (!empty($video_row->video_id)) {
                $post_obj = get_post(intval($video_row->video_id));
                if ($post_obj) {
                    $thumb = get_the_post_thumbnail_url($post_obj, 'medium');
                }
            }

            $title = !empty($video_row->title) ? esc_html($video_row->title) : __('Video Preview', 'content-protect-pro');
            $desc = !empty($video_row->description) ? esc_html(wp_trim_words($video_row->description, 25)) : '';

            $preview_html = '<div class="cpp-preview-card">';
            if ($thumb) {
                $preview_html .= '<div class="cpp-preview-thumb"><img src="' . esc_url($thumb) . '" alt="' . esc_attr($title) . '" /></div>';
            }
            $preview_html .= '<div class="cpp-preview-meta"><h4>' . $title . '</h4>';
            if ($desc) $preview_html .= '<p>' . $desc . '</p>';
            $preview_html .= '</div></div>';
        }

        wp_send_json_success(array('html' => $preview_html, 'title' => $video_row->title ?? ''));
    }

    /**
     * AJAX handler to return remaining session seconds for the current user/session.
     * Used by the front-end SessionMonitor polling.
     *
     * @since 1.0.0
     */
    public function get_session_remaining() {
        check_ajax_referer('cpp_public_nonce', 'nonce');

        $response = array('valid' => false, 'remaining_seconds' => 0);

        // If server-side playback token exists, validate it
        if (!empty($_COOKIE['cpp_playback_token'])) {
            global $wpdb;
            $token = sanitize_text_field($_COOKIE['cpp_playback_token']);
            $table = $wpdb->prefix . 'cpp_tokens';
            $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE token = %s LIMIT 1", $token));
            if ($row) {
                $expires = strtotime($row->expires_at);
                $remaining = max(0, $expires - time());
                $response['valid'] = $remaining > 0;
                $response['remaining_seconds'] = intval($remaining);
                wp_send_json_success($response);
            }
        }

        // No valid token found
        wp_send_json_success($response);
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