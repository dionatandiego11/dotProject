<?php
/**
 * DotProject Modern Project Service
 * 
 * Modern service using Entity/Repository pattern with:
 * - Rich domain entities (ProjectEntity)
 * - Repository pattern (ProjectRepository)
 * - Authorization integration (AuthorizationService)
 * - Validation (ValidationService)
 * - Multi-layer caching
 * 
 * @package DotProject\Service
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Service;

use DotProject\Core\Cache;
use DotProject\Core\Database;
use DotProject\Core\Logger;
use DotProject\Entity\ProjectEntity;
use DotProject\Repository\ProjectRepository;
use DotProject\Repository\TaskRepository;

/**
 * Modern Project Service
 * 
 * Business logic layer for project management with:
 * - Complete CRUD operations
 * - Permission checks on every operation
 * - Project health calculations
 * - Progress tracking
 * - Statistics and analytics
 */
class ModernProjectService
{
    private ProjectRepository $projectRepository;
    private TaskRepository $taskRepository;
    private AuthorizationService $auth;
    private ValidationService $validator;
    private Database $db;
    private Cache $cache;
    
    public function __construct(
        ?ProjectRepository $projectRepository = null,
        ?TaskRepository $taskRepository = null,
        ?AuthorizationService $auth = null,
        ?ValidationService $validator = null,
        ?Database $db = null,
        ?Cache $cache = null
    ) {
        $this->projectRepository = $projectRepository ?? new ProjectRepository();
        $this->taskRepository = $taskRepository ?? new TaskRepository();
        $this->auth = $auth ?? AuthorizationService::getInstance();
        $this->validator = $validator ?? new ValidationService();
        $this->db = $db ?? Database::getInstance();
        $this->cache = $cache ?? new Cache(prefix: 'project_service:');
    }
    
    /**
     * Get project by ID with permission check
     * 
     * @param int $projectId Project ID
     * @param int|null $userId User requesting (null = current user)
     * @return ProjectEntity|null Project or null if not found/no access
     */
    public function getProject(int $projectId, ?int $userId = null): ?ProjectEntity
    {
        $userId = $userId ?? $this->auth->getCurrentUser()?->getId();
        
        // Check permission
        if (!$this->auth->canAccessProject($projectId, AuthorizationService::PERMISSION_VIEW, $userId)) {
            Logger::warning('Unauthorized project access attempt', [
                'project_id' => $projectId,
                'user_id' => $userId
            ]);
            return null;
        }
        
        return $this->projectRepository->find($projectId);
    }
    
    /**
     * Get all projects accessible by user
     * 
     * @param int|null $userId User ID (null = current user)
     * @param array<string, mixed> $filters Optional filters
     * @return array<ProjectEntity>
     */
    public function getAccessibleProjects(?int $userId = null, array $filters = []): array
    {
        $userId = $userId ?? $this->auth->getCurrentUser()?->getId();
        
        if ($userId === null) {
            return [];
        }
        
        // Admin can see all projects
        if ($this->auth->isAdmin($userId)) {
            if (!empty($filters)) {
                return $this->projectRepository->findBy($filters);
            }
            return $this->projectRepository->findAll();
        }
        
        // Get accessible project IDs
        $accessibleIds = $this->auth->getAccessibleProjectIds($userId);
        
        if (empty($accessibleIds)) {
            return [];
        }
        
        // Build query with filters
        $criteria = $filters;
        $criteria['project_id'] = ['operator' => 'IN', 'value' => $accessibleIds];
        
        return $this->projectRepository->findBy($criteria);
    }
    
    /**
     * Get active projects for user
     * 
     * @param int|null $userId User ID
     * @return array<ProjectEntity>
     */
    public function getActiveProjects(?int $userId = null): array
    {
        return $this->getAccessibleProjects($userId, ['project_status' => 0]);
    }
    
