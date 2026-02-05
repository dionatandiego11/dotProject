<?php
/**
 * Kanban Column Repository
 * 
 * Repository para gerenciar colunas do Kanban.
 * 
 * @package DotProject\Repository
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Repository;

use DotProject\Entity\KanbanColumn;

/**
 * Repository para KanbanColumn
 */
class KanbanColumnRepository extends BaseRepository
{
    protected string $table = 'dotp_kanban_columns';
    protected string $primaryKey = 'column_id';
    protected int $cacheTtl = 300;
    
    /**
     * {@inheritdoc}
     */
    protected function hydrate(array $data): KanbanColumn
    {
        $entity = new KanbanColumn();
        $entity->setId((int) $data['column_id']);
        $entity->setBoardId((int) $data['column_board_id']);
        $entity->setName($data['column_name']);
        $entity->setColor($data['column_color'] ?? null);
        $entity->setOrder((int) $data['column_order']);
        $entity->setWipLimit($data['column_wip_limit'] ? (int) $data['column_wip_limit'] : null);
        $entity->setStatus((int) $data['column_status']);
        $entity->setIsDone((bool) $data['column_is_done']);
        $entity->setIsBacklog((bool) $data['column_is_backlog']);
        
        return $entity;
    }
    
    /**
     * {@inheritdoc}
     */
    protected function extract(object $entity): array
    {
        if (!$entity instanceof KanbanColumn) {
            throw new \InvalidArgumentException('Entity must be KanbanColumn');
        }
        
        return [
            'column_id' => $entity->getId(),
            'column_board_id' => $entity->getBoardId(),
            'column_name' => $entity->getName(),
            'column_color' => $entity->getColor(),
            'column_order' => $entity->getOrder(),
            'column_wip_limit' => $entity->getWipLimit(),
            'column_status' => $entity->getStatus(),
            'column_is_done' => $entity->isDone() ? 1 : 0,
            'column_is_backlog' => $entity->isBacklog() ? 1 : 0,
        ];
    }
    
    /**
     * {@inheritdoc}
     */
    public function save(object $entity): int
    {
        if (!$entity instanceof KanbanColumn) {
            throw new \InvalidArgumentException('Entity must be KanbanColumn');
        }
        
        $data = $this->extract($entity);
        
        if ($entity->getId() === null) {
            unset($data['column_id']);
            $result = $this->db->insert($this->table, $data);
            if ($result) {
                $entity->setId((int) $this->db->lastInsertId());
            }
        } else {
            $id = $data['column_id'];
            unset($data['column_id']);
            $result = $this->db->update($this->table, $data, "{$this->primaryKey} = {$id}");
        }
        
        if ($result) {
            $this->clearCache();
        }
        
        return $result ? ($entity->getId() ?? 0) : 0;
    }
    
    /**
     * Encontra colunas de um board
     * 
     * @return array<KanbanColumn>
     */
    public function findByBoard(int $boardId): array
    {
        return $this->findBy(
            ['column_board_id' => $boardId, 'column_status' => 0],
            ['column_order' => 'ASC']
        );
    }
    
    /**
     * Cria colunas padrão para um novo board
     */
    public function createDefaultColumns(int $boardId): void
    {
        $defaultColumns = [
            ['name' => 'Backlog', 'color' => '#6B7280', 'order' => 0, 'is_backlog' => true, 'wip_limit' => null],
            ['name' => 'To Do', 'color' => '#3B82F6', 'order' => 1, 'is_backlog' => false, 'wip_limit' => null],
            ['name' => 'In Progress', 'color' => '#F59E0B', 'order' => 2, 'is_backlog' => false, 'wip_limit' => 3],
            ['name' => 'Review', 'color' => '#8B5CF6', 'order' => 3, 'is_backlog' => false, 'wip_limit' => 5],
            ['name' => 'Done', 'color' => '#10B981', 'order' => 4, 'is_backlog' => false, 'is_done' => true, 'wip_limit' => null],
        ];
        
        foreach ($defaultColumns as $colData) {
            $column = new KanbanColumn();
            $column->setBoardId($boardId);
            $column->setName($colData['name']);
            $column->setColor($colData['color']);
            $column->setOrder($colData['order']);
            $column->setWipLimit($colData['wip_limit'] ?? null);
            $column->setIsBacklog($colData['is_backlog'] ?? false);
            $column->setIsDone($colData['is_done'] ?? false);
            $column->setStatus(0);
            
            $this->save($column);
        }
    }
    
    /**
     * Reordena colunas
     * 
     * @param array<int, int> $columnOrders Mapa [columnId => order]
     */
    public function reorderColumns(array $columnOrders): bool
    {
        try {
            foreach ($columnOrders as $columnId => $order) {
                $this->db->update(
                    $this->table,
                    ['column_order' => (int) $order],
                    "{$this->primaryKey} = " . (int) $columnId
                );
            }
            $this->clearCache();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Obtém a coluna de backlog de um board
     */
    public function findBacklogColumn(int $boardId): ?KanbanColumn
    {
        $columns = $this->findBy(
            ['column_board_id' => $boardId, 'column_is_backlog' => 1, 'column_status' => 0],
            ['column_order' => 'ASC'],
            1
        );
        
        return $columns[0] ?? null;
    }
    
    /**
     * Obtém a coluna de concluídos de um board
     */
    public function findDoneColumn(int $boardId): ?KanbanColumn
    {
        $columns = $this->findBy(
            ['column_board_id' => $boardId, 'column_is_done' => 1, 'column_status' => 0],
            ['column_order' => 'ASC'],
            1
        );
        
        return $columns[0] ?? null;
    }
    
    /**
     * Verifica se coluna está no limite WIP
     */
    public function isAtWipLimit(int $columnId): bool
    {
        $column = $this->find($columnId);
        if (!$column || $column->getWipLimit() === null) {
            return false;
        }
        
        $sql = sprintf(
            "SELECT COUNT(*) as count FROM `dotp_kanban_tasks` WHERE kanban_task_column_id = %d",
            $columnId
        );
        
        $count = (int) $this->db->fetchValue($sql);
        return $count >= $column->getWipLimit();
    }
    
    /**
     * Arquiva uma coluna
     */
    public function archive(int $columnId): bool
    {
        // Primeiro move todas as tarefas para o backlog ou coluna anterior
        $column = $this->find($columnId);
        if (!$column) {
            return false;
        }
        
        $result = $this->db->update(
            $this->table,
            ['column_status' => 1],
            "{$this->primaryKey} = {$columnId}"
        );
        
        if ($result) {
            $this->clearCache();
        }
        
        return $result;
    }
    
    /**
     * Duplica colunas de um board para outro
     */
    public function duplicateColumns(int $sourceBoardId, int $targetBoardId): bool
    {
        $columns = $this->findByBoard($sourceBoardId);
        
        foreach ($columns as $column) {
            $newColumn = new KanbanColumn();
            $newColumn->setBoardId($targetBoardId);
            $newColumn->setName($column->getName());
            $newColumn->setColor($column->getColor());
            $newColumn->setOrder($column->getOrder());
            $newColumn->setWipLimit($column->getWipLimit());
            $newColumn->setIsDone($column->isDone());
            $newColumn->setIsBacklog($column->isBacklog());
            $newColumn->setStatus(0);
            
            if (!$this->save($newColumn)) {
                return false;
            }
        }
        
        return true;
    }
}
