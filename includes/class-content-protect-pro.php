<?php
/**
 * The main plugin class
 *
 * This class defines the core functionality of the Content Protect Pro plugin,
 * combining gift code protection and video library protection features.
 *
 * @package Content_Protect_Pro
 * @since   1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Content_Protect_Pro {

    /**
     * The loader that's responsible for maintaining and registering all hooks
     *
     * @var CPP_Loader
     */
    protected $loader;

    /**
     * The unique identifier of this plugin
     *
     * @var string
     */
    protected $plugin_name;

    /**
     * The current version of the plugin
     *
     * @var string
     */
    protected $version;

    /**
     * Initialize the plugin and set its properties
     *
     * @since 1.0.0
     */
    public function __construct() {
        $this->plugin_name = 'content-protect-pro';
        $this->version = CPP_VERSION;

        $this->load_dependencies();
        $this->set_locale();
        $this->define_admin_hooks();
        $this->define_public_hooks();
    }

    /**
     * Load the required dependencies for this plugin
     *
     * @since 1.0.0
     */
    private function load_dependencies() {
        // Core loader
        require_once CPP_PLUGIN_DIR . 'includes/class-cpp-loader.php';
        
        // Internationalization
        require_once CPP_PLUGIN_DIR . 'includes/class-cpp-i18n.php';
        
        // Admin functionality
        require_once CPP_PLUGIN_DIR . 'admin/class-cpp-admin.php';
        
        // Public functionality
        require_once CPP_PLUGIN_DIR . 'public/class-cpp-public.php';
        
        // Gift code management
        require_once CPP_PLUGIN_DIR . 'includes/class-cpp-giftcode-manager.php';
        require_once CPP_PLUGIN_DIR . 'includes/class-cpp-giftcode-security.php';
        
        // Video protection (simplified - only Presto Player)
        require_once CPP_PLUGIN_DIR . 'includes/class-cpp-protection-manager.php';
        
        // Integrations (only Presto Player)
        require_once CPP_PLUGIN_DIR . 'includes/class-cpp-presto-integration.php';
        
        // Analytics and diagnostics
        require_once CPP_PLUGIN_DIR . 'includes/class-cpp-analytics.php';
        require_once CPP_PLUGIN_DIR . 'includes/class-cpp-diagnostic.php';
        
        // Basic features
        require_once CPP_PLUGIN_DIR . 'includes/class-cpp-encryption.php';
        require_once CPP_PLUGIN_DIR . 'includes/class-cpp-analytics-export.php';
        require_once CPP_PLUGIN_DIR . 'includes/class-cpp-migrations.php';
        
        // Helper functions
        require_once CPP_PLUGIN_DIR . 'includes/cpp-token-helpers.php';
        
        // AJAX handlers
        if (file_exists(CPP_PLUGIN_DIR . 'includes/cpp-ajax-handlers.php')) {
            require_once CPP_PLUGIN_DIR . 'includes/cpp-ajax-handlers.php';
        }

        $this->loader = new CPP_Loader();
    }

    /**
     * Define the locale for this plugin for internationalization
     *
     * @since 1.0.0
     */
    private function set_locale() {
        $plugin_i18n = new CPP_i18n();
        $this->loader->add_action('plugins_loaded', $plugin_i18n, 'load_plugin_textdomain');
    }

    /**
     * Register all of the hooks related to the admin area functionality
     *
     * @since 1.0.0
     */
    private function define_admin_hooks() {
        $plugin_admin = new CPP_Admin($this->get_plugin_name(), $this->get_version());

        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_styles');
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts');
        $this->loader->add_action('admin_menu', $plugin_admin, 'add_plugin_admin_menu');
        $this->loader->add_action('admin_init', $plugin_admin, 'options_update');
    }

    /**
     * Register all of the hooks related to the public-facing functionality
     *
     * @since 1.0.0
     */
    private function define_public_hooks() {
        $plugin_public = new CPP_Public($this->get_plugin_name(), $this->get_version());

        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_styles');
        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_scripts');
        
        // Shortcodes
        $this->loader->add_action('init', $plugin_public, 'init_shortcodes');
        
        // AJAX handlers
        $this->loader->add_action('wp_ajax_cpp_validate_giftcode', $plugin_public, 'validate_giftcode');
        $this->loader->add_action('wp_ajax_nopriv_cpp_validate_giftcode', $plugin_public, 'validate_giftcode');
        
        $this->loader->add_action('wp_ajax_cpp_get_video_token', $plugin_public, 'get_video_token');
        $this->loader->add_action('wp_ajax_nopriv_cpp_get_video_token', $plugin_public, 'get_video_token');

        // Video analytics tracking
        $this->loader->add_action('wp_ajax_cpp_track_video_event', $plugin_public, 'track_video_event');
        $this->loader->add_action('wp_ajax_nopriv_cpp_track_video_event', $plugin_public, 'track_video_event');
    }

    /**
     * Run the loader to execute all of the hooks
     *
     * @since 1.0.0
     */
    public function run() {
        // Run DB migrations if needed
        if (class_exists('CPP_Migrations')) {
            CPP_Migrations::maybe_migrate();
        }
        $this->loader->run();
    }

    /**
     * The name of the plugin used to uniquely identify it
     *
     * @since 1.0.0
     * @return string
     */
    public function get_plugin_name() {
        return $this->plugin_name;
    }

    /**
     * The reference to the class that orchestrates the hooks
     *
     * @since 1.0.0
     * @return CPP_Loader
     */
    public function get_loader() {
        return $this->loader;
    }

    /**
     * Retrieve the version number of the plugin
     *
     * @since 1.0.0
     * @return string
     */
    public function get_version() {
        return $this->version;
    }
}