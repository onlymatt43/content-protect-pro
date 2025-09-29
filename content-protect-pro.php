<?php
/**
 * Plugin Name: Content Protect Pro
 * Plugin URI: https://github.com/onlymatt43/content-protect-pro
 * Description: Unified protection system for gift codes and video content with advanced security features, analytics, and third-party integrations.
 * Version: 1.0.0
 * Author: ONLY MATT
 * Author URI: https://om43.dev
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: content-protect-pro
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.3
 * Requires PHP: 7.4
 * Network: false
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('CPP_PLUGIN_FILE', __FILE__);
define('CPP_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('CPP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CPP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CPP_VERSION', '1.0.0');
define('CPP_MIN_PHP_VERSION', '7.4');
define('CPP_MIN_WP_VERSION', '5.0');

/**
 * Check if PHP and WordPress versions are compatible
 */
function cpp_check_compatibility() {
    if (version_compare(PHP_VERSION, CPP_MIN_PHP_VERSION, '<')) {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p>';
            printf(
                __('Content Protect Pro requires PHP %s or higher. You are running PHP %s.', 'content-protect-pro'),
                CPP_MIN_PHP_VERSION,
                PHP_VERSION
            );
            echo '</p></div>';
        });
        return false;
    }

    global $wp_version;
    if (version_compare($wp_version, CPP_MIN_WP_VERSION, '<')) {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p>';
            printf(
                __('Content Protect Pro requires WordPress %s or higher. You are running WordPress %s.', 'content-protect-pro'),
                CPP_MIN_WP_VERSION,
                $GLOBALS['wp_version']
            );
            echo '</p></div>';
        });
        return false;
    }

    return true;
}

/**
 * Load the plugin text domain for internationalization
 */
function cpp_load_textdomain() {
    load_plugin_textdomain(
        'content-protect-pro',
        false,
        dirname(CPP_PLUGIN_BASENAME) . '/languages/'
    );
}
add_action('plugins_loaded', 'cpp_load_textdomain');

/**
 * Initialize the plugin
 */
function cpp_init() {
    if (!cpp_check_compatibility()) {
        return;
    }

    // Load core files
    require_once CPP_PLUGIN_DIR . 'includes/class-cpp-loader.php';
    require_once CPP_PLUGIN_DIR . 'includes/class-cpp-activator.php';
    require_once CPP_PLUGIN_DIR . 'includes/class-cpp-deactivator.php';
    require_once CPP_PLUGIN_DIR . 'includes/class-content-protect-pro.php';
    
    // Load helper functions
    require_once CPP_PLUGIN_DIR . 'includes/cpp-token-helpers.php';

    // Run the plugin
    $plugin = new Content_Protect_Pro();
    $plugin->run();
}
add_action('plugins_loaded', 'cpp_init');

/**
 * Plugin activation hook
 */
function cpp_activate() {
    if (!cpp_check_compatibility()) {
        wp_die(__('Content Protect Pro cannot be activated due to compatibility issues.', 'content-protect-pro'));
    }

    require_once CPP_PLUGIN_DIR . 'includes/class-cpp-activator.php';
    CPP_Activator::activate();
}
register_activation_hook(__FILE__, 'cpp_activate');

/**
 * Plugin deactivation hook
 */
function cpp_deactivate() {
    require_once CPP_PLUGIN_DIR . 'includes/class-cpp-deactivator.php';
    CPP_Deactivator::deactivate();
}
register_deactivation_hook(__FILE__, 'cpp_deactivate');

/**
 * Load AJAX handlers
 */
if (is_admin() && defined('DOING_AJAX') && DOING_AJAX) {
    require_once CPP_PLUGIN_DIR . 'includes/cpp-ajax-handlers.php';
}