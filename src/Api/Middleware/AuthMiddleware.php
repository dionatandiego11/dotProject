<?php
/**
 * DotProject Auth Middleware
 *
 * Middleware de autenticacao JWT para a API.
 *
 * @package DotProject\Api\Middleware
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Api\Middleware;

use DotProject\Api\Request;
use DotProject\Api\Response;
use DotProject\Auth\JwtManager;
use DotProject\Repository\UserRepository;
use DotProject\Service\AuthorizationService;

/**
 * Middleware de autenticacao
 */
class AuthMiddleware
{
    /** @var array<string> Rotas publicas que nao precisam de autenticacao */
    private static array $publicRoutes = [
        '/v1/auth/login',
        '/v1/auth/register',
        '/v1/health',
    ];

    /**
     * Executa o middleware
     *
     * @return bool|array False para interromper, array com dados do usuario, ou true para continuar
     */
    public static function handle(Request $request, Response $response): bool|array
    {
        $uri = $request->getUri();

        // Rotas publicas nao precisam de autenticacao
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

        $userId = (int) ($payload['user_id'] ?? 0);
        if ($userId <= 0) {
            $response->unauthorized('Invalid token payload')->send();
            return false;
        }

        $userRepository = new UserRepository();
        $user = $userRepository->find($userId);
        if ($user === null) {
            $response->unauthorized('User not found')->send();
            return false;
        }

        if ($userRepository->supportsUserStatus() && !$user->isActive()) {
            $response->unauthorized('User is inactive')->send();
            return false;
        }

        // Armazena dados do usuario autenticado na request
        $request->setParams(array_merge($request->getParams(), [
            '_user_id' => $userId,
            '_user_data' => array_merge($payload, [
                'username' => $user->getUsername(),
                'name' => $user->getFullName(),
                'email' => $user->getEmail(),
            ]),
        ]));

        // Seta o usuario no AuthorizationService para uso nos services
        AuthorizationService::getInstance()->setCurrentUser($user);

        return true;
    }

    /**
     * Adiciona uma rota publica
     */
    public static function addPublicRoute(string $route): void
    {
        self::$publicRoutes[] = $route;
    }
}
