#!/usr/bin/env php
<?php
/**
 * Emergency Diagnostic - Check Plugin Status
 * 
 * Usage: php emergency-diagnostic.php
 */

echo "╔═══════════════════════════════════════════════╗\n";
echo "║   Content Protect Pro - Emergency Diagnostic  ║\n";
echo "╚═══════════════════════════════════════════════╝\n\n";

// Test 1: Check main file syntax
echo "1️⃣ Testing main file syntax...\n";
exec('php -l content-protect-pro.php 2>&1', $output, $return);
if ($return === 0) {
    echo "   ✅ Main file: OK\n";
} else {
    echo "   ❌ Main file: SYNTAX ERROR\n";
    print_r($output);
    exit(1);
}

// Test 2: Check class loading
echo "\n2️⃣ Testing class loading...\n";
$critical_files = [
    'includes/class-cpp-activator.php',
    'includes/class-cpp-loader.php',
    'includes/class-content-protect-pro.php',
];

foreach ($critical_files as $file) {
    if (!file_exists($file)) {
        echo "   ❌ Missing: $file\n";
        continue;
    }
    
    exec("php -l $file 2>&1", $output, $return);
    if ($return === 0) {
        echo "   ✅ $file\n";
    } else {
        echo "   ❌ Syntax error in $file\n";
    }
}

// Test 3: Check for duplicate class definitions
echo "\n3️⃣ Checking for duplicate classes...\n";
exec('grep -r "^class CPP_" includes/ admin/ public/ 2>/dev/null | cut -d: -f2 | sort | uniq -d', $duplicates);
if (empty($duplicates)) {
    echo "   ✅ No duplicate classes found\n";
} else {
    echo "   ⚠️  Duplicate classes detected:\n";
    foreach ($duplicates as $dup) {
        echo "      • $dup\n";
    }
}

// Test 4: Check activation errors
echo "\n4️⃣ Simulating activation...\n";

// Mock WordPress functions
function __($text, $domain = '') { return $text; }
function esc_html__($text, $domain = '') { return htmlspecialchars($text); }
function plugin_dir_path($file) { return dirname($file) . '/'; }
function plugin_dir_url($file) { return 'http://test.local/wp-content/plugins/' . basename(dirname($file)) . '/'; }
function plugin_basename($file) { return basename(dirname($file)) . '/' . basename($file); }

define('ABSPATH', '/tmp/');
define('WPINC', 'wp-includes');

try {
    require_once 'includes/class-cpp-activator.php';
    echo "   ✅ Activator class loaded\n";
    
    // Check if activate method exists
    if (method_exists('CPP_Activator', 'activate')) {
        echo "   ✅ Activate method exists\n";
    } else {
        echo "   ❌ Activate method missing\n";
    }
} catch (Throwable $e) {
    echo "   ❌ ERROR: " . $e->getMessage() . "\n";
    echo "      File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "\n";
echo "╔═══════════════════════════════════════════════╗\n";
echo "║                 RECOMMENDATIONS                ║\n";
echo "╚═══════════════════════════════════════════════╝\n\n";

echo "📋 To deactivate the plugin manually:\n";
echo "   mysql -u USER -p DATABASE_NAME\n";
echo "   UPDATE wp_options SET option_value = REPLACE(option_value, 'content-protect-pro', '') WHERE option_name = 'active_plugins';\n\n";

echo "📋 To check WordPress error logs:\n";
echo "   tail -f /path/to/wp-content/debug.log\n\n";

echo "📋 To enable WordPress debug mode:\n";
echo "   Add to wp-config.php:\n";
echo "   define('WP_DEBUG', true);\n";
echo "   define('WP_DEBUG_LOG', true);\n";
echo "   define('WP_DEBUG_DISPLAY', false);\n\n";
