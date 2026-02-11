<?php
/**
 * Kanban Service
 * 
 * Servico de negocio para gerenciamento de quadros Kanban.
 * 
 * @package DotProject\Service
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Service;

use DotProject\Core\Cache;
use DotProject\Core\Database;
use DotProject\Core\Logger;
use DotProject\Entity\KanbanBoard;
use DotProject\Entity\KanbanColumn;
use DotProject\Entity\KanbanTask;
use DotProject\Repository\KanbanColumnRepository;
use DotProject\Repository\KanbanTaskRepository;
use DateTime;

/**
 * Servico Kanban
 */
class KanbanService
{
    private Database $db;
    private AuthorizationService $auth;
    private Cache $cache;
    private KanbanColumnRepository $columnRepo;
    private KanbanTaskRepository $taskRepo;
    private ProjectProgressSyncService $projectProgressSync;
    private bool $schemaChecked = false;
    private bool $schemaReady = false;
    /** @var array<string, bool> */
    private array $columnPresenceCache = [];

    public function __construct(
        ?Database $db = null,
        ?AuthorizationService $auth = null,
        ?Cache $cache = null
    ) {
        $this->db = $db ?? Database::getInstance();
        $this->auth = $auth ?? AuthorizationService::getInstance();
        $this->cache = $cache ?? new Cache(null, 'kanban:');
        $this->columnRepo = new KanbanColumnRepository($this->db, $this->cache);
        $this->taskRepo = new KanbanTaskRepository($this->db, $this->cache);
        $this->projectProgressSync = new ProjectProgressSyncService($this->db);
    }

    /**
     * Lista boards acessiveis ao usuario
     */
    public function getAccessibleBoards(int $companyId, ?int $projectId = null, ?int $userId = null): array
    {
        if (!$this->ensureSchema()) {
            return [];
        }
        $userId = $userId ?? $this->auth->getCurrentUser()?->getId();

        if ($userId === null) {
            return [];
        }

        if ($projectId !== null) {
            $sql = sprintf(
                "SELECT * FROM `dotp_kanban_boards` 
                 WHERE board_company = %d AND board_status = 0 
                 AND (board_project = %d OR board_project IS NULL)
                 ORDER BY board_project IS NULL, board_created_at DESC",
                $companyId,
                $projectId
            );
        } else {
            $sql = sprintf(
                "SELECT * FROM `dotp_kanban_boards` 
                 WHERE board_company = %d AND board_status = 0
                 ORDER BY board_created_at DESC",
                $companyId
            );
        }

        $results = $this->db->fetchAll($sql);
        $boards = [];

        foreach ($results as $data) {
            $boards[] = $this->hydrateBoard($data);
        }

        return $boards;
    }

    /**
     * Cria um novo board
     */
    public function createBoard(array $data, int $userId): KanbanBoard
    {
        if (!$this->ensureSchema()) {
            throw new \RuntimeException('Kanban schema not available');
        }
        $board = new KanbanBoard();
        $board->setName($data['name']);
        $board->setDescription($data['description'] ?? null);
        $board->setProjectId($data['project_id'] ?? null);
        $board->setCompanyId($data['company_id']);
        $board->setCreatedBy($userId);
        $board->setStatus(0);

        $insertData = [
            'board_name' => $board->getName(),
            'board_description' => $board->getDescription(),
            'board_project' => $board->getProjectId(),
            'board_company' => $board->getCompanyId(),
            'board_created_by' => $board->getCreatedBy(),
            'board_status' => $board->getStatus(),
        ];

        $result = $this->db->insert('dotp_kanban_boards', $insertData);

        if (!$result) {
            throw new \RuntimeException('Failed to create board');
        }

        $board->setId((int) $this->db->lastInsertId());

        // Cria colunas padrão
        $this->createDefaultColumns($board->getId());

        Logger::info('Kanban board created', [
            'board_id' => $board->getId(),
            'user_id' => $userId,
        ]);

        return $board;
    }

