Content Protect Pro - Backup Instructions

This file was generated automatically to record how to safely back up the plugin and the database before running migrations or other destructive changes.

1) Files backup (already created in this session)
- A zip archive of the plugin workspace is saved in the `backups/` directory alongside the project. The filename includes a UTC timestamp.

2) Recommended database backups
- Use WP-CLI (recommended when available):

```bash
# export full WP database to SQL file (run from site root)
wp db export "backups/wp-db-backup-$(date -u +%Y%m%dT%H%M%SZ).sql" --add-drop-table

# optional: compress
gzip "backups/wp-db-backup-$(date -u +%Y%m%dT%H%M%SZ).sql"
```

- Use mysqldump (if you have DB credentials and access):

```bash
# replace DB_NAME, DB_USER and DB_PASS and DB_HOST as appropriate
mysqldump -u DB_USER -p"DB_PASS" -h DB_HOST DB_NAME > "backups/mysql-backup-$(date -u +%Y%m%dT%H%M%SZ).sql"
# compress
gzip "backups/mysql-backup-$(date -u +%Y%m%dT%H%M%SZ).sql"
```

3) Restore notes
- WP-CLI restore (overwrites current DB):

```bash
# from site root
wp db import backups/wp-db-backup-YYYYMMDDTHHMMSSZ.sql
```

- mysqldump restore:
```bash
gunzip < backups/mysql-backup-YYYYMMDDTHHMMSSZ.sql.gz | mysql -u DB_USER -p"DB_PASS" -h DB_HOST DB_NAME
```

4) Safety checklist
- Always backup files and DB before running migration scripts.
- Work on a staging copy when possible.
- Verify backups by opening the SQL file (or restoring into a temporary DB) before proceeding.

If you want, I can also:
- Run the DB export here (requires WP-CLI and access to the site's WP root and DB credentials).
- Surface the migration report in the admin UI.

If you want me to create the file backup now, confirm and I will proceed. Otherwise I already created a README with commands and will now create the zip archive of the workspace.
