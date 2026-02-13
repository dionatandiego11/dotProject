<?php
/**
 * Entidade Tarefa (extensão da dotp_tasks)
 * Nível 4 - Execução Micro
 * 
 * @package DotProject\Entity
 */

declare(strict_types=1);

namespace DotProject\Entity;

use DateTime;

class TaskEntity
{
    // Campos existentes da dotp_tasks
    private ?int $id = null;
    private string $name;
    private ?string $description = null;
    private int $projectId;
    private ?ProjetoEntity $project = null;
    private int $ownerId = 0;
    private ?int $parentTaskId = null;
    private ?int $assignedTo = null;
    private ?DateTime $startDate = null;
    private ?DateTime $endDate = null;
    private ?DateTime $actualEndDate = null;
    private int $priority = 1; // 0=baixa, 1=normal, 2=alta, 3=urgente
    private int $status = 0;
    private int $percentComplete = 0;
    private ?float $estimatedHours = null;
    private ?float $actualHours = null;

    // Novos campos do PPA
    private ?int $etapaId = null; // FK opcional para etapa
    private string $estado = 'A_Fazer'; // Backlog, A_Fazer, Em_Andamento, Pausada, Bloqueada, Em_Revisao, Concluida, Cancelada
    private ?string $evidenciaAnexo = null; // URL do arquivo
    private float $peso = 1.00;
    private ?DateTime $createdAt = null;
    private ?DateTime $updatedAt = null;

    public function getPeso(): float
    {
        return $this->peso;
    }

    public function setPeso(float $peso): self
    {
        if ($peso <= 0) {
            throw new \InvalidArgumentException('Peso deve ser maior que zero.');
        }
        $this->peso = $peso;
        return $this;
    }

    public function __construct()
    {
        $this->createdAt = new DateTime();
    }

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

    public function getProjectId(): int
    {
        return $this->projectId;
    }

    public function setProjectId(int $id): self
    {
        $this->projectId = $id;
        return $this;
    }

    public function getProject(): ?ProjetoEntity
    {
        return $this->project;
    }

    public function setProject(?ProjetoEntity $project): self
    {
        $this->project = $project;
        if ($project) {
            $this->projectId = $project->getId() ?? 0;
        }
        return $this;
    }

    public function getAssignedTo(): ?int
    {
        return $this->assignedTo;
    }

    public function setAssignedTo(?int $userId): self
    {
        $this->assignedTo = $userId;
        return $this;
    }

    public function getParentTaskId(): ?int
    {
        return $this->parentTaskId;
    }

    public function setParentTaskId(?int $parentTaskId): self
    {
        $this->parentTaskId = $parentTaskId;
        return $this;
    }

    public function getOwnerId(): int
    {
        return $this->ownerId;
    }

    public function setOwnerId(int $ownerId): self
    {
        $this->ownerId = $ownerId;
        return $this;
    }

    public function getEtapaId(): ?int
    {
        return $this->etapaId;
    }

    public function setEtapaId(?int $id): self
    {
        $this->etapaId = $id;
        return $this;
    }

    public function getEstado(): string
    {
        return $this->estado;
    }

    public function setEstado(string $estado): self
    {
        $this->estado = $estado;
        $this->updatedAt = new DateTime();

        // Atualiza percentual baseado no estado
        $this->percentComplete = match ($estado) {
            'Concluida' => 100,
            'Em_Andamento' => max(50, $this->percentComplete),
            'A_Fazer', 'Backlog' => 0,
            default => $this->percentComplete,
        };

        return $this;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): self
    {
        $this->priority = max(0, min(4, $priority));
        return $this;
    }

    public function getPriorityLabel(): string
    {
        return match ($this->priority) {
            0 => 'Baixa',
            2 => 'Alta',
            3 => 'Urgente',
            default => 'Normal',
        };
    }

    public function getEndDate(): ?DateTime
    {
        return $this->endDate;
    }

    public function getStartDate(): ?DateTime
    {
        return $this->startDate;
    }

    public function setStartDate(?DateTime $date): self
    {
        $this->startDate = $date;
        return $this;
    }

    public function setEndDate(?DateTime $date): self
    {
        $this->endDate = $date;
        return $this;
    }

