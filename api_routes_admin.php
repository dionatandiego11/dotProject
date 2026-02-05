<?php
/**
 * Rotas da API para Administração da Estrutura Organizacional
 * 
 * @package DotProject\Api
 */

declare(strict_types=1);

use DotProject\Api\Request;
use DotProject\Api\Response;
use DotProject\Api\Controller\AdminController;

error_log('DEBUG api_routes_admin.php - Arquivo carregado');

$prefix = '/v1/admin';

// Obter request e response do contexto global
$request = $GLOBALS['request'] ?? null;
$response = $GLOBALS['response'] ?? null;

if (!$request || !$response) {
    // Fallback: criar novas instâncias se não estiverem disponíveis
    $request = new Request();
    $response = new Response();
}

// ===========================================
// DASHBOARD
// ===========================================
$router->get("{$prefix}/dashboard", function() use ($request, $response) {
    $controller = new AdminController($request, $response);
    return $controller->dashboard();
});

// ===========================================
// NÍVEIS HIERÁRQUICOS
// ===========================================
$router->get("{$prefix}/niveis", function() use ($request, $response) {
    $controller = new AdminController($request, $response);
    return $controller->listNiveis();
});

$router->get("{$prefix}/niveis/{id}", function(Request $req, Response $res) {
    $controller = new AdminController($req, $res);
    return $controller->getNivel((int) $req->getParam('id'));
});

$router->post("{$prefix}/niveis", function() use ($request, $response) {
    $controller = new AdminController($request, $response);
    return $controller->createNivel();
});

$router->put("{$prefix}/niveis/{id}", function(Request $req, Response $res) {
    $controller = new AdminController($req, $res);
    return $controller->updateNivel((int) $req->getParam('id'));
});

$router->delete("{$prefix}/niveis/{id}", function(Request $req, Response $res) {
    $controller = new AdminController($req, $res);
    return $controller->deleteNivel((int) $req->getParam('id'));
});

$router->put("{$prefix}/niveis/reordenar", function() use ($request, $response) {
    $controller = new AdminController($request, $response);
    return $controller->reordenarNiveis();
});

// ===========================================
// UNIDADES ORGANIZACIONAIS
// ===========================================
$router->get("{$prefix}/unidades", function() use ($request, $response) {
    $controller = new AdminController($request, $response);
    return $controller->listUnidades();
});

$router->get("{$prefix}/unidades/arvore", function() use ($request, $response) {
    $controller = new AdminController($request, $response);
    return $controller->getArvore();
});

$router->get("{$prefix}/unidades/{id}", function(Request $req, Response $res) {
    $controller = new AdminController($req, $res);
    return $controller->getUnidade((int) $req->getParam('id'));
});

$router->post("{$prefix}/unidades", function() use ($request, $response) {
    error_log('DEBUG api_routes_admin.php - Rota POST /unidades chamada');
    $controller = new AdminController($request, $response);
    return $controller->createUnidade();
});

$router->put("{$prefix}/unidades/{id}", function(Request $req, Response $res) {
    $controller = new AdminController($req, $res);
    return $controller->updateUnidade((int) $req->getParam('id'));
});

$router->delete("{$prefix}/unidades/{id}", function(Request $req, Response $res) {
    $controller = new AdminController($req, $res);
    return $controller->deleteUnidade((int) $req->getParam('id'));
});

$router->put("{$prefix}/unidades/{id}/mover", function(Request $req, Response $res) {
    $controller = new AdminController($req, $res);
    return $controller->moverUnidade((int) $req->getParam('id'));
});

$router->get("{$prefix}/unidades/{id}/subordinadas", function(Request $req, Response $res) {
    $controller = new AdminController($req, $res);
    return $controller->getSubordinadas((int) $req->getParam('id'));
});

// ===========================================
// VÍNCULOS USUÁRIO-UNIDADE
// ===========================================
$router->get("{$prefix}/vinculos", function() use ($request, $response) {
    $controller = new AdminController($request, $response);
    return $controller->listVinculos();
});

$router->post("{$prefix}/vinculos", function() use ($request, $response) {
    $controller = new AdminController($request, $response);
    return $controller->createVinculo();
});

$router->put("{$prefix}/vinculos/{id}", function(Request $req, Response $res) {
    $controller = new AdminController($req, $res);
    return $controller->updateVinculo((int) $req->getParam('id'));
});

$router->delete("{$prefix}/vinculos/{id}", function(Request $req, Response $res) {
    $controller = new AdminController($req, $res);
    return $controller->deleteVinculo((int) $req->getParam('id'));
});

$router->put("{$prefix}/vinculos/{id}/principal", function(Request $req, Response $res) {
    $controller = new AdminController($req, $res);
    return $controller->definirPrincipal((int) $req->getParam('id'));
});

// ===========================================
// USUARIOS
// ===========================================
$router->get("{$prefix}/usuarios", function() use ($request, $response) {
    $controller = new AdminController($request, $response);
    return $controller->listUsuarios();
});

$router->get("{$prefix}/usuarios/{id}", function(Request $req, Response $res) {
    $controller = new AdminController($req, $res);
    return $controller->getUsuario((int) $req->getParam('id'));
});

$router->post("{$prefix}/usuarios", function() use ($request, $response) {
    $controller = new AdminController($request, $response);
    return $controller->createUsuario();
});

$router->put("{$prefix}/usuarios/{id}", function(Request $req, Response $res) {
    $controller = new AdminController($req, $res);
    return $controller->updateUsuario((int) $req->getParam('id'));
});

$router->delete("{$prefix}/usuarios/{id}", function(Request $req, Response $res) {
    $controller = new AdminController($req, $res);
    return $controller->deleteUsuario((int) $req->getParam('id'));
});

// ===========================================
// MATRIZ DE PERMISSÕES
// ===========================================
$router->get("{$prefix}/permissoes/matriz", function() use ($request, $response) {
    $controller = new AdminController($request, $response);
    return $controller->getMatrizPermissoes();
});

$router->put("{$prefix}/permissoes/matriz", function() use ($request, $response) {
    $controller = new AdminController($request, $response);
    return $controller->updateMatrizPermissoes();
});

// ===========================================
// ORGANOGRAMA
// ===========================================
$router->get("{$prefix}/organograma", function() use ($request, $response) {
    $controller = new AdminController($request, $response);
    return $controller->getArvore();
});
