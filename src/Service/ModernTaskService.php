<?php
/**
 * DotProject Modern Task Service
 * 
 * Modern service using Entity/Repository pattern for task management.
 * Integrates with AuthorizationService for permission control.
 * 
 * @package DotProject\Service
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Service;

use DotProject\Core\Cache;
use DotProject\Core\Database;
use DotProject\Core\Logger;
use DotProject\Entity\TaskEntity;
use DotProject\Repository\TaskRepository;
use DotProject\Repository\ProjectRepository;

/**
 * Modern Task Service
 * 
 * Business logic layer for task management with:
 * - Complete CRUD operations
 * - Permission checks
 * - Task dependencies management
 * - Assignment management
 * - Progress tracking
 */
class ModernTaskService
{
    private TaskRepository $taskRepository;
    private ProjectRepository $projectRepository;
    private AuthorizationService $auth;
    private ValidationService $validator;
    private Database $db;
    private Cache $cache;
    
    public function __construct(
        ?TaskRepository $taskRepository = null,
        ?ProjectRepository $projectRepository = null,
        ?AuthorizationService $auth = null,
        ?ValidationService $validator = null,
        ?Database $db = null,
        ?Cache $cache = null
    ) {
        $this->taskRepository = $taskRepository ?? new TaskRepository();
        $this->projectRepository = $projectRepository ?? new ProjectRepository();
        $this->auth = $auth ?? AuthorizationService::getInstance();
        $this->validator = $validator ?? new ValidationService();
        $this->db = $db ?? Database::getInstance();
        $this->cache = $cache ?? new Cache(prefix: 'task_service:');
    }
    
    /**
     * Get task by ID with permission check
     * 
     * @param int $taskId Task ID
     * @param int|null $userId User requesting
     * @return TaskEntity|null Task or null if not found/no access
     */
    public function getTask(int $taskId, ?int $userId = null): ?TaskEntity
    {
        $userId = $userId ?? $this->auth->getCurrentUser()?->getId();
        
        // Check permission
        if (!$this->auth->canAccessTask($taskId, AuthorizationService::PERMISSION_VIEW, $userId)) {
            Logger::warning('Unauthorized task access attempt', [
                'task_id' => $taskId,
                'user_id' => $userId
            ]);
            return null;
        }
        
        return $this->taskRepository->find($taskId);
    }
    
    /**
     * Get tasks by project
     * 
     * @param int $projectId Project ID
     * @param int|null $userId User requesting
     * @return array<TaskEntity>
     */
    public function getProjectTasks(int $projectId, ?int $userId = null): array
    {
        $userId = $userId ?? $this->auth->getCurrentUser()?->getId();
        
        // Check project access
        if (!$this->auth->canAccessProject($projectId, AuthorizationService::PERMISSION_VIEW, $userId)) {
            return [];
        }
        
        return $this->taskRepository->findByProject($projectId);
    }
    
    /**
     * Get tasks assigned to user
     * 
     * @param int|null $userId User ID (null = current user)
     * @param array<string, mixed> $filters Optional filters
     * @return array<TaskEntity>
     */
    public function getMyTasks(?int $userId = null, array $filters = []): array
    {
        $userId = $userId ?? $this->auth->getCurrentUser()?->getId();
        
        if ($userId === null) {
            return [];
        }
        
        $tasks = $this->taskRepository->findByAssignee($userId);
        
        // Apply additional filters
        if (isset($filters['status'])) {
            $tasks = array_filter($tasks, fn($t) => $t->getStatus() === $filters['status']);
        }
        if (isset($filters['overdue']) && $filters['overdue']) {
            $tasks = array_filter($tasks, fn($t) => $t->isOverdue());
        }
        if (isset($filters['completed']) && $filters['completed']) {
            $tasks = array_filter($tasks, fn($t) => $t->isCompleted());
        }
        
        return array_values($tasks);
    }
    
