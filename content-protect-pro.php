<?php
/**
 * Plugin Name: Content Protect Pro
 * Plugin URI: https://onlymatt.ca/content-protect-pro
 * Description: Token-based video protection with gift code redemption system. Presto Player integration with AI admin assistant.
 * Version: 3.1.0
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: OnlyMatt
 * Author URI: https://onlymatt.ca
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: content-protect-pro
 * Domain Path: /languages
 *
 * @package Content_Protect_Pro
 */

if (!defined('ABSPATH')) {
    exit;
}

// Plugin version (for cache busting and migrations)
define('CPP_VERSION', '3.1.0');

// Plugin root directory
define('CPP_PLUGIN_DIR', plugin_dir_path(__FILE__));

// Plugin root URL
define('CPP_PLUGIN_URL', plugin_dir_url(__FILE__));

// Plugin basename (for activation/deactivation hooks)
define('CPP_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Plugin activation hook
 * Creates database tables and sets default options
 */
function activate_content_protect_pro() {
    require_once CPP_PLUGIN_DIR . 'includes/class-cpp-activator.php';
    CPP_Activator::activate();
}

/**
 * Plugin deactivation hook
 * Cleans up transients and scheduled events
 */
function deactivate_content_protect_pro() {
    require_once CPP_PLUGIN_DIR . 'includes/class-cpp-deactivator.php';
    CPP_Deactivator::deactivate();
}

register_activation_hook(__FILE__, 'activate_content_protect_pro');
register_deactivation_hook(__FILE__, 'deactivate_content_protect_pro');

/**
 * Core plugin class
 * Loads dependencies and initializes the plugin
 */
require CPP_PLUGIN_DIR . 'includes/class-content-protect-pro.php';

/**
 * Bunny CDN Integration (LEGACY)
 * 
 * Optional integration per copilot-instructions.md.
 * Generates signed URLs for Bunny Stream videos.
 *
 * @package Content_Protect_Pro
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}
/**
 * Begin plugin execution
 */
function run_content_protect_pro() {
    $plugin = new Content_Protect_Pro();
    $plugin->run();
}
run_content_protect_pro();