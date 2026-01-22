<?php
/**
 * DotProject Task Service
 * 
 * Service class for task-related business operations.
 * Encapsulates business logic that can be shared between legacy and modern code.
 * 
 * @package DotProject\Service
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Service;

use DotProject\Core\Database;
use DotProject\Entity\Task;

/**
 * Task Service
 * 
 * Handles task-related business operations.
 */
class TaskService
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    /**
     * Get tasks for a project
     * 
     * @param int $projectId Project ID
     * @param bool $onlyParent If true, return only parent tasks
     * @return array<int, Task>
     */
    public function getProjectTasks(int $projectId, bool $onlyParent = false): array
    {
        $where = sprintf('task_project = %d', $projectId);

        if ($onlyParent) {
            $where .= ' AND task_id = task_parent';
        }

        return Task::findAll($where, 'task_start_date ASC, task_order ASC');
    }

    /**
     * Get child tasks of a parent task
     * 
     * @param int $parentId Parent task ID
     * @return array<int, Task>
     */
    public function getChildTasks(int $parentId): array
    {
        return Task::findAll(
            sprintf('task_parent = %d AND task_id != task_parent', $parentId),
            'task_order ASC'
        );
    }

    /**
     * Create a new task
     * 
     * @param array<string, mixed> $data Task data
     * @return Task|null Created task or null on failure
     */
    public function createTask(array $data): ?Task
    {
        // Validate required fields
        if (empty($data['task_name']) || empty($data['task_project'])) {
            return null;
        }

        // Set defaults
        $defaults = [
            'task_status' => 0,
            'task_percent_complete' => 0,
            'task_priority' => 0,
            'task_milestone' => 0,
            'task_dynamic' => 0,
            'task_access' => 0,
            'task_notify' => 0,
            'task_duration' => 1,
            'task_duration_type' => 1, // hours
        ];

        $data = array_merge($defaults, $data);

        $task = new Task($data);

        if ($task->save()) {
            // Set task_parent to itself if not specified
            if (empty($data['task_parent'])) {
                $task->setAttribute('task_parent', $task->getId());
                $task->save();
            }
            return $task;
        }

        return null;
    }

    /**
     * Update task progress
     * 
     * @param int $taskId Task ID
     * @param int $percent Completion percentage (0-100)
     * @return bool Success
     */
    public function updateProgress(int $taskId, int $percent): bool
    {
        $task = Task::find($taskId);
        if ($task === null) {
            return false;
        }

        $percent = max(0, min(100, $percent));
        $task->setAttribute('task_percent_complete', $percent);

        return $task->save();
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
     * Add a dependency between tasks
     * 
     * @param int $taskId The dependent task
     * @param int $requiredTaskId The required task
     * @return bool Success
     */
    public function addDependency(int $taskId, int $requiredTaskId): bool
    {
        // Prevent self-dependency
        if ($taskId === $requiredTaskId) {
            return false;
        }

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
     * Remove a dependency
     * 
     * @param int $taskId The dependent task
     * @param int $requiredTaskId The required task
     * @return bool Success
     */
    public function removeDependency(int $taskId, int $requiredTaskId): bool
    {
        return $this->db->delete(
            'task_dependencies',
            sprintf(
                'dependencies_task_id = %d AND dependencies_req_task_id = %d',
                $taskId,
                $requiredTaskId
            )
        );
    }

    /**
     * Get overdue tasks for a project
     * 
     * @param int|null $projectId Project ID (null for all projects)
     * @return array<int, Task>
     */
    public function getOverdueTasks(?int $projectId = null): array
    {
        $where = "task_percent_complete < 100 
                  AND task_end_date < NOW() 
                  AND task_end_date != '0000-00-00 00:00:00'";

        if ($projectId !== null) {
            $where .= sprintf(' AND task_project = %d', $projectId);
        }

        return Task::findAll($where, 'task_end_date ASC');
    }

    /**
     * Get milestones for a project
     * 
     * @param int $projectId Project ID
     * @return array<int, Task>
     */
    public function getMilestones(int $projectId): array
    {
        return Task::findAll(
            sprintf('task_project = %d AND task_milestone = 1', $projectId),
            'task_end_date ASC'
        );
    }

    /**
     * Get upcoming tasks (due in next N days)
     * 
     * @param int $days Number of days to look ahead
     * @param int|null $userId Filter by assigned user
     * @return array<int, Task>
     */
    public function getUpcomingTasks(int $days = 7, ?int $userId = null): array
    {
        $where = sprintf(
            "task_end_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL %d DAY)
             AND task_percent_complete < 100",
            $days
        );

        if ($userId !== null) {
            $where .= sprintf(' AND task_owner = %d', $userId);
        }

        return Task::findAll($where, 'task_end_date ASC');
    }

    /**
     * Assign user to task
     * 
     * @param int $taskId Task ID
     * @param int $userId User ID
     * @param int $percent Assignment percentage (0-100)
     * @return bool Success
     */
    public function assignUser(int $taskId, int $userId, int $percent = 100): bool
    {
        // Check if already assigned
        $sql = sprintf(
            "SELECT COUNT(*) FROM `%s` WHERE task_id = %d AND user_id = %d",
            $this->db->table('user_tasks'),
            $taskId,
            $userId
        );

        if ((int) $this->db->fetchValue($sql) > 0) {
            // Update existing assignment
            return $this->db->update(
                'user_tasks',
                ['perc_assignment' => $percent],
                sprintf('task_id = %d AND user_id = %d', $taskId, $userId)
            );
        }

        // Create new assignment
        $result = $this->db->insert('user_tasks', [
            'task_id' => $taskId,
            'user_id' => $userId,
            'perc_assignment' => $percent,
        ]);

        return $result !== false;
    }

    /**
     * Unassign user from task
     * 
     * @param int $taskId Task ID
     * @param int $userId User ID
     * @return bool Success
     */
    public function unassignUser(int $taskId, int $userId): bool
    {
        return $this->db->delete(
            'user_tasks',
            sprintf('task_id = %d AND user_id = %d', $taskId, $userId)
        );
    }

    /**
     * Get users assigned to a task
     * 
     * @param int $taskId Task ID
     * @return array<int, array<string, mixed>>
     */
    public function getAssignedUsers(int $taskId): array
    {
        $sql = sprintf(
            "SELECT u.user_id, u.user_username, c.contact_first_name, c.contact_last_name,
                    ut.perc_assignment
             FROM `%s` ut
             JOIN `%s` u ON u.user_id = ut.user_id
             LEFT JOIN `%s` c ON c.contact_id = u.user_contact
             WHERE ut.task_id = %d",
            $this->db->table('user_tasks'),
            $this->db->table('users'),
            $this->db->table('contacts'),
            $taskId
        );

        return $this->db->fetchAll($sql);
    }
}
