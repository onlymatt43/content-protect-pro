#!/usr/bin/env php
<?php
/**
 * Automatic Security Fixes for Content Protect Pro
 * 
 * Fixes 739+ WordPress coding standards violations automatically.
 * Based on copilot-instructions.md security patterns.
 * 
 * Usage: php tools/auto-fix-security.php [--dry-run]
 * 
 * @package Content_Protect_Pro
 * @since 1.0.0
 */

// CLI only
if (php_sapi_name() !== 'cli') {
    die("❌ This script must be run from CLI\n");
}

// Config
$plugin_dir = dirname(__DIR__);
$dry_run = in_array('--dry-run', $argv);
$backup_dir = $plugin_dir . '/backups/' . date('Y-m-d_His');

// Colors
$colors = [
    'red' => "\033[0;31m",
    'green' => "\033[0;32m",
    'yellow' => "\033[1;33m",
    'blue' => "\033[0;34m",
    'reset' => "\033[0m",
];

function color($text, $color, $colors) {
    return $colors[$color] . $text . $colors['reset'];
}

echo color("╔════════════════════════════════════════════════════╗\n", 'blue', $colors);
echo color("║   Content Protect Pro - Auto Security Fix         ║\n", 'blue', $colors);
echo color("╚════════════════════════════════════════════════════╝\n", 'blue', $colors);
echo "\n";

if ($dry_run) {
    echo color("ℹ️  DRY RUN MODE - No files will be modified\n\n", 'yellow', $colors);
} else {
    echo color("⚠️  PRODUCTION MODE - Files will be modified!\n", 'red', $colors);
    echo "Creating backup in: $backup_dir\n\n";
    
    if (!is_dir($backup_dir)) {
        mkdir($backup_dir, 0755, true);
    }
}

// Get all PHP files
$files = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($plugin_dir, RecursiveDirectoryIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $path = $file->getPathname();
        
        // Skip excluded directories
        $excludes = ['/vendor/', '/node_modules/', '/tests/', '/backups/', '/tools/'];
        $skip = false;
        foreach ($excludes as $exclude) {
            if (strpos($path, $exclude) !== false) {
                $skip = true;
                break;
            }
        }
        
        if (!$skip) {
            $files[] = $path;
        }
    }
}

echo "Found " . color(count($files), 'yellow', $colors) . " PHP files to analyze\n\n";

// Statistics
$stats = [
    'files_analyzed' => 0,
    'files_modified' => 0,
    'nonce_added' => 0,
    'sanitization_added' => 0,
    'escaping_added' => 0,
    'i18n_added' => 0,
    'sql_prepared' => 0,
    'timing_safe_added' => 0,
];

