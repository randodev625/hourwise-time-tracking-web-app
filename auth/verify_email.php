<?php
require __DIR__ . '/../middleware.php';
if (!empty($_SESSION['user'])) {
    header('Location: /dashboard.php');
    exit;
}

$token = trim((string)($_GET['token'] ?? ''));
$verified = false;
$err = '';

if ($token === '') {
    $err = 'This verification link is invalid or has expired.';
} else {
    try {
        $verified = verify_account_email($pdo, $token);
        if (!$verified) {
            $err = 'This verification link is invalid or has expired.';
        }
    } catch (Throwable $e) {
        log_exception($e, 'Email verification failed.');
        $err = 'We could not verify your email right now. Please try again later.';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Email - HourWise</title>
    <link href="/assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/style.css">

    <link rel="icon" type="image/png" sizes="32x32" href="/assets/img/favicon_io/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/assets/img/favicon_io/favicon-16x16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/img/favicon_io/apple-touch-icon.png">
    <link rel="manifest" href="/assets/img/favicon_io/site.webmanifest">
    <link rel="shortcut icon" href="/assets/img/favicon_io/favicon.ico">
    <meta name="theme-color" content="#ffffff">
</head>
<body class="bg-light">
    <div class="container auth-page">
        <div class="card auth-card shadow mx-auto">
            <div class="card-body">
                <div class="auth-brand text-center pb-3">
                    <img src="/assets/img/favicon_io/android-chrome-512x512.png" alt="HourWise Logo" class="auth-logo img-fluid">
                    <h1 class="auth-title">HourWise</h1>
                </div>

                <h2 class="card-title auth-form-title">Verify Email</h2>

                <?php if ($verified): ?>
                    <div class="alert alert-success small">Your email has been verified. You can sign in now.</div>
                    <a class="btn btn-primary" href="/auth/login.php?verified=1">Continue to Login</a>
                <?php else: ?>
                    <div class="alert alert-danger small"><?= h($err) ?></div>
                    <a class="btn btn-outline-primary" href="/auth/resend_verification.php">Resend Verification Email</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
