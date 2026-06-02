<?php
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

$dbCredentialsPath = __DIR__ . '/../secrets/db_credentials.php';
$emailSecretPath = __DIR__ . '/../secrets/email_secret.php';
$appSecretPath = __DIR__ . '/../secrets/app_secret.php';

$dbCredentials = is_file($dbCredentialsPath) ? require $dbCredentialsPath : [];
$emailSecret = is_file($emailSecretPath) ? require $emailSecretPath : [];
$appSecret = is_file($appSecretPath) ? require $appSecretPath : [];

return [
    'db' => [
        'dsn' => (string)($dbCredentials['dsn'] ?? ''),
        'user' => (string)($dbCredentials['user'] ?? ''),
        'pass' => (string)($dbCredentials['pass'] ?? ''),
    ],
    'app' => [
        'base_url' => (string)($appSecret['APP_BASE_URL'] ?? ''),
        'timezone' => (string)($appSecret['APP_TIMEZONE'] ?? 'America/New_York'),
        'allow_registration' => (bool)($appSecret['APP_ALLOW_REGISTRATION'] ?? false),
        'session_name' => 'tt_sess',
        'session_secure' => (bool)($appSecret['APP_SESSION_SECURE'] ?? true), // true if HTTPS only
        'session_lifetime' => 60 * 60 * 24 * 7, // 7 days
    ],
    'mail' => [
        'phpmailer_path' => __DIR__ . '/lib/PHPMailer',

        'host' => (string)($emailSecret['CRM_SMTP_HOST'] ?? ''),
        'username' => (string)($emailSecret['CRM_SMTP_USER'] ?? ''),
        'password' => (string)($emailSecret['CRM_SMTP_PASS'] ?? ''),
        'port' => (int)($emailSecret['CRM_SMTP_PORT'] ?? 465),
        'encryption' => ((int)($emailSecret['CRM_SMTP_PORT'] ?? 465) === 465) ? 'ssl' : 'tls',

        'from_email' => (string)($emailSecret['CRM_FROM_EMAIL'] ?? ''),
        'from_name' => $emailSecret['CRM_FROM_NAME'] ?? 'HourWise',
    ],
    'auth' => [
        'password_reset_expires_minutes' => 60,
        'email_verification_expires_minutes' => 1440,
    ],
    'setup' => [
        'enabled' => false, // optional override: allow /setup.php even after initial setup is complete
    ],
];
