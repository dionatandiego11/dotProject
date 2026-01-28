<?php
/**
 * Task Assignment Event
 * 
 * Evento de dominio para atribuicao de tarefas.
 * 
 * @package DotProject\Event
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Event;

use DotProject\Core\Event;
use DotProject\Entity\TaskEntity;

/**
 * Evento de atribuicao de tarefa
 */
class TaskAssignmentEvent extends Event
{
    private TaskEntity $task;
    private int $assignedTo;
    private ?int $assignedBy;
    private int $percent;
    
    public function __construct(
        string $name,
        TaskEntity $task,
        int $assignedTo,
        ?int $assignedBy = null,
        int $percent = 100
    ) {
        parent::__construct($name);
        $this->task = $task;
        $this->assignedTo = $assignedTo;
        $this->assignedBy = $assignedBy;
        $this->percent = $percent;
        
        $this->setData([
            'task_id' => $task->getId(),
            'task_name' => $task->getName(),
            'project_id' => $task->getProjectId(),
            'assigned_to' => $assignedTo,
            'assigned_by' => $assignedBy,
            'percent' => $percent,
            'timestamp' => time(),
        ]);
    }
    
    public function getTask(): TaskEntity
    {
        return $this->task;
    }
    
    public function getAssignedTo(): int
    {
        return $this->assignedTo;
    }
    
    public function getAssignedBy(): ?int
    {
        return $this->assignedBy;
    }
    
    public function getPercent(): int
    {
        return $this->percent;
    }
    
    public function getTaskId(): ?int
    {
        return $this->task->getId();
    }
    
    public function getProjectId(): int
    {
        return $this->task->getProjectId();
    }
}
