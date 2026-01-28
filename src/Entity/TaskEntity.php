<?php
/**
 * Entity Task - Modern
 * 
 * @package DotProject\Entity
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Entity;

use DateTime;

class TaskEntity
{
    private ?int $id = null;
    private string $name;
    private ?string $description = null;
    private int $projectId;
    private ?int $parentTaskId = null;
    private ?int $assignedTo = null;
    private int $ownerId;
    private int $status = 0; // 0=ativo, 1=completado, -1=inativo
    private int $priority = 3; // 1=baixa, 2=média, 3=alta, 4=urgente
    private int $percentComplete = 0;
    private ?float $estimatedHours = null;
    private ?float $actualHours = null;
    private ?DateTime $startDate = null;
    private ?DateTime $endDate = null;
    private ?DateTime $actualEndDate = null;
    private ?DateTime $createdAt = null;
    private ?DateTime $updatedAt = null;

    // Getters e Setters
    public function getId(): ?int { return $this->id; }
    public function setId(int $id): self { $this->id = $id; return $this; }

    public function getName(): string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): self { $this->description = $description; return $this; }

    public function getProjectId(): int { return $this->projectId; }
    public function setProjectId(int $projectId): self { $this->projectId = $projectId; return $this; }

    public function getParentTaskId(): ?int { return $this->parentTaskId; }
    public function setParentTaskId(?int $parentTaskId): self { $this->parentTaskId = $parentTaskId; return $this; }

    public function getAssignedTo(): ?int { return $this->assignedTo; }
    public function setAssignedTo(?int $assignedTo): self { $this->assignedTo = $assignedTo; return $this; }

    public function getOwnerId(): int { return $this->ownerId; }
    public function setOwnerId(int $ownerId): self { $this->ownerId = $ownerId; return $this; }

    public function getStatus(): int { return $this->status; }
    public function setStatus(int $status): self { $this->status = $status; return $this; }

    public function isActive(): bool { return $this->status === 0; }
    public function isCompleted(): bool { return $this->status === 1; }
    public function isInactive(): bool { return $this->status === -1; }

    public function getPriority(): int { return $this->priority; }
    public function setPriority(int $priority): self { $this->priority = $priority; return $this; }

    public function getPercentComplete(): int { return $this->percentComplete; }
    public function setPercentComplete(int $percentComplete): self { 
        $this->percentComplete = max(0, min(100, $percentComplete)); 
        return $this; 
    }

    public function getEstimatedHours(): ?float { return $this->estimatedHours; }
    public function setEstimatedHours(?float $estimatedHours): self { $this->estimatedHours = $estimatedHours; return $this; }

    public function getActualHours(): ?float { return $this->actualHours; }
    public function setActualHours(?float $actualHours): self { $this->actualHours = $actualHours; return $this; }

    public function getStartDate(): ?DateTime { return $this->startDate; }
    public function setStartDate(?DateTime $startDate): self { $this->startDate = $startDate; return $this; }

    public function getEndDate(): ?DateTime { return $this->endDate; }
    public function setEndDate(?DateTime $endDate): self { $this->endDate = $endDate; return $this; }

    public function getActualEndDate(): ?DateTime { return $this->actualEndDate; }
    public function setActualEndDate(?DateTime $actualEndDate): self { $this->actualEndDate = $actualEndDate; return $this; }

    public function getCreatedAt(): ?DateTime { return $this->createdAt; }
    public function setCreatedAt(?DateTime $createdAt): self { $this->createdAt = $createdAt; return $this; }

    public function getUpdatedAt(): ?DateTime { return $this->updatedAt; }
    public function setUpdatedAt(?DateTime $updatedAt): self { $this->updatedAt = $updatedAt; return $this; }

    /**
     * Verifica se a tarefa está atrasada
     */
    public function isOverdue(): bool
    {
        if ($this->endDate === null || $this->isCompleted()) {
            return false;
        }
        return new DateTime() > $this->endDate;
    }

    /**
     * Retorna dias restantes ou dias de atraso
     */
    public function getDaysRemaining(): int
    {
        if ($this->endDate === null) {
            return 0;
        }
        $now = new DateTime();
        $interval = $now->diff($this->endDate);
        return $this->isOverdue() ? -$interval->days : $interval->days;
    }

    /**
     * Converte para array
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'project_id' => $this->projectId,
            'parent_task_id' => $this->parentTaskId,
            'assigned_to' => $this->assignedTo,
            'owner_id' => $this->ownerId,
            'status' => $this->status,
            'priority' => $this->priority,
            'percent_complete' => $this->percentComplete,
            'estimated_hours' => $this->estimatedHours,
            'actual_hours' => $this->actualHours,
            'start_date' => $this->startDate?->format('Y-m-d'),
            'end_date' => $this->endDate?->format('Y-m-d'),
            'actual_end_date' => $this->actualEndDate?->format('Y-m-d'),
            'is_active' => $this->isActive(),
            'is_completed' => $this->isCompleted(),
            'is_overdue' => $this->isOverdue(),
            'days_remaining' => $this->getDaysRemaining(),
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s'),
        ];
    }
}
