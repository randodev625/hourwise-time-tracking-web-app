<?php
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

$dbCredentials = require __DIR__ . '/../secrets/db_credentials.php';
$emailSecret = require __DIR__ . '/../secrets/email_secret.php';

return [
    'db' => [
        'dsn' => $dbCredentials['dsn'],
        'user' => $dbCredentials['user'],
        'pass' => $dbCredentials['pass'],
    ],
    'app' => [
        'base_url' => 'https://your-url.com', // no trailing slash
        'timezone' => 'America/New_York',
        'session_name' => 'tt_sess',
        'session_secure' => true, // true if HTTPS only
        'session_lifetime' => 60 * 60 * 24 * 7, // 7 days
    ],
    'mail' => [
        'phpmailer_path' => __DIR__ . '/lib/PHPMailer',

        'host' => $emailSecret['CRM_SMTP_HOST'],
        'username' => $emailSecret['CRM_SMTP_USER'],
        'password' => $emailSecret['CRM_SMTP_PASS'],
        'port' => (int)$emailSecret['CRM_SMTP_PORT'],
        'encryption' => ((int)$emailSecret['CRM_SMTP_PORT'] === 465) ? 'ssl' : 'tls',

        'from_email' => $emailSecret['CRM_FROM_EMAIL'],
        'from_name' => $emailSecret['CRM_FROM_NAME'] ?? 'Time Tracker',
    ],
    'auth' => [
        'password_reset_expires_minutes' => 60,
    ],
    'setup' => [
        'enabled' => false, // optional override: allow /setup.php even after initial setup is complete
    ],
];
