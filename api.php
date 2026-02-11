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

if (!(isset($GLOBALS['OS_WIN']))) {
    $GLOBALS['OS_WIN'] = (mb_stristr(PHP_OS, 'WIN') !== false);
}

// Required before main_functions
require_once DP_BASE_DIR . '/classes/csscolor.class.php';
require_once DP_BASE_DIR . '/includes/main_functions.php';

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
use DotProject\Api\Controller\AnalyticsController;
use DotProject\Service\AuthorizationService;

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

$router->put('/v1/projects/{id}/status', function (Request $req, Response $res) {
    $controller = new ProjectController($req, $res);
    return $controller->updateStatus();
});

$router->delete('/v1/projects/{id}', function (Request $req, Response $res) {
    $controller = new ProjectController($req, $res);
    return $controller->destroy();
});

$router->get('/v1/projects/{id}/tasks', function (Request $req, Response $res) {
    $controller = new ProjectController($req, $res);
    return $controller->tasks();
});

$router->get('/v1/projects/{id}/status-history', function (Request $req, Response $res) {
    $controller = new ProjectController($req, $res);
    return $controller->statusHistory();
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
// ROTAS DE ANALYTICS (adicionais)
// ===========================================

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
// ROTA DE CACHE (admin only)
// ===========================================

use DotProject\Core\Cache;
use DotProject\Core\FeatureFlag;
use DotProject\Core\LegacyAdapter;

$ensureAdmin = static function (Request $req, Response $res): ?Response {
    $userId = $req->getParam('_user_id');
    if ($userId === null) {
        return $res->unauthorized();
    }

    if (!AuthorizationService::getInstance()->isAdmin((int) $userId)) {
        return $res->forbidden('Admin privileges required');
    }

    return null;
};

$router->get('/v1/cache/stats', function (Request $req, Response $res) use ($ensureAdmin) {
    if ($guard = $ensureAdmin($req, $res)) {
        return $guard;
    }

    $cache = new Cache();
    return $res->json($cache->getStats());
});

// Feature Flags endpoints
$router->get('/v1/features', function (Request $req, Response $res) {
    $features = FeatureFlag::getInstance();
    return $res->json([
        'features' => $features->getAllFeatures(),
    ]);
});

$router->get('/v1/features/{name}', function (Request $req, Response $res) {
    $userId = $req->getParam('_user_id');
    $featureName = $req->getParam('name');
    $features = FeatureFlag::getInstance();
    
    return $res->json([
        'feature' => $featureName,
        'enabled' => $features->isEnabled($featureName, $userId),
        'user_id' => $userId,
    ]);
});

// Migration status
$router->get('/v1/migration/status', function (Request $req, Response $res) {
    return $res->json([
        'modules' => LegacyAdapter::getMigrationStatus(),
        'overall_progress' => '80%', // 4 de 5 fases
    ]);
});

// ===========================================
// ROTAS DE KANBAN
// ===========================================

use DotProject\Api\Controller\KanbanController;

$router->get('/v1/kanban/boards', function (Request $req, Response $res) {
    $controller = new KanbanController($req, $res);
    $controller->listBoards();
});

$router->get('/v1/kanban/boards/{id}', function (Request $req, Response $res) {
    $controller = new KanbanController($req, $res);
    $controller->getBoard((int) $req->getParam('id'));
});

$router->post('/v1/kanban/boards', function (Request $req, Response $res) {
    $controller = new KanbanController($req, $res);
    $controller->createBoard();
});

$router->post('/v1/kanban/boards/{id}/columns', function (Request $req, Response $res) {
    $controller = new KanbanController($req, $res);
    $controller->addColumn((int) $req->getParam('id'));
});

$router->put('/v1/kanban/columns/{id}', function (Request $req, Response $res) {
    $controller = new KanbanController($req, $res);
    $controller->updateColumn((int) $req->getParam('id'));
});

$router->put('/v1/kanban/tasks/{id}/move', function (Request $req, Response $res) {
    $controller = new KanbanController($req, $res);
    $controller->moveTask((int) $req->getParam('id'));
});

$router->get('/v1/kanban/boards/{id}/analytics', function (Request $req, Response $res) {
    $controller = new KanbanController($req, $res);
    $controller->getAnalytics((int) $req->getParam('id'));
});

// ===========================================
// ROTAS DE NOTIFICAÇÕES
// ===========================================

use DotProject\Api\Controller\NotificationController;

$router->get('/v1/notifications', function (Request $req, Response $res) {
    $controller = new NotificationController($req, $res);
    $controller->list();
});

$router->get('/v1/notifications/unread-count', function (Request $req, Response $res) {
    $controller = new NotificationController($req, $res);
    $controller->count();
});

$router->post('/v1/notifications/{id}/read', function (Request $req, Response $res) {
    $controller = new NotificationController($req, $res);
    $controller->markAsRead((int) $req->getParam('id'));
});

$router->post('/v1/notifications/mark-all-read', function (Request $req, Response $res) {
    $controller = new NotificationController($req, $res);
    $controller->markAllAsRead();
});

$router->delete('/v1/cache/clear', function (Request $req, Response $res) use ($ensureAdmin) {
    if ($guard = $ensureAdmin($req, $res)) {
        return $guard;
    }

    $cache = new Cache();
    $result = $cache->clear();
    return $res->json([
        'success' => $result,
        'message' => $result ? 'Cache cleared' : 'Failed to clear cache'
    ]);
});

// ===========================================
// ROTAS DE ARQUIVOS
// ===========================================

use DotProject\Api\Controller\FileController;

$router->get('/v1/tasks/{id}/files', function (Request $req, Response $res) {
    $controller = new FileController($req, $res);
    $controller->list((int) $req->getParam('id'));
});

$router->post('/v1/tasks/{id}/files', function (Request $req, Response $res) {
    $controller = new FileController($req, $res);
    $controller->upload((int) $req->getParam('id'));
});

$router->get('/v1/files/{id}/download', function (Request $req, Response $res) {
    $controller = new FileController($req, $res);
    $controller->download((int) $req->getParam('id'));
});

$router->delete('/v1/files/{id}', function (Request $req, Response $res) {
    $controller = new FileController($req, $res);
    $controller->delete((int) $req->getParam('id'));
});

// ===========================================
// ROTAS DE ANALYTICS
// ===========================================

$router->get('/v1/analytics/productivity', function (Request $req, Response $res) {
    $controller = new AnalyticsController($req, $res);
    $controller->productivity();
});

// ===========================================
// PREPARA VARIÁVEIS GLOBAIS PARA ROTAS
// ===========================================
$GLOBALS['request'] = $router->getRequest();
$GLOBALS['response'] = $router->getResponse();

// ===========================================
// ROTAS DO SISTEMA PPA (Gestão Pública)
// ===========================================
require_once __DIR__ . '/api_routes_ppa.php';

// ===========================================
// ROTAS DE DASHBOARD POR PERFIL
// ===========================================
require_once __DIR__ . '/api_routes_dashboard.php';

// ===========================================
// ROTAS DE ADMINISTRAÇÃO (Estrutura Organizacional)
// ===========================================
require_once __DIR__ . '/api_routes_admin.php';

// ===========================================
// EXECUTA O ROTEADOR
// ===========================================

$router->run();
