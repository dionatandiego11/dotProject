<?php
/**
 * Analytics Service
 * 
 * Serviço para fornecer métricas e estatísticas do sistema.
 * 
 * @package DotProject\Service
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Service;

use DotProject\Core\Database;
use DotProject\Core\Cache;

/**
 * Serviço de Analytics
 */
class AnalyticsService
{
    private Database $db;
    private Cache $cache;
    private AuthorizationService $auth;
    
    public function __construct(
        ?Database $db = null,
        ?Cache $cache = null,
        ?AuthorizationService $auth = null
    ) {
        $this->db = $db ?? Database::getInstance();
        $this->cache = $cache ?? new Cache(prefix: 'analytics:');
        $this->auth = $auth ?? AuthorizationService::getInstance();
    }
    
    /**
     * Obtém resumo do dashboard
     */
    public function getDashboardSummary(?int $userId = null): array
    {
        $cacheKey = "dashboard:{$userId}";
        $cached = $this->cache->get($cacheKey);
        
        if ($cached !== null) {
            return $cached;
        }
        
        $summary = [
            'projects' => $this->getProjectsStats($userId),
            'tasks' => $this->getTasksStats($userId),
            'deadlines' => $this->getDeadlinesStats($userId),
            'activity' => $this->getRecentActivity($userId),
        ];
        
        $this->cache->set($cacheKey, $summary, 300); // Cache 5 minutos
        
        return $summary;
    }
    
    /**
     * Estatísticas de projetos
     */
    private function getProjectsStats(?int $userId = null): array
    {
        // Total de projetos
        $sql = "SELECT COUNT(*) FROM `dotp_projects` WHERE project_status != -1";
        $total = (int) $this->db->fetchValue($sql);
        
        // Por status
        $sql = "SELECT 
            SUM(CASE WHEN project_status = 0 THEN 1 ELSE 0 END) as not_defined,
            SUM(CASE WHEN project_status = 1 THEN 1 ELSE 0 END) as proposed,
            SUM(CASE WHEN project_status = 2 THEN 1 ELSE 0 END) as planning,
            SUM(CASE WHEN project_status = 3 THEN 1 ELSE 0 END) as in_progress,
            SUM(CASE WHEN project_status = 4 THEN 1 ELSE 0 END) as on_hold,
            SUM(CASE WHEN project_status = 5 THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN project_status = 6 THEN 1 ELSE 0 END) as archived
        FROM `dotp_projects` 
        WHERE project_status != -1";
        
        $byStatus = $this->db->fetchOne($sql) ?? [
            'not_defined' => 0,
            'proposed' => 0,
            'planning' => 0,
            'in_progress' => 0,
            'on_hold' => 0,
            'completed' => 0,
            'archived' => 0
        ];
        
        // Progresso médio
        $sql = "SELECT AVG(project_percent_complete) FROM `dotp_projects` WHERE project_status != -1";
        $avgProgress = (float) ($this->db->fetchValue($sql) ?? 0);
        
        return [
            'total' => $total,
            'by_status' => [
                ['label' => 'Em Progresso', 'value' => (int) $byStatus['in_progress'], 'color' => '#3b82f6'],
                ['label' => 'Concluídos', 'value' => (int) $byStatus['completed'], 'color' => '#22c55e'],
                ['label' => 'Planejamento', 'value' => (int) $byStatus['planning'], 'color' => '#f59e0b'],
                ['label' => 'Em Espera', 'value' => (int) $byStatus['on_hold'], 'color' => '#6b7280'],
                ['label' => 'Propostos', 'value' => (int) $byStatus['proposed'], 'color' => '#8b5cf6'],
            ],
            'avg_progress' => round($avgProgress, 1)
        ];
    }
    
    /**
     * Estatísticas de tarefas
     */
    private function getTasksStats(?int $userId = null): array
    {
        // Total
        $sql = "SELECT COUNT(*) FROM `dotp_tasks` WHERE task_status != -1";
        $total = (int) $this->db->fetchValue($sql);
        
        // Por status
        $sql = "SELECT 
            SUM(CASE WHEN task_percent_complete = 100 THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN task_percent_complete < 100 AND task_percent_complete > 0 THEN 1 ELSE 0 END) as in_progress,
            SUM(CASE WHEN task_percent_complete = 0 THEN 1 ELSE 0 END) as not_started
        FROM `dotp_tasks` 
        WHERE task_status != -1";
        
        $byStatus = $this->db->fetchOne($sql) ?? [
            'completed' => 0,
            'in_progress' => 0,
            'not_started' => 0
        ];
        
        // Atrasadas
        $sql = "SELECT COUNT(*) FROM `dotp_tasks` 
        WHERE task_status != -1 
        AND task_percent_complete < 100
        AND task_end_date IS NOT NULL 
        AND task_end_date < CURDATE()";
        $overdue = (int) $this->db->fetchValue($sql);
        
        // Por prioridade
        $sql = "SELECT 
            SUM(CASE WHEN task_priority = 3 THEN 1 ELSE 0 END) as urgent,
            SUM(CASE WHEN task_priority = 2 THEN 1 ELSE 0 END) as high,
            SUM(CASE WHEN task_priority = 1 THEN 1 ELSE 0 END) as normal,
            SUM(CASE WHEN task_priority = 0 THEN 1 ELSE 0 END) as low
        FROM `dotp_tasks` 
        WHERE task_status != -1 AND task_percent_complete < 100";
        
        $byPriority = $this->db->fetchOne($sql) ?? [
            'urgent' => 0,
            'high' => 0,
            'normal' => 0,
            'low' => 0
        ];
        
        return [
            'total' => $total,
            'completed' => (int) $byStatus['completed'],
            'in_progress' => (int) $byStatus['in_progress'],
            'not_started' => (int) $byStatus['not_started'],
            'overdue' => $overdue,
            'by_priority' => [
                ['label' => 'Urgente', 'value' => (int) $byPriority['urgent'], 'color' => '#ef4444'],
                ['label' => 'Alta', 'value' => (int) $byPriority['high'], 'color' => '#f97316'],
                ['label' => 'Normal', 'value' => (int) $byPriority['normal'], 'color' => '#3b82f6'],
                ['label' => 'Baixa', 'value' => (int) $byPriority['low'], 'color' => '#6b7280'],
            ]
        ];
    }
    
