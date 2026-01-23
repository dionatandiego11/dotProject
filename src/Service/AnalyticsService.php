<?php
/**
 * DotProject Analytics Service
 * 
 * Service for generating analytics, KPIs and dashboard data.
 * 
 * @package DotProject\Service
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Service;

use DotProject\Core\Database;

/**
 * Analytics Service
 * 
 * Provides analytics data for dashboards including KPIs,
 * trends, and performance metrics.
 */
class AnalyticsService
{
    private Database $db;

    private static ?AnalyticsService $instance = null;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get dashboard summary with key KPIs
     * 
     * @return array<string, mixed>
     */
    public function getDashboardSummary(?int $userId = null): array
    {
        $userWhere = $userId ? sprintf(' AND project_owner = %d', $userId) : '';
        $taskUserWhere = $userId ? sprintf(' AND task_owner = %d', $userId) : '';

        // Active projects count
        $activeProjects = (int) $this->db->fetchValue(sprintf(
            "SELECT COUNT(*) FROM %s WHERE project_status = 3 %s",
            $this->db->table('projects'),
            $userWhere
        ));

        // Projects by status
        $projectsByStatus = $this->db->fetchAll(sprintf(
            "SELECT project_status, COUNT(*) as count 
             FROM %s 
             WHERE 1=1 %s
             GROUP BY project_status",
            $this->db->table('projects'),
            $userWhere
        ));

        // Tasks summary
        $tasksSummary = $this->db->fetchOne(sprintf(
            "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN task_percent_complete = 100 THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN task_percent_complete < 100 AND task_end_date < CURDATE() THEN 1 ELSE 0 END) as overdue,
                SUM(CASE WHEN task_percent_complete < 100 AND task_end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as due_soon
             FROM %s
             WHERE 1=1 %s",
            $this->db->table('tasks'),
            $taskUserWhere
        ));

        // Hours this week
        $hoursThisWeek = (float) $this->db->fetchValue(sprintf(
            "SELECT SUM(task_hours_worked) 
             FROM %s 
             WHERE task_updated >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) %s",
            $this->db->table('tasks'),
            $taskUserWhere
        )) ?? 0;

        return [
            'projects' => [
                'active' => $activeProjects,
                'by_status' => $this->formatStatusCounts($projectsByStatus),
            ],
            'tasks' => [
                'total' => (int) ($tasksSummary['total'] ?? 0),
                'completed' => (int) ($tasksSummary['completed'] ?? 0),
                'overdue' => (int) ($tasksSummary['overdue'] ?? 0),
                'due_soon' => (int) ($tasksSummary['due_soon'] ?? 0),
                'completion_rate' => $this->calculateCompletionRate($tasksSummary),
            ],
            'hours' => [
                'this_week' => $hoursThisWeek,
            ],
        ];
    }

    /**
     * Get project health overview
     * 
     * @return array<string, mixed>
     */
    public function getProjectsHealth(): array
    {
        $sql = sprintf(
            "SELECT 
                p.project_id,
                p.project_name,
                p.project_percent_complete,
                p.project_status,
                p.project_end_date,
                COUNT(t.task_id) as total_tasks,
                SUM(CASE WHEN t.task_percent_complete = 100 THEN 1 ELSE 0 END) as completed_tasks,
                SUM(CASE WHEN t.task_percent_complete < 100 AND t.task_end_date < CURDATE() THEN 1 ELSE 0 END) as overdue_tasks
             FROM %s p
             LEFT JOIN %s t ON p.project_id = t.task_project
             WHERE p.project_status = 3
             GROUP BY p.project_id, p.project_name, p.project_percent_complete, p.project_status, p.project_end_date
             ORDER BY overdue_tasks DESC, p.project_name ASC
             LIMIT 20",
            $this->db->table('projects'),
            $this->db->table('tasks')
        );

        $projects = $this->db->fetchAll($sql);

        return array_map(function ($p) {
            $health = $this->calculateHealth(
                (int) $p['overdue_tasks'],
                (int) $p['total_tasks'],
                (int) $p['project_percent_complete']
            );

            return [
                'id' => (int) $p['project_id'],
                'name' => $p['project_name'],
                'progress' => (int) $p['project_percent_complete'],
                'total_tasks' => (int) $p['total_tasks'],
                'completed_tasks' => (int) $p['completed_tasks'],
                'overdue_tasks' => (int) $p['overdue_tasks'],
                'health' => $health,
                'end_date' => $p['project_end_date'],
            ];
        }, $projects);
    }

    /**
     * Get task completion trend (last N days)
     * 
     * @return array<int, array<string, mixed>>
     */
    public function getCompletionTrend(int $days = 30): array
    {
        $sql = sprintf(
            "SELECT 
                DATE(task_updated) as date,
                COUNT(*) as completed
             FROM %s
             WHERE task_percent_complete = 100
               AND task_updated >= DATE_SUB(CURDATE(), INTERVAL %d DAY)
             GROUP BY DATE(task_updated)
             ORDER BY date ASC",
            $this->db->table('tasks'),
            $days
        );

        $results = $this->db->fetchAll($sql);

        // Fill in missing dates with zeros
        $trend = [];
        $start = new \DateTime("-{$days} days");
        $end = new \DateTime();

        $dataByDate = [];
        foreach ($results as $row) {
            $dataByDate[$row['date']] = (int) $row['completed'];
        }

        while ($start <= $end) {
            $dateStr = $start->format('Y-m-d');
            $trend[] = [
                'date' => $dateStr,
                'completed' => $dataByDate[$dateStr] ?? 0,
            ];
            $start->modify('+1 day');
        }

        return $trend;
    }

