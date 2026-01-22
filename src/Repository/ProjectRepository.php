<?php
/**
 * DotProject Project Repository
 * 
 * Repository for Project entity data access.
 * 
 * @package DotProject\Repository
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Repository;

use DotProject\Entity\Project;

/**
 * Project Repository
 * 
 * @extends BaseRepository<Project>
 */
class ProjectRepository extends BaseRepository
{
    protected function getEntityClass(): string
    {
        return Project::class;
    }

    protected function getTable(): string
    {
        return 'projects';
    }

    protected function getPrimaryKey(): string
    {
        return 'project_id';
    }

    /**
     * Find active projects (status = 3)
     * 
     * @return array<int, Project>
     */
    public function findActive(): array
    {
        return $this->findBy(['project_status' => 3], 'project_name ASC');
    }

    /**
     * Find projects by status
     * 
     * @param int $status Project status
     * @return array<int, Project>
     */
    public function findByStatus(int $status): array
    {
        return $this->findBy(['project_status' => $status], 'project_name ASC');
    }

    /**
     * Find projects by company
     * 
     * @param int $companyId Company ID
     * @return array<int, Project>
     */
    public function findByCompany(int $companyId): array
    {
        return $this->findBy(['project_company' => $companyId], 'project_name ASC');
    }

    /**
     * Find projects by owner
     * 
     * @param int $ownerId Owner user ID
     * @return array<int, Project>
     */
    public function findByOwner(int $ownerId): array
    {
        return $this->findBy(['project_owner' => $ownerId], 'project_name ASC');
    }

    /**
     * Search projects by name
     * 
     * @param string $query Search query
     * @return array<int, Project>
     */
    public function search(string $query): array
    {
        $escaped = $this->db->escape($query);

        $sql = sprintf(
            "SELECT * FROM `%s` WHERE project_name LIKE '%%%s%%' OR project_short_name LIKE '%%%s%%' ORDER BY project_name ASC",
            $this->db->table($this->getTable()),
            $escaped,
            $escaped
        );

        return $this->query($sql);
    }

    /**
     * Find recent projects
     * 
     * @param int $limit Number of projects
     * @return array<int, Project>
     */
    public function findRecent(int $limit = 10): array
    {
        $sql = sprintf(
            "SELECT * FROM `%s` ORDER BY project_id DESC LIMIT %d",
            $this->db->table($this->getTable()),
            $limit
        );

        return $this->query($sql);
    }

    /**
     * Count projects by status
     * 
     * @return array<int, int> Status => count
     */
    public function countByStatus(): array
    {
        $sql = sprintf(
            "SELECT project_status, COUNT(*) as cnt FROM `%s` GROUP BY project_status",
            $this->db->table($this->getTable())
        );

        $rows = $this->db->fetchAll($sql);
        $result = [];

        foreach ($rows as $row) {
            $result[(int) $row['project_status']] = (int) $row['cnt'];
        }

        return $result;
    }

    /**
     * Find projects with task statistics
     * 
     * @param int|null $status Optional status filter
     * @return array<int, array<string, mixed>>
     */
    public function findWithTaskStats(?int $status = null): array
    {
        $sql = sprintf(
            "SELECT p.*, 
                    COUNT(t.task_id) as total_tasks,
                    SUM(CASE WHEN t.task_percent_complete = 100 THEN 1 ELSE 0 END) as completed_tasks
             FROM `%s` p
             LEFT JOIN `%s` t ON t.task_project = p.project_id",
            $this->db->table('projects'),
            $this->db->table('tasks')
        );

        if ($status !== null) {
            $sql .= sprintf(' WHERE p.project_status = %d', $status);
        }

        $sql .= ' GROUP BY p.project_id ORDER BY p.project_name ASC';

        return $this->db->fetchAll($sql);
    }
}
