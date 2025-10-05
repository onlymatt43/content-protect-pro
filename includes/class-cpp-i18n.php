<?php
/**
 * Internationalization Handler
 * 
 * Loads and defines translation domain for the plugin.
 *
 * @package Content_Protect_Pro
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class CPP_i18n {
    
    /**
     * Load plugin text domain for translations
     *
     * @since 1.0.0
     */
    public function load_plugin_textdomain() {
        load_plugin_textdomain(
            'content-protect-pro',
            false,
            dirname(CPP_PLUGIN_BASENAME) . '/languages/'
        );
    }
}