    /**
     * Obtém board completo
     */
    public function getBoard(int $boardId, ?int $userId = null): ?array
    {
        if (!$this->ensureSchema()) {
            return null;
        }
        $sql = sprintf("SELECT * FROM `dotp_kanban_boards` WHERE board_id = %d", $boardId);
        $data = $this->db->fetchOne($sql);

        if (!$data) {
            return null;
        }

        $board = $this->hydrateBoard($data);
        $boardArray = $board->toArray();

        if ($board->getProjectId() !== null) {
            $this->ensureProjectTasksInBoard($boardId, $board->getProjectId(), $userId);
        }

        $columns = $this->getColumns($boardId);
        $tasksByColumn = $this->getTasksByColumn($boardId);
        $columnsArray = [];
        $totalTasks = 0;

        foreach ($columns as $column) {
            $tasks = $tasksByColumn[$column->getId() ?? 0] ?? [];
            $taskCount = count($tasks);
            $totalTasks += $taskCount;

            $avgProgress = 0;
            if ($taskCount > 0) {
                $sum = 0;
                foreach ($tasks as $task) {
                    $sum += (int) ($task['task']['percent_complete'] ?? 0);
                }
                $avgProgress = round($sum / $taskCount, 1);
            }

            $columnsArray[] = [
                'id' => $column->getId(),
                'board_id' => $column->getBoardId(),
                'name' => $column->getName(),
                'color' => $column->getColor(),
                'order' => $column->getOrder(),
                'wip_limit' => $column->getWipLimit(),
                'is_at_wip_limit' => $column->getWipLimit() ? $taskCount >= $column->getWipLimit() : false,
                'remaining_wip_slots' => $column->getWipLimit() ? max(0, $column->getWipLimit() - $taskCount) : null,
                'status' => $column->getStatus(),
                'is_done' => $column->isDone(),
                'is_backlog' => $column->isBacklog(),
                'task_count' => $taskCount,
                'average_progress' => $avgProgress,
                'overdue_count' => $this->countOverdueTasks($tasks),
                'tasks' => $tasks,
            ];
        }

        $boardArray['total_tasks'] = $totalTasks;
        $boardArray['columns'] = $columnsArray;

        return [
            'board' => $boardArray,
            'columns' => $columnsArray,
        ];
    }

    /**
     * Cria colunas padrão para um board
     */
    private function createDefaultColumns(int $boardId): void
    {
        $defaults = [
            ['name' => 'Backlog', 'order' => 1, 'is_backlog' => 1],
            ['name' => 'To Do', 'order' => 2],
            ['name' => 'In Progress', 'order' => 3],
            ['name' => 'Done', 'order' => 4, 'is_done' => 1],
        ];

        foreach ($defaults as $col) {
            $this->db->insert('dotp_kanban_columns', [
                'column_board_id' => $boardId,
                'column_name' => $col['name'],
                'column_order' => $col['order'],
                'column_is_backlog' => $col['is_backlog'] ?? 0,
                'column_is_done' => $col['is_done'] ?? 0,
                'column_status' => 0,
            ]);
        }
    }

    /**
     * Obtém colunas de um board
     */
    public function getColumns(int $boardId): array
    {
        if (!$this->ensureSchema()) {
            return [];
        }
        return $this->columnRepo->findByBoard($boardId);
    }

    /**
     * Move tarefa entre colunas
     */
    public function moveTask(int $kanbanTaskId, int $targetColumnId, int $newOrder, ?int $userId = null): bool
    {
        if (!$this->ensureSchema()) {
            return false;
        }
        $moved = $this->taskRepo->moveToColumn($kanbanTaskId, $targetColumnId, $newOrder, $userId);
        if (!$moved) {
            return false;
        }

        $this->syncTaskProgressFromColumn($kanbanTaskId, $targetColumnId, $userId);
        $this->invalidateDashboardCaches();

        return true;
    }

    /**
     * Cria coluna no board
     */
    public function addColumn(int $boardId, array $data): KanbanColumn
    {
        if (!$this->ensureSchema()) {
            throw new \RuntimeException('Kanban schema not available');
        }
        $column = new KanbanColumn();
        $column->setBoardId($boardId);
        $column->setName($data['name'] ?? 'Nova coluna');
        $column->setColor($data['color'] ?? null);
        $column->setOrder((int) ($data['order'] ?? 0));
        $column->setWipLimit(isset($data['wip_limit']) ? (int) $data['wip_limit'] : null);
        $column->setIsBacklog((bool) ($data['is_backlog'] ?? false));
        $column->setIsDone((bool) ($data['is_done'] ?? false));
        $column->setStatus(0);

        if ($this->columnRepo->save($column) <= 0) {
            throw new \RuntimeException('Failed to create column');
        }

        return $column;
    }

