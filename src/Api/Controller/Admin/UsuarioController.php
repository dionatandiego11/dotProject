<?php
/**
 * Controller for User management (Usuários).
 *
 * @package DotProject\Api\Controller\Admin
 */

declare(strict_types=1);

namespace DotProject\Api\Controller\Admin;

use DotProject\Api\Controller\BaseController;
use DotProject\Api\Request;
use DotProject\Api\Response;
use DotProject\Repository\UserRepository;
use DotProject\Repository\UsuarioUnidadeRepository;
use DotProject\Core\Logger;
use DotProject\Core\TenantContext;
use DotProject\Service\UserService;

class UsuarioController extends BaseController
{
    private UserRepository $userRepo;
    private UsuarioUnidadeRepository $vinculoRepo;
    private UserService $userService;
    /** @var array<string, bool> */
    private array $columnPresenceCache = [];

    public function __construct(Request $request, Response $response)
    {
        parent::__construct($request, $response);
        $this->userRepo = new UserRepository();
        $this->vinculoRepo = new UsuarioUnidadeRepository();
        $this->userService = new UserService();
        Logger::debug('UsuarioController inicializado');
    }

    /**
     * GET /api/v1/admin/usuarios
     */
    public function listUsuarios(): Response
    {
        $query = (string) ($this->request->getQuery('q') ?? '');
        $includeInactive = $this->normalizeBool($this->request->getQuery('include_inactive', false));

        if ($query !== '') {
            $users = $this->userService->searchUsers($query);
        } else {
            if ($includeInactive) {
                $table = $this->db->table('users');
                $contactsTable = $this->db->table('contacts');
                $sql = "SELECT u.*, c.contact_first_name, c.contact_last_name, c.contact_email
                        FROM {$table} u
                        LEFT JOIN {$contactsTable} c ON c.contact_id = u.user_contact" . $this->tenantAndCondition($contactsTable, 'c') . "
                        WHERE 1=1" . $this->tenantAndCondition($table, 'u') . "
                        ORDER BY u.user_username";
                $rows = $this->db->fetchAll($sql);
                $users = array_map(fn($row) => $this->userRepo->hydrateRow($row), $rows);
            } else {
                $users = $this->userRepo->findActive();
            }
        }

        return $this->json([
            'data' => array_map(fn($u) => $u->toArray(), $users),
        ]);
    }

    /**
     * GET /api/v1/admin/usuarios/{id}
     */
    public function getUsuario(int $id): Response
    {
        $user = $this->userRepo->find($id);

        if (!$user) {
            return $this->notFound('Usuario nao encontrado');
        }

        $data = $user->toArray();
        $data['vinculos'] = $this->vinculoRepo->findByUsuario($id);

        return $this->json([
            'data' => $data,
        ]);
    }

    /**
     * POST /api/v1/admin/usuarios
     */
    public function createUsuario(): Response
    {
        $data = $this->request->getJsonBody();

        if (empty($data['user_username'])) {
            return $this->validationError(['user_username' => 'Username e obrigatorio']);
        }
        if (!array_key_exists('user_password', $data) || trim((string) $data['user_password']) === '') {
            return $this->validationError(['user_password' => 'Senha e obrigatoria']);
        }

        $currentUserId = $this->getUserId() ?? 0;

        try {
            $user = $this->userService->createUser($data, $currentUserId);
        } catch (\InvalidArgumentException $e) {
            return $this->validationError(['user' => $e->getMessage()]);
        }

        if (!$user) {
            $dbError = $this->db->getError();
            $message = 'Falha ao criar usuario';
            if (!empty($dbError)) {
                $message .= ': ' . $dbError;
            }
            return $this->error($message, 500);
        }

        return $this->json([
            'message' => 'Usuario criado com sucesso',
            'data' => $user->toArray(),
        ], 201);
    }

    /**
     * PUT /api/v1/admin/usuarios/{id}
     */
    public function updateUsuario(int $id): Response
    {
        $data = $this->request->getJsonBody();
        $data['id'] = $id;

        try {
            $user = $this->userService->updateUser($id, $data);
        } catch (\InvalidArgumentException $e) {
            return $this->validationError(['user' => $e->getMessage()]);
        }

        if (!$user) {
            $dbError = $this->db->getError();
            $message = 'Usuario nao encontrado';
            if (!empty($dbError)) {
                $message .= ': ' . $dbError;
            }
            return $this->notFound($message);
        }

        return $this->json([
            'message' => 'Usuario atualizado com sucesso',
            'data' => $user->toArray(),
        ]);
    }

