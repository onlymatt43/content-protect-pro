<?php
/**
 * Token and code generation helper functions
 *
 * @package Content_Protect_Pro
 * @since   1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Generate a simple 6-character code from a secure token
 *
 * @param string $token Secure token (64 characters)
 * @return string Simple 6-character code
 */
function cpp_generate_simple_code_from_token($token) {
    // Use first 8 characters of token for hash
    $hash = substr($token, 0, 8);
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // Excludes similar looking chars
    $code = '';
    
    // Convert hex characters to readable code
    for ($i = 0; $i < 6; $i++) {
        $hex_char = isset($hash[$i]) ? $hash[$i] : '0';
        $decimal = hexdec($hex_char);
        $code .= $chars[$decimal % strlen($chars)];
    }
    
    return $code;
}

/**
 * Convert duration value and unit to minutes
 *
 * @param int $value Duration value
 * @param string $unit Duration unit (minutes, hours, days, months, years)
 * @return int Duration in minutes
 */
function cpp_convert_to_minutes($value, $unit) {
    $minutes = intval($value);
    
    switch ($unit) {
        case 'minutes':
            return $minutes;
        case 'hours':
            return $minutes * 60;
        case 'days':
            return $minutes * 60 * 24;
        case 'months':
            return $minutes * 60 * 24 * 30; // Approximate
        case 'years':
            return $minutes * 60 * 24 * 365; // Approximate
        default:
            return $minutes;
    }
}

/**
 * Generate a secure session token
 *
 * @return string 64-character secure token
 */
function cpp_generate_secure_token() {
    return bin2hex(random_bytes(32));
}

/**
 * Validate client IP against restrictions
 *
 * @param string $client_ip Client IP address
 * @param string $restrictions IP restrictions (one per line, supports CIDR)
 * @return bool True if IP is allowed
 */
function cpp_validate_client_ip($client_ip, $restrictions) {
    if (empty($restrictions)) {
        return true; // No restrictions = allow all
    }
    
    $allowed_ips = array_filter(array_map('trim', explode("\n", $restrictions)));
    
    foreach ($allowed_ips as $allowed_ip) {
        if (cpp_ip_in_range($client_ip, $allowed_ip)) {
            return true;
        }
    }
    
    return false;
}

/**
 * Check if IP is in range (supports CIDR notation)
 *
 * @param string $ip IP address to check
 * @param string $range IP range (can be single IP or CIDR notation)
 * @return bool True if IP is in range
 */
function cpp_ip_in_range($ip, $range) {
    // Handle single IP
    if (strpos($range, '/') === false) {
        return $ip === $range;
    }
    
    // Handle CIDR notation
    list($subnet, $bits) = explode('/', $range);
    
    // IPv4
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && 
        filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        
        $ip_long = ip2long($ip);
        $subnet_long = ip2long($subnet);
        $mask = -1 << (32 - $bits);
        
        return ($ip_long & $mask) == ($subnet_long & $mask);
    }
    
    // IPv6 (basic support)
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) && 
        filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        
        // Convert to binary for comparison
        $ip_bin = inet_pton($ip);
        $subnet_bin = inet_pton($subnet);
        
        if ($ip_bin === false || $subnet_bin === false) {
            return false;
        }
        
        $bytes = intval($bits / 8);
        $bits_remainder = $bits % 8;
        
        // Check full bytes
        if (substr($ip_bin, 0, $bytes) !== substr($subnet_bin, 0, $bytes)) {
            return false;
        }
        
        // Check remaining bits
        if ($bits_remainder > 0 && $bytes < strlen($ip_bin)) {
            $mask = 0xFF << (8 - $bits_remainder);
            $ip_byte = ord($ip_bin[$bytes]);
            $subnet_byte = ord($subnet_bin[$bytes]);
            
            return ($ip_byte & $mask) === ($subnet_byte & $mask);
        }
        
        return true;
    }
    
    return false;
}

/**
 * Get client real IP address
 *
 * @return string Client IP address
 */
