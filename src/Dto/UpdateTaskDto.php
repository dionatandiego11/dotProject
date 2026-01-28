<?php
/**
 * Update Task DTO
 * 
 * DTO para atualizacao de tarefas.
 * 
 * @package DotProject\Dto
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Dto;

/**
 * DTO para atualizacao de tarefa
 */
class UpdateTaskDto extends BaseDto
{
    public ?string $name = null;
    public ?string $description = null;
    public ?int $assignedTo = null;
    public ?int $status = null;
    public ?int $priority = null;
    public ?int $percentComplete = null;
    public ?float $estimatedHours = null;
    public ?float $actualHours = null;
    public ?string $startDate = null;
    public ?string $endDate = null;
    public ?string $actualEndDate = null;
    
    /**
     * Valida os dados
     */
    public function validate(): array
    {
        $errors = [];
        
        if ($this->name !== null) {
            if (strlen($this->name) < 2) {
                $errors['name'] = 'Task name must be at least 2 characters';
            } elseif (strlen($this->name) > 255) {
                $errors['name'] = 'Task name must be at most 255 characters';
            }
        }
        
        if ($this->startDate !== null && !$this->isValidDate($this->startDate)) {
            $errors['start_date'] = 'Invalid start date format (YYYY-MM-DD)';
        }
        
        if ($this->endDate !== null && !$this->isValidDate($this->endDate)) {
            $errors['end_date'] = 'Invalid end date format (YYYY-MM-DD)';
        }
        
        if ($this->startDate !== null && $this->endDate !== null) {
            if ($this->endDate < $this->startDate) {
                $errors['end_date'] = 'End date must be after start date';
            }
        }
        
        if ($this->percentComplete !== null && ($this->percentComplete < 0 || $this->percentComplete > 100)) {
            $errors['percent_complete'] = 'Percent complete must be between 0 and 100';
        }
        
        if ($this->estimatedHours !== null && $this->estimatedHours < 0) {
            $errors['estimated_hours'] = 'Estimated hours cannot be negative';
        }
        
        if ($this->actualHours !== null && $this->actualHours < 0) {
            $errors['actual_hours'] = 'Actual hours cannot be negative';
        }
        
        return $errors;
    }
    
    /**
     * Verifica se ha campos para atualizar
     */
    public function hasChanges(): bool
    {
        return count(array_filter(get_object_vars($this), fn($v) => $v !== null)) > 0;
    }
    
    /**
     * Valida formato de data
     */
    private function isValidDate(string $date): bool
    {
        $d = \DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }
}
