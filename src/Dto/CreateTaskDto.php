<?php
/**
 * Create Task DTO
 * 
 * DTO para criacao de tarefas.
 * 
 * @package DotProject\Dto
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Dto;

/**
 * DTO para criacao de tarefa
 */
class CreateTaskDto extends BaseDto
{
    public string $name;
    public int $projectId;
    public ?string $description = null;
    public ?int $parentTaskId = null;
    public ?int $assignedTo = null;
    public int $status = 0;
    public int $priority = 3;
    public ?float $estimatedHours = null;
    public ?string $startDate = null;
    public ?string $endDate = null;
    
    /**
     * Valida os dados
     */
    public function validate(): array
    {
        $errors = [];
        
        if (empty($this->name)) {
            $errors['name'] = 'Task name is required';
        } elseif (strlen($this->name) < 2) {
            $errors['name'] = 'Task name must be at least 2 characters';
        } elseif (strlen($this->name) > 255) {
            $errors['name'] = 'Task name must be at most 255 characters';
        }
        
        if (empty($this->projectId)) {
            $errors['project_id'] = 'Project ID is required';
        } elseif ($this->projectId <= 0) {
            $errors['project_id'] = 'Invalid project ID';
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
        
        if ($this->estimatedHours !== null && $this->estimatedHours < 0) {
            $errors['estimated_hours'] = 'Estimated hours cannot be negative';
        }
        
        return $errors;
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
