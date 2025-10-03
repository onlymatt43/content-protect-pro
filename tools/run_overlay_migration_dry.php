<?php
// CLI helper to run the overlay migration dry-run from the site filesystem.
// Usage: php tools/run_overlay_migration_dry.php

// Locate wp-load.php by walking up directories
$dir = __DIR__ . '/..';
$found = false;
for ($i = 0; $i < 6; $i++) {
    $maybe = realpath($dir . str_repeat('/..', $i) . '/wp-load.php');
    if ($maybe && file_exists($maybe)) {
        require_once $maybe;
        $found = true;
        break;
    }
}
if (!$found) {
    // Try one more fallback: common location
    if (file_exists(__DIR__ . '/../../wp-load.php')) {
        require_once __DIR__ . '/../../wp-load.php';
        $found = true;
    }
}
if (!$found) {
    fwrite(STDERR, "Cannot locate wp-load.php. Run this from inside a WP install.\n");
    exit(2);
}

if (!class_exists('CPP_Migrations')) {
    fwrite(STDERR, "CPP_Migrations class not found. Ensure plugin files are in place.\n");
    exit(3);
}

$result = CPP_Migrations::run_overlay_migration_dry_run();
if ($result === false) {
    fwrite(STDERR, "Dry-run failed (internal error).\n");
    exit(4);
}

// Print JSON summary
echo json_encode($result, JSON_PRETTY_PRINT) . "\n";
return 0;
