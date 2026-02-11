<?php
/**
 * DotProject Authorization Service
 * 
 * Provides centralized access control and permission management.
 * Handles role-based access control (RBAC) and resource-level permissions.
 * 
 * @package DotProject\Service
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Service;

use DotProject\Core\Cache;
use DotProject\Core\Database;
use DotProject\Core\TenantContext;
use DotProject\Entity\UserEntity;
use DotProject\Repository\UserRepository;

/**
 * Authorization Service
 * 
 * Centralized security layer for access control:
 * - Role-based permissions (RBAC)
 * - Resource-level access control
 * - Company/department isolation (multi-tenant)
 * - Permission caching for performance
 */
class AuthorizationService
{
    /** Permission constants */
    public const PERMISSION_VIEW = 'view';
    public const PERMISSION_CREATE = 'create';
    public const PERMISSION_EDIT = 'edit';
    public const PERMISSION_DELETE = 'delete';
    public const PERMISSION_ADMIN = 'admin';
    
    /** Resource types */
    public const RESOURCE_PROJECT = 'project';
    public const RESOURCE_TASK = 'task';
    public const RESOURCE_USER = 'user';
    public const RESOURCE_COMPANY = 'company';
    public const RESOURCE_FILE = 'file';
    public const RESOURCE_CALENDAR = 'calendar';
    public const RESOURCE_REPORT = 'report';
    
    /** Role constants */
    public const ROLE_ADMIN = 1;
    public const ROLE_DIRECTOR = 2;
    public const ROLE_MANAGER = 3;
    public const ROLE_SUPERVISOR = 4;
    public const ROLE_USER = 5;
    public const ROLE_GUEST = 6;
    
    private Database $db;
    private Cache $cache;
    private ?UserEntity $currentUser = null;
    private ?array $userPermissions = null;
    private ?string $vinculoStatusColumn = null;
    private ?bool $hasUserTasksTable = null;
    /** @var array<string, bool> */
    private array $columnPresenceCache = [];
    
    private static ?AuthorizationService $instance = null;
    
