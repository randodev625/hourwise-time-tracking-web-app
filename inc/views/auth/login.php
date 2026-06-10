<?php
require __DIR__ . '/../../core/middleware.php';
if (!empty($_SESSION['user'])) {
    redirect_to_route('dashboard');
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
        $stmt = $pdo->prepare('SELECT id, email, display_name, avatar_path, timezone, password_hash, email_verified_at FROM users WHERE email=?');
        $stmt->execute([$email]);
        $u = $stmt->fetch();

        if (!$u || !password_verify($password, $u['password_hash'])) {
            auth_rate_limit_record_attempt($pdo, 'login', $email);
            audit_log('login_failed', ['email_hash' => hash('sha256', strtolower($email))]);
            $err = 'Invalid credentials.';
        } elseif (empty($u['email_verified_at'])) {
            auth_rate_limit_clear($pdo, 'login', $email);
            audit_log('login_unverified_email', ['user_id' => (int)$u['id']]);
            $err = 'Please verify your email address before signing in. You can request a new verification email below.';
        } else {
            auth_rate_limit_clear($pdo, 'login', $email);

            if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
                $new = password_hash($password, PASSWORD_DEFAULT);
                $pdo->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([$new, $u['id']]);
            }

            if (two_factor_enabled($pdo, (int)$u['id'])) {
                session_regenerate_id(true);
                $_SESSION['pending_2fa_user_id'] = (int)$u['id'];
                $_SESSION['pending_2fa_started_at'] = time();
                audit_log('login_2fa_required', ['user_id' => (int)$u['id']]);
                redirect_to_route('two_factor');
            }

            set_user_session($u);
            refresh_session_security();
            audit_log('login_success');
            redirect_to_route('dashboard');
        }
    }
}

render_layout_header();
?>
<?php if (isset($_GET['registered']) && $_GET['registered'] === 'verify'): ?>
    <div class="alert alert-success small">Registration successful. Check your email to verify your account before signing in.</div>
<?php elseif (isset($_GET['registered'])): ?>
    <div class="alert alert-success small">Registration successful. Please log in.</div>
<?php endif; ?>

<?php if (isset($_GET['verified'])): ?>
    <div class="alert alert-success small">Email verified. You can sign in now.</div>
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
        <span class="d-flex flex-column align-items-end gap-1">
            <a class="small" href="<?= h(route_url('forgot_password')) ?>">Forgot password?</a>
            <a class="small" href="<?= h(route_url('resend_verification')) ?>">Resend verification email</a>
        </span>
    </div>
</form>

<p class="auth-switch mb-0">
    <?php if (!empty($config['app']['allow_registration'])): ?>
        Don’t have an account? <a href="<?= h(route_url('register')) ?>">Register here</a>.
    <?php endif; ?>
</p>
<?php render_layout_footer(); ?>
