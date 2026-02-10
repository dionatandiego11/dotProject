<?php
/**
 * Rotas da API para Dashboards por Perfil
 * 
 * @package DotProject\Api
 */

declare(strict_types=1);

use DotProject\Api\Request;
use DotProject\Api\Response;
use DotProject\Api\Controller\Dashboard\DashboardRouterController;
use DotProject\Api\Controller\Dashboard\PrefeitoDashboardController;
use DotProject\Api\Controller\Dashboard\SecretarioDashboardController;
use DotProject\Api\Controller\Dashboard\CoordenadorDashboardController;
use DotProject\Api\Controller\Dashboard\TecnicoDashboardController;
use DotProject\Api\Controller\Dashboard\ControladorDashboardController;
use DotProject\Api\Controller\Dashboard\AlertaDashboardController;

$prefix = '/v1/dashboard';

// Obter request e response do contexto global
$request = $GLOBALS['request'] ?? null;
$response = $GLOBALS['response'] ?? null;

if (!$request || !$response) {
    $request = new Request();
    $response = new Response();
}

// ===========================================
// DASHBOARD PRINCIPAL (roteador por perfil)
// ===========================================
$router->get("{$prefix}", function () use ($request, $response) {
    $controller = new DashboardRouterController($request, $response);
    return $controller->index();
});

// ===========================================
// DASHBOARDS POR PERFIL
// ===========================================
$router->get("{$prefix}/prefeito", function () use ($request, $response) {
    $controller = new PrefeitoDashboardController($request, $response);
    return $controller->prefeito();
});

$router->get("{$prefix}/secretario", function () use ($request, $response) {
    $controller = new SecretarioDashboardController($request, $response);
    return $controller->secretario();
});

$router->get("{$prefix}/coordenador", function () use ($request, $response) {
    $controller = new CoordenadorDashboardController($request, $response);
    return $controller->coordenador();
});

$router->get("{$prefix}/tecnico", function () use ($request, $response) {
    $controller = new TecnicoDashboardController($request, $response);
    return $controller->tecnico();
});

$router->get("{$prefix}/controlador", function () use ($request, $response) {
    $controller = new ControladorDashboardController($request, $response);
    return $controller->controlador();
});

// ===========================================
// ALERTAS
// ===========================================
$router->get("{$prefix}/alertas", function () use ($request, $response) {
    $controller = new AlertaDashboardController($request, $response);
    return $controller->alertas();
});

$router->put("{$prefix}/alertas/{id}/lido", function (Request $req, Response $res) {
    $controller = new AlertaDashboardController($req, $res);
    return $controller->marcarAlertaLido((int) $req->getParam('id'));
});

$router->put("{$prefix}/alertas/lidos", function () use ($request, $response) {
    $controller = new AlertaDashboardController($request, $response);
    return $controller->marcarTodosLidos();
});

// ===========================================
// STATUS
// ===========================================
$router->get("{$prefix}/status", function () use ($request, $response) {
    $controller = new DashboardRouterController($request, $response);
    return $controller->status();
});
