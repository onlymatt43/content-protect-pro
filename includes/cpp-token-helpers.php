<?php
/**
 * Token Generation Helpers
 * 
 * Utility functions for creating secure tokens and cookies.
 * Following token-based auth pattern from copilot-instructions.md
 *
 * @package Content_Protect_Pro
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Generate secure session token
 *
 * @return string 64-character hex token
 */
function cpp_generate_session_token() {
    return CPP_Encryption::generate_token(32);
}

/**
 * Get client IP address
 * Wrapper for CPP_Giftcode_Security::get_client_ip()
 *
 * @return string Client IP
 */
function cpp_get_client_ip() {
    if (!class_exists('CPP_Giftcode_Security')) {
        require_once CPP_PLUGIN_DIR . 'includes/class-cpp-giftcode-security.php';
    }
    
    $security = new CPP_Giftcode_Security();
    return $security->get_client_ip();
}

/**
 * Set HttpOnly secure cookie
 * Following CSRF protection pattern from copilot-instructions.md
 *
 * @param string $name Cookie name
 * @param string $value Cookie value
 * @param int $expires Unix timestamp
 * @return bool Success
 */
function cpp_set_secure_cookie($name, $value, $expires) {
    $secure = is_ssl();
    $httponly = true;
    $samesite = 'Strict';
    
    // Use PHP 7.3+ setcookie with options array
    if (PHP_VERSION_ID >= 70300) {
        return setcookie($name, $value, [
            'expires' => $expires,
            'path' => COOKIEPATH ?: '/',
            'domain' => COOKIE_DOMAIN ?: '',
            'secure' => $secure,
            'httponly' => $httponly,
            'samesite' => $samesite,
        ]);
    }
    
    // Fallback for PHP < 7.3
    return setcookie(
        $name,
        $value,
        $expires,
        COOKIEPATH ?: '/',
        COOKIE_DOMAIN ?: '',
        $secure,
        $httponly
    );
}

/**
 * Validate session token (timing-safe comparison)
 * Following security pattern from copilot-instructions.md
 *
 * @param string $provided_token Token from cookie
 * @param string $expected_token Token from database
 * @return bool Valid
 */
function cpp_validate_session_token($provided_token, $expected_token) {
    if (empty($provided_token) || empty($expected_token)) {
        return false;
    }
    
    // Timing-safe comparison (prevents timing attacks)
    return hash_equals($expected_token, $provided_token);
}

/**
 * Generate gift code (URL-safe, human-readable)
 *
 * @param int $length Code length (default 8)
 * @return string Gift code
 */
function cpp_generate_gift_code($length = 8) {
    // Exclude ambiguous characters (0, O, 1, I, l)
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $code = '';
    
    for ($i = 0; $i < $length; $i++) {
        $code .= $chars[random_int(0, strlen($chars) - 1)];
    }
    
    return $code;
}