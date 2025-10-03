WP-CLI helper for Content Protect Pro

What this does
- Provides a small WP-CLI command `wp cpp overlay-migrate` to run the overlay URL -> attachment migration or to perform a dry-run and save a JSON sample to the uploads folder.

Where to run
- Run from your WordPress installation root (the directory containing wp-config.php).

Examples
- Dry-run and print JSON to terminal:
  wp --require=wp-content/plugins/content-protect-pro/tools/wp-cli-cpp.php cpp overlay-migrate --dry-run

- Dry-run and save JSON to uploads/content-protect-pro/backups/:
  wp --require=wp-content/plugins/content-protect-pro/tools/wp-cli-cpp.php cpp overlay-migrate --dry-run --save

- Run live migration (will write DB changes):
  wp --require=wp-content/plugins/content-protect-pro/tools/wp-cli-cpp.php cpp overlay-migrate

- Run live migration but first export a DB backup to uploads/content-protect-pro/backups/:
  wp --require=wp-content/plugins/content-protect-pro/tools/wp-cli-cpp.php cpp overlay-migrate --backup

Notes
- The helper uses WP-CLI's `db export` when `--backup` is used. Ensure WP-CLI is available on your server and that the webserver process has write access to the uploads directory.
- Backups are written to the WordPress uploads folder under `content-protect-pro/backups/` to avoid adding server-wide write needs.
- Running the live migration should only be done after verifying the dry-run samples and making a DB backup.