    /**
     * Get overdue tasks for user
     * 
     * @param int|null $userId User ID
     * @return array<TaskEntity>
     */
    public function getOverdueTasks(?int $userId = null): array
    {
        $userId = $userId ?? $this->auth->getCurrentUser()?->getId();
        
        if ($userId === null) {
            return [];
        }
        
        $tasks = $this->taskRepository->findOverdue();
        
        // Filter by access
        return array_filter($tasks, function (TaskEntity $task) use ($userId) {
            return $this->auth->canAccessTask($task->getId() ?? 0, AuthorizationService::PERMISSION_VIEW, $userId);
        });
    }
    
    /**
     * Get tasks due soon
     * 
     * @param int $days Number of days ahead
     * @param int|null $userId User ID
     * @return array<TaskEntity>
     */
    public function getTasksDueSoon(int $days = 7, ?int $userId = null): array
    {
        $userId = $userId ?? $this->auth->getCurrentUser()?->getId();
        
        if ($userId === null) {
            return [];
        }
        
        $tasks = $this->taskRepository->findDueSoon($days);
        
        // Filter by access
        return array_filter($tasks, function (TaskEntity $task) use ($userId) {
            return $this->auth->canAccessTask($task->getId() ?? 0, AuthorizationService::PERMISSION_VIEW, $userId);
        });
    }
    
    /**
     * Create new task
     * 
     * @param array<string, mixed> $data Task data
     * @param int|null $createdBy User creating
     * @return TaskEntity Created task
     * @throws \InvalidArgumentException If validation fails
     * @throws \RuntimeException If no permission
     */
    public function createTask(array $data, ?int $createdBy = null): TaskEntity
    {
        $createdBy = $createdBy ?? $this->auth->getCurrentUser()?->getId();
        
        if ($createdBy === null) {
            throw new \RuntimeException('User not authenticated');
        }
        
        // Validate project exists and user has access
        $projectId = $data['task_project'] ?? null;
        if (!$projectId) {
            throw new \RuntimeException('Project is required');
        }
        
        $this->auth->enforceProjectAccess($projectId, AuthorizationService::PERMISSION_EDIT, $createdBy);
        
        // Check task creation permission
        $this->auth->enforce(AuthorizationService::RESOURCE_TASK, AuthorizationService::PERMISSION_CREATE, $createdBy);
        
        // Validate
        $validation = $this->validator->validateTask($data);
        if ($validation->fails()) {
            throw new \InvalidArgumentException($validation->firstError() ?? 'Validation failed');
        }
        
        // Create entity
        $task = new TaskEntity();
        $task->setName($data['task_name']);
        $task->setDescription($data['task_description'] ?? null);
        $task->setProjectId((int) $projectId);
        $task->setOwnerId($createdBy);
        $task->setStatus((int) ($data['task_status'] ?? 0));
        $task->setPriority((int) ($data['task_priority'] ?? 3));
        $task->setPercentComplete(0);
        
        // Assignment
        if (!empty($data['task_assigned_to'])) {
            $task->setAssignedTo((int) $data['task_assigned_to']);
        }
        
        // Parent task (subtask)
        if (!empty($data['task_parent']) && $data['task_parent'] !== $task->getId()) {
            $task->setParentTaskId((int) $data['task_parent']);
        }
        
        // Hours
        if (isset($data['task_hours'])) {
            $task->setEstimatedHours((float) $data['task_hours']);
        }
        if (isset($data['task_actual_hours'])) {
            $task->setActualHours((float) $data['task_actual_hours']);
        }
        
        // Dates
        if (!empty($data['task_start_date'])) {
            $task->setStartDate(new \DateTime($data['task_start_date']));
        }
        if (!empty($data['task_end_date'])) {
            $task->setEndDate(new \DateTime($data['task_end_date']));
        }
        
        // Save
        if (!$this->taskRepository->save($task)) {
            throw new \RuntimeException('Failed to create task');
        }
        
        // Set parent to self if not a subtask
        if ($task->getParentTaskId() === null) {
            $task->setParentTaskId($task->getId());
            $this->taskRepository->save($task);
        }
        
        // Assign user if specified
        if ($task->getAssignedTo()) {
            $this->assignUser($task->getId() ?? 0, $task->getAssignedTo(), 100, $createdBy);
        }
        
        Logger::info('Task created', [
            'task_id' => $task->getId(),
            'project_id' => $projectId,
            'user_id' => $createdBy
        ]);
        
        return $task;
    }
    
