<?php
/**
 * Advanced Settings Management
 *
 * @package Content_Protect_Pro
 * @since   1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class CPP_Settings_Advanced {

    /**
     * Export plugin settings
     *
     * @param array $options Export options
     * @return string JSON settings export
     * @since 1.0.0
     */
    public function export_settings($options = []) {
        $defaults = [
            'include_sensitive' => false,
            'include_analytics' => false,
            'format_version' => '1.0'
        ];
        
        $options = array_merge($defaults, $options);
        
        // Get all plugin settings
        $settings = [
            'cpp_general_settings' => get_option('cpp_general_settings', []),
            'cpp_giftcode_settings' => get_option('cpp_giftcode_settings', []),
            'cpp_video_settings' => get_option('cpp_video_settings', []),
            'cpp_security_settings' => get_option('cpp_security_settings', []),
            'cpp_integration_settings' => get_option('cpp_integration_settings', [])
        ];
        
        // Remove sensitive data if not included
        if (!$options['include_sensitive']) {
            // Remove API keys and sensitive credentials
            if (isset($settings['cpp_integration_settings']['bunny_api_key'])) {
                $settings['cpp_integration_settings']['bunny_api_key'] = '[REDACTED]';
            }
            
            if (isset($settings['cpp_integration_settings']['drm_license_key'])) {
                $settings['cpp_integration_settings']['drm_license_key'] = '[REDACTED]';
            }
        }
        
        // Export structure
        $export = [
            'export_info' => [
                'plugin' => 'Content Protect Pro',
                'version' => CPP_VERSION,
                'exported_at' => current_time('mysql'),
                'wordpress_version' => get_bloginfo('version'),
                'php_version' => PHP_VERSION,
                'format_version' => $options['format_version']
            ],
            'settings' => $settings
        ];
        
        // Include analytics summary if requested
        if ($options['include_analytics'] && class_exists('CPP_Analytics')) {
            $analytics = new CPP_Analytics();
            $export['analytics_summary'] = $analytics->get_summary_stats();
        }
        
        return json_encode($export, JSON_PRETTY_PRINT);
    }

    /**
     * Import plugin settings
     *
     * @param string $json_data JSON settings data
     * @param array $options Import options
     * @return array Import result
     * @since 1.0.0
     */
    public function import_settings($json_data, $options = []) {
        $defaults = [
            'overwrite_existing' => false,
            'validate_compatibility' => true,
            'backup_current' => true
        ];
        
        $options = array_merge($defaults, $options);
        
        // Parse JSON data
        $import_data = json_decode($json_data, true);
        
        if (!$import_data) {
            return [
                'success' => false,
                'message' => __('Invalid JSON data provided.', 'content-protect-pro'),
                'imported_settings' => []
            ];
        }
        
        // Validate format
        if (!isset($import_data['export_info']) || !isset($import_data['settings'])) {
            return [
                'success' => false,
                'message' => __('Invalid settings export format.', 'content-protect-pro'),
                'imported_settings' => []
            ];
        }
        
        // Compatibility check
        if ($options['validate_compatibility']) {
            $compatibility_result = $this->check_import_compatibility($import_data['export_info']);
            if (!$compatibility_result['compatible']) {
                return [
                    'success' => false,
                    'message' => $compatibility_result['message'],
                    'imported_settings' => []
                ];
            }
        }
        
        // Backup current settings
        $backup_id = null;
        if ($options['backup_current']) {
            $backup_id = $this->create_settings_backup();
        }
        
        $imported_settings = [];
        $errors = [];
        
        // Import each setting group
        foreach ($import_data['settings'] as $option_name => $option_value) {
            // Skip if exists and not overwriting
            if (!$options['overwrite_existing'] && get_option($option_name) !== false) {
                continue;
            }
            
            // Validate setting values
            $validation_result = $this->validate_setting($option_name, $option_value);
            if (!$validation_result['valid']) {
                $errors[] = "Setting '{$option_name}': " . $validation_result['message'];
                continue;
            }
            
            // Import setting
            $updated = update_option($option_name, $option_value);
            if ($updated) {
                $imported_settings[] = $option_name;
            } else {
                $errors[] = "Failed to import setting: {$option_name}";
            }
        }
        
        // Log import activity
        if (class_exists('CPP_Analytics')) {
            $analytics = new CPP_Analytics();
            $analytics->log_event(
                'settings_imported',
                'system',
                'settings',
                [
                    'imported_count' => count($imported_settings),
                    'error_count' => count($errors),
                    'backup_id' => $backup_id,
                    'admin_user' => get_current_user_id()
                ]
            );
        }
        
        return [
            'success' => empty($errors) || !empty($imported_settings),
            'message' => sprintf(
                __('%d settings imported successfully.', 'content-protect-pro'),
                count($imported_settings)
            ),
            'imported_settings' => $imported_settings,
            'errors' => $errors,
            'backup_id' => $backup_id
        ];
    }

    /**
     * Create backup of current settings
     *
     * @return string Backup ID
     * @since 1.0.0
     */
    public function create_settings_backup() {
        $backup_id = 'cpp_backup_' . date('Y_m_d_H_i_s');
        
        $current_settings = [
            'cpp_general_settings' => get_option('cpp_general_settings', []),
            'cpp_giftcode_settings' => get_option('cpp_giftcode_settings', []),
            'cpp_video_settings' => get_option('cpp_video_settings', []),
            'cpp_security_settings' => get_option('cpp_security_settings', []),
            'cpp_integration_settings' => get_option('cpp_integration_settings', [])
        ];
        
        $backup_data = [
            'created_at' => current_time('mysql'),
            'version' => CPP_VERSION,
            'user_id' => get_current_user_id(),
            'settings' => $current_settings
        ];
        
        update_option($backup_id, $backup_data);
        
        // Keep only last 10 backups
        $this->cleanup_old_backups();
        
        return $backup_id;
    }

    /**
     * Restore settings from backup
     *
     * @param string $backup_id Backup ID
     * @return array Restore result
     * @since 1.0.0
     */
    public function restore_settings_backup($backup_id) {
        $backup_data = get_option($backup_id);
        
        if (!$backup_data || !isset($backup_data['settings'])) {
            return [
                'success' => false,
                'message' => __('Backup not found or invalid.', 'content-protect-pro')
            ];
        }
        
        $restored_settings = [];
        $errors = [];
        
        foreach ($backup_data['settings'] as $option_name => $option_value) {
            $updated = update_option($option_name, $option_value);
            if ($updated) {
                $restored_settings[] = $option_name;
            } else {
                $errors[] = "Failed to restore setting: {$option_name}";
            }
        }
        
        return [
            'success' => !empty($restored_settings),
            'message' => sprintf(
                __('%d settings restored from backup.', 'content-protect-pro'),
                count($restored_settings)
            ),
            'restored_settings' => $restored_settings,
            'errors' => $errors
        ];
    }

    /**
     * Get list of available backups
     *
     * @return array List of backups
     * @since 1.0.0
     */
    public function get_settings_backups() {
        global $wpdb;
        
        $backups = $wpdb->get_results(
            "SELECT option_name, option_value 
             FROM {$wpdb->options} 
             WHERE option_name LIKE 'cpp_backup_%' 
             ORDER BY option_name DESC",
            ARRAY_A
        );
        
        $backup_list = [];
        foreach ($backups as $backup) {
            $backup_data = maybe_unserialize($backup['option_value']);
            if (isset($backup_data['created_at'])) {
                $backup_list[] = [
                    'id' => $backup['option_name'],
                    'created_at' => $backup_data['created_at'],
                    'version' => $backup_data['version'] ?? 'Unknown',
                    'user_id' => $backup_data['user_id'] ?? 0,
                    'settings_count' => count($backup_data['settings'] ?? [])
                ];
            }
        }
        
        return $backup_list;
    }

    /**
     * Check import compatibility
     *
     * @param array $export_info Export information
     * @return array Compatibility result
     * @since 1.0.0
     */
    private function check_import_compatibility($export_info) {
        // Check plugin version compatibility
        if (isset($export_info['version'])) {
            $export_version = $export_info['version'];
            $current_version = CPP_VERSION;
            
            // Simple version comparison (you might want more sophisticated logic)
            if (version_compare($export_version, $current_version, '>')) {
                return [
                    'compatible' => false,
                    'message' => sprintf(
                        __('Settings were exported from a newer version (%s) than current (%s).', 'content-protect-pro'),
                        $export_version,
                        $current_version
                    )
                ];
            }
        }
        
        // Check format version
        if (isset($export_info['format_version'])) {
            $supported_formats = ['1.0'];
            if (!in_array($export_info['format_version'], $supported_formats)) {
                return [
                    'compatible' => false,
                    'message' => __('Unsupported export format version.', 'content-protect-pro')
                ];
            }
        }
        
        return [
            'compatible' => true,
            'message' => __('Export is compatible with current version.', 'content-protect-pro')
        ];
    }

    /**
     * Validate individual setting
     *
     * @param string $option_name Option name
     * @param mixed $option_value Option value
     * @return array Validation result
     * @since 1.0.0
     */
    private function validate_setting($option_name, $option_value) {
        // Basic validation rules
        switch ($option_name) {
            case 'cpp_integration_settings':
                if (isset($option_value['bunny_api_key']) && 
                    !empty($option_value['bunny_api_key']) && 
                    $option_value['bunny_api_key'] !== '[REDACTED]' &&
                    strlen($option_value['bunny_api_key']) < 10) {
                    return [
                        'valid' => false,
                        'message' => __('Invalid Bunny API key length.', 'content-protect-pro')
                    ];
                }
                break;
                
            case 'cpp_security_settings':
                if (isset($option_value['rate_limit_attempts']) && 
                    (!is_numeric($option_value['rate_limit_attempts']) || 
                     $option_value['rate_limit_attempts'] < 1 || 
                     $option_value['rate_limit_attempts'] > 1000)) {
                    return [
                        'valid' => false,
                        'message' => __('Rate limit attempts must be between 1 and 1000.', 'content-protect-pro')
                    ];
                }
                break;
        }
        
        return [
            'valid' => true,
            'message' => __('Setting is valid.', 'content-protect-pro')
        ];
    }

    /**
     * Clean up old backup files
     *
     * @since 1.0.0
     */
    private function cleanup_old_backups() {
        global $wpdb;
        
        // Keep only last 10 backups
        $backups = $wpdb->get_results(
            "SELECT option_name 
             FROM {$wpdb->options} 
             WHERE option_name LIKE 'cpp_backup_%' 
             ORDER BY option_name DESC 
             LIMIT 999 OFFSET 10"
        );
        
        foreach ($backups as $backup) {
            delete_option($backup->option_name);
        }
    }

    /**
     * Reset all plugin settings to defaults
     *
     * @param array $options Reset options
     * @return array Reset result
     * @since 1.0.0
     */
    public function reset_to_defaults($options = []) {
        $defaults = [
            'create_backup' => true,
            'reset_analytics' => false
        ];
        
        $options = array_merge($defaults, $options);
        
        // Create backup before reset
        $backup_id = null;
        if ($options['create_backup']) {
            $backup_id = $this->create_settings_backup();
        }
        
        // Default settings (you should define these based on your plugin's needs)
        $default_settings = [
            'cpp_general_settings' => [
                'enable_plugin' => 1,
                'debug_mode' => 0
            ],
            'cpp_giftcode_settings' => [
                'enable_giftcodes' => 1,
                'code_length' => 6,
                'case_sensitive' => 0,
                'allow_partial_use' => 0
            ],
            'cpp_video_settings' => [
                'enable_protection' => 1,
                'default_token_expiry' => 3600,
                'ip_restriction' => 0
            ],
            'cpp_security_settings' => [
                'enable_rate_limiting' => 1,
                'rate_limit_attempts' => 10,
                'rate_limit_window' => 60
            ],
            'cpp_integration_settings' => [
                'bunny_enabled' => 0,
                'presto_enabled' => 0
            ]
        ];
        
        $reset_settings = [];
        foreach ($default_settings as $option_name => $option_value) {
            update_option($option_name, $option_value);
            $reset_settings[] = $option_name;
        }
        
        // Reset analytics if requested
        if ($options['reset_analytics'] && class_exists('CPP_Analytics')) {
            global $wpdb;
            $analytics_table = $wpdb->prefix . 'cpp_analytics';
            $wpdb->query("TRUNCATE TABLE {$analytics_table}");
        }
        
        return [
            'success' => true,
            'message' => __('Settings reset to defaults successfully.', 'content-protect-pro'),
            'reset_settings' => $reset_settings,
            'backup_id' => $backup_id
        ];
    }
}