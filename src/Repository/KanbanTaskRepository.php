<?php
/**
 * Kanban Task Repository
 * 
 * Repository para gerenciar posição de tarefas no Kanban.
 * 
 * @package DotProject\Repository
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Repository;

use DotProject\Entity\KanbanTask;
use DateTime;

/**
 * Repository para KanbanTask
 */
class KanbanTaskRepository extends BaseRepository
{
    protected string $table = 'dotp_kanban_tasks';
    protected string $primaryKey = 'kanban_task_id';
    protected int $cacheTtl = 60; // Cache curto por ser volátil
    
    /**
     * {@inheritdoc}
     */
    protected function hydrate(array $data): KanbanTask
    {
        $entity = new KanbanTask();
        $entity->setId((int) $data['kanban_task_id']);
        $entity->setColumnId((int) $data['kanban_task_column_id']);
        $entity->setTaskId((int) $data['kanban_task_task_id']);
        $entity->setOrder((int) $data['kanban_task_order']);
        
        if (!empty($data['kanban_task_moved_at'])) {
            $entity->setMovedAt(new DateTime($data['kanban_task_moved_at']));
        }
        
        $entity->setMovedBy($data['kanban_task_moved_by'] ? (int) $data['kanban_task_moved_by'] : null);
        
        return $entity;
    }
    
    /**
     * {@inheritdoc}
     */
    protected function extract(object $entity): array
    {
        if (!$entity instanceof KanbanTask) {
            throw new \InvalidArgumentException('Entity must be KanbanTask');
        }
        
        return [
            'kanban_task_id' => $entity->getId(),
            'kanban_task_column_id' => $entity->getColumnId(),
            'kanban_task_task_id' => $entity->getTaskId(),
            'kanban_task_order' => $entity->getOrder(),
            'kanban_task_moved_at' => $entity->getMovedAt()?->format('Y-m-d H:i:s'),
            'kanban_task_moved_by' => $entity->getMovedBy(),
        ];
    }
    
    /**
     * {@inheritdoc}
     */
    public function save(object $entity): int
    {
        if (!$entity instanceof KanbanTask) {
            throw new \InvalidArgumentException('Entity must be KanbanTask');
        }
        
        $data = $this->extract($entity);
        
        if ($entity->getId() === null) {
            unset($data['kanban_task_id']);
            $data['kanban_task_moved_at'] = date('Y-m-d H:i:s');
            $result = $this->db->insert($this->table, $data);
            if ($result) {
                $entity->setId((int) $this->db->lastInsertId());
            }
        } else {
            $id = $data['kanban_task_id'];
            unset($data['kanban_task_id']);
            $result = $this->db->update($this->table, $data, "{$this->primaryKey} = {$id}");
        }
        
        if ($result) {
            $this->cache->invalidate("kanban:column:*");
        }
        
        return $result ? ($entity->getId() ?? 0) : 0;
    }
    
    /**
     * {@inheritdoc}
     */
    public function delete(int $id): bool
    {
        $result = parent::delete($id);
        
        if ($result) {
            $this->cache->invalidate("kanban:column:*");
        }
        
        return $result;
    }
    
    /**
     * Encontra tarefas em uma coluna
     * 
     * @return array<KanbanTask>
     */
    public function findByColumn(int $columnId): array
    {
        return $this->findBy(
            ['kanban_task_column_id' => $columnId],
            ['kanban_task_order' => 'ASC']
        );
    }
    
    /**
     * Encontra posição de uma tarefa no kanban
     */
    public function findByTask(int $taskId): ?KanbanTask
    {
        $results = $this->findBy(['kanban_task_task_id' => $taskId], null, 1);
        return $results[0] ?? null;
    }
    
    /**
     * Verifica se uma tarefa está em algum board
     */
    public function isTaskInBoard(int $taskId): bool
    {
        return $this->findByTask($taskId) !== null;
    }
    
    /**
     * Move uma tarefa para outra coluna
     */
    public function moveToColumn(int $kanbanTaskId, int $newColumnId, int $newOrder, ?int $movedBy = null): bool
    {
        $result = $this->db->update(
            $this->table,
            [
                'kanban_task_column_id' => $newColumnId,
                'kanban_task_order' => $newOrder,
                'kanban_task_moved_at' => date('Y-m-d H:i:s'),
                'kanban_task_moved_by' => $movedBy,
            ],
            "{$this->primaryKey} = {$kanbanTaskId}"
        );
        
        if ($result) {
            $this->cache->invalidate("kanban:column:*");
        }
        
        return $result;
    }
    
