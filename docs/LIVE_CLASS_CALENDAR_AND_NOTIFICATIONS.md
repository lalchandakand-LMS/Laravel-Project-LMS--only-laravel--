# Live class calendar and notification delivery

## Calendar views

- Students can switch between the existing live-class list and a month-by-month calendar at /my/live-classes. The calendar uses the same course, batch, branch and enrollment eligibility rules as the list.
- Teachers can switch between list and month calendar at /teacher/live-classes. The teacher calendar is limited to courses assigned to the signed-in instructor (administrators retain the existing broader scope).
- Previous/next month navigation is available in both portals. Calendar views show cancellation/status labels and batch/course-wide targeting.

## In-app notifications

Database notifications remain enabled for scheduled, cancelled and rescheduled live classes. They are delivered through the existing Laravel notification center and do not require a third-party account.

## Email delivery (SMTP)

Email is opt-in and disabled by default. To enable it on the production server:

1. Configure the server .env with the SMTP details supplied by your email/hosting provider:
   - MAIL_MAILER=smtp
   - MAIL_SCHEME=tls (or the scheme/port combination supplied by the provider)
   - MAIL_HOST=..., MAIL_PORT=587
   - MAIL_USERNAME=..., MAIL_PASSWORD=...
   - MAIL_FROM_ADDRESS=..., MAIL_FROM_NAME="Alom's English Center"
2. Set LIVE_CLASS_EMAIL_NOTIFICATIONS=true.
3. Run php artisan config:clear && php artisan config:cache.
4. Send a test notification to a mailbox you control before enabling this for all students.

The application deliberately refuses to add the email channel while Laravel is using the log or array mailer. No real SMTP credentials are included in source control, and SMTP delivery has not been tested against the account's production mail server.

## SMS delivery (MiMSMS Bangladesh)

The application includes an opt-in MiMSMS transactional SMS adapter. It sends one recipient per API request using the provider's documented POST https://api.mimsms.com/api/V2/SMS endpoint and validates both the HTTP response and provider statusCode / status fields. It normalizes local Bangladesh numbers (01..., 8801..., +8801...) to 8801XXXXXXXXX.

Before enabling SMS:

1. Create/activate a MiMSMS account and activate its API key in the provider's Developer settings.
2. Whitelist the production server IP address and domain name in the provider panel.
3. Obtain an approved Sender ID (or permitted sender number).
4. Add the following server-only values to .env:
   - LIVE_CLASS_SMS_NOTIFICATIONS=true
   - LIVE_CLASS_SMS_PROVIDER=mimsms
   - MIMSMS_API_ENDPOINT=https://api.mimsms.com/api/V2/SMS
   - MIMSMS_USERNAME=... (the account login email)
   - MIMSMS_API_KEY=...
   - MIMSMS_SENDER_NAME=... (the sender approved for the account)
5. Run php artisan config:clear && php artisan config:cache.
6. Keep QUEUE_CONNECTION=database and run the notification queue worker.

### cPanel queue worker

The SMS dispatch is queued on the notifications queue, with up to 3 attempts and backoff delays. Add a cPanel Cron Job to process pending work each minute (replace the path with the actual Laravel application path):

    * * * * * cd /home/CPANEL_USER/public_html && php artisan queue:work database --queue=notifications,default --stop-when-empty --tries=3 --timeout=30 >> /dev/null 2>&1

The existing jobs and failed_jobs tables are part of the Laravel migrations. Check php artisan queue:failed for exhausted jobs and use php artisan queue:retry all after correcting the cause.

MiMSMS transactional requests accept one recipient at a time. The job logs the provider tracking ID and retries temporary failures. A provider API success means the request was accepted by the gateway; it is not proof that the handset received the message. Confirm handset delivery in the provider delivery report/panel. Because the transactional API does not document an idempotency key, a network timeout after provider acceptance can theoretically lead to a duplicate when a job retries.

## Security and deployment notes

- Never commit SMTP passwords, SMS API keys, or sender credentials to GitHub.
- Do not paste production secrets into chat or issue comments. Add them only to the production .env file with restricted file permissions.
- Email and SMS remain disabled until the server owner explicitly sets their respective flags to true.
- After changing environment values on cPanel, clear and rebuild Laravel's configuration cache.
- No production credentials were available to the development environment, so live SMTP/SMS delivery and handset receipt must be verified after the credentials and provider account are configured.
