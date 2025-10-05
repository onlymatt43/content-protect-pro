<?php
/**
 * DIAGNOSTIC FILE - FOR DEVELOPMENT ONLY
 * 
 * ⚠️ WARNING: This file should not be loaded in production.
 * SQL queries in this file are for diagnostic purposes only.
 * 
 * @package Content_Protect_Pro
 * @internal
 */
/**
 * Simple Video Test - Content Protect Pro
 * Test individual video loaecho "Presto Player enabled: " . (!empty($settings['presto_enabled']) ? 'Yes' : 'No') . "<br>";ng components
 */

if (!defined('ABSPATH')) {
    require_once('wp-load.php');
}

echo "<h1>🎥 SIMPLE VIDEO TEST</h1>";
echo "<style>body{font-family:Arial;margin:20px;} .test-box{margin:10px 0;padding:15px;border:1px solid #ddd;border-radius:5px;}</style>";

// Test database connection and video count
echo "<div class='test-box'>";
echo "<h3>Database Check</h3>";
global $wpdb;
$video_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}cpp_protected_videos");
echo "Videos in database: <strong>$video_count</strong><br>";

if ($video_count > 0) {
    $videos = $wpdb->get_results("SELECT video_id, title FROM {$wpdb->prefix}cpp_protected_videos LIMIT 3");
    echo "Sample videos:<br>";
    foreach ($videos as $video) {
        echo "- {$video->title} (ID: {$video->video_id})<br>";
    }
}
echo "</div>";

// Test AJAX endpoint manually
echo "<div class='test-box'>";
echo "<h3>AJAX Endpoint Test</h3>";
if ($video_count > 0) {
    $first_video = $wpdb->get_row("SELECT video_id FROM {$wpdb->prefix}cpp_protected_videos LIMIT 1");

    // Simulate AJAX call
    sanitize_text_field($_POST['action'] ?? '') = 'cpp_get_video_token';
    sanitize_text_field($_POST['video_id'] ?? '') = $first_video->video_id;
    sanitize_text_field($_POST['nonce'] ?? '') = wp_create_nonce('cpp_public_nonce');

    try {
        require_once plugin_dir_path(dirname(__FILE__)) . 'content-protect-pro/public/class-cpp-public.php';
        $public = new CPP_Public('content-protect-pro', '1.0.0');

        ob_start();
        $public->get_video_token();
        $ajax_response = ob_get_clean();

        echo "AJAX Response for video {$first_video->video_id}:<br>";
        echo "<pre>" . htmlspecialchars($ajax_response) . "</pre>";
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage();
    }
} else {
    echo __("No videos to test AJAX with.", 'content-protect-pro');
}
echo "</div>";

// Test shortcode rendering
echo "<div class='test-box'>";
echo "<h3>Shortcode Test</h3>";
if (shortcode_exists('cpp_video_library')) {
    echo "✅ Shortcode 'cpp_video_library' is registered<br>";
    $shortcode_output = do_shortcode('[cpp_video_library]');
    $has_content = !empty(trim($shortcode_output));
    echo "Shortcode output length: " . strlen($shortcode_output) . " characters<br>";
    if ($has_content) {
        echo "✅ Shortcode produces output<br>";
        echo "Preview: " . htmlspecialchars(substr($shortcode_output, 0, 200)) . "...";
    } else {
        echo "❌ Shortcode produces no output";
    }
} else {
    echo "❌ Shortcode 'cpp_video_library' is NOT registered";
}
echo "</div>";

// Test integrations
echo "<div class='test-box'>";
echo "<h3>Integration Check</h3>";
$settings = get_option('cpp_integration_settings', array());
echo "Bunny enabled: " . (!empty($settings['bunny_enabled']) ? 'Yes' : 'No') . "<br>";
echo "Presto enabled: " . (!empty($settings['presto_enabled']) ? 'Yes' : 'No') . "<br>";
echo "Provider preference: " . ($settings['provider_preference'] ?? 'Not set') . "<br>";
echo "</div>";

// JavaScript test
echo "<div class='test-box'>";
echo "<h3>JavaScript Check</h3>";
echo "<p>Open browser console (F12) and check for JavaScript errors when loading videos.</p>";
echo "<p>Also check if jQuery is loaded: <script>document.write(typeof jQuery !== 'undefined' ? '✅ jQuery loaded' : '❌ jQuery missing');</script></p>";
echo "</div>";

// Recommendations
echo "<div class='test-box'>";
echo "<h3>🔧 Troubleshooting Steps</h3>";
echo "<ol>";
echo "<li>Check browser console for JavaScript errors</li>";
echo "<li>Verify videos exist in admin panel</li>";
echo "<li>Configure at least one integration (Bunny or Presto)</li>";
echo "<li>Test with a simple direct URL video first</li>";
echo "<li>Check WordPress debug logs for PHP errors</li>";
echo "<li>Ensure proper file permissions on wp-content/uploads/</li>";
echo "</ol>";
echo "</div>";

echo "<hr><p>Test completed at " . date('Y-m-d H:i:s') . "</p>";
?>