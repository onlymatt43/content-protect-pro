<?php
/**
 * The public-facing functionality of the plugin
 *
 * Optimized for performance with lazy loading, proper caching, and security hardening.
 * Follows WordPress coding standards and Content Protect Pro architecture patterns.
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
     * Cached instances for dependency injection
     *
     * @var array
     */
    private static $instances = [];

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
     * Only enqueues when shortcodes are detected on the page
     *
     * @since 1.0.0
     */
    public function enqueue_styles() {
        global $post;
        
        // Base styles always loaded
        wp_enqueue_style(
            $this->plugin_name,
            CPP_PLUGIN_URL . 'public/css/cpp-public.css',
            [],
            $this->version,
            'all'
        );

        // Conditional loading for library (only when shortcode present)
        if ($this->has_cpp_shortcode($post)) {
            wp_enqueue_style(
                'cpp-video-library',
                CPP_PLUGIN_URL . 'public/css/cpp-public-gallery.css',
                [],
                $this->version,
                'all'
            );
        }
    }

    /**
     * Register the JavaScript for the public-facing side of the site
     * Optimized with conditional loading and proper localization
     *
     * @since 1.0.0
     */
    public function enqueue_scripts() {
        global $post;

        // Main public script
        wp_enqueue_script(
            $this->plugin_name,
            CPP_PLUGIN_URL . 'public/js/cpp-public.js',
            ['jquery'],
            $this->version,
            true // Load in footer
        );

        // Get integration settings once (cached)
        $integration_settings = $this->get_integration_settings();
        
        // Resolve overlay image (POST-MIGRATION: attachment ID to URL)
        $default_overlay = $this->resolve_overlay_image($integration_settings['overlay_image'] ?? '');

        // Localize with security nonce
        wp_localize_script(
            $this->plugin_name,
            'cpp_public_ajax',
            [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('cpp_public_nonce'),
                'strings' => [
                    'invalid_code' => __(__('Invalid gift code.', 'content-protect-pro'), 'content-protect-pro'),
                    'expired_code' => __(__('This gift code has expired.', 'content-protect-pro'), 'content-protect-pro'),
                    'used_code' => __(__('This gift code has already been used.', 'content-protect-pro'), 'content-protect-pro'),
                    'loading' => __('Loading...', 'content-protect-pro'),
                    'error' => __('An error occurred. Please try again.', 'content-protect-pro'),
                    'overlay_expired' => __('Session expired', 'content-protect-pro'),
                    'overlay_prompt' => __('Your session has ended. Purchase more minutes to continue watching.', 'content-protect-pro'),
                    'overlay_buy' => __('Buy more minutes', 'content-protect-pro'),
                ],
                'overlay_image' => $default_overlay,
            ]
        );

        // Conditional library script
        if ($this->has_cpp_shortcode($post)) {
            wp_enqueue_script(
                'cpp-video-library',
                CPP_PLUGIN_URL . 'public/js/cpp-video-library.js',
                ['jquery'],
                $this->version,
                true
            );

            // Localize library-specific vars
            wp_localize_script('cpp-video-library', 'cppVars', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('cpp_library_nonce'),
                'strings' => [
                    'no_videos' => __('No videos found', 'content-protect-pro'),
                    'load_error' => __('Failed to load videos', 'content-protect-pro'),
                    'playback_error' => __('Failed to load video', 'content-protect-pro'),
                    'prev' => __('Previous', 'content-protect-pro'),
                    'next' => __('Next', 'content-protect-pro'),
                ]
            ]);
        }
    }

    /**
     * Check if post contains CPP shortcodes (optimization helper)
     *
     * @param WP_Post|null $post Post object
     * @return bool
     */
    private function has_cpp_shortcode($post) {
        if (!$post || !isset($post->post_content)) {
            return false;
        }

        $shortcodes = ['cpp_video_library', 'cpp_protected_video', 'cpp_giftcode_form'];
        
        foreach ($shortcodes as $shortcode) {
            if (has_shortcode($post->post_content, $shortcode)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get integration settings (cached)
     *
     * @return array
     */
    private function get_integration_settings() {
        static $settings = null;
        
        if ($settings === null) {
            $settings = get_option('cpp_integration_settings', []);
        }

        return $settings;
    }

    /**
     * Resolve overlay image (POST-MIGRATION: converts attachment ID to URL)
     * Following security pattern: no external URLs accepted
     *
     * @param mixed $overlay_value Attachment ID or empty
     * @return string URL or empty string
     */
    private function resolve_overlay_image($overlay_value) {
        if (empty($overlay_value)) {
            return '';
        }

        // POST-MIGRATION: only accept attachment IDs
        if (ctype_digit((string) $overlay_value)) {
            $url = wp_get_attachment_url(absint($overlay_value));
            return $url ? esc_url($url) : '';
        }

        // Defensive: reject external URLs
        return '';
    }

    /**
     * Initialize shortcodes
     *
     * @since 1.0.0
     */
    public function init_shortcodes() {
        add_shortcode('cpp_giftcode_form', [$this, 'giftcode_form_shortcode']);
        add_shortcode('cpp_protected_video', [$this, 'protected_video_shortcode']);
        add_shortcode('cpp_giftcode_check', [$this, 'giftcode_check_shortcode']);
        add_shortcode('cpp_video_library', [$this, 'video_library_shortcode']);
    }

    /**
     * Gift code form shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string
     * @since 1.0.0
     */
    public function giftcode_form_shortcode($atts) {
        $atts = shortcode_atts([
            'redirect_url' => '',
            'success_message' => __('Gift code validated successfully!', 'content-protect-pro'),
            'class' => 'cpp-giftcode-form',
        ], $atts, 'cpp_giftcode_form');

        ob_start();
        ?>
        <div class="<?php echo esc_attr($atts['class']); ?>">
            <form id="cpp-giftcode-form" method="post">
                <div class="cpp-form-group">
                    <label for="cpp-giftcode"><?php esc_html_e('Enter Gift Code:', 'content-protect-pro'); ?></label>
                    <input 
                        type="text" 
                        id="cpp-giftcode" 
                        name="giftcode" 
                        required 
                        autocomplete="off"
                        aria-required="true"
                    />
                </div>
                <div class="cpp-form-group">
                    <button type="submit"><?php esc_html_e('Validate Code', 'content-protect-pro'); ?></button>
                </div>
                <div id="cpp-giftcode-message" class="cpp-message" style="display: none;" role="alert"></div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Protected video shortcode - displays Presto Player video with gift code protection
     * Optimized with lazy loading of dependencies and proper token flow
     *
     * @param array $atts Shortcode attributes
     * @return string
     * @since 1.0.0
     */
    public function protected_video_shortcode($atts) {
        $atts = shortcode_atts([
            'id' => '',
            'code' => '',
            'width' => '100%',
            'height' => '400px',
        ], $atts, 'cpp_protected_video');

        if (empty($atts['id'])) {
            return '<p class="cpp-error">' . esc_html__(__('Presto Player video ID is required.', 'content-protect-pro'), 'content-protect-pro') . '</p>';
        }

        $video_id = absint($atts['id']);
        
        // Validate Presto Player post
        $post = get_post($video_id);
        if (!$post || $post->post_type !== 'pp_video_block') {
            return '<p class="cpp-error">' . esc_html__('Invalid video ID. Please check that this is a valid Presto Player video.', 'content-protect-pro') . '</p>';
        }
        
        // Check protection requirements
        $required_minutes = absint(get_post_meta($video_id, '_cpp_required_minutes', true));
        $requires_code = $required_minutes > 0;
        
        if ($requires_code) {
            if (empty($atts['code'])) {
                return sprintf(
                    '<div class="cpp-protected-content"><p>%s</p><p class="cpp-hint">%s</p></div>',
                    sprintf(
                        /* translators: %d: number of minutes */
                        esc_html__('This video requires a gift code with at least %d minutes of access.', 'content-protect-pro'),
                        $required_minutes
                    ),
                    esc_html(sprintf(
                        __('Use: [cpp_protected_video id="%d" code="YOUR_CODE"]', 'content-protect-pro'),
                        $video_id
                    ))
                );
            }
            
            // Validate gift code (lazy load manager)
            $giftcode_manager = $this->get_giftcode_manager();
            $validation = $giftcode_manager->validate_code($atts['code']);
            
            if (!$validation['valid']) {
                return '<div class="cpp-protected-content"><p>' . 
                       esc_html__(__('Invalid or expired gift code.', 'content-protect-pro'), 'content-protect-pro') . 
                       '</p></div>';
            }

            // Create server-side token (following token-based auth pattern)
            $this->create_playback_token($video_id);
        }

        // Return Presto Player embed
        return do_shortcode('[presto_player id="' . $video_id . '"]');
    }

    /**
     * Gift code check shortcode
     * Optimized with proper token validation
     *
     * @param array $atts Shortcode attributes
     * @return string
     * @since 1.0.0
     */
    public function giftcode_check_shortcode($atts) {
        $atts = shortcode_atts([
            'required_codes' => '',
            'success_content' => '',
            'failure_content' => __(__('Please enter a valid gift code to access this content.', 'content-protect-pro'), 'content-protect-pro'),
        ], $atts, 'cpp_giftcode_check');

        $has_access = $this->check_user_access($atts['required_codes']);

        if ($has_access) {
            return do_shortcode($atts['success_content']);
        }

        return '<div class="cpp-protected-content">' . wp_kses_post($atts['failure_content']) . '</div>';
    }

    /**
     * Video library shortcode - displays all videos in a grid
     * Optimized with query caching and conditional rendering
     *
     * @param array $atts Shortcode attributes
     * @return string
     * @since 1.0.0
     */
    public function video_library_shortcode($atts) {
        $atts = shortcode_atts([
            'per_page' => 12,
            'columns' => 3,
            'show_filters' => 'true',
            'show_search' => 'true',
            'access_level' => '',
            'require_giftcode' => 'false',
            'class' => 'cpp-video-library',
        ], $atts, 'cpp_video_library');

        // Lazy load video manager
        $video_manager = $this->get_video_manager();

        // Get videos with transient caching (5 minutes)
        $cache_key = 'cpp_library_' . md5(serialize($atts));
        $videos_data = get_transient($cache_key);

        if (false === $videos_data) {
            $videos_data = $video_manager->get_protected_videos([
                'per_page' => absint($atts['per_page']),
                'page' => 1,
                'access_level' => sanitize_text_field($atts['access_level']),
                'orderby' => 'created_at',
                'order' => 'DESC',
            ]);

            // Cache for 5 minutes
            set_transient($cache_key, $videos_data, 5 * MINUTE_IN_SECONDS);
        }

        $videos = $videos_data['videos'] ?? [];

        ob_start();
        ?>
        <div class="<?php echo esc_attr($atts['class']); ?>">
            <?php if ($atts['show_search'] === 'true'): ?>
                <div class="cpp-library-search">
                    <input 
                        type="search" 
                        id="cpp-video-search" 
                        placeholder="<?php esc_attr_e('Search videos...', 'content-protect-pro'); ?>" 
                        aria-label="<?php esc_attr_e('Search videos', 'content-protect-pro'); ?>"
                    />
                </div>
            <?php endif; ?>

            <?php if ($atts['show_filters'] === 'true'): ?>
                <div class="cpp-library-filters">
                    <button class="cpp-filter-btn active" data-filter="all">
                        <?php esc_html_e('All Videos', 'content-protect-pro'); ?>
                    </button>
                    <button class="cpp-filter-btn" data-filter="presto">
                        <?php esc_html_e('Presto Player', 'content-protect-pro'); ?>
                    </button>
                    <button class="cpp-filter-btn" data-filter="direct">
                        <?php esc_html_e('Direct URL', 'content-protect-pro'); ?>
                    </button>
                </div>
            <?php endif; ?>

            <div class="cpp-video-grid" data-columns="<?php echo esc_attr($atts['columns']); ?>">
                <?php if (!empty($videos)): ?>
                    <?php foreach ($videos as $video): 
                        echo $this->render_video_card($video);
                    endforeach; ?>
                <?php else: ?>
                    <div class="cpp-no-videos">
                        <div class="cpp-empty-state">
                            <div class="cpp-empty-icon">
                                <span class="dashicons dashicons-video-alt3"></span>
                            </div>
                            <h3><?php esc_html_e('No videos available', 'content-protect-pro'); ?></h3>
                            <p><?php esc_html_e(__('There are no videos in your library yet.', 'content-protect-pro'), 'content-protect-pro'); ?></p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Video Modal -->
            <div id="cpp-video-modal" class="cpp-modal" aria-hidden="true">
                <div class="cpp-modal-overlay"></div>
                <div class="cpp-modal-content" role="dialog" aria-modal="true" aria-labelledby="cpp-modal-title">
                    <div class="cpp-modal-header">
                        <h3 id="cpp-modal-title"></h3>
                        <button class="cpp-modal-close" type="button" aria-label="<?php esc_attr_e('Close', 'content-protect-pro'); ?>">&times;</button>
                    </div>
                    <div class="cpp-modal-body">
                        <div id="cpp-modal-video-container" class="cpp-modal-inner"></div>
                    </div>
                </div>
            </div>
        </div>
        <?php

        // Inline script for modal (deferred to avoid blocking)
        $this->print_library_script();

        return ob_get_clean();
    }

    /**
     * Render individual video card
     * Extracted for better maintainability
     *
     * @param object $video Video data
     * @return string HTML
     */
    private function render_video_card($video) {
        $thumb_url = '';
        $post_obj = null;

        if (!empty($video->video_id)) {
            $post_obj = get_post(absint($video->video_id));
        }

        if ($post_obj) {
            $thumb_url = get_the_post_thumbnail_url($post_obj, 'medium');
        }

        // Fallback thumbnail
        if (empty($thumb_url) && !empty($video->thumbnail_url)) {
            $thumb_url = esc_url($video->thumbnail_url);
        }

        ob_start();
        ?>
        <div class="cpp-video-item" data-video-id="<?php echo esc_attr($video->video_id); ?>">
            <button 
                class="cpp-video-link" 
                type="button" 
                data-video-id="<?php echo esc_attr($video->video_id); ?>"
                aria-label="<?php echo esc_attr(sprintf(__('Play %s', 'content-protect-pro'), $video->title)); ?>"
            >
                <div class="cpp-video-thumbnail">
                    <?php if (!empty($thumb_url)): ?>
                        <img 
                            src="<?php echo esc_url($thumb_url); ?>" 
                            alt="<?php echo esc_attr($video->title); ?>" 
                            loading="lazy"
                        />
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
                            <span class="cpp-integration-badge cpp-presto">
                                <?php esc_html_e('Presto Player', 'content-protect-pro'); ?>
                            </span>
                        <?php else: ?>
                            <span class="cpp-integration-badge cpp-direct">
                                <?php esc_html_e('Direct URL', 'content-protect-pro'); ?>
                            </span>
                        <?php endif; ?>
                        <?php if ($video->requires_giftcode): ?>
                            <span class="cpp-requires-code">
                                <?php esc_html_e('Code required', 'content-protect-pro'); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </button>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Print library JavaScript (inline, deferred)
     */
    private function print_library_script() {
        $nonce = wp_create_nonce('cpp_public_nonce');
        $ajax_url = admin_url('admin-ajax.php');
        ?>
        <script>
        jQuery(document).ready(function($) {
            // Search with debounce
            let searchTimeout;
            $('#cpp-video-search').on('input', function() {
                clearTimeout(searchTimeout);
                const term = $(this).val().toLowerCase();
                searchTimeout = setTimeout(() => {
                    $('.cpp-video-item').each(function() {
                        const title = $(this).find('h4').text().toLowerCase();
                        const desc = $(this).find('p').text().toLowerCase();
                        $(this).toggle(title.includes(term) || desc.includes(term));
                    });
                }, 300);
            });

            // Filters
            $('.cpp-filter-btn').on('click', function() {
                $('.cpp-filter-btn').removeClass('active');
                $(this).addClass('active');
                const filter = $(this).data('filter');
                
                if (filter === 'all') {
                    $('.cpp-video-item').show();
                } else {
                    $('.cpp-video-item').each(function() {
                        const $badge = $(this).find('.cpp-integration-badge');
                        $(this).toggle($badge.hasClass('cpp-' + filter));
                    });
                }
            });

            // Modal
            $('.cpp-video-link').on('click', function(e) {
                e.preventDefault();
                const videoId = $(this).data('video-id');
                if (videoId) openVideoModal(videoId);
            });

            $('.cpp-modal-close, .cpp-modal-overlay').on('click', () => {
                $('#cpp-video-modal').hide().attr('aria-hidden', 'true');
                $('#cpp-modal-video-container').empty();
            });

            function openVideoModal(videoId) {
                $.ajax({
                    url: '<?php echo esc_js($ajax_url); ?>',
                    method: 'POST',
                    data: {
                        action: 'cpp_get_video_preview',
                        video_id: videoId,
                        nonce: '<?php echo esc_js($nonce); ?>'
                    },
                    beforeSend: () => {
                        $('#cpp-modal-video-container').html('<div class="spinner is-active"></div>');
                        $('#cpp-video-modal').show().attr('aria-hidden', 'false');
                    },
                    success: (res) => {
                        if (res.success) {
                            $('#cpp-modal-title').text(res.data.title || '');
                            $('#cpp-modal-video-container').html(res.data.html || '');
                        } else {
                            $('#cpp-modal-video-container').html('<p class="cpp-error">' + (res.data?.message || 'Error') + '</p>');
                        }
                    },
                    error: () => {
                        $('#cpp-modal-video-container').html('<p class="cpp-error"><?php echo esc_js(__('Failed to load video', 'content-protect-pro')); ?></p>');
                    }
                });
            }
        });
        </script>
        <?php
    }

    /**
     * Check user access (session or token validation)
     * Following token-based auth pattern from copilot-instructions
     *
     * @param string $required_codes Comma-separated codes
     * @return bool
     */
    private function check_user_access($required_codes = '') {
        // Check for playback token cookie
        if (!empty($_COOKIE['cpp_playback_token'])) {
            $token = sanitize_text_field($_COOKIE['cpp_playback_token']);
            global $wpdb;
            
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}cpp_tokens 
                 WHERE token = %s AND expires_at >= NOW() LIMIT 1",
                $token
            ));

            if ($row) {
                return true;
            }
        }

        // Check session codes (legacy support)
        $session_codes = [];
        foreach ($_COOKIE as $key => $value) {
            if (strpos($key, 'cpp_session_') === 0) {
                $cookie_data = json_decode(base64_decode($value), true);
                if ($cookie_data && !empty($cookie_data['code'])) {
                    $session_codes[] = $cookie_data['code'];
                }
            }
        }

        if (!empty($required_codes)) {
            $required = array_map('trim', explode(',', $required_codes));
            return !empty(array_intersect($required, $session_codes));
        }

        return !empty($session_codes);
    }

    /**
     * Create playback token (following token-based auth pattern)
     * Ensures migrations run and sets HttpOnly cookie
     *
     * @param int $video_id Video ID
     */
    private function create_playback_token($video_id) {
        global $wpdb;

        // Ensure migrations run (only once per request)
        static $migrations_run = false;
        if (!$migrations_run && class_exists('CPP_Migrations')) {
            CPP_Migrations::maybe_migrate();
            $migrations_run = true;
        }

        $integration_settings = $this->get_integration_settings();
        $expiry_seconds = absint($integration_settings['token_expiry'] ?? 900);
        $expiry_seconds = max(60, $expiry_seconds); // Min 1 minute
        
        $expires_at = time() + $expiry_seconds;
        $token = bin2hex(random_bytes(32));
        $user_id = get_current_user_id();
        $ip_address = $this->get_client_ip();

        $wpdb->insert(
            $wpdb->prefix . 'cpp_tokens',
            [
                'token' => $token,
                'user_id' => $user_id,
                'video_id' => (string) $video_id,
                'expires_at' => gmdate('Y-m-d H:i:s', $expires_at),
                'ip_address' => $ip_address,
            ],
            ['%s', '%d', '%s', '%s', '%s']
        );

        // Set HttpOnly secure cookie (CSRF protection)
        $secure = is_ssl();
        $cookie_path = COOKIEPATH ?: '/';
        $cookie_domain = COOKIE_DOMAIN ?: '';
        
        setcookie(
            'cpp_playback_token',
            $token,
            $expires_at,
            $cookie_path,
            $cookie_domain,
            $secure,
            true // HttpOnly
        );
    }

    /**
     * Get client IP (following security pattern from copilot-instructions)
     *
     * @return string
     */
    private function get_client_ip() {
        if (function_exists('cpp_get_client_ip')) {
            return cpp_get_client_ip();
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        // Check proxy headers (Cloudflare, etc.)
        $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR'];
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                // Handle comma-separated IPs
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                break;
            }
        }

        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }

    /**
     * Get gift code manager instance (lazy loading)
     *
     * @return CPP_Giftcode_Manager
     */
    private function get_giftcode_manager() {
        if (!isset(self::$instances['giftcode_manager'])) {
            if (!class_exists('CPP_Giftcode_Manager')) {
                require_once CPP_PLUGIN_DIR . 'includes/class-cpp-giftcode-manager.php';
            }
            self::$instances['giftcode_manager'] = new CPP_Giftcode_Manager();
        }

        return self::$instances['giftcode_manager'];
    }

    /**
     * Get video manager instance (lazy loading)
     *
     * @return CPP_Video_Manager
     */
    private function get_video_manager() {
        if (!isset(self::$instances['video_manager'])) {
            if (!class_exists('CPP_Video_Manager')) {
                require_once CPP_PLUGIN_DIR . 'includes/class-cpp-video-manager.php';
            }
            self::$instances['video_manager'] = new CPP_Video_Manager();
        }

        return self::$instances['video_manager'];
    }

    /**
     * AJAX handler for gift code validation
     * Following CSRF protection pattern from copilot-instructions
     *
     * @since 1.0.0
     */
    public function validate_giftcode() {
        // CSRF protection (required by copilot-instructions)
        if (!check_ajax_referer('cpp_public_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __(__('Security check failed.', 'content-protect-pro'), 'content-protect-pro')], 403);
        }

        $code = sanitize_text_field($_POST['code'] ?? '');
        
        if (empty($code)) {
            wp_send_json_error(['message' => __(__('Gift code is required.', 'content-protect-pro'), 'content-protect-pro')], 400);
        }

        $giftcode_manager = $this->get_giftcode_manager();
        $result = $giftcode_manager->validate_code($code);
        
        if ($result['valid']) {
            // Create playback token
            $this->create_playback_token('');

            wp_send_json_success([
                'message' => __('Gift code validated successfully!', 'content-protect-pro'),
                'redirect_url' => $result['redirect_url'] ?? ''
            ]);
        }

        wp_send_json_error(['message' => $result['message']], 403);
    }

    /**
     * AJAX handler for video token generation
     * Optimized with provider-specific logic
     *
     * @since 1.0.0
     */
    public function get_video_token() {
        // CSRF protection
        if (!check_ajax_referer('cpp_public_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __(__('Security check failed.', 'content-protect-pro'), 'content-protect-pro')], 403);
        }

        $video_id = sanitize_text_field($_POST['video_id'] ?? '');
        
        if (empty($video_id)) {
            wp_send_json_error(['message' => __(__('Video ID is required.', 'content-protect-pro'), 'content-protect-pro')], 400);
        }

        $video_manager = $this->get_video_manager();
        $video_row = $video_manager->get_protected_video($video_id);

        if (!$video_row) {
            wp_send_json_error(['message' => __(__('Video not found.', 'content-protect-pro'), 'content-protect-pro')], 404);
        }

        $response = [];

        // Provider-specific handling (Presto preferred per copilot-instructions)
        $integration_type = strtolower($video_row->integration_type ?? '');

        if ($integration_type === 'presto' && !empty($video_row->presto_player_id)) {
            $presto_id = absint($video_row->presto_player_id);
            $embed_html = do_shortcode('[presto_player id="' . $presto_id . '"]');
            
            if (!empty($embed_html)) {
                $response['provider'] = 'presto';
                $response['presto_embed'] = $embed_html;
            }
        } elseif (!empty($video_row->direct_url)) {
            $response['provider'] = 'direct';
            $response['url'] = esc_url($video_row->direct_url);
        }

        // Add overlay/purchase URL
        $integration_settings = $this->get_integration_settings();
        
        $overlay = $this->resolve_overlay_image($integration_settings['overlay_image'] ?? '');
        if ($overlay) {
            $response['overlay_image'] = $overlay;
        }

        if (!empty($integration_settings['purchase_url'])) {
            $response['purchase_url'] = esc_url($integration_settings['purchase_url']);
        }

        if (!empty($response)) {
            wp_send_json_success($response);
        }

        wp_send_json_error(['message' => __(__('Unable to generate video access.', 'content-protect-pro'), 'content-protect-pro')], 500);
    }

    /**
     * AJAX handler for video preview (modal display)
     *
     * @since 1.0.0
     */
    public function get_video_preview() {
        // CSRF protection
        if (!check_ajax_referer('cpp_public_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __(__('Security check failed.', 'content-protect-pro'), 'content-protect-pro')], 403);
        }

        $video_id = sanitize_text_field($_POST['video_id'] ?? '');
        
        if (empty($video_id)) {
            wp_send_json_error(['message' => __(__('Video ID is required.', 'content-protect-pro'), 'content-protect-pro')], 400);
        }

        $video_manager = $this->get_video_manager();
        $video_row = $video_manager->get_protected_video($video_id);

        if (!$video_row) {
            wp_send_json_error(['message' => __(__('Video not found.', 'content-protect-pro'), 'content-protect-pro')], 404);
        }

        $preview_html = '';

        // Try Presto embed first (following architecture preference)
        if (!empty($video_row->presto_player_id)) {
            $presto_id = absint($video_row->presto_player_id);
            $embed_html = do_shortcode('[presto_player id="' . $presto_id . '"]');
            
            if (!empty($embed_html)) {
                $preview_html = $embed_html;
            }
        }

        // Fallback: thumbnail card
        if (empty($preview_html)) {
            $thumb = '';
            
            if (!empty($video_row->thumbnail_url)) {
                $thumb = esc_url($video_row->thumbnail_url);
            } elseif (!empty($video_row->video_id)) {
                $post = get_post(absint($video_row->video_id));
                if ($post) {
                    $thumb = get_the_post_thumbnail_url($post, 'medium');
                }
            }

            $title = esc_html($video_row->title ?? __('Video Preview', 'content-protect-pro'));
            $desc = !empty($video_row->description) 
                ? esc_html(wp_trim_words($video_row->description, 25)) 
                : '';

            ob_start();
            ?>
            <div class="cpp-preview-card">
                <?php if ($thumb): ?>
                    <div class="cpp-preview-thumb">
                        <img src="<?php echo esc_url($thumb); ?>" alt="<?php echo esc_attr($title); ?>" />
                    </div>
                <?php endif; ?>
                <div class="cpp-preview-meta">
                    <h4><?php echo esc_html($title); ?></h4>
                    <?php if ($desc): ?>
                        <p><?php echo esc_html($desc); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <?php
            $preview_html = ob_get_clean();
        }

        wp_send_json_success([
            'html' => $preview_html,
            'title' => $video_row->title ?? ''
        ]);
    }

    /**
     * AJAX handler for tracking video events
     * Optimized with lazy loading and error suppression
     *
     * @since 1.0.0
     */
    public function track_video_event() {
        // CSRF protection
        if (!check_ajax_referer('cpp_public_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __(__('Security check failed.', 'content-protect-pro'), 'content-protect-pro')], 403);
        }

        $event_type = sanitize_text_field($_POST['event_type'] ?? '');
        $video_id = sanitize_text_field($_POST['video_id'] ?? '');

        if (empty($event_type) || empty($video_id)) {
            wp_send_json_error(['message' => __(__('Missing parameters.', 'content-protect-pro'), 'content-protect-pro')], 400);
        }

        // Lazy load analytics
        if (!class_exists('CPP_Analytics')) {
            wp_send_json_success(['message' => 'Analytics disabled']);
        }

        $analytics = new CPP_Analytics();
        $analytics->log_event($event_type, 'video', $video_id, [
            'referrer' => esc_url_raw($_SERVER['HTTP_REFERER'] ?? ''),
        ]);

        wp_send_json_success(['message' => 'ok']);
    }
}