# Time Tracker App Guide

## Overview
This is a server-rendered PHP time-tracking app for freelancers/small teams.

Core features:
- User authentication (register/login/logout)
- Password reset by email (PHPMailer + SMTP)
- Client/Project/Category management
- Start/stop timers and edit entries
- Dashboard summaries and charts
- CSV export
- Account management (profile, avatar, password, delete account)
- Per-user timezone support

## Tech Stack
- PHP (plain PHP pages, no framework)
- MySQL (via PDO)
- Bootstrap 5 (UI/layout)
- Chart.js (local vendor file): `/assets/vendor/chartjs/chart.umd.min.js`

## App Structure
- `middleware.php`: bootstraps config, helpers, session, DB.
- `config.php`: runtime config loaded from external secrets.
- `db.php`: PDO connection.
- `helpers.php`: shared auth, timezone, formatting, mail, account delete, and utility functions.
- `auth/*.php`: login/register/forgot/reset/logout.
- `dashboard.php`: weekly totals, donut + trend charts, quick timer actions.
- `track.php`: running timers and start/stop.
- `entries.php`, `entries_ajax.php`, `entry_edit.php`: listing/filtering/loading/editing/exporting entries.
- `clients.php`, `projects.php`, `categories.php` (+ edit pages): management CRUD.
- `account.php`: profile, timezone, password, avatar, delete account modal.
- `migrations/*.sql`: schema changes (for example timezone column).

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
- `users` (includes `timezone`, `avatar_path`, password hash)
- `clients`
- `projects` (belongs to client + user)
- `work_categories`
- `time_records`
- `password_reset_tokens`
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

Migration used for this:
- `migrations/2026-06-01_add_users_timezone.sql`

## Secrets Implementation
Yes, this app uses external secrets files and does not store sensitive credentials in repo code.

`config.php` loads:
- `../secrets/db_credentials.php`
- `../secrets/email_secret.php`

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
  'CRM_FROM_NAME' => 'Time Tracker',
];
```

Important:
- Keep `secrets/` outside web root and out of version control.
- Ensure filesystem permissions restrict read access.
- Rotate SMTP/DB credentials if exposed.

## First-Time Setup Checklist
1. Create DB and tables.
2. Apply migrations in `migrations/`.
3. Create `../secrets/db_credentials.php`.
4. Create `../secrets/email_secret.php`.
5. Confirm local PHPMailer path exists at `lib/PHPMailer`.
6. Configure `config.php` app values (`base_url`, `session_secure`, default timezone).

## Notes for Future Development
- Keep timezone conversion centralized in helpers.
- Avoid hardcoding timezone strings in page files.
- Prefer prepared statements (already used consistently).
- When adding new user-scoped tables, include cleanup in `delete_user_account(...)`.
