# cPanel production deployment checklist

This repository template is intentionally secret-free. Copy values into the production environment privately in cPanel; never commit a real `.env`, API key, SMTP password, database password or gateway credential.

## Before deployment

- Create a separate staging subdomain and a separate MySQL database/user. Do not test migrations or payment callbacks against production.
- Confirm the PHP version is 8.3 and required extensions are enabled: OpenSSL, PDO MySQL, Mbstring, Tokenizer, XML, Ctype, JSON, BCMath, Fileinfo, GD and cURL.
- Set the document root to Laravel's `public/` directory. Do not expose `.env`, `storage/`, `vendor/` or application source files through the web root.
- Upload the reviewed release and install Composer dependencies with production flags where shell access is available.
- Copy `.env.production.example` to the private production `.env`; set a unique `APP_KEY` using `php artisan key:generate --force` in the target environment.
- Set the production MySQL database, SMTP sender/host/port/user/password, HTTPS APP_URL and secure session cookie settings privately.
- Keep AI and SMS disabled until their credentials, provider limits, consent, delivery and failure handling are tested.
- Do not enable automated payment gateways until each provider's official merchant sandbox certification and callback/replay tests pass.

## First staging release

Run from the Laravel project root:

```sh
composer install --no-dev --prefer-dist --optimize-autoloader
php artisan config:clear
php artisan migrate --force
php artisan db:seed --force
php artisan storage:link
php artisan optimize
php artisan route:list
```

Use a fresh staging database. Review seeded demo accounts and remove/disable demo users before any public exposure. Verify login, password reset email, email verification, uploads, course enrollment, orders, payment proof, admin review, branch review, refunds, live-class scheduling and backups.

## Queue and scheduler

The app uses the database queue by default. Ensure the queue tables exist (the Laravel migrations should be present) and test a real queued job on staging.

If cPanel does not support a persistent queue worker, configure a cron entry every minute to drain queued work:

```cron
* * * * * cd /home/CPANEL_USER/path/to/project && /usr/local/bin/php artisan queue:work --stop-when-empty --tries=3 --timeout=120 >> /dev/null 2>&1
```

Configure Laravel's scheduler once per minute:

```cron
* * * * * cd /home/CPANEL_USER/path/to/project && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
```

The Laravel scheduler already runs `system:backup --keep=14` daily at 02:00 and Recycle Bin pruning at 02:30. Also schedule the repository's full backup script once daily to capture the private `.env`, database and uploaded files:

```cron
0 3 * * * cd /home/CPANEL_USER/path/to/project && /bin/bash backup.sh >> storage/logs/backup.log 2>&1
```

The script writes restricted local backup files under `storage/backups/`; it is not an off-site or encrypted backup by itself. Configure a separate encrypted off-site copy using your hosting provider's supported backup destination, and perform a restore drill. Confirm the actual PHP binary, shell path and project path in the hosting account before saving cron entries.

## Backup and recovery

- Schedule encrypted off-server database and uploaded-media backups.
- Keep multiple restore points and restrict backup download access to authorized administrators.
- Perform a restore drill into a separate database and directory before production launch.
- Record the restore procedure and verify uploaded files, database relationships and login after restore.
- Do not treat a successful backup command as proof of recoverability.

## Production go-live gate

Go live only after staging browser acceptance, clean migrations, no failing/skipped required CI jobs, working mail delivery, tested queue/scheduler, successful restore drill, HTTPS/security review and a documented rollback point. Do not enable real bKash/Nagad/Rocket/SSLCommerz transactions until the corresponding provider's official merchant credentials and end-to-end verification tests are available.