    /**
     * Permission matrix by role
     * Format: [role => [resource => [permission => bool]]]
     */
    private array $permissionMatrix = [
        self::ROLE_ADMIN => [
            self::RESOURCE_PROJECT => [self::PERMISSION_VIEW => true, self::PERMISSION_CREATE => true, self::PERMISSION_EDIT => true, self::PERMISSION_DELETE => true, self::PERMISSION_ADMIN => true],
            self::RESOURCE_TASK => [self::PERMISSION_VIEW => true, self::PERMISSION_CREATE => true, self::PERMISSION_EDIT => true, self::PERMISSION_DELETE => true, self::PERMISSION_ADMIN => true],
            self::RESOURCE_USER => [self::PERMISSION_VIEW => true, self::PERMISSION_CREATE => true, self::PERMISSION_EDIT => true, self::PERMISSION_DELETE => true, self::PERMISSION_ADMIN => true],
            self::RESOURCE_COMPANY => [self::PERMISSION_VIEW => true, self::PERMISSION_CREATE => true, self::PERMISSION_EDIT => true, self::PERMISSION_DELETE => true, self::PERMISSION_ADMIN => true],
            self::RESOURCE_FILE => [self::PERMISSION_VIEW => true, self::PERMISSION_CREATE => true, self::PERMISSION_EDIT => true, self::PERMISSION_DELETE => true, self::PERMISSION_ADMIN => true],
            self::RESOURCE_CALENDAR => [self::PERMISSION_VIEW => true, self::PERMISSION_CREATE => true, self::PERMISSION_EDIT => true, self::PERMISSION_DELETE => true, self::PERMISSION_ADMIN => true],
            self::RESOURCE_REPORT => [self::PERMISSION_VIEW => true, self::PERMISSION_CREATE => true, self::PERMISSION_EDIT => true, self::PERMISSION_DELETE => true, self::PERMISSION_ADMIN => true],
        ],
        self::ROLE_DIRECTOR => [
            self::RESOURCE_PROJECT => [self::PERMISSION_VIEW => true, self::PERMISSION_CREATE => true, self::PERMISSION_EDIT => true, self::PERMISSION_DELETE => false, self::PERMISSION_ADMIN => false],
            self::RESOURCE_TASK => [self::PERMISSION_VIEW => true, self::PERMISSION_CREATE => true, self::PERMISSION_EDIT => true, self::PERMISSION_DELETE => false, self::PERMISSION_ADMIN => false],
            self::RESOURCE_USER => [self::PERMISSION_VIEW => true, self::PERMISSION_CREATE => true, self::PERMISSION_EDIT => true, self::PERMISSION_DELETE => false, self::PERMISSION_ADMIN => false],
            self::RESOURCE_COMPANY => [self::PERMISSION_VIEW => true, self::PERMISSION_CREATE => false, self::PERMISSION_EDIT => false, self::PERMISSION_DELETE => false, self::PERMISSION_ADMIN => false],
            self::RESOURCE_FILE => [self::PERMISSION_VIEW => true, self::PERMISSION_CREATE => true, self::PERMISSION_EDIT => true, self::PERMISSION_DELETE => true, self::PERMISSION_ADMIN => false],
            self::RESOURCE_CALENDAR => [self::PERMISSION_VIEW => true, self::PERMISSION_CREATE => true, self::PERMISSION_EDIT => true, self::PERMISSION_DELETE => true, self::PERMISSION_ADMIN => false],
            self::RESOURCE_REPORT => [self::PERMISSION_VIEW => true, self::PERMISSION_CREATE => true, self::PERMISSION_EDIT => true, self::PERMISSION_DELETE => false, self::PERMISSION_ADMIN => false],
        ],
        self::ROLE_MANAGER => [
            self::RESOURCE_PROJECT => [self::PERMISSION_VIEW => true, self::PERMISSION_CREATE => true, self::PERMISSION_EDIT => true, self::PERMISSION_DELETE => false, self::PERMISSION_ADMIN => false],
            self::RESOURCE_TASK => [self::PERMISSION_VIEW => true, self::PERMISSION_CREATE => true, self::PERMISSION_EDIT => true, self::PERMISSION_DELETE => true, self::PERMISSION_ADMIN => false],
            self::RESOURCE_USER => [self::PERMISSION_VIEW => true, self::PERMISSION_CREATE => false, self::PERMISSION_EDIT => false, self::PERMISSION_DELETE => false, self::PERMISSION_ADMIN => false],
            self::RESOURCE_COMPANY => [self::PERMISSION_VIEW => true, self::PERMISSION_CREATE => false, self::PERMISSION_EDIT => false, self::PERMISSION_DELETE => false, self::PERMISSION_ADMIN => false],
            self::RESOURCE_FILE => [self::PERMISSION_VIEW => true, self::PERMISSION_CREATE => true, self::PERMISSION_EDIT => true, self::PERMISSION_DELETE => true, self::PERMISSION_ADMIN => false],
            self::RESOURCE_CALENDAR => [self::PERMISSION_VIEW => true, self::PERMISSION_CREATE => true, self::PERMISSION_EDIT => true, self::PERMISSION_DELETE => true, self::PERMISSION_ADMIN => false],
            self::RESOURCE_REPORT => [self::PERMISSION_VIEW => true, self::PERMISSION_CREATE => true, self::PERMISSION_EDIT => false, self::PERMISSION_DELETE => false, self::PERMISSION_ADMIN => false],
        ],
        self::ROLE_SUPERVISOR => [
            self::RESOURCE_PROJECT => [self::PERMISSION_VIEW => true, self::PERMISSION_CREATE => false, self::PERMISSION_EDIT => false, self::PERMISSION_DELETE => false, self::PERMISSION_ADMIN => false],
            self::RESOURCE_TASK => [self::PERMISSION_VIEW => true, self::PERMISSION_CREATE => true, self::PERMISSION_EDIT => true, self::PERMISSION_DELETE => false, self::PERMISSION_ADMIN => false],
            self::RESOURCE_USER => [self::PERMISSION_VIEW => true, self::PERMISSION_CREATE => false, self::PERMISSION_EDIT => false, self::PERMISSION_DELETE => false, self::PERMISSION_ADMIN => false],
            self::RESOURCE_COMPANY => [self::PERMISSION_VIEW => true, self::PERMISSION_CREATE => false, self::PERMISSION_EDIT => false, self::PERMISSION_DELETE => false, self::PERMISSION_ADMIN => false],
            self::RESOURCE_FILE => [self::PERMISSION_VIEW => true, self::PERMISSION_CREATE => true, self::PERMISSION_EDIT => true, self::PERMISSION_DELETE => false, self::PERMISSION_ADMIN => false],
            self::RESOURCE_CALENDAR => [self::PERMISSION_VIEW => true, self::PERMISSION_CREATE => true, self::PERMISSION_EDIT => true, self::PERMISSION_DELETE => false, self::PERMISSION_ADMIN => false],
            self::RESOURCE_REPORT => [self::PERMISSION_VIEW => true, self::PERMISSION_CREATE => false, self::PERMISSION_EDIT => false, self::PERMISSION_DELETE => false, self::PERMISSION_ADMIN => false],
        ],
        self::ROLE_USER => [
            self::RESOURCE_PROJECT => [self::PERMISSION_VIEW => true, self::PERMISSION_CREATE => false, self::PERMISSION_EDIT => false, self::PERMISSION_DELETE => false, self::PERMISSION_ADMIN => false],
            self::RESOURCE_TASK => [self::PERMISSION_VIEW => true, self::PERMISSION_CREATE => true, self::PERMISSION_EDIT => true, self::PERMISSION_DELETE => false, self::PERMISSION_ADMIN => false],
            self::RESOURCE_USER => [self::PERMISSION_VIEW => true, self::PERMISSION_CREATE => false, self::PERMISSION_EDIT => false, self::PERMISSION_DELETE => false, self::PERMISSION_ADMIN => false],
            self::RESOURCE_COMPANY => [self::PERMISSION_VIEW => true, self::PERMISSION_CREATE => false, self::PERMISSION_EDIT => false, self::PERMISSION_DELETE => false, self::PERMISSION_ADMIN => false],
            self::RESOURCE_FILE => [self::PERMISSION_VIEW => true, self::PERMISSION_CREATE => true, self::PERMISSION_EDIT => false, self::PERMISSION_DELETE => false, self::PERMISSION_ADMIN => false],
            self::RESOURCE_CALENDAR => [self::PERMISSION_VIEW => true, self::PERMISSION_CREATE => true, self::PERMISSION_EDIT => true, self::PERMISSION_DELETE => false, self::PERMISSION_ADMIN => false],
            self::RESOURCE_REPORT => [self::PERMISSION_VIEW => false, self::PERMISSION_CREATE => false, self::PERMISSION_EDIT => false, self::PERMISSION_DELETE => false, self::PERMISSION_ADMIN => false],
        ],
        self::ROLE_GUEST => [
            self::RESOURCE_PROJECT => [self::PERMISSION_VIEW => true, self::PERMISSION_CREATE => false, self::PERMISSION_EDIT => false, self::PERMISSION_DELETE => false, self::PERMISSION_ADMIN => false],
            self::RESOURCE_TASK => [self::PERMISSION_VIEW => true, self::PERMISSION_CREATE => false, self::PERMISSION_EDIT => false, self::PERMISSION_DELETE => false, self::PERMISSION_ADMIN => false],
            self::RESOURCE_USER => [self::PERMISSION_VIEW => false, self::PERMISSION_CREATE => false, self::PERMISSION_EDIT => false, self::PERMISSION_DELETE => false, self::PERMISSION_ADMIN => false],
            self::RESOURCE_COMPANY => [self::PERMISSION_VIEW => false, self::PERMISSION_CREATE => false, self::PERMISSION_EDIT => false, self::PERMISSION_DELETE => false, self::PERMISSION_ADMIN => false],
            self::RESOURCE_FILE => [self::PERMISSION_VIEW => true, self::PERMISSION_CREATE => false, self::PERMISSION_EDIT => false, self::PERMISSION_DELETE => false, self::PERMISSION_ADMIN => false],
            self::RESOURCE_CALENDAR => [self::PERMISSION_VIEW => true, self::PERMISSION_CREATE => false, self::PERMISSION_EDIT => false, self::PERMISSION_DELETE => false, self::PERMISSION_ADMIN => false],
            self::RESOURCE_REPORT => [self::PERMISSION_VIEW => false, self::PERMISSION_CREATE => false, self::PERMISSION_EDIT => false, self::PERMISSION_DELETE => false, self::PERMISSION_ADMIN => false],
        ],
    ];
    
