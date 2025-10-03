<?php
// tools/backup_and_dry.php
// CLI helper to (1) export DB to backups/ and (2) run the plugin dry-run migration and save the JSON to uploads.
// Usage: php tools/backup_and_dry.php
// Run this from inside a WP installation where the plugin is installed.

if (php_sapi_name() !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line (SSH).\n");
    exit(2);
}

$cwd = getcwd();
$found = false;
$wp_load = null;
// Walk up to 6 levels to find wp-load.php
$dir = $cwd;
for ($i = 0; $i < 6; $i++) {
    $maybe = $dir . str_repeat('/..', $i) . '/wp-load.php';
    $maybe_real = realpath($maybe);
    if ($maybe_real && file_exists($maybe_real)) {
        $wp_load = $maybe_real;
        $found = true;
        break;
    }
}
// Try plugin parent heuristics
if (!$found) {
    $maybe = __DIR__ . '/../../wp-load.php';
    if (file_exists($maybe)) {
        $wp_load = $maybe;
        $found = true;
    }
}

if (!$found) {
    fwrite(STDERR, "Cannot locate wp-load.php. Run this from inside a WordPress install (site root or plugin folder).\n");
    exit(3);
}

// Include wp-load to get WP functions where possible
require_once $wp_load;

// Get DB constants from WP if available
if (defined('DB_NAME') && defined('DB_USER') && defined('DB_PASSWORD') && defined('DB_HOST')) {
    $db_name = DB_NAME;
    $db_user = DB_USER;
    $db_pass = DB_PASSWORD;
    $db_host = DB_HOST;
} else {
    // Fallback: parse wp-config.php
    $wp_config = dirname($wp_load) . '/wp-config.php';
    if (!file_exists($wp_config)) {
        fwrite(STDERR, "wp-config.php not found for parsing DB credentials.\n");
        exit(4);
    }
    $content = file_get_contents($wp_config);
    $get = function($key) use ($content) {
        if (preg_match("/define\(\s*'" . preg_quote($key, '/') . "'\s*,\s*'([^']*)'\s*\)/", $content, $m)) return $m[1];
        return '';
    };
    $db_name = $get('DB_NAME');
    $db_user = $get('DB_USER');
    $db_pass = $get('DB_PASSWORD');
    $db_host = $get('DB_HOST');
}

if (empty($db_name) || empty($db_user)) {
    fwrite(STDERR, "DB credentials not found in wp-config.php.\n");
    exit(5);
}

// Prepare backups dir
$backup_dir = rtrim(__DIR__ . '/..', DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR;
if (!is_dir($backup_dir)) @mkdir($backup_dir, 0755, true);

$timestamp = gmdate('Ymd') . 'T' . gmdate('His') . 'Z';
$dump_file = $backup_dir . 'mysql-backup-' . $timestamp . '.sql';
$gz_file = $dump_file . '.gz';

// Build mysqldump command
$escaped_db = escapeshellarg($db_name);
$escaped_user = escapeshellarg($db_user);
$escaped_host = escapeshellarg($db_host);
// Include password directly (read from wp-config) — this runs locally on the server only
$escaped_pass = $db_pass;

// Some hosts require --single-transaction for InnoDB
$cmd = "mysqldump -u " . $escaped_user . " -p" . escapeshellarg($escaped_pass) . " -h " . $escaped_host . " " . $escaped_db . " --single-transaction --quick --lock-tables=false > " . escapeshellarg($dump_file);

fwrite(STDOUT, "Running DB dump to: $dump_file\n");
$return = null;
exec($cmd, $out, $return);
if ($return !== 0) {
    fwrite(STDERR, "mysqldump failed (exit code $return). You can run the command manually:\n$cmd\n");
    exit(6);
}

// Compress the dump
fwrite(STDOUT, "Compressing dump to: $gz_file\n");
exec('gzip -f ' . escapeshellarg($dump_file), $out2, $r2);
if ($r2 !== 0) {
    fwrite(STDERR, "gzip failed (exit code $r2).\n");
    exit(7);
}

// Run plugin dry-run method if available
fwrite(STDOUT, "Running plugin dry-run migration (overlay -> attachment)\n");
if (!class_exists('CPP_Migrations')) {
    fwrite(STDERR, "CPP_Migrations class not available. Is the plugin active and loaded?\n");
    exit(8);
}

$report = CPP_Migrations::run_overlay_migration_dry_run();
if ($report === false) {
    fwrite(STDERR, "Dry-run returned error.\n");
    exit(9);
}

// Save report into uploads for audit
$uploaded = false;
if (function_exists('wp_upload_dir')) {
    $uploads = wp_upload_dir();
    $target_dir = trailingslashit($uploads['basedir']) . 'content-protect-pro/backups/';
    if (!is_dir($target_dir)) @mkdir($target_dir, 0755, true);
    $file_name = 'cpp-dry-samples-' . $timestamp . '.json';
    $full_path = $target_dir . $file_name;
    file_put_contents($full_path, json_encode(array('generated_at' => time(), 'report' => $report), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    $uploaded = true;
}

// Print summary
fwrite(STDOUT, "\nSUMMARY:\n");
fwrite(STDOUT, "DB dump: " . basename($gz_file) . "\n");
fwrite(STDOUT, "Dry-run migrated: " . intval($report['migrated']) . "\n");
fwrite(STDOUT, "Dry-run cleared: " . intval($report['cleared']) . "\n");
if ($uploaded) fwrite(STDOUT, "Dry-run JSON saved to uploads: $file_name\n");

fwrite(STDOUT, "Done. Please download the JSON and review samples before running the real migration.\n");

exit(0);
