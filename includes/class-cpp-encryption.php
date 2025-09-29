<?php
/**
 * Encryption utilities for secure token storage
 *
 * @package Content_Protect_Pro
 * @since   1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class CPP_Encryption {

    /**
     * Encryption method
     */
    const METHOD = 'AES-256-CBC';

    /**
     * Get encryption key
     *
     * @return string Encryption key
     */
    private static function get_key() {
        $key = get_option('cpp_encryption_key');
        
        if (!$key) {
            // Generate new encryption key
            $key = base64_encode(random_bytes(32)); // 256-bit key
            update_option('cpp_encryption_key', $key);
        }
        
        return base64_decode($key);
    }

    /**
     * Encrypt sensitive data
     *
     * @param string $data Data to encrypt
     * @return string Encrypted data
     */
    public static function encrypt($data) {
        if (empty($data)) {
            return $data;
        }

        $key = self::get_key();
        $iv = random_bytes(16); // 128-bit IV for AES-256-CBC
        
        $encrypted = openssl_encrypt($data, self::METHOD, $key, 0, $iv);
        
        if ($encrypted === false) {
            error_log('CPP Encryption: Failed to encrypt data');
            return $data; // Return original on failure
        }

        // Combine IV and encrypted data
        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypt sensitive data
     *
     * @param string $encrypted_data Encrypted data
     * @return string Decrypted data
     */
    public static function decrypt($encrypted_data) {
        if (empty($encrypted_data)) {
            return $encrypted_data;
        }

        $key = self::get_key();
        $data = base64_decode($encrypted_data);
        
        if ($data === false || strlen($data) < 16) {
            return $encrypted_data; // Return as-is if not properly encrypted
        }

        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);

        $decrypted = openssl_decrypt($encrypted, self::METHOD, $key, 0, $iv);
        
        if ($decrypted === false) {
            error_log('CPP Encryption: Failed to decrypt data');
            return $encrypted_data; // Return original on failure
        }

        return $decrypted;
    }

    /**
     * Hash data securely
     *
     * @param string $data Data to hash
     * @return string Hashed data
     */
    public static function hash($data) {
        return hash('sha256', $data . self::get_salt());
    }

    /**
     * Verify hashed data
     *
     * @param string $data Original data
     * @param string $hash Hash to verify
     * @return bool True if valid
     */
    public static function verify_hash($data, $hash) {
        return hash_equals(self::hash($data), $hash);
    }

    /**
     * Get salt for hashing
     *
     * @return string Salt
     */
    private static function get_salt() {
        $salt = get_option('cpp_hash_salt');
        
        if (!$salt) {
            $salt = base64_encode(random_bytes(32));
            update_option('cpp_hash_salt', $salt);
        }
        
        return $salt;
    }

    /**
     * Secure token comparison
     *
     * @param string $token1 First token
     * @param string $token2 Second token
     * @return bool True if equal (timing-safe)
     */
    public static function compare_tokens($token1, $token2) {
        return hash_equals($token1, $token2);
    }

    /**
     * Generate secure random token
     *
     * @param int $length Token length in bytes
     * @return string Hex token
     */
    public static function generate_token($length = 32) {
        return bin2hex(random_bytes($length));
    }
}