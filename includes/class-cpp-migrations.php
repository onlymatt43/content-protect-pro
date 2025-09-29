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

        update_option('cpp_migrations_last_run', time());
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

        if (!empty($add_sql)) {
            $sql = 'ALTER TABLE ' . $table . ' ' . implode(', ', $add_sql);
            $wpdb->query($sql);
        }
    }
}
