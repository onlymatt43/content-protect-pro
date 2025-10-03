<?php
/**
 * Database migrations for Content Protect Pro
 *
 * @package Content_Protect_Pro
 * @since   1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class CPP_Migrations {

    /**
     * Run lightweight migrations to align DB schema
     * Adds missing columns used by current code without dropping legacy columns
     */
    public static function maybe_migrate() {
        global $wpdb;

        // Avoid running too often
        $last_run = get_option('cpp_migrations_last_run');
        if ($last_run && (time() - intval($last_run) < 12 * HOUR_IN_SECONDS)) {
            return;
        }

        self::migrate_videos_table($wpdb);
        self::migrate_giftcodes_table($wpdb);
    self::migrate_tokens_table($wpdb);
        // Migrate legacy overlay URLs to attachment IDs when possible
        self::migrate_overlay_urls_to_attachments($wpdb);

        update_option('cpp_migrations_last_run', time());
    }

    /**
     * Public runner to be called from admin UI or WP-CLI wrapper.
     * Returns the migration report array or false on failure.
     */
    public static function run_overlay_migration() {
        global $wpdb;
        try {
            self::migrate_overlay_urls_to_attachments($wpdb);
            $report = get_option('cpp_migrations_overlay_report', array());
            return $report;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Dry-run of overlay migration: returns what would be migrated/cleared without writing changes.
     * Returns array with 'migrated' count, 'cleared' count, and 'samples' (up to 20 rows)
     */
    public static function run_overlay_migration_dry_run() {
        global $wpdb;
        $table = $wpdb->prefix . 'cpp_giftcodes';

        $rows = $wpdb->get_results("SELECT id, overlay_image FROM {$table} WHERE overlay_image IS NOT NULL AND overlay_image <> ''");
        if (empty($rows)) return array('migrated' => 0, 'cleared' => 0, 'samples' => array());

        $migrated = 0;
        $cleared = 0;
        $samples = array();

        foreach ($rows as $r) {
            if (ctype_digit((string) $r->overlay_image)) continue; // already numeric
            $url = $r->overlay_image;
            $action = 'clear';
            $attach_id = 0;
            // Try to find by guid
            $attach_id = $wpdb->get_var($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE guid = %s AND post_type = 'attachment' LIMIT 1", $url));
            if ($attach_id) {
                $action = 'migrate';
            } else {
                // Try match by file basename
                $path = wp_basename(parse_url($url, PHP_URL_PATH));
                if ($path) {
                    $attach_id = $wpdb->get_var($wpdb->prepare("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value LIKE %s LIMIT 1", '%' . $wpdb->esc_like($path) . '%'));
                    if ($attach_id) {
                        $action = 'migrate';
                    }
                }
            }

            if ($action === 'migrate') {
                $migrated++;
            } else {
                $cleared++;
            }

            if (count($samples) < 20) {
                $samples[] = array(
                    'id' => $r->id,
                    'overlay' => $r->overlay_image,
                    'action' => $action,
                    'attach_id' => $attach_id ? intval($attach_id) : 0,
                );
            }
        }

        return array('migrated' => $migrated, 'cleared' => $cleared, 'samples' => $samples);
    }

    private static function migrate_videos_table($wpdb) {
        $table = $wpdb->prefix . 'cpp_protected_videos';
        if (!$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table))) {
            return; // Will be created on activation
        }

        $columns = $wpdb->get_col("SHOW COLUMNS FROM {$table}", 0);
        $missing = array();
        $add_sql = array();

        if (!in_array('required_minutes', $columns, true)) {
            $add_sql[] = 'ADD COLUMN required_minutes INT(11) NOT NULL DEFAULT 60 AFTER title';
        }
        if (!in_array('integration_type', $columns, true)) {
            $add_sql[] = "ADD COLUMN integration_type VARCHAR(50) NOT NULL DEFAULT 'bunny' AFTER required_minutes";
        }
        if (!in_array('bunny_library_id', $columns, true)) {
            $add_sql[] = 'ADD COLUMN bunny_library_id VARCHAR(255) NULL AFTER integration_type';
        }
        if (!in_array('presto_player_id', $columns, true)) {
            $add_sql[] = 'ADD COLUMN presto_player_id VARCHAR(255) NULL AFTER bunny_library_id';
        }
        if (!in_array('direct_url', $columns, true)) {
            $add_sql[] = 'ADD COLUMN direct_url TEXT NULL AFTER presto_player_id';
        }
        if (!in_array('status', $columns, true)) {
            $add_sql[] = "ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'active' AFTER description";
        }
        if (!in_array('usage_count', $columns, true)) {
            $add_sql[] = 'ADD COLUMN usage_count INT(11) NOT NULL DEFAULT 0 AFTER status';
        }
        if (!in_array('max_uses', $columns, true)) {
            $add_sql[] = 'ADD COLUMN max_uses INT(11) NULL AFTER usage_count';
        }

        if (!empty($add_sql)) {
            $sql = 'ALTER TABLE ' . $table . ' ' . implode(', ', $add_sql);
            $wpdb->query($sql);
        }
    }

    private static function migrate_giftcodes_table($wpdb) {
        $table = $wpdb->prefix . 'cpp_giftcodes';
        if (!$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table))) {
            return; // Will be created on activation
        }

        $columns = $wpdb->get_col("SHOW COLUMNS FROM {$table}", 0);
        $add_sql = array();

        if (!in_array('secure_token', $columns, true)) {
            $add_sql[] = 'ADD COLUMN secure_token VARCHAR(255) NOT NULL AFTER code';
        }
        if (!in_array('duration_minutes', $columns, true)) {
            $add_sql[] = 'ADD COLUMN duration_minutes INT(11) NOT NULL DEFAULT 60 AFTER secure_token';
        }
        if (!in_array('duration_display', $columns, true)) {
            $add_sql[] = 'ADD COLUMN duration_display VARCHAR(50) NULL AFTER duration_minutes';
        }
        if (!in_array('ip_restrictions', $columns, true)) {
            $add_sql[] = 'ADD COLUMN ip_restrictions TEXT NULL AFTER status';
        }
        if (!in_array('description', $columns, true)) {
            $add_sql[] = 'ADD COLUMN description TEXT NULL AFTER expires_at';
        }
        if (!in_array('overlay_image', $columns, true)) {
            $add_sql[] = "ADD COLUMN overlay_image TEXT NULL AFTER description";
        }
        if (!in_array('purchase_url', $columns, true)) {
            $add_sql[] = "ADD COLUMN purchase_url VARCHAR(255) NULL AFTER overlay_image";
        }

        if (!empty($add_sql)) {
            $sql = 'ALTER TABLE ' . $table . ' ' . implode(', ', $add_sql);
            $wpdb->query($sql);
        }
    }

    /**
     * Migrate legacy overlay_image URL values to attachment IDs when possible.
     * This looks up attachments by GUID and updates the giftcodes table.
     */
    private static function migrate_overlay_urls_to_attachments($wpdb) {
        $table = $wpdb->prefix . 'cpp_giftcodes';

        // Find rows where overlay_image appears to be a URL (non-numeric)
        $rows = $wpdb->get_results("SELECT id, overlay_image FROM {$table} WHERE overlay_image IS NOT NULL AND overlay_image <> ''");
        if (empty($rows)) return;

        $migrated = 0;
        $cleared = 0;

        foreach ($rows as $r) {
            if (ctype_digit((string) $r->overlay_image)) continue; // already numeric
            $url = $r->overlay_image;
            // Try to find an attachment whose guid matches the URL
            $attach_id = $wpdb->get_var($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE guid = %s AND post_type = 'attachment' LIMIT 1", $url));
            if ($attach_id) {
                $wpdb->update($table, array('overlay_image' => $attach_id), array('id' => $r->id), array('%d'), array('%d'));
                $migrated++;
                continue;
            }
            // Try matching by meta '_wp_attached_file' (relative path) - compare end of URL
            $path = wp_basename(parse_url($url, PHP_URL_PATH));
            if ($path) {
                $attach_id = $wpdb->get_var($wpdb->prepare("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value LIKE %s LIMIT 1", '%' . $wpdb->esc_like($path) . '%'));
                if ($attach_id) {
                    $wpdb->update($table, array('overlay_image' => $attach_id), array('id' => $r->id), array('%d'), array('%d'));
                    $migrated++;
                    continue;
                }
            }
            // If no match, clear the value to avoid storing external URLs
            $wpdb->update($table, array('overlay_image' => ''), array('id' => $r->id), array('%s'), array('%d'));
            $cleared++;
        }

        // Store a small report for admin viewing
        update_option('cpp_migrations_overlay_report', array(
            'migrated' => $migrated,
            'cleared' => $cleared,
            'run_at' => time(),
        ));
    }

    private static function migrate_tokens_table($wpdb) {
        $table = $wpdb->prefix . 'cpp_tokens';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table))) {
            return; // already exists
        }

        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            token VARCHAR(128) NOT NULL,
            user_id BIGINT(20) UNSIGNED DEFAULT 0,
            video_id VARCHAR(255) NOT NULL,
            ip_address VARCHAR(45) NULL,
            expires_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY token (token),
            KEY video_id (video_id),
            KEY expires_at (expires_at)
        ) {$charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

}
