<?php
require __DIR__ . '/../../core/middleware.php';
require_admin();

$errors = [];
$messages = [];

$mail = $config['mail'] ?? [];
$host = trim((string)($mail['host'] ?? ''));
$username = trim((string)($mail['username'] ?? ''));
$password = '';
$port = (int)($mail['port'] ?? 465);
$fromEmail = trim((string)($mail['from_email'] ?? ''));
$fromName = trim((string)($mail['from_name'] ?? 'HourWise'));
$hasExistingSmtpPassword = trim((string)($mail['password'] ?? '')) !== '';
$allowRegistration = (bool)($config['app']['allow_registration'] ?? false);

$appSecretPath = secrets_root_path('app_secret.php');
$appSecret = is_file($appSecretPath) ? (require $appSecretPath) : [];

function admin_ini_enabled(string $name): bool
{
    $value = strtolower((string)ini_get($name));
    return in_array($value, ['1', 'on', 'true', 'yes'], true);
}

function admin_path_is_outside_web_root(string $path): bool
{
    $webRoot = realpath(public_root_path());
    $target = realpath($path) ?: realpath(dirname($path));
    if ($webRoot === false || $target === false) {
        return false;
    }

    return !str_starts_with($target, $webRoot . DIRECTORY_SEPARATOR) && $target !== $webRoot;
}

function admin_log_target_writable(string $path): bool
{
    $dir = is_dir($path) ? $path : dirname($path);
    if (is_dir($dir)) {
        return is_writable($dir);
    }

    $parent = dirname($dir);
    return is_dir($parent) && is_writable($parent);
}

function admin_diagnostic_row(string $label, string $value, bool $ok, string $recommendation): array
{
    return [
        'label' => $label,
        'value' => $value,
        'ok' => $ok,
        'recommendation' => $ok ? 'No action needed.' : $recommendation,
    ];
}

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
            $fromName = trim((string)($_POST['from_name'] ?? 'HourWise'));

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
                $emailSecretsPath = secrets_root_path('email_secret.php');

                if ($password === '') {
                    $existingPath = secrets_root_path('email_secret.php');
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
                        'CRM_FROM_NAME' => $fromName !== '' ? $fromName : 'HourWise',
                    ]);
                    $messages[] = 'Mail settings saved.';
                    audit_log('admin_mail_settings_updated');
                } catch (Throwable $e) {
                    log_exception($e, 'Could not save mail settings.', ['action' => $action]);
                    $errors[] = 'Could not save mail settings right now. Please check the server logs and try again.';
                }
            }
        }

        if ($action === 'save_app_controls') {
            $allowRegistration = isset($_POST['allow_registration']) && $_POST['allow_registration'] === '1';

            try {
                $appSecret['APP_ALLOW_REGISTRATION'] = $allowRegistration;
                write_php_array_file($appSecretPath, $appSecret);
                $messages[] = 'Registration access setting saved.';
                audit_log('admin_app_controls_updated', ['allow_registration' => $allowRegistration]);
            } catch (Throwable $e) {
                log_exception($e, 'Could not save app controls.', ['action' => $action]);
                $errors[] = 'Could not save app controls right now. Please check the server logs and try again.';
            }
        }
    }
}

$appLogPath = app_log_path('app.log');
$auditLogPath = app_log_path('audit.log');
$appLogDir = dirname($appLogPath);
$phpErrorLog = trim((string)ini_get('error_log'));
$phpErrorLogValue = $phpErrorLog !== '' ? 'Custom path configured' : 'Host default';
$phpErrorLogOk = $phpErrorLog === '' || admin_path_is_outside_web_root($phpErrorLog);

$diagnostics = [
    admin_diagnostic_row(
        'Display errors',
        admin_ini_enabled('display_errors') ? 'On' : 'Off',
        !admin_ini_enabled('display_errors'),
        'Turn display_errors off in the host PHP settings so errors are not shown to users.'
    ),
    admin_diagnostic_row(
        'PHP error logging',
        admin_ini_enabled('log_errors') ? 'On' : 'Off',
        admin_ini_enabled('log_errors'),
        'Turn log_errors on in the host PHP settings so PHP errors are written to server logs.'
    ),
    admin_diagnostic_row(
        'PHP expose_php',
        admin_ini_enabled('expose_php') ? 'On' : 'Off',
        !admin_ini_enabled('expose_php'),
        'Disable expose_php in the host PHP settings to avoid advertising the PHP version.'
    ),
    admin_diagnostic_row(
        'Host PHP error log path',
        $phpErrorLogValue,
        $phpErrorLogOk,
        'Configure the host PHP error log outside the app web root.'
    ),
    admin_diagnostic_row(
        'App log location',
        admin_path_is_outside_web_root($appLogDir) ? 'Outside web root' : 'Review needed',
        admin_path_is_outside_web_root($appLogDir),
        'Keep application logs under ../secrets/logs or another path outside public_html.'
    ),
    admin_diagnostic_row(
        'App log writable',
        admin_log_target_writable($appLogPath) ? 'Writable' : 'Not writable',
        admin_log_target_writable($appLogPath),
        'Ensure ../secrets/logs can be created and written by the PHP process.'
    ),
    admin_diagnostic_row(
        'Audit log writable',
        admin_log_target_writable($auditLogPath) ? 'Writable' : 'Not writable',
        admin_log_target_writable($auditLogPath),
        'Ensure ../secrets/logs can be created and written by the PHP process.'
    ),
    admin_diagnostic_row(
        'Secure session cookies',
        !empty($config['app']['session_secure']) ? 'Enabled' : 'Disabled',
        !empty($config['app']['session_secure']),
        'Enable HTTPS Cookies in setup or set APP_SESSION_SECURE to true for production.'
    ),
];

render_layout_header();
?>
<h1 class="mb-4">Admin Settings</h1>

<?php foreach ($messages as $message): ?>
    <div class="alert alert-success"><?= h($message) ?></div>
<?php endforeach; ?>
<?php foreach ($errors as $error): ?>
    <div class="alert alert-danger"><?= h($error) ?></div>
<?php endforeach; ?>

<div class="card p-4 mb-4">
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
                <?= $allowRegistration ? 'checked' : '' ?>>
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
                placeholder="<?= $hasExistingSmtpPassword ? 'Leave blank to keep existing SMTP password' : 'Enter SMTP password' ?>">
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

<div class="card p-4 mb-4">
    <div class="d-flex flex-column flex-md-row justify-content-between gap-2 mb-3">
        <div>
            <h2 class="h5 mb-1">Diagnostics</h2>
            <p class="text-muted mb-0">Read-only production checks for error handling, logging, and session safety.</p>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead>
                <tr>
                    <th scope="col">Setting</th>
                    <th scope="col">Current</th>
                    <th scope="col">Status</th>
                    <th scope="col">Recommendation</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($diagnostics as $diagnostic): ?>
                    <tr>
                        <th scope="row"><?= h($diagnostic['label']) ?></th>
                        <td><?= h($diagnostic['value']) ?></td>
                        <td>
                            <span class="badge <?= $diagnostic['ok'] ? 'text-bg-success' : 'text-bg-warning' ?>">
                                <?= $diagnostic['ok'] ? 'OK' : 'Review' ?>
                            </span>
                        </td>
                        <td><?= h($diagnostic['recommendation']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php render_layout_footer(); ?>
