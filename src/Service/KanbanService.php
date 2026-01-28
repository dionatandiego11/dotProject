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
use DotProject\Core\DomainEvents;
use DotProject\Core\EventDispatcher;
use DotProject\Core\Logger;
use DotProject\Entity\KanbanBoard;
use DotProject\Entity\KanbanColumn;
use DotProject\Entity\KanbanTask;
use DotProject\Entity\TaskEntity;
use DotProject\Repository\KanbanBoardRepository;
use DotProject\Repository\KanbanColumnRepository;
use DotProject\Repository\KanbanTaskRepository;
use DotProject\Repository\TaskRepository;

/**
 * Servico Kanban
 */
class KanbanService
{
    private KanbanBoardRepository $boardRepo;
    private KanbanColumnRepository $columnRepo;
    private KanbanTaskRepository $kanbanTaskRepo;
    private TaskRepository $taskRepo;
    private AuthorizationService $auth;
    private EventDispatcher $dispatcher;
    private Cache $cache;
    
    public function __construct(
        ?KanbanBoardRepository $boardRepo = null,
        ?KanbanColumnRepository $columnRepo = null,
        ?KanbanTaskRepository $kanbanTaskRepo = null,
        ?TaskRepository $taskRepo = null,
        ?AuthorizationService $auth = null,
        ?EventDispatcher $dispatcher = null,
        ?Cache $cache = null
    ) {
        $this->boardRepo = $boardRepo ?? new KanbanBoardRepository();
        $this->columnRepo = $columnRepo ?? new KanbanColumnRepository();
        $this->kanbanTaskRepo = $kanbanTaskRepo ?? new KanbanTaskRepository();
        $this->taskRepo = $taskRepo ?? new TaskRepository();
        $this->auth = $auth ?? AuthorizationService::getInstance();
        $this->dispatcher = $dispatcher ?? EventDispatcher::getInstance();
        $this->cache = $cache ?? new Cache(prefix: 'kanban:');
    }
    
    // =====================================================
    // BOARD OPERATIONS
    // =====================================================
    
    /**
     * Cria um novo board com colunas padrão
     */
    public function createBoard(array $data, int $userId): KanbanBoard
    {
        // Verifica permissao
        $this->auth->enforce(AuthorizationService::RESOURCE_PROJECT, AuthorizationService::PERMISSION_CREATE, $userId);
        
        $board = new KanbanBoard();
        $board->setName($data['name']);
        $board->setDescription($data['description'] ?? null);
        $board->setProjectId($data['project_id'] ?? null);
        $board->setCompanyId($data['company_id']);
        $board->setCreatedBy($userId);
        $board->setStatus(0);
        
        if (!$this->boardRepo->save($board)) {
            throw new \RuntimeException('Failed to create board');
        }
        
        // Cria colunas padrão
        $this->columnRepo->createDefaultColumns($board->getId() ?? 0);
        
        Logger::info('Kanban board created', [
            'board_id' => $board->getId(),
            'user_id' => $userId,
        ]);
        
        return $board;
    }
    
    /**
     * Obtém board completo com colunas e tarefas
     */
    public function getBoard(int $boardId, ?int $userId = null): ?array
    {
        $userId = $userId ?? $this->auth->getCurrentUser()?->getId();
        
        $board = $this->boardRepo->find($boardId);
        if (!$board) {
            return null;
        }
        
        // Verifica acesso
        if ($board->getProjectId()) {
            if (!$this->auth->canAccessProject($board->getProjectId(), AuthorizationService::PERMISSION_VIEW, $userId)) {
                return null;
            }
        }
        
        $cacheKey = "board_full:{$boardId}";
        $cached = $this->cache->get($cacheKey);
        
        if ($cached !== null) {
            return $cached;
        }
        
        // Carrega colunas
        $columns = $this->columnRepo->findByBoard($boardId);
        
        // Carrega tarefas de cada coluna
        foreach ($columns as $column) {
            $kanbanTasks = $this->kanbanTaskRepo->findByColumn($column->getId() ?? 0);
            
            // Hidrata dados das tarefas
            foreach ($kanbanTasks as $kanbanTask) {
                $task = $this->taskRepo->find($kanbanTask->getTaskId());
                if ($task) {
                    $kanbanTask->setTask($task);
                }
            }
            
            $column->setTasks($kanbanTasks);
        }
        
        $board->setColumns($columns);
        
        $result = [
            'board' => $board->toArray(),
            'stats' => $this->boardRepo->getBoardStats($boardId),
        ];
        
        $this->cache->set($cacheKey, $result, 60); // Cache curto
        
        return $result;
    }
    
