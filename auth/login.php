<?php
require __DIR__ . '/../middleware.php';
if (!empty($_SESSION['user'])) {
    header('Location: /dashboard.php');
    exit;
}

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $err = 'Invalid CSRF token';
    }

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$err && auth_rate_limit_status($pdo, 'login', $email)['limited']) {
        $err = 'Too many attempts. Please try again later.';
    }

    if (!$err) {
        $stmt = $pdo->prepare('SELECT id, email, display_name, avatar_path, timezone, password_hash FROM users WHERE email=?');
        $stmt->execute([$email]);
        $u = $stmt->fetch();

        if (!$u || !password_verify($password, $u['password_hash'])) {
            auth_rate_limit_record_attempt($pdo, 'login', $email);
            audit_log('login_failed', ['email_hash' => hash('sha256', strtolower($email))]);
            $err = 'Invalid credentials.';
        } else {
            auth_rate_limit_clear($pdo, 'login', $email);

            if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
                $new = password_hash($password, PASSWORD_DEFAULT);
                $pdo->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([$new, $u['id']]);
            }

            set_user_session($u);
            refresh_session_security();
            audit_log('login_success');
            header('Location: /dashboard.php');
            exit;
        }
    }
}
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Time Tracker</title>
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
                    <img src="/assets/img/favicon_io/android-chrome-512x512.png" alt="Time Tracker Logo" class="auth-logo img-fluid">
                    <h1 class="auth-title">Time Tracker</h1>
                    <p class="auth-subtitle text-muted px-2">
                        Simple time tracking for freelancers and small teams.
                    </p>
                </div>

                <h2 class="card-title auth-form-title">Login</h2>

                <?php if (isset($_GET['registered'])): ?>
                    <div class="alert alert-success small">Registration successful. Please log in.</div>
                <?php endif; ?>

                <?php if (isset($_GET['reset'])): ?>
                    <div class="alert alert-success small">Your password has been reset. Please log in.</div>
                <?php endif; ?>

                <?php if ($err): ?>
                    <div class="alert alert-danger small"><?= h($err) ?></div>
                <?php endif; ?>

                <form method="post" class="auth-form d-block mb-4">
                    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">

                    <div class="mb-3">
                        <label class="form-label" for="login_email">Email</label>
                        <input id="login_email" class="form-control" name="email" type="email" required autocomplete="email">
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="login_password">Password</label>
                        <input id="login_password" class="form-control" name="password" type="password" required autocomplete="current-password">
                    </div>

                    <div class="d-flex justify-content-between align-items-center gap-3">
                        <button class="btn btn-primary" type="submit">Sign In</button>
                        <a class="small" href="/auth/forgot_password.php">Forgot password?</a>
                    </div>
                </form>

                <p class="auth-switch mb-0">
                    <?php if (!empty($config['app']['allow_registration'])): ?>
                        Don’t have an account? <a href="/auth/register.php">Register here</a>.
                    <?php else: ?>
                        
                    <?php endif; ?>
                </p>
            </div>
        </div>
    </div>
</body>

</html>
