# Students & Staff Management

This module extends the existing users identity model instead of creating separate login accounts.

## Data model

- users: authentication, role, branch assignment, contact details and account enabled/disabled state.
- student_profiles: academic details, guardian contacts, quota information and academic status.
- staff_profiles: employee code, department, designation, employment dates, qualification, employment status and base salary.
- profile_documents: polymorphic private documents for a student/staff profile or a pending coaching admission.
- staff_attendances: daily staff attendance, check-in/out, status, branch and recording user.
- staff_payrolls: monthly salary ledger, allowances, deductions, net salary, approval and payment audit fields.

Existing student class attendance continues to use attendance_sessions and attendances. Staff attendance is a separate HR register; it does not silently deduct salary. Admin records deductions explicitly in each payroll draft.

## Admin workflows

- Students & Staff directory: role, branch, text and profile-status filters; create, edit, view and soft-delete.
- Student profile: academic data, guardian contact, quota and private document upload/verification.
- Staff profile: employment data, qualification, base salary, document verification, attendance history and payroll history.
- Admission: applicant academic details, guardian information, quota, photo and supporting documents. Uploaded files remain private. Admin must approve all submitted documents before approving the admission. Approved admission details and documents are transferred to the linked student profile.
- Staff attendance: daily register with present, absent, late, leave, half-day and remote statuses. Re-saving the same staff/date updates the existing record.
- Payroll: one record per staff profile and month; draft → approved → paid. Payment date, method, reference and approving/paying admin are retained. Approved/paid entries cannot be deleted; only drafts can be deleted.

## Branch manager scope

Branch managers can create, edit and soft-delete students and teachers assigned to their own branch. The branch ID and role are set server-side; submitted role/branch overrides are rejected. Cross-branch records return not found. Payroll and document verification remain Admin-only.

## Private file handling

Profile and admission documents use Laravel's local disk, not the public web disk. They are served only through authenticated Admin download routes after ownership checks. Accepted formats are PDF/JPG/JPEG/PNG/WebP. Admission student photos are limited to 5 MB; other documents are limited to 10 MB.

Ensure the server's storage/app/private area is not exposed by the web server and that Laravel storage permissions allow the application user to read/write files. Do not copy these documents into public/.

## Deployment

After merging, back up the database and private storage, deploy the code, then run:

    php artisan migrate --force
    php artisan optimize:clear
    php artisan config:cache

No production migration or deployment is performed by this PR. Confirm APP_ENV=production, APP_DEBUG=false, HTTPS, private storage permissions and backups before applying migrations to the live site.
