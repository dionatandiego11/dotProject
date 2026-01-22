<?php
/**
 * DotProject Project Service
 * 
 * Service class for project-related business operations.
 * Encapsulates business logic that can be shared between legacy and modern code.
 * 
 * @package DotProject\Service
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Service;

use DotProject\Core\Database;
use DotProject\Entity\Project;

/**
 * Project Service
 * 
 * Handles project-related business operations.
 */
class ProjectService
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    /**
     * Get active projects with optional filtering
     * 
     * @param int|null $companyId Filter by company
     * @param int|null $userId Filter by owner
     * @return array<int, Project>
     */
    public function getActiveProjects(?int $companyId = null, ?int $userId = null): array
    {
        $where = 'project_status = 3';

        if ($companyId !== null) {
            $where .= sprintf(' AND project_company = %d', $companyId);
        }

        if ($userId !== null) {
            $where .= sprintf(' AND project_owner = %d', $userId);
        }

        return Project::findAll($where, 'project_name ASC');
    }

    /**
     * Get projects by status
     * 
     * @param int $status Project status (0=Not Defined, 1=Proposed, 2=In Planning, 3=In Progress, 4=On Hold, 5=Complete, 6=Archived)
     * @return array<int, Project>
     */
    public function getProjectsByStatus(int $status): array
    {
        return Project::findAll(
            sprintf('project_status = %d', $status),
            'project_name ASC'
        );
    }

    /**
     * Create a new project
     * 
     * @param array<string, mixed> $data Project data
     * @return Project|null Created project or null on failure
     */
    public function createProject(array $data): ?Project
    {
        // Validate required fields
        if (empty($data['project_name'])) {
            return null;
        }

        if (empty($data['project_short_name'])) {
            $data['project_short_name'] = substr($data['project_name'], 0, 10);
        }

        // Set defaults
        $defaults = [
            'project_status' => 0,
            'project_percent_complete' => 0,
            'project_priority' => 0,
            'project_type' => 0,
            'project_private' => 0,
            'project_color_identifier' => $this->generateProjectColor(),
        ];

        $data = array_merge($defaults, $data);

        $project = new Project($data);

        if ($project->save()) {
            return $project;
        }

        return null;
    }

    /**
     * Update project progress based on task completion
     * 
     * @param int $projectId Project ID
     * @return float|null Updated percentage or null on failure
     */
    public function updateProgress(int $projectId): ?float
    {
        $project = Project::find($projectId);
        if ($project === null) {
            return null;
        }

        $workingHours = $this->getConfigValue('daily_working_hours', 8);

        $sql = sprintf(
            "SELECT 
                SUM(task_duration * task_percent_complete * IF(task_duration_type = 24, %d, task_duration_type)) / 
                SUM(task_duration * IF(task_duration_type = 24, %d, task_duration_type)) AS progress
            FROM `%s`
            WHERE task_project = %d AND task_id = task_parent",
            $workingHours,
            $workingHours,
            $this->db->table('tasks'),
            $projectId
        );

        $progress = (float) ($this->db->fetchValue($sql) ?? 0);

        $project->setAttribute('project_percent_complete', $progress);
        $project->save();

        return $progress;
    }

    /**
     * Get project statistics
     * 
     * @param int $projectId Project ID
     * @return array<string, mixed>
     */
    public function getProjectStatistics(int $projectId): array
    {
        $stats = [
            'total_tasks' => 0,
            'completed_tasks' => 0,
            'overdue_tasks' => 0,
            'total_hours_worked' => 0.0,
            'total_hours_estimated' => 0.0,
            'milestones' => 0,
            'progress' => 0.0,
        ];

        // Total tasks
        $sql = sprintf(
            "SELECT COUNT(*) FROM `%s` WHERE task_project = %d",
            $this->db->table('tasks'),
            $projectId
        );
        $stats['total_tasks'] = (int) ($this->db->fetchValue($sql) ?? 0);

        // Completed tasks (100%)
        $sql = sprintf(
            "SELECT COUNT(*) FROM `%s` WHERE task_project = %d AND task_percent_complete = 100",
            $this->db->table('tasks'),
            $projectId
        );
        $stats['completed_tasks'] = (int) ($this->db->fetchValue($sql) ?? 0);

        // Overdue tasks
        $sql = sprintf(
            "SELECT COUNT(*) FROM `%s` WHERE task_project = %d 
             AND task_percent_complete < 100 
             AND task_end_date < NOW() 
             AND task_end_date != '0000-00-00 00:00:00'",
            $this->db->table('tasks'),
            $projectId
        );
        $stats['overdue_tasks'] = (int) ($this->db->fetchValue($sql) ?? 0);

        // Total hours worked
        $sql = sprintf(
            "SELECT SUM(task_hours_worked) FROM `%s` WHERE task_project = %d",
            $this->db->table('tasks'),
            $projectId
        );
        $stats['total_hours_worked'] = (float) ($this->db->fetchValue($sql) ?? 0);

        // Milestones
        $sql = sprintf(
            "SELECT COUNT(*) FROM `%s` WHERE task_project = %d AND task_milestone = 1",
            $this->db->table('tasks'),
            $projectId
        );
        $stats['milestones'] = (int) ($this->db->fetchValue($sql) ?? 0);

        // Progress from project
        $project = Project::find($projectId);
        if ($project !== null) {
            $stats['progress'] = (float) $project->getPercentComplete();
        }

        return $stats;
    }

    /**
     * Get recent projects
     * 
     * @param int $limit Number of projects to return
     * @return array<int, Project>
     */
    public function getRecentProjects(int $limit = 10): array
    {
        $sql = sprintf(
            "SELECT * FROM `%s` ORDER BY project_id DESC LIMIT %d",
            $this->db->table('projects'),
            $limit
        );

        $rows = $this->db->fetchAll($sql);
        return array_map(fn($row) => Project::fromArray($row), $rows);
    }

    /**
     * Search projects by name
     * 
     * @param string $query Search query
     * @return array<int, Project>
     */
    public function searchProjects(string $query): array
    {
        $escapedQuery = $this->db->escape($query);

        return Project::findAll(
            sprintf(
                "project_name LIKE '%%%s%%' OR project_short_name LIKE '%%%s%%'",
                $escapedQuery,
                $escapedQuery
            ),
            'project_name ASC'
        );
    }

    /**
     * Generate a random color for project identifier
     */
    private function generateProjectColor(): string
    {
        $colors = [
            'FF6B6B',
            'FF8E53',
            'FFDD59',
            '32FF7E',
            '18DCFF',
            '7D5FFF',
            'C56CF0',
            'FF9FF3',
            '54A0FF',
            '5F27CD'
        ];

        return $colors[array_rand($colors)];
    }

    /**
     * Get configuration value
     */
    private function getConfigValue(string $key, mixed $default = null): mixed
    {
        global $dPconfig;
        return $dPconfig[$key] ?? $default;
    }
}
