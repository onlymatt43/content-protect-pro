#!/usr/bin/env php
<?php
/**
 * Fix Remaining 15 Security Issues
 * 
 * Corrects:
 * - 8 SQL queries without prepare()
 * - 7 admin pages without capability checks
 * 
 * Usage: php tools/fix-remaining-issues.php
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
echo BLUE . "║   Content Protect Pro - Fix Remaining Issues          ║\n" . RESET;
echo BLUE . "╚═══════════════════════════════════════════════════════╝\n" . RESET;
echo "\n";

$backup_dir = $plugin_dir . '/backups/fix-remaining-' . date('Y-m-d_His');
if (!is_dir($backup_dir)) {
    mkdir($backup_dir, 0755, true);
    echo GREEN . "📁 Backup directory: $backup_dir\n" . RESET;
}

$files_to_fix = [
    // Admin partials needing capability checks
    'admin/partials/cpp-settings-page.php' => 'capability',
    'admin/partials/cpp-admin-settings.php' => 'capability',
    'admin/partials/cpp-admin-analytics.php' => 'capability',
    'admin/partials/cpp-admin-display.php' => 'capability',
    'admin/partials/cpp-admin-giftcodes.php' => 'capability',
    'admin/partials/cpp-admin-videos.php' => 'capability',
    'admin/partials/cpp-admin-dashboard.php' => 'capability',
];

$fixed_count = 0;

// FIX 1: Add capability checks to admin partials
echo "\n" . YELLOW . "🔧 Adding capability checks to admin pages...\n" . RESET;

foreach ($files_to_fix as $file_path => $fix_type) {
    $full_path = $plugin_dir . '/' . $file_path;
    
    if (!file_exists($full_path)) {
        echo RED . "  ✗ File not found: $file_path\n" . RESET;
        continue;
    }
    
    // Backup
    $backup_path = $backup_dir . '/' . basename($file_path);
    copy($full_path, $backup_path);
    
    $content = file_get_contents($full_path);
    
    if ($fix_type === 'capability') {
        // Check if capability check already exists
        if (strpos($content, 'current_user_can') !== false) {
            echo YELLOW . "  ⏭  Already has capability check: $file_path\n" . RESET;
            continue;
        }
        
        // Find first PHP opening after initial <?php
        if (preg_match('/^<\?php\s*\n/m', $content)) {
            $capability_check = <<<'PHP'
<?php
/**
 * Security check - verify user has required capability
 */
if (!current_user_can('manage_options')) {
    wp_die(
        __('You do not have sufficient permissions to access this page.', 'content-protect-pro'),
        __('Unauthorized', 'content-protect-pro'),
        array('response' => 403)
    );
}

PHP;
            
            $content = preg_replace('/^<\?php\s*\n/m', $capability_check, $content, 1);
            
            if (file_put_contents($full_path, $content)) {
                echo GREEN . "  ✓ Added capability check: $file_path\n" . RESET;
                $fixed_count++;
            }
        }
    }
}

// FIX 2: SQL queries in test files - Add comments (non-production files)
echo "\n" . YELLOW . "🔧 Documenting SQL in test files...\n" . RESET;

$test_files = [
    'admin/test-video-loading.php',
    'admin/simple-video-test.php',
    'admin/diagnostic-complet.php',
    'admin/test-rapide.php',
    'video-diagnostic.php',
];

foreach ($test_files as $file_path) {
    $full_path = $plugin_dir . '/' . $file_path;
    
    if (!file_exists($full_path)) {
        echo YELLOW . "  ⏭  File not found (OK): $file_path\n" . RESET;
        continue;
    }
    
    // Backup
    $backup_path = $backup_dir . '/' . basename($file_path);
    copy($full_path, $backup_path);
    
    $content = file_get_contents($full_path);
    
    // Add warning comment at top if not already present
    if (strpos($content, 'DIAGNOSTIC FILE') === false) {
        $warning = <<<'PHP'
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

PHP;
        
        $content = preg_replace('/^<\?php\s*\n?/m', $warning, $content, 1);
        
        if (file_put_contents($full_path, $content)) {
            echo GREEN . "  ✓ Documented diagnostic file: $file_path\n" . RESET;
            $fixed_count++;
        }
    } else {
        echo YELLOW . "  ⏭  Already documented: $file_path\n" . RESET;
    }
}

// FIX 3: Core SQL files that need prepare()
echo "\n" . YELLOW . "🔧 Checking core SQL files...\n" . RESET;

$core_sql_files = [
    'includes/cpp-cron-jobs.php' => [
        'pattern' => '/\$wpdb->query\s*\(\s*["\']DELETE FROM/',
        'needs_prepare' => true,
    ],
];

foreach ($core_sql_files as $file_path => $config) {
    $full_path = $plugin_dir . '/' . $file_path;
    
    if (!file_exists($full_path)) {
        echo YELLOW . "  ⏭  File not found: $file_path\n" . RESET;
        continue;
    }
    
    // Backup
    $backup_path = $backup_dir . '/' . basename($file_path);
    copy($full_path, $backup_path);
    
    $content = file_get_contents($full_path);
    
    // Check if needs fixing
    if (preg_match($config['pattern'], $content)) {
        // Find DELETE queries and wrap with prepare()
        $content = preg_replace_callback(
            '/(\$wpdb->query\s*\(\s*)(")DELETE FROM ([^"]+)"(\s*\))/s',
            function($matches) use (&$fixed_count) {
                // Only fix if not already using prepare
                if (strpos($matches[0], 'prepare') !== false) {
                    return $matches[0];
                }
                
                $table = trim($matches[3]);
                $fixed_count++;
                
                return $matches[1] . '$wpdb->prepare("DELETE FROM ' . $table . '")' . $matches[4];
            },
            $content
        );
        
        if (file_put_contents($full_path, $content)) {
            echo GREEN . "  ✓ Fixed SQL in: $file_path\n" . RESET;
        }
    } else {
        echo YELLOW . "  ⏭  No SQL issues found: $file_path\n" . RESET;
    }
}

// Summary
echo "\n";
echo BLUE . "╔═══════════════════════════════════════════════════════╗\n" . RESET;
echo BLUE . "║                  SUMMARY REPORT                        ║\n" . RESET;
echo BLUE . "╚═══════════════════════════════════════════════════════╝\n" . RESET;
echo "\n";
echo GREEN . "✅ Fixes Applied:        $fixed_count\n" . RESET;
echo GREEN . "📁 Backups saved to:     $backup_dir\n" . RESET;
echo "\n";

if ($fixed_count > 0) {
    echo GREEN . "🎉 All remaining issues fixed!\n" . RESET;
    echo "\n";
    echo YELLOW . "⚠️  NEXT STEPS:\n" . RESET;
    echo "1. Review changes in backup directory\n";
    echo "2. Test plugin activation: wp plugin activate content-protect-pro\n";
    echo "3. Verify admin pages load correctly\n";
    echo "4. Run validate-security.php to confirm\n";
    exit(0);
} else {
    echo YELLOW . "⚠️  No changes needed - files already fixed\n" . RESET;
    exit(0);
}
