<?php
/**
 * Shortcode System
 * 
 * Following copilot-instructions.md patterns:
 * - [cpp_giftcode_form] - Gift code redemption
 * - [cpp_protected_video id="X"] - Single video player
 * - [cpp_video_library] - Gallery with filters
 * - [cpp_giftcode_check required_codes="X,Y"] - Conditional content
 *
 * @package Content_Protect_Pro
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register all shortcodes
 */
add_shortcode('cpp_giftcode_form', 'cpp_shortcode_giftcode_form');
add_shortcode('cpp_protected_video', 'cpp_shortcode_protected_video');
add_shortcode('cpp_video_library', 'cpp_shortcode_video_library');
add_shortcode('cpp_giftcode_check', 'cpp_shortcode_giftcode_check');

/**
 * Gift code redemption form
 * 
 * Usage: [cpp_giftcode_form]
 * Attributes: redirect_url (optional URL after success)
 */
function cpp_shortcode_giftcode_form($atts) {
    $atts = shortcode_atts([
        'redirect_url' => '',
        'button_text' => __('Validate Code', 'content-protect-pro'),
        'placeholder' => __('Enter your gift code', 'content-protect-pro'),
    ], $atts, 'cpp_giftcode_form');
    
    // Check if user already has active session
    $has_session = false;
    $session_info = null;
    
    if (!empty($_COOKIE['cpp_session_token'])) {
        if (!class_exists('CPP_Protection_Manager')) {
            require_once CPP_PLUGIN_DIR . 'includes/class-cpp-protection-manager.php';
        }
        
        $protection = new CPP_Protection_Manager();
        $session = $protection->get_active_session(sanitize_text_field($_COOKIE['cpp_session_token']));
        
        if ($session) {
            $has_session = true;
            $session_info = [
                'duration_minutes' => (int) $session->duration_minutes,
                'expires_at' => $session->expires_at,
            ];
        }
    }
    
    ob_start();
    ?>
    <div class="cpp-giftcode-form-wrapper" data-cpp-form>
        <?php if ($has_session): ?>
            <div class="cpp-session-active">
                <p class="cpp-success-message">
                    <?php
                    printf(
                        esc_html__('✓ Active session: %d minutes of access', 'content-protect-pro'),
                        $session_info['duration_minutes']
                    );
                    ?>
                </p>
                <p class="cpp-session-expires">
                    <?php
                    printf(
                        esc_html__('Expires: %s', 'content-protect-pro'),
                        esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($session_info['expires_at'])))
                    );
                    ?>
                </p>
                <button type="button" class="cpp-btn cpp-btn-secondary" data-cpp-end-session>
                    <?php esc_html_e('End Session', 'content-protect-pro'); ?>
                </button>
            </div>
        <?php else: ?>
            <form class="cpp-giftcode-form" data-cpp-redeem-form>
                <div class="cpp-form-group">
                    <label for="cpp-gift-code">
                        <?php esc_html_e('Gift Code', 'content-protect-pro'); ?>
                    </label>
                    <input 
                        type="text" 
                        id="cpp-gift-code" 
                        name="code" 
                        class="cpp-input"
                        placeholder="<?php echo esc_attr($atts['placeholder']); ?>"
                        required
                        autocomplete="off"
                        pattern="[A-Z0-9]{6,12}"
                        title="<?php esc_attr_e('Enter your gift code (6-12 characters)', 'content-protect-pro'); ?>"
                    />
                </div>
                
                <button type="submit" class="cpp-btn cpp-btn-primary">
                    <?php echo esc_html($atts['button_text']); ?>
                </button>
                
                <?php if (!empty($atts['redirect_url'])): ?>
                    <input type="hidden" name="redirect_url" value="<?php echo esc_url($atts['redirect_url']); ?>" />
                <?php endif; ?>
            </form>
            
            <div class="cpp-form-messages" data-cpp-messages style="display:none;">
                <div class="cpp-message"></div>
            </div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Protected video player
 * 
 * Usage: [cpp_protected_video id="presto-123"]
 * Attributes: 
 *   id (required) - Video ID or Presto Player ID
 *   show_overlay (default: true) - Show overlay when locked
 */
