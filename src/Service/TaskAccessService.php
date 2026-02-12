<?php
/**
 * Task Access Service
 *
 * Centralizes task/project access checks used by TaskController.
 *
 * @package DotProject\Service
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Service;

use DotProject\Core\Database;
use DotProject\Core\TenantAwareTrait;

class TaskAccessService
{
    use TenantAwareTrait;

    public const RESULT_ALLOWED = 'allowed';
    public const RESULT_UNAUTHORIZED = 'unauthorized';
    public const RESULT_NOT_FOUND = 'not_found';
    public const RESULT_FORBIDDEN = 'forbidden';

    private Database $db;
    private PermissionService $permissionService;

    public function __construct(?Database $db = null, ?PermissionService $permissionService = null)
    {
        $this->db = $db ?? Database::getInstance();
        $this->permissionService = $permissionService ?? new PermissionService();
    }

    public function resolveTaskAccess(?int $userId, int $taskId): string
    {
        if ($userId === null) {
            return self::RESULT_UNAUTHORIZED;
        }

        $auth = AuthorizationService::getInstance();
        if ($auth->isAdmin($userId)) {
            return self::RESULT_ALLOWED;
        }

        $row = $this->db->fetchOne(sprintf(
            "SELECT t.task_owner, t.task_creator, p.project_owner, p.project_creator, p.project_company
             FROM %s t
             LEFT JOIN %s p ON t.task_project = p.project_id%s
             WHERE t.task_id = %d%s",
            $this->db->table('tasks'),
            $this->db->table('projects'),
            $this->tenantAndCondition($this->db->table('projects'), 'p'),
            $taskId,
            $this->tenantAndCondition($this->db->table('tasks'), 't')
        ));

        if ($row === null) {
            return self::RESULT_NOT_FOUND;
        }

        $taskOwner = $row['task_owner'] ? (int) $row['task_owner'] : null;
        $taskCreator = $row['task_creator'] ? (int) $row['task_creator'] : null;
        $projectOwner = $row['project_owner'] ? (int) $row['project_owner'] : null;
        $projectCreator = $row['project_creator'] ? (int) $row['project_creator'] : null;
        $projectCompany = $row['project_company'] ? (int) $row['project_company'] : null;

        $escopo = $this->permissionService->getEscopoDados($userId);
        if ($escopo) {
            if (($escopo['role'] ?? null) === PermissionService::ROLE_PREFEITO) {
                return self::RESULT_ALLOWED;
            }

            if ($projectCompany && in_array($projectCompany, (array) ($escopo['unidades_escopo'] ?? []), true)) {
                return self::RESULT_ALLOWED;
            }
        }

        if (
            ($taskOwner && $taskOwner === $userId) ||
            ($taskCreator && $taskCreator === $userId) ||
            ($projectOwner && $projectOwner === $userId) ||
            ($projectCreator && $projectCreator === $userId)
        ) {
            return self::RESULT_ALLOWED;
        }

        return self::RESULT_FORBIDDEN;
    }

    public function resolveProjectAccess(?int $userId, int $projectId): string
    {
        if ($userId === null) {
            return self::RESULT_UNAUTHORIZED;
        }

        $auth = AuthorizationService::getInstance();
        if ($auth->isAdmin($userId)) {
            return self::RESULT_ALLOWED;
        }

        $row = $this->db->fetchOne(sprintf(
            "SELECT project_owner, project_creator, project_company
             FROM %s
             WHERE project_id = %d%s",
            $this->db->table('projects'),
            $projectId,
            $this->tenantAndCondition($this->db->table('projects'))
        ));

        if ($row === null) {
            return self::RESULT_NOT_FOUND;
        }

        $ownerId = $row['project_owner'] ? (int) $row['project_owner'] : null;
        $creatorId = $row['project_creator'] ? (int) $row['project_creator'] : null;
        $companyId = $row['project_company'] ? (int) $row['project_company'] : null;

        $escopo = $this->permissionService->getEscopoDados($userId);
        if ($escopo) {
            if (($escopo['role'] ?? null) === PermissionService::ROLE_PREFEITO) {
                return self::RESULT_ALLOWED;
            }

            if ($companyId && in_array($companyId, (array) ($escopo['unidades_escopo'] ?? []), true)) {
                return self::RESULT_ALLOWED;
            }
        }

        if (!$companyId && (($ownerId && $ownerId === $userId) || ($creatorId && $creatorId === $userId))) {
            return self::RESULT_ALLOWED;
        }

        return self::RESULT_FORBIDDEN;
    }

    protected function getDatabase(): Database
    {
        return $this->db;
    }
}
