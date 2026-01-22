<?php
/**
 * DotProject Task Repository
 * 
 * Repository for Task entity data access.
 * 
 * @package DotProject\Repository
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Repository;

use DotProject\Entity\Task;

/**
 * Task Repository
 * 
 * @extends BaseRepository<Task>
 */
class TaskRepository extends BaseRepository
{
    protected function getEntityClass(): string
    {
        return Task::class;
    }

    protected function getTable(): string
    {
        return 'tasks';
    }

    protected function getPrimaryKey(): string
    {
        return 'task_id';
    }

    /**
     * Find tasks by project
     * 
     * @param int $projectId Project ID
     * @param bool $parentOnly Return only parent tasks
     * @return array<int, Task>
     */
    public function findByProject(int $projectId, bool $parentOnly = false): array
    {
        $sql = sprintf(
            "SELECT * FROM `%s` WHERE task_project = %d",
            $this->db->table($this->getTable()),
            $projectId
        );

        if ($parentOnly) {
            $sql .= ' AND task_id = task_parent';
        }

        $sql .= ' ORDER BY task_start_date ASC, task_order ASC';

        return $this->query($sql);
    }

    /**
     * Find child tasks
     * 
     * @param int $parentId Parent task ID
     * @return array<int, Task>
     */
    public function findChildren(int $parentId): array
    {
        $sql = sprintf(
            "SELECT * FROM `%s` WHERE task_parent = %d AND task_id != task_parent ORDER BY task_order ASC",
            $this->db->table($this->getTable()),
            $parentId
        );

        return $this->query($sql);
    }

    /**
     * Find overdue tasks
     * 
     * @param int|null $projectId Optional project filter
     * @return array<int, Task>
     */
    public function findOverdue(?int $projectId = null): array
    {
        $sql = sprintf(
            "SELECT * FROM `%s` WHERE task_percent_complete < 100 
             AND task_end_date < NOW() 
             AND task_end_date != '0000-00-00 00:00:00'",
            $this->db->table($this->getTable())
        );

        if ($projectId !== null) {
            $sql .= sprintf(' AND task_project = %d', $projectId);
        }

        $sql .= ' ORDER BY task_end_date ASC';

        return $this->query($sql);
    }

    /**
     * Find milestones
     * 
     * @param int $projectId Project ID
     * @return array<int, Task>
     */
    public function findMilestones(int $projectId): array
    {
        return $this->findBy(
            ['task_project' => $projectId, 'task_milestone' => 1],
            'task_end_date ASC'
        );
    }

    /**
     * Find tasks by owner
     * 
     * @param int $ownerId Owner user ID
     * @return array<int, Task>
     */
    public function findByOwner(int $ownerId): array
    {
        return $this->findBy(['task_owner' => $ownerId], 'task_end_date ASC');
    }

    /**
     * Find tasks due in next N days
     * 
     * @param int $days Number of days
     * @param int|null $userId Optional user filter
     * @return array<int, Task>
     */
    public function findUpcoming(int $days = 7, ?int $userId = null): array
    {
        $sql = sprintf(
            "SELECT * FROM `%s` 
             WHERE task_end_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL %d DAY)
             AND task_percent_complete < 100",
            $this->db->table($this->getTable()),
            $days
        );

        if ($userId !== null) {
            $sql .= sprintf(' AND task_owner = %d', $userId);
        }

        $sql .= ' ORDER BY task_end_date ASC';

        return $this->query($sql);
    }

    /**
     * Find assigned tasks for a user
     * 
     * @param int $userId User ID
     * @return array<int, Task>
     */
    public function findAssignedTo(int $userId): array
    {
        $sql = sprintf(
            "SELECT t.* FROM `%s` t
             JOIN `%s` ut ON ut.task_id = t.task_id
             WHERE ut.user_id = %d
             ORDER BY t.task_end_date ASC",
            $this->db->table('tasks'),
            $this->db->table('user_tasks'),
            $userId
        );

        return $this->query($sql);
    }

    /**
     * Count tasks by project
     * 
     * @param int $projectId Project ID
     * @return array<string, int> Keys: total, completed, overdue
     */
    public function countByProject(int $projectId): array
    {
        $result = ['total' => 0, 'completed' => 0, 'overdue' => 0];

        // Total
        $result['total'] = $this->count(['task_project' => $projectId]);

        // Completed
        $sql = sprintf(
            "SELECT COUNT(*) FROM `%s` WHERE task_project = %d AND task_percent_complete = 100",
            $this->db->table($this->getTable()),
            $projectId
        );
        $result['completed'] = (int) ($this->db->fetchValue($sql) ?? 0);

        // Overdue
        $sql = sprintf(
            "SELECT COUNT(*) FROM `%s` 
             WHERE task_project = %d 
             AND task_percent_complete < 100 
             AND task_end_date < NOW() 
             AND task_end_date != '0000-00-00 00:00:00'",
            $this->db->table($this->getTable()),
            $projectId
        );
        $result['overdue'] = (int) ($this->db->fetchValue($sql) ?? 0);

        return $result;
    }

    /**
     * Get task dependencies
     * 
     * @param int $taskId Task ID
     * @return array<int, Task>
     */
    public function getDependencies(int $taskId): array
    {
        $sql = sprintf(
            "SELECT t.* FROM `%s` td
             JOIN `%s` t ON t.task_id = td.dependencies_req_task_id
             WHERE td.dependencies_task_id = %d",
            $this->db->table('task_dependencies'),
            $this->db->table('tasks'),
            $taskId
        );

        return $this->query($sql);
    }

    /**
     * Get tasks that depend on this task
     * 
     * @param int $taskId Task ID
     * @return array<int, Task>
     */
    public function getDependents(int $taskId): array
    {
        $sql = sprintf(
            "SELECT t.* FROM `%s` td
             JOIN `%s` t ON t.task_id = td.dependencies_task_id
             WHERE td.dependencies_req_task_id = %d",
            $this->db->table('task_dependencies'),
            $this->db->table('tasks'),
            $taskId
        );

        return $this->query($sql);
    }
}
