<?php
/**
 * Analytics API routes.
 */

declare(strict_types=1);

use DotProject\Api\Request;
use DotProject\Api\Response;
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

$router->get('/v1/analytics/productivity', function (Request $req, Response $res) {
    $controller = new AnalyticsController($req, $res);
    $controller->productivity();
});
