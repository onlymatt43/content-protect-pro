<?php
/**
 * Video Loading Diagnostic Script
 * Run this in your WordPress root to diagnose video loading issues
 */

if (!defined('ABSPATH')) {
    require_once('wp-load.php');
}

echo "<h1>🔧 VIDEO LOADING DIAGNOSTIC</h1>";
echo "<style>body{font-family:Arial;margin:20px;} .diag-box{margin:15px 0;padding:15px;border:1px solid #ddd;border-radius:5px;} .success{background:#d4edda;border-color:#c3e6cb;} .error{background:#f8d7da;border-color:#f5c6cb;} .warning{background:#fff3cd;border-color:#ffeaa7;} .info{background:#d1ecf1;border-color:#bee5eb;} .code{font-family:monospace;background:#f8f9fa;padding:5px;border-radius:3px;}</style>";

// 1. Check plugin status
echo "<div class='diag-box success'>";
echo "<h3>1. Plugin Status</h3>";
$active = is_plugin_active('content-protect-pro/content-protect-pro.php');
echo "Plugin active: " . ($active ? "✅ Yes" : "❌ No") . "<br>";
echo "Plugin file exists: " . (file_exists(WP_PLUGIN_DIR . '/content-protect-pro/content-protect-pro.php') ? "✅ Yes" : "❌ No");
echo "</div>";

// 2. Check database tables
echo "<div class='diag-box info'>";
echo "<h3>2. Database Tables</h3>";
global $wpdb;
$tables = ['cpp_protected_videos', 'cpp_giftcodes', 'cpp_analytics'];
foreach ($tables as $table) {
    $full_table = $wpdb->prefix . $table;
    $exists = $wpdb->get_var("SHOW TABLES LIKE '$full_table'") === $full_table;
    echo "Table <code>$full_table</code>: " . ($exists ? "✅ Exists" : "❌ Missing") . "<br>";
}
echo "</div>";

// 3. Check video data
echo "<div class='diag-box info'>";
echo "<h3>3. Video Data</h3>";
$video_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}cpp_protected_videos");
echo "Total videos: <strong>$video_count</strong><br>";

