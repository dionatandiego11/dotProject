<?php
/**
 * DotProject REST API Entry Point
 * 
 * Ponto de entrada para a API REST moderna do dotProject.
 * 
 * Exemplo de uso:
 *   GET  /api.php/v1/projects
 *   POST /api.php/v1/auth/login
 * 
 * @package DotProject\Api
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

// Carrega o sistema base
require_once __DIR__ . '/base.php';
require_once __DIR__ . '/bootstrap.php';

// Carrega configurações
if (file_exists(DP_BASE_DIR . '/includes/config.php')) {
    require_once DP_BASE_DIR . '/includes/config.php';
}

// Carrega conexão com banco de dados
require_once DP_BASE_DIR . '/includes/db_adodb.php';
require_once DP_BASE_DIR . '/includes/db_connect.php';

use DotProject\Api\Router;
use DotProject\Api\Request;
use DotProject\Api\Response;
use DotProject\Api\Middleware\AuthMiddleware;
use DotProject\Api\Controller\AuthController;
use DotProject\Api\Controller\ProjectController;
use DotProject\Api\Controller\TaskController;

// Cria o roteador
$router = new Router();

// Adiciona middleware de autenticação
$router->use([AuthMiddleware::class, 'handle']);

// ===========================================
// ROTAS DE SAÚDE E INFORMAÇÃO
// ===========================================

$router->get('/', function (Request $req, Response $res) {
    return $res->json([
        'name' => 'dotProject API',
        'version' => '1.0.0',
        'status' => 'running',
    ]);
});

$router->get('/v1/health', function (Request $req, Response $res) {
    return $res->json([
        'status' => 'healthy',
        'timestamp' => date('c'),
    ]);
});

// ===========================================
// ROTAS DE AUTENTICAÇÃO
// ===========================================

$router->post('/v1/auth/login', function (Request $req, Response $res) {
    $controller = new AuthController($req, $res);
    return $controller->login();
});

$router->post('/v1/auth/refresh', function (Request $req, Response $res) {
    $controller = new AuthController($req, $res);
    return $controller->refresh();
});

$router->get('/v1/auth/me', function (Request $req, Response $res) {
    $controller = new AuthController($req, $res);
    return $controller->me();
});

// ===========================================
// ROTAS DE PROJETOS
// ===========================================

$router->get('/v1/projects', function (Request $req, Response $res) {
    $controller = new ProjectController($req, $res);
    return $controller->index();
});

$router->get('/v1/projects/{id}', function (Request $req, Response $res) {
    $controller = new ProjectController($req, $res);
    return $controller->show();
});

$router->post('/v1/projects', function (Request $req, Response $res) {
    $controller = new ProjectController($req, $res);
    return $controller->store();
});

$router->put('/v1/projects/{id}', function (Request $req, Response $res) {
    $controller = new ProjectController($req, $res);
    return $controller->update();
});

$router->delete('/v1/projects/{id}', function (Request $req, Response $res) {
    $controller = new ProjectController($req, $res);
    return $controller->destroy();
});

$router->get('/v1/projects/{id}/tasks', function (Request $req, Response $res) {
    $controller = new ProjectController($req, $res);
    return $controller->tasks();
});

// ===========================================
// ROTAS DE TAREFAS
// ===========================================

$router->get('/v1/tasks', function (Request $req, Response $res) {
    $controller = new TaskController($req, $res);
    return $controller->index();
});

$router->get('/v1/tasks/{id}', function (Request $req, Response $res) {
    $controller = new TaskController($req, $res);
    return $controller->show();
});

$router->post('/v1/tasks', function (Request $req, Response $res) {
    $controller = new TaskController($req, $res);
    return $controller->store();
});

$router->put('/v1/tasks/{id}', function (Request $req, Response $res) {
    $controller = new TaskController($req, $res);
    return $controller->update();
});

$router->delete('/v1/tasks/{id}', function (Request $req, Response $res) {
    $controller = new TaskController($req, $res);
    return $controller->destroy();
});

// ===========================================
// ROTAS DE ANALYTICS
// ===========================================

use DotProject\Api\Controller\AnalyticsController;

$router->get('/v1/analytics/dashboard', function (Request $req, Response $res) {
    $controller = new AnalyticsController($req, $res);
    return $controller->dashboard();
});

$router->get('/v1/analytics/projects-health', function (Request $req, Response $res) {
    $controller = new AnalyticsController($req, $res);
    return $controller->projectsHealth();
});

$router->get('/v1/analytics/completion-trend', function (Request $req, Response $res) {
    $controller = new AnalyticsController($req, $res);
    return $controller->completionTrend();
});

$router->get('/v1/analytics/team-performance', function (Request $req, Response $res) {
    $controller = new AnalyticsController($req, $res);
    return $controller->teamPerformance();
});

$router->get('/v1/analytics/velocity', function (Request $req, Response $res) {
    $controller = new AnalyticsController($req, $res);
    return $controller->velocity();
});

$router->get('/v1/analytics/projects/{id}/burndown', function (Request $req, Response $res) {
    $controller = new AnalyticsController($req, $res);
    return $controller->projectBurndown();
});

$router->get('/v1/analytics/projects/{id}/statistics', function (Request $req, Response $res) {
    $controller = new AnalyticsController($req, $res);
    return $controller->projectStatistics();
});

// ===========================================
// ROTAS DE INTEGRAÇÃO GOOGLE
// ===========================================

use DotProject\Api\Controller\IntegrationController;

// Make callback public (no auth required)
AuthMiddleware::addPublicRoute('/v1/integrations/google/callback');

$router->get('/v1/integrations/google/auth', function (Request $req, Response $res) {
    $controller = new IntegrationController($req, $res);
    return $controller->googleAuth();
});

$router->get('/v1/integrations/google/callback', function (Request $req, Response $res) {
    $controller = new IntegrationController($req, $res);
    return $controller->googleCallback();
});

$router->get('/v1/integrations/google/status', function (Request $req, Response $res) {
    $controller = new IntegrationController($req, $res);
    return $controller->googleStatus();
});

$router->delete('/v1/integrations/google', function (Request $req, Response $res) {
    $controller = new IntegrationController($req, $res);
    return $controller->googleDisconnect();
});

$router->get('/v1/integrations/google/calendars', function (Request $req, Response $res) {
    $controller = new IntegrationController($req, $res);
    return $controller->googleCalendars();
});

$router->post('/v1/integrations/google/calendar/sync-task', function (Request $req, Response $res) {
    $controller = new IntegrationController($req, $res);
    return $controller->syncTaskToCalendar();
});

$router->post('/v1/integrations/google/calendar/sync-project', function (Request $req, Response $res) {
    $controller = new IntegrationController($req, $res);
    return $controller->syncProjectToCalendar();
});

$router->get('/v1/integrations/google/drive/files', function (Request $req, Response $res) {
    $controller = new IntegrationController($req, $res);
    return $controller->driveFiles();
});

$router->post('/v1/integrations/google/drive/attach', function (Request $req, Response $res) {
    $controller = new IntegrationController($req, $res);
    return $controller->driveAttach();
});

$router->get('/v1/integrations/google/drive/project/{id}/files', function (Request $req, Response $res) {
    $controller = new IntegrationController($req, $res);
    return $controller->projectDriveFiles();
});

$router->get('/v1/integrations/google/drive/task/{id}/files', function (Request $req, Response $res) {
    $controller = new IntegrationController($req, $res);
    return $controller->taskDriveFiles();
});

// ===========================================
// EXECUTA O ROTEADOR
// ===========================================

$router->run();
