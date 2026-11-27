# cPanel production deployment checklist

This repository is prepared for a private production environment. Real database, mail, OAuth, SMS and AI credentials must be entered only in the server-side `.env` through cPanel Terminal/File Manager or a protected deployment secret store. Never commit a populated `.env`, paste API keys into GitHub, or expose credentials in browser code.

## 1. Hosting and document root

- Create a separate staging subdomain and a separate MySQL database/user. Do not test migrations, mail, OAuth callbacks or payment callbacks against production.
- Confirm PHP 8.3 and required extensions: OpenSSL, PDO MySQL, Mbstring, Tokenizer, XML, Ctype, JSON, BCMath, Fileinfo, GD and cURL.
- Point the domain document root to Laravel's `public/` directory. If the hosting plan forces `public_html`, keep the Laravel project, `.env`, `vendor/`, `storage/` and application source outside the web root and use the documented front-controller path.
- Confirm HTTPS and the certificate work before setting secure cookies.
- Ensure `storage/`, `bootstrap/cache/`, and the backup directory are writable by the cPanel account and not publicly accessible.

## 2. Build and upload a release

Build frontend assets on a trusted local/CI machine (cPanel Node.js is not required if the artifact is built before upload):

```bash
npm ci
npm run build
```

Upload the generated `public/build/` directory with the same code release. Do not upload a stale build from another commit. GitHub Actions also builds Vite assets and publishes the `vite-production-build` artifact; use the artifact from the exact release commit.

Install PHP dependencies on the server:

```bash
composer install --no-dev --prefer-dist --optimize-autoloader
```

## 3. Private production environment

Copy `.env.production.example` to the Laravel project's private `.env`. Replace every `CHANGE_ME_*` placeholder and generate the application key once on the target server:

```bash
php artisan key:generate --force
```

Never regenerate an existing production `APP_KEY`: doing so invalidates encrypted cookies/sessions and any encrypted application data.

### Required MySQL values

Create a database and database user in cPanel → MySQL Databases. Grant that user only the permissions Laravel needs for the application database. Set:

- `DB_CONNECTION=mysql`
- `DB_HOST` (often `localhost`, confirm with the host)
- `DB_PORT=3306`
- `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` using the exact cPanel-prefixed names

### SMTP mail

Use the mailbox and SMTP host supplied by Web Host BD (or the selected mail provider). Set `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_SCHEME=tls`, and a verified `MAIL_FROM_ADDRESS`. Do not assume the host or mailbox password. Test password reset, email verification and notification delivery; verify SPF, DKIM and DMARC for the sender domain.

### Google OAuth and Analytics / Search Console

- Create a Web OAuth client in Google Cloud.
- Add the exact authorized redirect URIs: `https://alomsenglishcenter.com/auth/google/callback` for sign-in and `https://alomsenglishcenter.com/admin/seo/google/callback` for reporting.
- Put the client ID and secret in `GOOGLE_CLIENT_ID` and `GOOGLE_CLIENT_SECRET`; keep the two redirect URI variables aligned with the Google Cloud console.
- Enable Google Analytics Data API and Search Console API, and authorize an account that has access to the correct GA4 property and verified Search Console property.
- After deployment, an Admin connects Google in Admin → SEO → Google Reports and sets the GA4 property ID and Search Console property URL there. OAuth credentials alone do not identify the properties or grant account access.

### MiMSMS and Gemini

- Keep `LIVE_CLASS_SMS_NOTIFICATIONS=false` until MiMSMS supplies the exact production endpoint, username, API key and approved sender name. Add those to the server `.env`; test one consented destination and verify delivery/credits before enabling SMS.
- Keep `AI_AGENTS_ENABLED=false` until a Gemini API key is created and the usage/billing limits are set. Store it only as `GEMINI_API_KEY` on the server. Enable the feature only after a successful restricted admin test.

Optional integrations must remain disabled when their credentials are absent. Do not add real secrets to this repository's example files.

## 4. First production database setup (NO demo seeding)

Run only after verifying the database name and completing a backup:

```bash
php artisan config:clear
php artisan migrate --force
php artisan storage:link
php artisan lms:admin:create
php artisan optimize
```

The `lms:admin:create` command prompts for the first admin name/email and a hidden password (minimum 14 characters). It creates an active, email-verified administrator. It does not print or store the password in source control.

**Do not run `php artisan db:seed` in production.** Demo seeders are now blocked in the production environment because they create public demo users with the shared `password` credential. The seeders remain available for local/test/staging demo databases. Never run `migrate:fresh`, `db:wipe`, `migrate:refresh` or `db:seed` against a live database.

