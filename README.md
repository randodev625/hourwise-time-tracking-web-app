# HourWise
![HourWise Social Card](assets/img/hourwise-social-card.jpg)

## Overview
HourWise is a time tracking and timesheet app for freelancers who want a straightforward, reliable way to see where their time goes and generate reports.

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

## Installation
### Quick Start
1. Create an empty MySQL database.
2. Upload all files to your public web root, usually `public_html`.
3. Visit your site's URL to start setup.

### Browser-Based Installer
- On a fresh install, HourWise automatically redirects to `/setup`.
- Enter database, app URL, and timezone details to generate the external secrets files.
- SMTP is optional during setup and can be configured later in Admin Settings.
- Choose whether to allow self-registration during setup.
- Run migrations, then create the first admin account.
- After the first user exists, setup auto-locks and normal app routes resume.
- Optional: In `inc/core/config.php`, set `setup.enabled` to `true` only when you intentionally want to regain access to the setup form.

### Manual Requirements
- Create `../secrets/db_credentials.php`.
- Create `../secrets/email_secret.php`.
- Create `../secrets/app_secret.php`.
- Confirm the PHPMailer path exists at `lib/PHPMailer`.
- Apply migrations with the browser-based setup flow, or run `php scripts/migrate.php` from the project root if you keep the full project structure on the server.

## Tech Stack
- PHP 8.3, 8.4, and 8.5 compatible (plain PHP pages, no framework)
- MySQL (via PDO)
- Bootstrap 5 (self-hosted vendor files)
- Font Awesome (self-hosted vendor files)
- Chart.js (local vendor file): `/assets/vendor/chartjs/chart.umd.min.js`
- QRCode.js (local vendor file): `/assets/vendor/qrcodejs/qrcode.min.js`

## App Structure
- `index.php`: front controller that resolves the request path and dispatches routed requests.
- `inc/core/routes.php`: route table for canonical app URLs, legacy aliases, and API-style endpoints.
- `inc/core/middleware.php`: bootstraps config, helpers, session, DB, and canonical redirects.
- `inc/core/config.php`: runtime config loaded from external secrets.
- `inc/core/db.php`: PDO connection.
- `.htaccess`: Apache/LiteSpeed rewrite rules for pretty URLs plus baseline security headers.
- `inc/helpers/helpers.php`: shared auth, CSRF, rate limiting, timezone, formatting, mail, logging, account delete, and utility functions.
- `inc/views/app/*.php`: routed application views for the signed-in area.
- `inc/views/auth/*.php`: routed auth views rendered through shared auth layout wrappers.
- `inc/views/errors/status.php`: shared routed error view driven by route metadata and front-controller/server error routes.
- `inc/views/setup/*.php`: setup and install flow views.
- `inc/api/*.php`: JSON/partial-response handlers for search, filters, and entry list loading.
- `inc/layout/*.php`: shared document, app, auth, and setup layout wrappers.
- Legacy `*.php` page URLs are preserved through route aliases and the front controller, without keeping duplicate page files in the web root.
- `scripts/migrate.php`: CLI migration runner kept outside the web root.
- `migrations/*.sql`: schema changes and incremental feature/security updates.

## Migration Overview
The repository includes these SQL migrations:
- `migrations/0001_initial_schema.sql`: creates the core tables for users, clients, projects, categories, time records, password reset tokens, and legacy compatibility tables.
- `migrations/2026-06-01_add_users_timezone.sql`: adds the per-user timezone column.
- `migrations/2026-06-02_add_email_verification.sql`: adds email verification support and the token table.
- `migrations/2026-06-02_add_pending_email_verification.sql`: adds pending-email support so address changes can be verified without locking out the old login email.
- `migrations/2026-06-02_add_two_factor_auth.sql`: adds the two-factor auth table.
- `migrations/2026-06-02_add_auth_rate_limits.sql`: adds the auth rate limiting table used by login, password reset, and 2FA flows.

## Request Flow
1. Apache/LiteSpeed rewrites clean application URLs through `index.php`.
2. The matched handler includes `inc/core/middleware.php`.
3. `inc/core/middleware.php` loads:
   - `inc/core/config.php`
   - `inc/helpers/helpers.php`
   - session via `start_session(...)`
   - DB via `inc/core/db.php`
4. Protected pages call `require_login()`.
5. Page logic runs queries and renders HTML.

