<?php
/**
 * Shortcodes & Frontend rendering for Content Protect Pro
 * - [cpp_video_library] : Fallback registration
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Backwards-compatible fallback: if the class-registered shortcode `cpp_video_library`
// is not available for any reason, register it to delegate to the sv_library output.
if (!shortcode_exists('cpp_video_library')) {
  add_shortcode('cpp_video_library', function($atts = []){
    // This function is a fallback and currently does not produce output.
    // It ensures the shortcode exists to prevent errors, but the primary
    // implementation is in the CPP_Public class.
    // Consider logging a warning if this fallback is ever executed.
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[Content Protect Pro][WARNING] Fallback shortcode [cpp_video_library] was executed. The primary shortcode in CPP_Public class may have failed to load.');
    }
    return '';
  });

  // Debug: confirm fallback registration
  if (function_exists('error_log')) {
      error_log('[Content Protect Pro][DEBUG] fallback cpp_video_library registered by includes/cpp-shortcodes.php');
  }
}