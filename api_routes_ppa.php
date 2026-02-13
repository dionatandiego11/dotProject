<?php
/**
 * Rotas da API para o Sistema PPA
 * 
 * Incluir em api.php:
 * require_once 'api_routes_ppa.php';
 */

use DotProject\Api\Request;
use DotProject\Api\Response;
use DotProject\Api\Controller\ProjetoController;
use DotProject\Api\Controller\ProgramaController;
use DotProject\Api\Controller\PpaController;
use DotProject\Api\Controller\AcaoController;
use DotProject\Api\Controller\Dashboard\PrefeitoDashboardController;
use DotProject\Api\Controller\Dashboard\SecretarioDashboardController;
use DotProject\Api\Controller\Dashboard\CoordenadorDashboardController;
use DotProject\Api\Controller\Dashboard\TecnicoDashboardController;

// ===========================================
// ROTAS DE DASHBOARD
// ===========================================

$router->get('/v1/dashboard/executivo', function (Request $req, Response $res) {
    $controller = new PrefeitoDashboardController($req, $res);
    return $controller->prefeito();
});

$router->get('/v1/dashboard/secretario', function (Request $req, Response $res) {
    $controller = new SecretarioDashboardController($req, $res);
    return $controller->secretario();
});

$router->get('/v1/dashboard/coordenador', function (Request $req, Response $res) {
    $controller = new CoordenadorDashboardController($req, $res);
    return $controller->coordenador();
});

$router->get('/v1/dashboard/tecnico', function (Request $req, Response $res) {
    $controller = new TecnicoDashboardController($req, $res);
    return $controller->tecnico();
});

$router->get('/v1/dashboard/projetos-risco', function (Request $req, Response $res) {
    // Compatibilidade: endpoint legado reaproveita payload executivo.
    $controller = new PrefeitoDashboardController($req, $res);
    return $controller->prefeito();
});

// ===========================================
// ROTAS DE PPA
// ===========================================

$router->get('/v1/ppas', function (Request $req, Response $res) {
    $controller = new PpaController($req, $res);
    return $controller->index();
});

$router->get('/v1/ppas/{id}', function (Request $req, Response $res) {
    $controller = new PpaController($req, $res);
    return $controller->show();
});

$router->post('/v1/ppas', function (Request $req, Response $res) {
    $controller = new PpaController($req, $res);
    return $controller->store();
});

$router->put('/v1/ppas/{id}', function (Request $req, Response $res) {
    $controller = new PpaController($req, $res);
    return $controller->update();
});

$router->delete('/v1/ppas/{id}', function (Request $req, Response $res) {
    $controller = new PpaController($req, $res);
    return $controller->destroy();
});

// ===========================================
// ROTAS DE PROGRAMAS
// ===========================================

$router->get('/v1/programas', function (Request $req, Response $res) {
    $controller = new ProgramaController($req, $res);
    return $controller->index();
});

$router->get('/v1/programas/{id}', function (Request $req, Response $res) {
    $controller = new ProgramaController($req, $res);
    return $controller->show();
});

$router->post('/v1/programas', function (Request $req, Response $res) {
    $controller = new ProgramaController($req, $res);
    return $controller->store();
});

$router->put('/v1/programas/{id}', function (Request $req, Response $res) {
    $controller = new ProgramaController($req, $res);
    return $controller->update();
});

$router->delete('/v1/programas/{id}', function (Request $req, Response $res) {
    $controller = new ProgramaController($req, $res);
    return $controller->destroy();
});

// ===========================================
// ROTAS DE ACOES
// ===========================================

$router->get('/v1/acoes', function (Request $req, Response $res) {
    $controller = new AcaoController($req, $res);
    return $controller->index();
});

$router->get('/v1/acoes/{id}', function (Request $req, Response $res) {
    $controller = new AcaoController($req, $res);
    return $controller->show();
});

$router->post('/v1/acoes', function (Request $req, Response $res) {
    $controller = new AcaoController($req, $res);
    return $controller->store();
});

$router->put('/v1/acoes/{id}', function (Request $req, Response $res) {
    $controller = new AcaoController($req, $res);
    return $controller->update();
});

$router->delete('/v1/acoes/{id}', function (Request $req, Response $res) {
    $controller = new AcaoController($req, $res);
    return $controller->destroy();
});

