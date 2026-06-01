<?php
require __DIR__ . '/../middleware.php';
if (!empty($_SESSION['user'])) {
    header('Location: /dashboard.php');
    exit;
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
        } else {
            try {
                issue_password_reset_token($pdo, $email);
                $sent = true;
            } catch (Throwable $e) {
                $err = 'We could not send the reset email right now. Please try again again shortly.';
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
    <title>Forgot Password - Time Tracker</title>
    <link href="/assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body class="bg-light">
    <div class="container auth-page">
        <div class="card auth-card shadow mx-auto">
            <div class="card-body">
                <div class="auth-brand text-center pb-3">
                    <img src="/assets/img/favicon_io/android-chrome-512x512.png" alt="Time Tracker Logo" class="auth-logo img-fluid">
                    <h1 class="auth-title">Time Tracker</h1>
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
                        <a class="small" href="/auth/login.php">Back to login</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
