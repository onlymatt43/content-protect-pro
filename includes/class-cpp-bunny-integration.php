<?php
/**
 * Bunny CDN Integration
 * 
 * LEGACY/OPTIONAL support per copilot-instructions.md.
 * Generates signed URLs for Bunny CDN with token authentication.
 *
 * @package Content_Protect_Pro
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class CPP_Bunny_Integration {
    
    /**
     * Generate signed Bunny CDN URL
     * Following Bunny's token authentication system
     *
     * @param string $base_url Base video URL
     * @param int $expires_seconds Expiration time in seconds
     * @return string|false Signed URL or false
     */
    public function generate_signed_url($base_url, $expires_seconds = 3600) {
        // Get Bunny CDN settings
        $settings = get_option('cpp_integration_settings', []);
        $token_key = $settings['bunny_token_key'] ?? '';
        
        if (empty($token_key) || empty($base_url)) {
            return false;
        }
        
        // Parse URL to get path
        $parsed = wp_parse_url($base_url);
        if (!$parsed || empty($parsed['path'])) {
            return false;
        }
        
        $path = $parsed['path'];
        $expires = time() + absint($expires_seconds);
        
        // Generate token following Bunny's format
        // token = md5(token_key + path + expires)
        $hash_data = $token_key . $path . $expires;
        $token = md5($hash_data);
        
        // Build signed URL
        $separator = strpos($base_url, '?') !== false ? '&' : '?';
        $signed_url = $base_url . $separator . 'token=' . $token . '&expires=' . $expires;
        
        return $signed_url;
    }
    
    /**
     * Validate Bunny CDN settings
     *
     * @return bool Valid configuration
     */
    public function validate_settings() {
        $settings = get_option('cpp_integration_settings', []);
        
        return !empty($settings['bunny_cdn_hostname']) && 
               !empty($settings['bunny_token_key']);
    }
    
    /**
     * Get Bunny CDN video info
     * Makes API call to Bunny to get video metadata
     *
     * @param string $video_id Bunny video ID
     * @return array|false Video info or false
     */
    public function get_video_info($video_id) {
        $settings = get_option('cpp_integration_settings', []);
        $api_key = $settings['bunny_api_key'] ?? '';
        
        if (empty($api_key) || empty($video_id)) {
            return false;
        }
        
        // Bunny API endpoint
        $api_url = 'https://api.bunny.net/videolibrary/' . sanitize_text_field($video_id);
        
        $response = wp_remote_get($api_url, [
            'headers' => [
                'AccessKey' => $api_key,
            ],
            'timeout' => 15,
        ]);
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        return $data ?: false;
    }
}