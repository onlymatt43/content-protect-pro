<?php
/**
 * Plugin Name: Content Protect Pro
 * Plugin URI: https://onlymatt.ca/content-protect-pro
 * Description: Token-based video protection with gift code redemption system. Presto Player integration with AI admin assistant.
 * Version: 3.1.0
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: OnlyMatt
 * Author URI: https://onlymatt.ca
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: content-protect-pro
 * Domain Path: /languages
 *
 * @package Content_Protect_Pro
 */

if (!defined('ABSPATH')) {
    exit;
}

// Plugin version (for cache busting and migrations)
define('CPP_VERSION', '3.1.0');

// Plugin root directory
define('CPP_PLUGIN_DIR', plugin_dir_path(__FILE__));

// Plugin root URL
define('CPP_PLUGIN_URL', plugin_dir_url(__FILE__));

// Plugin basename (for activation/deactivation hooks)
define('CPP_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Plugin activation hook
 * Creates database tables and sets default options
 */
function activate_content_protect_pro() {
    require_once CPP_PLUGIN_DIR . 'includes/class-cpp-activator.php';
    CPP_Activator::activate();
}

/**
 * Plugin deactivation hook
 * Cleans up transients and scheduled events
 */
function deactivate_content_protect_pro() {
    require_once CPP_PLUGIN_DIR . 'includes/class-cpp-deactivator.php';
    CPP_Deactivator::deactivate();
}

register_activation_hook(__FILE__, 'activate_content_protect_pro');
register_deactivation_hook(__FILE__, 'deactivate_content_protect_pro');

/**
 * Core plugin class
 * Loads dependencies and initializes the plugin
 */
require CPP_PLUGIN_DIR . 'includes/class-content-protect-pro.php';

/**
 * Bunny CDN Integration (LEGACY)
 * 
 * Optional integration per copilot-instructions.md.
 * Generates signed URLs for Bunny Stream videos.
 *
 * @package Content_Protect_Pro
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Guard against double-loading
if (class_exists('CPP_Bunny_Integration')) {
    return;
}

class CPP_Bunny_Integration {
    
    /**
     * Bunny CDN settings
     *
     * @var array
     */
    private $settings;
    
    /**
     * Constructor
     */
    public function __construct() {
        $integration_settings = get_option('cpp_integration_settings', array());
        
        $this->settings = array(
            'hostname' => isset($integration_settings['bunny_cdn_hostname']) ? $integration_settings['bunny_cdn_hostname'] : '',
            'token_key' => isset($integration_settings['bunny_token_key']) ? $integration_settings['bunny_token_key'] : '',
            'api_key' => isset($integration_settings['bunny_api_key']) ? $integration_settings['bunny_api_key'] : '',
        );
    }
    
    /**
     * Check if Bunny CDN is configured
     *
     * @return bool Bunny CDN is available
     */
    public function is_available() {
        return !empty($this->settings['hostname']) && !empty($this->settings['token_key']);
    }
    
    /**
     * Generate signed URL for Bunny Stream video
     * Per copilot-instructions: LEGACY integration method
     *
     * @param string $video_url Base video URL
     * @param int $expires_in Expiration time in seconds (default: 3600)
     * @return string|false Signed URL or false on failure
     */
    public function generate_signed_url($video_url, $expires_in = 3600) {
        if (!$this->is_available()) {
            return false;
        }
        
        if (empty($video_url)) {
            return false;
        }
        
        $expires = time() + absint($expires_in);
        
        $parsed_url = wp_parse_url($video_url);
        $path = isset($parsed_url['path']) ? $parsed_url['path'] : '';
        
        if (empty($path)) {
            return false;
        }
        
        $signature_string = $this->settings['token_key'] . $path . $expires;
        $signature = hash('sha256', $signature_string);
        
        $signed_url = sprintf(
            'https://%s%s?token=%s&expires=%d',
            $this->settings['hostname'],
            $path,
            $signature,
            $expires
        );
        
        if (class_exists('CPP_Analytics')) {
            $analytics = new CPP_Analytics();
            $analytics->log_event('bunny_signed_url_generated', 'video', $video_url, array(
                'expires_in' => $expires_in,
            ));
        }
        
        return $signed_url;
    }
    
    /**
     * Validate Bunny video URL format
     *
     * @param string $video_url Video URL
     * @return bool URL is valid
     */
    public function validate_video_url($video_url) {
        if (empty($video_url)) {
            return false;
        }
        
        $parsed = wp_parse_url($video_url);
        
        if (!isset($parsed['scheme']) || !isset($parsed['host'])) {
            return false;
        }
        
        if (!empty($this->settings['hostname'])) {
            return strpos($parsed['host'], $this->settings['hostname']) !== false;
        }
        
        return strpos($parsed['host'], 'b-cdn.net') !== false;
    }
    
    /**
     * Get video metadata from Bunny API
     * LEGACY method - requires API key
     *
     * @param string $video_id Bunny video ID
     * @return array|false Video metadata or false
     */
    public function get_video_metadata($video_id) {
        if (empty($this->settings['api_key'])) {
            return false;
        }
        
        $api_url = sprintf(
            'https://video.bunnycdn.com/library/%s/videos/%s',
            $this->settings['library_id'],
            sanitize_text_field($video_id)
        );
        
        $response = wp_remote_get($api_url, array(
            'headers' => array(
                'AccessKey' => $this->settings['api_key'],
            ),
            'timeout' => 15,
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        return is_array($data) ? $data : false;
    }
}

/**
 * Begin plugin execution
 */
function run_content_protect_pro() {
    $plugin = new Content_Protect_Pro();
    $plugin->run();
}
run_content_protect_pro();


// ❌ AVANT
echo '<div>' . $video_title . '</div>';

// ✅ APRÈS
echo '<div>' . esc_html($video_title) . '</div>';

/**
 * AJAX handler for gift code validation
 * Per copilot-instructions: MUST include nonce + rate limiting
 */
function cpp_ajax_validate_giftcode() {
    // Security validation (copilot-instructions pattern)
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'cpp_public_nonce')) {
        wp_send_json_error([
            'message' => __('Security check failed. Please refresh and try again.', 'content-protect-pro')
        ], 403);
    }
    
    // Rate limiting
    if (!class_exists('CPP_Giftcode_Security')) {
        require_once plugin_dir_path(__FILE__) . 'includes/class-cpp-giftcode-security.php';
    }
    
    $security = new CPP_Giftcode_Security();
    $client_ip = $security->get_client_ip();
    
    if (!$security->check_rate_limit($client_ip, 'redeem_code')) {
        wp_send_json_error([
            'message' => __('Too many attempts. Please wait a few minutes and try again.', 'content-protect-pro')
        ], 429);
    }
    
    // Sanitize input
    $code = sanitize_text_field($_POST['code'] ?? '');
    
    if (empty($code)) {
        wp_send_json_error([
            'message' => __('Gift code is required.', 'content-protect-pro')
        ], 400);
    }
    
    // Validate code
    if (!class_exists('CPP_Giftcode_Manager')) {
        require_once plugin_dir_path(__FILE__) . 'includes/class-cpp-giftcode-manager.php';
    }
    
    $manager = new CPP_Giftcode_Manager();
    $result = $manager->validate_code($code);
    
    if (!$result['valid']) {
        wp_send_json_error([
            'message' => esc_html($result['message'])
        ], 400);
    }
    
    // Create session
    if (!class_exists('CPP_Protection_Manager')) {
        require_once plugin_dir_path(__FILE__) . 'includes/class-cpp-protection-manager.php';
    }
    
    $protection = new CPP_Protection_Manager();
    $session = $protection->create_session($code, $client_ip);
    
    if (!$session) {
        wp_send_json_error([
            'message' => __('Failed to create session. Please try again.', 'content-protect-pro')
        ], 500);
    }
    
    wp_send_json_success([
        'message' => __('Gift code validated successfully!', 'content-protect-pro'),
        'duration_minutes' => absint($result['duration_minutes']),
        'expires_at' => esc_html($session['expires_at']),
    ]);
}