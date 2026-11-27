# cPanel production deployment checklist

This repository template is secret-free. Keep production .env files, API keys, SMTP passwords, database credentials and gateway credentials private.

## Before deployment
- Create a separate staging subdomain and MySQL database/user. Never test migrations or payment callbacks against production.
- Confirm PHP 8.3 and required extensions: OpenSSL, PDO MySQL, Mbstring, Tokenizer, XML, Ctype, JSON, BCMath, Fileinfo, GD and cURL.
- Set the web document root to Laravel public/. Do not expose .env, storage/, vendor/ or application source.
- Install production Composer dependencies and configure APP_ENV=production, APP_DEBUG=false, HTTPS APP_URL, secure session cookies, database and SMTP privately.
- Set QUEUE_CONNECTION=database and ensure queue/cache/session tables exist as required by the selected drivers.
- Configure a verified MAIL_FROM_ADDRESS and MAIL_FROM_NAME. Verify SPF, DKIM and DMARC.
- Keep SMS, AI and automated payment gateways disabled until credentials, consent, provider certification and end-to-end tests are complete.

## Staging release
From the Laravel project root:

~~~
composer install --no-dev --prefer-dist --optimize-autoloader
php artisan config:clear
php artisan migrate --force
php artisan db:seed --force
php artisan storage:link
php artisan optimize
php artisan route:list
~~~

Use a fresh staging database. Review seeded demo accounts and disable/remove demo users before public exposure. Never retain documented demo passwords in production.

## Frontend assets, favicon and CSP
- [ ] Build frontend assets during release using the repository's lockfile and build script (npm ci, then npm run build). Confirm public/build/manifest.json exists and assets load.
- [ ] Do not assume public/build/ must be committed: it is intentionally gitignored; the release pipeline or deployment process must generate and deploy it.
- [ ] The current assets partial falls back to the Tailwind CDN when the Vite manifest is absent. Treat this as a deployment failure for production; do not rely on the CDN fallback.
- [ ] Replace the zero-byte public/favicon.ico with a valid ICO file and verify the favicon route returns a non-empty image. The SVG favicon may remain as an additional format.
- [ ] ID-card QR generation currently loads QRCode.js from cdnjs.cloudflare.com. If production CSP disallows that host or third-party scripts, vendor and pin the library locally, update the view and test the QR rendering under the actual CSP.
- [ ] Define and test a CSP compatible with required local assets and approved third-party services. Do not enable an untested strict policy that breaks checkout, analytics consent, QR rendering or admin workflows.

## Queue worker and scheduler
Newsletter jobs use the dedicated notifications queue. A worker listening only to default will not send newsletter campaigns. Prefer a persistent worker:

~~~
php artisan queue:work database --queue=notifications,default --sleep=3 --tries=3 --timeout=60
~~~

If the host cannot keep persistent processes, use a non-overlapping one-minute cron worker, with paths confirmed in cPanel:

~~~
* * * * * cd /home/CPANEL_USER/path/to/project && /usr/bin/flock -n storage/framework/newsletter-worker.lock /usr/local/bin/php artisan queue:work database --queue=notifications,default --stop-when-empty --max-time=50 --sleep=3 --tries=3 --timeout=60 >> storage/logs/newsletter-queue.log 2>&1
~~~

The database queue retry_after defaults to 90 seconds; keep the worker timeout below it. Confirm PHP CLI path, flock, permissions and log rotation before enabling. See deployment/newsletter-queue.md.

Configure Laravel scheduler separately:

~~~
* * * * * cd /home/CPANEL_USER/path/to/project && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
~~~

The scheduler does not replace the queue worker. The app schedules daily backup retention, recycle-bin pruning and unpaid-order expiry. The repository backup script stores local backups under storage/backups; this alone is not encrypted off-site storage.

## SEO, Pixel and conversion tracking
Configure GA4 Measurement ID, Meta Pixel ID and optional GTM ID in Admin → SEO & Marketing only after production provider properties exist. Optional scripts are consent-gated. Test reject, analytics-only, marketing-only and accept-all choices. Verify PageView, Lead and fully verified Purchase in GA4 DebugView/Realtime and Meta Events Manager Test Events. Partial/unverified orders must not emit Purchase. Browser tracking does not include Meta Conversions API. See deployment/seo-pixel-conversion.md.

## Third-party credentials
- SMTP: verify password reset, email verification, newsletter confirmation and campaign delivery.
- MiMSMS: only enable LIVE_CLASS_SMS_NOTIFICATIONS after real credentials, sender ID, balance, delivery receipts and OTP flows are tested.
- Gemini AI: only enable AI_AGENTS_ENABLED after a private API key is set, quota/budget limits are configured and failures are handled.
- Google OAuth/GA4/GSC: configure client ID/secret privately and register exact production callback URLs in Google Cloud and the app. Verify OAuth consent, token refresh and each reporting API separately.
- Payment gateways: bKash production activation requires merchant onboarding, official credentials, callback/SNS configuration and provider certification. Nagad, Rocket, SSLCommerz and card acquiring remain unavailable until an adapter, routes, callback verification, reconciliation and tests exist. Do not unlock live mode by changing a flag alone.

## Backup and restore
- [ ] Configure encrypted off-server backups for database and uploaded files, separate from the web host.
- [ ] Restrict backup access and retention; never expose storage/app/private or backup archives publicly.
- [ ] Restore into a separate database and directory; verify login, uploaded files, course records, orders and payment ledger.
- [ ] Record restore owner, duration, recovery point and result. A successful backup command is not proof of recoverability.

## Production go-live gate
Go live only after required CI jobs pass without skipped required checks, staging browser acceptance is complete, migrations are clean, mail/queue/scheduler work, assets build, favicon and CSP are checked, consent tracking is verified, backups restore successfully, HTTPS/security review passes and a rollback point is documented. Do not claim production-ready while external credentials, provider certification or cPanel acceptance tests remain pending.