    /**
     * Reordena tarefas em uma coluna
     * 
     * @param array<int, int> $taskOrders Mapa [kanbanTaskId => order]
     */
    public function reorderTasks(int $columnId, array $taskOrders): bool
    {
        try {
            foreach ($taskOrders as $kanbanTaskId => $order) {
                $this->db->update(
                    $this->table,
                    [
                        'kanban_task_order' => (int) $order,
                        'kanban_task_moved_at' => date('Y-m-d H:i:s'),
                    ],
                    "{$this->primaryKey} = " . (int) $kanbanTaskId
                );
            }
            $this->cache->invalidate("kanban:column:{$columnId}");
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Adiciona uma tarefa a uma coluna
     */
    public function addTaskToColumn(int $taskId, int $columnId, ?int $movedBy = null): ?KanbanTask
    {
        // Verifica se já existe
        $existing = $this->findByTask($taskId);
        if ($existing) {
            // Move para nova coluna
            $this->moveToColumn($existing->getId() ?? 0, $columnId, 999, $movedBy);
            return $existing;
        }
        
        // Cria novo
        $kanbanTask = new KanbanTask();
        $kanbanTask->setTaskId($taskId);
        $kanbanTask->setColumnId($columnId);
        $kanbanTask->setOrder(999); // Vai para o final
        $kanbanTask->setMovedBy($movedBy);
        
        if ($this->save($kanbanTask) > 0) {
            return $kanbanTask;
        }
        
        return null;
    }
    
    /**
     * Remove uma tarefa do kanban
     */
    public function removeTask(int $taskId): bool
    {
        $existing = $this->findByTask($taskId);
        if (!$existing) {
            return true;
        }
        
        return $this->delete($existing->getId() ?? 0);
    }
    
    /**
     * Obtém próxima ordem disponível em uma coluna
     */
    public function getNextOrder(int $columnId): int
    {
        $sql = sprintf(
            "SELECT MAX(kanban_task_order) as max_order FROM `%s` WHERE kanban_task_column_id = %d",
            $this->table,
            $columnId
        );
        
        $max = (int) ($this->db->fetchValue($sql) ?? 0);
        return $max + 1;
    }
    
    /**
     * Obtém estatísticas de tempo médio em cada coluna
     */
    public function getColumnTimeStats(int $boardId): array
    {
        $sql = sprintf(
            "SELECT 
                c.column_name,
                c.column_id,
                AVG(TIMESTAMPDIFF(HOUR, kt.kanban_task_moved_at, NOW())) as avg_hours
            FROM `dotp_kanban_columns` c
            LEFT JOIN `dotp_kanban_tasks` kt ON kt.kanban_task_column_id = c.column_id
            WHERE c.column_board_id = %d AND c.column_status = 0
            GROUP BY c.column_id, c.column_name
            ORDER BY c.column_order",
            $boardId
        );
        
        return $this->db->fetchAll($sql);
    }
    
    /**
     * Obtém tarefas "travadas" (muito tempo na mesma coluna)
     * 
     * @return array<KanbanTask>
     */
    public function findStaleTasks(int $thresholdHours = 48): array
    {
        $sql = sprintf(
            "SELECT kt.* FROM `%s` kt
             JOIN `dotp_kanban_columns` c ON c.column_id = kt.kanban_task_column_id
             WHERE c.column_is_done = 0
             AND kt.kanban_task_moved_at < DATE_SUB(NOW(), INTERVAL %d HOUR)
             ORDER BY kt.kanban_task_moved_at ASC",
            $this->table,
            $thresholdHours
        );
        
        $results = $this->db->fetchAll($sql);
        return array_map([$this, 'hydrate'], $results);
    }
    
    /**
     * Conta tarefas por coluna
     */
    public function countByColumn(int $columnId): int
    {
        $sql = sprintf(
            "SELECT COUNT(*) FROM `%s` WHERE kanban_task_column_id = %d",
            $this->table,
            $columnId
        );
        
        return (int) $this->db->fetchValue($sql);
    }
}
