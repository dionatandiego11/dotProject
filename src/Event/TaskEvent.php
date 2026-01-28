<?php
/**
 * Task Event
 * 
 * Evento de dominio para tarefas.
 * 
 * @package DotProject\Event
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Event;

use DotProject\Core\Event;
use DotProject\Entity\TaskEntity;

/**
 * Evento relacionado a tarefas
 */
class TaskEvent extends Event
{
    private TaskEntity $task;
    private ?int $userId;
    private ?array $changedFields;
    
    public function __construct(
        string $name, 
        TaskEntity $task, 
        ?int $userId = null,
        ?array $changedFields = null
    ) {
        parent::__construct($name);
        $this->task = $task;
        $this->userId = $userId;
        $this->changedFields = $changedFields;
        
        $this->setData([
            'task_id' => $task->getId(),
            'task_name' => $task->getName(),
            'project_id' => $task->getProjectId(),
            'task_status' => $task->getStatus(),
            'task_percent_complete' => $task->getPercentComplete(),
            'user_id' => $userId,
            'changed_fields' => $changedFields,
            'timestamp' => time(),
        ]);
    }
    
    public function getTask(): TaskEntity
    {
        return $this->task;
    }
    
    public function getUserId(): ?int
    {
        return $this->userId;
    }
    
    public function getChangedFields(): ?array
    {
        return $this->changedFields;
    }
    
    public function getTaskId(): ?int
    {
        return $this->task->getId();
    }
    
    public function getProjectId(): int
    {
        return $this->task->getProjectId();
    }
    
    public function wasChanged(string $field): bool
    {
        if ($this->changedFields === null) {
            return false;
        }
        return in_array($field, $this->changedFields, true);
    }
    
    public function isCompleted(): bool
    {
        return $this->task->isCompleted();
    }
    
    public function isOverdue(): bool
    {
        return $this->task->isOverdue();
    }
}
