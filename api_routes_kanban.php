<?php
/**
 * Kanban API routes.
 */

declare(strict_types=1);

use DotProject\Api\Request;
use DotProject\Api\Response;
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
