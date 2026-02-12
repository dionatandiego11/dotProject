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
use DotProject\Entity\ProjectEntity;
use DotProject\Repository\ProjectRepository;

/**
 * Project Service
 * 
 * Handles project-related business operations.
 */
class ProjectService
{
    private Database $db;
    private ProjectRepository $projectRepository;
    /** @var array<string, bool> */
    private array $columnPresenceCache = [];

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
        $this->projectRepository = new ProjectRepository($this->db);
    }

    /**
     * Get active projects with optional filtering
     * 
     * @param int|null $companyId Filter by company
     * @param int|null $userId Filter by owner
     * @return array<int, ProjectEntity>
     */
    public function getActiveProjects(?int $companyId = null, ?int $userId = null): array
    {
        $criteria = ['project_status' => 3];

        if ($companyId !== null) {
            $criteria['project_company'] = $companyId;
        }

        if ($userId !== null) {
            $criteria['project_owner'] = $userId;
        }

        return $this->projectRepository->findBy($criteria, ['project_name' => 'ASC']);
    }

    /**
     * Get projects by status
     * 
     * @param int $status Project status (0=Not Defined, 1=Proposed, 2=In Planning, 3=In Progress, 4=On Hold, 5=Complete, 6=Archived)
     * @return array<int, ProjectEntity>
     */
    public function getProjectsByStatus(int $status): array
    {
        return $this->projectRepository->findBy(
            ['project_status' => $status],
            ['project_name' => 'ASC']
        );
    }

    /**
     * Create a new project
     * 
     * @param array<string, mixed> $data Project data
     * @return ProjectEntity|null Created project or null on failure
     */
    public function createProject(array $data): ?ProjectEntity
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
        $project = new ProjectEntity();
        $project->setName((string) $data['project_name']);
        $project->setShortName(isset($data['project_short_name']) ? (string) $data['project_short_name'] : null);
        $project->setDescription(isset($data['project_description']) ? (string) $data['project_description'] : null);
        $project->setStatus((int) $data['project_status']);
        $project->setPriority((int) $data['project_priority']);
        $project->setPercentComplete((int) $data['project_percent_complete']);
        $project->setOwnerId(isset($data['project_owner']) ? (int) $data['project_owner'] : null);
        $project->setCompanyId(isset($data['project_company']) ? (int) $data['project_company'] : null);
        $project->setColorIdentifier((string) $data['project_color_identifier']);
        $project->setUrl(isset($data['project_url']) ? (string) $data['project_url'] : null);

        if (!empty($data['project_start_date'])) {
            $project->setStartDate(new \DateTime((string) $data['project_start_date']));
        }
        if (!empty($data['project_end_date'])) {
            $project->setEndDate(new \DateTime((string) $data['project_end_date']));
        }

        $savedId = $this->projectRepository->save($project);
        if ($savedId <= 0) {
            return null;
        }

        return $this->projectRepository->find($savedId);
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

        $project = $this->projectRepository->find($projectId);
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

        $this->projectRepository->updatePercentComplete($projectId, (int) round($progress));

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
            $project = $this->projectRepository->find($projectId);
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
     * @return array<int, ProjectEntity>
     */
    public function getRecentProjects(int $limit = 10): array
    {
        return $this->projectRepository->findBy([], ['project_id' => 'DESC'], $limit);
    }

    /**
     * Search projects by name
     * 
     * @param string $query Search query
     * @return array<int, ProjectEntity>
     */
    public function searchProjects(string $query): array
    {
        return $this->projectRepository->searchByName($query);
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
     * @return array{total: int, terminal: int, active: int}
     */
    public function projectTaskDeletionStats(int $projectId): array
    {
        $tasksTable = $this->db->table('tasks');
        $row = $this->db->fetchOne(
            sprintf(
                "SELECT
                    COUNT(*) AS total,
                    SUM(CASE WHEN task_status IN (3, 5, 6) THEN 1 ELSE 0 END) AS terminal
                 FROM %s
                 WHERE task_project = ?%s",
                $tasksTable,
                $this->tenantAndCondition($tasksTable)
            ),
            [$projectId]
        );

        $total = (int) ($row['total'] ?? 0);
        $terminal = (int) ($row['terminal'] ?? 0);
        $active = max(0, $total - $terminal);

        return [
            'total' => $total,
            'terminal' => $terminal,
            'active' => $active,
        ];
    }

    public function purgeProjectTasks(int $projectId): bool
    {
        $tasksTable = $this->db->table('tasks');
        $taskIdsSql = sprintf(
            'SELECT task_id FROM %s WHERE task_project = ?%s',
            $tasksTable,
            $this->tenantAndCondition($tasksTable)
        );

        $cleanupSteps = [
            [
                'table' => 'task_log',
                'sql' => sprintf('DELETE FROM %s WHERE task_log_task IN (%s)', $this->db->table('task_log'), $taskIdsSql),
                'params' => [$projectId],
            ],
            [
                'table' => 'task_contacts',
                'sql' => sprintf('DELETE FROM %s WHERE task_id IN (%s)', $this->db->table('task_contacts'), $taskIdsSql),
                'params' => [$projectId],
            ],
            [
                'table' => 'task_departments',
                'sql' => sprintf('DELETE FROM %s WHERE task_id IN (%s)', $this->db->table('task_departments'), $taskIdsSql),
                'params' => [$projectId],
            ],
            [
                'table' => 'user_tasks',
                'sql' => sprintf('DELETE FROM %s WHERE task_id IN (%s)', $this->db->table('user_tasks'), $taskIdsSql),
                'params' => [$projectId],
            ],
            [
                'table' => 'task_dependencies',
                'sql' => sprintf(
                    'DELETE FROM %s WHERE dependencies_task_id IN (%s) OR dependencies_req_task_id IN (%s)',
                    $this->db->table('task_dependencies'),
                    $taskIdsSql,
                    $taskIdsSql
                ),
                'params' => [$projectId, $projectId],
            ],
        ];

        foreach ($cleanupSteps as $step) {
            if (!$this->tableExists($step['table'])) {
                continue;
            }

            $result = $this->db->execute($step['sql'], $step['params']);
            if ($result === false) {
                return false;
            }
        }

        $deleted = $this->db->execute(
            sprintf(
                'DELETE FROM %s WHERE task_project = ?%s',
                $tasksTable,
                $this->tenantAndCondition($tasksTable)
            ),
            [$projectId]
        );

        return $deleted !== false;
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

    private function tableExists(string $table): bool
    {
        $exists = (int) ($this->db->fetchValue(
            "SELECT COUNT(*)
             FROM information_schema.tables
             WHERE table_schema = DATABASE()
               AND table_name = ?",
            [$this->db->table($table)]
        ) ?? 0);

        return $exists > 0;
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