    /**
     * Update task
     * 
     * @param int $taskId Task ID
     * @param array<string, mixed> $data Update data
     * @param int|null $updatedBy User updating
     * @return TaskEntity Updated task
     */
    public function updateTask(int $taskId, array $data, ?int $updatedBy = null): TaskEntity
    {
        $updatedBy = $updatedBy ?? $this->auth->getCurrentUser()?->getId();
        
        if ($updatedBy === null) {
            throw new \RuntimeException('User not authenticated');
        }
        
        // Check permission
        $this->auth->enforceTaskAccess($taskId, AuthorizationService::PERMISSION_EDIT, $updatedBy);
        
        // Get task
        $task = $this->taskRepository->find($taskId);
        if ($task === null) {
            throw new \RuntimeException('Task not found');
        }
        
        // Update fields
        if (isset($data['task_name'])) {
            $task->setName($data['task_name']);
        }
        if (isset($data['task_description'])) {
            $task->setDescription($data['task_description']);
        }
        if (isset($data['task_status'])) {
            $task->setStatus((int) $data['task_status']);
        }
        if (isset($data['task_priority'])) {
            $task->setPriority((int) $data['task_priority']);
        }
        if (isset($data['task_percent_complete'])) {
            $task->setPercentComplete((int) $data['task_percent_complete']);
        }
        if (isset($data['task_assigned_to'])) {
            $task->setAssignedTo($data['task_assigned_to'] ?: null);
            // Update assignment
            if ($task->getAssignedTo()) {
                $this->assignUser($taskId, $task->getAssignedTo(), 100, $updatedBy);
            } else {
                $this->unassignAllUsers($taskId);
            }
        }
        
        // Hours
        if (isset($data['task_hours'])) {
            $task->setEstimatedHours($data['task_hours'] ? (float) $data['task_hours'] : null);
        }
        if (isset($data['task_actual_hours'])) {
            $task->setActualHours($data['task_actual_hours'] ? (float) $data['task_actual_hours'] : null);
        }
        
        // Dates
        if (isset($data['task_start_date'])) {
            $task->setStartDate($data['task_start_date'] ? new \DateTime($data['task_start_date']) : null);
        }
        if (isset($data['task_end_date'])) {
            $task->setEndDate($data['task_end_date'] ? new \DateTime($data['task_end_date']) : null);
        }
        
        // Save
        if (!$this->taskRepository->save($task)) {
            throw new \RuntimeException('Failed to update task');
        }
        
        Logger::info('Task updated', [
            'task_id' => $taskId,
            'user_id' => $updatedBy
        ]);
        
        return $task;
    }
    
    /**
     * Update task progress
     * 
     * @param int $taskId Task ID
     * @param int $percent Progress percentage (0-100)
     * @param int|null $updatedBy User updating
     * @return TaskEntity Updated task
     */
    public function updateProgress(int $taskId, int $percent, ?int $updatedBy = null): TaskEntity
    {
        $percent = max(0, min(100, $percent));
        
        // Also update actual hours if provided
        $data = ['task_percent_complete' => $percent];
        
        // If 100%, set actual end date
        if ($percent === 100) {
            $data['task_actual_end_date'] = date('Y-m-d');
        }
        
        return $this->updateTask($taskId, $data, $updatedBy);
    }
    
