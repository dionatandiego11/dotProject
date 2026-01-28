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
use DateTime;

/**
 * Servico Kanban
 */
class KanbanService
{
    private Database $db;
    private AuthorizationService $auth;
    private Cache $cache;
    
    public function __construct(
        ?Database $db = null,
        ?AuthorizationService $auth = null,
        ?Cache $cache = null
    ) {
        $this->db = $db ?? Database::getInstance();
        $this->auth = $auth ?? AuthorizationService::getInstance();
        $this->cache = $cache ?? new Cache(prefix: 'kanban:');
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
        $sql = sprintf("SELECT * FROM `dotp_kanban_boards` WHERE board_id = %d", $boardId);
        $data = $this->db->fetchOne($sql);
        
        if (!$data) {
            return null;
        }
        
        $board = $this->hydrateBoard($data);
        
        // Carrega colunas
        $columns = $this->getColumns($boardId);
        
        return [
            'board' => $board->toArray(),
            'columns' => array_map(fn($c) => $c->toArray(), $columns),
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
        $sql = sprintf(
            "SELECT * FROM `dotp_kanban_columns` 
             WHERE column_board_id = %d AND column_status = 0
             ORDER BY column_order ASC",
            $boardId
        );
        
        $results = $this->db->fetchAll($sql);
        $columns = [];
        
        foreach ($results as $data) {
            $columns[] = $this->hydrateColumn($data);
        }
        
        return $columns;
    }
    
    /**
     * Move tarefa entre colunas
     */
    public function moveTask(int $kanbanTaskId, int $targetColumnId, int $newOrder, ?int $userId = null): bool
    {
        $sql = sprintf(
            "UPDATE `dotp_kanban_tasks` 
             SET kanban_task_column_id = %d, 
                 kanban_task_order = %d,
                 kanban_task_moved_by = %d,
                 kanban_task_moved_at = NOW()
             WHERE kanban_task_id = %d",
            $targetColumnId,
            $newOrder,
            $userId ?? 0,
            $kanbanTaskId
        );
        
        return $this->db->query($sql);
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
