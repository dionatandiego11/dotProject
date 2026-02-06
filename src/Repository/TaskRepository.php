<?php
/**
 * Repository Task
 * 
 * @package DotProject\Repository
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Repository;

use DotProject\Entity\TaskEntity;
use DateTime;

class TaskRepository extends BaseRepository
{
    protected string $table = 'dotp_tasks';
    protected string $primaryKey = 'task_id';

    protected function hydrate(array $data): TaskEntity
    {
        $entity = new TaskEntity();
        $entity->setId((int) $data['task_id']);
        $entity->setName($data['task_name']);
        $entity->setDescription($data['task_description'] ?? null);
        $entity->setProjectId((int) $data['task_project']);
        $entity->setParentTaskId($data['task_parent'] ? (int) $data['task_parent'] : null);
        $entity->setAssignedTo(!empty($data['task_assigned_to']) ? (int) $data['task_assigned_to'] : null);
        $entity->setOwnerId((int) ($data['task_owner'] ?? 0));
        $entity->setStatus((int) ($data['task_status'] ?? 0));
        $entity->setPriority((int) ($data['task_priority'] ?? 3));
        $entity->setPercentComplete((int) ($data['task_percent_complete'] ?? 0));
        $entity->setEstimatedHours(isset($data['task_hours']) && $data['task_hours'] !== null ? (float) $data['task_hours'] : null);
        $entity->setActualHours(isset($data['task_actual_hours']) && $data['task_actual_hours'] !== null ? (float) $data['task_actual_hours'] : null);
        
        if (!empty($data['task_start_date'])) {
            $entity->setStartDate(new DateTime($data['task_start_date']));
        }
        if (!empty($data['task_end_date'])) {
            $entity->setEndDate(new DateTime($data['task_end_date']));
        }
        if (!empty($data['task_actual_end_date'])) {
            $entity->setActualEndDate(new DateTime($data['task_actual_end_date']));
        }
        if (!empty($data['task_created'])) {
            $entity->setCreatedAt(new DateTime($data['task_created']));
        }
        if (!empty($data['task_updated'])) {
            $entity->setUpdatedAt(new DateTime($data['task_updated']));
        }

        return $entity;
    }

    protected function extract(object $entity): array
    {
        if (!$entity instanceof TaskEntity) {
            throw new \InvalidArgumentException('Entity must be TaskEntity');
        }

        return [
            'task_id' => $entity->getId(),
            'task_name' => $entity->getName(),
            'task_description' => $entity->getDescription(),
            'task_project' => $entity->getProjectId(),
            'task_parent' => $entity->getParentTaskId(),
            'task_assigned_to' => $entity->getAssignedTo(),
            'task_owner' => $entity->getOwnerId(),
            'task_status' => $entity->getStatus(),
            'task_priority' => $entity->getPriority(),
            'task_percent_complete' => $entity->getPercentComplete(),
            'task_hours' => $entity->getEstimatedHours(),
            'task_actual_hours' => $entity->getActualHours(),
            'task_start_date' => $entity->getStartDate()?->format('Y-m-d'),
            'task_end_date' => $entity->getEndDate()?->format('Y-m-d'),
            'task_actual_end_date' => $entity->getActualEndDate()?->format('Y-m-d'),
        ];
    }

    public function save(object $entity): int
    {
        if (!$entity instanceof TaskEntity) {
            throw new \InvalidArgumentException('Entity must be TaskEntity');
        }

        $data = $this->extract($entity);
        $result = false;
        
        if ($entity->getId() === null) {
            unset($data['task_id']);
            $result = $this->db->insert($this->table, $data);
            if ($result) {
                $entity->setId((int) $this->db->lastInsertId());
            }
        } else {
            $id = $data['task_id'];
            unset($data['task_id']);
            $result = $this->db->update($this->table, $data, "task_id = {$id}");
        }

        if ($result) {
            $this->clearCache();
        }
        return $result ? (int) $entity->getId() : 0;
    }

    public function delete(int $id): bool
    {
        $result = $this->db->delete($this->table, "task_id = {$id}");
        if ($result) {
            $this->cache->delete($this->cacheKey("find:{$id}"));
            $this->cache->invalidate($this->cacheKey('*'));
        }
        return $result;
    }

    /**
     * Busca tarefas por projeto
     * @return array<TaskEntity>
     */
    public function findByProject(int $projectId): array
    {
        return $this->findBy(['task_project' => $projectId], ['task_priority' => 'ASC', 'task_end_date' => 'ASC']);
    }

    /**
     * Busca tarefas por responsável
     * @return array<TaskEntity>
     */
    public function findByAssignee(int $userId): array
    {
        return $this->findBy(['task_assigned_to' => $userId, 'task_status' => 0], ['task_end_date' => 'ASC']);
    }

    /**
     * Busca tarefas atrasadas
     * @return array<TaskEntity>
     */
    public function findOverdue(): array
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE task_end_date < CURDATE() 
                AND task_status = 0
                ORDER BY task_end_date ASC";
        $results = $this->db->fetchAll($sql);
        return array_map([$this, 'hydrate'], $results);
    }

    /**
     * Busca tarefas próximas do vencimento (próximos 3 dias)
     * @return array<TaskEntity>
     */
    public function findDueSoon(int $days = 3): array
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE task_end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
                AND task_status = 0
                ORDER BY task_end_date ASC";
        $results = $this->db->fetchAll($sql, [$days]);
        return array_map([$this, 'hydrate'], $results);
    }

    /**
     * Conta tarefas por status em um projeto
     */
    public function countByStatus(int $projectId): array
    {
        $sql = "SELECT 
                    SUM(CASE WHEN task_status = 0 THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN task_status = 1 THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN task_end_date < CURDATE() AND task_status = 0 THEN 1 ELSE 0 END) as overdue,
                    COUNT(*) as total
                FROM {$this->table} 
                WHERE task_project = ?";
        return $this->db->fetchOne($sql, [$projectId]) ?: ['pending' => 0, 'completed' => 0, 'overdue' => 0, 'total' => 0];
    }
}
