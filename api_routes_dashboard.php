<?php
/**
 * Rotas da API para Dashboards
 * 
 * @package DotProject\Api
 */

declare(strict_types=1);

use DotProject\Api\Request;
use DotProject\Api\Response;
use DotProject\Api\Controller\DashboardController;

// Obter request e response do contexto global
$request = $GLOBALS['request'] ?? null;
$response = $GLOBALS['response'] ?? null;

if (!$request || !$response) {
    // Fallback: criar novas instâncias se não estiverem disponíveis
    $request = new Request();
    $response = new Response();
}

$prefix = '/v1/dashboard';

// Dashboard principal (auto-detecta perfil)
$router->get($prefix, function () use ($request, $response) {
    $controller = new DashboardController($request, $response);
    return $controller->index();
});

// Dashboards por perfil
$router->get("{$prefix}/prefeito", function () use ($request, $response) {
    $controller = new DashboardController($request, $response);
    return $controller->prefeito();
});

$router->get("{$prefix}/secretario", function () use ($request, $response) {
    $controller = new DashboardController($request, $response);
    return $controller->secretario();
});

$router->get("{$prefix}/coordenador", function () use ($request, $response) {
    $controller = new DashboardController($request, $response);
    return $controller->coordenador();
});

$router->get("{$prefix}/tecnico", function () use ($request, $response) {
    $controller = new DashboardController($request, $response);
    return $controller->tecnico();
});

$router->get("{$prefix}/controlador", function () use ($request, $response) {
    $controller = new DashboardController($request, $response);
    return $controller->controlador();
});

// Alertas
$router->get("{$prefix}/alertas", function () use ($request, $response) {
    $controller = new DashboardController($request, $response);
    return $controller->alertas();
});

$router->put("{$prefix}/alertas/:id/lido", function ($id) use ($request, $response) {
    $controller = new DashboardController($request, $response);
    return $controller->marcarAlertaLido((int) $id);
});

$router->put("{$prefix}/alertas/lidos", function () use ($request, $response) {
    $controller = new DashboardController($request, $response);
    return $controller->marcarTodosLidos();
});

// Status do sistema (para debug)
$router->get("{$prefix}/status", function () use ($request, $response) {
    $controller = new DashboardController($request, $response);
    return $controller->status();
});
