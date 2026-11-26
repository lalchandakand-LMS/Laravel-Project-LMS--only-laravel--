# Safe System Operations

## Database backups

- Admins can create and restore a database snapshot from **Admin → System → Backup & Restore**. The panel and scheduled `system:backup` command currently handle the database only; they do not package or restore uploaded files.
- MySQL backups require the host to provide the `mysqldump` and `mysql` command-line clients. The shell backup/update scripts pass the MySQL password through `MYSQL_PWD`, not a command-line `-p` argument.
- SQLite backups are copied as database files.
- Admin-panel backup files are written to `storage/app/private/system-backups`, outside the public web root, with restrictive directory/file permissions.
- `backup.sh` and `update.sh` create separate archives for public uploads (`storage/app/public`) and private uploads (`storage/app/private`, including admission documents, payment evidence and assignment submissions). They exclude `storage/app/private/system-backups` to avoid recursively archiving database backups. The backup directory is restricted to mode 0700 and backup artifacts to mode 0600.
- Keep a matching database, `.env`, public-upload and private-upload backup set, and store an encrypted copy off-server. The Admin panel restore does not restore media archives; restore the matching `uploads-*.tar.gz` and `private-*.tar.gz` archives to `storage/app/` after database recovery, keeping private files outside the public web root. A backup stored only on the same hosting account is not a disaster-recovery strategy.
- The scheduled command is `php artisan system:backup --keep=14`; it retains the newest 14 backups and removes activity log rows older than 180 days.
- The scheduler runs the database backup daily at 02:00 server time and safe Recycle Bin cleanup at 02:30 server time. Recycle Bin retention defaults to 30 days and can be changed with RECYCLE_BIN_RETENTION_DAYS; linked/financial records are never auto-purged. In cPanel → Cron Jobs, add the standard Laravel scheduler entry (replace the path and PHP binary with the account's actual values):

```cron
* * * * * cd /home/CPANEL_USER/public_html && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
```

## Restore procedure

1. Take a fresh backup and download it before restoring.
2. Open **Backup & Restore**, select a database dump matching the configured database driver, and type `RESTORE`.
3. The application creates a second pre-restore safety snapshot before importing/replacing the database.
4. Verify login, courses, enrollments, orders, payments and learning progress after restore.

MySQL restore requires both `mysqldump` and `mysql` binaries and sufficient database permissions. If these are unavailable on shared hosting, restore through cPanel/phpMyAdmin after taking a cPanel backup.

## Safe code upgrades

Use `./update.sh --dry-run` first. The update script takes timestamped database, environment, public-upload and private-upload backups before applying additive Laravel migrations. It does not run `migrate:fresh`, `db:wipe` or seed commands.

## Activity audit

Successful mutating web requests (POST, PUT, PATCH and DELETE) are logged with actor, route, method, path, IP address and user-agent. Request payloads, passwords and payment credentials are not copied into the audit table. Logs are visible to admins under **Activity Logs**. The scheduled maintenance command removes audit entries older than 180 days.

## Recycle Bin

Soft-deleted accounts and selected learning/content records can be restored by admins. Financial orders and payment records are intentionally excluded. Permanent deletion is blocked for selected records while protected student, learning or transaction relations still exist.
