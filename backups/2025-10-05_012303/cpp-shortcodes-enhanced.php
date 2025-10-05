<?php
<?php
/**
 * Enhanced Shortcodes for Video Library
 * 
 * Provides modern gallery with filtering, search, and proper Presto/Bunny integration.
 * 
 * @package ContentProtectPro
 * @since 3.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Enhanced video library shortcode
 * Usage: [cpp_video_library_v2 category="tutorials" per_page="12"]
 * 
 * @param array $atts Shortcode attributes
 * @return string HTML output
 */
function cpp_video_library_v2_shortcode($atts) {
    $atts = shortcode_atts([
        'category' => '',
        'per_page' => 12,
        'columns' => 3,
        'show_search' => 'yes',
        'show_filters' => 'yes',
        'integration_type' => '', // 'presto', 'bunny', or empty for both
    ], $atts, 'cpp_video_library_v2');
    
    // Check if user has session
    $session = CPP_Video_Library::get_current_session();
    
    ob_start();
    ?>
    
    <div class="cpp-video-library-enhanced" data-per-page="<?php echo esc_attr($atts['per_page']); ?>" data-columns="<?php echo esc_attr($atts['columns']); ?>">
        
        <?php if (!$session): ?>
            <!-- No Session: Show Redemption Prompt -->
            <div class="cpp-library-locked">
                <div class="cpp-lock-icon">
                    <span class="dashicons dashicons-lock"></span>
                </div>
                <h3><?php echo esc_html__('Unlock Video Library', 'content-protect-pro'); ?></h3>
                <p><?php echo esc_html__('Please redeem a gift code to access our video library.', 'content-protect-pro'); ?></p>
                
                <?php 
                // Include gift code form
                echo do_shortcode('[cpp_giftcode_form]'); 
                ?>
            </div>
        <?php else: ?>
            <!-- Has Session: Show Library -->
            
            <?php if ($atts['show_search'] === 'yes' || $atts['show_filters'] === 'yes'): ?>
            <div class="cpp-library-controls">
                
                <?php if ($atts['show_search'] === 'yes'): ?>
                <div class="cpp-library-search">
                    <input 
                        type="search" 
                        id="cpp-video-search" 
                        class="cpp-search-input"
                        placeholder="<?php echo esc_attr__('Search videos...', 'content-protect-pro'); ?>"
                    />
                    <button type="button" class="cpp-search-btn">
                        <span class="dashicons dashicons-search"></span>
                    </button>
                </div>
                <?php endif; ?>
                
                <?php if ($atts['show_filters'] === 'yes'): ?>
                <div class="cpp-library-filters">
                    <label for="cpp-category-filter"><?php echo esc_html__('Category:', 'content-protect-pro'); ?></label>
                    <select id="cpp-category-filter" class="cpp-filter-select">
                        <option value=""><?php echo esc_html__('All Categories', 'content-protect-pro'); ?></option>
                        <?php
                        $categories = CPP_Video_Library::get_categories();
                        foreach ($categories as $cat => $count) {
                            $selected = ($atts['category'] === $cat) ? 'selected' : '';
                            printf(
                                '<option value="%s" %s>%s (%d)</option>',
                                esc_attr($cat),
                                $selected,
                                esc_html(ucfirst($cat)),
                                (int) $count
                            );
                        }
                        ?>
                    </select>
                    
                    <?php if (!empty($atts['integration_type'])): ?>
                        <input type="hidden" id="cpp-integration-filter" value="<?php echo esc_attr($atts['integration_type']); ?>" />
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
            </div>
            <?php endif; ?>
            
            <!-- Session Info Bar -->
            <div class="cpp-session-info">
                <span class="cpp-session-icon">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <?php echo esc_html__('Active Session', 'content-protect-pro'); ?>
                </span>
                <span class="cpp-session-minutes">
                    <?php 
                    printf(
                        /* translators: %d: number of minutes */
                        esc_html__('%d minutes access', 'content-protect-pro'),
                        (int) $session->duration_minutes
                    ); 
                    ?>
                </span>
                <span class="cpp-session-expires">
                    <?php 
                    printf(
                        /* translators: %s: expiration time */
                        esc_html__('Expires: %s', 'content-protect-pro'),
                        esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($session->expires_at)))
                    ); 
                    ?>
                </span>
            </div>
            
            <!-- Video Grid -->
            <div class="cpp-video-grid" data-columns="<?php echo esc_attr($atts['columns']); ?>">
                <div class="cpp-loading-spinner" style="display:none;">
                    <span class="spinner is-active"></span>
                    <p><?php echo esc_html__('Loading videos...', 'content-protect-pro'); ?></p>
                </div>
                
                <!-- Videos will be loaded here via AJAX -->
            </div>
            
            <!-- Pagination -->
            <div class="cpp-library-pagination">
                <!-- Pagination buttons will be inserted here -->
            </div>
            
        <?php endif; ?>
    </div>
    
    <?php
    return ob_get_clean();
}
add_shortcode('cpp_video_library_v2', 'cpp_video_library_v2_shortcode');

/**
 * Single video player shortcode (enhanced)
 * Usage: [cpp_video_player id="video-123" integration="presto"]
 * 
 * @param array $atts Shortcode attributes
 * @return string HTML output
 */
function cpp_video_player_enhanced_shortcode($atts) {
    $atts = shortcode_atts([
        'id' => '',
        'integration' => 'presto', // 'presto' or 'bunny'
        'width' => '100%',
        'height' => 'auto',
        'autoplay' => 'no'
    ], $atts, 'cpp_video_player');
    
    if (empty($atts['id'])) {
        return '<p class="cpp-error">' . esc_html__('Video ID required', 'content-protect-pro') . '</p>';
    }
    
    // Get playback data
    $playback = CPP_Video_Library::get_video_playback($atts['id']);
    
    if (!$playback['success']) {
        ob_start();
        ?>
        <div class="cpp-video-locked">
            <div class="cpp-lock-overlay">
                <span class="dashicons dashicons-lock"></span>
                <p><?php echo esc_html($playback['message']); ?></p>
                <?php if (isset($playback['required_minutes'])): ?>
                    <p class="cpp-access-info">
                        <?php 
                        printf(
                            esc_html__('Required: %d minutes | Your session: %d minutes', 'content-protect-pro'),
                            (int) $playback['required_minutes'],
                            (int) $playback['session_minutes']
                        ); 
                        ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    ob_start();
    
    if ($playback['type'] === 'embed') {
        // Presto Player embed
        ?>
        <div class="cpp-video-player cpp-presto-player" style="width: <?php echo esc_attr($atts['width']); ?>;">
            <?php echo $playback['data']['embed_html']; // Already sanitized by do_shortcode ?>
        </div>
        <?php
    } elseif ($playback['type'] === 'url') {
        // Bunny CDN direct URL
        ?>
        <div class="cpp-video-player cpp-bunny-player" style="width: <?php echo esc_attr($atts['width']); ?>;">
            <video 
                controls 
                <?php echo ($atts['autoplay'] === 'yes') ? 'autoplay' : ''; ?>
                style="width: 100%; height: <?php echo esc_attr($atts['height']); ?>;"
            >
                <source src="<?php echo esc_url($playback['data']['playback_url']); ?>" type="video/mp4">
                <?php echo esc_html__('Your browser does not support the video tag.', 'content-protect-pro'); ?>
            </video>
        </div>
        <?php
    }
    
    return ob_get_clean();
}
add_shortcode('cpp_video_player', 'cpp_video_player_enhanced_shortcode');