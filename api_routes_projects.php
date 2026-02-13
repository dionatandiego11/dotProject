<?php
/**
 * Project and task API routes.
 */

declare(strict_types=1);

use DotProject\Api\Request;
use DotProject\Api\Response;
use DotProject\Api\Controller\ProjectController;
use DotProject\Api\Controller\TaskController;

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

$router->get('/v1/projects/{id}/etapas', function (Request $req, Response $res) {
    $controller = new ProjectController($req, $res);
    return $controller->etapas();
});

$router->put('/v1/projects/{id}/etapas/{etapaId}', function (Request $req, Response $res) {
    $controller = new ProjectController($req, $res);
    return $controller->atualizarEtapa();
});

$router->post('/v1/projects/{id}/etapas/{etapaId}/concluir', function (Request $req, Response $res) {
    $controller = new ProjectController($req, $res);
    return $controller->concluirEtapa();
});

$router->get('/v1/projects/{id}/status-history', function (Request $req, Response $res) {
    $controller = new ProjectController($req, $res);
    return $controller->statusHistory();
});

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
