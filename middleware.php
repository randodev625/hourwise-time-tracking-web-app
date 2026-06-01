<?php
$config = require __DIR__ . '/config.php';
require __DIR__ . '/helpers.php';
start_session($config);

$currentScript = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));

$hasDbConfig = trim((string)($config['db']['dsn'] ?? '')) !== ''
    && trim((string)($config['db']['user'] ?? '')) !== '';
$hasAppConfig = trim((string)($config['app']['base_url'] ?? '')) !== '';

if ($currentScript !== 'setup.php' && (!$hasDbConfig || !$hasAppConfig)) {
    header('Location: /setup.php');
    exit;
}

require __DIR__ . '/db.php';

if ($currentScript !== 'setup.php' && app_setup_required($pdo)) {
    header('Location: /setup.php');
    exit;
}
