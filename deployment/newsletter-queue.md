# Newsletter campaign queue operations

Newsletter campaigns use Laravel's database queue. Before sending campaigns in production:

1. Configure SMTP credentials in the production environment and verify ordinary application email delivery.
2. Set `QUEUE_CONNECTION=database` (the repository includes the `jobs` and `failed_jobs` migrations).
3. Keep a queue worker running for the `notifications` queue. The campaign fan-out job and individual recipient delivery jobs both use this queue.
4. Monitor the campaign's Sent, Failed and Skipped counters in Admin → Marketing → Newsletter Campaigns, and inspect failed queue jobs before retrying.

Example worker command from the Laravel project root:

```sh
php artisan queue:work database --queue=notifications --tries=3 --timeout=60
```

For cPanel hosting, configure the worker using the hosting provider's supported persistent process manager or cron-based queue-worker approach. Replace paths and PHP binary locations with the values supplied by the hosting account. Do not set the production queue connection to `sync`: the application blocks bulk newsletter campaign creation in production when synchronous queue mode is active.

Only subscribers with confirmed opt-in are included in a campaign snapshot. Each message contains a signed unsubscribe link. Subscribers who unsubscribe after the campaign is queued are skipped when their delivery job runs. Campaign messages are plain text; opens, clicks, bounces and provider delivery analytics are not currently measured.
