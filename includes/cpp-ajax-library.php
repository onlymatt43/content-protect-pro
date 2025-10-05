<?php
/**
 * AJAX Handlers for Enhanced Video Library
 * 
 * Handles video loading, filtering, and playback requests.
 * Follows security patterns from copilot-instructions.
 * 
 * @package ContentProtectPro
 * @since 3.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Load videos for library (AJAX)
 * Action: wp_ajax_cpp_load_library_videos
 */
function cpp_load_library_videos_ajax() {
    // CSRF protection (required by copilot-instructions)
    if (!check_ajax_referer('cpp_library_nonce', 'nonce', false)) {
        wp_send_json_error([
            'message' => __('Security check failed', 'content-protect-pro')
        ], 403);
    }
    
    // Get parameters
    $category = isset(sanitize_text_field($_POST['category'] ?? '')) ? sanitize_text_field(sanitize_text_field($_POST['category'] ?? '')) : '';
    $search = isset(sanitize_text_field($_POST['search'] ?? '')) ? sanitize_text_field(sanitize_text_field($_POST['search'] ?? '')) : '';
    $page = isset(sanitize_text_field($_POST['page'] ?? '')) ? absint(sanitize_text_field($_POST['page'] ?? '')) : 1;
    $per_page = isset(sanitize_text_field($_POST['per_page'] ?? '')) ? absint(sanitize_text_field($_POST['per_page'] ?? '')) : 12;
    $integration_type = isset(sanitize_text_field($_POST['integration_type'] ?? '')) ? sanitize_text_field(sanitize_text_field($_POST['integration_type'] ?? '')) : '';
    
    // Validate per_page (prevent abuse)
    $per_page = min($per_page, 50);
    
    $args = [
        'category' => $category,
        'search' => $search,
        'limit' => $per_page,
        'offset' => ($page - 1) * $per_page,
        'integration_type' => $integration_type
    ];
    
    $result = CPP_Video_Library::get_library_videos($args);
    
    // Format videos for frontend
    $videos_html = array_map(function($video) {
        return cpp_render_video_card($video);
    }, $result['videos']);
    
    wp_send_json_success([
        'videos_html' => $videos_html,
        'total' => $result['total'],
        'page' => $result['page'],
        'total_pages' => ceil($result['total'] / $result['per_page']),
        'has_session' => $result['has_session'],
        'session_minutes' => $result['session_minutes']
    ]);
}
add_action('wp_ajax_cpp_load_library_videos', 'cpp_load_library_videos_ajax');
add_action('wp_ajax_nopriv_cpp_load_library_videos', 'cpp_load_library_videos_ajax');

/**
 * Get video playback (AJAX)
 * Action: wp_ajax_cpp_get_video_playback
 */
function cpp_get_video_playback_ajax() {
    // CSRF protection
    if (!check_ajax_referer('cpp_library_nonce', 'nonce', false)) {
        wp_send_json_error([
            'message' => __('Security check failed', 'content-protect-pro')
        ], 403);
    }
    
    $video_id = isset(sanitize_text_field($_POST['video_id'] ?? '')) ? sanitize_text_field(sanitize_text_field($_POST['video_id'] ?? '')) : '';
    
    if (empty($video_id)) {
        wp_send_json_error([
            'message' => __('Video ID required', 'content-protect-pro')
        ], 400);
    }
    
    $result = CPP_Video_Library::get_video_playback($video_id);
    
    if ($result['success']) {
        wp_send_json_success($result['data']);
    } else {
        wp_send_json_error([
            'message' => $result['message']
        ], 403);
    }
}
add_action('wp_ajax_cpp_get_video_playback', 'cpp_get_video_playback_ajax');
add_action('wp_ajax_nopriv_cpp_get_video_playback', 'cpp_get_video_playback_ajax');

/**
 * Render video card HTML
 * 
 * @param array $video Video data
 * @return string HTML
 */
function cpp_render_video_card($video) {
    $has_access = $video['has_access'];
    $lock_class = $has_access ? '' : 'cpp-video-locked';
    
    // Fallback thumbnail
    $thumbnail = !empty($video['thumbnail']) 
        ? esc_url($video['thumbnail']) 
        : plugins_url('public/images/video-placeholder.jpg', dirname(__FILE__));
    
    ob_start();
    ?>
    <div class="cpp-video-card <?php echo esc_attr($lock_class); ?>" data-video-id="<?php echo esc_attr($video['video_id']); ?>">
        <div class="cpp-video-thumbnail">
            <img src="<?php echo esc_html($thumbnail); ?>" alt="<?php echo esc_attr($video['title']); ?>" loading="lazy" />
            
            <?php if (!$has_access): ?>
                <div class="cpp-video-overlay">
                    <span class="dashicons dashicons-lock"></span>
                    <span class="cpp-required-minutes">
                        <?php 
                        printf(
                            /* translators: %d: required minutes */
                            esc_html__('Requires %d min', 'content-protect-pro'),
                            (int) $video['required_minutes']
                        ); 
                        ?>
                    </span>
                </div>
            <?php else: ?>
                <div class="cpp-video-play-btn">
                    <span class="dashicons dashicons-controls-play"></span>
                </div>
            <?php endif; ?>
            
            <?php if ($video['duration']): ?>
                <span class="cpp-video-duration"><?php echo esc_html(gmdate('i:s', $video['duration'])); ?></span>
            <?php endif; ?>
        </div>
        
        <div class="cpp-video-info">
            <h3 class="cpp-video-title"><?php echo esc_html($video['title']); ?></h3>
            
            <?php if (!empty($video['description'])): ?>
                <p class="cpp-video-description"><?php echo esc_html(wp_trim_words($video['description'], 15)); ?></p>
            <?php endif; ?>
            
            <div class="cpp-video-meta">
                <span class="cpp-video-category">
                    <span class="dashicons dashicons-category"></span>
                    <?php echo esc_html(ucfirst($video['category'])); ?>
                </span>
                
                <span class="cpp-video-type">
                    <?php if ($video['integration_type'] === 'presto'): ?>
                        <span class="dashicons dashicons-video-alt3"></span>
                        <?php echo esc_html__('Presto Player', 'content-protect-pro'); ?>
                    <?php else: ?>
                        <span class="dashicons dashicons-cloud"></span>
                        <?php echo esc_html__('Bunny CDN', 'content-protect-pro'); ?>
                    <?php endif; ?>
                </span>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}