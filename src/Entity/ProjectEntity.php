<?php
/**
 * Entidade Project (Moderna)
 * 
 * Versão moderna da entidade Project com tipagem.
 * 
 * @package DotProject\Entity
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Entity;

use DateTime;

/**
 * Entidade Project
 */
class ProjectEntity
{
    private ?int $id = null;
    private string $name;
    private ?string $shortName = null;
    private ?string $description = null;
    private ?DateTime $startDate = null;
    private ?DateTime $endDate = null;
    private ?DateTime $actualEndDate = null;
    private int $status = 0; // 0 = ativo, 1 = arquivado, etc
    private int $priority = 3; // 1 = baixa, 2 = média, 3 = alta, 4 = urgente
    private int $percentComplete = 0;
    private ?int $ownerId = null;
    private ?int $companyId = null;
    private ?string $colorIdentifier = null;
    private ?string $url = null;
    private ?DateTime $createdAt = null;
    private ?DateTime $updatedAt = null;

    // Getters e Setters

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;
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

    public function getShortName(): ?string
    {
        return $this->shortName;
    }

    public function setShortName(?string $shortName): self
    {
        $this->shortName = $shortName;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getStartDate(): ?DateTime
    {
        return $this->startDate;
    }

    public function setStartDate(?DateTime $startDate): self
    {
        $this->startDate = $startDate;
        return $this;
    }

    public function getEndDate(): ?DateTime
    {
        return $this->endDate;
    }

    public function setEndDate(?DateTime $endDate): self
    {
        $this->endDate = $endDate;
        return $this;
    }

    public function getActualEndDate(): ?DateTime
    {
        return $this->actualEndDate;
    }

    public function setActualEndDate(?DateTime $actualEndDate): self
    {
        $this->actualEndDate = $actualEndDate;
        return $this;
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

    public function isActive(): bool
    {
        return $this->status === 0;
    }

    public function isCompleted(): bool
    {
        return $this->status === 1;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): self
    {
        $this->priority = $priority;
        return $this;
    }

    public function getPercentComplete(): int
    {
        return $this->percentComplete;
    }

    public function setPercentComplete(int $percentComplete): self
    {
        $this->percentComplete = $percentComplete;
        return $this;
    }

    public function getOwnerId(): ?int
    {
        return $this->ownerId;
    }

    public function setOwnerId(?int $ownerId): self
    {
        $this->ownerId = $ownerId;
        return $this;
    }

    public function getCompanyId(): ?int
    {
        return $this->companyId;
    }

    public function setCompanyId(?int $companyId): self
    {
        $this->companyId = $companyId;
        return $this;
    }

    public function getColorIdentifier(): ?string
    {
        return $this->colorIdentifier;
    }

    public function setColorIdentifier(?string $colorIdentifier): self
    {
        $this->colorIdentifier = $colorIdentifier;
        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): self
    {
        $this->url = $url;
        return $this;
    }

    public function getCreatedAt(): ?DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?DateTime $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?DateTime $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    /**
     * Calcula se o projeto está atrasado
     */
    public function isOverdue(): bool
    {
        if ($this->endDate === null) {
            return false;
        }

        $now = new DateTime();
        return $now > $this->endDate && !$this->isCompleted();
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
     * 
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'short_name' => $this->shortName,
            'description' => $this->description,
            'start_date' => $this->startDate?->format('Y-m-d'),
            'end_date' => $this->endDate?->format('Y-m-d'),
            'actual_end_date' => $this->actualEndDate?->format('Y-m-d'),
            'status' => $this->status,
            'priority' => $this->priority,
            'percent_complete' => $this->percentComplete,
            'owner_id' => $this->ownerId,
            'company_id' => $this->companyId,
            'color_identifier' => $this->colorIdentifier,
            'url' => $this->url,
            'is_active' => $this->isActive(),
            'is_completed' => $this->isCompleted(),
            'is_overdue' => $this->isOverdue(),
            'days_remaining' => $this->getDaysRemaining(),
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s'),
        ];
    }
}
