<?php
require __DIR__ . '/middleware.php';
require_admin();

$page_title = 'Admin Settings';
$errors = [];
$messages = [];

$mail = $config['mail'] ?? [];
$host = trim((string)($mail['host'] ?? ''));
$username = trim((string)($mail['username'] ?? ''));
$password = '';
$port = (int)($mail['port'] ?? 465);
$fromEmail = trim((string)($mail['from_email'] ?? ''));
$fromName = trim((string)($mail['from_name'] ?? 'Time Tracker'));
$hasExistingSmtpPassword = trim((string)($mail['password'] ?? '')) !== '';
$allowRegistration = (bool)($config['app']['allow_registration'] ?? false);

$appSecretPath = __DIR__ . '/../secrets/app_secret.php';
$appSecret = is_file($appSecretPath) ? (require $appSecretPath) : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        $action = (string)($_POST['action'] ?? '');

        if ($action === 'save_mail') {
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

        if ($action === 'save_app_controls') {
            $allowRegistration = isset($_POST['allow_registration']) && $_POST['allow_registration'] === '1';

            try {
                $appSecret['APP_ALLOW_REGISTRATION'] = $allowRegistration;
                write_php_array_file($appSecretPath, $appSecret);
                $messages[] = 'Registration access setting saved.';
            } catch (Throwable $e) {
                $errors[] = 'Could not save app controls: ' . $e->getMessage();
            }
        }
    }
}

include __DIR__ . '/header.php';
?>
<h1 class="mb-4">Admin Settings</h1>

<?php foreach ($messages as $message): ?>
    <div class="alert alert-success"><?= h($message) ?></div>
<?php endforeach; ?>
<?php foreach ($errors as $error): ?>
    <div class="alert alert-danger"><?= h($error) ?></div>
<?php endforeach; ?>

<div class="card p-4">
    <h2 class="h5 mb-3">Registration Access</h2>
    <p class="text-muted">Only the first user account (admin) can access and change this setting.</p>
    <form method="post" class="mb-4">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="action" value="save_app_controls">
        <div class="form-check mb-3">
            <input
                class="form-check-input"
                type="checkbox"
                id="allow_registration"
                name="allow_registration"
                value="1"
                <?= $allowRegistration ? 'checked' : '' ?>
            >
            <label class="form-check-label" for="allow_registration">
                Allow new users to register accounts
            </label>
        </div>
        <button type="submit" class="btn btn-primary">Save Registration Setting</button>
    </form>

    <hr class="my-4">

    <h2 class="h5 mb-3">Mail Settings</h2>
    <p class="text-muted">
        SMTP is optional, but recommended for reliable delivery of app emails, especially password reset requests.
    </p>
    <form method="post" class="row g-3">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="action" value="save_mail">

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
            <input
                id="password"
                name="password"
                type="password"
                class="form-control"
                placeholder="<?= $hasExistingSmtpPassword ? 'Leave blank to keep existing SMTP password' : 'Enter SMTP password' ?>"
            >
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
