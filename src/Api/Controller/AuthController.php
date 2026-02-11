<?php
/**
 * DotProject Auth Controller
 *
 * Controller para autenticacao na API.
 *
 * @package DotProject\Api\Controller
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Api\Controller;

use DotProject\Api\Response;
use DotProject\Auth\JwtManager;

/**
 * Controller de autenticacao
 */
class AuthController extends BaseController
{
    private ?bool $hasUserStatusColumn = null;

    /**
     * POST /v1/auth/login
     *
     * Autentica um usuario e retorna um token JWT
     */
    public function login(): Response
    {
        $errors = $this->validateRequired(['username', 'password']);
        if ($errors !== null) {
            return $this->response->validationError($errors);
        }

        $username = (string) $this->request->getBodyParam('username');
        $password = (string) $this->request->getBodyParam('password');
        $statusSelect = $this->supportsUserStatusColumn() ? ', u.user_status' : '';

        // Busca o usuario no banco
        $user = $this->db->fetchOneParams(sprintf(
            "SELECT u.user_id, u.user_username, u.user_password,
                    c.contact_first_name, c.contact_last_name, c.contact_email%s
             FROM %s u
             LEFT JOIN %s c ON c.contact_id = u.user_contact
             WHERE u.user_username = ?",
            $statusSelect,
            $this->db->table('users'),
            $this->db->table('contacts')
        ), [$username]);

        if ($user === null || $this->isInactiveUser($user)) {
            return $this->error('Invalid credentials', Response::HTTP_UNAUTHORIZED);
        }

        // Verifica a senha (compativel com MD5 legado e password_hash moderno)
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

        $refreshToken = (string) $this->request->getBodyParam('refresh_token');

        $jwt = JwtManager::getInstance();
        $payload = $jwt->verify($refreshToken);

        if ($payload === null || ($payload['type'] ?? '') !== 'refresh') {
            return $this->error('Invalid refresh token', Response::HTTP_UNAUTHORIZED);
        }

        $userId = (int) ($payload['user_id'] ?? 0);
        if ($userId <= 0) {
            return $this->error('Invalid refresh token', Response::HTTP_UNAUTHORIZED);
        }

        $statusSelect = $this->supportsUserStatusColumn() ? ', u.user_status' : '';

        // Busca dados atualizados do usuario
        $user = $this->db->fetchOneParams(sprintf(
            "SELECT u.user_id, u.user_username, u.user_company,
                    c.contact_first_name, c.contact_last_name, c.contact_email%s,
                    un.unidade_nome
             FROM %s u
             LEFT JOIN %s c ON c.contact_id = u.user_contact
             LEFT JOIN dotp_unidades_organizacionais un ON un.unidade_id = u.user_company
             WHERE u.user_id = ?",
            $statusSelect,
            $this->db->table('users'),
            $this->db->table('contacts')
        ), [$userId]);

        if ($user === null) {
            return $this->error('User not found', Response::HTTP_UNAUTHORIZED);
        }

        if ($this->isInactiveUser($user)) {
            return $this->error('User is inactive', Response::HTTP_UNAUTHORIZED);
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
     * Retorna dados do usuario autenticado
     */
    public function me(): Response
    {
        $userId = $this->getUserId();

        if ($userId === null) {
            return $this->response->unauthorized();
        }

        $statusSelect = $this->supportsUserStatusColumn() ? ', u.user_status' : '';

        $user = $this->db->fetchOneParams(sprintf(
            "SELECT u.user_id, u.user_username, u.user_company,
                    c.contact_first_name, c.contact_last_name, c.contact_email%s,
                    un.unidade_nome
             FROM %s u
             LEFT JOIN %s c ON c.contact_id = u.user_contact
             LEFT JOIN dotp_unidades_organizacionais un ON un.unidade_id = u.user_company
             WHERE u.user_id = ?",
            $statusSelect,
            $this->db->table('users'),
            $this->db->table('contacts')
        ), [$userId]);

        if ($user === null) {
            return $this->notFound('User not found');
        }

        if ($this->isInactiveUser($user)) {
            return $this->response->unauthorized('User is inactive');
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

    private function supportsUserStatusColumn(): bool
    {
        if ($this->hasUserStatusColumn !== null) {
            return $this->hasUserStatusColumn;
        }

        try {
            $usersTable = (string) $this->db->table('users');
            $exists = (int) ($this->db->fetchValue(
                "SELECT COUNT(*)
                 FROM information_schema.columns
                 WHERE table_schema = DATABASE()
                   AND table_name = ?
                   AND column_name = 'user_status'",
                [$usersTable]
            ) ?? 0);

            $this->hasUserStatusColumn = $exists > 0;
        } catch (\Throwable) {
            $this->hasUserStatusColumn = false;
        }

        return $this->hasUserStatusColumn;
    }

    /**
     * @param array<string, mixed> $user
     */
    private function isInactiveUser(array $user): bool
    {
        if (!$this->supportsUserStatusColumn()) {
            return false;
        }

        return (int) ($user['user_status'] ?? 0) !== 0;
    }
}
