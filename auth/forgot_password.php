<?php
require __DIR__ . '/../middleware.php';
if (!empty($_SESSION['user'])) {
    redirect_to_route('dashboard');
}

$err = '';
$sent = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $err = 'Invalid CSRF token.';
    } else {
        $email = trim((string)($_POST['email'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $err = 'Please enter a valid email address.';
        } elseif (auth_rate_limit_status($pdo, 'password_reset', $email)['limited']) {
            audit_log('password_reset_rate_limited', ['email_hash' => hash('sha256', strtolower($email))]);
            $sent = true;
        } else {
            try {
                auth_rate_limit_record_attempt($pdo, 'password_reset', $email);
                issue_password_reset_token($pdo, $email);
                audit_log('password_reset_requested', ['email_hash' => hash('sha256', strtolower($email))]);
                $sent = true;
            } catch (Throwable $e) {
                log_exception($e, 'Password reset email failed.', ['email_hash' => hash('sha256', strtolower($email))]);
                $err = 'We could not send the reset email right now. Please try again shortly.';
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - HourWise</title>
    <link href="/assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/style.css">

    <link rel="icon" type="image/png" zes="32x32" href="/assets/img/favicon_io/favicon-32x32.png">
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
                    <p class="auth-subtitle text-muted px-2">Enter your email and we’ll send you a password reset link.</p>
                </div>

                <h2 class="card-title auth-form-title">Forgot Password</h2>

                <?php if ($sent): ?>
                    <div class="alert alert-success small">
                        If that email address is in our system, a password reset link has been sent.
                    </div>
                <?php endif; ?>

                <?php if ($err): ?>
                    <div class="alert alert-danger small"><?= h($err) ?></div>
                <?php endif; ?>

                <form method="post" class="auth-form d-block mb-4">
                    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">

                    <div class="mb-3">
                        <label class="form-label" for="forgot_email">Email</label>
                        <input id="forgot_email" class="form-control" name="email" type="email" required autocomplete="email" value="<?= h($_POST['email'] ?? '') ?>">
                    </div>

                    <div class="d-flex justify-content-between align-items-center gap-3">
                        <button class="btn btn-primary" type="submit">Send Reset Link</button>
                        <a class="small" href="<?= h(route_url('login')) ?>">Back to login</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
