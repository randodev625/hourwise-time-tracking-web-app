<?php
require __DIR__ . '/../../core/middleware.php';
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

render_layout_header();
?>
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
<?php render_layout_footer(); ?>
