<?php
/**
 * Kanban Board Entity
 * 
 * Entidade para quadros Kanban.
 * 
 * @package DotProject\Entity
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Entity;

use DateTime;

/**
 * Entidade KanbanBoard
 */
class KanbanBoard
{
    private ?int $id = null;
    private string $name;
    private ?string $description = null;
    private ?int $projectId = null;  // null = global/board independente
    private int $companyId;
    private ?int $createdBy = null;
    private int $status = 0;  // 0=ativo, 1=arquivado
    private ?DateTime $createdAt = null;
    private ?DateTime $updatedAt = null;
    
    /** @var KanbanColumn[] */
    private array $columns = [];
    
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
    
    public function getDescription(): ?string
    {
        return $this->description;
    }
    
    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }
    
    public function getProjectId(): ?int
    {
        return $this->projectId;
    }
    
    public function setProjectId(?int $projectId): self
    {
        $this->projectId = $projectId;
        return $this;
    }
    
    public function getCompanyId(): int
    {
        return $this->companyId;
    }
    
    public function setCompanyId(int $companyId): self
    {
        $this->companyId = $companyId;
        return $this;
    }
    
    public function getCreatedBy(): ?int
    {
        return $this->createdBy;
    }
    
    public function setCreatedBy(?int $createdBy): self
    {
        $this->createdBy = $createdBy;
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
     * @return KanbanColumn[]
     */
    public function getColumns(): array
    {
        return $this->columns;
    }
    
    /**
     * @param KanbanColumn[] $columns
     */
    public function setColumns(array $columns): self
    {
        $this->columns = $columns;
        return $this;
    }
    
    public function addColumn(KanbanColumn $column): self
    {
        $this->columns[] = $column;
        return $this;
    }
    
    /**
     * Retorna contagem de tarefas no board
     */
    public function getTotalTasks(): int
    {
        $total = 0;
        foreach ($this->columns as $column) {
            $total += count($column->getTasks());
        }
        return $total;
    }
    
    /**
     * Converte para array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'project_id' => $this->projectId,
            'company_id' => $this->companyId,
            'created_by' => $this->createdBy,
            'status' => $this->status,
            'is_active' => $this->isActive(),
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s'),
            'columns' => array_map(fn($col) => $col->toArray(), $this->columns),
            'total_tasks' => $this->getTotalTasks(),
        ];
    }
}
