<?php
require __DIR__ . '/middleware.php';
require_login();

$userId = user_id();
$page_title = 'Delete Account';
$errors = [];

$stmt = $pdo->prepare('SELECT id, email, display_name, avatar_path, password_hash FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    clear_auth_session();
    header('Location: /auth/login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $errors[] = 'Invalid CSRF token.';
    }

    $password = $_POST['current_password'] ?? '';
    $confirmed = isset($_POST['confirm_delete']) && $_POST['confirm_delete'] === '1';

    if (!$confirmed) {
        $errors[] = 'Please confirm that you understand this action cannot be undone.';
    }

    if (!password_verify($password, $user['password_hash'])) {
        $errors[] = 'Your current password is incorrect.';
    }

    if (!$errors) {
        try {
            delete_user_account($pdo, (int)$userId);
            clear_auth_session();
            header('Location: /auth/login.php?deleted=1');
            exit;
        } catch (Throwable $e) {
            $errors[] = 'Something went wrong while deleting your account.';
        }
    }
}

include __DIR__ . '/header.php';
?>
<h1 class="mb-4 text-danger">Delete Account</h1>

<?php foreach ($errors as $error): ?>
    <div class="alert alert-danger"><?= h($error) ?></div>
<?php endforeach; ?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card border-danger shadow-sm">
            <div class="card-body p-4 p-lg-5">
                <p class="lead mb-3">This action permanently deletes your account and cannot be undone.</p>
                <p>The following data will be removed:</p>
                <ul>
                    <li>Your account profile and login access</li>
                    <li>All clients and projects</li>
                    <li>All work categories and legacy jobs</li>
                    <li>All tracked time records and report links</li>
                    <li>Your uploaded profile photo</li>
                </ul>

                <div class="alert alert-warning mb-4">
                    If you have an active timer running, it will be deleted along with the rest of your account data.
                </div>

                <form method="post">
                    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">

                    <div class="mb-3">
                        <label for="current_password" class="form-label">Confirm with Current Password</label>
                        <input type="password" id="current_password" name="current_password" class="form-control" required autocomplete="current-password">
                    </div>

                    <div class="form-check mb-4">
                        <input class="form-check-input" type="checkbox" value="1" id="confirm_delete" name="confirm_delete">
                        <label class="form-check-label" for="confirm_delete">
                            I understand this action is permanent and cannot be undone.
                        </label>
                    </div>

                    <div class="d-flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-danger">Delete My Account</button>
                        <a href="/account.php" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/footer.php'; ?>