    /**
     * Get team performance metrics
     * 
     * @return array<int, array<string, mixed>>
     */
    public function getTeamPerformance(): array
    {
        $sql = sprintf(
            "SELECT 
                u.user_id,
                CONCAT(u.user_first_name, ' ', u.user_last_name) as name,
                COUNT(DISTINCT t.task_id) as total_tasks,
                SUM(CASE WHEN t.task_percent_complete = 100 THEN 1 ELSE 0 END) as completed_tasks,
                SUM(CASE WHEN t.task_percent_complete < 100 AND t.task_end_date < CURDATE() THEN 1 ELSE 0 END) as overdue_tasks,
                SUM(t.task_hours_worked) as hours_worked
             FROM %s u
             LEFT JOIN %s t ON u.user_id = t.task_owner
             WHERE u.user_id > 0
             GROUP BY u.user_id, u.user_first_name, u.user_last_name
             HAVING total_tasks > 0
             ORDER BY completed_tasks DESC
             LIMIT 20",
            $this->db->table('users'),
            $this->db->table('tasks')
        );

        $users = $this->db->fetchAll($sql);

        return array_map(function ($u) {
            $total = (int) $u['total_tasks'];
            $completed = (int) $u['completed_tasks'];

            return [
                'user_id' => (int) $u['user_id'],
                'name' => $u['name'],
                'total_tasks' => $total,
                'completed_tasks' => $completed,
                'overdue_tasks' => (int) $u['overdue_tasks'],
                'hours_worked' => (float) $u['hours_worked'],
                'completion_rate' => $total > 0 ? round(($completed / $total) * 100, 1) : 0,
            ];
        }, $users);
    }

    /**
     * Get burndown data for a project
     * 
     * @return array<string, mixed>
     */
    public function getProjectBurndown(int $projectId): array
    {
        // Get project dates
        $project = $this->db->fetchOne(sprintf(
            "SELECT project_start_date, project_end_date FROM %s WHERE project_id = %d",
            $this->db->table('projects'),
            $projectId
        ));

        if (!$project) {
            return [];
        }

        // Get total tasks/effort
        $totalTasks = (int) $this->db->fetchValue(sprintf(
            "SELECT COUNT(*) FROM %s WHERE task_project = %d",
            $this->db->table('tasks'),
            $projectId
        ));

        // Get completion over time
        $sql = sprintf(
            "SELECT 
                DATE(task_updated) as date,
                COUNT(*) as completed
             FROM %s
             WHERE task_project = %d
               AND task_percent_complete = 100
             GROUP BY DATE(task_updated)
             ORDER BY date ASC",
            $this->db->table('tasks'),
            $projectId
        );

        $completions = $this->db->fetchAll($sql);

        // Build burndown
        $burndown = [];
        $remaining = $totalTasks;

        foreach ($completions as $row) {
            $remaining -= (int) $row['completed'];
            $burndown[] = [
                'date' => $row['date'],
                'remaining' => $remaining,
                'completed' => (int) $row['completed'],
            ];
        }

        return [
            'project_id' => $projectId,
            'start_date' => $project['project_start_date'],
            'end_date' => $project['project_end_date'],
            'total_tasks' => $totalTasks,
            'burndown' => $burndown,
        ];
    }

    /**
     * Get velocity data (tasks completed per week)
     * 
     * @return array<int, array<string, mixed>>
     */
    public function getVelocity(int $weeks = 8): array
    {
        $sql = sprintf(
            "SELECT 
                YEARWEEK(task_updated) as week,
                MIN(DATE(task_updated)) as week_start,
                COUNT(*) as completed
             FROM %s
             WHERE task_percent_complete = 100
               AND task_updated >= DATE_SUB(CURDATE(), INTERVAL %d WEEK)
             GROUP BY YEARWEEK(task_updated)
             ORDER BY week ASC",
            $this->db->table('tasks'),
            $weeks
        );

        $results = $this->db->fetchAll($sql);

        return array_map(function ($row) {
            return [
                'week_start' => $row['week_start'],
                'completed' => (int) $row['completed'],
            ];
        }, $results);
    }

    /**
     * Calculate completion rate
     */
    private function calculateCompletionRate(?array $tasksSummary): float
    {
        $total = (int) ($tasksSummary['total'] ?? 0);
        $completed = (int) ($tasksSummary['completed'] ?? 0);

        if ($total === 0) {
            return 0.0;
        }

        return round(($completed / $total) * 100, 1);
    }

    /**
     * Calculate project health status
     */
    private function calculateHealth(int $overdue, int $total, int $progress): string
    {
        if ($total === 0) {
            return 'unknown';
        }

        $overdueRate = $overdue / $total;

        if ($overdueRate > 0.3) {
            return 'critical';
        }

        if ($overdueRate > 0.1) {
            return 'at_risk';
        }

        return 'healthy';
    }

    /**
     * Format status counts with labels
     */
    private function formatStatusCounts(array $statusCounts): array
    {
        $labels = [
            0 => 'Not Defined',
            1 => 'Proposed',
            2 => 'In Planning',
            3 => 'In Progress',
            4 => 'On Hold',
            5 => 'Complete',
            6 => 'Archived',
        ];

        $formatted = [];
        foreach ($statusCounts as $row) {
            $status = (int) $row['project_status'];
            $formatted[$labels[$status] ?? 'Unknown'] = (int) $row['count'];
        }

        return $formatted;
    }
}
