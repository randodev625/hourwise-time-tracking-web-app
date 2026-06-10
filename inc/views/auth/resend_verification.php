<?php
require __DIR__ . '/../../core/middleware.php';
if (!empty($_SESSION['user'])) {
    redirect_to_route('dashboard');
}

$err = '';
$sent = false;
$email = trim((string)($_POST['email'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $err = 'Invalid CSRF token.';
    } elseif ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $err = 'Please enter a valid email address.';
    } elseif (auth_rate_limit_status($pdo, 'email_verification', $email)['limited']) {
        audit_log('email_verification_rate_limited', ['email_hash' => hash('sha256', strtolower($email))]);
        $sent = true;
    } else {
        try {
            auth_rate_limit_record_attempt($pdo, 'email_verification', $email);
            $stmt = $pdo->prepare('
                SELECT id, email_verified_at, pending_email
                FROM users
                WHERE email = ? OR pending_email = ?
                ORDER BY CASE WHEN email = ? THEN 0 ELSE 1 END
                LIMIT 1
            ');
            $stmt->execute([$email, $email, $email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && (empty($user['email_verified_at']) || !empty($user['pending_email']))) {
                send_account_verification_for_user($pdo, (int)$user['id']);
                audit_log('email_verification_requested', ['user_id' => (int)$user['id']]);
            } elseif ($user) {
                send_account_verification_for_user($pdo, (int)$user['id']);
            }

            $sent = true;
        } catch (Throwable $e) {
            log_exception($e, 'Verification email resend failed.', ['email_hash' => hash('sha256', strtolower($email))]);
            $err = 'We could not send the verification email right now. Please try again shortly.';
        }
    }
}

render_layout_header();
?>
<?php if ($sent): ?>
    <div class="alert alert-success small">
        If that email address belongs to an unverified account, a verification link has been sent.
    </div>
<?php endif; ?>

<?php if ($err): ?>
    <div class="alert alert-danger small"><?= h($err) ?></div>
<?php endif; ?>

<form method="post" class="auth-form d-block mb-4">
    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">

    <div class="mb-3">
        <label class="form-label" for="verification_email">Email</label>
        <input id="verification_email" class="form-control" name="email" type="email" required autocomplete="email" value="<?= h($email) ?>">
    </div>

    <div class="d-flex justify-content-between align-items-center gap-3">
        <button class="btn btn-primary" type="submit">Send Verification Link</button>
        <a class="small" href="<?= h(route_url('login')) ?>">Back to login</a>
    </div>
</form>
<?php render_layout_footer(); ?>
