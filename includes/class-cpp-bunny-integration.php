<?php
/**
 * Bunny CDN and DRM integration functionality
 *
 * @package Content_Protect_Pro
 * @since   1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class CPP_Bunny_Integration {

    /**
     * Bunny API base URL
     */
    const API_BASE_URL = 'https://api.bunny.net';

    /**
     * Bunny Stream API base URL
     */
    const STREAM_API_URL = 'https://video.bunnycdn.com';

    /**
     * API key        // Use secure HTTP request with SSL validation
        $response = CPP_SSL_Validator::secure_http_request($url, array(
            'method' => 'GET',
            'headers' => array(
                'AccessKey' => $this->api_key,
                'Content-Type' => 'application/json',
            ),
            'timeout' => 30,
        ));nny CDN
     *
     * @var string
     */
    private $api_key;

    /**
     * Token authentication key for signed URLs
     *
     * @var string
     */
    private $token_auth_key;

    /**
     * Initialize the integration
     *
     * @since 1.0.0
     */
    public function __construct() {
        $integration_settings = get_option('cpp_integration_settings', array());

        // Decrypt API key if encrypted
        $api_key = isset($integration_settings['bunny_api_key']) ? $integration_settings['bunny_api_key'] : '';
        if (!empty($api_key) && class_exists('CPP_Encryption')) {
            $this->api_key = CPP_Encryption::decrypt($api_key);
        } else {
            $this->api_key = $api_key;
        }

        $this->library_id = isset($integration_settings['bunny_library_id']) ? $integration_settings['bunny_library_id'] : '';
        
        // Get token auth key from settings or decrypt if encrypted
        $token_auth_key = isset($integration_settings['bunny_token_auth_key']) ? $integration_settings['bunny_token_auth_key'] : '';
        if (!empty($token_auth_key) && class_exists('CPP_Encryption')) {
            $this->token_auth_key = CPP_Encryption::decrypt($token_auth_key);
        } else {
            $this->token_auth_key = $token_auth_key;
        }
    }

    /**
     * Check if Bunny integration is properly configured
     *
     * @return bool
     * @since 1.0.0
     */
    public function is_configured() {
        return !empty($this->api_key) && !empty($this->library_id);
    }

    /**
     * Generate a signed URL for protected video access
     *
     * @param string $video_id Video identifier
     * @param int    $expires  Token expiration timestamp
     * @param array  $options  Additional options
     * @return string|false Signed URL or false on failure
     * @since 1.0.0
     */
    public function generate_signed_url($video_id, $expires = null, $options = array()) {
        if (!$this->is_configured()) {
            return false;
        }

        if ($expires === null) {
            $video_settings = get_option('cpp_video_settings', array());
            $expiry_seconds = isset($video_settings['token_expiry']) ? intval($video_settings['token_expiry']) : 3600;
            $expires = time() + $expiry_seconds;
        }

        $defaults = array(
            'token_auth_key' => '',
            'ip_restriction' => false,
            'country_restriction' => array(),
            'referer_restriction' => '',
        );

        $options = wp_parse_args($options, $defaults);

        // Get video info from Bunny
        $video_info = $this->get_video_info($video_id);
        if (!$video_info) {
            return false;
        }

        // Build the base URL
        $base_url = "https://{$video_info['library']['hostname']}/{$video_info['guid']}/playlist.m3u8";

        // Build authentication parameters
        $auth_params = array(
            'expires' => $expires,
        );

        // Add IP restriction if specified
        if ($options['ip_restriction']) {
            $client_ip = $this->get_client_ip();
            $auth_params['ip'] = $client_ip;
        }

        // Add country restriction if specified
        if (!empty($options['country_restriction'])) {
            $auth_params['countries'] = implode(',', $options['country_restriction']);
        }

        // Add referer restriction if specified
        if (!empty($options['referer_restriction'])) {
            $auth_params['referer'] = $options['referer_restriction'];
        }

        // Generate authentication token
        // Use manually configured token key first, then fallback to API-provided key
        $token_auth_key = !empty($this->token_auth_key) ? $this->token_auth_key : 
                         (!empty($options['token_auth_key']) ? $options['token_auth_key'] : 
                         (isset($video_info['library']['tokenAuthenticationKey']) ? $video_info['library']['tokenAuthenticationKey'] : ''));
        
        if (empty($token_auth_key)) {
            error_log('CPP Bunny Integration: Token authentication key not found. Please configure it in Content Protect Pro settings if token authentication is enabled in Bunny Stream.');
            return false;
        }

        $auth_string = $this->build_auth_string($auth_params);
        $hash = hash('sha256', $token_auth_key . $auth_string);

        // Build final URL with authentication
        $signed_url = $base_url . '?' . $auth_string . '&token=' . $hash;

        return $signed_url;
    }

    /**
     * Get video information from Bunny Stream
     *
     * @param string $video_id Video identifier
     * @return array|false Video info or false on failure
     * @since 1.0.0
     */
    public function get_video_info($video_id) {
        if (!$this->is_configured()) {
            return false;
        }

        $url = self::STREAM_API_URL . "/library/{$this->library_id}/videos/{$video_id}";
        
        $response = wp_remote_get($url, array(
            'headers' => array(
                'AccessKey' => $this->api_key,
                'Accept' => 'application/json',
            ),
            'timeout' => 15,
        ));

        if (is_wp_error($response)) {
            error_log('CPP Bunny Integration: Error fetching video info - ' . $response->get_error_message());
            return false;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            error_log('CPP Bunny Integration: API error - Status code: ' . $status_code);
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('CPP Bunny Integration: Invalid JSON response');
            return false;
        }

        return $data;
    }

    // DRM (DASH/MPD) helpers intentionally omitted; using token-based HLS only for now.

    /**
     * Upload a video to Bunny Stream
     *
     * @param string $file_path Local file path
     * @param array  $metadata Video metadata
     * @return array|false Upload result or false on failure
     * @since 1.0.0
     */
    public function upload_video($file_path, $metadata = array()) {
        if (!$this->is_configured()) {
            return false;
        }

        if (!file_exists($file_path)) {
            error_log('CPP Bunny Integration: Video file not found - ' . $file_path);
            return false;
        }

        // First, create video entry
        $video_data = array(
            'title' => isset($metadata['title']) ? $metadata['title'] : basename($file_path),
        );

        if (isset($metadata['collection_id'])) {
            $video_data['collectionId'] = $metadata['collection_id'];
        }

        $create_url = self::STREAM_API_URL . "/library/{$this->library_id}/videos";
        
        $create_response = wp_remote_post($create_url, array(
            'headers' => array(
                'AccessKey' => $this->api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode($video_data),
            'timeout' => 30,
        ));

        if (is_wp_error($create_response)) {
            error_log('CPP Bunny Integration: Error creating video entry - ' . $create_response->get_error_message());
            return false;
        }

        $create_body = wp_remote_retrieve_body($create_response);
        $video_info = json_decode($create_body, true);

        if (!$video_info || !isset($video_info['guid'])) {
            error_log('CPP Bunny Integration: Invalid response when creating video');
            return false;
        }

        $video_id = $video_info['guid'];

        // Upload the actual video file
        $upload_url = self::STREAM_API_URL . "/library/{$this->library_id}/videos/{$video_id}";
        
        $file_handle = fopen($file_path, 'rb');
        if (!$file_handle) {
            error_log('CPP Bunny Integration: Cannot open video file - ' . $file_path);
            return false;
        }

        $upload_response = wp_remote_request($upload_url, array(
            'method' => 'PUT',
            'headers' => array(
                'AccessKey' => $this->api_key,
                'Content-Type' => 'application/octet-stream',
            ),
            'body' => fread($file_handle, filesize($file_path)),
            'timeout' => 300, // 5 minutes for large files
        ));

        fclose($file_handle);

        if (is_wp_error($upload_response)) {
            error_log('CPP Bunny Integration: Error uploading video - ' . $upload_response->get_error_message());
            return false;
        }

        return array(
            'video_id' => $video_id,
            'video_info' => $video_info,
            'upload_response' => $upload_response,
        );
    }

    /**
     * Delete a video from Bunny Stream
     *
     * @param string $video_id Video identifier
     * @return bool True on success, false on failure
     * @since 1.0.0
     */
    public function delete_video($video_id) {
        if (!$this->is_configured()) {
            return false;
        }

        $url = self::STREAM_API_URL . "/library/{$this->library_id}/videos/{$video_id}";
        
        $response = wp_remote_request($url, array(
            'method' => 'DELETE',
            'headers' => array(
                'AccessKey' => $this->api_key,
            ),
            'timeout' => 15,
        ));

        if (is_wp_error($response)) {
            error_log('CPP Bunny Integration: Error deleting video - ' . $response->get_error_message());
            return false;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        return $status_code === 200 || $status_code === 204;
    }

    /**
     * Get video statistics from Bunny
     *
     * @param string $video_id Video identifier
     * @param array  $params   Query parameters
     * @return array|false Statistics or false on failure
     * @since 1.0.0
     */
    public function get_video_statistics($video_id, $params = array()) {
        if (!$this->is_configured()) {
            return false;
        }

        $defaults = array(
            'dateFrom' => date('Y-m-d', strtotime('-30 days')),
            'dateTo' => date('Y-m-d'),
            'hourly' => false,
        );

        $params = wp_parse_args($params, $defaults);
        
        $query_string = http_build_query($params);
        $url = self::STREAM_API_URL . "/library/{$this->library_id}/videos/{$video_id}/statistics?" . $query_string;
        
        $response = wp_remote_get($url, array(
            'headers' => array(
                'AccessKey' => $this->api_key,
                'Accept' => 'application/json',
            ),
            'timeout' => 15,
        ));

        if (is_wp_error($response)) {
            error_log('CPP Bunny Integration: Error fetching statistics - ' . $response->get_error_message());
            return false;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        return json_decode($body, true);
    }

    /**
     * Build authentication string for signed URLs
     *
     * @param array $params Authentication parameters
     * @return string Authentication string
     * @since 1.0.0
     */
    private function build_auth_string($params) {
        $parts = array();
        
        foreach ($params as $key => $value) {
            if (!empty($value)) {
                $parts[] = $key . '=' . urlencode($value);
            }
        }
        
        return implode('&', $parts);
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
     * Test API connection
     *
     * @return array Test result
     * @since 1.0.0
     */
    public function test_connection() {
        if (!$this->is_configured()) {
            return array(
                'success' => false,
                'message' => 'API key and library ID are required.'
            );
        }

        $url = self::STREAM_API_URL . "/library/{$this->library_id}";
        
        $response = wp_remote_get($url, array(
            'headers' => array(
                'AccessKey' => $this->api_key,
                'Accept' => 'application/json',
            ),
            'timeout' => 10,
        ));

        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => 'Connection error: ' . $response->get_error_message()
            );
        }

        $status_code = wp_remote_retrieve_response_code($response);
        
        if ($status_code === 200) {
            return array(
                'success' => true,
                'message' => 'Connection successful!'
            );
        } else {
            return array(
                'success' => false,
                'message' => 'API error - Status code: ' . $status_code
            );
        }
    }
}