    /**
     * Delete task
     * 
     * @param int $taskId Task ID
     * @param int|null $deletedBy User deleting
     * @return bool Success
     */
    public function deleteTask(int $taskId, ?int $deletedBy = null): bool
    {
        $deletedBy = $deletedBy ?? $this->auth->getCurrentUser()?->getId();
        
        if ($deletedBy === null) {
            throw new \RuntimeException('User not authenticated');
        }
        
        // Check permission
        $this->auth->enforceTaskAccess($taskId, AuthorizationService::PERMISSION_DELETE, $deletedBy);
        
        // Check for dependencies
        $dependencies = $this->getDependencies($taskId);
        if (!empty($dependencies)) {
            throw new \RuntimeException('Cannot delete task with dependencies. Remove dependencies first.');
        }
        
        // Check for subtasks
        $subtasks = $this->taskRepository->findBy(['task_parent' => $taskId, 'task_id' => ['operator' => '!=', 'value' => $taskId]]);
        if (!empty($subtasks)) {
            throw new \RuntimeException('Cannot delete task with subtasks. Delete subtasks first.');
        }
        
        $result = $this->taskRepository->delete($taskId);
        
        if ($result) {
            Logger::info('Task deleted', [
                'task_id' => $taskId,
                'user_id' => $deletedBy
            ]);
        }
        
        return $result;
    }
    
    /**
     * Assign user to task
     * 
     * @param int $taskId Task ID
     * @param int $userId User to assign
     * @param int $percent Assignment percentage
     * @param int|null $assignedBy User assigning
     * @return bool Success
     */
    public function assignUser(int $taskId, int $userId, int $percent = 100, ?int $assignedBy = null): bool
    {
        $assignedBy = $assignedBy ?? $this->auth->getCurrentUser()?->getId();

        if ($assignedBy === null) {
            throw new \RuntimeException('User not authenticated');
        }
        
        // Check permission
        $this->auth->enforceTaskAccess($taskId, AuthorizationService::PERMISSION_EDIT, $assignedBy);
        
        $percent = max(0, min(100, $percent));
        
        // Check if already assigned
        $sql = sprintf(
            "SELECT COUNT(*) FROM `%s` WHERE task_id = %d AND user_id = %d",
            $this->db->table('user_tasks'),
            $taskId,
            $userId
        );
        
        if ((int) $this->db->fetchValue($sql) > 0) {
            // Update existing
            return $this->db->update(
                'user_tasks',
                ['perc_assignment' => $percent],
                "task_id = {$taskId} AND user_id = {$userId}"
            );
        }
        
        // Create new assignment
        $result = $this->db->insert('user_tasks', [
            'task_id' => $taskId,
            'user_id' => $userId,
            'perc_assignment' => $percent,
        ]);
        
        if ($result) {
            // Update task assigned_to
            $task = $this->taskRepository->find($taskId);
            if ($task && $task->getAssignedTo() === null) {
                $task->setAssignedTo($userId);
                $this->taskRepository->save($task);
            }
        }
        
        return $result;
    }
    
    /**
     * Unassign user from task
     * 
     * @param int $taskId Task ID
     * @param int $userId User to unassign
     * @param int|null $unassignedBy User unassigning
     * @return bool Success
     */
    public function unassignUser(int $taskId, int $userId, ?int $unassignedBy = null): bool
    {
        $unassignedBy = $unassignedBy ?? $this->auth->getCurrentUser()?->getId();

        if ($unassignedBy === null) {
            throw new \RuntimeException('User not authenticated');
        }
        
        $this->auth->enforceTaskAccess($taskId, AuthorizationService::PERMISSION_EDIT, $unassignedBy);
        
        $result = $this->db->delete(
            'user_tasks',
            "task_id = {$taskId} AND user_id = {$userId}"
        );
        
        if ($result) {
            // Update task assigned_to if this was the primary assignee
            $task = $this->taskRepository->find($taskId);
            if ($task && $task->getAssignedTo() === $userId) {
                // Get remaining assignees
                $sql = sprintf(
                    "SELECT user_id FROM `%s` WHERE task_id = %d ORDER BY perc_assignment DESC LIMIT 1",
                    $this->db->table('user_tasks'),
                    $taskId
                );
                $newAssignee = $this->db->fetchValue($sql);
                
                $task->setAssignedTo($newAssignee ? (int) $newAssignee : null);
                $this->taskRepository->save($task);
            }
        }
        
        return $result;
    }
    
