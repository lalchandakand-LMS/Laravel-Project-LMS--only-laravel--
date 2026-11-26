# Live class calendar and email notifications

## Calendar views

- Students can switch between the existing live-class list and a month-by-month calendar at `/my/live-classes`. The calendar uses the same course, batch, branch and enrollment eligibility rules as the list.
- Teachers can switch between list and month calendar at `/teacher/live-classes`. The teacher calendar is limited to courses assigned to the signed-in instructor (administrators retain the existing broader scope).
- Previous/next month navigation is available in both portals. Calendar views show cancellation/status labels and batch/course-wide targeting.

## Optional email notifications

Live-class changes continue to create in-app database notifications. Email copies for scheduled, cancelled and rescheduled classes are optional and disabled by default.

1. Configure a working Laravel mailer in the server `.env` (for example `MAIL_MAILER=smtp`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`, and `MAIL_FROM_NAME`).
2. Set `LIVE_CLASS_EMAIL_NOTIFICATIONS=true`.
3. Run `php artisan config:clear && php artisan config:cache`.

Only eligible active students with an email address are notified by the existing live-class recipient resolver. Email delivery has not been verified against a real SMTP provider in CI; use a test mailbox before enabling it in production.

## SMS status

SMS delivery is not enabled. No SMS provider, credentials, sender ID or delivery webhook has been configured for this repository. Do not enable SMS by adding a generic endpoint; integrate and test a chosen provider (for example a Bangladesh-supported gateway) with server-side credentials and delivery/error handling first.
