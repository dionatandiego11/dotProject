<?php
/**
 * Integrations API routes.
 */

declare(strict_types=1);

use DotProject\Api\Request;
use DotProject\Api\Response;
use DotProject\Api\Middleware\AuthMiddleware;
use DotProject\Api\Controller\IntegrationController;

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
