# HourWise App Guide
![HourWise Social Card](assets/img/hourwise-social-card.jpg)

## Overview
HourWise is a simple, but powerful, time tracking and timesheet production app for freelancers who want a straightforward, reliable way to see where their time goes and generate reports.

**Core features:**
- User authentication (register/login/logout)
- Email verification for self-registered accounts
- Optional two-factor authentication with recovery codes
- Password reset by email (PHPMailer; SMTP optional but recommended)
- First-run browser setup for database, SMTP, app settings, migrations, and initial admin account
- Admin settings (admin-only): SMTP config, registration access toggle, and read-only diagnostics
- Client/Project/Category management
- Start/stop timers and edit entries
- Dashboard summaries and charts
- CSV export
- Account management (profile, avatar, password, delete account)
- Per-user timezone support

**Security and operations features:**
- External secrets loaded from `../secrets/*`, outside the web root
- CSRF protection on state-changing POST actions
- DB-backed login and password-reset throttling
- Hashed email-verification and password-reset tokens
- Optional TOTP two-factor authentication for all users
- Session ID and CSRF token rotation after sensitive auth/account events
- Apache/LiteSpeed security header defaults in `.htaccess`
- Avatar upload validation and upload-directory execution protections
- Application and audit logs written outside the web root

## Tech Stack
- PHP 8.3, 8.4, and 8.5 compatible (plain PHP pages, no framework)
- MySQL (via PDO)
- Bootstrap 5 (self-hosted vendor files)
- Font Awesome (self-hosted vendor files)
- Chart.js (local vendor file): `/assets/vendor/chartjs/chart.umd.min.js`
- QRCode.js (local vendor file): `/assets/vendor/qrcodejs/qrcode.min.js`

## App Structure
- `middleware.php`: bootstraps config, helpers, session, DB.
- `config.php`: runtime config loaded from external secrets.
- `db.php`: PDO connection.
- `.htaccess`: Apache/LiteSpeed defaults for 404 handling and baseline security headers.
- `helpers.php`: shared auth, CSRF, rate limiting, timezone, formatting, mail, logging, account delete, and utility functions.
- `auth/*.php`: login/register/forgot/reset/logout.
- `dashboard.php`: weekly totals, donut + trend charts, quick timer actions.
- `track.php`: running timers and start/stop.
- `entries.php`, `entries_ajax.php`, `entry_edit.php`: listing/filtering/loading/editing/exporting entries.
- `clients.php`, `projects.php`, `categories.php` (+ edit pages): management CRUD.
- `account.php`: profile, timezone, password, avatar, delete account modal.
- `admin_settings.php`: admin-only mail, registration, and diagnostics view.
- `setup.php`: first-run setup wizard.
- `migrations/*.sql`: schema changes and incremental feature/security updates.

## Migration Overview
The repository currently includes these SQL migrations:
- `migrations/0001_initial_schema.sql`: creates the core tables for users, clients, projects, categories, time records, password reset tokens, and legacy compatibility tables.
- `migrations/2026-06-01_add_users_timezone.sql`: adds the per-user timezone column.
- `migrations/2026-06-02_add_email_verification.sql`: adds email verification support and the token table.
- `migrations/2026-06-02_add_pending_email_verification.sql`: adds pending-email support so address changes can be verified without locking out the old login email.
- `migrations/2026-06-02_add_two_factor_auth.sql`: adds the two-factor auth table.
- `migrations/2026-06-02_add_auth_rate_limits.sql`: adds the auth rate limiting table used by login, password reset, and 2FA flows.

## Request Flow
1. Page includes `middleware.php`.
2. `middleware.php` loads:
   - `config.php`
   - `helpers.php`
   - session via `start_session(...)`
   - DB via `db.php`
3. Protected pages call `require_login()`.
4. Page logic runs queries and renders HTML.

## Data Model (inferred from code)
- `users` (includes `timezone`, `avatar_path`, `pending_email`, password hash)
- `clients`
- `projects` (belongs to client + user)
- `work_categories`
- `time_records`
- `password_reset_tokens`
- `email_verification_tokens`
- `user_two_factor`
- `auth_rate_limits`
- legacy/optional tables referenced by code: `jobs`, `report_links`

## Timezone Behavior
- App default timezone is in config: `config.php -> app.timezone`.
- Each user has `users.timezone` (IANA value, e.g. `Europe/London`).
- Session stores timezone through `set_user_session(...)`.
- Conversions use helpers:
  - `user_timezone()`
  - `user_timezone_object()`
  - `formatLocalTime(...)`
  - `formatLocalTimeRecentEntries(...)`

## Secrets Implementation
Yes, this app uses external secrets files and does not store sensitive credentials in repo code.

`config.php` structure:
```php
return [
    'db' => [
        'dsn' => '',
        'user' => '',
        'pass' => '',
    ],
    'app' => [
        'base_url' => '',
        'timezone' => 'America/New_York',
        'allow_registration' => false,
        'session_name' => 'tt_sess',
        'session_secure' => true,
        'session_lifetime' => 60 * 60 * 24 * 7,
    ],
    'mail' => [
        'phpmailer_path' => __DIR__ . '/lib/PHPMailer',
        'host' => '',
        'username' => '',
        'password' => '',
        'port' => 465,
        'encryption' => 'ssl',
        'from_email' => '',
        'from_name' => 'HourWise',
    ],
    'auth' => [
        'password_reset_expires_minutes' => 60,
        'email_verification_expires_minutes' => 1440,
    ],
    'setup' => [
        'enabled' => false,
    ],
];
```

`config.php` loads:
- `../secrets/db_credentials.php`
- `../secrets/email_secret.php`
- `../secrets/app_secret.php`

