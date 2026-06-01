<?php
require __DIR__ . '/../middleware.php';
if (!empty($_SESSION['user'])) {
    header('Location: /dashboard.php');
    exit;
}
if (empty($config['app']['allow_registration'])) {
    http_response_code(404);
    exit('Registration is disabled for this installation.');
}

$err = '';
$display_name = trim($_POST['display_name'] ?? '');
$email = trim($_POST['email'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $err = 'Invalid CSRF token';
    }

    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';

    if (!$err && $display_name === '') {
        $err = 'Please enter a user name.';
    }

    if (!$err && mb_strlen($display_name) > 150) {
        $err = 'User name must be 150 characters or fewer.';
    }

    if (!$err && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $err = 'Please enter a valid email address.';
    }

    if (!$err) {
        $passwordError = validate_password_strength($password);
        if ($passwordError !== null) {
            $err = $passwordError;
        }
    }

    if (!$err && $password !== $password_confirm) {
        $err = 'Password confirmation does not match.';
    }

    if (!$err) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $err = 'Email already registered.';
        }
    }

    if (!$err) {
        $hash = password_hash($password, PASSWORD_DEFAULT);

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare('
                INSERT INTO users (email, display_name, password_hash, timezone)
                VALUES (?, ?, ?, ?)
            ');
            $stmt->execute([$email, $display_name, $hash, app_default_timezone()]);
            $newUserId = (int)$pdo->lastInsertId();

            create_default_user_workspace($pdo, $newUserId);

            $pdo->commit();
            header('Location: /auth/login.php?registered=1');
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $err = 'We could not finish creating your account right now. Please try again.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Time Tracker</title>
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
                    <p class="auth-subtitle text-muted px-2">
                        Simple time tracking for freelancers and small teams.
                    </p>
                </div>

                <h2 class="card-title auth-form-title">Create Account</h2>

                <?php if ($err): ?>
                    <div class="alert alert-danger small"><?= h($err) ?></div>
                <?php endif; ?>

                <form method="post" class="auth-form d-block mb-4">
                    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">

                    <div class="mb-3">
                        <label class="form-label" for="register_display_name">User Name</label>
                        <input
                            id="register_display_name"
                            class="form-control"
                            name="display_name"
                            type="text"
                            maxlength="150"
                            required
                            autocomplete="username"
                            value="<?= h($display_name) ?>"
                        >
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="register_email">Email</label>
                        <input
                            id="register_email"
                            class="form-control"
                            name="email"
                            type="email"
                            required
                            autocomplete="email"
                            value="<?= h($email) ?>"
                        >
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="register_password">Password</label>
                        <input
                            id="register_password"
                            class="form-control"
                            name="password"
                            type="password"
                            required
                            autocomplete="new-password"
                            minlength="12"
                        >
                        <div class="form-text">Use at least 12 characters with uppercase, lowercase, a number, and a symbol.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="register_password_confirm">Confirm Password</label>
                        <input
                            id="register_password_confirm"
                            class="form-control"
                            name="password_confirm"
                            type="password"
                            required
                            autocomplete="new-password"
                        >
                    </div>

                    <div class="d-flex justify-content-between">
                        <button class="btn btn-primary" type="submit">Register</button>
                    </div>
                </form>

                <p class="auth-switch mb-0">
                    Already have an account? <a href="/auth/login.php">Sign in here</a>.
                </p>
            </div>
        </div>
    </div>
</body>
</html>
