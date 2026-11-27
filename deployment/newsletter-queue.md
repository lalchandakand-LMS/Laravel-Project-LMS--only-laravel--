# Newsletter campaign queue — production operations

Newsletter fan-out and recipient delivery jobs are dispatched to the dedicated notifications queue. A worker listening only to default will leave campaigns queued. The app uses Laravel's database queue by default.

## Production prerequisites

- [ ] Configure a real SMTP provider and verified sender address/name.
- [ ] Publish and verify SPF, DKIM and DMARC for the sending domain with the provider.
- [ ] Set QUEUE_CONNECTION=database in the private production environment; never use sync for bulk campaigns.
- [ ] Run migrations and confirm jobs and failed_jobs tables exist.
- [ ] Confirm config/queue.php database retry_after (default 90 seconds) and keep worker timeout below it (60 seconds).
- [ ] Confirm storage/logs and storage/framework are writable by the cPanel account.
- [ ] Confirm provider rate limits, daily sending limits, list consent and unsubscribe policy.

Do not commit SMTP passwords, production .env files, API tokens or subscriber exports.

## Preferred persistent worker

From the Laravel project root, configure the hosting-supported process manager to keep this worker alive:

~~~
php artisan queue:work database --queue=notifications,default --sleep=3 --tries=3 --timeout=60
~~~

Include notifications first. Restart long-running workers after deployments:

~~~
php artisan queue:restart
~~~

A scheduler cron does not process queues; the worker must be running separately.

## cPanel cron fallback (only if persistent workers are unsupported)

Use a once-per-minute, non-overlapping worker that drains available jobs and exits. Replace the home directory, project path, PHP binary and flock path with values confirmed on the actual hosting account:

~~~
* * * * * cd /home/CPANEL_USER/path/to/project && /usr/bin/flock -n storage/framework/newsletter-worker.lock /usr/local/bin/php artisan queue:work database --queue=notifications,default --stop-when-empty --max-time=50 --sleep=3 --tries=3 --timeout=60 >> storage/logs/newsletter-queue.log 2>&1
~~~

Before enabling:
1. Confirm PHP CLI path, project path and flock availability in cPanel Terminal or with the host.
2. Confirm the cPanel account can write the lock and log paths.
3. Run the command manually once and verify it exits when the queue is empty.
4. Confirm a queued test campaign is processed from notifications.
5. If flock is unavailable, ask the host for a supported non-overlapping worker strategy; do not blindly remove locking where a previous run may overlap the next minute.

## Scheduler (separate from the queue worker)

Configure Laravel's scheduler once per minute, with actual hosting paths:

~~~
* * * * * cd /home/CPANEL_USER/path/to/project && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
~~~

The scheduler runs scheduled application maintenance; it does not send queued newsletter jobs unless a queue worker/cron drain is also configured.

## End-to-end staging test

Use a separate staging database and internal test mailboxes:
- [ ] Subscribe an internal address and verify the confirmation email arrives. Confirmation email currently uses the configured mail transport directly; this test also validates SMTP.
- [ ] Confirm the subscription link and verify the subscriber is marked subscribed.
- [ ] Create a small campaign and explicitly dispatch it.
- [ ] Verify campaign status progresses queued → sending → sent (or completed_with_errors).
- [ ] Verify only confirmed subscribers receive a message; pending and unsubscribed addresses do not.
- [ ] Verify each message contains a valid signed unsubscribe link.
- [ ] Unsubscribe one test address before its pending delivery executes; verify the delivery is skipped.
- [ ] Test a controlled provider failure in staging; verify delivery/campaign failure counts and failed_jobs visibility.
- [ ] Restart the worker and verify it resumes queued notifications.
- [ ] Check logs for secrets or unnecessary subscriber personal data.

## What is and is not tracked

Current campaign delivery records track sent, failed and skipped counts. Messages are plain text and include a signed unsubscribe link. The application does not currently measure opens, clicks, bounces, complaints or provider delivery analytics. Do not advertise those metrics as available. Implementing them requires provider webhooks/events, signed tracking links/pixels, privacy review, idempotent event ingestion and tests.

Do not blindly run queue:retry all for newsletter deliveries. Inspect the failed job and delivery state first. Failed delivery rows require an explicit recovery procedure that rechecks subscriber status and duplicate-send risk; the current admin UI does not provide a per-recipient retry action.

## Monitoring and stop procedure

Inspect Admin → Marketing → Newsletter Campaigns for Sent, Failed and Skipped counters. Use:

~~~
php artisan queue:failed
php artisan queue:work database --queue=notifications,default --once
~~~

If complaints, bounces or unexpected volume spike:
1. Stop the persistent worker or disable the cron entry.
2. Preserve pending jobs and delivery rows for investigation; do not delete them as the first response.
3. Review consent, recipient statuses, content, sender authentication and provider throttling.
4. Resume only after a small internal test and provider confirmation.

## Production sign-off

| Check | Status |
|---|---|
| SMTP authentication (SPF/DKIM/DMARC) verified | Pending |
| Test confirmation and campaign emails delivered | Pending |
| Database queue migrations present | Pending |
| Worker listens to notifications,default | Pending |
| Persistent process or non-overlapping cron verified | Pending |
| Unsubscribed queued recipient skipped | Pending |
| Failure inspection/recovery owner assigned | Pending |
| Provider limits and consent policy approved | Pending |
| Opens/clicks/bounces analytics | Not implemented |

Do not send a real campaign until every required operational check is signed off.