    /**
     * Atualiza coluna existente
     */
    public function updateColumn(int $columnId, array $data): ?KanbanColumn
    {
        if (!$this->ensureSchema()) {
            return null;
        }
        $column = $this->columnRepo->find($columnId);
        if (!$column) {
            return null;
        }

        if (isset($data['name'])) {
            $column->setName($data['name']);
        }
        if (array_key_exists('color', $data)) {
            $column->setColor($data['color']);
        }
        if (array_key_exists('order', $data)) {
            $column->setOrder((int) $data['order']);
        }
        if (array_key_exists('wip_limit', $data)) {
            $column->setWipLimit($data['wip_limit'] !== null ? (int) $data['wip_limit'] : null);
        }
        if (array_key_exists('is_backlog', $data)) {
            $column->setIsBacklog((bool) $data['is_backlog']);
        }
        if (array_key_exists('is_done', $data)) {
            $column->setIsDone((bool) $data['is_done']);
        }

        if ($this->columnRepo->save($column) <= 0) {
            throw new \RuntimeException('Failed to update column');
        }

        return $column;
    }

    /**
     * Estatisticas do board
     */
    public function getAnalytics(int $boardId): array
    {
        if (!$this->ensureSchema()) {
            return [];
        }
        return [
            'time_stats' => $this->taskRepo->getColumnTimeStats($boardId),
        ];
    }

    private function ensureSchema(): bool
    {
        $this->schemaReady = true;
        return true;
    }

    /**
     * Garante que tarefas do projeto estejam no kanban
     */
    private function ensureProjectTasksInBoard(int $boardId, int $projectId, ?int $userId): void
    {
        $backlogColumnId = $this->getBacklogColumnId($boardId);
        if ($backlogColumnId === null) {
            return;
        }

        $taskRows = $this->db->fetchAll(sprintf(
            "SELECT task_id FROM `%s` WHERE task_project = %d",
            $this->db->table('tasks'),
            $projectId
        ));
        if (empty($taskRows)) {
            return;
        }

        $taskIds = array_map(fn($r) => (int) $r['task_id'], $taskRows);
        $existingRows = $this->db->fetchAll(sprintf(
            "SELECT kt.kanban_task_task_id
             FROM `dotp_kanban_tasks` kt
             JOIN `dotp_kanban_columns` c ON c.column_id = kt.kanban_task_column_id
             WHERE c.column_board_id = %d",
            $boardId
        ));
        $existingIds = array_map(fn($r) => (int) $r['kanban_task_task_id'], $existingRows);

        foreach ($taskIds as $taskId) {
            if (!in_array($taskId, $existingIds, true)) {
                $this->taskRepo->addTaskToColumn($taskId, $backlogColumnId, $userId);
            }
        }
    }

    /**
     * Retorna a coluna backlog de um board
     */
    private function getBacklogColumnId(int $boardId): ?int
    {
        $row = $this->db->fetchOne(sprintf(
            "SELECT column_id FROM `dotp_kanban_columns`
             WHERE column_board_id = %d AND column_is_backlog = 1 AND column_status = 0
             ORDER BY column_order ASC LIMIT 1",
            $boardId
        ));
        if ($row && isset($row['column_id'])) {
            return (int) $row['column_id'];
        }

        $fallback = $this->db->fetchOne(sprintf(
            "SELECT column_id FROM `dotp_kanban_columns`
             WHERE column_board_id = %d AND column_status = 0
             ORDER BY column_order ASC LIMIT 1",
            $boardId
        ));

        return $fallback ? (int) $fallback['column_id'] : null;
    }

