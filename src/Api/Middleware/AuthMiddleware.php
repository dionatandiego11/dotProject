<?php
/**
 * DotProject Auth Middleware
 * 
 * Middleware de autenticação JWT para a API.
 * 
 * @package DotProject\Api\Middleware
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Api\Middleware;

use DotProject\Api\Request;
use DotProject\Api\Response;
use DotProject\Auth\JwtManager;
use DotProject\Entity\UserEntity;
use DotProject\Service\AuthorizationService;

/**
 * Middleware de autenticação
 */
class AuthMiddleware
{
    /** @var array<string> Rotas públicas que não precisam de autenticação */
    private static array $publicRoutes = [
        '/v1/auth/login',
        '/v1/auth/register',
        '/v1/health',
    ];

    /**
     * Executa o middleware
     * 
     * @return bool|array False para interromper, array com dados do usuário, ou true para continuar
     */
    public static function handle(Request $request, Response $response): bool|array
    {
        $uri = $request->getUri();

        // Rotas públicas não precisam de autenticação
        foreach (self::$publicRoutes as $publicRoute) {
            if ($uri === $publicRoute) {
                return true;
            }
        }

        // Verifica token
        $token = $request->getBearerToken();

        if ($token === null) {
            $response->unauthorized('Token not provided')->send();
            return false;
        }

        $jwt = JwtManager::getInstance();
        $payload = $jwt->verify($token);

        if ($payload === null) {
            $response->unauthorized('Invalid or expired token')->send();
            return false;
        }

        // Armazena dados do usuário autenticado na request
        $userId = $payload['user_id'] ?? null;
        $request->setParams(array_merge($request->getParams(), [
            '_user_id' => $userId,
            '_user_data' => $payload,
        ]));

        // Seta o usuário no AuthorizationService para uso nos services
        if ($userId !== null) {
            $user = new UserEntity();
            $user->setId($userId);
            $user->setUsername($payload['username'] ?? '');
            $user->setFirstName($payload['name'] ?? '');
            $user->setEmail($payload['email'] ?? '');
            
            AuthorizationService::getInstance()->setCurrentUser($user);
        }

        return true;
    }

    /**
     * Adiciona uma rota pública
     */
    public static function addPublicRoute(string $route): void
    {
        self::$publicRoutes[] = $route;
    }
}
