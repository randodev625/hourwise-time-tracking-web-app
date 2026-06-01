<?php
declare(strict_types=1);

$config = require __DIR__ . '/config.php';
require __DIR__ . '/helpers.php';
start_session($config);

$setupEnabled = (bool)($config['setup']['enabled'] ?? false);

function setup_connect(array $config): PDO {
    return new PDO(
        $config['db']['dsn'],
        $config['db']['user'],
        $config['db']['pass'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
}

function setup_write_php_array_file(string $path, array $data): void {
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0700, true)) {
        throw new RuntimeException('Could not create secrets directory.');
    }

    $php = "<?php\nreturn " . var_export($data, true) . ";\n";
    if (file_put_contents($path, $php, LOCK_EX) === false) {
        throw new RuntimeException('Could not write secrets file: ' . basename($path));
    }

    @chmod($path, 0600);
}

function setup_user_count(PDO $pdo): int {
    if (!table_exists($pdo, 'users')) {
        return 0;
    }
    return (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
}

function setup_run_migrations(PDO $pdo, string $migrationsDir): array {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS schema_migrations (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            filename VARCHAR(255) NOT NULL UNIQUE,
            applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $files = glob($migrationsDir . '/*.sql') ?: [];
    sort($files, SORT_STRING);

    $appliedStmt = $pdo->prepare('SELECT 1 FROM schema_migrations WHERE filename = ? LIMIT 1');
    $markAppliedStmt = $pdo->prepare('INSERT INTO schema_migrations (filename) VALUES (?)');

    $applied = [];
    $skipped = [];

    foreach ($files as $filePath) {
        $filename = basename($filePath);
        $appliedStmt->execute([$filename]);
        if ($appliedStmt->fetchColumn()) {
            $skipped[] = $filename;
            continue;
        }

        $sql = trim((string)file_get_contents($filePath));
        if ($sql === '') {
            $markAppliedStmt->execute([$filename]);
            $skipped[] = $filename . ' (empty)';
            continue;
        }

        $pdo->beginTransaction();
        try {
            $pdo->exec($sql);
            $markAppliedStmt->execute([$filename]);
            $pdo->commit();
            $applied[] = $filename;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    return [$applied, $skipped];
}

$messages = [];
$errors = [];

$displayName = trim((string)($_POST['display_name'] ?? ''));
$email = trim((string)($_POST['email'] ?? ''));
$timezone = trim((string)($_POST['timezone'] ?? app_default_timezone()));
$dbDsn = trim((string)($_POST['db_dsn'] ?? ($config['db']['dsn'] ?? '')));
$dbUser = trim((string)($_POST['db_user'] ?? ($config['db']['user'] ?? '')));
$dbPass = (string)($_POST['db_pass'] ?? ($config['db']['pass'] ?? ''));
$smtpHost = trim((string)($_POST['smtp_host'] ?? ($config['mail']['host'] ?? '')));
$smtpUser = trim((string)($_POST['smtp_user'] ?? ($config['mail']['username'] ?? '')));
$smtpPass = (string)($_POST['smtp_pass'] ?? ($config['mail']['password'] ?? ''));
$smtpPort = (string)($_POST['smtp_port'] ?? (string)($config['mail']['port'] ?? 465));
$fromEmail = trim((string)($_POST['from_email'] ?? ($config['mail']['from_email'] ?? '')));
$fromName = trim((string)($_POST['from_name'] ?? ($config['mail']['from_name'] ?? 'Time Tracker')));
$baseUrl = trim((string)($_POST['base_url'] ?? ($config['app']['base_url'] ?? '')));
$appTimezone = trim((string)($_POST['app_timezone'] ?? ($config['app']['timezone'] ?? 'America/New_York')));
$sessionSecure = isset($_POST['session_secure']) ? 1 : ((bool)($config['app']['session_secure'] ?? true) ? 1 : 0);

try {
    $pdo = setup_connect($config);
    $userCount = setup_user_count($pdo);
    $setupRequired = app_setup_required($pdo);
} catch (Throwable $e) {
    $pdo = null;
    $userCount = 0;
    $setupRequired = true;
    $errors[] = 'Could not connect to the database. Check DB config and database existence.';
}

if (!$setupRequired && !$setupEnabled) {
    header('Location: /');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'save_secrets') {
            if ($dbDsn === '' || $dbUser === '') {
                $errors[] = 'DB DSN and DB user are required.';
            }
            if ($smtpHost === '' || $smtpUser === '' || $smtpPort === '' || $fromEmail === '') {
                $errors[] = 'SMTP host, user, port, and from email are required.';
            }
            if ($baseUrl === '' || !preg_match('#^https?://#i', $baseUrl)) {
                $errors[] = 'Base URL is required and must start with http:// or https://';
            }
            if (!in_array($appTimezone, DateTimeZone::listIdentifiers(), true)) {
                $errors[] = 'App timezone must be a valid timezone identifier.';
            }
            if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'From email address is invalid.';
            }

            if (empty($errors)) {
                try {
                    $dbSecretsPath = __DIR__ . '/../secrets/db_credentials.php';
                    $emailSecretsPath = __DIR__ . '/../secrets/email_secret.php';
                    $appSecretsPath = __DIR__ . '/../secrets/app_secret.php';

                    setup_write_php_array_file($dbSecretsPath, [
                        'dsn' => $dbDsn,
                        'user' => $dbUser,
                        'pass' => $dbPass,
                    ]);

                    setup_write_php_array_file($emailSecretsPath, [
                        'CRM_SMTP_HOST' => $smtpHost,
                        'CRM_SMTP_USER' => $smtpUser,
                        'CRM_SMTP_PASS' => $smtpPass,
                        'CRM_SMTP_PORT' => (int)$smtpPort,
                        'CRM_FROM_EMAIL' => $fromEmail,
                        'CRM_FROM_NAME' => $fromName !== '' ? $fromName : 'Time Tracker',
                    ]);
                    setup_write_php_array_file($appSecretsPath, [
                        'APP_BASE_URL' => rtrim($baseUrl, '/'),
                        'APP_TIMEZONE' => $appTimezone,
                        'APP_SESSION_SECURE' => (bool)$sessionSecure,
                    ]);

                    $messages[] = 'Secrets saved. Re-testing DB connection...';

                    $config = require __DIR__ . '/config.php';
                    $pdo = setup_connect($config);
                    $setupRequired = app_setup_required($pdo);
                    $userCount = setup_user_count($pdo);
                    $messages[] = 'Database connection successful.';
                } catch (Throwable $e) {
                    $errors[] = 'Could not save secrets: ' . $e->getMessage();
                }
            }
        }

        if ($action !== 'save_secrets' && !$pdo) {
            $errors[] = 'Database connection is not available. Save secrets first.';
        }

        if ($action === 'run_migrations') {
            if ($pdo) {
                try {
                    [$applied, $skipped] = setup_run_migrations($pdo, __DIR__ . '/migrations');
                    $messages[] = 'Migrations complete. Applied: ' . count($applied) . ', Skipped: ' . count($skipped) . '.';
                    if (!empty($applied)) {
                        $messages[] = 'Applied: ' . implode(', ', $applied);
                    }
                    $userCount = setup_user_count($pdo);
                } catch (Throwable $e) {
                    $errors[] = 'Migration failed: ' . $e->getMessage();
                }
            }
        }

        if ($action === 'create_admin') {
            if (!table_exists($pdo, 'users')) {
                $errors[] = 'Users table does not exist yet. Run migrations first.';
            } elseif (setup_user_count($pdo) > 0) {
                $errors[] = 'Setup is locked because at least one user already exists.';
            } else {
                $password = (string)($_POST['password'] ?? '');
                $passwordConfirm = (string)($_POST['password_confirm'] ?? '');

                if ($displayName === '') {
                    $errors[] = 'Please enter a display name.';
                }
                if (mb_strlen($displayName) > 150) {
                    $errors[] = 'Display name must be 150 characters or fewer.';
                }
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = 'Please enter a valid email address.';
                }
                if (!in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
                    $errors[] = 'Please select a valid timezone.';
                }

                $passwordError = validate_password_strength($password);
                if ($passwordError !== null) {
                    $errors[] = $passwordError;
                }
                if (!hash_equals($password, $passwordConfirm)) {
                    $errors[] = 'Password confirmation does not match.';
                }

                if (empty($errors)) {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    try {
                        $pdo->beginTransaction();
                        $stmt = $pdo->prepare(
                            'INSERT INTO users (email, display_name, password_hash, timezone) VALUES (?, ?, ?, ?)'
                        );
                        $stmt->execute([$email, $displayName, $hash, $timezone]);
                        $newUserId = (int)$pdo->lastInsertId();
                        create_default_user_workspace($pdo, $newUserId);
                        $pdo->commit();

                        set_user_session([
                            'id' => $newUserId,
                            'email' => $email,
                            'display_name' => $displayName,
                            'avatar_path' => '',
                            'timezone' => $timezone,
                        ]);
                        session_regenerate_id(true);

                        header('Location: /setup_complete.php');
                        exit;
                    } catch (Throwable $e) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        $errors[] = 'Could not create admin user: ' . $e->getMessage();
                    }
                }
            }
        }
    }
}