    public function __construct(?Database $db = null, ?Cache $cache = null)
    {
        $this->db = $db ?? Database::getInstance();
        $this->cache = $cache ?? new Cache(prefix: 'auth:');
    }
    
    /**
     * Get singleton instance
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Set current authenticated user
     */
    public function setCurrentUser(UserEntity $user): void
    {
        $this->currentUser = $user;
        $this->userPermissions = null; // Clear cached permissions
    }
    
    /**
     * Get current user
     */
    public function getCurrentUser(): ?UserEntity
    {
        return $this->currentUser;
    }
    
    /**
     * Check if user is authenticated
     */
    public function isAuthenticated(): bool
    {
        return $this->currentUser !== null && $this->currentUser->getId() !== null;
    }
    
    /**
     * Get user role ID from database
     */
    public function getUserRole(int $userId): int
    {
        $cacheKey = "user_role:{$userId}";
        $cached = $this->cache->get($cacheKey);
        
        if ($cached !== null) {
            return $cached;
        }
        
        // Query user role from database
        $sql = sprintf(
            "SELECT user_type FROM `%s` WHERE user_id = %d%s",
            $this->db->table('users'),
            $userId,
            $this->tenantAndCondition($this->db->table('users'))
        );
        
        $rawRole = (int) ($this->db->fetchValue($sql) ?? self::ROLE_USER);
        $role = $this->normalizeRoleId($rawRole);
        
        // Cache for 5 minutes
        $this->cache->set($cacheKey, $role, 300);
        
        return $role;
    }
    