## Data Model Overview
- `users` (includes `timezone`, `avatar_path`, `pending_email`, password hash)
- `clients`
- `projects` (belongs to client + user)
- `work_categories`
- `time_records`
- `password_reset_tokens`
- `email_verification_tokens`
- `user_two_factor`
- `auth_rate_limits`
- Legacy or optional tables referenced by code: `jobs`, `report_links`

## Timezone Behavior
- App default timezone is in config: `inc/core/config.php -> app.timezone`.
- Each user has `users.timezone` (IANA value, e.g. `Europe/London`).
- Session stores timezone through `set_user_session(...)`.
- Conversions use helpers:
  - `user_timezone()`
  - `user_timezone_object()`
  - `formatLocalTime(...)`
  - `formatLocalTimeRecentEntries(...)`

## Secrets Implementation
HourWise uses external secrets files and does not store sensitive credentials in repository code.

`inc/core/config.php` structure:
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
        'phpmailer_path' => dirname(__DIR__, 2) . '/lib/PHPMailer',
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

`inc/core/config.php` loads:
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

## Security and Privacy Features
HourWise includes a layered set of protections designed to safeguard user accounts, time data, and uploaded files.

- Runtime credentials are loaded from `../secrets/*` instead of hardcoded in repo files.
- PDO prepared statements are used throughout the main data access paths.
- CSRF checks protect state-changing POST forms.
- Request-controlled redirects are constrained to known local app paths.
- Login and forgot-password flows use persistent DB-backed rate limiting.
- Self-registered accounts must verify their email address before login.
- Users can enable TOTP-based two-factor authentication with recovery codes.
- Session cookies use `HttpOnly` and `SameSite=Lax`; production should keep `APP_SESSION_SECURE = true`.
- Session IDs and CSRF tokens rotate after login, first-run setup login, password change, email change, and account deletion.
- Password reset tokens are stored as hashes.
- Email verification tokens are stored as hashes.
- Password reset tokens are invalidated after password changes.
- Apache/LiteSpeed `.htaccess` defaults provide baseline security headers where supported.
- Uploaded avatars use server-side MIME checks, image decode checks, dimension limits, randomized filenames, and non-executable permissions.
- Runtime avatar uploads are ignored by Git.
- User-facing errors avoid raw exception details; app and audit logs are written outside the web root.

## Operational Notes
- Supported PHP and MySQL versions should match the deployment environment.
- Keep database backups automated and verify restore procedures regularly.
- Host or CDN settings should be used for any domain-wide transport policy such as HSTS.

## Admin Controls
- `/admin/settings` is accessible only to the first user account (user ID `1`).
- Admin Settings includes:
  - SMTP mail configuration
  - Global registration toggle to allow or disallow other users to register accounts (`allow_registration`)
  - Read-only diagnostics for error display, PHP logging, `expose_php`, app/audit log writability, and secure session cookies
- When registration is disabled, `/register` redirects to login and registration links are hidden.
- When registration is enabled, new self-registered accounts must verify their email before login. Configuring SMTP first may help verification emails be delivered reliably.

## Deployment Notes
- For Apache/LiteSpeed, the root `.htaccess` provides pretty-URL rewrite rules plus baseline security headers, and the upload directories include `.htaccess` rules that block PHP-like script execution and directory listing.
- Other hosts, such as Nginx, Caddy, or CDN-fronted deployments, must configure equivalent headers and upload protections in their own server layer.
- Pretty URLs depend on server rewrites. On Apache/LiteSpeed, ensure `mod_rewrite` or the equivalent rewrite engine is enabled and that `.htaccess` overrides are allowed.
- The database bootstrap uses a version-aware PDO MySQL init-command option so the app runs cleanly on PHP 8.3, 8.4, and 8.5.
- `Strict-Transport-Security` is intentionally not enabled in the repository `.htaccess`; configure HSTS only after confirming HTTPS coverage for the production domain.
- The included `robots.txt` disallows all crawling by default, which is appropriate for an authenticated web app. It is a crawler hint, not a security control.
- Keep `display_errors` and `expose_php` disabled in production.

## License
HourWise by Jim Kulakowski is licensed under the **Elastic License 2.0 (ELv2)**.

In plain language:
- You may use this app for your own personal use or internal freelance business use (including self-hosting on your own server).
- You may modify it for your own needs.
- You may not offer this app to third parties as a hosted or managed service (for example, as a SaaS time-tracking product).

See the full license terms in [`LICENSE`](LICENSE).
