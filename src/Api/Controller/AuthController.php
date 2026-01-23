<?php
/**
 * DotProject Auth Controller
 * 
 * Controller para autenticação na API.
 * 
 * @package DotProject\Api\Controller
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Api\Controller;

use DotProject\Api\Request;
use DotProject\Api\Response;
use DotProject\Auth\JwtManager;

/**
 * Controller de autenticação
 */
class AuthController extends BaseController
{
    /**
     * POST /v1/auth/login
     * 
     * Autentica um usuário e retorna um token JWT
     */
    public function login(): Response
    {
        $errors = $this->validateRequired(['username', 'password']);
        if ($errors !== null) {
            return $this->response->validationError($errors);
        }

        $username = $this->request->getBodyParam('username');
        $password = $this->request->getBodyParam('password');

        // Busca o usuário no banco
        $user = $this->db->fetchOne(sprintf(
            "SELECT user_id, user_username, user_password, user_first_name, user_last_name, user_email 
             FROM %s 
             WHERE user_username = %s",
            $this->db->table('users'),
            $this->db->quote($username)
        ));

        if ($user === null) {
            return $this->error('Invalid credentials', Response::HTTP_UNAUTHORIZED);
        }

        // Verifica a senha (dotProject usa MD5)
        $passwordHash = md5($password);
        if ($user['user_password'] !== $passwordHash) {
            return $this->error('Invalid credentials', Response::HTTP_UNAUTHORIZED);
        }

        // Gera o token JWT
        $jwt = JwtManager::getInstance();
        $token = $jwt->generate([
            'user_id' => (int) $user['user_id'],
            'username' => $user['user_username'],
            'name' => $user['user_first_name'] . ' ' . $user['user_last_name'],
            'email' => $user['user_email'],
        ]);

        $refreshToken = $jwt->generateRefreshToken((int) $user['user_id']);

        return $this->json([
            'token' => $token,
            'refresh_token' => $refreshToken,
            'user' => [
                'id' => (int) $user['user_id'],
                'username' => $user['user_username'],
                'name' => $user['user_first_name'] . ' ' . $user['user_last_name'],
                'email' => $user['user_email'],
            ],
        ]);
    }

    /**
     * POST /v1/auth/refresh
     * 
     * Renova o token de acesso usando o refresh token
     */
    public function refresh(): Response
    {
        $errors = $this->validateRequired(['refresh_token']);
        if ($errors !== null) {
            return $this->response->validationError($errors);
        }

        $refreshToken = $this->request->getBodyParam('refresh_token');

        $jwt = JwtManager::getInstance();
        $payload = $jwt->verify($refreshToken);

        if ($payload === null || ($payload['type'] ?? '') !== 'refresh') {
            return $this->error('Invalid refresh token', Response::HTTP_UNAUTHORIZED);
        }

        $userId = $payload['user_id'];

        // Busca dados atualizados do usuário
        $user = $this->db->fetchOne(sprintf(
            "SELECT user_id, user_username, user_first_name, user_last_name, user_email 
             FROM %s 
             WHERE user_id = %d",
            $this->db->table('users'),
            $userId
        ));

        if ($user === null) {
            return $this->error('User not found', Response::HTTP_UNAUTHORIZED);
        }

        // Gera novo token
        $newToken = $jwt->generate([
            'user_id' => (int) $user['user_id'],
            'username' => $user['user_username'],
            'name' => $user['user_first_name'] . ' ' . $user['user_last_name'],
            'email' => $user['user_email'],
        ]);

        return $this->json([
            'token' => $newToken,
        ]);
    }

    /**
     * GET /v1/auth/me
     * 
     * Retorna dados do usuário autenticado
     */
    public function me(): Response
    {
        $userId = $this->getUserId();

        if ($userId === null) {
            return $this->response->unauthorized();
        }

        $user = $this->db->fetchOne(sprintf(
            "SELECT user_id, user_username, user_first_name, user_last_name, user_email, user_company 
             FROM %s 
             WHERE user_id = %d",
            $this->db->table('users'),
            $userId
        ));

        if ($user === null) {
            return $this->notFound('User not found');
        }

        return $this->json([
            'id' => (int) $user['user_id'],
            'username' => $user['user_username'],
            'first_name' => $user['user_first_name'],
            'last_name' => $user['user_last_name'],
            'email' => $user['user_email'],
            'company_id' => $user['user_company'] ? (int) $user['user_company'] : null,
        ]);
    }
}
