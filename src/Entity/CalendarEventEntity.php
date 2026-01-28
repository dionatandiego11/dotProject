<?php
/**
 * Entity CalendarEvent - Modern
 * 
 * @package DotProject\Entity
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Entity;

use DateTime;

class CalendarEventEntity
{
    private ?int $id = null;
    private string $title;
    private ?string $description = null;
    private int $userId;
    private ?int $projectId = null;
    private ?int $taskId = null;
    private DateTime $startDate;
    private ?DateTime $endDate = null;
    private bool $allDay = false;
    private ?string $color = null;
    private int $type = 0; // 0=meeting, 1=task, 2=reminder, etc
    private int $status = 0; // 0=ativo, 1=cancelado
    private ?DateTime $createdAt = null;
    private ?DateTime $updatedAt = null;

    public function getId(): ?int { return $this->id; }
    public function setId(int $id): self { $this->id = $id; return $this; }

    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): self { $this->title = $title; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): self { $this->description = $description; return $this; }

    public function getUserId(): int { return $this->userId; }
    public function setUserId(int $userId): self { $this->userId = $userId; return $this; }

    public function getProjectId(): ?int { return $this->projectId; }
    public function setProjectId(?int $projectId): self { $this->projectId = $projectId; return $this; }

    public function getTaskId(): ?int { return $this->taskId; }
    public function setTaskId(?int $taskId): self { $this->taskId = $taskId; return $this; }

    public function getStartDate(): DateTime { return $this->startDate; }
    public function setStartDate(DateTime $startDate): self { $this->startDate = $startDate; return $this; }

    public function getEndDate(): ?DateTime { return $this->endDate; }
    public function setEndDate(?DateTime $endDate): self { $this->endDate = $endDate; return $this; }

    public function isAllDay(): bool { return $this->allDay; }
    public function setAllDay(bool $allDay): self { $this->allDay = $allDay; return $this; }

    public function getColor(): ?string { return $this->color; }
    public function setColor(?string $color): self { $this->color = $color; return $this; }

    public function getType(): int { return $this->type; }
    public function setType(int $type): self { $this->type = $type; return $this; }

    public function getStatus(): int { return $this->status; }
    public function setStatus(int $status): self { $this->status = $status; return $this; }
    public function isActive(): bool { return $this->status === 0; }

    public function getCreatedAt(): ?DateTime { return $this->createdAt; }
    public function setCreatedAt(?DateTime $createdAt): self { $this->createdAt = $createdAt; return $this; }

    public function getUpdatedAt(): ?DateTime { return $this->updatedAt; }
    public function setUpdatedAt(?DateTime $updatedAt): self { $this->updatedAt = $updatedAt; return $this; }

    /**
     * Verifica se evento está no futuro
     */
    public function isFuture(): bool
    {
        return $this->startDate > new DateTime();
    }

    /**
     * Verifica se evento está no passado
     */
    public function isPast(): bool
    {
        $end = $this->endDate ?? $this->startDate;
        return $end < new DateTime();
    }

    /**
     * Verifica se evento é hoje
     */
    public function isToday(): bool
    {
        $today = new DateTime();
        return $this->startDate->format('Y-m-d') === $today->format('Y-m-d');
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'user_id' => $this->userId,
            'project_id' => $this->projectId,
            'task_id' => $this->taskId,
            'start_date' => $this->startDate->format('Y-m-d H:i:s'),
            'end_date' => $this->endDate?->format('Y-m-d H:i:s'),
            'all_day' => $this->allDay,
            'color' => $this->color,
            'type' => $this->type,
            'status' => $this->status,
            'is_active' => $this->isActive(),
            'is_future' => $this->isFuture(),
            'is_past' => $this->isPast(),
            'is_today' => $this->isToday(),
        ];
    }
}