    /**
     * Unassign all users from task
     */
    private function unassignAllUsers(int $taskId): bool
    {
        return $this->db->delete('user_tasks', "task_id = {$taskId}");
    }
    
    /**
     * Get task dependencies
     * 
     * @param int $taskId Task ID
     * @return array<int, array<string, mixed>>
     */
    public function getDependencies(int $taskId): array
    {
        $sql = sprintf(
            "SELECT t.*, td.dependencies_req_task_id 
             FROM `%s` td
             JOIN `%s` t ON t.task_id = td.dependencies_req_task_id
             WHERE td.dependencies_task_id = %d",
            $this->db->table('task_dependencies'),
            $this->db->table('tasks'),
            $taskId
        );
        
        return $this->db->fetchAll($sql);
    }
    
    /**
     * Get tasks depending on this task
     * 
     * @param int $taskId Task ID
     * @return array<int, array<string, mixed>>
     */
    public function getDependents(int $taskId): array
    {
        $sql = sprintf(
            "SELECT t.*, td.dependencies_task_id 
             FROM `%s` td
             JOIN `%s` t ON t.task_id = td.dependencies_task_id
             WHERE td.dependencies_req_task_id = %d",
            $this->db->table('task_dependencies'),
            $this->db->table('tasks'),
            $taskId
        );
        
        return $this->db->fetchAll($sql);
    }
    
    /**
     * Add dependency between tasks
     * 
     * @param int $taskId The dependent task
     * @param int $requiredTaskId The required task
     * @param int|null $createdBy User creating
     * @return bool Success
     */
    public function addDependency(int $taskId, int $requiredTaskId, ?int $createdBy = null): bool
    {
        $createdBy = $createdBy ?? $this->auth->getCurrentUser()?->getId();

        if ($createdBy === null) {
            throw new \RuntimeException('User not authenticated');
        }

        // Prevent self-dependency before permission checks.
        if ($taskId === $requiredTaskId) {
            throw new \InvalidArgumentException('Task cannot depend on itself');
        }
        
        // Check permissions on both tasks
        $this->auth->enforceTaskAccess($taskId, AuthorizationService::PERMISSION_EDIT, $createdBy);
        $this->auth->enforceTaskAccess($requiredTaskId, AuthorizationService::PERMISSION_VIEW, $createdBy);
        
        // Check if already exists
        $sql = sprintf(
            "SELECT COUNT(*) FROM `%s` 
             WHERE dependencies_task_id = %d AND dependencies_req_task_id = %d",
            $this->db->table('task_dependencies'),
            $taskId,
            $requiredTaskId
        );
        
        if ((int) $this->db->fetchValue($sql) > 0) {
            return true; // Already exists
        }
        
        $result = $this->db->insert('task_dependencies', [
            'dependencies_task_id' => $taskId,
            'dependencies_req_task_id' => $requiredTaskId,
        ]);
        
        return $result !== false;
    }
    
    /**
     * Remove dependency
     * 
     * @param int $taskId The dependent task
     * @param int $requiredTaskId The required task
     * @param int|null $deletedBy User removing
     * @return bool Success
     */
    public function removeDependency(int $taskId, int $requiredTaskId, ?int $deletedBy = null): bool
    {
        $deletedBy = $deletedBy ?? $this->auth->getCurrentUser()?->getId();

        if ($deletedBy === null) {
            throw new \RuntimeException('User not authenticated');
        }
        
        $this->auth->enforceTaskAccess($taskId, AuthorizationService::PERMISSION_EDIT, $deletedBy);
        
        return $this->db->delete(
            'task_dependencies',
            "dependencies_task_id = {$taskId} AND dependencies_req_task_id = {$requiredTaskId}"
        );
    }
    
