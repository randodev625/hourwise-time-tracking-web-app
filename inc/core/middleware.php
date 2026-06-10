<?php
$config = require __DIR__ . '/config.php';
require_once __DIR__ . '/../helpers/helpers.php';
start_session($config);
redirect_to_canonical_route_if_needed();

$isSetupRoute = current_route_name() === 'setup';

$hasDbConfig = trim((string)($config['db']['dsn'] ?? '')) !== ''
    && trim((string)($config['db']['user'] ?? '')) !== '';
$hasAppConfig = trim((string)($config['app']['base_url'] ?? '')) !== '';

if (!$isSetupRoute && (!$hasDbConfig || !$hasAppConfig)) {
    redirect_to_route('setup');
}

require __DIR__ . '/db.php';

if (!$isSetupRoute && app_setup_required($pdo)) {
    redirect_to_route('setup');
}
