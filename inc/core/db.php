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
    $_SERVER['HOURWISE_ROUTE_NAME'] = 'error_500';
    $_SERVER['HOURWISE_ROUTE_PATH'] = '/500';
    $_SERVER['HOURWISE_ROUTE_PARAMS'] = [];
    require dirname(__DIR__) . '/views/errors/status.php';
    exit;
}
