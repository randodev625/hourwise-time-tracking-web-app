<?php
require __DIR__ . '/../middleware.php';
if (!empty($_SESSION['user'])) {
    header('Location: /dashboard.php');
    exit;
}

$token = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));
$record = get_password_reset_record($pdo, $token);
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $err = 'Invalid CSRF token.';
    } elseif (!$record) {
        $err = 'This reset link is invalid or has expired.';
    } else {
        $password = (string)($_POST['password'] ?? '');
        $passwordConfirm = (string)($_POST['password_confirm'] ?? '');

        $passwordError = validate_password_strength($password);
        if ($passwordError !== null) {
            $err = $passwordError;
        } elseif (!hash_equals($password, $passwordConfirm)) {
            $err = 'The password confirmation does not match.';
        } else {
            try {
                if (reset_user_password($pdo, $token, $password)) {
                    header('Location: /auth/login.php?reset=1');
                    exit;
                }
                $err = 'This reset link is invalid or has expired.';
            } catch (Throwable $e) {
                $err = 'We could not reset your password right now. Please try again later.';
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
    <title>Reset Password - Time Tracker</title>
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
                    <p class="auth-subtitle text-muted px-2">Choose a new password for your account.</p>
                </div>

                <h2 class="card-title auth-form-title">Reset Password</h2>

                <?php if (!$record): ?>
                    <div class="alert alert-danger small mb-3">This reset link is invalid or has expired.</div>
                    <p class="mb-0"><a href="/auth/forgot_password.php">Request a new password reset email</a>.</p>
                <?php else: ?>
                    <?php if ($err): ?>
                        <div class="alert alert-danger small"><?= h($err) ?></div>
                    <?php endif; ?>

                    <form method="post" class="auth-form d-block mb-4">
                        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                        <input type="hidden" name="token" value="<?= h($token) ?>">

                        <div class="mb-3">
                            <label class="form-label" for="reset_password">New password</label>
                            <input id="reset_password" class="form-control" name="password" type="password" required autocomplete="new-password" minlength="12">
                            <div class="form-text">Use at least 12 characters with uppercase, lowercase, a number, and a symbol.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="reset_password_confirm">Confirm new password</label>
                            <input id="reset_password_confirm" class="form-control" name="password_confirm" type="password" required autocomplete="new-password" minlength="12">
                        </div>

                        <div class="d-flex justify-content-between align-items-center gap-3">
                            <a class="small" href="/auth/login.php">Back to login</a>
                            <button class="btn btn-primary" type="submit">Reset Password</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
