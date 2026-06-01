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
    } elseif (!$pdo) {
        $errors[] = 'Database connection is not available.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'run_migrations') {
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
        <h2 class="h5">Step 1: Run Migrations</h2>
        <p class="text-muted mb-3">Creates required tables from <code>/migrations</code> and tracks applied files.</p>
        <form method="post">
            <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="action" value="run_migrations">
            <button type="submit" class="btn btn-primary">Run Migrations</button>
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
