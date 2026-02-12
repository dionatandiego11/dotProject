<?php
/**
 * Scope Filter Service
 *
 * Resolves data-access scope for authenticated users.
 * Encapsulates the duplicated scope-checking logic
 * from ProjectController and TaskController.
 *
 * @package DotProject\Service
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Service;

use DotProject\Core\Database;
use DotProject\Core\TenantContext;

class ScopeFilterService
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Result container returned by resolve methods.
     *
     * @return array{restricted: bool, project_ids: int[]|null, unidade_ids: int[]|null}
     *   - restricted=false: user sees everything (admin/prefeito)
     *   - restricted=true + project_ids=[]: user sees nothing
     *   - restricted=true + project_ids=[1,2,3]: user sees only those projects
     */
    public function resolveUserScope(?int $userId): array
    {
        if ($userId === null) {
            return ['restricted' => true, 'project_ids' => [], 'unidade_ids' => []];
        }

        $auth = AuthorizationService::getInstance();
        if ($auth->isAdmin($userId)) {
            return ['restricted' => false, 'project_ids' => null, 'unidade_ids' => null];
        }

        $perm = new PermissionService();
        $escopo = $perm->getEscopoDados($userId);

        if (!$escopo) {
            return ['restricted' => true, 'project_ids' => [], 'unidade_ids' => []];
        }

        if ($escopo['role'] === PermissionService::ROLE_PREFEITO) {
            return ['restricted' => false, 'project_ids' => null, 'unidade_ids' => null];
        }

        $unidades = array_map('intval', (array) ($escopo['unidades_escopo'] ?? []));
        if (empty($unidades)) {
            return ['restricted' => true, 'project_ids' => [], 'unidade_ids' => []];
        }

        // Resolve project IDs accessible via unidades
        $placeholders = implode(',', array_fill(0, count($unidades), '?'));
        $tenantCond = $this->tenantAndCondition($this->db->table('projects'));
        $projectRows = $this->db->fetchAllParams(
            "SELECT project_id FROM {$this->db->table('projects')} WHERE project_company IN ({$placeholders}){$tenantCond}",
            $unidades
        );
        $projectIds = array_map(fn($row) => (int) $row['project_id'], $projectRows);

        return [
            'restricted' => true,
            'project_ids' => $projectIds,
            'unidade_ids' => $unidades,
        ];
    }

    /**
     * Append WHERE conditions for project-level scoping.
     *
     * @param string $where  Existing WHERE clause
     * @param array  $params Existing query parameters
     * @param string $column Column name for project_company (e.g. 'project_company')
     * @param array  $scope  Result from resolveUserScope()
     * @return array{0: string, 1: array}  [where, params]
     */
    public function applyProjectScope(string $where, array $params, string $column, array $scope): array
    {
        if (!$scope['restricted'] || $scope['unidade_ids'] === null) {
            return [$where, $params];
        }

        $unidades = $scope['unidade_ids'];
        if (empty($unidades)) {
            // Will yield zero results but keeps SQL valid
            $where .= ' AND 1=0';
            return [$where, $params];
        }

        $placeholders = implode(',', array_fill(0, count($unidades), '?'));
        $where .= " AND {$column} IN ({$placeholders})";
        $params = array_merge($params, $unidades);

        return [$where, $params];
    }

    /**
     * Append WHERE conditions for task-level scoping (via project).
     *
     * @param string $where  Existing WHERE clause
     * @param array  $params Existing query parameters
     * @param string $column Column name for task_project (e.g. 't.task_project')
     * @param array  $scope  Result from resolveUserScope()
     * @return array{0: string, 1: array}  [where, params]
     */
    public function applyTaskScope(string $where, array $params, string $column, array $scope): array
    {
        if (!$scope['restricted'] || $scope['project_ids'] === null) {
            return [$where, $params];
        }

        $projectIds = $scope['project_ids'];
        if (empty($projectIds)) {
            $where .= ' AND 1=0';
            return [$where, $params];
        }

        $placeholders = implode(',', array_fill(0, count($projectIds), '?'));
        $where .= " AND {$column} IN ({$placeholders})";
        $params = array_merge($params, $projectIds);

        return [$where, $params];
    }

    private function tenantAndCondition(string $table): string
    {
        if (!TenantContext::isEnabled()) {
            return '';
        }

        $tenantId = TenantContext::getTenantId();
        if ($tenantId === null || $tenantId <= 0) {
            return '';
        }

        return " AND tenant_id = {$tenantId}";
    }
}
