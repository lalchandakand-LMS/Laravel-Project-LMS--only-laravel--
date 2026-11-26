# Identity cards, phone verification and payroll reporting

## QR identity cards

- Admins can print a Student or Staff identity card from Admin → Students & Staff → profile → Print ID card.
- Students can open their card from My Profile. Teachers and branch managers can open their own card from their portal dashboard.
- Each card uses a cryptographically random, unguessable verification token stored on the associated profile.
- The QR image is rendered in the user's browser. The QR contains only the verification URL; it does not encode private contact details or uploaded documents.
- The public verification page shows only the holder's name, role, ID code, branch, profile status and active-account state. Disabled accounts return 404.
- The QR renderer currently loads QRCode.js from cdnjs; keep the URL pinned and consider vendoring the script locally if the production Content Security Policy blocks third-party scripts.

## Bangladesh mobile OTP verification

- A signed-in user saves their phone number in their profile, then requests a six-digit SMS OTP.
- The existing MiMSMS transactional gateway sends the OTP to the normalized Bangladesh mobile number (8801XXXXXXXXX).
- OTPs are stored as password hashes in the configured Laravel cache, expire after five minutes and allow at most five verification attempts.
- The send endpoint is throttled to three requests per ten minutes; verification is throttled to ten requests per ten minutes in addition to the five-attempt challenge limit.
- Changing the saved phone number clears phone_verified_at. Admin and Branch Manager phone edits also clear the verification state.
- SMS delivery requires the existing LIVE_CLASS_SMS / MIMSMS credentials in the environment. Delivery remains unavailable until the gateway is configured. No credentials are committed.
- This adds user-controlled verification but does not force OTP at login or block LMS access when SMS is not configured.

## Staff payroll reports

- Admin → Staff Payroll → Payroll Reports supports month range, branch and status filters.
- Summary cards show record count, basic salary, allowances, deductions and net payroll totals.
- Monthly and staff-wise summaries are available on screen.
- CSV export respects the same filters and includes staff, branch, month, salary components, payment status and payment reference. The UTF-8 BOM supports Excel's Bangla text handling.

## Deployment notes

After merging, back up the database and run the additive migration:

    php artisan migrate --force
    php artisan optimize:clear
    php artisan config:cache

Before enabling OTP in production, confirm the MiMSMS transactional SMS endpoint, sender name, credentials and sufficient SMS balance. Test delivery with a staff-owned phone first. The QR code requires the QRCode.js CDN to be reachable; if the site uses a strict Content Security Policy, allow the pinned cdnjs script host or vendor the library locally.