if ($video_count > 0) {
    $videos = $wpdb->get_results("SELECT video_id, title, bunny_library_id, presto_player_id, direct_url, integration_type FROM {$wpdb->prefix}cpp_protected_videos LIMIT 5");
    echo "<table border='1' style='border-collapse:collapse;margin-top:10px;'>";
    echo "<tr><th>Video ID</th><th>Title</th><th>Bunny ID</th><th>Presto ID</th><th>Direct URL</th><th>Type</th></tr>";
    foreach ($videos as $video) {
        echo "<tr>";
        echo "<td>{$video->video_id}</td>";
        echo "<td>{$video->title}</td>";
        echo "<td>" . ($video->bunny_library_id ?: '-') . "</td>";
        echo "<td>" . ($video->presto_player_id ?: '-') . "</td>";
        echo "<td>" . ($video->direct_url ? 'Yes' : 'No') . "</td>";
        echo "<td>" . ($video->integration_type ?: 'auto') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<strong>No videos found!</strong> You need to add videos in the admin panel.";
}
echo "</div>";

// 4. Check integrations
echo "<div class='diag-box info'>";
echo "<h3>4. Integration Settings</h3>";
$settings = get_option('cpp_integration_settings', []);
echo "Bunny enabled: " . (!empty($settings['bunny_enabled']) ? "✅ Yes" : "⚠️ No") . "<br>";
echo "Bunny API key set: " . (!empty($settings['bunny_api_key']) ? "✅ Yes" : "❌ No") . "<br>";
echo "Bunny library ID set: " . (!empty($settings['bunny_library_id']) ? "✅ Yes" : "❌ No") . "<br>";
echo "Presto enabled: " . (!empty($settings['presto_enabled']) ? "✅ Yes" : "⚠️ No") . "<br>";
echo "Provider preference: " . ($settings['provider_preference'] ?? 'auto') . "<br>";
echo "</div>";

// 5. Test shortcode
echo "<div class='diag-box info'>";
echo "<h3>5. Shortcode Test</h3>";
if (shortcode_exists('cpp_video_library')) {
    echo "Shortcode registered: ✅ Yes<br>";
    $output = do_shortcode('[cpp_video_library]');
    $has_content = !empty(trim($output));
    echo "Shortcode output: " . ($has_content ? "✅ Has content" : "❌ Empty") . "<br>";
    echo "Output length: " . strlen($output) . " characters<br>";
    if ($has_content) {
        echo "<details><summary>Preview output</summary><pre>" . htmlspecialchars(substr($output, 0, 500)) . "...</pre></details>";
    }
} else {
    echo "Shortcode registered: ❌ No";
}
echo "</div>";

// 6. Test AJAX endpoint
echo "<div class='diag-box warning'>";
echo "<h3>6. AJAX Endpoint Test</h3>";
if ($video_count > 0) {
    $test_video = $wpdb->get_row("SELECT video_id FROM {$wpdb->prefix}cpp_protected_videos LIMIT 1");

    // Simulate AJAX request
    $_POST['action'] = 'cpp_get_video_token';
    $_POST['video_id'] = $test_video->video_id;
    $_POST['nonce'] = wp_create_nonce('cpp_public_nonce');

    try {
        require_once WP_PLUGIN_DIR . '/content-protect-pro/public/class-cpp-public.php';
        $public = new CPP_Public('content-protect-pro', '1.0.0');

        ob_start();
        $result = $public->get_video_token();
        $output = ob_get_clean();

        $response = json_decode($output, true);
        if ($response) {
            echo "AJAX response: ✅ Valid JSON<br>";
            echo "Success: " . ($response['success'] ? "✅ Yes" : "❌ No") . "<br>";
            if ($response['success']) {
                echo "Provider: " . ($response['data']['provider'] ?? 'N/A') . "<br>";
                echo "Has signed URL: " . (!empty($response['data']['signed_url']) ? "✅ Yes" : "❌ No") . "<br>";
                echo "Has Presto embed: " . (!empty($response['data']['presto_embed']) ? "✅ Yes" : "❌ No") . "<br>";
            } else {
                echo "Error: " . ($response['data']['message'] ?? 'Unknown') . "<br>";
            }
        } else {
            echo "AJAX response: ❌ Invalid JSON<br>";
            echo "<pre>$output</pre>";
        }
    } catch (Exception $e) {
        echo "Exception: " . $e->getMessage() . "<br>";
    }
} else {
    echo "No videos to test AJAX with.";
}
echo "</div>";

// 7. Check JavaScript
echo "<div class='diag-box info'>";
echo "<h3>7. JavaScript Check</h3>";
$js_file = WP_PLUGIN_DIR . '/content-protect-pro/public/js/cpp-public.js';
$css_file = WP_PLUGIN_DIR . '/content-protect-pro/public/css/cpp-public.css';
echo "JS file exists: " . (file_exists($js_file) ? "✅ Yes" : "❌ No") . "<br>";
echo "CSS file exists: " . (file_exists($css_file) ? "✅ Yes" : "❌ No") . "<br>";
echo "jQuery check: <script>document.write(typeof jQuery !== 'undefined' ? '✅ Available' : '❌ Missing');</script><br>";
echo "</div>";

// 8. Recommendations
echo "<div class='diag-box warning'>";
echo "<h3>8. Recommendations</h3>";
$issues = [];

if (!$active) $issues[] = "Activate the Content Protect Pro plugin";
if ($video_count == 0) $issues[] = "Add videos in the admin panel";
if (empty($settings['bunny_enabled']) && empty($settings['presto_enabled'])) $issues[] = "Configure at least one video integration (Bunny or Presto)";
if (!shortcode_exists('cpp_video_library')) $issues[] = "Shortcode not registered - check for PHP errors";

if (empty($issues)) {
    echo "✅ No obvious issues detected. Check browser console for JavaScript errors.";
} else {
    echo "<ul>";
    foreach ($issues as $issue) {
        echo "<li>$issue</li>";
    }
    echo "</ul>";
}
echo "</div>";

// 9. Debug info
echo "<div class='diag-box info'>";
echo "<h3>9. Debug Information</h3>";
echo "WordPress version: " . get_bloginfo('version') . "<br>";
echo "PHP version: " . PHP_VERSION . "<br>";
echo "Plugin directory: " . WP_PLUGIN_DIR . "/content-protect-pro/<br>";
echo "Test completed at: " . date('Y-m-d H:i:s') . "<br>";
echo "</div>";

echo "<hr><p><em>If videos still don't load, check the browser console (F12) for JavaScript errors and PHP error logs.</em></p>";
?>