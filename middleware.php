<?php
$config = require __DIR__ . '/config.php';
require __DIR__ . '/helpers.php';
start_session($config);
require __DIR__ . '/db.php';

$currentScript = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
if ($currentScript !== 'setup.php' && app_setup_required($pdo)) {
    header('Location: /setup.php');
    exit;
}
