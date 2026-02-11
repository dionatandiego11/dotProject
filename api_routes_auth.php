<?php
/**
 * Authentication API routes.
 */

declare(strict_types=1);

use DotProject\Api\Request;
use DotProject\Api\Response;
use DotProject\Api\Controller\AuthController;

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
