<?php
/**
 * File upload/download API routes.
 */

declare(strict_types=1);

use DotProject\Api\Request;
use DotProject\Api\Response;
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