// ===========================================
// ROTAS DE PROJETOS
// ===========================================

$router->get('/v1/projetos', function (Request $req, Response $res) {
    $controller = new ProjetoController($req, $res);
    $controller->index();
});

$router->get('/v1/projetos/{id}', function (Request $req, Response $res) {
    $controller = new ProjetoController($req, $res);
    $controller->show();
});

$router->post('/v1/projetos', function (Request $req, Response $res) {
    $controller = new ProjetoController($req, $res);
    $controller->store();
});

$router->put('/v1/projetos/{id}', function (Request $req, Response $res) {
    $controller = new ProjetoController($req, $res);
    $controller->update();
});

// Transicionar estado do projeto
$router->post('/v1/projetos/{id}/transicionar', function (Request $req, Response $res) {
    $controller = new ProjetoController($req, $res);
    $controller->transicionarEstado();
});

// Avançar etapa
$router->post('/v1/projetos/{id}/avancar-etapa', function (Request $req, Response $res) {
    $controller = new ProjetoController($req, $res);
    $controller->avancarEtapa();
});

// Listar etapas do projeto
$router->get('/v1/projetos/{id}/etapas', function (Request $req, Response $res) {
    $controller = new ProjetoController($req, $res);
    $controller->etapas();
});

// Atualizar etapa específica
$router->put('/v1/projetos/{id}/etapas/{etapaId}', function (Request $req, Response $res) {
    $controller = new ProjetoController($req, $res);
    $controller->atualizarEtapa();
});

// Concluir etapa
$router->post('/v1/projetos/{id}/etapas/{etapaId}/concluir', function (Request $req, Response $res) {
    $controller = new ProjetoController($req, $res);
    $controller->concluirEtapa();
});

// ===========================================
// ROTAS DE TAREFAS (extensão das existentes)
// ===========================================

// Listar tarefas do projeto
$router->get('/v1/projetos/{id}/tarefas', function (Request $req, Response $res) {
    $controller = new \DotProject\Api\Controller\TaskController($req, $res);
    $controller->porProjeto();
});

// Listar tarefas por etapa
$router->get('/v1/projetos/{id}/etapas/{etapaId}/tarefas', function (Request $req, Response $res) {
    $controller = new \DotProject\Api\Controller\TaskController($req, $res);
    $controller->porEtapa();
});

// Atualizar estado da tarefa
$router->put('/v1/tarefas/{id}/estado', function (Request $req, Response $res) {
    $controller = new \DotProject\Api\Controller\TaskController($req, $res);
    $controller->atualizarEstado();
});

// ===========================================
// METAS (Indicadores TCE)
// ===========================================

use DotProject\Api\Controller\MetaController;

$router->get('/v1/metas', function (Request $req, Response $res) {
    $controller = new MetaController($req, $res);
    return $controller->index();
});

$router->get('/v1/metas/resumo/{acaoId}', function (Request $req, Response $res) {
    $controller = new MetaController($req, $res);
    return $controller->resumo();
});

$router->get('/v1/metas/{id}', function (Request $req, Response $res) {
    $controller = new MetaController($req, $res);
    return $controller->show();
});

$router->post('/v1/metas', function (Request $req, Response $res) {
    $controller = new MetaController($req, $res);
    return $controller->store();
});

$router->put('/v1/metas/{id}', function (Request $req, Response $res) {
    $controller = new MetaController($req, $res);
    return $controller->update();
});

$router->delete('/v1/metas/{id}', function (Request $req, Response $res) {
    $controller = new MetaController($req, $res);
    return $controller->destroy();
});

// ===========================================
// ADMIN: Reprocessamento de Rollup + Saúde
// ===========================================

$router->post('/v1/admin/recomputar-rollup', function (Request $req, Response $res) {
    $db = \DotProject\Core\Database::getInstance();
    $tenant = \DotProject\Core\TenantContext::getInstance();

    $rollup = new \DotProject\Service\RollupService($db, $tenant);
    $saude = new \DotProject\Service\SaudeCalculator($db, $tenant);

    $statsRollup = $rollup->recomputarTudo();
    $statsSaude = $saude->recomputarSaudeGlobal();

    $res->json([
        'message' => 'Recomputação concluída.',
        'rollup' => $statsRollup,
        'saude' => $statsSaude,
    ]);
    return $res;
});

