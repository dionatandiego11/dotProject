<?php
/**
 * Middleware de Autorização Hierárquica
 * Verifica permissões baseado na estrutura organizacional
 * 
 * @package DotProject\Api\Middleware
 */

declare(strict_types=1);

namespace DotProject\Api\Middleware;

use DotProject\Core\Request;
use DotProject\Core\Response;
use DotProject\Service\PermissionService;
use DotProject\Repository\UsuarioUnidadeRepository;

class HierarquiaMiddleware
{
    private PermissionService $permissionService;
    private UsuarioUnidadeRepository $vinculoRepo;
    
    public function __construct()
    {
        $this->permissionService = new PermissionService();
        $this->vinculoRepo = new UsuarioUnidadeRepository();
    }
    
    /**
     * Middleware handler
     * Verifica se o usuário tem permissão para acessar o recurso
     */
    public function handle(Request $request, Response $response, callable $next, array $params = []): Response
    {
        $userId = $request->getAttribute('user_id');
        
        if (!$userId) {
            return $response->json([
                'error' => 'Não autenticado',
                'message' => 'Token de autenticação não encontrado ou inválido',
            ], 401);
        }
        
        // Verifica se usuário tem vínculo ativo
        $vinculo = $this->vinculoRepo->findPrincipal($userId);
        
        if (!$vinculo) {
            return $response->json([
                'error' => 'Sem vínculo',
                'message' => 'Usuário não está vinculado a nenhuma unidade organizacional',
            ], 403);
        }
        
        // Adiciona informações do escopo ao request
        $escopo = $this->permissionService->getEscopoDados($userId);
        $request->setAttribute('escopo', $escopo);
        $request->setAttribute('role', $vinculo['vinculo_role']);
        $request->setAttribute('unidade_id', $vinculo['vinculo_unidade_id']);
        
        // Se especificado recurso e ação, verifica permissão
        if (!empty($params['recurso']) && !empty($params['acao'])) {
            $recursoId = $params['recurso_id'] ?? null;
            
            if (!$this->permissionService->can($userId, $params['recurso'], $params['acao'], $recursoId)) {
                return $response->json([
                    'error' => 'Sem permissão',
                    'message' => "Você não tem permissão para {$params['acao']} em {$params['recurso']}",
                ], 403);
            }
        }
        
        return $next($request, $response);
    }
    
    /**
     * Verifica permissão específica
     */
    public function requirePermission(string $recurso, string $acao): callable
    {
        return function (Request $request, Response $response, callable $next) use ($recurso, $acao) {
            return $this->handle($request, $response, $next, [
                'recurso' => $recurso,
                'acao' => $acao,
            ]);
        };
    }
    
    /**
     * Verifica se é admin (Prefeito ou Controlador)
     */
    public function requireAdmin(Request $request, Response $response, callable $next): Response
    {
        $userId = $request->getAttribute('user_id');
        $role = $request->getAttribute('role');
        
        if (!in_array($role, [
            PermissionService::ROLE_PREFEITO, 
            PermissionService::ROLE_CONTROLADOR
        ], true)) {
            return $response->json([
                'error' => 'Acesso restrito',
                'message' => 'Esta área é restrita a administradores',
            ], 403);
        }
        
        return $next($request, $response);
    }
    
    /**
     * Verifica se é gestor (Prefeito, Secretário ou Coordenador)
     */
    public function requireGestor(Request $request, Response $response, callable $next): Response
    {
        $role = $request->getAttribute('role');
        
        if (!in_array($role, [
            PermissionService::ROLE_PREFEITO,
            PermissionService::ROLE_SECRETARIO,
            PermissionService::ROLE_COORDENADOR,
        ], true)) {
            return $response->json([
                'error' => 'Acesso restrito',
                'message' => 'Esta área é restrita a gestores',
            ], 403);
        }
        
        return $next($request, $response);
    }
    
    /**
     * Filtro de unidades no escopo
     * Adiciona where clause nas queries
     */
    public function filtrarPorEscopo(string $tabelaAlias = 'u'): callable
    {
        return function (Request $request, Response $response, callable $next) use ($tabelaAlias) {
            $userId = $request->getAttribute('user_id');
            $filtro = $this->permissionService->getFiltroUnidades($userId, $tabelaAlias);
            
            $request->setAttribute('filtro_unidades', $filtro);
            
            return $next($request, $response);
        };
    }
}