    /**
     * Get overdue projects
     * 
     * @param int|null $userId User ID
     * @return array<ProjectEntity>
     */
    public function getOverdueProjects(?int $userId = null): array
    {
        $userId = $userId ?? $this->auth->getCurrentUser()?->getId();
        
        if ($userId === null) {
            return [];
        }
        
        // Get all overdue projects
        $overdueProjects = $this->projectRepository->findOverdue();
        
        // Filter by access
        return array_filter($overdueProjects, function (ProjectEntity $project) use ($userId) {
            return $this->auth->canAccessProject($project->getId() ?? 0, AuthorizationService::PERMISSION_VIEW, $userId);
        });
    }
    
    /**
     * Create new project
     * 
     * @param array<string, mixed> $data Project data
     * @param int|null $createdBy User creating (null = current user)
     * @return ProjectEntity Created project
     * @throws \RuntimeException If validation/auth/permission fails
     */
    public function createProject(array $data, ?int $createdBy = null): ProjectEntity
    {
        $createdBy = $createdBy ?? $this->auth->getCurrentUser()?->getId();
        
        if ($createdBy === null) {
            throw new \RuntimeException('User not authenticated');
        }
        
        // Check permission
        $this->auth->enforce(AuthorizationService::RESOURCE_PROJECT, AuthorizationService::PERMISSION_CREATE, $createdBy);
        
        // Validate
        $validation = $this->validator->validateProject($data);
        if ($validation->fails()) {
            throw new \RuntimeException($validation->firstError() ?? 'Validation failed');
        }
        
        // Create entity
        $project = new ProjectEntity();
        $project->setName($data['project_name']);
        $project->setShortName($data['project_short_name'] ?? substr($data['project_name'], 0, 10));
        $project->setDescription($data['project_description'] ?? null);
        $project->setStatus((int) ($data['project_status'] ?? 0));
        $project->setPriority((int) ($data['project_priority'] ?? 3));
        $project->setOwnerId($createdBy);
        $project->setCompanyId($data['project_company'] ?? null);
        $project->setColorIdentifier($this->generateColor());
        $project->setPercentComplete(0);
        
        // Dates
        if (!empty($data['project_start_date'])) {
            $project->setStartDate(new \DateTime($data['project_start_date']));
        }
        if (!empty($data['project_end_date'])) {
            $project->setEndDate(new \DateTime($data['project_end_date']));
        }
        
        // Save
        if (!$this->projectRepository->save($project)) {
            throw new \RuntimeException('Failed to create project');
        }
        
        Logger::info('Project created', [
            'project_id' => $project->getId(),
            'user_id' => $createdBy
        ]);
        
        return $project;
    }
    
