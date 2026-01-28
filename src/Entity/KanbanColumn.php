<?php
/**
 * Kanban Column Entity
 * 
 * Entidade para colunas do Kanban.
 * 
 * @package DotProject\Entity
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Entity;

/**
 * Entidade KanbanColumn
 */
class KanbanColumn
{
    private ?int $id = null;
    private int $boardId;
    private string $name;
    private ?string $color = null;  // Hex color para identificação visual
    private int $order = 0;  // Ordem da coluna no board (da esquerda para direita)
    private ?int $wipLimit = null;  // Work In Progress limit
    private int $status = 0;  // 0=ativo, 1=arquivado
    private bool $isDone = false;  // Coluna representa tarefas concluídas
    private bool $isBacklog = false;  // Coluna é o backlog inicial
    
    /** @var KanbanTask[] */
    private array $tasks = [];
    
    public function getId(): ?int
    {
        return $this->id;
    }
    
    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }
    
    public function getBoardId(): int
    {
        return $this->boardId;
    }
    
    public function setBoardId(int $boardId): self
    {
        $this->boardId = $boardId;
        return $this;
    }
    
    public function getName(): string
    {
        return $this->name;
    }
    
    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }
    
    public function getColor(): ?string
    {
        return $this->color;
    }
    
    public function setColor(?string $color): self
    {
        $this->color = $color;
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
    
    public function getWipLimit(): ?int
    {
        return $this->wipLimit;
    }
    
    public function setWipLimit(?int $wipLimit): self
    {
        $this->wipLimit = $wipLimit;
        return $this;
    }
    
    /**
     * Verifica se coluna está no limite WIP
     */
    public function isAtWipLimit(): bool
    {
        if ($this->wipLimit === null) {
            return false;
        }
        return count($this->tasks) >= $this->wipLimit;
    }
    
    /**
     * Retorna quantas tarefas ainda podem entrar na coluna
     */
    public function getRemainingWipSlots(): ?int
    {
        if ($this->wipLimit === null) {
            return null;
        }
        return max(0, $this->wipLimit - count($this->tasks));
    }
    
    public function getStatus(): int
    {
        return $this->status;
    }
    
    public function setStatus(int $status): self
    {
        $this->status = $status;
        return $this;
    }
    
    public function isDone(): bool
    {
        return $this->isDone;
    }
    
    public function setIsDone(bool $isDone): self
    {
        $this->isDone = $isDone;
        return $this;
    }
    
    public function isBacklog(): bool
    {
        return $this->isBacklog;
    }
    
    public function setIsBacklog(bool $isBacklog): self
    {
        $this->isBacklog = $isBacklog;
        return $this;
    }
    
    /**
     * @return KanbanTask[]
     */
    public function getTasks(): array
    {
        return $this->tasks;
    }
    
    /**
     * @param KanbanTask[] $tasks
     */
    public function setTasks(array $tasks): self
    {
        $this->tasks = $tasks;
        return $this;
    }
    
    public function addTask(KanbanTask $task): self
    {
        $this->tasks[] = $task;
        return $this;
    }
    
    /**
     * Retorna contagem de tarefas na coluna
     */
    public function getTaskCount(): int
    {
        return count($this->tasks);
    }
    
    /**
     * Calcula progresso médio das tarefas na coluna
     */
    public function getAverageProgress(): float
    {
        if (empty($this->tasks)) {
            return 0.0;
        }
        
        $total = 0;
        foreach ($this->tasks as $task) {
            $total += $task->getTask()?->getPercentComplete() ?? 0;
        }
        
        return round($total / count($this->tasks), 1);
    }
    
    /**
     * Conta tarefas atrasadas na coluna
     */
    public function getOverdueCount(): int
    {
        $count = 0;
        foreach ($this->tasks as $task) {
            if ($task->getTask()?->isOverdue()) {
                $count++;
            }
        }
        return $count;
    }
    
    /**
     * Converte para array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'board_id' => $this->boardId,
            'name' => $this->name,
            'color' => $this->color,
            'order' => $this->order,
            'wip_limit' => $this->wipLimit,
            'is_at_wip_limit' => $this->isAtWipLimit(),
            'remaining_wip_slots' => $this->getRemainingWipSlots(),
            'status' => $this->status,
            'is_done' => $this->isDone,
            'is_backlog' => $this->isBacklog,
            'task_count' => $this->getTaskCount(),
            'average_progress' => $this->getAverageProgress(),
            'overdue_count' => $this->getOverdueCount(),
            'tasks' => array_map(fn($task) => $task->toArray(), $this->tasks),
        ];
    }
}