## 5. cPanel Cron Jobs

Use the actual cPanel home directory, project path and PHP binary shown by your hosting account. Example only:

```cron
* * * * * cd /home/CPANEL_USER/path/to/project && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
* * * * * cd /home/CPANEL_USER/path/to/project && /usr/local/bin/php artisan queue:work database --queue=notifications,default --stop-when-empty --max-time=50 --tries=3 --timeout=60 >> storage/logs/queue-worker.log 2>&1
0 3 * * * cd /home/CPANEL_USER/path/to/project && /bin/bash backup.sh >> storage/logs/backup.log 2>&1
```

- **Scheduler:** the once-per-minute `schedule:run` triggers existing tasks: `system:backup --keep=14` at 02:00, Recycle Bin pruning at 02:30 and unpaid-order expiry at 03:00.
- **Queue:** the app uses the database queue. The worker must listen to `notifications,default` in that order; a worker listening only to `default` leaves newsletter jobs waiting. The example drains jobs and exits within the cron interval; `--timeout=60` stays below the default 90-second retry window. Confirm the host permits the command and inspect `storage/logs/queue-worker.log`. If jobs need longer, tune worker timeout and `DB_QUEUE_RETRY_AFTER` together.
- **Full backup:** `backup.sh` captures the database, private `.env`, public uploads and private uploads. It requires `mysqldump` for MySQL backups. Verify the script can execute and that the hosting shell has the required binary; if not, configure the host's supported database backup or phpMyAdmin export and a separate media backup.
- Confirm cron's timezone and backup retention against the hosting plan. The script keeps 30 timestamped snapshots; Laravel's database backup command has its own retention setting.

The local backup directory is not an off-site backup. Configure an encrypted off-server copy, restrict access to backup archives, and complete a restore drill into a separate database and directory.

## 6. Post-deploy checks and release gate

- Check `php artisan about`, `php artisan migrate:status`, `php artisan route:list` and `php artisan schedule:list`.
- Confirm the Vite manifest exists under `public/build/manifest.json` and the public pages load compiled CSS/JS.
- Test admin login, password reset, email verification, file uploads, course enrollment, order/payment verification, branch access, live-class schedule, queue processing and backup/restore.
- Confirm Google OAuth callbacks, Google reporting access, SMS provider response and Gemini response only after their credentials are installed.
- Verify `APP_ENV=production`, `APP_DEBUG=false`, correct HTTPS `APP_URL`, secure cookies, no public `.env`, and no publicly accessible backup archives.
- Confirm all required CI jobs pass on the exact release commit, staging acceptance is complete, and a rollback point exists before go-live.

No production deployment should be considered complete until the hosting account, real credentials, mail delivery, scheduled jobs, queue, backup restore and live-site smoke tests have been verified.

## Newsletter queue production runbook
Newsletter fan-out and recipient delivery jobs use the dedicated notifications queue. A worker listening only to default will leave campaigns queued. Prefer a persistent worker:

~~~
php artisan queue:work database --queue=notifications,default --sleep=3 --tries=3 --timeout=60
~~~

Restart long-running workers after deployments with `php artisan queue:restart`. If persistent workers are unsupported, use a non-overlapping cron drain only after confirming the PHP binary, project path, flock availability and writable lock/log paths:

~~~
* * * * * cd /home/CPANEL_USER/path/to/project && /usr/bin/flock -n storage/framework/newsletter-worker.lock /usr/local/bin/php artisan queue:work database --queue=notifications,default --stop-when-empty --max-time=50 --sleep=3 --tries=3 --timeout=60 >> storage/logs/newsletter-queue.log 2>&1
~~~

Keep worker timeout below the database queue retry_after (default 90 seconds). The scheduler is separate and does not process queued jobs. See deployment/newsletter-queue.md for staging acceptance, failure recovery, unsubscribe checks and operational sign-off.

## SEO, consent and conversion tracking acceptance
Configure GA4 Measurement ID, Meta Pixel ID and optional GTM ID in Admin → SEO & Marketing only after the production provider properties exist. Optional scripts are consent-gated. Test reject, analytics-only, marketing-only and accept-all choices. Verify PageView, Lead and fully verified Purchase in GA4 DebugView/Realtime and Meta Events Manager Test Events. Partial/unverified orders must not emit Purchase. Browser tracking does not include Meta Conversions API or guaranteed server/browser deduplication. See deployment/seo-pixel-conversion.md for provider setup, staging tests, production activation and rollback.