    /**
     * DELETE /api/v1/admin/usuarios/{id}
     */
    public function deleteUsuario(int $id): Response
    {
        $user = $this->userRepo->find($id);
        $currentUserId = $this->getUserId() ?? 0;

        if (!$user) {
            return $this->notFound('Usuario nao encontrado');
        }

        if ($id === $currentUserId) {
            return $this->validationError(['user' => 'Nao e permitido desativar o proprio usuario']);
        }

        // Soft-delete: try to ensure user_status exists to avoid hard-delete FK failures.
        if ($this->ensureUserStatusColumn()) {
            $updated = $this->db->update(
                'users',
                ['user_status' => 1],
                "user_id = {$id}" . $this->tenantAndCondition($this->db->table('users'))
            );
            if (!$updated) {
                $dbError = $this->db->getError();
                $message = 'Falha ao desativar usuario';
                if (!empty($dbError)) {
                    $message .= ': ' . $dbError;
                }
                return $this->error($message, 500);
            }

            // Deactivate active vínculos to keep scope and dashboards consistent.
            $this->vinculoRepo->removerTodosVinculos($id);

            // Invalidate both legacy and repository cache namespaces.
            $this->cache->invalidate('users:*');
            $this->cache->invalidate('DotProject.Repository.UserRepository:dotp_users:*');

            return $this->json([
                'message' => 'Usuario desativado com sucesso',
            ]);
        }

        try {
            $deleted = $this->userService->deleteUser($id, $currentUserId);
        } catch (\InvalidArgumentException $e) {
            return $this->validationError(['user' => $e->getMessage()]);
        }

        if (!$deleted) {
            $dbError = $this->db->getError();
            $message = 'Falha ao excluir usuario';
            if (!empty($dbError)) {
                $message .= ': ' . $dbError;
            }
            return $this->error($message, 500);
        }

        return $this->json([
            'message' => 'Usuario excluido com sucesso',
        ]);
    }

    // ===========================================
    // PRIVATE HELPERS
    // ===========================================

    private function normalizeBool(mixed $value, bool $default = false): bool
    {
        if ($value === null) {
            return $default;
        }

        $parsed = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
        return $parsed ?? $default;
    }

    protected function ensureUserStatusColumn(): bool
    {
        $usersTable = $this->db->table('users');
        $usersTableName = trim($usersTable, '`');
        $exists = (int) ($this->db->fetchValue(
            "SELECT COUNT(*)
             FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = ?
               AND column_name = 'user_status'",
            [$usersTableName]
        ) ?? 0);

        if ($exists > 0) {
            return true;
        }

        try {
            $this->db->execute(
                sprintf(
                    "ALTER TABLE `%s` ADD COLUMN user_status TINYINT(1) NOT NULL DEFAULT 0 AFTER user_department",
                    $usersTable
                )
            );
        } catch (\Throwable $e) {
            return false;
        }

        try {
            $this->db->execute(sprintf(
                "UPDATE `%s` SET user_status = 0 WHERE user_status IS NULL%s",
                $usersTable,
                $this->tenantAndCondition($usersTable)
            ));

            $idxExists = (int) ($this->db->fetchValue(
                "SELECT COUNT(*)
                 FROM information_schema.statistics
                 WHERE table_schema = DATABASE()
                   AND table_name = ?
                   AND index_name = 'idx_user_status'",
                [$usersTableName]
            ) ?? 0);

            if ($idxExists === 0) {
                $this->db->execute(sprintf(
                    "ALTER TABLE `%s` ADD INDEX idx_user_status (user_status)",
                    $usersTable
                ));
            }
        } catch (\Throwable $e) {
            // Column was created; index/backfill can be handled by migration later.
        }

        $existsAfter = (int) ($this->db->fetchValue(
            "SELECT COUNT(*)
             FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = ?
               AND column_name = 'user_status'",
            [$usersTableName]
        ) ?? 0);

        return $existsAfter > 0;
    }

    private function tenantAndCondition(string $table, ?string $alias = null): string
    {
        $tenantId = $this->getTenantId();
        if ($tenantId === null || !$this->hasTableColumn($table, 'tenant_id')) {
            return '';
        }

        $column = $alias !== null && $alias !== ''
            ? $alias . '.tenant_id'
            : 'tenant_id';

        return " AND {$column} = {$tenantId}";
    }

    private function hasTableColumn(string $table, string $column): bool
    {
        $tableName = trim($table, '`');
        $cacheKey = $tableName . ':' . $column;
        if (array_key_exists($cacheKey, $this->columnPresenceCache)) {
            return $this->columnPresenceCache[$cacheKey];
        }

        $exists = (int) ($this->db->fetchValue(
            "SELECT COUNT(*)
             FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = ?
               AND column_name = ?",
            [$tableName, $column]
        ) ?? 0);

        $this->columnPresenceCache[$cacheKey] = $exists > 0;
        return $this->columnPresenceCache[$cacheKey];
    }

    private function getTenantId(): ?int
    {
        if (!TenantContext::isEnabled()) {
            return null;
        }

        $tenantId = TenantContext::getTenantId();
        if ($tenantId === null || $tenantId <= 0) {
            return null;
        }

        return $tenantId;
    }
}
