<?php
$config = require __DIR__ . '/config.php';

try {
    $pdo = new PDO(
        $config['db']['dsn'],
        $config['db']['user'],
        $config['db']['pass'],
        pdo_connection_options()
    );
} catch (Throwable $e) {
    if (function_exists('log_exception')) {
        log_exception($e, 'Database connection failed.');
    }
    require __DIR__ . '/500.php';
    exit;
}