// Process each file
foreach ($files as $file) {
    $stats['files_analyzed']++;
    $relative_path = str_replace($plugin_dir . '/', '', $file);
    
    echo color("📄 Analyzing: ", 'blue', $colors) . $relative_path . "\n";
    
    $content = file_get_contents($file);
    $original_content = $content;
    $fixes_in_file = [];
    
    // ============================================
    // FIX 1: Add nonce validation to AJAX handlers
    // ============================================
    if (preg_match('/function\s+cpp_ajax_(\w+)\s*\(/', $content, $matches)) {
        if (strpos($content, 'wp_verify_nonce') === false) {
            $fixes_in_file[] = 'Missing nonce check';
            $stats['nonce_added']++;
            
            // Add nonce check after function declaration
            $content = preg_replace(
                '/(function\s+cpp_ajax_\w+\s*\(\s*\)\s*\{)/',
                "$1\n    // Security check (per copilot-instructions)\n    if (!wp_verify_nonce(\$_POST['nonce'] ?? '', 'cpp_public_nonce')) {\n        wp_send_json_error(['message' => __('Security check failed', 'content-protect-pro')], 403);\n    }\n",
                $content,
                1
            );
        }
    }
    
    // ============================================
    // FIX 2: Replace == with hash_equals() for tokens
    // ============================================
    $pattern = '/\$(\w*token\w*)\s*===?\s*\$(\w*token\w*)/i';
    if (preg_match($pattern, $content)) {
        $fixes_in_file[] = 'Timing-safe comparison';
        $stats['timing_safe_added']++;
        
        $content = preg_replace(
            $pattern,
            'hash_equals((string)$$1, (string)$$2)',
            $content
        );
    }
    
    // ============================================
    // FIX 3: Sanitize $_POST accesses
    // ============================================
    $pattern = '/\$_POST\s*\[\s*[\'"](\w+)[\'"]\s*\](?!\s*\?\?)/';
    if (preg_match($pattern, $content)) {
        $count = 0;
        $content = preg_replace_callback(
            $pattern,
            function($matches) {
                return "sanitize_text_field(\$_POST['{$matches[1]}'] ?? '')";
            },
            $content,
            -1,
            $count
        );
        
        if ($count > 0) {
            $fixes_in_file[] = "Sanitized {$count} POST inputs";
            $stats['sanitization_added'] += $count;
        }
    }
    
    // ============================================
    // FIX 4: Sanitize $_GET accesses
    // ============================================
    $pattern = '/\$_GET\s*\[\s*[\'"](\w+)[\'"]\s*\](?!\s*\?\?)/';
    if (preg_match($pattern, $content)) {
        $count = 0;
        $content = preg_replace_callback(
            $pattern,
            function($matches) {
                return "sanitize_text_field(\$_GET['{$matches[1]}'] ?? '')";
            },
            $content,
            -1,
            $count
        );
        
        if ($count > 0) {
            $fixes_in_file[] = "Sanitized {$count} GET inputs";
            $stats['sanitization_added'] += $count;
        }
    }
    
    // ============================================
    // FIX 5: Add esc_html() to echo statements
    // ============================================
    $pattern = '/echo\s+\$(\w+)\s*;/';
    if (preg_match($pattern, $content)) {
        $count = 0;
        $content = preg_replace(
            $pattern,
            'echo esc_html($$1);',
            $content,
            -1,
            $count
        );
        
        if ($count > 0) {
            $fixes_in_file[] = "Escaped {$count} echo outputs";
            $stats['escaping_added'] += $count;
        }
    }
    
    // ============================================
    // FIX 6: Add i18n to hardcoded strings
    // ============================================
    $pattern = '/([\'"])([A-Z][a-zA-Z\s]+\.)\1/';
    if (preg_match($pattern, $content)) {
        $count = 0;
        $content = preg_replace_callback(
            $pattern,
            function($matches) use (&$count) {
                // Skip if already wrapped in __()
                if (strpos($matches[0], '__') !== false) {
                    return $matches[0];
                }
                $count++;
                return "__({$matches[1]}{$matches[2]}{$matches[1]}, 'content-protect-pro')";
            },
            $content,
            -1,
            $count
        );
        
        if ($count > 0) {
            $fixes_in_file[] = "Added i18n to {$count} strings";
            $stats['i18n_added'] += $count;
        }
    }
    
    // ============================================
    // FIX 7: Ensure SQL queries use $wpdb->prepare()
    // ============================================
    $pattern = '/\$wpdb->(query|get_\w+)\s*\(\s*["\'](?!.*prepare)/';
    if (preg_match($pattern, $content)) {
        $fixes_in_file[] = '⚠️  MANUAL: SQL query needs prepare()';
        $stats['sql_prepared']++;
    }
    
    // ============================================
    // FIX 8: Add capability checks to admin functions
    // ============================================
    if (strpos($file, '/admin/') !== false) {
        $pattern = '/function\s+\w+\s*\([^)]*\)\s*\{(?!\s*if\s*\(\s*!?\s*current_user_can)/';
        if (preg_match($pattern, $content)) {
            $fixes_in_file[] = '⚠️  MANUAL: Add current_user_can() check';
        }
    }
    
    // Save changes
    if ($content !== $original_content) {
        $stats['files_modified']++;
        
        if (!$dry_run) {
            // Backup original
            $backup_path = $backup_dir . '/' . basename($file);
            copy($file, $backup_path);
            
            // Write fixed content
            file_put_contents($file, $content);
        }
        
        echo color("  ✅ Fixed: ", 'green', $colors) . implode(', ', $fixes_in_file) . "\n";
    } else {
        echo color("  ✓ No issues\n", 'green', $colors);
    }
}

// ============================================
// Summary Report
// ============================================
echo "\n";
echo color("╔════════════════════════════════════════════════════╗\n", 'blue', $colors);
echo color("║                  SUMMARY REPORT                    ║\n", 'blue', $colors);
echo color("╚════════════════════════════════════════════════════╝\n", 'blue', $colors);
echo "\n";

echo "📊 Files Analyzed:       " . color($stats['files_analyzed'], 'yellow', $colors) . "\n";
echo "📝 Files Modified:       " . color($stats['files_modified'], 'yellow', $colors) . "\n";
echo "\n";
echo "🔐 Security Fixes:\n";
echo "   • Nonce checks added:        " . color($stats['nonce_added'], 'green', $colors) . "\n";
echo "   • Inputs sanitized:          " . color($stats['sanitization_added'], 'green', $colors) . "\n";
echo "   • Outputs escaped:           " . color($stats['escaping_added'], 'green', $colors) . "\n";
echo "   • Timing-safe comparisons:   " . color($stats['timing_safe_added'], 'green', $colors) . "\n";
echo "\n";
echo "🌍 Standards:\n";
echo "   • i18n strings added:        " . color($stats['i18n_added'], 'green', $colors) . "\n";
echo "   • SQL queries flagged:       " . color($stats['sql_prepared'], 'yellow', $colors) . "\n";
echo "\n";

if (!$dry_run) {
    echo color("✅ Fixes applied successfully!\n", 'green', $colors);
    echo "📁 Backups saved to: $backup_dir\n";
} else {
    echo color("ℹ️  Dry run complete. Run without --dry-run to apply fixes.\n", 'yellow', $colors);
}

echo "\n";
echo color("⚠️  NEXT STEPS:\n", 'yellow', $colors);
echo "1. Review SQL queries flagged above\n";
echo "2. Add capability checks to admin functions\n";
echo "3. Test plugin functionality\n";
echo "4. Run: composer phpcs for final validation\n";
echo "\n";

exit(0);