    /**
     * Check if user has permission on resource
     * 
     * @param string $resource Resource type (project, task, etc)
     * @param string $permission Permission type (view, create, edit, delete, admin)
     * @param int|null $userId User ID (null = current user)
     */
    public function can(string $resource, string $permission, ?int $userId = null): bool
    {
        $userId = $userId ?? $this->currentUser?->getId();
        
        if ($userId === null) {
            return false;
        }
        
        $role = $this->getUserRole($userId);
        
        // Admin always has all permissions
        if ($role === self::ROLE_ADMIN) {
            return true;
        }
        
        return $this->permissionMatrix[$role][$resource][$permission] ?? false;
    }
    
    /**
     * Check if user can view resource
     */
    public function canView(string $resource, ?int $userId = null): bool
    {
        return $this->can($resource, self::PERMISSION_VIEW, $userId);
    }
    
    /**
     * Check if user can create resource
     */
    public function canCreate(string $resource, ?int $userId = null): bool
    {
        return $this->can($resource, self::PERMISSION_CREATE, $userId);
    }
    
    /**
     * Check if user can edit resource
     */
    public function canEdit(string $resource, ?int $userId = null): bool
    {
        return $this->can($resource, self::PERMISSION_EDIT, $userId);
    }
    
    /**
     * Check if user can delete resource
     */
    public function canDelete(string $resource, ?int $userId = null): bool
    {
        return $this->can($resource, self::PERMISSION_DELETE, $userId);
    }
    
