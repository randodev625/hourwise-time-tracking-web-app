<?php
return [
    'db' => [
        'dsn' => '', // loaded from ../secrets/db_credentials.php
        'user' => '',
        'pass' => '',
    ],
    'app' => [
        'base_url' => '', // loaded from ../secrets/app_secret.php
        'timezone' => 'America/New_York', // loaded from app secret with fallback
        'session_name' => 'tt_sess',
        'session_secure' => true, // loaded from app secret with fallback
        'session_lifetime' => 60 * 60 * 24 * 7,
    ],
    'mail' => [
        'phpmailer_path' => __DIR__ . '/lib/PHPMailer',
        'host' => '', // loaded from ../secrets/email_secret.php
        'username' => '',
        'password' => '',
        'port' => 465,
        'encryption' => 'ssl',
        'from_email' => '',
        'from_name' => 'Time Tracker',
    ],
    'auth' => [
        'password_reset_expires_minutes' => 60,
        'email_verification_expires_minutes' => 1440,
    ],
    'setup' => [
        'enabled' => false, // optional override: allow /setup.php even after initial setup is complete
    ],
];