    /**
     * Update project
     * 
     * @param int $projectId Project ID
     * @param array<string, mixed> $data Update data
     * @param int|null $updatedBy User updating (null = current user)
     * @return ProjectEntity Updated project
     * @throws \InvalidArgumentException If validation fails
     * @throws \RuntimeException If no permission or not found
     */
    public function updateProject(int $projectId, array $data, ?int $updatedBy = null): ProjectEntity
    {
        $updatedBy = $updatedBy ?? $this->auth->getCurrentUser()?->getId();
        
        if ($updatedBy === null) {
            throw new \RuntimeException('User not authenticated');
        }
        
        // Check permission
        $this->auth->enforceProjectAccess($projectId, AuthorizationService::PERMISSION_EDIT, $updatedBy);
        
        // Get project
        $project = $this->projectRepository->find($projectId);
        if ($project === null) {
            throw new \RuntimeException('Project not found');
        }
        
        // Validate
        $validation = $this->validator->validateProject($data);
        if ($validation->fails()) {
            throw new \InvalidArgumentException($validation->firstError() ?? 'Validation failed');
        }
        
        // Update fields
        if (isset($data['project_name'])) {
            $project->setName($data['project_name']);
        }
        if (isset($data['project_short_name'])) {
            $project->setShortName($data['project_short_name']);
        }
        if (isset($data['project_description'])) {
            $project->setDescription($data['project_description']);
        }
        if (isset($data['project_status'])) {
            $project->setStatus((int) $data['project_status']);
        }
        if (isset($data['project_priority'])) {
            $project->setPriority((int) $data['project_priority']);
        }
        if (isset($data['project_company'])) {
            $project->setCompanyId($data['project_company'] ?: null);
        }
        if (isset($data['project_color_identifier'])) {
            $project->setColorIdentifier($data['project_color_identifier']);
        }
        if (isset($data['project_url'])) {
            $project->setUrl($data['project_url']);
        }
        
        // Update dates
        if (isset($data['project_start_date'])) {
            $project->setStartDate($data['project_start_date'] ? new \DateTime($data['project_start_date']) : null);
        }
        if (isset($data['project_end_date'])) {
            $project->setEndDate($data['project_end_date'] ? new \DateTime($data['project_end_date']) : null);
        }
        if (isset($data['project_actual_end_date'])) {
            $project->setActualEndDate($data['project_actual_end_date'] ? new \DateTime($data['project_actual_end_date']) : null);
        }
        
        // Save
        if (!$this->projectRepository->save($project)) {
            throw new \RuntimeException('Failed to update project');
        }
        
        Logger::info('Project updated', [
            'project_id' => $projectId,
            'user_id' => $updatedBy
        ]);
        
        return $project;
    }
    
    /**
     * Delete project
     * 
     * @param int $projectId Project ID
     * @param int|null $deletedBy User deleting (null = current user)
     * @return bool Success
     * @throws \RuntimeException If no permission
     */
    public function deleteProject(int $projectId, ?int $deletedBy = null): bool
    {
        $deletedBy = $deletedBy ?? $this->auth->getCurrentUser()?->getId();
        
        if ($deletedBy === null) {
            throw new \RuntimeException('User not authenticated');
        }
        
        // Check permission
        $this->auth->enforceProjectAccess($projectId, AuthorizationService::PERMISSION_DELETE, $deletedBy);
        
        // Check if project has tasks
        $taskCount = $this->taskRepository->count(['task_project' => $projectId]);
        if ($taskCount > 0) {
            throw new \RuntimeException('Cannot delete project with tasks. Delete tasks first or archive the project.');
        }
        
        $result = $this->projectRepository->delete($projectId);
        
        if ($result) {
            Logger::info('Project deleted', [
                'project_id' => $projectId,
                'user_id' => $deletedBy
            ]);
        }
        
        return $result;
    }
    
    /**
     * Archive project (soft delete)
     * 
     * @param int $projectId Project ID
     * @param int|null $archivedBy User archiving (null = current user)
     * @return ProjectEntity Archived project
     */
    public function archiveProject(int $projectId, ?int $archivedBy = null): ProjectEntity
    {
        $archivedBy = $archivedBy ?? $this->auth->getCurrentUser()?->getId();
        
        // Update status to archived (6)
        return $this->updateProject($projectId, [
            'project_status' => 6,
            'project_name' => null, // Don't change name
        ], $archivedBy);
    }
    
    /**
     * Update project progress based on tasks
     * 
     * @param int $projectId Project ID
     * @return float New progress percentage
     */
    public function updateProgress(int $projectId): float
    {
        // Get all tasks for project
        $tasks = $this->taskRepository->findByProject($projectId);
        
        if (empty($tasks)) {
            return 0.0;
        }
        
        // Calculate weighted progress
        $totalHours = 0;
        $completedHours = 0;
        
        foreach ($tasks as $task) {
            $estimated = $task->getEstimatedHours() ?? 1;
            $totalHours += $estimated;
            $completedHours += $estimated * ($task->getPercentComplete() / 100);
        }
        
        $progress = $totalHours > 0 ? ($completedHours / $totalHours) * 100 : 0;
        $progress = round($progress, 2);
        
        // Update project
        $this->projectRepository->updatePercentComplete($projectId, (int) $progress);
        
        return $progress;
    }
    
