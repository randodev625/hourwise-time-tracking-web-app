<?php
require __DIR__ . '/../middleware.php';
if (!empty($_SESSION['user'])) {
    header('Location: /dashboard.php');
    exit;
}

$pendingUserId = (int)($_SESSION['pending_2fa_user_id'] ?? 0);
$pendingStartedAt = (int)($_SESSION['pending_2fa_started_at'] ?? 0);
if ($pendingUserId <= 0 || $pendingStartedAt <= 0 || $pendingStartedAt < time() - 600) {
    unset($_SESSION['pending_2fa_user_id'], $_SESSION['pending_2fa_started_at']);
    header('Location: /auth/login.php');
    exit;
}

$settings = two_factor_settings($pdo, $pendingUserId);
if (!$settings) {
    two_factor_complete_login($pdo, $pendingUserId);
    header('Location: /dashboard.php');
    exit;
}

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $err = 'Invalid CSRF token.';
    } elseif (auth_rate_limit_status($pdo, 'two_factor', (string)$pendingUserId)['limited']) {
        $err = 'Too many attempts. Please try again later.';
    } else {
        $code = trim((string)($_POST['code'] ?? ''));
        $validTotp = two_factor_verify_code((string)$settings['secret'], $code);
        $validRecovery = false;

        if (!$validTotp) {
            $validRecovery = two_factor_use_recovery_code($pdo, $pendingUserId, $code);
        }

        if ($validTotp || $validRecovery) {
            if ($validRecovery) {
                audit_log('two_factor_recovery_code_used', ['user_id' => $pendingUserId]);
            }
            two_factor_complete_login($pdo, $pendingUserId);
            header('Location: /dashboard.php');
            exit;
        }

        auth_rate_limit_record_attempt($pdo, 'two_factor', (string)$pendingUserId);
        audit_log('two_factor_failed', ['user_id' => $pendingUserId]);
        $err = 'Invalid two-factor code.';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Two-Factor Authentication - HourWise</title>
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
                    <p class="auth-subtitle text-muted px-2">Enter your authenticator code to finish signing in.</p>
                </div>

                <h2 class="card-title auth-form-title">Two-Factor Authentication</h2>

                <?php if ($err): ?>
                    <div class="alert alert-danger small"><?= h($err) ?></div>
                <?php endif; ?>

                <form method="post" class="auth-form d-block mb-4">
                    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">

                    <div class="mb-3">
                        <label class="form-label" for="two_factor_code">Authentication Code</label>
                        <input id="two_factor_code" class="form-control" name="code" type="text" required autocomplete="one-time-code" autofocus>
                        <div class="form-text">Use a 6-digit authenticator code or one unused recovery code.</div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center gap-3">
                        <button class="btn btn-primary" type="submit">Verify</button>
                        <a class="small" href="/auth/logout.php">Cancel login</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
