<?php
require __DIR__ . '/../../core/middleware.php';
if (!empty($_SESSION['user'])) {
    redirect_to_route('dashboard');
}

$pendingUserId = (int)($_SESSION['pending_2fa_user_id'] ?? 0);
$pendingStartedAt = (int)($_SESSION['pending_2fa_started_at'] ?? 0);
if ($pendingUserId <= 0 || $pendingStartedAt <= 0 || $pendingStartedAt < time() - 600) {
    unset($_SESSION['pending_2fa_user_id'], $_SESSION['pending_2fa_started_at']);
    redirect_to_route('login');
}

$settings = two_factor_settings($pdo, $pendingUserId);
if (!$settings) {
    two_factor_complete_login($pdo, $pendingUserId);
    redirect_to_route('dashboard');
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
            redirect_to_route('dashboard');
        }

        auth_rate_limit_record_attempt($pdo, 'two_factor', (string)$pendingUserId);
        audit_log('two_factor_failed', ['user_id' => $pendingUserId]);
        $err = 'Invalid two-factor code.';
    }
}

render_layout_header();
?>
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
        <a class="small" href="<?= h(route_url('logout')) ?>">Cancel login</a>
    </div>
</form>
<?php render_layout_footer(); ?>
