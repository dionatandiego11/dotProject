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
use DotProject\Core\TenantContext;
use DotProject\Entity\Project;

/**
 * Project Service
 * 
 * Handles project-related business operations.
 */
class ProjectService
{
    private Database $db;
    /** @var array<string, bool> */
    private array $columnPresenceCache = [];

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
        $where .= $this->tenantAndCondition($this->db->table('projects'));

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
            sprintf(
                'project_status = %d%s',
                $status,
                $this->tenantAndCondition($this->db->table('projects'))
            ),
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
        $tenantId = $this->getTenantId();
        if ($tenantId !== null && $this->hasTableColumn($this->db->table('projects'), 'tenant_id')) {
            $data['tenant_id'] = $tenantId;
        }

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
        $projectExists = $this->db->fetchValue(sprintf(
            "SELECT project_id FROM `%s` WHERE project_id = %d%s",
            $this->db->table('projects'),
            $projectId,
            $this->tenantAndCondition($this->db->table('projects'))
        ));
        if ($projectExists === null) {
            return null;
        }

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
            WHERE task_project = %d AND task_id = task_parent%s",
            $workingHours,
            $workingHours,
            $this->db->table('tasks'),
            $projectId,
            $this->tenantAndCondition($this->db->table('tasks'))
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
            "SELECT COUNT(*) FROM `%s` WHERE task_project = %d%s",
            $this->db->table('tasks'),
            $projectId,
            $this->tenantAndCondition($this->db->table('tasks'))
        );
        $stats['total_tasks'] = (int) ($this->db->fetchValue($sql) ?? 0);

        // Completed tasks (100%)
        $sql = sprintf(
            "SELECT COUNT(*) FROM `%s` WHERE task_project = %d AND task_percent_complete = 100%s",
            $this->db->table('tasks'),
            $projectId,
            $this->tenantAndCondition($this->db->table('tasks'))
        );
        $stats['completed_tasks'] = (int) ($this->db->fetchValue($sql) ?? 0);

        // Overdue tasks
        $sql = sprintf(
            "SELECT COUNT(*) FROM `%s` WHERE task_project = %d
             AND task_percent_complete < 100
             AND task_end_date < NOW()
             AND task_end_date != '0000-00-00 00:00:00'%s",
            $this->db->table('tasks'),
            $projectId,
            $this->tenantAndCondition($this->db->table('tasks'))
        );
        $stats['overdue_tasks'] = (int) ($this->db->fetchValue($sql) ?? 0);

        // Total hours worked
        $sql = sprintf(
            "SELECT SUM(task_hours_worked) FROM `%s` WHERE task_project = %d%s",
            $this->db->table('tasks'),
            $projectId,
            $this->tenantAndCondition($this->db->table('tasks'))
        );
        $stats['total_hours_worked'] = (float) ($this->db->fetchValue($sql) ?? 0);

        // Milestones
        $sql = sprintf(
            "SELECT COUNT(*) FROM `%s` WHERE task_project = %d AND task_milestone = 1%s",
            $this->db->table('tasks'),
            $projectId,
            $this->tenantAndCondition($this->db->table('tasks'))
        );
        $stats['milestones'] = (int) ($this->db->fetchValue($sql) ?? 0);

        // Progress from project
        $projectExists = $this->db->fetchValue(sprintf(
            "SELECT project_id FROM `%s` WHERE project_id = %d%s",
            $this->db->table('projects'),
            $projectId,
            $this->tenantAndCondition($this->db->table('projects'))
        ));
        if ($projectExists !== null) {
            $project = Project::find($projectId);
            if ($project !== null) {
                $stats['progress'] = (float) $project->getPercentComplete();
            }
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
            "SELECT * FROM `%s` WHERE 1=1%s ORDER BY project_id DESC LIMIT %d",
            $this->db->table('projects'),
            $this->tenantAndCondition($this->db->table('projects')),
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
                "(project_name LIKE '%%%s%%' OR project_short_name LIKE '%%%s%%')%s",
                $escapedQuery,
                $escapedQuery,
                $this->tenantAndCondition($this->db->table('projects'))
            ),
            'project_name ASC'
        );
    }

    /**
     * Validate project payload for legacy callers/tests.
     *
     * @param array<string, mixed> $data
     * @return array<string, string>
     */
    public function validate(array $data): array
    {
        $errors = [];

        if (empty(trim((string) ($data['project_name'] ?? '')))) {
            $errors['project_name'] = 'Project name is required';
        }

        if (!empty($data['project_start_date']) && strtotime((string) $data['project_start_date']) === false) {
            $errors['project_start_date'] = 'Invalid start date';
        }

        if (!empty($data['project_end_date']) && strtotime((string) $data['project_end_date']) === false) {
            $errors['project_end_date'] = 'Invalid end date';
        }

        return $errors;
    }

    /**
     * Calculate average progress from task rows.
     *
     * @param array<int, array<string, mixed>> $tasks
     */
    public function calculateProgress(array $tasks): float
    {
        if (empty($tasks)) {
            return 0.0;
        }

        $total = 0.0;
        $count = 0;

        foreach ($tasks as $task) {
            $percent = (float) ($task['task_percent_complete'] ?? 0);
            $total += max(0.0, min(100.0, $percent));
            $count++;
        }

        if ($count === 0) {
            return 0.0;
        }

        return round($total / $count, 2);
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
            "SELECT COUNT(*)
             FROM information_schema.columns
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
}
