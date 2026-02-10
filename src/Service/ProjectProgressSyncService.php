<?php
/**
 * DotProject Project Progress Sync Service
 *
 * Sincroniza progresso e status macro de projetos com base nas tarefas.
 *
 * @package DotProject\Service
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Service;

use DotProject\Core\Cache;
use DotProject\Core\Database;
use DotProject\Core\Logger;

/**
 * Service para manter project_percent_complete coerente com tarefas.
 */
class ProjectProgressSyncService
{
    private Database $db;
    private Cache $cache;
    private ?ProjectStatusHistoryService $statusHistoryService = null;

    public function __construct(?Database $db = null, ?Cache $cache = null)
    {
        $this->db = $db ?? Database::getInstance();
        $this->cache = $cache ?? new Cache();
    }

    public function syncByTaskId(int $taskId, ?int $changedByUserId = null, string $source = 'tasks_progress_sync'): void
    {
        if ($taskId <= 0) {
            return;
        }

        $projectId = (int) ($this->db->fetchValue(
            sprintf(
                "SELECT task_project
                 FROM `%s`
                 WHERE task_id = ?
                 LIMIT 1",
                $this->db->table('tasks')
            ),
            [$taskId]
        ) ?? 0);

        if ($projectId <= 0) {
            return;
        }

        $this->syncProject($projectId, $changedByUserId, $source);
    }

    public function syncProject(int $projectId, ?int $changedByUserId = null, string $source = 'tasks_progress_sync'): void
    {
        if ($projectId <= 0) {
            return;
        }

        $project = $this->db->fetchOne(
            sprintf(
                "SELECT project_status, project_percent_complete
                 FROM `%s`
                 WHERE project_id = ?
                 LIMIT 1",
                $this->db->table('projects')
            ),
            [$projectId]
        );

        if (!$project) {
            return;
        }

        $taskStats = $this->db->fetchOne(
            sprintf(
                "SELECT
                    COUNT(*) AS total_tasks,
                    SUM(
                        CASE
                            WHEN COALESCE(task_status, 0) IN (1, 2, 3, 5, 6, 7) OR COALESCE(task_percent_complete, 0) > 0
                                THEN 1
                            ELSE 0
                        END
                    ) AS started_tasks,
                    SUM(
                        CASE
                            WHEN COALESCE(task_status, 0) IN (3, 5, 6) OR COALESCE(task_percent_complete, 0) >= 100
                                THEN 1
                            ELSE 0
                        END
                    ) AS completed_tasks,
                    ROUND(
                        AVG(
                            CASE
                                WHEN COALESCE(task_status, 0) IN (3, 5, 6) THEN 100
                                WHEN COALESCE(task_percent_complete, 0) < 0 THEN 0
                                WHEN COALESCE(task_percent_complete, 0) > 100 THEN 100
                                ELSE COALESCE(task_percent_complete, 0)
                            END
                        ),
                        0
                    ) AS avg_percent
                 FROM `%s`
                 WHERE task_project = ?
                   AND COALESCE(task_status, 0) <> -1",
                $this->db->table('tasks')
            ),
            [$projectId]
        );

        if (!$taskStats) {
            return;
        }

        $currentStatus = (int) ($project['project_status'] ?? 0);
        $currentPercent = (int) ($project['project_percent_complete'] ?? 0);
        $totalTasks = (int) ($taskStats['total_tasks'] ?? 0);
        $startedTasks = (int) ($taskStats['started_tasks'] ?? 0);
        $completedTasks = (int) ($taskStats['completed_tasks'] ?? 0);
        $percent = $totalTasks > 0 ? (int) ($taskStats['avg_percent'] ?? 0) : 0;
        $percent = max(0, min(100, $percent));

        if ($currentStatus === 6) {
            // Arquivado: mantem percentual manual.
            $percent = max(0, min(100, $currentPercent));
        } elseif ($currentStatus === 5 && $totalTasks === 0) {
            // Completo sem tarefas: preserva coerencia visual.
            $percent = 100;
        }

        $nextStatus = $this->resolveNextStatus($currentStatus, $totalTasks, $startedTasks, $completedTasks);
        $updateData = ['project_percent_complete' => $percent];

        if ($nextStatus !== $currentStatus) {
            $updateData['project_status'] = $nextStatus;
        }

        $updated = $this->db->update(
            'projects',
            $updateData,
            sprintf('project_id = %d', $projectId)
        );

        if (!$updated) {
            Logger::warning('Failed to sync project progress from tasks', [
                'project_id' => $projectId,
                'update_data' => $updateData,
            ]);
            return;
        }

        if ($nextStatus !== $currentStatus) {
            $this->statusHistoryService()->recordStatusChange(
                $projectId,
                $currentStatus,
                $nextStatus,
                $changedByUserId,
                $source
            );
        }

        $this->invalidateCaches();
    }

    private function resolveNextStatus(
        int $currentStatus,
        int $totalTasks,
        int $startedTasks,
        int $completedTasks
    ): int {
        // Arquivado segue manual.
        if ($currentStatus === 6) {
            return 6;
        }

        // Sem tarefas: preserva status existente (exceto ajuste visual de completo no percentual acima).
        if ($totalTasks <= 0) {
            return $currentStatus;
        }

        // Todas as tarefas terminais implicam projeto completo.
        if ($completedTasks >= $totalTasks) {
            return 5;
        }

        // Completo reabre se surgirem tarefas nao concluidas.
        if ($currentStatus === 5) {
            return 3;
        }

        // Projeto entra em progresso quando a execucao das tarefas comeca.
        if (in_array($currentStatus, [0, 1, 2], true) && $startedTasks > 0) {
            return 3;
        }

        return $currentStatus;
    }

    private function invalidateCaches(): void
    {
        $this->cache->invalidate('*DashboardController*dashboard*');
        $this->cache->invalidate('dashboard:*');
        $this->cache->invalidate(
            sprintf(
                '%s:%s:*',
                str_replace('\\', '.', \DotProject\Repository\ProjectRepository::class),
                $this->db->table('projects')
            )
        );

        $analyticsCache = new Cache(prefix: 'analytics:');
        $analyticsCache->invalidate('dashboard:*');
        $analyticsCache->invalidate('productivity:*');
        $analyticsCache->invalidate('completion-trend:*');

        $projectServiceCache = new Cache(prefix: 'project_service:');
        $projectServiceCache->invalidate('stats:*');
        $projectServiceCache->invalidate('dashboard:*');
    }

    private function statusHistoryService(): ProjectStatusHistoryService
    {
        if ($this->statusHistoryService === null) {
            $this->statusHistoryService = new ProjectStatusHistoryService($this->db);
        }

        return $this->statusHistoryService;
    }
}
