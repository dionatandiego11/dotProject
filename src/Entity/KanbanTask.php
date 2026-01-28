<?php
/**
 * Kanban Task Entity
 * 
 * Entidade para vincular tarefas a colunas do Kanban.
 * Representa a posição de uma tarefa em uma coluna específica.
 * 
 * @package DotProject\Entity
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Entity;

use DateTime;

/**
 * Entidade KanbanTask
 */
class KanbanTask
{
    private ?int $id = null;
    private int $columnId;
    private int $taskId;
    private int $order = 0;  // Ordem da tarefa na coluna (de cima para baixo)
    private ?DateTime $movedAt = null;  // Quando foi movida para esta coluna
    private ?int $movedBy = null;  // Quem moveu
    
    /** @var TaskEntity|null */
    private ?TaskEntity $task = null;
    
    public function getId(): ?int
    {
        return $this->id;
    }
    
    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }
    
    public function getColumnId(): int
    {
        return $this->columnId;
    }
    
    public function setColumnId(int $columnId): self
    {
        $this->columnId = $columnId;
        return $this;
    }
    
    public function getTaskId(): int
    {
        return $this->taskId;
    }
    
    public function setTaskId(int $taskId): self
    {
        $this->taskId = $taskId;
        return $this;
    }
    
    public function getOrder(): int
    {
        return $this->order;
    }
    
    public function setOrder(int $order): self
    {
        $this->order = $order;
        return $this;
    }
    
    public function getMovedAt(): ?DateTime
    {
        return $this->movedAt;
    }
    
    public function setMovedAt(?DateTime $movedAt): self
    {
        $this->movedAt = $movedAt;
        return $this;
    }
    
    public function getMovedBy(): ?int
    {
        return $this->movedBy;
    }
    
    public function setMovedBy(?int $movedBy): self
    {
        $this->movedBy = $movedBy;
        return $this;
    }
    
    public function getTask(): ?TaskEntity
    {
        return $this->task;
    }
    
    public function setTask(?TaskEntity $task): self
    {
        $this->task = $task;
        return $this;
    }
    
    /**
     * Calcula tempo que a tarefa está nesta coluna
     */
    public function getTimeInColumn(): ?string
    {
        if ($this->movedAt === null) {
            return null;
        }
        
        $now = new DateTime();
        $diff = $this->movedAt->diff($now);
        
        if ($diff->d > 0) {
            return $diff->d . 'd';
        }
        if ($diff->h > 0) {
            return $diff->h . 'h';
        }
        return $diff->i . 'm';
    }
    
    /**
     * Verifica se tarefa está "travada" na coluna (muito tempo parada)
     */
    public function isStale(int $thresholdHours = 48): bool
    {
        if ($this->movedAt === null) {
            return false;
        }
        
        $now = new DateTime();
        $diff = $this->movedAt->diff($now);
        $hours = ($diff->days * 24) + $diff->h;
        
        return $hours >= $thresholdHours;
    }
    
    /**
     * Converte para array
     */
    public function toArray(): array
    {
        $data = [
            'id' => $this->id,
            'column_id' => $this->columnId,
            'task_id' => $this->taskId,
            'order' => $this->order,
            'moved_at' => $this->movedAt?->format('Y-m-d H:i:s'),
            'moved_by' => $this->movedBy,
            'time_in_column' => $this->getTimeInColumn(),
            'is_stale' => $this->isStale(),
        ];
        
        if ($this->task !== null) {
            $data['task'] = $this->task->toArray();
        }
        
        return $data;
    }
}