    /**
     * Get project statistics
     * 
     * @param int $projectId Project ID
     * @param int|null $userId User requesting
     * @return array<string, mixed>|null Statistics or null if no access
     */
    public function getProjectStatistics(int $projectId, ?int $userId = null): ?array
    {
        // Check access
        if (!$this->auth->canAccessProject($projectId, AuthorizationService::PERMISSION_VIEW, $userId)) {
            return null;
        }
        
        $cacheKey = "stats:{$projectId}";
        $cached = $this->cache->get($cacheKey);
        
        if ($cached !== null) {
            return $cached;
        }
        
        $project = $this->projectRepository->find($projectId);
        if ($project === null) {
            return null;
        }
        
        // Task statistics
        $tasks = $this->taskRepository->findByProject($projectId);
        $totalTasks = count($tasks);
        $completedTasks = count(array_filter($tasks, fn($t) => $t->isCompleted()));
        $overdueTasks = count(array_filter($tasks, fn($t) => $t->isOverdue()));
        
        // Hours
        $totalEstimated = array_sum(array_map(fn($t) => $t->getEstimatedHours() ?? 0, $tasks));
        $totalActual = array_sum(array_map(fn($t) => $t->getActualHours() ?? 0, $tasks));
        
        // Health score (0-100)
        $healthScore = $this->calculateHealthScore($project, $tasks);
        
        $stats = [
            'project_id' => $projectId,
            'project_name' => $project->getName(),
            'progress' => $project->getPercentComplete(),
            'status' => $project->getStatus(),
            'is_overdue' => $project->isOverdue(),
            'days_remaining' => $project->getDaysRemaining(),
            'tasks' => [
                'total' => $totalTasks,
                'completed' => $completedTasks,
                'overdue' => $overdueTasks,
                'completion_rate' => $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 1) : 0,
            ],
            'hours' => [
                'estimated' => $totalEstimated,
                'actual' => $totalActual,
                'variance' => $totalActual - $totalEstimated,
            ],
            'health' => [
                'score' => $healthScore,
                'status' => $this->getHealthStatus($healthScore),
            ],
        ];
        
        // Cache for 5 minutes
        $this->cache->set($cacheKey, $stats, 300);
        