    /**
     * Check if user is admin
     */
    public function isAdmin(?int $userId = null): bool
    {
        $userId = $userId ?? $this->currentUser?->getId();
        
        if ($userId === null) {
            return false;
        }
        
        return $this->getUserRole($userId) === self::ROLE_ADMIN;
    }
    
    /**
     * Check if user can access specific project
     * 
     * @param int $projectId Project ID
     * @param string $permission Permission type
     * @param int|null $userId User ID (null = current user)
     */
    public function canAccessProject(int $projectId, string $permission = self::PERMISSION_VIEW, ?int $userId = null): bool
    {
        $userId = $userId ?? $this->currentUser?->getId();
        
        if ($userId === null) {
            return false;
        }
        
        // Check general project permission
        if (!$this->can(self::RESOURCE_PROJECT, $permission, $userId)) {
            return false;
        }
        
        // Admin can access all projects
        if ($this->isAdmin($userId)) {
            return true;
        }
        
        // Check project-specific permissions
        $cacheKey = "project_access:{$userId}:{$projectId}";
        $cached = $this->cache->get($cacheKey);
        
        if ($cached !== null) {
            return $cached;
        }
        
        $hasAccess = $this->checkProjectAccess($projectId, $userId);
        
        // Cache for 1 minute
        $this->cache->set($cacheKey, $hasAccess, 60);
        
        return $hasAccess;
    }
    
    /**
     * Check actual project access in database
     */
    private function checkProjectAccess(int $projectId, int $userId): bool
    {
        // Check if user is project owner
        $sql = sprintf(
            "SELECT COUNT(*) FROM `%s` WHERE project_id = %d AND project_owner = %d%s",
            $this->db->table('projects'),
            $projectId,
            $userId,
            $this->tenantAndCondition($this->db->table('projects'))
        );
        
        if ((int) $this->db->fetchValue($sql) > 0) {
            return true;
        }
        
        // Check if user is assigned to any task in the project
        if ($this->hasUserTasksTable()) {
            $sql = sprintf(
                "SELECT COUNT(*) FROM `%s` t 
                 JOIN `%s` ut ON t.task_id = ut.task_id 
                 WHERE t.task_project = %d AND ut.user_id = %d%s%s",
                $this->db->table('tasks'),
                $this->db->table('user_tasks'),
                $projectId,
                $userId,
                $this->tenantAndCondition($this->db->table('tasks'), 't'),
                $this->tenantAndCondition($this->db->table('user_tasks'), 'ut')
            );

            if ((int) $this->db->fetchValue($sql) > 0) {
                return true;
            }
        }
        
        // Check company/unidade scope access.
        $scopeCompanyIds = $this->getUserScopeCompanyIds($userId);
        if (empty($scopeCompanyIds)) {
            return false;
        }

        $placeholders = implode(',', array_fill(0, count($scopeCompanyIds), '?'));
        $sql = sprintf(
            "SELECT COUNT(*) FROM `%s` 
             WHERE project_id = ? 
               AND project_company IN (%s)%s",
            $this->db->table('projects'),
            $placeholders,
            $this->tenantAndCondition($this->db->table('projects'))
        );

        return (int) $this->db->fetchValue(
            $sql,
            array_merge([$projectId], $scopeCompanyIds)
        ) > 0;
    }
    
