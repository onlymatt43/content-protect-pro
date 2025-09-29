<?php
/**
 * The admin-specific functionality of the plugin
 *
 * @package Content_Protect_Pro
 * @since   1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class CPP_Admin {

    /**
     * The ID of this plugin
     *
     * @var string
     */
    private $plugin_name;

    /**
     * The version of this plugin
     *
     * @var string
     */
    private $version;

    /**
     * Initialize the class and set its properties
     *
     * @param string $plugin_name The name of this plugin
     * @param string $version     The version of this plugin
     * @since 1.0.0
     */
    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    /**
     * Register the stylesheets for the admin area
     *
     * @since 1.0.0
     */
    public function enqueue_styles() {
        wp_enqueue_style(
            $this->plugin_name,
            CPP_PLUGIN_URL . 'admin/css/cpp-admin.css',
            array(),
            $this->version,
            'all'
        );
    }

    /**
     * Register the JavaScript for the admin area
     *
     * @since 1.0.0
     */
    public function enqueue_scripts() {
        wp_enqueue_script(
            $this->plugin_name,
            CPP_PLUGIN_URL . 'admin/js/cpp-admin.js',
            array('jquery'),
            $this->version,
            false
        );

        wp_localize_script(
            $this->plugin_name,
            'cpp_admin_ajax',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('cpp_admin_nonce'),
                'strings' => array(
                    'confirm_delete' => __('Are you sure you want to delete this item?', 'content-protect-pro'),
                    'loading' => __('Loading...', 'content-protect-pro'),
                    'error' => __('An error occurred. Please try again.', 'content-protect-pro'),
                )
            )
        );
    }

    /**
     * Add admin menu pages
     *
     * @since 1.0.0
     */
    public function add_plugin_admin_menu() {
        add_menu_page(
            __('Content Protect Pro', 'content-protect-pro'),
            __('Content Protect Pro', 'content-protect-pro'),
            'manage_options',
            $this->plugin_name,
            array($this, 'display_plugin_setup_page'),
            'dashicons-shield-alt',
            30
        );

        add_submenu_page(
            $this->plugin_name,
            __('Gift Codes', 'content-protect-pro'),
            __('Gift Codes', 'content-protect-pro'),
            'manage_options',
            $this->plugin_name . '-giftcodes',
            array($this, 'display_giftcodes_page')
        );

        add_submenu_page(
            $this->plugin_name,
            __('Protected Videos', 'content-protect-pro'),
            __('Protected Videos', 'content-protect-pro'),
            'manage_options',
            $this->plugin_name . '-videos',
            array($this, 'display_videos_page')
        );

        add_submenu_page(
            $this->plugin_name,
            __('Analytics', 'content-protect-pro'),
            __('Analytics', 'content-protect-pro'),
            'manage_options',
            $this->plugin_name . '-analytics',
            array($this, 'display_analytics_page')
        );

        add_submenu_page(
            $this->plugin_name,
            __('Settings', 'content-protect-pro'),
            __('Settings', 'content-protect-pro'),
            'manage_options',
            $this->plugin_name . '-settings',
            array($this, 'display_settings_page')
        );
    }

    /**
     * Main plugin page
     *
     * @since 1.0.0
     */
    public function display_plugin_setup_page() {
        include_once CPP_PLUGIN_DIR . 'admin/partials/cpp-admin-display.php';
    }

    /**
     * Gift codes management page
     *
     * @since 1.0.0
     */
    public function display_giftcodes_page() {
        include_once CPP_PLUGIN_DIR . 'admin/partials/cpp-admin-giftcodes.php';
    }

    /**
     * Protected videos management page
     *
     * @since 1.0.0
     */
    public function display_videos_page() {
        include_once CPP_PLUGIN_DIR . 'admin/partials/cpp-admin-videos.php';
    }

    /**
     * Analytics page
     *
     * @since 1.0.0
     */
    public function display_analytics_page() {
        include_once CPP_PLUGIN_DIR . 'admin/partials/cpp-admin-analytics.php';
    }

    /**
     * Settings page
     *
     * @since 1.0.0
     */
    public function display_settings_page() {
        include_once CPP_PLUGIN_DIR . 'admin/partials/cpp-admin-settings.php';
    }

    /**
     * Register and add settings
     *
     * @since 1.0.0
     */
    public function options_update() {
        register_setting(
            $this->plugin_name . '_giftcode_settings',
            $this->plugin_name . '_giftcode_settings',
            array($this, 'validate_giftcode_settings')
        );

        register_setting(
            $this->plugin_name . '_video_settings',
            $this->plugin_name . '_video_settings',
            array($this, 'validate_video_settings')
        );

        register_setting(
            $this->plugin_name . '_security_settings',
            $this->plugin_name . '_security_settings',
            array($this, 'validate_security_settings')
        );

        register_setting(
            $this->plugin_name . '_analytics_settings',
            $this->plugin_name . '_analytics_settings',
            array($this, 'validate_analytics_settings')
        );
    }

    /**
     * Validate gift code settings
     *
     * @param array $input
     * @return array
     * @since 1.0.0
     */
    public function validate_giftcode_settings($input) {
        $valid = array();

        $valid['enable_giftcodes'] = (isset($input['enable_giftcodes']) && !empty($input['enable_giftcodes'])) ? 1 : 0;
        $valid['allow_partial_use'] = (isset($input['allow_partial_use']) && !empty($input['allow_partial_use'])) ? 1 : 0;
        $valid['case_sensitive'] = (isset($input['case_sensitive']) && !empty($input['case_sensitive'])) ? 1 : 0;
        $valid['auto_generate_codes'] = (isset($input['auto_generate_codes']) && !empty($input['auto_generate_codes'])) ? 1 : 0;

        if (isset($input['code_length'])) {
            $valid['code_length'] = absint($input['code_length']);
            if ($valid['code_length'] < 4) {
                $valid['code_length'] = 4;
                add_settings_error(
                    $this->plugin_name . '_giftcode_settings',
                    'code_length',
                    __('Code length must be at least 4 characters.', 'content-protect-pro')
                );
            }
        }

        $valid['code_prefix'] = isset($input['code_prefix']) ? sanitize_text_field($input['code_prefix']) : '';
        $valid['code_suffix'] = isset($input['code_suffix']) ? sanitize_text_field($input['code_suffix']) : '';

        return $valid;
    }

    /**
     * Validate video settings
     *
     * @param array $input
     * @return array
     * @since 1.0.0
     */
    public function validate_video_settings($input) {
        $valid = array();

        $valid['enable_video_protection'] = (isset($input['enable_video_protection']) && !empty($input['enable_video_protection'])) ? 1 : 0;
        $valid['enable_bunny'] = (isset($input['enable_bunny']) && !empty($input['enable_bunny'])) ? 1 : 0;
        $valid['enable_presto'] = (isset($input['enable_presto']) && !empty($input['enable_presto'])) ? 1 : 0;

        if (isset($input['token_expiry'])) {
            $valid['token_expiry'] = absint($input['token_expiry']);
            if ($valid['token_expiry'] < 300) {
                $valid['token_expiry'] = 300;
                add_settings_error(
                    $this->plugin_name . '_video_settings',
                    'token_expiry',
                    __('Token expiry must be at least 300 seconds (5 minutes).', 'content-protect-pro')
                );
            }
        }

        $valid['default_access_level'] = isset($input['default_access_level']) ? sanitize_text_field($input['default_access_level']) : 'public';

        return $valid;
    }

    /**
     * Validate security settings
     *
     * @param array $input
     * @return array
     * @since 1.0.0
     */
    public function validate_security_settings($input) {
        $valid = array();

        $valid['enable_logging'] = (isset($input['enable_logging']) && !empty($input['enable_logging'])) ? 1 : 0;
        $valid['enable_rate_limiting'] = (isset($input['enable_rate_limiting']) && !empty($input['enable_rate_limiting'])) ? 1 : 0;

        $valid['log_retention_days'] = isset($input['log_retention_days']) ? absint($input['log_retention_days']) : 30;
        $valid['rate_limit_requests'] = isset($input['rate_limit_requests']) ? absint($input['rate_limit_requests']) : 100;
        $valid['rate_limit_window'] = isset($input['rate_limit_window']) ? absint($input['rate_limit_window']) : 3600;

        return $valid;
    }

    /**
     * Validate analytics settings
     *
     * @param array $input
     * @return array
     * @since 1.0.0
     */
    public function validate_analytics_settings($input) {
        $valid = array();

        $valid['enable_analytics'] = (isset($input['enable_analytics']) && !empty($input['enable_analytics'])) ? 1 : 0;
        $valid['track_giftcode_usage'] = (isset($input['track_giftcode_usage']) && !empty($input['track_giftcode_usage'])) ? 1 : 0;
        $valid['track_video_views'] = (isset($input['track_video_views']) && !empty($input['track_video_views'])) ? 1 : 0;
        $valid['anonymize_ip'] = (isset($input['anonymize_ip']) && !empty($input['anonymize_ip'])) ? 1 : 0;

        return $valid;
    }
}