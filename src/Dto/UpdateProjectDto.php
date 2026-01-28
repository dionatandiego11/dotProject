<?php
/**
 * Update Project DTO
 * 
 * DTO para atualizacao de projetos.
 * 
 * @package DotProject\Dto
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Dto;

/**
 * DTO para atualizacao de projeto
 */
class UpdateProjectDto extends BaseDto
{
    public ?string $name = null;
    public ?string $shortName = null;
    public ?string $description = null;
    public ?string $startDate = null;
    public ?string $endDate = null;
    public ?int $status = null;
    public ?int $priority = null;
    public ?int $companyId = null;
    public ?string $colorIdentifier = null;
    public ?string $url = null;
    public ?int $percentComplete = null;
    
    /**
     * Valida os dados
     */
    public function validate(): array
    {
        $errors = [];
        
        if ($this->name !== null) {
            if (strlen($this->name) < 3) {
                $errors['name'] = 'Project name must be at least 3 characters';
            } elseif (strlen($this->name) > 255) {
                $errors['name'] = 'Project name must be at most 255 characters';
            }
        }
        
        if ($this->shortName !== null && strlen($this->shortName) > 10) {
            $errors['short_name'] = 'Short name must be at most 10 characters';
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
        
        if ($this->status !== null && ($this->status < 0 || $this->status > 7)) {
            $errors['status'] = 'Invalid status value';
        }
        
        if ($this->priority !== null && ($this->priority < -1 || $this->priority > 5)) {
            $errors['priority'] = 'Invalid priority value';
        }
        
        if ($this->percentComplete !== null && ($this->percentComplete < 0 || $this->percentComplete > 100)) {
            $errors['percent_complete'] = 'Percent complete must be between 0 and 100';
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