    /**
     * Check if user can access specific task
     * 
     * @param int $taskId Task ID
     * @param string $permission Permission type
     * @param int|null $userId User ID (null = current user)
     */
    public function canAccessTask(int $taskId, string $permission = self::PERMISSION_VIEW, ?int $userId = null): bool
    {
        $userId = $userId ?? $this->currentUser?->getId();
        
        if ($userId === null) {
            return false;
        }
        
        // Check general task permission
        if (!$this->can(self::RESOURCE_TASK, $permission, $userId)) {
            return false;
        }
        
        // Admin can access all tasks
        if ($this->isAdmin($userId)) {
            return true;
        }
        
        // Check task-specific permissions
        $cacheKey = "task_access:{$userId}:{$taskId}";
        $cached = $this->cache->get($cacheKey);
        
        if ($cached !== null) {
            return $cached;
        }
        
        $hasAccess = $this->checkTaskAccess($taskId, $userId);
        
        // Cache for 1 minute
        $this->cache->set($cacheKey, $hasAccess, 60);
        
        return $hasAccess;
    }
    
    /**
     * Check actual task access in database
     */
    private function checkTaskAccess(int $taskId, int $userId): bool
    {
        // Check if user is task owner
        $sql = sprintf(
            "SELECT COUNT(*) FROM `%s` WHERE task_id = %d AND task_owner = %d%s",
            $this->db->table('tasks'),
            $taskId,
            $userId,
            $this->tenantAndCondition($this->db->table('tasks'))
        );
        
        if ((int) $this->db->fetchValue($sql) > 0) {
            return true;
        }
        
        // Check if user is assigned to task
        if ($this->hasUserTasksTable()) {
            $sql = sprintf(
                "SELECT COUNT(*) FROM `%s` WHERE task_id = %d AND user_id = %d%s",
                $this->db->table('user_tasks'),
                $taskId,
                $userId,
                $this->tenantAndCondition($this->db->table('user_tasks'))
            );
            
            if ((int) $this->db->fetchValue($sql) > 0) {
                return true;
            }
        }
        
        // Check project access (if user can access project, can view tasks)
        $sql = sprintf(
            "SELECT task_project FROM `%s` WHERE task_id = %d%s",
            $this->db->table('tasks'),
            $taskId,
            $this->tenantAndCondition($this->db->table('tasks'))
        );
        
        $projectId = (int) $this->db->fetchValue($sql);
        
        if ($projectId > 0) {
            return $this->canAccessProject($projectId, self::PERMISSION_VIEW, $userId);
        }
        
        return false;
    }
    
    /**
     * Enforce permission - throws exception if not allowed
     * 
     * @throws \RuntimeException If user doesn't have permission
     */
    public function enforce(string $resource, string $permission, ?int $userId = null): void
    {
        if (!$this->can($resource, $permission, $userId)) {
            $userId = $userId ?? $this->currentUser?->getId() ?? 'anonymous';
            throw new \RuntimeException(
                sprintf('User %d does not have "%s" permission on "%s"', $userId, $permission, $resource),
                403
            );
        }
    }
    
    /**
     * Enforce project access - throws exception if not allowed
     * 
     * @throws \RuntimeException If user cannot access project
     */
    public function enforceProjectAccess(int $projectId, string $permission = self::PERMISSION_VIEW, ?int $userId = null): void
    {
        if (!$this->canAccessProject($projectId, $permission, $userId)) {
            $userId = $userId ?? $this->currentUser?->getId() ?? 'anonymous';
            throw new \RuntimeException(
                sprintf('User %d cannot %s project %d', $userId, $permission, $projectId),
                403
            );
        }
    }
    
    /**
     * Enforce task access - throws exception if not allowed
     * 
     * @throws \RuntimeException If user cannot access task
     */
    public function enforceTaskAccess(int $taskId, string $permission = self::PERMISSION_VIEW, ?int $userId = null): void
    {
        if (!$this->canAccessTask($taskId, $permission, $userId)) {
            $userId = $userId ?? $this->currentUser?->getId() ?? 'anonymous';
            throw new \RuntimeException(
                sprintf('User %d cannot %s task %d', $userId, $permission, $taskId),
                403
            );
        }
    }
    
