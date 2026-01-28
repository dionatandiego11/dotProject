<?php
/**
 * Project Event
 * 
 * Evento de dominio para projetos.
 * 
 * @package DotProject\Event
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Event;

use DotProject\Core\Event;
use DotProject\Entity\ProjectEntity;

/**
 * Evento relacionado a projetos
 */
class ProjectEvent extends Event
{
    private ProjectEntity $project;
    private ?int $userId;
    private ?array $changedFields;
    
    public function __construct(
        string $name, 
        ProjectEntity $project, 
        ?int $userId = null,
        ?array $changedFields = null
    ) {
        parent::__construct($name);
        $this->project = $project;
        $this->userId = $userId;
        $this->changedFields = $changedFields;
        
        $this->setData([
            'project_id' => $project->getId(),
            'project_name' => $project->getName(),
            'project_status' => $project->getStatus(),
            'user_id' => $userId,
            'changed_fields' => $changedFields,
            'timestamp' => time(),
        ]);
    }
    
    public function getProject(): ProjectEntity
    {
        return $this->project;
    }
    
    public function getUserId(): ?int
    {
        return $this->userId;
    }
    
    public function getChangedFields(): ?array
    {
        return $this->changedFields;
    }
    
    public function getProjectId(): ?int
    {
        return $this->project->getId();
    }
    
    public function getProjectName(): string
    {
        return $this->project->getName();
    }
    
    public function wasChanged(string $field): bool
    {
        if ($this->changedFields === null) {
            return false;
        }
        return in_array($field, $this->changedFields, true);
    }
}
