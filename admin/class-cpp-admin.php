<?php
/**
 * Admin Area Handler
 * 
 * Registers admin menu, pages, and enqueues admin assets.
 * Following copilot-instructions hook registration pattern.
 *
 * @package Content_Protect_Pro
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class CPP_Admin {
    
    /**
     * Plugin name
     *
     * @var string
     */
    private $plugin_name;
    
    /**
     * Plugin version
     *
     * @var string
     */
    private $version;
    
    /**
     * Initialize admin class
     *
     * @param string $plugin_name Plugin name
     * @param string $version Plugin version
     */
    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }
    
    /**
     * Register admin menu
     */
    public function add_plugin_admin_menu() {
        // Main menu
        add_menu_page(
            __('Content Protect Pro', 'content-protect-pro'),
            __('Content Protect Pro', 'content-protect-pro'),
            'manage_options',
            $this->plugin_name,
            [$this, 'display_plugin_dashboard'],
            'dashicons-shield',
            30
        );
        
        // Dashboard submenu
        add_submenu_page(
            $this->plugin_name,
            __('Dashboard', 'content-protect-pro'),
            __('Dashboard', 'content-protect-pro'),
            'manage_options',
            $this->plugin_name,
            [$this, 'display_plugin_dashboard']
        );
        
        // Gift Codes submenu
        add_submenu_page(
            $this->plugin_name,
            __('Gift Codes', 'content-protect-pro'),
            __('Gift Codes', 'content-protect-pro'),
            'manage_options',
            $this->plugin_name . '-giftcodes',
            [$this, 'display_giftcodes_page']
        );
        
        // Protected Videos submenu
        add_submenu_page(
            $this->plugin_name,
            __('Protected Videos', 'content-protect-pro'),
            __('Protected Videos', 'content-protect-pro'),
            'manage_options',
            $this->plugin_name . '-videos',
            [$this, 'display_videos_page']
        );
        
        // Analytics submenu
        add_submenu_page(
            $this->plugin_name,
            __('Analytics', 'content-protect-pro'),
            __('Analytics', 'content-protect-pro'),
            'manage_options',
            $this->plugin_name . '-analytics',
            [$this, 'display_analytics_page']
        );
        
        // AI Assistant submenu
        add_submenu_page(
            $this->plugin_name,
            __('🤖 AI Assistant', 'content-protect-pro'),
            __('🤖 AI Assistant', 'content-protect-pro'),
            'manage_options',
            $this->plugin_name . '-ai-assistant',
            [$this, 'display_ai_assistant_page']
        );
        
        // Settings submenu
        add_submenu_page(
            $this->plugin_name,
            __('Settings', 'content-protect-pro'),
            __('Settings', 'content-protect-pro'),
            'manage_options',
            $this->plugin_name . '-settings',
            [$this, 'display_settings_page']
        );
    }
    
    /**
     * Enqueue admin styles
     */
    public function enqueue_styles() {
        $screen = get_current_screen();
        
        // Load on CPP pages only
        if (strpos($screen->id, 'content-protect-pro') !== false) {
            wp_enqueue_style(
                $this->plugin_name,
                CPP_PLUGIN_URL . 'admin/css/cpp-admin.css',
                [],
                $this->version,
                'all'
            );
            
            // AI Assistant specific styles
            if (strpos($screen->id, 'ai-assistant') !== false) {
                wp_enqueue_style(
                    $this->plugin_name . '-ai',
                    CPP_PLUGIN_URL . 'admin/css/cpp-ai-assistant.css',
                    [],
                    $this->version,
                    'all'
                );
            }
        }
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_scripts() {
        $screen = get_current_screen();
        
        // Load on CPP pages only
        if (strpos($screen->id, 'content-protect-pro') !== false) {
            wp_enqueue_script(
                $this->plugin_name,
                CPP_PLUGIN_URL . 'admin/js/cpp-admin.js',
                ['jquery'],
                $this->version,
                true
            );
            
            // Localize script with nonce and AJAX URL
            wp_localize_script(
                $this->plugin_name,
                'cppAdmin',
                [
                    'ajaxUrl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('cpp_admin_nonce'),
                    'strings' => [
                        'confirmDelete' => __('Are you sure you want to delete this item?', 'content-protect-pro'),
                        'copySuccess' => __('Copied to clipboard!', 'content-protect-pro'),
                        'error' => __('An error occurred. Please try again.', 'content-protect-pro'),
                    ],
                ]
            );
            
            // AI Assistant specific scripts
            if (strpos($screen->id, 'ai-assistant') !== false) {
                wp_enqueue_script(
                    $this->plugin_name . '-ai',
                    CPP_PLUGIN_URL . 'admin/js/cpp-ai-assistant.js',
                    ['jquery'],
                    $this->version,
                    true
                );
            }
            
            // Media uploader for settings (overlay image picker)
            if (strpos($screen->id, 'settings') !== false) {
                wp_enqueue_media();
            }
        }
    }
    
    /**
     * Display dashboard page
     */
    public function display_plugin_dashboard() {
        require_once CPP_PLUGIN_DIR . 'admin/partials/cpp-admin-dashboard.php';
    }
    
    /**
     * Display gift codes page
     */
    public function display_giftcodes_page() {
        require_once CPP_PLUGIN_DIR . 'admin/partials/cpp-admin-giftcodes.php';
    }
    
    /**
     * Display protected videos page
     */
    public function display_videos_page() {
        require_once CPP_PLUGIN_DIR . 'admin/partials/cpp-admin-videos.php';
    }
    
    /**
     * Display analytics page
     */
    public function display_analytics_page() {
        require_once CPP_PLUGIN_DIR . 'admin/partials/cpp-admin-analytics.php';
    }
    
    /**
     * Display AI assistant page
     */
    public function display_ai_assistant_page() {
        require_once CPP_PLUGIN_DIR . 'admin/partials/cpp-admin-ai-assistant-display.php';
    }
    
    /**
     * Display settings page
     */
    public function display_settings_page() {
        require_once CPP_PLUGIN_DIR . 'admin/partials/cpp-admin-settings.php';
    }
    
    /**
     * Register plugin settings
     */
    public function register_settings() {
        // Integration settings
        register_setting('cpp_integration_settings_group', 'cpp_integration_settings', [
            'sanitize_callback' => [$this, 'sanitize_integration_settings'],
        ]);
        
        // Security settings
        register_setting('cpp_security_settings_group', 'cpp_security_settings', [
            'sanitize_callback' => [$this, 'sanitize_security_settings'],
        ]);
        
        // AI Assistant settings
        register_setting('cpp_ai_settings_group', 'cpp_ai_assistant_enabled');
        register_setting('cpp_ai_settings_group', 'cpp_onlymatt_api_key', [
            'sanitize_callback' => [$this, 'sanitize_api_key'],
        ]);
    }
    
    /**
     * Sanitize integration settings
     *
     * @param array $input Raw input
     * @return array Sanitized settings
     */
    public function sanitize_integration_settings($input) {
        $sanitized = [];
        
        // Token expiry
        $sanitized['token_expiry'] = isset($input['token_expiry']) ? absint($input['token_expiry']) : 900;
        
        // Overlay image (attachment ID per copilot-instructions POST-MIGRATION)
        $sanitized['overlay_image'] = isset($input['overlay_image']) ? absint($input['overlay_image']) : 0;
        
        // Purchase URL
        $sanitized['purchase_url'] = isset($input['purchase_url']) ? esc_url_raw($input['purchase_url']) : '';
        
        // Default required minutes
        $sanitized['default_required_minutes'] = isset($input['default_required_minutes']) ? absint($input['default_required_minutes']) : 10;
        
        // Bunny CDN settings (legacy/optional per copilot-instructions)
        $sanitized['bunny_cdn_hostname'] = isset($input['bunny_cdn_hostname']) ? sanitize_text_field($input['bunny_cdn_hostname']) : '';
        $sanitized['bunny_token_key'] = isset($input['bunny_token_key']) ? sanitize_text_field($input['bunny_token_key']) : '';
        $sanitized['bunny_api_key'] = isset($input['bunny_api_key']) ? sanitize_text_field($input['bunny_api_key']) : '';
        
        return $sanitized;
    }
    
    /**
     * Sanitize security settings
     *
     * @param array $input Raw input
     * @return array Sanitized settings
     */
    public function sanitize_security_settings($input) {
        $sanitized = [];
        
        $sanitized['rate_limit_enabled'] = isset($input['rate_limit_enabled']) && $input['rate_limit_enabled'] === '1';
        $sanitized['rate_limit_attempts'] = isset($input['rate_limit_attempts']) ? absint($input['rate_limit_attempts']) : 5;
        $sanitized['rate_limit_window'] = isset($input['rate_limit_window']) ? absint($input['rate_limit_window']) : 300;
        $sanitized['ip_validation_enabled'] = isset($input['ip_validation_enabled']) && $input['ip_validation_enabled'] === '1';
        $sanitized['session_encryption_enabled'] = isset($input['session_encryption_enabled']) && $input['session_encryption_enabled'] === '1';
        
        return $sanitized;
    }
    
    /**
     * Sanitize and encrypt API key
     * Following copilot-instructions encryption pattern
     *
     * @param string $api_key Raw API key
     * @return string Encrypted API key
     */
    public function sanitize_api_key($api_key) {
        if (empty($api_key)) {
            return '';
        }
        
        $api_key = sanitize_text_field($api_key);
        
        // Encrypt using CPP_Encryption (per copilot-instructions)
        if (!class_exists('CPP_Encryption')) {
            require_once CPP_PLUGIN_DIR . 'includes/class-cpp-encryption.php';
        }
        
        return CPP_Encryption::encrypt($api_key);
    }
    
    /**
     * Add settings link on plugins page
     *
     * @param array $links Existing links
     * @return array Modified links
     */
    public function add_action_links($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=' . $this->plugin_name . '-settings') . '">' . 
                        __('Settings', 'content-protect-pro') . '</a>';
        
        array_unshift($links, $settings_link);
        
        return $links;
    }
}