    /**
     * Estatísticas de prazos
     */
    private function getDeadlinesStats(?int $userId = null): array
    {
        // Esta semana
        $sql = "SELECT COUNT(*) FROM `dotp_tasks` 
        WHERE task_status != -1 
        AND task_percent_complete < 100
        AND task_end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
        $thisWeek = (int) $this->db->fetchValue($sql);
        
        // Próxima semana
        $sql = "SELECT COUNT(*) FROM `dotp_tasks` 
        WHERE task_status != -1 
        AND task_percent_complete < 100
        AND task_end_date BETWEEN DATE_ADD(CURDATE(), INTERVAL 8 DAY) AND DATE_ADD(CURDATE(), INTERVAL 14 DAY)";
        $nextWeek = (int) $this->db->fetchValue($sql);
        
        // Este mês
        $sql = "SELECT COUNT(*) FROM `dotp_tasks` 
        WHERE task_status != -1 
        AND task_percent_complete < 100
        AND task_end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
        $thisMonth = (int) $this->db->fetchValue($sql);
        
        // Sem data
        $sql = "SELECT COUNT(*) FROM `dotp_tasks` 
        WHERE task_status != -1 
        AND task_percent_complete < 100
        AND task_end_date IS NULL";
        $noDate = (int) $this->db->fetchValue($sql);
        
        return [
            'this_week' => $thisWeek,
            'next_week' => $nextWeek,
            'this_month' => $thisMonth,
            'no_date' => $noDate,
            'timeline' => [
                ['label' => 'Semana', 'value' => $thisWeek],
                ['label' => 'Próxima', 'value' => $nextWeek],
                ['label' => 'Mês', 'value' => $thisMonth],
            ]
        ];
    }
    
    /**
     * Atividade recente
     */
    private function getRecentActivity(?int $userId = null): array
    {
        // Tarefas criadas recentemente
        $sql = "SELECT 
            t.task_id as id,
            t.task_name as name,
            p.project_name as project,
            u.user_username as created_by,
            t.task_created as created_at,
            'task_created' as type
        FROM `dotp_tasks` t
        LEFT JOIN `dotp_projects` p ON p.project_id = t.task_project
        LEFT JOIN `dotp_users` u ON u.user_id = t.task_owner
        WHERE t.task_status != -1
        ORDER BY t.task_created DESC
        LIMIT 5";
        
        $recentTasks = $this->db->fetchAll($sql);
        
        // Projetos atualizados
        $sql = "SELECT 
            project_id as id,
            project_name as name,
            project_updated as updated_at,
            'project_updated' as type
        FROM `dotp_projects`
        WHERE project_status != -1
        ORDER BY project_updated DESC
        LIMIT 3";
        
        $recentProjects = $this->db->fetchAll($sql);
        
        // Combinar e ordenar
        $activity = [];
        
        foreach ($recentTasks as $task) {
            $activity[] = [
                'type' => 'task',
                'action' => 'created',
                'title' => $task['name'],
                'subtitle' => $task['project'] ? "Projeto: {$task['project']}" : '',
                'user' => $task['created_by'],
                'date' => $task['created_at'],
                'icon' => '✓'
            ];
        }
        
        return array_slice($activity, 0, 5);
    }
    
    /**
     * Dados para gráfico de produtividade (últimos 7 dias)
     */
    public function getProductivityTrend(int $days = 7): array
    {
        $cacheKey = "productivity:{$days}";
        $cached = $this->cache->get($cacheKey);
        
        if ($cached !== null) {
            return $cached;
        }
        
        $data = [];
        
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            
            // Tarefas completadas neste dia
            $sql = "SELECT COUNT(*) FROM `dotp_task_log` 
            WHERE task_log_date = '{$date}' 
            AND task_log_description LIKE '%completed%'";
            $completed = (int) $this->db->fetchValue($sql);
            
            // Se não tem log, contar do histórico
            if ($completed === 0) {
                $sql = "SELECT COUNT(*) FROM `dotp_tasks` 
                WHERE task_percent_complete = 100
                AND DATE(task_updated) = '{$date}'";
                $completed = (int) $this->db->fetchValue($sql);
            }
            
            $data[] = [
                'date' => $date,
                'label' => date('d/m', strtotime($date)),
                'value' => $completed
            ];
        }
        
        $this->cache->set($cacheKey, $data, 600);
        
        return $data;
    }
}
