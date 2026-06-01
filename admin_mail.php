<?php
require __DIR__ . '/middleware.php';
require_admin();

$page_title = 'Mail Settings';
$errors = [];
$messages = [];

$mail = $config['mail'] ?? [];
$host = trim((string)($mail['host'] ?? ''));
$username = trim((string)($mail['username'] ?? ''));
$password = '';
$port = (int)($mail['port'] ?? 465);
$fromEmail = trim((string)($mail['from_email'] ?? ''));
$fromName = trim((string)($mail['from_name'] ?? 'Time Tracker'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        $host = trim((string)($_POST['host'] ?? ''));
        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $port = (int)($_POST['port'] ?? 465);
        $fromEmail = trim((string)($_POST['from_email'] ?? ''));
        $fromName = trim((string)($_POST['from_name'] ?? 'Time Tracker'));

        if ($host === '' || $username === '' || $fromEmail === '') {
            $errors[] = 'Host, username, and from email are required.';
        }
        if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'From email must be a valid email address.';
        }
        if ($port <= 0) {
            $errors[] = 'SMTP port must be a positive number.';
        }

        if (empty($errors)) {
            $emailSecretsPath = __DIR__ . '/../secrets/email_secret.php';

            if ($password === '') {
                $existingPath = __DIR__ . '/../secrets/email_secret.php';
                $existing = is_file($existingPath) ? require $existingPath : [];
                $password = (string)($existing['CRM_SMTP_PASS'] ?? '');
            }

            try {
                write_php_array_file($emailSecretsPath, [
                    'CRM_SMTP_HOST' => $host,
                    'CRM_SMTP_USER' => $username,
                    'CRM_SMTP_PASS' => $password,
                    'CRM_SMTP_PORT' => $port,
                    'CRM_FROM_EMAIL' => $fromEmail,
                    'CRM_FROM_NAME' => $fromName !== '' ? $fromName : 'Time Tracker',
                ]);
                $messages[] = 'Mail settings saved.';
            } catch (Throwable $e) {
                $errors[] = 'Could not save mail settings: ' . $e->getMessage();
            }
        }
    }
}

include __DIR__ . '/header.php';
?>
<h1 class="mb-4">Mail Settings</h1>

<?php foreach ($messages as $message): ?>
    <div class="alert alert-success"><?= h($message) ?></div>
<?php endforeach; ?>
<?php foreach ($errors as $error): ?>
    <div class="alert alert-danger"><?= h($error) ?></div>
<?php endforeach; ?>

<div class="card p-4">
    <p class="text-muted">Only the first user account (admin) can access this page.</p>
    <form method="post" class="row g-3">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">

        <div class="col-md-4">
            <label class="form-label" for="host">SMTP Host</label>
            <input id="host" name="host" class="form-control" required value="<?= h($host) ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label" for="username">SMTP Username</label>
            <input id="username" name="username" class="form-control" required value="<?= h($username) ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label" for="port">SMTP Port</label>
            <input id="port" name="port" type="number" class="form-control" required value="<?= h((string)$port) ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label" for="from_name">From Name</label>
            <input id="from_name" name="from_name" class="form-control" value="<?= h($fromName) ?>">
        </div>

        <div class="col-md-6">
            <label class="form-label" for="password">SMTP Password</label>
            <input id="password" name="password" type="password" class="form-control" placeholder="Leave blank to keep existing password">
        </div>
        <div class="col-md-6">
            <label class="form-label" for="from_email">From Email</label>
            <input id="from_email" name="from_email" type="email" class="form-control" required value="<?= h($fromEmail) ?>">
        </div>

        <div class="col-12">
            <button type="submit" class="btn btn-primary">Save Mail Settings</button>
        </div>
    </form>
</div>

<?php include __DIR__ . '/footer.php'; ?>
