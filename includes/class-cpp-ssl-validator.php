<?php
/**
 * SSL/TLS validation for secure API communications
 *
 * @package Content_Protect_Pro
 * @since   1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * SSL/TLS Validator Class
 */
class CPP_SSL_Validator {
    
    /**
     * Validate SSL certificate for a given domain
     *
     * @param string $domain Domain to validate
     * @return array Validation result with certificate details
     * @since 1.0.0
     */
    public static function validate_ssl_certificate($domain) {
        // Remove protocol if present
        $domain = preg_replace('/^https?:\/\//', '', $domain);
        $domain = preg_replace('/\/.*$/', '', $domain);
        
        $context = stream_context_create([
            "ssl" => [
                "capture_peer_cert" => true,
                "verify_peer" => true,
                "verify_peer_name" => true,
                "allow_self_signed" => false,
            ],
        ]);
        
        $result = [
            'valid' => false,
            'domain' => $domain,
            'certificate' => null,
            'expires_at' => null,
            'days_until_expiry' => 0,
            'issuer' => '',
            'error' => ''
        ];
        
        try {
            // Attempt to connect and capture certificate
            $socket = @stream_socket_client(
                "ssl://{$domain}:443",
                $errno,
                $errstr,
                30,
                STREAM_CLIENT_CONNECT,
                $context
            );
            
            if (!$socket) {
                $result['error'] = "Connection failed: {$errstr} ({$errno})";
                return $result;
            }
            
            // Get certificate information
            $params = stream_context_get_params($socket);
            $certificate = $params['options']['ssl']['peer_certificate'];
            
            if (!$certificate) {
                $result['error'] = 'No certificate found';
                fclose($socket);
                return $result;
            }
            
            // Parse certificate data
            $cert_data = openssl_x509_parse($certificate);
            
            if (!$cert_data) {
                $result['error'] = 'Failed to parse certificate';
                fclose($socket);
                return $result;
            }
            
            // Check expiration
            $expires_timestamp = $cert_data['validTo_time_t'];
            $current_timestamp = time();
            $days_until_expiry = floor(($expires_timestamp - $current_timestamp) / (60 * 60 * 24));
            
            // Check if certificate is valid
            if ($expires_timestamp < $current_timestamp) {
                $result['error'] = 'Certificate has expired';
                fclose($socket);
                return $result;
            }
            
            if ($days_until_expiry < 7) {
                $result['error'] = 'Certificate expires soon (less than 7 days)';
            }
            
            $result['valid'] = true;
            $result['certificate'] = $cert_data;
            $result['expires_at'] = date('Y-m-d H:i:s', $expires_timestamp);
            $result['days_until_expiry'] = $days_until_expiry;
            $result['issuer'] = $cert_data['issuer']['CN'] ?? 'Unknown';
            
            fclose($socket);
            
        } catch (Exception $e) {
            $result['error'] = 'SSL validation error: ' . $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Validate Bunny CDN SSL certificates
     *
     * @return array Validation results for all Bunny domains
     * @since 1.0.0
     */
    public static function validate_bunny_ssl() {
        $domains_to_check = [
            'api.bunny.net',
            'video.bunnycdn.com',
            'bunnycdn.com'
        ];
        
        $results = [];
        
        foreach ($domains_to_check as $domain) {
            $results[$domain] = self::validate_ssl_certificate($domain);
        }
        
        return $results;
    }
    
    /**
     * Enhanced cURL options for secure API requests
     *
     * @return array cURL options for maximum security
     * @since 1.0.0
     */
    public static function get_secure_curl_options() {
        return [
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
            CURLOPT_SSL_CIPHER_LIST => 'ECDHE+AESGCM:ECDHE+CHACHA20:DHE+AESGCM:DHE+CHACHA20:!aNULL:!MD5:!DSS',
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_MAXREDIRS => 0,
            CURLOPT_USERAGENT => 'Content-Protect-Pro/1.0.0 (WordPress Plugin)',
        ];
    }
    
    /**
     * Secure HTTP request wrapper for WordPress
     *
     * @param string $url Request URL
     * @param array $args Request arguments
     * @return array|WP_Error Response or error
     * @since 1.0.0
     */
    public static function secure_http_request($url, $args = []) {
        $defaults = [
            'timeout' => 30,
            'redirection' => 0,
            'user-agent' => 'Content-Protect-Pro/1.0.0 (WordPress Plugin)',
            'sslverify' => true,
            'sslcertificates' => ABSPATH . WPINC . '/certificates/ca-bundle.crt',
        ];
        
        $args = array_merge($defaults, $args);
        
        // Add SSL validation log
        $ssl_validation = self::validate_ssl_certificate(parse_url($url, PHP_URL_HOST));
        
        if (!$ssl_validation['valid']) {
            return new WP_Error(
                'ssl_validation_failed',
                'SSL validation failed: ' . $ssl_validation['error']
            );
        }
        
        return wp_remote_request($url, $args);
    }
    
    /**
     * Check certificate transparency logs for domain
     *
     * @param string $domain Domain to check
     * @return array Certificate transparency information
     * @since 1.0.0
     */
    public static function check_certificate_transparency($domain) {
        $ct_logs = [
            'https://crt.sh/?q=' . urlencode($domain) . '&output=json',
        ];
        
        $results = [];
        
        foreach ($ct_logs as $log_url) {
            $response = self::secure_http_request($log_url);
            
            if (is_wp_error($response)) {
                continue;
            }
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if ($data && is_array($data)) {
                $results[] = [
                    'source' => $log_url,
                    'certificates_found' => count($data),
                    'latest_entry' => $data[0] ?? null
                ];
            }
        }
        
        return $results;
    }
}