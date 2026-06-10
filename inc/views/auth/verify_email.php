<?php
require __DIR__ . '/../../core/middleware.php';

$token = trim((string)($_GET['token'] ?? ''));
$verified = false;
$err = '';

if ($token === '') {
    $err = 'This verification link is invalid or has expired.';
} else {
    try {
        $verified = verify_account_email($pdo, $token);
        if (!$verified) {
            $err = 'This verification link is invalid or has expired.';
        } elseif (!empty($_SESSION['user']['id'])) {
            $stmt = $pdo->prepare('SELECT id, email, display_name, avatar_path, timezone FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([(int)$_SESSION['user']['id']]);
            $currentUser = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($currentUser) {
                set_user_session($currentUser);
            }
        }
    } catch (Throwable $e) {
        log_exception($e, 'Email verification failed.');
        $err = 'We could not verify your email right now. Please try again later.';
    }
}

render_layout_header();
?>
<?php if ($verified): ?>
    <div class="alert alert-success small">Your email has been verified. You can sign in now.</div>
    <?php if (!empty($_SESSION['user'])): ?>
        <a class="btn btn-primary" href="<?= h(route_url('dashboard')) ?>">Continue to Dashboard</a>
    <?php else: ?>
        <a class="btn btn-primary" href="<?= h(route_url('login', ['verified' => '1'])) ?>">Continue to Login</a>
    <?php endif; ?>
<?php else: ?>
    <div class="alert alert-danger small"><?= h($err) ?></div>
    <a class="btn btn-outline-primary" href="<?= h(route_url('resend_verification')) ?>">Resend Verification Email</a>
<?php endif; ?>
<?php render_layout_footer(); ?>