Expected shape:

`../secrets/db_credentials.php`
```php
<?php
return [
  'dsn' => 'mysql:host=...;dbname=...;charset=utf8mb4',
  'user' => '...',
  'pass' => '...',
];
```

`../secrets/email_secret.php`
```php
<?php
return [
  'CRM_SMTP_HOST' => '...',
  'CRM_SMTP_USER' => '...',
  'CRM_SMTP_PASS' => '...',
  'CRM_SMTP_PORT' => 465,
  'CRM_FROM_EMAIL' => '...',
  'CRM_FROM_NAME' => 'HourWise',
];
```

`../secrets/app_secret.php`
```php
<?php
return [
  'APP_BASE_URL' => 'https://time.example.com',
  'APP_TIMEZONE' => 'America/New_York',
  'APP_SESSION_SECURE' => true,
  'APP_ALLOW_REGISTRATION' => false,
];
```

Important:
- Keep `secrets/` outside web root and out of version control.
- Ensure filesystem permissions restrict read access.
- Rotate SMTP/DB credentials if exposed.

Runtime logs are also stored outside the web root:
- `../secrets/logs/app.log`: application exception details as JSON lines.
- `../secrets/logs/audit.log`: security/audit events as JSON lines.

## Security Hardening Status
See [SECURITY-HARDENING.md](SECURITY-HARDENING.md) for the working checklist and remaining release-readiness tasks.

**Current positive foundations:**
- Runtime credentials are loaded from `../secrets/*` instead of hardcoded in repo files.
- PDO prepared statements are used throughout the main data access paths.
- CSRF checks protect state-changing POST forms.
- Request-controlled redirects are constrained to known local app paths.
- Login and forgot-password flows use persistent DB-backed rate limiting.
- Self-registered accounts must verify their email address before login.
- Users can enable TOTP-based two-factor authentication and recovery codes.
- Session cookies use `HttpOnly` and `SameSite=Lax`; production should keep `APP_SESSION_SECURE = true`.
- Session IDs and CSRF tokens rotate after login, first-run setup login, password change, email change, and account deletion.
- Password reset tokens are stored as hashes.
- Email verification tokens are stored as hashes.
- Password reset tokens are invalidated after password changes.
- Apache/LiteSpeed `.htaccess` defaults provide baseline security headers where supported.
- Uploaded avatars use server-side MIME checks, image decode checks, dimension limits, randomized filenames, and non-executable permissions.
- Runtime avatar uploads are ignored by Git.
- User-facing errors avoid raw exception details; app/audit logs are written outside the web root.

**Remaining operational work to track:**
- Add CI checks for PHP syntax linting once PHP is available in the build environment.
- Document supported PHP and MySQL versions.
- Confirm backup automation and test restore.
- Decide whether to configure production HSTS at the host/CDN layer.

## First-Time Setup Checklist
1. Create an empty MySQL database.
2. Apply migrations in `migrations/`:
   `php scripts/migrate.php`
3. Create `../secrets/db_credentials.php`.
4. Create `../secrets/email_secret.php`.
5. Create `../secrets/app_secret.php`.
6. Confirm local PHPMailer path exists at `lib/PHPMailer`.

### Optional Browser-Based Installer
- On a fresh install, the app automatically redirects to `/setup.php`.
- Enter DB and app URL/timezone credentials to generate secret files.
- SMTP is optional during install; it can be configured later in Admin Settings.
- Choose whether to allow new user self-registration during setup.
- Run migrations, then create the first admin account.
- After the first user exists, setup auto-locks and normal app routes resume.
- Optional: set `config.php` -> `setup.enabled` to `true` only when you intentionally need setup access again.

## Admin Controls
- `/admin_settings.php` is accessible only to the first user account (user ID `1`).
- Admin Settings includes:
  - SMTP mail configuration
  - Global registration toggle (`allow_registration`)
  - Read-only diagnostics for error display, PHP logging, `expose_php`, app/audit log writability, and secure session cookies
- When registration is disabled, `/auth/register.php` redirects to login and registration links are hidden.
- When registration is enabled, new self-registered accounts must verify their email before login. Configure SMTP first so verification emails can be delivered.

## Deployment Notes
- For Apache/LiteSpeed, the root `.htaccess` provides baseline security headers and the upload directories include `.htaccess` rules that block PHP-like script execution and directory listing.
- Other hosts, such as Nginx, Caddy, or CDN-fronted deployments, must configure equivalent headers and upload protections in their own server layer.
- The database bootstrap uses a version-aware PDO MySQL init-command option so the app runs cleanly on PHP 8.3, 8.4, and 8.5.
- `Strict-Transport-Security` is intentionally not enabled in the repository `.htaccess`; configure HSTS only after confirming HTTPS coverage for the production domain.
- Keep `display_errors` and `expose_php` disabled in production.
- Keep host/server logs and app logs outside the web root.

## Notes for Future Development
- Keep timezone conversion centralized in helpers.
- Avoid hardcoding timezone strings in page files.
- Prefer prepared statements (already used consistently).
- Require CSRF validation for every POST that creates, updates, deletes, exports, starts, or stops app data.
- Keep redirects constrained to known local app paths.
- Add migrations for any new security tables, such as rate limiting or audit logging.
- When adding new user-scoped tables, include cleanup in `delete_user_account(...)`.

## License
HourWise by Jim Kulakowski is licensed under the **Elastic License 2.0 (ELv2)**.

In plain language:
- You may use this app for your own personal use or internal freelance business use (including self-hosting on your own server).
- You may modify it for your own needs.
- You may not offer this app to third parties as a hosted or managed service (for example, as a SaaS time-tracking product).

See the full license terms in [`LICENSE`](LICENSE).
