<?php
/**
 * Encryption Handler
 * 
 * Encrypts/decrypts sensitive data (API keys, tokens).
 * Uses WordPress salts for key derivation (following copilot-instructions.md)
 *
 * @package Content_Protect_Pro
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class CPP_Encryption {
    
    /**
     * Encryption method
     *
     * @var string
     */
    private const METHOD = 'aes-256-cbc';
    
    /**
     * Get encryption key from WordPress salts
     *
     * @return string
     */
    private static function get_key() {
        // Use WordPress AUTH_KEY + SECURE_AUTH_KEY for entropy
        $salt = AUTH_KEY . SECURE_AUTH_KEY;
        return hash('sha256', $salt, true);
    }
    
    /**
     * Encrypt data
     *
     * @param string $data Data to encrypt
     * @return string|false Encrypted string or false on failure
     */
    public static function encrypt($data) {
        if (empty($data)) {
            return false;
        }
        
        $key = self::get_key();
        $iv_length = openssl_cipher_iv_length(self::METHOD);
        $iv = openssl_random_pseudo_bytes($iv_length);
        
        $encrypted = openssl_encrypt($data, self::METHOD, $key, 0, $iv);
        
        if ($encrypted === false) {
            return false;
        }
        
        // Prepend IV to encrypted data for decryption
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * Decrypt data
     *
     * @param string $data Encrypted data
     * @return string|false Decrypted string or false on failure
     */
    public static function decrypt($data) {
        if (empty($data)) {
            return false;
        }
        
        $data = base64_decode($data);
        if ($data === false) {
            return false;
        }
        
        $key = self::get_key();
        $iv_length = openssl_cipher_iv_length(self::METHOD);
        
        // Extract IV from beginning of data
        $iv = substr($data, 0, $iv_length);
        $encrypted = substr($data, $iv_length);
        
        return openssl_decrypt($encrypted, self::METHOD, $key, 0, $iv);
    }
    
    /**
     * Generate secure random token
     *
     * @param int $length Token length in bytes (default 32)
     * @return string Hex-encoded token
     */
    public static function generate_token($length = 32) {
        return bin2hex(random_bytes($length));
    }
}