    /**
     * Get task statistics
     * 
     * @param int|null $projectId Project ID (null for all accessible projects)
     * @param int|null $userId User ID
     * @return array<string, mixed>
     */
    public function getTaskStatistics(?int $projectId = null, ?int $userId = null): array
    {
        $userId = $userId ?? $this->auth->getCurrentUser()?->getId();
        
        if ($projectId) {
            $tasks = $this->getProjectTasks($projectId, $userId);
        } else {
            $tasks = $this->getMyTasks($userId);
        }
        
        $total = count($tasks);
        $completed = count(array_filter($tasks, fn($t) => $t->isCompleted()));
        $overdue = count(array_filter($tasks, fn($t) => $t->isOverdue()));
        $active = count(array_filter($tasks, fn($t) => $t->isActive()));
        
        // Priority breakdown
        $byPriority = [
            'low' => count(array_filter($tasks, fn($t) => $t->getPriority() === 1)),
            'medium' => count(array_filter($tasks, fn($t) => $t->getPriority() === 2)),
            'high' => count(array_filter($tasks, fn($t) => $t->getPriority() === 3)),
            'urgent' => count(array_filter($tasks, fn($t) => $t->getPriority() === 4)),
        ];
        
        return [
            'total' => $total,
            'completed' => $completed,
            'active' => $active,
            'overdue' => $overdue,
            'completion_rate' => $total > 0 ? round(($completed / $total) * 100, 1) : 0,
            'by_priority' => $byPriority,
        ];
    }
    
    /**
     * Log time to task
     * 
     * @param int $taskId Task ID
     * @param float $hours Hours worked
     * @param string|null $description Work description
     * @param int|null $userId User logging time
     * @return bool Success
     */
    public function logTime(int $taskId, float $hours, ?string $description = null, ?int $userId = null): bool
    {
        $userId = $userId ?? $this->auth->getCurrentUser()?->getId();
        
        if ($userId === null) {
            throw new \RuntimeException('User not authenticated');
        }
        
        $this->auth->enforceTaskAccess($taskId, AuthorizationService::PERMISSION_EDIT, $userId);
        
        $task = $this->taskRepository->find($taskId);
        if ($task === null) {
            throw new \RuntimeException('Task not found');
        }
        
        // Update actual hours
        $currentHours = $task->getActualHours() ?? 0;
        $task->setActualHours($currentHours + $hours);
        
        // Log to task_log
        $result = $this->db->insert('task_log', [
            'task_log_task' => $taskId,
            'task_log_creator' => $userId,
            'task_log_hours' => $hours,
            'task_log_description' => $description ?? '',
            'task_log_date' => date('Y-m-d H:i:s'),
        ]);
        
        if ($result) {
            $this->taskRepository->save($task);
            Logger::info('Time logged', [
                'task_id' => $taskId,
                'user_id' => $userId,
                'hours' => $hours
            ]);
        }
        
        return $result;
    }
    
    /**
     * Get task with full details
     * 
     * @param int $taskId Task ID
     * @param int|null $userId User requesting
     * @return array<string, mixed>|null
     */
    public function getTaskDetails(int $taskId, ?int $userId = null): ?array
    {
        $userId = $userId ?? $this->auth->getCurrentUser()?->getId();
        
        $task = $this->getTask($taskId, $userId);
        if ($task === null) {
            return null;
        }
        
        $details = $task->toArray();
        $details['dependencies'] = $this->getDependencies($taskId);
        $details['dependents'] = $this->getDependents($taskId);
        $details['can_edit'] = $this->auth->canAccessTask($taskId, AuthorizationService::PERMISSION_EDIT, $userId);
        $details['can_delete'] = $this->auth->canAccessTask($taskId, AuthorizationService::PERMISSION_DELETE, $userId);
        
        // Get project info
        $project = $this->projectRepository->find($task->getProjectId());
        if ($project) {
            $details['project_name'] = $project->getName();
        }
        
        return $details;
    }
}