    /**
     * Lista boards acessiveis ao usuario
     */
    public function getAccessibleBoards(int $companyId, ?int $projectId = null, ?int $userId = null): array
    {
        $userId = $userId ?? $this->auth->getCurrentUser()?->getId();
        
        if ($userId === null) {
            return [];
        }
        
        $boards = $this->boardRepo->findByCompany($companyId, $projectId);
        
        // Filtra por acesso ao projeto
        return array_filter($boards, function (KanbanBoard $board) use ($userId) {
            if ($board->getProjectId() === null) {
                return true; // Board global
            }
            return $this->auth->canAccessProject($board->getProjectId(), AuthorizationService::PERMISSION_VIEW, $userId);
        });
    }
    
    // =====================================================
    // COLUMN OPERATIONS
    // =====================================================
    
    /**
     * Adiciona coluna ao board
     */
    public function addColumn(int $boardId, array $data, int $userId): KanbanColumn
    {
        $board = $this->boardRepo->find($boardId);
        if (!$board) {
            throw new \RuntimeException('Board not found');
        }
        
        if ($board->getProjectId()) {
            $this->auth->enforceProjectAccess($board->getProjectId(), AuthorizationService::PERMISSION_EDIT, $userId);
        }
        
        $column = new KanbanColumn();
        $column->setBoardId($boardId);
        $column->setName($data['name']);
        $column->setColor($data['color'] ?? null);
        $column->setWipLimit($data['wip_limit'] ?? null);
        $column->setIsDone($data['is_done'] ?? false);
        $column->setIsBacklog($data['is_backlog'] ?? false);
        $column->setOrder($data['order'] ?? 999);
        $column->setStatus(0);
        
        if (!$this->columnRepo->save($column)) {
            throw new \RuntimeException('Failed to create column');
        }
        
        $this->cache->invalidate("board_full:{$boardId}");
        
        return $column;
    }
    
    /**
     * Atualiza coluna
     */
    public function updateColumn(int $columnId, array $data, int $userId): KanbanColumn
    {
        $column = $this->columnRepo->find($columnId);
        if (!$column) {
            throw new \RuntimeException('Column not found');
        }
        
        $board = $this->boardRepo->find($column->getBoardId());
        if ($board && $board->getProjectId()) {
            $this->auth->enforceProjectAccess($board->getProjectId(), AuthorizationService::PERMISSION_EDIT, $userId);
        }
        
        if (isset($data['name'])) {
            $column->setName($data['name']);
        }
        if (isset($data['color'])) {
            $column->setColor($data['color']);
        }
        if (isset($data['wip_limit'])) {
            $column->setWipLimit($data['wip_limit']);
        }
        if (isset($data['is_done'])) {
            $column->setIsDone($data['is_done']);
        }
        
        $this->columnRepo->save($column);
        $this->cache->invalidate("board_full:{$column->getBoardId()}");
        
        return $column;
    }
    
    /**
     * Reordena colunas
     */
    public function reorderColumns(int $boardId, array $columnOrders, int $userId): bool
    {
        $board = $this->boardRepo->find($boardId);
        if (!$board) {
            throw new \RuntimeException('Board not found');
        }
        
        if ($board->getProjectId()) {
            $this->auth->enforceProjectAccess($board->getProjectId(), AuthorizationService::PERMISSION_EDIT, $userId);
        }
        
        $result = $this->columnRepo->reorderColumns($columnOrders);
        
        if ($result) {
            $this->cache->invalidate("board_full:{$boardId}");
        }
        
        return $result;
    }
    
    // =====================================================
    // TASK OPERATIONS
    // =====================================================
    
    /**
     * Adiciona tarefa ao kanban
     */
    public function addTask(int $taskId, int $columnId, ?int $userId = null): ?KanbanTask
    {
        $userId = $userId ?? $this->auth->getCurrentUser()?->getId();
        
        $column = $this->columnRepo->find($columnId);
        if (!$column) {
            throw new \RuntimeException('Column not found');
        }
        
        $task = $this->taskRepo->find($taskId);
        if (!$task) {
            throw new \RuntimeException('Task not found');
        }
        
        // Verifica acesso a tarefa
        if (!$this->auth->canAccessTask($taskId, AuthorizationService::PERMISSION_EDIT, $userId)) {
            throw new \RuntimeException('Access denied');
        }
        
        // Verifica WIP limit
        if ($column->isAtWipLimit()) {
            throw new \RuntimeException('Column is at WIP limit');
        }
        
        $kanbanTask = $this->kanbanTaskRepo->addTaskToColumn($taskId, $columnId, $userId);
        
        if ($kanbanTask) {
            $this->cache->invalidate("board_full:{$column->getBoardId()}");
            
            Logger::info('Task added to kanban', [
                'task_id' => $taskId,
                'column_id' => $columnId,
                'user_id' => $userId,
            ]);
        }
        
        return $kanbanTask;
    }
    