    /**
     * Get all permissions for a user
     * 
     * @return array<string, array<string, bool>>
     */
    public function getUserPermissions(?int $userId = null): array
    {
        $userId = $userId ?? $this->currentUser?->getId();
        
        if ($userId === null) {
            return [];
        }
        
        if ($this->userPermissions !== null && $userId === $this->currentUser?->getId()) {
            return $this->userPermissions;
        }
        
        $role = $this->normalizeRoleId($this->getUserRole($userId));
        
        return $this->permissionMatrix[$role] ?? $this->permissionMatrix[self::ROLE_GUEST];
    }
    
    /**
     * Get accessible project IDs for user
     * 
     * @return array<int>
     */
    public function getAccessibleProjectIds(?int $userId = null): array
    {
        $userId = $userId ?? $this->currentUser?->getId();
        
        if ($userId === null) {
            return [];
        }
        
        // Admin can access all projects
        if ($this->isAdmin($userId)) {
            $sql = sprintf(
                "SELECT project_id FROM `%s` WHERE 1=1%s",
                $this->db->table('projects'),
                $this->tenantAndCondition($this->db->table('projects'))
            );
            $results = $this->db->fetchAll($sql);
            return array_map(fn($row) => (int) $row['project_id'], $results);
        }
        
        // Get projects where user is owner
        $sql = sprintf(
            "SELECT project_id FROM `%s` WHERE project_owner = %d%s",
            $this->db->table('projects'),
            $userId,
            $this->tenantAndCondition($this->db->table('projects'))
        );
        $ownerProjects = $this->db->fetchAll($sql);
        
        // Get projects where user has tasks
        $taskProjects = [];
        if ($this->hasUserTasksTable()) {
            $sql = sprintf(
                "SELECT DISTINCT t.task_project as project_id 
                 FROM `%s` t 
                 JOIN `%s` ut ON t.task_id = ut.task_id 
                 WHERE ut.user_id = %d%s%s",
                $this->db->table('tasks'),
                $this->db->table('user_tasks'),
                $userId,
                $this->tenantAndCondition($this->db->table('tasks'), 't'),
                $this->tenantAndCondition($this->db->table('user_tasks'), 'ut')
            );
            $taskProjects = $this->db->fetchAll($sql);
        }

        $companyProjects = [];
        $scopeCompanyIds = $this->getUserScopeCompanyIds($userId);
        if (!empty($scopeCompanyIds)) {
            $placeholders = implode(',', array_fill(0, count($scopeCompanyIds), '?'));
            $sql = sprintf(
                "SELECT project_id FROM `%s` WHERE project_company IN (%s)%s",
                $this->db->table('projects'),
                $placeholders,
                $this->tenantAndCondition($this->db->table('projects'))
            );
            $companyProjects = $this->db->fetchAll($sql, $scopeCompanyIds);
        }
        
        // Merge and deduplicate
        $allProjects = array_merge($ownerProjects, $taskProjects, $companyProjects);
        $projectIds = array_map(fn($row) => (int) $row['project_id'], $allProjects);
        
        return array_values(array_unique($projectIds));
    }
    
    /**
     * Clear permission cache for user
     */
    public function clearUserCache(int $userId): void
    {
        $this->cache->delete("user_role:{$userId}");
        $this->cache->delete("user_scope_companies:{$userId}");
        $this->cache->invalidate("project_access:{$userId}:*");
        $this->cache->invalidate("task_access:{$userId}:*");
    }

    private function normalizeRoleId(int $roleId): int
    {
        return array_key_exists($roleId, $this->permissionMatrix)
            ? $roleId
            : self::ROLE_USER;
    }