    public function getActualEndDate(): ?DateTime
    {
        return $this->actualEndDate;
    }

    public function setActualEndDate(?DateTime $date): self
    {
        $this->actualEndDate = $date;
        return $this;
    }

    public function getEstimatedHours(): ?float
    {
        return $this->estimatedHours;
    }

    public function setEstimatedHours(?float $hours): self
    {
        $this->estimatedHours = $hours;
        return $this;
    }

    public function getActualHours(): ?float
    {
        return $this->actualHours;
    }

    public function setActualHours(?float $hours): self
    {
        $this->actualHours = $hours;
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

    public function getPercentComplete(): int
    {
        return $this->percentComplete;
    }

    public function setPercentComplete(int $percent): self
    {
        $this->percentComplete = max(0, min(100, $percent));
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

    public function getEvidenciaAnexo(): ?string
    {
        return $this->evidenciaAnexo;
    }

    public function setEvidenciaAnexo(?string $url): self
    {
        $this->evidenciaAnexo = $url;
        return $this;
    }

    public function isConcluida(): bool
    {
        return $this->estado === 'Concluida';
    }

    public function isAtrasada(): bool
    {
        if ($this->isConcluida() || $this->isCompleted() || !$this->endDate) {
            return false;
        }
        return new DateTime() > $this->endDate;
    }

    public function isCompleted(): bool
    {
        return $this->percentComplete >= 100 || $this->status === 1 || $this->estado === 'Concluida';
    }

    public function isOverdue(): bool
    {
        return $this->isAtrasada();
    }

    public function isActive(): bool
    {
        return !$this->isCompleted() && $this->status === 0;
    }

    public function getDaysRemaining(): ?int
    {
        if ($this->endDate === null) {
            return null;
        }

        $today = new DateTime('today');
        $target = clone $this->endDate;
        $target->setTime(0, 0, 0);
        $diff = $today->diff($target);

        return $diff->invert ? -$diff->days : $diff->days;
    }

    /**
     * Inicia execução da tarefa
     */
    public function iniciar(): void
    {
        $this->estado = 'Em_Andamento';
        if ($this->percentComplete < 50) {
            $this->percentComplete = 50;
        }
        $this->updatedAt = new DateTime();
    }

    /**
     * Conclui a tarefa
     */
    public function concluir(?string $evidencia = null): void
    {
        $this->estado = 'Concluida';
        $this->percentComplete = 100;
        $this->evidenciaAnexo = $evidencia ?? $this->evidenciaAnexo;
        $this->updatedAt = new DateTime();

        // Atualiza last_update do projeto
        if ($this->project) {
            $this->project->touch();
        }
    }

    /**
     * Bloqueia a tarefa
     */
    public function bloquear(string $motivo): void
    {
        $this->estado = 'Bloqueada';
        $this->updatedAt = new DateTime();
        // TODO: Registrar motivo do bloqueio em tabela separada
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->name,
            'descricao' => $this->description,
            'projeto_id' => $this->projectId,
            'etapa_id' => $this->etapaId,
            'responsavel_id' => $this->assignedTo,
            'estado' => $this->estado,
            'prioridade' => $this->priority,
            'prioridade_label' => $this->getPriorityLabel(),
            'percent_concluido' => $this->percentComplete,
            'data_fim' => $this->endDate?->format('Y-m-d'),
            'atrasada' => $this->isAtrasada(),
            'evidencia_url' => $this->evidenciaAnexo,
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s'),
            // Compatibilidade com formato legado (dotp_tasks)
            'task_id' => $this->id,
            'task_name' => $this->name,
            'task_description' => $this->description,
            'task_project' => $this->projectId,
            'task_parent' => $this->parentTaskId,
            'task_owner' => $this->ownerId,
            'task_assigned_to' => $this->assignedTo,
            'task_status' => $this->status,
            'task_priority' => $this->priority,
            'task_percent_complete' => $this->percentComplete,
            'task_hours' => $this->estimatedHours,
            'task_actual_hours' => $this->actualHours,
            'task_start_date' => $this->startDate?->format('Y-m-d'),
            'task_end_date' => $this->endDate?->format('Y-m-d'),
            'task_actual_end_date' => $this->actualEndDate?->format('Y-m-d'),
        ];
    }
}
