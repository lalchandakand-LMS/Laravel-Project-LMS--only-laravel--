# Alom's English Center — Custom LMS (Laravel 12)

A full-featured Learning Management System foundation for Alom's English Center, with courses, curriculum builder, video player, MCQ/CQ exams,
e-books, cart and manual bKash/Nagad payment verification, certificates, reviews and role-based management. Live payment-gateway activation and production deployment remain separate release tasks.

Built with **Laravel 12 + Blade + Tailwind CSS**. The compiled Vite bundle is
already shipped in `public/build`, so it runs without `npm` (Tailwind falls back
to the CDN if you delete that folder).

---

## 1. Requirements

| Requirement | Version |
|---|---|
| PHP | 8.2+ (with `pdo_sqlite` / `pdo_mysql`, `fileinfo`, `mbstring`, `openssl`) |
| Composer | 2.x |
| Database | MySQL 8 / MariaDB 10.4+ **or** SQLite (default) |

## 2. Installation

```bash
# 1. dependencies
composer install

# 2. environment
cp .env.example .env
php artisan key:generate

# 3. database — SQLite (already configured in .env.example)
touch database/database.sqlite
#    …or MySQL: set DB_CONNECTION=mysql + DB_DATABASE/DB_USERNAME/DB_PASSWORD in .env

# 4. schema + demo data
php artisan migrate --seed

# 5. public storage symlink (course thumbnails, e-book PDFs, avatars)
php artisan storage:link

# 6. run
php artisan serve
```

Open <http://127.0.0.1:8000>.

> **CI verification:** the current GitHub Actions run on 26 November 2026 passed
> the full Laravel suite with 256 tests / 1,383 assertions (4 tests marked risky).
> CI validates the application in a clean SQLite test environment; still run a
> staging install and acceptance test before production. The writable directories
> (`storage/framework/{cache,sessions,views}`, `storage/logs`,
> `storage/app/public`, `bootstrap/cache`) ship with `.gitignore` placeholders;
> if your unzip/mover drops empty folders, recreate them with
> `mkdir -p storage/framework/{cache/data,sessions,views} storage/logs storage/app/public bootstrap/cache`
> before serving, otherwise Blade/session writes fail with HTTP 500.

### Updating an existing install (no data loss)

```bash
./update.sh              # backup DB + .env -> maintenance mode -> composer ->
                         # php artisan migrate --force -> rebuild caches -> up
./update.sh --dry-run    # show every step without changing anything
./backup.sh              # database + .env only (safe for cron)
```

`migrate` only applies **new** migration files; every migration in this project
is additive, so existing users/orders/enrolments are untouched. Never run
`migrate:fresh`, `db:wipe`, `migrate:refresh` or `db:seed` on a live database.
Full step-by-step (VPS, cPanel, manual update, rollback, restore, backup cron)
lives in **[UPDATE.md](UPDATE.md)**.

### Demo accounts (created by the seeder)

| Role | Email | Password (demo only) | Lands on |
|---|---|---|---|
| Admin | `admin@lms.test` | `password` | `/admin` |
| Instructor / teacher | `teacher1@lms.test` | `password` | `/teacher` |
| Student | `student@lms.test` | `password` | `/my` |
| Guardian | `guardian1@lms.test` | `password` | `/guardian` |
| Branch manager | `manager1@lms.test` | `password` | `/branch` |

> These are demo credentials only. Delete or rotate every seeded account before
> exposing the site publicly; never leave the shared `password` password in production.
> Each role is redirected to its own dashboard automatically
> and blocked from the others by `RoleMiddleware`; disabled users are logged out.

## 3. What's included

### Public site
- **Home** – admin-controlled visibility for hero, learning paths, featured courses, online batches, stats, latest courses, live classes, exams, teachers, board results, reviews, e-books and admission CTA; hero slides and content records are database-driven.
- **Course catalogue** – search, category filter, level filter, sort by
  newest / popular / rating / price, pagination.
- **Course landing page** – outcomes, description, expandable curriculum with
  free-preview badges, live-class list, exam list, reviews, sticky buy box with
  progress bar for enrolled students.
- **E-books store** – cover grid, detail page, in-browser PDF reader.
- **Certificate verification** – public `/verify/{code}` page.
- **Cart & checkout** – session cart (courses + e-books), coupon codes,
  order summary, billing form, free-order handling.