    /**
     * Retorna tarefas agrupadas por coluna
     */
    private function getTasksByColumn(int $boardId): array
    {
        $tasksTable = $this->db->table('tasks');
        $usersTable = $this->db->table('users');
        $contactsTable = $this->db->table('contacts');
        $hasAssignedTo = $this->hasTableColumn($tasksTable, 'task_assigned_to');
        $taskAssignedToSelect = $hasAssignedTo
            ? 't.task_assigned_to'
            : 'NULL AS task_assigned_to';
        $assigneeJoinExpr = $hasAssignedTo
            ? 'COALESCE(t.task_assigned_to, t.task_owner)'
            : 't.task_owner';

        $sql = sprintf(
            "SELECT
                kt.kanban_task_id,
                kt.kanban_task_column_id,
                kt.kanban_task_task_id,
                kt.kanban_task_order,
                kt.kanban_task_moved_at,
                kt.kanban_task_moved_by,
                t.task_name,
                t.task_description,
                t.task_priority,
                t.task_percent_complete,
                t.task_end_date,
                t.task_owner,
                %s,
                t.task_duration,
                ct.contact_first_name AS user_first_name,
                ct.contact_last_name AS user_last_name,
                u.user_username
            FROM `dotp_kanban_tasks` kt
            JOIN `dotp_kanban_columns` c ON c.column_id = kt.kanban_task_column_id
            JOIN `%s` t ON t.task_id = kt.kanban_task_task_id
            LEFT JOIN `%s` u ON u.user_id = %s
            LEFT JOIN `%s` ct ON ct.contact_id = u.user_contact
            WHERE c.column_board_id = %d AND c.column_status = 0
            ORDER BY c.column_order ASC, kt.kanban_task_order ASC",
            $taskAssignedToSelect,
            $this->db->table('tasks'),
            $this->db->table('users'),
            $assigneeJoinExpr,
            $this->db->table('contacts'),
            $boardId
        );

        $rows = $this->db->fetchAll($sql);
        $byColumn = [];

        foreach ($rows as $row) {
            $columnId = (int) $row['kanban_task_column_id'];
            $byColumn[$columnId][] = [
                'id' => (int) $row['kanban_task_id'],
                'column_id' => $columnId,
                'task_id' => (int) $row['kanban_task_task_id'],
                'order' => (int) $row['kanban_task_order'],
                'moved_at' => $row['kanban_task_moved_at'],
                'moved_by' => $row['kanban_task_moved_by'] ? (int) $row['kanban_task_moved_by'] : null,
                'time_in_column' => $this->formatTimeInColumn($row['kanban_task_moved_at'] ?? null),
                'is_stale' => false,
                'task' => [
                    'name' => $row['task_name'],
                    'description' => $row['task_description'] ?? '',
                    'priority' => $this->mapPriority((int) ($row['task_priority'] ?? 0)),
                    'percent_complete' => (int) ($row['task_percent_complete'] ?? 0),
                    'is_overdue' => $this->isOverdue(
                        $row['task_end_date'] ?? null,
                        (int) ($row['task_percent_complete'] ?? 0)
                    ),
                    'assigned_to' => $row['task_assigned_to'] ?? $row['task_owner'] ?? null,
                    'assigned_to_name' => $this->formatUserName($row),
                    'estimated_hours' => $row['task_duration'] ?? null,
                    'comments_count' => 0,
                    'attachments_count' => 0,
                ],
            ];
        }

        return $byColumn;
    }

    private function hasTableColumn(string $table, string $column): bool
    {
        $cacheKey = $table . ':' . $column;
        if (array_key_exists($cacheKey, $this->columnPresenceCache)) {
            return $this->columnPresenceCache[$cacheKey];
        }

        $count = (int) ($this->db->fetchValue(
            "SELECT COUNT(*)
             FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = ?
               AND column_name = ?",
            [$table, $column]
        ) ?? 0);

        $this->columnPresenceCache[$cacheKey] = $count > 0;
        return $this->columnPresenceCache[$cacheKey];
    }

    private function syncTaskProgressFromColumn(int $kanbanTaskId, int $targetColumnId, ?int $movedByUserId = null): void
    {
        $taskRow = $this->db->fetchOne(
            "SELECT kanban_task_task_id
             FROM `dotp_kanban_tasks`
             WHERE kanban_task_id = ?
             LIMIT 1",
            [$kanbanTaskId]
        );
        if (!$taskRow || empty($taskRow['kanban_task_task_id'])) {
            return;
        }

        $taskId = (int) $taskRow['kanban_task_task_id'];
        $column = $this->db->fetchOne(
            "SELECT column_board_id, column_order, column_is_backlog, column_is_done
             FROM `dotp_kanban_columns`
             WHERE column_id = ?
             LIMIT 1",
            [$targetColumnId]
        );
        if (!$column) {
            return;
        }

        $boardId = (int) ($column['column_board_id'] ?? 0);
        if ($boardId <= 0) {
            return;
        }

        $firstActiveColumnId = (int) ($this->db->fetchValue(
            "SELECT column_id
             FROM `dotp_kanban_columns`
             WHERE column_board_id = ?
               AND column_status = 0
               AND column_is_backlog = 0
               AND column_is_done = 0
             ORDER BY column_order ASC
             LIMIT 1",
            [$boardId]
        ) ?? 0);

        $isBacklog = (int) ($column['column_is_backlog'] ?? 0) === 1;
        $isDone = (int) ($column['column_is_done'] ?? 0) === 1;
        $isFirstActive = $firstActiveColumnId > 0 && $firstActiveColumnId === $targetColumnId;

        $taskCurrent = $this->db->fetchOne(
            sprintf(
                "SELECT task_status, task_percent_complete
                 FROM `%s`
                 WHERE task_id = ?
                 LIMIT 1",
                $this->db->table('tasks')
            ),
            [$taskId]
        );
        if (!$taskCurrent) {
            return;
        }

        $status = (int) ($taskCurrent['task_status'] ?? 0);
        $percent = (int) ($taskCurrent['task_percent_complete'] ?? 0);

        if ($isBacklog) {
            $status = 0;
            $percent = 0;
        } elseif ($isDone) {
            $status = 3;
            $percent = 100;
        } elseif ($isFirstActive) {
            $status = 1;
            if ($percent < 0 || $percent > 99) {
                $percent = 0;
            }
        } else {
            $status = 2;
            if ($percent <= 0 || $percent >= 100) {
                $percent = 50;
            }
        }

        $this->db->update('tasks', [
            'task_status' => $status,
            'task_percent_complete' => max(0, min(100, $percent)),
        ], 'task_id = ' . $taskId);

        $this->projectProgressSync->syncByTaskId($taskId, $movedByUserId, 'kanban_move');
    }

