<?php
/**
 * DotProject Authorization Middleware
 * 
 * Middleware de autorizacao baseado em RBAC (Role-Based Access Control).
 * Verifica permissoes do usuario autenticado em rotas protegidas.
 * 
 * @package DotProject\Api\Middleware
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Api\Middleware;

use DotProject\Api\Request;
use DotProject\Api\Response;
use DotProject\Core\Logger;
use DotProject\Entity\UserEntity;
use DotProject\Repository\UserRepository;
use DotProject\Service\AuthorizationService;

/**
 * Middleware de autorizacao
 */
class AuthorizationMiddleware
{
    /** @var array<string, array<string, string>> Mapeamento de rotas para permissoes */
    private static array $routePermissions = [];
    
    /**
     * Executa o middleware de autorizacao
     */
    public static function handle(Request $request, Response $response): bool
    {
        $userId = $request->getParam('_user_id');
        
        if ($userId === null) {
            return true;
        }
        
        $uri = $request->getUri();
        $method = $request->getMethod();
        
        $authService = AuthorizationService::getInstance();
        
        // Carrega usuario atual
        $user = self::loadUser((int) $userId);
        if ($user) {
            $authService->setCurrentUser($user);
        }
        
        // Verifica permissoes especificas da rota
        $requiredPermission = self::getRequiredPermission($uri, $method);
        
        if ($requiredPermission !== null) {
            $hasPermission = $authService->can(
                $requiredPermission['resource'],
                $requiredPermission['permission'],
                (int) $userId
            );
            
            if (!$hasPermission) {
                Logger::warning('Authorization denied', [
                    'user_id' => $userId,
                    'uri' => $uri,
                    'method' => $method,
                    'required' => $requiredPermission,
                ]);
                
                $response->json([
                    'error' => 'Forbidden',
                    'message' => 'You do not have permission to access this resource',
                    'required_permission' => $requiredPermission['permission'],
                    'resource' => $requiredPermission['resource'],
                ], 403)->send();
                
                return false;
            }
        }
        
        // Verifica acesso a recursos especificos
        $resourceCheck = self::checkResourceAccess($uri, $method, (int) $userId, $authService);
        
        if (!$resourceCheck) {
            Logger::warning('Resource access denied', [
                'user_id' => $userId,
                'uri' => $uri,
            ]);
            
            $response->json([
                'error' => 'Forbidden',
                'message' => 'You do not have access to this resource',
            ], 403)->send();
            
            return false;
        }
        
        // Armazena o servico de autorizacao na request
        $request->setParams(array_merge($request->getParams(), [
            '_auth_service' => $authService,
        ]));
        
        return true;
    }
    
    /**
     * Carrega usuario do repositorio
     */
    private static function loadUser(int $userId): ?UserEntity
    {
        $repository = new UserRepository();
        return $repository->find($userId);
    }
    
    /**
     * Obtem a permissao requerida para uma rota
     */
    private static function getRequiredPermission(string $uri, string $method): ?array
    {
        foreach (self::$routePermissions as $pattern => $permission) {
            if (self::matchRoute($uri, $pattern)) {
                return $permission;
            }
        }
        
        return self::inferPermissionFromRoute($uri, $method);
    }
    
    /**
     * Infere permissao baseada no padrao da URL e metodo HTTP
     */
    private static function inferPermissionFromRoute(string $uri, string $method): ?array
    {
        $resourcePatterns = [
            '/v1/projects' => AuthorizationService::RESOURCE_PROJECT,
            '/v1/tasks' => AuthorizationService::RESOURCE_TASK,
            '/v1/users' => AuthorizationService::RESOURCE_USER,
            '/v1/files' => AuthorizationService::RESOURCE_FILE,
            '/v1/calendar' => AuthorizationService::RESOURCE_CALENDAR,
            '/v1/reports' => AuthorizationService::RESOURCE_REPORT,
            '/v1/companies' => AuthorizationService::RESOURCE_COMPANY,
        ];
        
        $resource = null;
        foreach ($resourcePatterns as $pattern => $res) {
            if (str_starts_with($uri, $pattern)) {
                $resource = $res;
                break;
            }
        }
        
        if ($resource === null) {
            return null;
        }
        
        $permission = match ($method) {
            'GET' => AuthorizationService::PERMISSION_VIEW,
            'POST' => AuthorizationService::PERMISSION_CREATE,
            'PUT', 'PATCH' => AuthorizationService::PERMISSION_EDIT,
            'DELETE' => AuthorizationService::PERMISSION_DELETE,
            default => null,
        };
        
        if ($permission === null) {
            return null;
        }
        
        return [
            'resource' => $resource,
            'permission' => $permission,
        ];
    }
    
    /**
     * Verifica acesso ao recurso especifico
     */
    private static function checkResourceAccess(
        string $uri, 
        string $method, 
        int $userId,
        AuthorizationService $authService
    ): bool {
        $resourceId = self::extractResourceId($uri);
        
        if ($resourceId === null) {
            return true;
        }
        
        if (str_contains($uri, '/v1/projects/')) {
            $permission = in_array($method, ['PUT', 'PATCH', 'DELETE']) 
                ? ($method === 'DELETE' ? AuthorizationService::PERMISSION_DELETE : AuthorizationService::PERMISSION_EDIT)
                : AuthorizationService::PERMISSION_VIEW;
                
            return $authService->canAccessProject($resourceId, $permission, $userId);
        }
        
        if (str_contains($uri, '/v1/tasks/')) {
            $permission = in_array($method, ['PUT', 'PATCH', 'DELETE'])
                ? ($method === 'DELETE' ? AuthorizationService::PERMISSION_DELETE : AuthorizationService::PERMISSION_EDIT)
                : AuthorizationService::PERMISSION_VIEW;
                
            return $authService->canAccessTask($resourceId, $permission, $userId);
        }
        
        return true;
    }
    
    /**
     * Extrai ID do recurso da URL
     */
    private static function extractResourceId(string $uri): ?int
    {
        if (preg_match('#/v1/\w+/(\d+)#', $uri, $matches)) {
            return (int) $matches[1];
        }
        
        return null;
    }
    
    /**
     * Verifica se URI corresponde ao padrao
     */
    private static function matchRoute(string $uri, string $pattern): bool
    {
        if (str_ends_with($pattern, '/*')) {
            $prefix = substr($pattern, 0, -1);
            return str_starts_with($uri, $prefix);
        }
        
        return $uri === $pattern;
    }
    
    /**
     * Registra permissao para uma rota
     */
    public static function requirePermission(string $routePattern, string $resource, string $permission): void
    {
        self::$routePermissions[$routePattern] = [
            'resource' => $resource,
            'permission' => $permission,
        ];
    }
    
    /**
     * Registra multiplas permissoes
     */
    public static function registerPermissions(array $permissions): void
    {
        foreach ($permissions as $route => $config) {
            self::$routePermissions[$route] = $config;
        }
    }
    
    /**
     * Limpa todas as permissoes registradas
     */
    public static function clearPermissions(): void
    {
        self::$routePermissions = [];
    }
    
    /**
     * Obtem todas as permissoes registradas
     */
    public static function getRegisteredPermissions(): array
    {
        return self::$routePermissions;
    }
}