        return $stats;
    }
    
    /**
     * Search projects by name
     * 
     * @param string $query Search query
     * @param int|null $userId User searching
     * @return array<ProjectEntity>
     */
    public function searchProjects(string $query, ?int $userId = null): array
    {
        $userId = $userId ?? $this->auth->getCurrentUser()?->getId();
        
        if ($userId === null) {
            return [];
        }
        
        $projects = $this->projectRepository->searchByName($query);
        
        // Filter by access
        return array_filter($projects, function (ProjectEntity $project) use ($userId) {
            return $this->auth->canAccessProject($project->getId() ?? 0, AuthorizationService::PERMISSION_VIEW, $userId);
        });
    }
    
    /**
     * Get dashboard data for user
     * 
     * @param int|null $userId User ID
     * @return array<string, mixed>
     */
    public function getDashboardData(?int $userId = null): array
    {
        $userId = $userId ?? $this->auth->getCurrentUser()?->getId();
        
        if ($userId === null) {
            return [];
        }
        
        $cacheKey = "dashboard:{$userId}";
        $cached = $this->cache->get($cacheKey);
        
        if ($cached !== null) {
            return $cached;
        }
        
        $projects = $this->getAccessibleProjects($userId);
        $activeProjects = array_filter($projects, fn($p) => $p->isActive());
        $overdueProjects = array_filter($projects, fn($p) => $p->isOverdue());
        
        // Calculate average health
        $healthScores = [];
        foreach ($projects as $project) {
            $stats = $this->getProjectStatistics($project->getId() ?? 0, $userId);
            if ($stats) {
                $healthScores[] = $stats['health']['score'];
            }
        }
        
        $data = [
            'projects' => [
                'total' => count($projects),
                'active' => count($activeProjects),
                'overdue' => count($overdueProjects),
                'health_score' => !empty($healthScores) ? round(array_sum($healthScores) / count($healthScores), 1) : 0,
            ],
            'recent' => array_slice(array_map(fn($p) => $p->toArray(), $projects), 0, 5),
        ];
        
        // Cache for 2 minutes
        $this->cache->set($cacheKey, $data, 120);
        
        return $data;
    }
    
    /**
     * Calculate project health score (0-100)
     * 
     * @param ProjectEntity $project Project
     * @param array $tasks Project tasks
     * @return int Health score
     */
    private function calculateHealthScore(ProjectEntity $project, array $tasks): int
    {
        if (empty($tasks)) {
            return 50; // Neutral
        }
        
        $score = 100;
        
        // Deduct for overdue tasks
        $overdueCount = count(array_filter($tasks, fn($t) => $t->isOverdue()));
        $score -= $overdueCount * 10;
        
        // Deduct for overdue project
        if ($project->isOverdue()) {
            $score -= 20;
        }
        
        // Adjust based on completion
        $completedCount = count(array_filter($tasks, fn($t) => $t->isCompleted()));
        $completionRate = $completedCount / count($tasks);
        $score += ($completionRate * 20);
        
        return max(0, min(100, (int) $score));
    }
    
    /**
     * Get health status label
     */
    private function getHealthStatus(int $score): string
    {
        return match (true) {
            $score >= 80 => 'healthy',
            $score >= 60 => 'at_risk',
            $score >= 40 => 'warning',
            default => 'critical',
        };
    }
    
    /**
     * Generate random color for project
     */
    private function generateColor(): string
    {
        $colors = [
            'FF6B6B', 'FF8E53', 'FFDD59', '32FF7E', '18DCFF',
            '7D5FFF', 'C56CF0', 'FF9FF3', '54A0FF', '5F27CD',
            '00D2D3', 'FF9F43', '10AC84', 'EE5A6F', 'C44569'
        ];
        
        return $colors[array_rand($colors)];
    }
    
    /**
     * Get project with full details
     * 
     * @param int $projectId Project ID
     * @param int|null $userId User requesting
     * @return array<string, mixed>|null
     */
    public function getProjectDetails(int $projectId, ?int $userId = null): ?array
    {
        $userId = $userId ?? $this->auth->getCurrentUser()?->getId();
        
        // Check access
        if (!$this->auth->canAccessProject($projectId, AuthorizationService::PERMISSION_VIEW, $userId)) {
            return null;
        }
        
        $project = $this->projectRepository->find($projectId);
        if ($project === null) {
            return null;
        }
        
        $details = $project->toArray();
        $details['statistics'] = $this->getProjectStatistics($projectId, $userId);
        $details['can_edit'] = $this->auth->canAccessProject($projectId, AuthorizationService::PERMISSION_EDIT, $userId);
        $details['can_delete'] = $this->auth->canAccessProject($projectId, AuthorizationService::PERMISSION_DELETE, $userId);
        
        return $details;
    }
    
    /**
     * Bulk update project status
     * 
     * @param array<int> $projectIds Project IDs
     * @param int $newStatus New status
     * @param int|null $updatedBy User updating
     * @return int Number of projects updated
     */
    public function bulkUpdateStatus(array $projectIds, int $newStatus, ?int $updatedBy = null): int
    {
        $updatedBy = $updatedBy ?? $this->auth->getCurrentUser()?->getId();
        if ($updatedBy === null) {
            throw new \RuntimeException('User not authenticated');
        }

        $updated = 0;
        
        foreach ($projectIds as $projectId) {
            try {
                $this->updateProject($projectId, ['project_status' => $newStatus], $updatedBy);
                $updated++;
            } catch (\Exception $e) {
                Logger::warning('Failed to update project status', [
                    'project_id' => $projectId,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        return $updated;
    }
}