    /**
     * Move tarefa entre colunas ou reordena na mesma coluna
     */
    public function moveTask(
        int $kanbanTaskId, 
        int $targetColumnId, 
        int $newOrder, 
        ?int $userId = null
    ): bool {
        $userId = $userId ?? $this->auth->getCurrentUser()?->getId();
        
        $kanbanTask = $this->kanbanTaskRepo->find($kanbanTaskId);
        if (!$kanbanTask) {
            throw new \RuntimeException('Kanban task not found');
        }
        
        $sourceColumn = $this->columnRepo->find($kanbanTask->getColumnId());
        $targetColumn = $this->columnRepo->find($targetColumnId);
        
        if (!$targetColumn) {
            throw new \RuntimeException('Target column not found');
        }
        
        $task = $this->taskRepo->find($kanbanTask->getTaskId());
        if (!$task) {
            throw new \RuntimeException('Task not found');
        }
        
        // Verifica permissao
        if (!$this->auth->canAccessTask($task->getId() ?? 0, AuthorizationService::PERMISSION_EDIT, $userId)) {
            throw new \RuntimeException('Access denied');
        }
        
        // Verifica WIP limit na coluna de destino (se for coluna diferente)
        if ($sourceColumn->getId() !== $targetColumnId && $targetColumn->isAtWipLimit()) {
            throw new \RuntimeException('Target column is at WIP limit');
        }
        
        // Executa movimento
        $result = $this->kanbanTaskRepo->moveToColumn($kanbanTaskId, $targetColumnId, $newOrder, $userId);
        
        if ($result) {
            $this->cache->invalidate("board_full:{$targetColumn->getBoardId()}");
            
            // Se moveu para coluna "done", atualiza progresso da tarefa
            if ($targetColumn->isDone() && !$task->isCompleted()) {
                $task->setStatus(1); // Completada
                $task->setPercentComplete(100);
                $this->taskRepo->save($task);
            }
            
            Logger::info('Task moved in kanban', [
                'kanban_task_id' => $kanbanTaskId,
                'from_column' => $sourceColumn->getId(),
                'to_column' => $targetColumnId,
                'user_id' => $userId,
            ]);
        }
        
        return $result;
    }
    
    /**
     * Move múltiplas tarefas (drag & drop em lote)
     */
    public function moveTasks(array $moves, int $boardId, ?int $userId = null): bool
    {
        $userId = $userId ?? $this->auth->getCurrentUser()?->getId();
        
        try {
            foreach ($moves as $move) {
                $this->moveTask(
                    $move['kanban_task_id'],
                    $move['column_id'],
                    $move['order'],
                    $userId
                );
            }
            
            $this->cache->invalidate("board_full:{$boardId}");
            return true;
        } catch (\Exception $e) {
            Logger::error('Failed to move tasks', ['error' => $e->getMessage()]);
            return false;
        }
    }
    
    /**
     * Remove tarefa do kanban
     */
    public function removeTask(int $taskId, ?int $userId = null): bool
    {
        $userId = $userId ?? $this->auth->getCurrentUser()?->getId();
        
        $kanbanTask = $this->kanbanTaskRepo->findByTask($taskId);
        if (!$kanbanTask) {
            return true;
        }
        
        $column = $this->columnRepo->find($kanbanTask->getColumnId());
        
        if (!$this->auth->canAccessTask($taskId, AuthorizationService::PERMISSION_DELETE, $userId)) {
            throw new \RuntimeException('Access denied');
        }
        
        $result = $this->kanbanTaskRepo->removeTask($taskId);
        
        if ($result && $column) {
            $this->cache->invalidate("board_full:{$column->getBoardId()}");
        }
        
        return $result;
    }
    
    // =====================================================
    // ANALYTICS
    // =====================================================
    
    /**
     * Obtém estatísticas do board
     */
    public function getBoardAnalytics(int $boardId): array
    {
        $board = $this->boardRepo->find($boardId);
        if (!$board) {
            throw new \RuntimeException('Board not found');
        }
        
        $columns = $this->columnRepo->findByBoard($boardId);
        $columnStats = [];
        
        foreach ($columns as $column) {
            $count = $this->kanbanTaskRepo->countByColumn($column->getId() ?? 0);
            $columnStats[] = [
                'column_id' => $column->getId(),
                'column_name' => $column->getName(),
                'task_count' => $count,
                'wip_limit' => $column->getWipLimit(),
                'is_at_limit' => $column->isAtWipLimit(),
            ];
        }
        
        // Tarefas travadas
        $staleTasks = $this->kanbanTaskRepo->findStaleTasks();
        
        return [
            'board_id' => $boardId,
            'columns' => $columnStats,
            'stale_tasks_count' => count($staleTasks),
            'stale_tasks' => array_map(fn($t) => $t->toArray(), $staleTasks),
        ];
    }
    
    /**
     * Obtém tempo médio em cada coluna (lead time analysis)
     */
    public function getLeadTimeAnalysis(int $boardId): array
    {
        return $this->kanbanTaskRepo->getColumnTimeStats($boardId);
    }
}