- **Manual mobile-banking payment** – bKash / Nagad / Rocket / bank, student
  submits transaction ID, admin verifies → access granted instantly.

### Student area (`/my`)
- Dashboard: stats, "continue learning", progress bars, upcoming exams, activity.
- **Video course player** – YouTube/Vimeo/mp4 embed, lesson notes, attachments,
  sidebar curriculum with completion ticks, mark-complete, prev/next,
  announcements, live progress %.
- **Exam engine** – timed attempts, autosave every 15 s, question navigator,
  resume unfinished attempt, negative marking, auto-grading, per-question review
  with explanations, pass/fail, attempt limits.
- **Assignments** – view course assignments, submit text or private file uploads,
  receive teacher feedback/grades, and revise only when a teacher returns work.
- **Live class schedule** – eligible enrolled students see published sessions;
  meeting links/passwords unlock only inside the join window and recordings
  become available after the session window.
- My courses, orders, e-books, certificates (printable), profile & password.
- **AI Agent Workspace** – optional Gemini-powered Student, Teacher, Admin,
  Support and Learning agents. Teacher assignment drafts require human approval
  and are created unpublished. See [AI Agent Setup](docs/AI_AGENT_SETUP.md).

### Multi-role organisation layer
- **Branches** (`/admin/branches`) – CRUD, activate/close, per-branch student &
  user counts, revenue roll-up, branch-level student roster.
- **Home slider** (`/admin/sliders`) – CRUD, image upload or URL, ordering,
  show/hide; the public hero renders active slides with autoplay dots/arrows. Homepage section visibility and carousel/grid preferences are managed in Site Settings.
- **Live classes** (`/admin/live-classes`) – schedule per course/branch, meeting
  link, start/end time, join button inside the student dashboard.
- **Teacher portal** (`/teacher`) – my courses, my students, upcoming live
  classes, exam results, and staff leave requests with overlap protection.
- **Guardian portal** (`/guardian`) – linked children, per-child attendance,
  results, exam history and enrolled courses (read-only).
- **Branch manager portal** (`/branch`) – students, staff, enrolments, revenue
  and live classes **scoped to the manager's own branch** only.

### Admin / instructor panel (`/admin`)
- Dashboard: revenue, students, enrollments, pending orders, 6-month revenue
  chart, top courses, recent orders/enrollments, pending-review queue. Admin also manages branch admissions, attendance review, branch finance, staff payroll, backups, activity logs, notifications, media, menus and site customization.
- **Courses** – full CRUD, thumbnail upload, outcomes, pricing/discount,
  publish & feature flags, certificate toggle, SEO fields.
- **Curriculum builder** – sections and lessons inline (video, text, PDF, live,
  exam types), free-preview flag, attachments.
- **Exams** – CRUD, schedule window, duration, negative marking, attempt limit,
  shuffle; **question builder** for MCQ (4 options + correct answer, explanation)
  and descriptive CQ/short questions.
- Categories, e-books (cover + PDF upload), announcements, coupons,
  enrollments (manual enrol / remove), students & staff (roles, activate,
  disable, password reset), staff leave approval/rejection with reviewer notes,
  orders (status change, payment verification), review moderation, site settings
  (branding, contact, bKash/Nagad numbers).
- Role protection: instructors only see/manage **their own** courses,
  students can never reach `/admin` (403).

### Architecture

```
app/
├── Http/Controllers/
│   ├── Auth/AuthController.php          login / register / logout
│   ├── HomeController.php               home page
│   ├── CourseController.php             catalogue, detail, free enrol
│   ├── EbookController.php              store + reader
│   ├── CartController.php               session cart, coupons, totals
│   ├── CheckoutController.php           orders + manual payment
│   ├── ReviewController.php             course reviews
│   ├── Student/                         dashboard, player, exams, certificate
│   ├── Teacher/                         teacher portal (own courses/students)
│   ├── Guardian/                        guardian portal (linked children)
│   ├── Branch/                          branch-scoped manager portal
│   └── Admin/                           full back-office (20+ controllers)
├── Models/                              Eloquent domain models (see app/Models/)
├── Services/OrderService.php            fulfilment + certificate issuing
└── Http/Middleware/RoleMiddleware.php   role:admin,instructor guard
```

Database schema is maintained in the ordered migration files under `database/migrations/`. It now covers the LMS core, commerce and payment verification, branches, batches, attendance, admissions, assignments, notifications/messages, profile documents, payroll, media, blog, AI-agent audit records and supporting workflows. Do not rely on a fixed migration count; apply new migrations with `php artisan migrate --force` during a backed-up deployment.

