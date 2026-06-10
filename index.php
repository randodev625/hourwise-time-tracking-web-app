<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/helpers/helpers.php';

function hourwise_dispatch_home(): void
{
    require __DIR__ . '/inc/core/middleware.php';

    if (!empty($_SESSION['user'])) {
        redirect_to_route('dashboard');
    }

    redirect_to_route('login');
}

$routes = require __DIR__ . '/inc/core/routes.php';
$path = request_path();

if ($path === '/') {
    hourwise_dispatch_home();
}

$route = resolve_route($path);
if ($route === null) {
    $_SERVER['HOURWISE_ROUTE_NAME'] = 'error_404';
    $_SERVER['HOURWISE_ROUTE_PATH'] = '/404';
    $_SERVER['HOURWISE_ROUTE_PARAMS'] = [];
    require __DIR__ . '/inc/views/errors/status.php';
    exit;
}

$routeFile = $route['file'] ?? '';
if (!is_string($routeFile) || $routeFile === '' || !is_file($routeFile)) {
    $_SERVER['HOURWISE_ROUTE_NAME'] = 'error_500';
    $_SERVER['HOURWISE_ROUTE_PATH'] = '/500';
    $_SERVER['HOURWISE_ROUTE_PARAMS'] = [];
    require __DIR__ . '/inc/views/errors/status.php';
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
