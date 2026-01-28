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
            "SELECT user_type FROM `%s` WHERE user_id = %d",
            $this->db->table('users'),
            $userId
        );
        
        $role = (int) ($this->db->fetchValue($sql) ?? self::ROLE_USER);
        
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
            "SELECT COUNT(*) FROM `%s` WHERE project_id = %d AND project_owner = %d",
            $this->db->table('projects'),
            $projectId,
            $userId
        );
        
        if ((int) $this->db->fetchValue($sql) > 0) {
            return true;
        }
        
        // Check if user is assigned to any task in the project
        $sql = sprintf(
            "SELECT COUNT(*) FROM `%s` t 
             JOIN `%s` ut ON t.task_id = ut.task_id 
             WHERE t.task_project = %d AND ut.user_id = %d",
            $this->db->table('tasks'),
            $this->db->table('user_tasks'),
            $projectId,
            $userId
        );
        
        if ((int) $this->db->fetchValue($sql) > 0) {
            return true;
        }
        
        // Check company access (user's company == project company)
        $sql = sprintf(
            "SELECT COUNT(*) FROM `%s` p 
             JOIN `%s` u ON u.user_company = p.project_company 
             WHERE p.project_id = %d AND u.user_id = %d",
            $this->db->table('projects'),
            $this->db->table('users'),
            $projectId,
            $userId
        );
        
        return (int) $this->db->fetchValue($sql) > 0;
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
            "SELECT COUNT(*) FROM `%s` WHERE task_id = %d AND task_owner = %d",
            $this->db->table('tasks'),
            $taskId,
            $userId
        );
        
        if ((int) $this->db->fetchValue($sql) > 0) {
            return true;
        }
        
        // Check if user is assigned to task
        $sql = sprintf(
            "SELECT COUNT(*) FROM `%s` WHERE task_id = %d AND user_id = %d",
            $this->db->table('user_tasks'),
            $taskId,
            $userId
        );
        
        if ((int) $this->db->fetchValue($sql) > 0) {
            return true;
        }
        
        // Check project access (if user can access project, can view tasks)
        $sql = sprintf(
            "SELECT task_project FROM `%s` WHERE task_id = %d",
            $this->db->table('tasks'),
            $taskId
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
        
        $role = $this->getUserRole($userId);
        
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
            $sql = sprintf("SELECT project_id FROM `%s`", $this->db->table('projects'));
            $results = $this->db->fetchAll($sql);
            return array_map(fn($row) => (int) $row['project_id'], $results);
        }
        
        // Get projects where user is owner
        $sql = sprintf(
            "SELECT project_id FROM `%s` WHERE project_owner = %d",
            $this->db->table('projects'),
            $userId
        );
        $ownerProjects = $this->db->fetchAll($sql);
        
        // Get projects where user has tasks
        $sql = sprintf(
            "SELECT DISTINCT t.task_project as project_id 
             FROM `%s` t 
             JOIN `%s` ut ON t.task_id = ut.task_id 
             WHERE ut.user_id = %d",
            $this->db->table('tasks'),
            $this->db->table('user_tasks'),
            $userId
        );
        $taskProjects = $this->db->fetchAll($sql);
        
        // Get projects from user's company
        $sql = sprintf(
            "SELECT p.project_id FROM `%s` p 
             JOIN `%s` u ON u.user_company = p.project_company 
             WHERE u.user_id = %d",
            $this->db->table('projects'),
            $this->db->table('users'),
            $userId
        );
        $companyProjects = $this->db->fetchAll($sql);
        
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
        $this->cache->invalidate("project_access:{$userId}:*");
        $this->cache->invalidate("task_access:{$userId}:*");
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
