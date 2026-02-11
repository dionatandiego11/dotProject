<?php
/**
 * DotProject REST API entry point.
 */

declare(strict_types=1);

require_once __DIR__ . '/base.php';
require_once __DIR__ . '/bootstrap.php';

if (file_exists(DP_BASE_DIR . '/includes/config.php')) {
    require_once DP_BASE_DIR . '/includes/config.php';
}

if (!isset($GLOBALS['OS_WIN'])) {
    $GLOBALS['OS_WIN'] = (mb_stristr(PHP_OS, 'WIN') !== false);
}

require_once DP_BASE_DIR . '/classes/csscolor.class.php';
require_once DP_BASE_DIR . '/includes/main_functions.php';
require_once DP_BASE_DIR . '/includes/db_adodb.php';
require_once DP_BASE_DIR . '/includes/db_connect.php';

use DotProject\Api\Router;
use DotProject\Api\Middleware\AuthMiddleware;
use DotProject\Api\Middleware\TenantMiddleware;

$router = new Router();
$router->use([TenantMiddleware::class, 'handle']);
$router->use([AuthMiddleware::class, 'handle']);

$GLOBALS['request'] = $router->getRequest();
$GLOBALS['response'] = $router->getResponse();

$routeFiles = [
    'api_routes_system.php',
    'api_routes_auth.php',
    'api_routes_projects.php',
    'api_routes_analytics.php',
    'api_routes_integrations.php',
    'api_routes_kanban.php',
    'api_routes_notifications.php',
    'api_routes_files.php',
    'api_routes_ppa.php',
    'api_routes_dashboard.php',
    'api_routes_admin.php',
];

foreach ($routeFiles as $routeFile) {
    require_once __DIR__ . '/' . $routeFile;
}

$router->run();