    private function invalidateDashboardCaches(): void
    {
        // Dashboard data is cached with default cache prefix (dp:), not kanban prefix.
        $globalCache = new Cache();
        $globalCache->invalidate('*Dashboard*');
        $globalCache->invalidate('dashboard:*');

        // Analytics endpoints use dedicated prefix and need explicit invalidation.
        $analyticsCache = new Cache(prefix: 'analytics:');
        $analyticsCache->invalidate('dashboard:*');
        $analyticsCache->invalidate('productivity:*');
        $analyticsCache->invalidate('completion-trend:*');
    }

    private function isOverdue(?string $endDate, int $percentComplete): bool
    {
        if (!$endDate || $percentComplete >= 100) {
            return false;
        }
        return strtotime($endDate) < strtotime(date('Y-m-d'));
    }

    private function mapPriority(int $priority): int
    {
        return max(1, min(4, $priority + 1));
    }

    private function formatUserName(array $row): ?string
    {
        $first = $row['user_first_name'] ?? null;
        $last = $row['user_last_name'] ?? null;
        $username = $row['user_username'] ?? null;

        if ($first || $last) {
            return trim(($first ?? '') . ' ' . ($last ?? ''));
        }

        return $username ?: null;
    }

    private function formatTimeInColumn(?string $movedAt): ?string
    {
        if (!$movedAt) {
            return null;
        }
        $start = strtotime($movedAt);
        if ($start === false) {
            return null;
        }
        $diff = time() - $start;
        if ($diff < 60) {
            return '0m';
        }
        $minutes = (int) floor($diff / 60);
        if ($minutes < 60) {
            return $minutes . 'm';
        }
        $hours = (int) floor($minutes / 60);
        if ($hours < 24) {
            return $hours . 'h';
        }
        $days = (int) floor($hours / 24);
        return $days . 'd';
    }

    private function countOverdueTasks(array $tasks): int
    {
        $count = 0;
        foreach ($tasks as $task) {
            if (!empty($task['task']['is_overdue'])) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Hidrata dados do board
     */
    private function hydrateBoard(array $data): KanbanBoard
    {
        $entity = new KanbanBoard();
        $entity->setId((int) $data['board_id']);
        $entity->setName($data['board_name']);
        $entity->setDescription($data['board_description'] ?? null);
        $entity->setProjectId($data['board_project'] ? (int) $data['board_project'] : null);
        $entity->setCompanyId((int) $data['board_company']);
        $entity->setCreatedBy($data['board_created_by'] ? (int) $data['board_created_by'] : null);
        $entity->setStatus((int) $data['board_status']);

        if (!empty($data['board_created_at'])) {
            $entity->setCreatedAt(new DateTime($data['board_created_at']));
        }
        if (!empty($data['board_updated_at'])) {
            $entity->setUpdatedAt(new DateTime($data['board_updated_at']));
        }

        return $entity;
    }

    /**
     * Hidrata dados da coluna
     */
    private function hydrateColumn(array $data): KanbanColumn
    {
        $entity = new KanbanColumn();
        $entity->setId((int) $data['column_id']);
        $entity->setBoardId((int) $data['column_board_id']);
        $entity->setName($data['column_name']);
        $entity->setColor($data['column_color'] ?? null);
        $entity->setOrder((int) $data['column_order']);
        $entity->setWipLimit($data['column_wip_limit'] ? (int) $data['column_wip_limit'] : null);
        $entity->setIsDone((bool) $data['column_is_done']);
        $entity->setIsBacklog((bool) $data['column_is_backlog']);
        $entity->setStatus((int) $data['column_status']);

        return $entity;
    }
}