$canCreateAdmin = $pdo && table_exists($pdo, 'users') && setup_user_count($pdo) === 0;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Time Tracker Setup</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body class="bg-light">
<div class="container py-5" style="max-width: 860px;">
    <h1 class="mb-3">Time Tracker First-Time Setup</h1>
    <?php if ($setupEnabled): ?>
        <div class="alert alert-warning">
            Setup override is enabled in config. Disable <code>config['setup']['enabled']</code> when not needed.
        </div>
    <?php endif; ?>

    <?php foreach ($messages as $m): ?>
        <div class="alert alert-success"><?= h($m) ?></div>
    <?php endforeach; ?>
    <?php foreach ($errors as $e): ?>
        <div class="alert alert-danger"><?= h($e) ?></div>
    <?php endforeach; ?>

    <div class="card p-4 mb-4">
        <h2 class="h5">Step 0: Save Hosting and Mail Credentials</h2>
        <p class="text-muted mb-3">Writes <code>../secrets/db_credentials.php</code>, <code>../secrets/email_secret.php</code>, and <code>../secrets/app_secret.php</code>.</p>
        <form method="post" class="row g-3">
            <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="action" value="save_secrets">

            <div class="col-12"><h3 class="h6 mb-0">Database</h3></div>
            <div class="col-md-7">
                <label class="form-label" for="db_dsn">DB DSN</label>
                <input id="db_dsn" name="db_dsn" class="form-control" required value="<?= h($dbDsn) ?>" placeholder="mysql:host=localhost;dbname=app;charset=utf8mb4">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="db_user">DB User</label>
                <input id="db_user" name="db_user" class="form-control" required value="<?= h($dbUser) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label" for="db_pass">DB Pass</label>
                <input id="db_pass" name="db_pass" type="password" class="form-control" value="<?= h($dbPass) ?>">
            </div>

            <div class="col-12 mt-2"><h3 class="h6 mb-0">SMTP / Email</h3></div>
            <div class="col-md-4">
                <label class="form-label" for="smtp_host">SMTP Host</label>
                <input id="smtp_host" name="smtp_host" class="form-control" required value="<?= h($smtpHost) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="smtp_user">SMTP User</label>
                <input id="smtp_user" name="smtp_user" class="form-control" required value="<?= h($smtpUser) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label" for="smtp_pass">SMTP Pass</label>
                <input id="smtp_pass" name="smtp_pass" type="password" class="form-control" value="<?= h($smtpPass) ?>">
            </div>
            <div class="col-md-1">
                <label class="form-label" for="smtp_port">Port</label>
                <input id="smtp_port" name="smtp_port" type="number" class="form-control" required value="<?= h($smtpPort) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label" for="from_name">From Name</label>
                <input id="from_name" name="from_name" class="form-control" value="<?= h($fromName) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="from_email">From Email</label>
                <input id="from_email" name="from_email" type="email" class="form-control" required value="<?= h($fromEmail) ?>">
            </div>

            <div class="col-12 mt-2"><h3 class="h6 mb-0">App</h3></div>
            <div class="col-md-6">
                <label class="form-label" for="base_url">Base URL</label>
                <input id="base_url" name="base_url" class="form-control" required value="<?= h($baseUrl) ?>" placeholder="https://time.example.com">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="app_timezone">Default Timezone</label>
                <select id="app_timezone" name="app_timezone" class="form-select" required>
                    <?php foreach (DateTimeZone::listIdentifiers() as $tz): ?>
                        <option value="<?= h($tz) ?>" <?= $appTimezone === $tz ? 'selected' : '' ?>><?= h($tz) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" id="session_secure" name="session_secure" value="1" <?= $sessionSecure ? 'checked' : '' ?>>
                    <label class="form-check-label" for="session_secure">HTTPS Cookies</label>
                </div>
            </div>

            <div class="col-12">
                <button type="submit" class="btn btn-dark">Save Credentials</button>
            </div>
        </form>
    </div>

    <div class="card p-4 mb-4">
        <h2 class="h5">Step 1: Run Migrations</h2>
        <p class="text-muted mb-3">Creates required tables from <code>/migrations</code> and tracks applied files.</p>
        <form method="post">
            <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="action" value="run_migrations">
            <button type="submit" class="btn btn-primary" <?= !$pdo ? 'disabled' : '' ?>>Run Migrations</button>
        </form>
    </div>

    <div class="card p-4">
        <h2 class="h5">Step 2: Create First Admin Account</h2>
        <?php if (!$canCreateAdmin): ?>
            <p class="text-muted mb-0">
                This step unlocks only when the <code>users</code> table exists and has zero users.
            </p>
        <?php else: ?>
            <form method="post" class="row g-3">
                <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="action" value="create_admin">

                <div class="col-md-6">
                    <label class="form-label" for="display_name">Display Name</label>
                    <input id="display_name" name="display_name" class="form-control" required maxlength="150" value="<?= h($displayName) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="email">Email</label>
                    <input id="email" name="email" type="email" class="form-control" required value="<?= h($email) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="password">Password</label>
                    <input id="password" name="password" type="password" class="form-control" minlength="12" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="password_confirm">Confirm Password</label>
                    <input id="password_confirm" name="password_confirm" type="password" class="form-control" minlength="12" required>
                </div>
                <div class="col-12">
                    <label class="form-label" for="timezone">Timezone</label>
                    <select id="timezone" name="timezone" class="form-select" required>
                        <?php foreach (DateTimeZone::listIdentifiers() as $tz): ?>
                            <option value="<?= h($tz) ?>" <?= $timezone === $tz ? 'selected' : '' ?>><?= h($tz) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-success">Create Admin User</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
