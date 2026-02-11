<?php
/**
 * Notifications API routes.
 */

declare(strict_types=1);

use DotProject\Api\Request;
use DotProject\Api\Response;
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
