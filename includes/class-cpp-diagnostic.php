<?php
/**
 * Diagnostic and troubleshooting functionality
 *
 * @package Content_Protect_Pro
 * @since   1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class CPP_Diagnostic {

    /**
     * Run system diagnostic tests
     *
     * @since 1.0.0
     * @return array Diagnostic results
     */
    public function run_diagnostics() {
        $results = array();
        
        // WordPress environment checks
        $results['wordpress'] = $this->check_wordpress_environment();
        
        // PHP environment checks
        $results['php'] = $this->check_php_environment();
        
        // Database checks
        $results['database'] = $this->check_database();
        
        // Plugin functionality checks
        $results['plugin'] = $this->check_plugin_functionality();
        
        // Integration checks
        $results['integrations'] = $this->check_integrations();
        
        // Performance checks
        $results['performance'] = $this->check_performance();
        
        return $results;
    }

    /**
     * Check WordPress environment
     *
     * @since 1.0.0
     * @return array WordPress checks
     */
    private function check_wordpress_environment() {
        $checks = array();
        
        // WordPress version
        $wp_version = get_bloginfo('version');
        $checks['wp_version'] = array(
            'label' => 'WordPress Version',
            'value' => $wp_version,
            'status' => version_compare($wp_version, '5.0', '>=') ? 'pass' : 'warning',
            'message' => version_compare($wp_version, '5.0', '>=') ? 
                'WordPress version is compatible' : 
                'WordPress 5.0+ recommended for best compatibility'
        );
        
        // Plugin compatibility
        $checks['plugin_compatibility'] = array(
            'label' => 'Plugin Compatibility',
            'value' => defined('CONTENT_PROTECT_PRO_VERSION') ? CONTENT_PROTECT_PRO_VERSION : 'Unknown',
            'status' => defined('CONTENT_PROTECT_PRO_VERSION') ? 'pass' : 'fail',
            'message' => defined('CONTENT_PROTECT_PRO_VERSION') ? 
                'Plugin constants loaded correctly' : 
                'Plugin constants not defined'
        );
        
        // WordPress debug mode
        $checks['debug_mode'] = array(
            'label' => 'Debug Mode',
            'value' => WP_DEBUG ? 'Enabled' : 'Disabled',
            'status' => WP_DEBUG ? 'warning' : 'pass',
            'message' => WP_DEBUG ? 
                'Debug mode enabled - disable for production' : 
                'Debug mode properly disabled'
        );
        
        // SSL/HTTPS
        $checks['ssl'] = array(
            'label' => 'SSL/HTTPS',
            'value' => is_ssl() ? 'Enabled' : 'Disabled',
            'status' => is_ssl() ? 'pass' : 'warning',
            'message' => is_ssl() ? 
                'Site properly secured with SSL' : 
                'SSL recommended for security'
        );
        
        return $checks;
    }

    /**
     * Check PHP environment
     *
     * @since 1.0.0
     * @return array PHP checks
     */
    private function check_php_environment() {
        $checks = array();
        
        // PHP version
        $php_version = phpversion();
        $checks['php_version'] = array(
            'label' => 'PHP Version',
            'value' => $php_version,
            'status' => version_compare($php_version, '7.4', '>=') ? 'pass' : 'fail',
            'message' => version_compare($php_version, '7.4', '>=') ? 
                'PHP version is compatible' : 
                'PHP 7.4+ required'
        );
        
        // Required extensions
        $required_extensions = array('json', 'curl', 'openssl', 'hash');
        $missing_extensions = array();
        
        foreach ($required_extensions as $extension) {
            if (!extension_loaded($extension)) {
                $missing_extensions[] = $extension;
            }
        }
        
        $checks['php_extensions'] = array(
            'label' => 'Required PHP Extensions',
            'value' => empty($missing_extensions) ? 'All present' : 'Missing: ' . implode(', ', $missing_extensions),
            'status' => empty($missing_extensions) ? 'pass' : 'fail',
            'message' => empty($missing_extensions) ? 
                'All required extensions loaded' : 
                'Missing required PHP extensions: ' . implode(', ', $missing_extensions)
        );
        
        // Memory limit
        $memory_limit = ini_get('memory_limit');
        $memory_bytes = $this->convert_to_bytes($memory_limit);
        $checks['memory_limit'] = array(
            'label' => 'PHP Memory Limit',
            'value' => $memory_limit,
            'status' => $memory_bytes >= 134217728 ? 'pass' : 'warning', // 128MB
            'message' => $memory_bytes >= 134217728 ? 
                'Sufficient memory allocated' : 
                'Consider increasing memory limit to 128MB+'
        );
        
        // Max execution time
        $max_execution_time = ini_get('max_execution_time');
        $checks['execution_time'] = array(
            'label' => 'Max Execution Time',
            'value' => $max_execution_time . 's',
            'status' => $max_execution_time >= 30 ? 'pass' : 'warning',
            'message' => $max_execution_time >= 30 ? 
                'Sufficient execution time' : 
                'Consider increasing execution time limit'
        );
        
        return $checks;
    }

    /**
     * Check database connectivity and tables
     *
     * @since 1.0.0
     * @return array Database checks
     */
    private function check_database() {
        global $wpdb;
        $checks = array();
        
        // Database connectivity
        $db_connection = $wpdb->get_var("SELECT 1");
        $checks['connectivity'] = array(
            'label' => 'Database Connectivity',
            'value' => $db_connection ? 'Connected' : 'Failed',
            'status' => $db_connection ? 'pass' : 'fail',
            'message' => $db_connection ? 
                'Database connection working' : 
                'Cannot connect to database'
        );
        
        // Plugin tables
        $plugin_tables = array(
            'cpp_giftcodes' => $wpdb->prefix . 'cpp_giftcodes',
            'cpp_protected_videos' => $wpdb->prefix . 'cpp_protected_videos',
            'cpp_analytics' => $wpdb->prefix . 'cpp_analytics'
        );
        
        $missing_tables = array();
        $table_counts = array();
        
        foreach ($plugin_tables as $key => $table_name) {
            $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name));
            if (!$exists) {
                $missing_tables[] = $key;
            } else {
                $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
                $table_counts[$key] = $count;
            }
        }
        
        $checks['plugin_tables'] = array(
            'label' => 'Plugin Database Tables',
            'value' => empty($missing_tables) ? 'All present' : 'Missing: ' . implode(', ', $missing_tables),
            'status' => empty($missing_tables) ? 'pass' : 'fail',
            'message' => empty($missing_tables) ? 
                'All plugin tables exist' : 
                'Missing plugin tables - try reactivating plugin'
        );
        
        // Table data
        if (empty($missing_tables)) {
            $checks['table_data'] = array(
                'label' => 'Table Data',
                'value' => json_encode($table_counts),
                'status' => 'info',
                'message' => 'Data counts: ' . 
                    'Gift codes: ' . ($table_counts['cpp_giftcodes'] ?? 0) . ', ' .
                    'Protected videos: ' . ($table_counts['cpp_protected_videos'] ?? 0) . ', ' .
                    'Analytics events: ' . ($table_counts['cpp_analytics'] ?? 0)
            );
        }
        
        return $checks;
    }

    /**
     * Check plugin functionality
     *
     * @since 1.0.0
     * @return array Plugin functionality checks
     */
    private function check_plugin_functionality() {
        $checks = array();
        
        // Plugin classes
        $required_classes = array(
            'Content_Protect_Pro',
            'CPP_Giftcode_Manager',
            'CPP_Video_Manager',
            'CPP_Analytics',
            'CPP_Admin',
            'CPP_Public'
        );
        
        $missing_classes = array();
        foreach ($required_classes as $class) {
            if (!class_exists($class)) {
                $missing_classes[] = $class;
            }
        }
        
        $checks['plugin_classes'] = array(
            'label' => 'Plugin Classes',
            'value' => empty($missing_classes) ? 'All loaded' : 'Missing: ' . implode(', ', $missing_classes),
            'status' => empty($missing_classes) ? 'pass' : 'fail',
            'message' => empty($missing_classes) ? 
                'All plugin classes loaded successfully' : 
                'Some plugin classes failed to load'
        );
        
        // Options
        $plugin_options = array(
            'cpp_general_settings',
            'cpp_security_settings',
            'cpp_integration_settings'
        );
        
        $missing_options = array();
        foreach ($plugin_options as $option) {
            if (get_option($option) === false) {
                $missing_options[] = $option;
            }
        }
        
        $checks['plugin_options'] = array(
            'label' => 'Plugin Settings',
            'value' => empty($missing_options) ? 'All present' : 'Missing: ' . implode(', ', $missing_options),
            'status' => empty($missing_options) ? 'pass' : 'warning',
            'message' => empty($missing_options) ? 
                'All plugin settings initialized' : 
                'Some plugin settings not configured'
        );
        
        // AJAX endpoints
        $ajax_endpoints = array(
            'cpp_validate_giftcode',
            'cpp_validate_video_access'
        );
        
        $checks['ajax_endpoints'] = array(
            'label' => 'AJAX Endpoints',
            'value' => count($ajax_endpoints) . ' configured',
            'status' => 'info',
            'message' => 'AJAX endpoints: ' . implode(', ', $ajax_endpoints)
        );
        
        return $checks;
    }

    /**
     * Check integration status
     *
     * @since 1.0.0
     * @return array Integration checks
     */
    private function check_integrations() {
        $checks = array();
        $settings = get_option('cpp_integration_settings', array());
        
        // Bunny CDN integration
        $bunny_enabled = !empty($settings['bunny_enabled']);
        $bunny_configured = !empty($settings['bunny_api_key']) && !empty($settings['bunny_library_id']);
        
        $checks['bunny_cdn'] = array(
            'label' => 'Bunny CDN Integration',
            'value' => $bunny_enabled ? ($bunny_configured ? 'Enabled & Configured' : 'Enabled but not configured') : 'Disabled',
            'status' => $bunny_enabled ? ($bunny_configured ? 'pass' : 'warning') : 'info',
            'message' => $bunny_enabled ? 
                ($bunny_configured ? 'Bunny CDN integration ready' : 'Bunny CDN enabled but missing API credentials') :
                'Bunny CDN integration disabled'
        );
        
        // Test Bunny API if configured
        if ($bunny_enabled && $bunny_configured && class_exists('CPP_Bunny_Integration')) {
            $bunny = new CPP_Bunny_Integration();
            $api_test = $bunny->test_connection();
            
            $checks['bunny_api'] = array(
                'label' => 'Bunny API Connection',
                'value' => $api_test['success'] ? 'Connected' : 'Failed',
                'status' => $api_test['success'] ? 'pass' : 'fail',
                'message' => $api_test['message']
            );
        }
        
        // Presto Player integration
        $presto_enabled = !empty($settings['presto_enabled']);
        $presto_plugin_active = is_plugin_active('presto-player/presto-player.php');
        
        $checks['presto_player'] = array(
            'label' => 'Presto Player Integration',
            'value' => $presto_enabled ? ($presto_plugin_active ? 'Enabled & Plugin Active' : 'Enabled but plugin inactive') : 'Disabled',
            'status' => $presto_enabled ? ($presto_plugin_active ? 'pass' : 'warning') : 'info',
            'message' => $presto_enabled ?
                ($presto_plugin_active ? 'Presto Player integration ready' : 'Presto Player integration enabled but plugin not active') :
                'Presto Player integration disabled'
        );
        
        return $checks;
    }

    /**
     * Check performance metrics
     *
     * @since 1.0.0
     * @return array Performance checks
     */
    private function check_performance() {
        $checks = array();
        
        // Plugin impact on page load
        $start_time = microtime(true);
        
        // Simulate some plugin operations
        if (class_exists('CPP_Giftcode_Manager')) {
            $giftcode_manager = new CPP_Giftcode_Manager();
        }
        
        if (class_exists('CPP_Analytics')) {
            $analytics = new CPP_Analytics();
        }
        
        $execution_time = (microtime(true) - $start_time) * 1000; // Convert to milliseconds
        
        $checks['initialization_time'] = array(
            'label' => 'Plugin Initialization Time',
            'value' => round($execution_time, 2) . 'ms',
            'status' => $execution_time < 50 ? 'pass' : ($execution_time < 100 ? 'warning' : 'fail'),
            'message' => $execution_time < 50 ? 
                'Plugin loads quickly' : 
                ($execution_time < 100 ? 'Plugin load time acceptable' : 'Plugin may be impacting performance')
        );
        
        // Database query performance (sample)
        if (class_exists('CPP_Analytics')) {
            $start_query = microtime(true);
            $analytics = new CPP_Analytics();
            $analytics->get_analytics(array('limit' => 10));
            $query_time = (microtime(true) - $start_query) * 1000;
            
            $checks['database_performance'] = array(
                'label' => 'Database Query Performance',
                'value' => round($query_time, 2) . 'ms',
                'status' => $query_time < 100 ? 'pass' : ($query_time < 500 ? 'warning' : 'fail'),
                'message' => $query_time < 100 ? 
                    'Database queries performing well' : 
                    ($query_time < 500 ? 'Database performance acceptable' : 'Database queries may be slow')
            );
        }
        
        return $checks;
    }

    /**
     * Get system information
     *
     * @since 1.0.0
     * @return array System information
     */
    public function get_system_info() {
        global $wpdb;
        
        $info = array(
            'wordpress' => array(
                'version' => get_bloginfo('version'),
                'url' => home_url(),
                'admin_email' => get_option('admin_email'),
                'timezone' => get_option('timezone_string'),
                'language' => get_locale(),
                'multisite' => is_multisite() ? 'Yes' : 'No',
            ),
            'server' => array(
                'php_version' => phpversion(),
                'mysql_version' => $wpdb->db_version(),
                'server_software' => isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : 'Unknown',
                'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'Unknown',
            ),
            'plugin' => array(
                'version' => defined('CONTENT_PROTECT_PRO_VERSION') ? CONTENT_PROTECT_PRO_VERSION : 'Unknown',
                'path' => defined('CONTENT_PROTECT_PRO_PATH') ? CONTENT_PROTECT_PRO_PATH : 'Unknown',
                'url' => defined('CONTENT_PROTECT_PRO_URL') ? CONTENT_PROTECT_PRO_URL : 'Unknown',
            )
        );
        
        return $info;
    }

    /**
     * Convert memory limit string to bytes
     *
     * @param string $limit Memory limit string
     * @return int Memory limit in bytes
     * @since 1.0.0
     */
    private function convert_to_bytes($limit) {
        $limit = trim($limit);
        $last = strtolower($limit[strlen($limit) - 1]);
        $number = substr($limit, 0, -1);
        
        switch ($last) {
            case 'g':
                $number *= 1024;
            case 'm':
                $number *= 1024;
            case 'k':
                $number *= 1024;
        }
        
        return $number;
    }

    /**
     * Export diagnostic data
     *
     * @since 1.0.0
     * @return string JSON encoded diagnostic data
     */
    public function export_diagnostics() {
        $data = array(
            'diagnostics' => $this->run_diagnostics(),
            'system_info' => $this->get_system_info(),
            'generated_at' => current_time('mysql'),
            'generated_by' => wp_get_current_user()->user_login ?? 'unknown',
        );
        
        return json_encode($data, JSON_PRETTY_PRINT);
    }
}