<?php
declare(strict_types=1);

/**
 * Simple migration runner for /migrations/*.sql files.
 *
 * Usage:
 *   php scripts/migrate.php
 */

$root = dirname(__DIR__);
$configPath = $root . '/config.php';
$migrationsDir = $root . '/migrations';

if (!is_file($configPath)) {
    fwrite(STDERR, "Missing config.php\n");
    exit(1);
}

$config = require $configPath;

try {
    $pdo = new PDO(
        $config['db']['dsn'],
        $config['db']['user'],
        $config['db']['pass'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (Throwable $e) {
    fwrite(STDERR, "DB connection failed: " . $e->getMessage() . PHP_EOL);
    exit(1);
}

$pdo->exec("
    CREATE TABLE IF NOT EXISTS schema_migrations (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        filename VARCHAR(255) NOT NULL UNIQUE,
        applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$files = glob($migrationsDir . '/*.sql') ?: [];
sort($files, SORT_STRING);

if (empty($files)) {
    echo "No migration files found in /migrations\n";
    exit(0);
}

$appliedStmt = $pdo->prepare('SELECT 1 FROM schema_migrations WHERE filename = ? LIMIT 1');
$markAppliedStmt = $pdo->prepare('INSERT INTO schema_migrations (filename) VALUES (?)');

$appliedCount = 0;
$skippedCount = 0;

foreach ($files as $filePath) {
    $filename = basename($filePath);
    $appliedStmt->execute([$filename]);
    if ($appliedStmt->fetchColumn()) {
        echo "Skipping {$filename} (already applied)\n";
        $skippedCount++;
        continue;
    }

    $sql = trim((string)file_get_contents($filePath));
    if ($sql === '') {
        echo "Skipping {$filename} (empty file)\n";
        $markAppliedStmt->execute([$filename]);
        $skippedCount++;
        continue;
    }

    echo "Applying {$filename}...\n";

    try {
        $pdo->exec($sql);
        $markAppliedStmt->execute([$filename]);
        echo "Applied {$filename}\n";
        $appliedCount++;
    } catch (Throwable $e) {
        fwrite(STDERR, "Failed on {$filename}: " . $e->getMessage() . PHP_EOL);
        exit(1);
    }
}

echo "Done. Applied: {$appliedCount}, Skipped: {$skippedCount}\n";
