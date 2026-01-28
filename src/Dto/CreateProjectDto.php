<?php
/**
 * Create Project DTO
 * 
 * DTO para criacao de projetos.
 * 
 * @package DotProject\Dto
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Dto;

/**
 * DTO para criacao de projeto
 */
class CreateProjectDto extends BaseDto
{
    public string $name;
    public ?string $shortName = null;
    public ?string $description = null;
    public ?string $startDate = null;
    public ?string $endDate = null;
    public int $status = 0;
    public int $priority = 3;
    public ?int $companyId = null;
    public ?string $colorIdentifier = null;
    public ?string $url = null;
    
    /**
     * Valida os dados
     */
    public function validate(): array
    {
        $errors = [];
        
        if (empty($this->name)) {
            $errors['name'] = 'Project name is required';
        } elseif (strlen($this->name) < 3) {
            $errors['name'] = 'Project name must be at least 3 characters';
        } elseif (strlen($this->name) > 255) {
            $errors['name'] = 'Project name must be at most 255 characters';
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
        
        if ($this->status < 0 || $this->status > 7) {
            $errors['status'] = 'Invalid status value';
        }
        
        if ($this->priority < -1 || $this->priority > 5) {
            $errors['priority'] = 'Invalid priority value';
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