    /**
     * @return int[]
     */
    private function getUserScopeCompanyIds(int $userId): array
    {
        $cacheKey = "user_scope_companies:{$userId}";
        $cached = $this->cache->get($cacheKey);
        if (is_array($cached)) {
            return array_map('intval', $cached);
        }

        $ids = [];

        $userCompany = (int) ($this->db->fetchValue(
            sprintf(
                "SELECT user_company FROM `%s` WHERE user_id = ? AND user_company IS NOT NULL AND user_company <> 0%s",
                $this->db->table('users'),
                $this->tenantAndCondition($this->db->table('users'))
            ),
            [$userId]
        ) ?? 0);
        if ($userCompany > 0) {
            $ids[] = $userCompany;
        }

        $vinculoStatusColumn = $this->resolveVinculoStatusColumn();
        $activeValue = $this->vinculoStatusValueForSql(true, $vinculoStatusColumn);
        $vinculoRows = $this->db->fetchAll(
            sprintf(
                "SELECT vinculo_unidade_id
                 FROM `%s`
                 WHERE vinculo_user_id = ?
                   AND %s = ?%s",
                $this->db->table('usuario_unidades'),
                $vinculoStatusColumn,
                $this->tenantAndCondition($this->db->table('usuario_unidades'))
            ),
            [$userId, $activeValue]
        );

        foreach ($vinculoRows as $row) {
            $vinculoId = (int) ($row['vinculo_unidade_id'] ?? 0);
            if ($vinculoId > 0) {
                $ids[] = $vinculoId;
            }
        }

        $ids = array_values(array_unique(array_map('intval', $ids)));
        $this->cache->set($cacheKey, $ids, 300);

        return $ids;
    }

    private function resolveVinculoStatusColumn(): string
    {
        if ($this->vinculoStatusColumn !== null) {
            return $this->vinculoStatusColumn;
        }

        $table = trim($this->db->table('usuario_unidades'), '`');
        $exists = (int) ($this->db->fetchValue(
            "SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = ?
               AND column_name = 'vinculo_status'",
            [$table]
        ) ?? 0);

        $this->vinculoStatusColumn = $exists > 0 ? 'vinculo_status' : 'vinculo_ativo';
        return $this->vinculoStatusColumn;
    }

    private function vinculoStatusValueForSql(bool $active, string $column): int|string
    {
        if ($column === 'vinculo_status') {
            return $active ? 'ativo' : 'inativo';
        }

        return $active ? 1 : 0;
    }

    private function hasUserTasksTable(): bool
    {
        if ($this->hasUserTasksTable !== null) {
            return $this->hasUserTasksTable;
        }

        $table = trim($this->db->table('user_tasks'), '`');
        $exists = (int) ($this->db->fetchValue(
            "SELECT COUNT(*) FROM information_schema.tables
             WHERE table_schema = DATABASE()
               AND table_name = ?",
            [$table]
        ) ?? 0);

        $this->hasUserTasksTable = $exists > 0;
        return $this->hasUserTasksTable;
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
            "SELECT COUNT(*) FROM information_schema.columns
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
    
    /**
     * Get role name by ID
     */
    public function getRoleName(int $roleId): string
    {
        return match ($roleId) {
            self::ROLE_ADMIN => 'Administrator',
            self::ROLE_DIRECTOR => 'Director',
            self::ROLE_MANAGER => 'Manager',
            self::ROLE_SUPERVISOR => 'Supervisor',
            self::ROLE_USER => 'User',
            self::ROLE_GUEST => 'Guest',
            default => 'Unknown',
        };
    }
    
    /**
     * Get all available roles
     * 
     * @return array<int, string>
     */
    public function getAvailableRoles(): array
    {
        return [
            self::ROLE_ADMIN => 'Administrator',
            self::ROLE_DIRECTOR => 'Director',
            self::ROLE_MANAGER => 'Manager',
            self::ROLE_SUPERVISOR => 'Supervisor',
            self::ROLE_USER => 'User',
            self::ROLE_GUEST => 'Guest',
        ];
    }
}