function cpp_shortcode_protected_video($atts) {
    $atts = shortcode_atts([
        'id' => '',
        'show_overlay' => 'true',
    ], $atts, 'cpp_protected_video');
    
    if (empty($atts['id'])) {
        return '<p class="cpp-error">' . esc_html__('Error: Video ID is required.', 'content-protect-pro') . '</p>';
    }
    
    $video_id = sanitize_text_field($atts['id']);
    $show_overlay = filter_var($atts['show_overlay'], FILTER_VALIDATE_BOOLEAN);
    
    // Get video data
    if (!class_exists('CPP_Video_Manager')) {
        require_once CPP_PLUGIN_DIR . 'includes/class-cpp-video-manager.php';
    }
    
    $video_manager = new CPP_Video_Manager();
    $video = $video_manager->get_protected_video($video_id);
    
    if (!$video) {
        return '<p class="cpp-error">' . esc_html__('Error: Video not found.', 'content-protect-pro') . '</p>';
    }
    
    // Check if user has access
    $has_access = false;
    $session_token = $_COOKIE['cpp_session_token'] ?? '';
    
    if (!empty($session_token)) {
        if (!class_exists('CPP_Protection_Manager')) {
            require_once CPP_PLUGIN_DIR . 'includes/class-cpp-protection-manager.php';
        }
        
        $protection = new CPP_Protection_Manager();
        $has_access = $protection->check_video_access($video_id, $session_token);
    }
    
    ob_start();
    ?>
    <div class="cpp-video-container" data-video-id="<?php echo esc_attr($video_id); ?>">
        <?php if ($has_access): ?>
            <div class="cpp-video-player" data-cpp-video-player>
                <?php
                // Generate playback based on integration type
                if ($video->integration_type === 'presto' && !empty($video->presto_player_id)) {
                    // Presto Player embed (primary per copilot-instructions)
                    echo do_shortcode('[presto_player id="' . absint($video->presto_player_id) . '"]');
                } elseif (!empty($video->direct_url)) {
                    // Fallback: Direct URL video player
                    ?>
                    <video 
                        controls 
                        preload="metadata"
                        style="width:100%;height:auto;"
                        poster="<?php echo esc_url($video->thumbnail_url); ?>"
                    >
                        <source src="<?php echo esc_url($video->direct_url); ?>" type="video/mp4" />
                        <?php esc_html_e('Your browser does not support the video tag.', 'content-protect-pro'); ?>
                    </video>
                    <?php
                }
                ?>
            </div>
        <?php else: ?>
            <div class="cpp-video-locked">
                <?php if ($show_overlay): ?>
                    <?php
                    // Get overlay image (attachment ID per copilot-instructions POST-MIGRATION)
                    $settings = get_option('cpp_integration_settings', []);
                    $overlay_id = $settings['overlay_image'] ?? 0;
                    $overlay_url = '';
                    
                    if ($overlay_id) {
                        $overlay_url = wp_get_attachment_url(absint($overlay_id));
                    }
                    
                    // Fallback to thumbnail
                    if (empty($overlay_url) && !empty($video->thumbnail_url)) {
                        $overlay_url = $video->thumbnail_url;
                    }
                    ?>
                    
                    <?php if ($overlay_url): ?>
                        <div class="cpp-overlay-image">
                            <img src="<?php echo esc_url($overlay_url); ?>" alt="<?php esc_attr_e('Video Locked', 'content-protect-pro'); ?>" />
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
                
                <div class="cpp-lock-message">
                    <span class="cpp-lock-icon">🔒</span>
                    <h4><?php esc_html_e('This video is protected', 'content-protect-pro'); ?></h4>
                    <p>
                        <?php
                        printf(
                            esc_html__('Requires %d minutes of access', 'content-protect-pro'),
                            absint($video->required_minutes)
                        );
                        ?>
                    </p>
                    
                    <?php
                    // Link to purchase or code entry
                    $purchase_url = $settings['purchase_url'] ?? '';
                    if (!empty($purchase_url)):
                    ?>
                        <a href="<?php echo esc_url($purchase_url); ?>" class="cpp-btn cpp-btn-primary">
                            <?php esc_html_e('Get Access', 'content-protect-pro'); ?>
                        </a>
                    <?php else: ?>
                        <p class="cpp-redeem-hint">
                            <?php esc_html_e('Have a gift code?', 'content-protect-pro'); ?>
                            <a href="#cpp-redeem-form"><?php esc_html_e('Enter it here', 'content-protect-pro'); ?></a>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Video library gallery
 * 
 * Usage: [cpp_video_library per_page="12" show_search="true"]
 * Attributes:
 *   per_page (default: 12) - Videos per page
 *   show_search (default: true) - Show search box
 *   show_filters (default: true) - Show access level filters
 *   integration_type - Filter by 'presto', 'bunny', or empty for all
 */
function cpp_shortcode_video_library($atts) {
    $atts = shortcode_atts([
        'per_page' => 12,
        'show_search' => 'true',
        'show_filters' => 'true',
        'integration_type' => '',
    ], $atts, 'cpp_video_library');
    
    $per_page = absint($atts['per_page']) ?: 12;
    $show_search = filter_var($atts['show_search'], FILTER_VALIDATE_BOOLEAN);
    $show_filters = filter_var($atts['show_filters'], FILTER_VALIDATE_BOOLEAN);
    
    // Get query params
    $page = isset($_GET['cpp_page']) ? absint($_GET['cpp_page']) : 1;
    $search = isset($_GET['cpp_search']) ? sanitize_text_field($_GET['cpp_search']) : '';
    $access_filter = isset($_GET['cpp_access']) ? absint($_GET['cpp_access']) : 0;
    
    // Get user's session for access level
    $user_access_minutes = 0;
    if (!empty($_COOKIE['cpp_session_token'])) {
        if (!class_exists('CPP_Protection_Manager')) {
            require_once CPP_PLUGIN_DIR . 'includes/class-cpp-protection-manager.php';
        }
        
        $protection = new CPP_Protection_Manager();
        $session = $protection->get_active_session(sanitize_text_field($_COOKIE['cpp_session_token']));
        
        if ($session) {
            $user_access_minutes = (int) $session->duration_minutes;
        }
    }
    
    // Get videos
    if (!class_exists('CPP_Video_Manager')) {
        require_once CPP_PLUGIN_DIR . 'includes/class-cpp-video-manager.php';
    }
    
    $video_manager = new CPP_Video_Manager();
    $result = $video_manager->get_protected_videos([
        'per_page' => $per_page,
        'page' => $page,
        'search' => $search,
        'access_level' => $access_filter,
        'integration_type' => $atts['integration_type'],
    ]);
    
    ob_start();
    ?>
    <div class="cpp-video-library-wrapper">
        <?php if ($show_search || $show_filters): ?>
            <div class="cpp-library-filters">
                <?php if ($show_search): ?>
                    <form method="get" class="cpp-search-form">
                        <input 
                            type="text" 
                            name="cpp_search" 
                            placeholder="<?php esc_attr_e('Search videos...', 'content-protect-pro'); ?>"
                            value="<?php echo esc_attr($search); ?>"
                            class="cpp-search-input"
                        />
                        <button type="submit" class="cpp-btn cpp-btn-secondary">
                            <?php esc_html_e('Search', 'content-protect-pro'); ?>
                        </button>
                    </form>
                <?php endif; ?>
                
                <?php if ($show_filters): ?>
                    <div class="cpp-access-filters">
                        <button type="button" class="cpp-filter-btn <?php echo ($access_filter === 0) ? 'active' : ''; ?>" data-filter="0">
                            <?php esc_html_e('All Videos', 'content-protect-pro'); ?>
                        </button>
                        <button type="button" class="cpp-filter-btn <?php echo ($access_filter === $user_access_minutes) ? 'active' : ''; ?>" data-filter="<?php echo esc_attr($user_access_minutes); ?>">
                            <?php esc_html_e('Available to Me', 'content-protect-pro'); ?>
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($result['videos'])): ?>
            <div class="cpp-video-grid">
                <?php foreach ($result['videos'] as $video): ?>
                    <?php
                    $has_access = ($user_access_minutes >= (int) $video->required_minutes);
                    $thumbnail = '';
                    
                    if (!empty($video->thumbnail_url)) {
                        $thumbnail = $video->thumbnail_url;
                    } elseif (!empty($video->presto_player_id)) {
                        $thumbnail = get_the_post_thumbnail_url(absint($video->presto_player_id), 'medium');
                    }
                    ?>
                    
                    <div class="cpp-video-card <?php echo $has_access ? 'has-access' : 'locked'; ?>">
                        <?php if ($thumbnail): ?>
                            <div class="cpp-card-thumb">
                                <img src="<?php echo esc_url($thumbnail); ?>" alt="<?php echo esc_attr($video->title); ?>" loading="lazy" />
                                <?php if (!$has_access): ?>
                                    <span class="cpp-lock-badge">🔒</span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="cpp-card-body">
                            <h4 class="cpp-card-title"><?php echo esc_html($video->title); ?></h4>
                            
                            <?php if (!empty($video->description)): ?>
                                <p class="cpp-card-desc">
                                    <?php echo esc_html(wp_trim_words($video->description, 15)); ?>
                                </p>
                            <?php endif; ?>
                            
                            <div class="cpp-card-meta">
                                <span class="cpp-access-badge">
                                    <?php
                                    printf(
                                        esc_html__('%d min access required', 'content-protect-pro'),
                                        absint($video->required_minutes)
                                    );
                                    ?>
                                </span>
                            </div>
                            
                            <div class="cpp-card-actions">
                                <?php if ($has_access): ?>
                                    <a href="<?php echo esc_url(add_query_arg('cpp_video', $video->video_id)); ?>" class="cpp-btn cpp-btn-primary">
                                        <?php esc_html_e('Watch Now', 'content-protect-pro'); ?>
                                    </a>
                                <?php else: ?>
                                    <button type="button" class="cpp-btn cpp-btn-secondary" data-cpp-video-preview="<?php echo esc_attr($video->video_id); ?>">
                                        <?php esc_html_e('Preview', 'content-protect-pro'); ?>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <?php if ($result['pages'] > 1): ?>
                <div class="cpp-pagination">
                    <?php
                    for ($i = 1; $i <= $result['pages']; $i++) {
                        $url = add_query_arg('cpp_page', $i);
                        $active = ($i === $page) ? 'active' : '';
                        echo '<a href="' . esc_url($url) . '" class="cpp-page-link ' . esc_attr($active) . '">' . absint($i) . '</a>';
                    }
                    ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <p class="cpp-no-results">
                <?php esc_html_e('No videos found.', 'content-protect-pro'); ?>
            </p>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Conditional content based on gift code access
 * 
 * Usage: [cpp_giftcode_check required_codes="VIP2024,PREMIUM"]Content[/cpp_giftcode_check]
 * Shows content only if user has redeemed one of the required codes
 */
function cpp_shortcode_giftcode_check($atts, $content = '') {
    $atts = shortcode_atts([
        'required_codes' => '',
        'required_minutes' => 0,
    ], $atts, 'cpp_giftcode_check');
    
    // Check if user has active session
    if (empty($_COOKIE['cpp_session_token'])) {
        return '';
    }
    
    if (!class_exists('CPP_Protection_Manager')) {
        require_once CPP_PLUGIN_DIR . 'includes/class-cpp-protection-manager.php';
    }
    
    $protection = new CPP_Protection_Manager();
    $session = $protection->get_active_session(sanitize_text_field($_COOKIE['cpp_session_token']));
    
    if (!$session) {
        return '';
    }
    
    // Check required codes
    if (!empty($atts['required_codes'])) {
        $required_codes = array_map('trim', explode(',', $atts['required_codes']));
        if (!in_array($session->code, $required_codes, true)) {
            return '';
        }
    }
    
    // Check required minutes
    if (!empty($atts['required_minutes'])) {
        $required_minutes = absint($atts['required_minutes']);
        if ((int) $session->duration_minutes < $required_minutes) {
            return '';
        }
    }
    
    // User has access - show content
    return do_shortcode($content);
}