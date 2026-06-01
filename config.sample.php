<?php
return [
    'db' => [
        'dsn' => 'mysql:host=localhost;dbname=YOUR_DB;charset=utf8mb4',
        'user' => 'YOUR_DB_USER',
        'pass' => 'YOUR_DB_PASS',
    ],
    'app' => [
        'base_url' => 'https://example.com', // no trailing slash
        'timezone' => 'America/New_York',
        'session_name' => 'tt_sess',
        'session_secure' => true,
        'session_lifetime' => 60 * 60 * 24 * 7,
    ],
    'mail' => [
        'phpmailer_path' => __DIR__ . '/../lib/PHPMailer',
        'secret_include' => '/domains/example.com/secrets/email_secret.php',
        'host' => 'smtp.hostinger.com',
        'username' => 'you@example.com',
        'password' => 'SMTP_PASSWORD',
        'port' => 465,
        'encryption' => 'ssl',
        'from_email' => 'you@example.com',
        'from_name' => 'Time Tracker',
    ],
    'auth' => [
        'password_reset_expires_minutes' => 60,
    ],
    'setup' => [
        'enabled' => false, // optional override: allow /setup.php even after initial setup is complete
    ],
];