## 4. Tests

```bash
php artisan test            # full Unit + Feature suite
```

The current GitHub Actions validation (26 November 2026) passed 256 tests / 1,383 assertions, with 4 tests reported as risky. Coverage includes course and curriculum workflows, payments and refunds, eligibility/access control, exams, assignments, live classes, admissions, branches, attendance, finance, notifications, media, recycle-bin recovery, profile documents, payroll, AI-agent approval/audit, site settings and homepage behavior. Automated CI is necessary but not sufficient: complete browser-based responsive, accessibility, payment-provider sandbox and cPanel staging checks before launch.

The suite is written to be deterministic: the seeder randomises enrollments and
branch assignment, so tests resolve their fixtures through explicit queries
(courses the student does not own, students outside the guardian's children,
students outside the manager's branch) instead of relying on seed order.

## 5. Production notes

1. **Tailwind**: the production bundle (`public/build`) is included, so
   `@vite()` is used out of the box. To restyle, edit the Blade files and run
   `npm install && npm run build`. Delete `public/build` and the layout falls
   back to the Tailwind CDN (`resources/views/partials/assets.blade.php`).
2. **Payments**: the live application currently supports manual payment submission and staff verification; it does not yet have a live bKash/Nagad provider adapter. See [Payment gateway integration contract](docs/PAYMENT_GATEWAY_INTEGRATION.md). Provider credentials, signature verification, idempotent callbacks, reconciliation and sandbox tests are required before enabling a real gateway. Do not treat a browser redirect or transaction screenshot as settlement proof.
3. **Media**: use a CDN or S3 by changing `FILESYSTEM_DISK` and the `->store(..., 'public')`
   calls to your disk; YouTube/Vimeo URLs are already supported for videos.
4. Set `APP_ENV=production`, `APP_DEBUG=false`, run `php artisan config:cache`
   `route:cache` `view:cache`, and point the web root at `public/`.
5. Seed data uses Unsplash course photos, Unsplash slider images and
   `ui-avatars.com` avatars; upload real thumbnails in the admin panel to
   replace them.
6. Routes: run `php artisan route:list` for the current named route map; route count changes as modules evolve.

## 6. Recycle Bin & safe system upgrades

- Admins can recover deleted users, courses, categories, curriculum sections/lessons, exams/questions, e-books, live classes, announcements, reviews, sliders, menu items, coupons and branches from Admin → System → Recycle Bin.
- Deletion is soft-delete based; the original record ID and linked learning history remain intact. The audit field records who deleted an item and when.
- Restore is available per item and in batches for one selected record type. A restore that conflicts with a unique email/slug/code is safely skipped and reported.
- Permanent deletion is guarded. Accounts, courses, lessons, exams, questions, e-books and branches cannot be purged while protected relationships exist; course/ebook order history, enrolments, certificates, exam attempts and progress are preserved. Orders and payments are not part of the Recycle Bin.
- The updater verifies backups of the database, .env, and uploaded public media before changing the application. It only runs additive Laravel migrations; it never runs destructive fresh/wipe/seed commands.
- If a critical update step fails, the updater intentionally leaves the site in maintenance mode instead of exposing a partially upgraded application. Restore the verified backups and investigate before bringing the site online.


## Media Library and image / document assets

- Admin → System → Media library is the central catalogue for reusable public images and PDFs. Admins can upload JPG, PNG, WebP, GIF and PDF files (up to 25 MB), add external HTTP/HTTPS links, preview images, copy the public URL, filter, and remove library items.
- Site logo, dashboard banner, slider background, course thumbnail, e-book cover, and user profile photo accept either a local upload or an external HTTP/HTTPS URL.
- Lesson attachments, teacher assignment reference files, and e-book PDF/EPUB files accept uploads or trusted hosted URLs. Remote lesson/assignment resources and e-book files are only redirected to after the existing lesson, enrollment, or e-book purchase authorization check.
- Student assignment submissions and payment/admission evidence remain private uploads; they are not converted into public media links.
- Uploaded public assets are stored on Laravel's `public` disk. After deployment, ensure the public storage symlink exists (`php artisan storage:link`) and that `storage/app/public` is writable by the web server.
- Paid lesson files, assignment submissions, and uploaded e-book files continue to use private local storage and their protected download routes. Do not move those files into the public Media Library.