function cpp_get_client_ip() {
    $ip_keys = array(
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_REAL_IP',
        'HTTP_CLIENT_IP',
        'HTTP_X_CLUSTER_CLIENT_IP',
        'REMOTE_ADDR'
    );
    
    foreach ($ip_keys as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            foreach (explode(',', $_SERVER[$key]) as $ip) {
                $ip = trim($ip);
                
                // Validate IP and exclude private ranges for proxy detection
                if (filter_var($ip, FILTER_VALIDATE_IP, 
                    FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                    return $ip;
                }
            }
        }
    }
    
    // Fallback to REMOTE_ADDR even if it's private
    return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
}

/**
 * Create a secure session for a validated code
 *
 * @param string $code Gift code
 * @param int $duration_minutes Session duration in minutes
 * @param string $secure_token Associated secure token
 * @return array Session data
 */
function cpp_create_session($code, $duration_minutes, $secure_token) {
    $client_ip = cpp_get_client_ip();
    $session_id = uniqid('cpp_', true);
    $expires_at = time() + ($duration_minutes * 60);
    
    // Store session in database
    global $wpdb;
    $table_name = $wpdb->prefix . 'cpp_sessions';
    
    $session_data = array(
        'session_id' => $session_id,
        'code' => $code,
        'secure_token' => $secure_token,
        'client_ip' => $client_ip,
        'expires_at' => date('Y-m-d H:i:s', $expires_at),
        'created_at' => date('Y-m-d H:i:s'),
        'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '',
        'status' => 'active'
    );
    
    $result = $wpdb->insert($table_name, $session_data);
    
    if ($result) {
        // Set secure cookie
        $cookie_name = 'cpp_session_' . substr(md5($code), 0, 8);
        $cookie_value = base64_encode(json_encode(array(
            'session_id' => $session_id,
            'token' => substr($secure_token, 0, 16), // Partial token for verification
            'expires' => $expires_at
        )));
        
        // Sécurisation complète du cookie
        $cookie_options = array(
            'expires' => $expires_at,
            'path' => '/',
            'domain' => '',
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
            'httponly' => true,
            'samesite' => 'Strict'
        );
        
        if (PHP_VERSION_ID >= 70300) {
            // PHP 7.3+ support du paramètre samesite
            setcookie($cookie_name, $cookie_value, $cookie_options);
        } else {
            // Fallback pour versions antérieures
            setcookie($cookie_name, $cookie_value, $expires_at, '/; SameSite=Strict', '', $cookie_options['secure'], true);
        }
        
        return array(
            'success' => true,
            'session_id' => $session_id,
            'expires_at' => $expires_at,
            'cookie_name' => $cookie_name
        );
    }
    
    return array('success' => false, 'message' => 'Failed to create session');
}

/**
 * Validate an existing session
 *
 * @param string $code Gift code
 * @return array Validation result
 */
function cpp_validate_session($code) {
    $client_ip = cpp_get_client_ip();
    $cookie_name = 'cpp_session_' . substr(md5($code), 0, 8);
    
    if (!isset($_COOKIE[$cookie_name])) {
        return array('valid' => false, 'message' => 'No session found');
    }
    
    $cookie_data = json_decode(base64_decode($_COOKIE[$cookie_name]), true);
    
    if (!$cookie_data || !isset($cookie_data['session_id'])) {
        return array('valid' => false, 'message' => 'Invalid session data');
    }
    
    // Verify session in database
    global $wpdb;
    $table_name = $wpdb->prefix . 'cpp_sessions';
    
    $session = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table_name} 
         WHERE session_id = %s 
         AND code = %s 
         AND client_ip = %s 
         AND status = 'active' 
         AND expires_at > NOW()",
        $cookie_data['session_id'],
        $code,
        $client_ip
    ));
    
    if (!$session) {
        return array('valid' => false, 'message' => 'Session expired or invalid');
    }
    
    // Verify partial token
    $partial_token = substr($session->secure_token, 0, 16);
    if ($cookie_data['token'] !== $partial_token) {
        return array('valid' => false, 'message' => 'Token mismatch');
    }
    
    return array(
        'valid' => true,
        'session_id' => $session->session_id,
        'expires_at' => strtotime($session->expires_at),
        'remaining_minutes' => max(0, ceil((strtotime($session->expires_at) - time()) / 60))
    );
}