<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

function hourwise_dispatch_home(): void
{
    require __DIR__ . '/middleware.php';

    if (!empty($_SESSION['user'])) {
        redirect_to_route('dashboard');
    }

    redirect_to_route('login');
}

$routes = require __DIR__ . '/routes.php';
$path = request_path();

if ($path === '/') {
    hourwise_dispatch_home();
}

$route = resolve_route($path);
if ($route === null) {
    require __DIR__ . '/404.php';
    exit;
}

$routeFile = $route['file'] ?? '';
if (!is_string($routeFile) || $routeFile === '' || !is_file($routeFile)) {
    require __DIR__ . '/500.php';
    exit;
}

$_SERVER['HOURWISE_ROUTE_PATH'] = $path;
$_SERVER['HOURWISE_ROUTE_NAME'] = (string)($route['name'] ?? '');
$_SERVER['HOURWISE_ROUTE_PARAMS'] = (array)($route['params'] ?? []);

foreach ((array)($route['params'] ?? []) as $key => $value) {
    if (!isset($_GET[$key])) {
        $_GET[$key] = $value;
    }
    if (!isset($_REQUEST[$key])) {
        $_REQUEST[$key] = $value;
    }
}

require $routeFile;
