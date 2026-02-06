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
        $user = $this->db->fetchOneParams(sprintf(
            "SELECT u.user_id, u.user_username, u.user_password,
                    c.contact_first_name, c.contact_last_name, c.contact_email
             FROM %s u
             LEFT JOIN %s c ON c.contact_id = u.user_contact
             WHERE u.user_username = ?",
            $this->db->table('users'),
            $this->db->table('contacts')
        ), [$username]);

        if ($user === null) {
            return $this->error('Invalid credentials', Response::HTTP_UNAUTHORIZED);
        }

        // Verifica a senha (compatÃ­vel com MD5 legado e password_hash moderno)
        $stored = trim((string) $user['user_password']);
        $verified = false;

        if (strlen($stored) === 32 && md5($password) === $stored) {
            $verified = true;
            // Migra para password_hash no login bem-sucedido
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            if ($newHash) {
                $this->db->update(
                    'users',
                    ['user_password' => $newHash],
                    sprintf('user_id = %d', (int) $user['user_id'])
                );
            }
        } elseif (password_get_info($stored)['algo'] !== 0) {
            $verified = password_verify($password, $stored);
        }

        if (!$verified) {
            return $this->error('Invalid credentials', Response::HTTP_UNAUTHORIZED);
        }

        // Gera o token JWT
        $jwt = JwtManager::getInstance();
        $token = $jwt->generate([
            'user_id' => (int) $user['user_id'],
            'username' => $user['user_username'],
            'name' => trim(($user['contact_first_name'] ?? '') . ' ' . ($user['contact_last_name'] ?? '')),
            'email' => $user['contact_email'] ?? null,
        ]);

        $refreshToken = $jwt->generateRefreshToken((int) $user['user_id']);

        return $this->json([
            'token' => $token,
            'refresh_token' => $refreshToken,
            'user' => [
                'id' => (int) $user['user_id'],
                'username' => $user['user_username'],
                'name' => trim(($user['contact_first_name'] ?? '') . ' ' . ($user['contact_last_name'] ?? '')),
                'email' => $user['contact_email'] ?? null,
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
        $user = $this->db->fetchOneParams(sprintf(
            "SELECT u.user_id, u.user_username, u.user_company,
                    c.contact_first_name, c.contact_last_name, c.contact_email,
                    un.unidade_nome
             FROM %s u
             LEFT JOIN %s c ON c.contact_id = u.user_contact
             LEFT JOIN dotp_unidades_organizacionais un ON un.unidade_id = u.user_company
             WHERE u.user_id = ?",
            $this->db->table('users'),
            $this->db->table('contacts')
        ), [$userId]);

        if ($user === null) {
            return $this->error('User not found', Response::HTTP_UNAUTHORIZED);
        }

        // Gera novo token
        $newToken = $jwt->generate([
            'user_id' => (int) $user['user_id'],
            'username' => $user['user_username'],
            'name' => trim(($user['contact_first_name'] ?? '') . ' ' . ($user['contact_last_name'] ?? '')),
            'email' => $user['contact_email'] ?? null,
        ]);
        $newRefreshToken = $jwt->generateRefreshToken((int) $user['user_id']);

        return $this->json([
            'token' => $newToken,
            'refresh_token' => $newRefreshToken,
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

        $user = $this->db->fetchOneParams(sprintf(
            "SELECT u.user_id, u.user_username, u.user_company,
                    c.contact_first_name, c.contact_last_name, c.contact_email,
                    un.unidade_nome
             FROM %s u
             LEFT JOIN %s c ON c.contact_id = u.user_contact
             LEFT JOIN dotp_unidades_organizacionais un ON un.unidade_id = u.user_company
             WHERE u.user_id = ?",
            $this->db->table('users'),
            $this->db->table('contacts')
        ), [$userId]);

        if ($user === null) {
            return $this->notFound('User not found');
        }

        $unidadeId = $user['user_company'] ? (int) $user['user_company'] : null;

        return $this->json([
            'id' => (int) $user['user_id'],
            'username' => $user['user_username'],
            'first_name' => $user['contact_first_name'] ?? null,
            'last_name' => $user['contact_last_name'] ?? null,
            'email' => $user['contact_email'] ?? null,
            'unidade_id' => $unidadeId,
            'unidade' => [
                'id' => $unidadeId,
                'nome' => $user['unidade_nome'] ?? null,
            ],
            'company_id' => $unidadeId,
        ]);
    }
}
