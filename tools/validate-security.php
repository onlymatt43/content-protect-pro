#!/usr/bin/env php
<?php
/**
 * Security Validation Scanner
 * 
 * Scans plugin for remaining security issues after auto-fix.
 * 
 * Usage: php tools/validate-security.php
 * 
 * @package Content_Protect_Pro
 * @since 1.0.0
 */

if (php_sapi_name() !== 'cli') {
    die("❌ This script must be run from CLI\n");
}

$plugin_dir = dirname(__DIR__);

// Colors
define('RED', "\033[0;31m");
define('GREEN', "\033[0;32m");
define('YELLOW', "\033[1;33m");
define('BLUE', "\033[0;34m");
define('RESET', "\033[0m");

echo BLUE . "╔═══════════════════════════════════════════════════════╗\n" . RESET;
echo BLUE . "║   Content Protect Pro - Security Validation          ║\n" . RESET;
echo BLUE . "╚═══════════════════════════════════════════════════════╝\n" . RESET;
echo "\n";

$issues = [
    'critical' => [],
    'warning' => [],
    'info' => [],
];

// Get all PHP files
$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($plugin_dir, RecursiveDirectoryIterator::SKIP_DOTS)
);

foreach ($files as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $path = $file->getPathname();
        
        // Skip excluded
        if (strpos($path, '/vendor/') !== false || 
            strpos($path, '/node_modules/') !== false ||
            strpos($path, '/backups/') !== false ||
            strpos($path, '/tools/') !== false) {
            continue;
        }
        
        $content = file_get_contents($path);
        $relative_path = str_replace($plugin_dir . '/', '', $path);
        
        // Check 1: AJAX without nonce
        if (preg_match('/function\s+cpp_ajax_(\w+)/', $content)) {
            if (strpos($content, 'wp_verify_nonce') === false) {
                $issues['critical'][] = "Missing nonce: $relative_path";
            }
        }
        
        // Check 2: Direct $_POST without sanitization
        if (preg_match('/\$_POST\s*\[[\'"](\w+)[\'"]\](?!\s*\?\?)/', $content, $matches)) {
            $issues['warning'][] = "Unsanitized POST in $relative_path: \${$matches[1]}";
        }
        
        // Check 3: SQL without prepare
        if (preg_match('/\$wpdb->(query|get_\w+)\s*\(\s*["\']SELECT|INSERT|UPDATE|DELETE/', $content)) {
            if (strpos($content, '$wpdb->prepare') === false) {
                $issues['critical'][] = "SQL without prepare() in $relative_path";
            }
        }
        
        // Check 4: Echo without escaping
        if (preg_match('/echo\s+\$\w+\s*;/', $content)) {
            if (strpos($content, 'esc_html') === false && 
                strpos($content, 'esc_attr') === false) {
                $issues['warning'][] = "Unescaped echo in $relative_path";
            }
        }
        
        // Check 5: Admin page without capability check
        if (strpos($path, '/admin/partials/') !== false) {
            if (strpos($content, 'current_user_can') === false) {
                $issues['info'][] = "No capability check in $relative_path";
            }
        }
    }
}

// Display results
echo "\n";
echo RED . "🔴 CRITICAL ISSUES: " . count($issues['critical']) . RESET . "\n";
foreach ($issues['critical'] as $issue) {
    echo RED . "  • $issue\n" . RESET;
}

echo "\n";
echo YELLOW . "⚠️  WARNINGS: " . count($issues['warning']) . RESET . "\n";
foreach ($issues['warning'] as $issue) {
    echo YELLOW . "  • $issue\n" . RESET;
}

echo "\n";
echo BLUE . "ℹ️  INFO: " . count($issues['info']) . RESET . "\n";
foreach ($issues['info'] as $issue) {
    echo BLUE . "  • $issue\n" . RESET;
}

echo "\n";

// Final verdict
$total_issues = count($issues['critical']) + count($issues['warning']) + count($issues['info']);

if ($total_issues === 0) {
    echo GREEN . "✅ No security issues found!\n" . RESET;
    exit(0);
} else {
    echo YELLOW . "⚠️  Found $total_issues issues requiring attention\n" . RESET;
    exit(1);
}
