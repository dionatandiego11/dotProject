<?php
/**
 * DotProject Project Entity
 * 
 * Modern entity class for Project management.
 * 
 * @package DotProject\Entity
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Entity;

/**
 * Project Entity
 * 
 * @property string|null $project_name
 * @property string|null $project_short_name
 * @property int|null $project_company
 * @property int|null $project_owner
 * @property int|null $project_status
 * @property string|null $project_description
 * @property string|null $project_start_date
 * @property string|null $project_end_date
 * @property int $project_percent_complete
 * @property string $project_color_identifier
 * @property int $project_priority
 */
class Project extends BaseEntity
{
    public static function getTable(): string
    {
        return 'projects';
    }

    public static function getPrimaryKey(): string
    {
        return 'project_id';
    }

    protected static function getFillable(): array
    {
        return [
            'project_company',
            'project_company_internal',
            'project_department',
            'project_name',
            'project_short_name',
            'project_owner',
            'project_url',
            'project_demo_url',
            'project_start_date',
            'project_end_date',
            'project_status',
            'project_percent_complete',
            'project_color_identifier',
            'project_description',
            'project_target_budget',
            'project_actual_budget',
            'project_creator',
            'project_private',
            'project_departments',
            'project_contacts',
            'project_priority',
            'project_type',
        ];
    }

    /**
     * Get project name
     */
    public function getName(): ?string
    {
        return $this->getAttribute('project_name');
    }

    /**
     * Set project name
     */
    public function setName(string $name): static
    {
        return $this->setAttribute('project_name', $name);
    }

    /**
     * Get project status
     */
    public function getStatus(): int
    {
        return (int) ($this->getAttribute('project_status') ?? 0);
    }

    /**
     * Set project status
     */
    public function setStatus(int $status): static
    {
        return $this->setAttribute('project_status', $status);
    }

    /**
     * Get project owner ID
     */
    public function getOwnerId(): ?int
    {
        $owner = $this->getAttribute('project_owner');
        return $owner !== null ? (int) $owner : null;
    }

    /**
     * Get project completion percentage
     */
    public function getPercentComplete(): int
    {
        return (int) ($this->getAttribute('project_percent_complete') ?? 0);
    }

    /**
     * Check if project is complete
     */
    public function isComplete(): bool
    {
        return $this->getPercentComplete() >= 100;
    }

    /**
     * Check if project is active (status = 3)
     */
    public function isActive(): bool
    {
        return $this->getStatus() === 3;
    }

    /**
     * Find projects by company
     * 
     * @param int $companyId
     * @return array<int, static>
     */
    public static function findByCompany(int $companyId): array
    {
        return static::findAll(
            sprintf('project_company = %d', $companyId),
            'project_name ASC'
        );
    }

    /**
     * Find projects by owner
     * 
     * @param int $ownerId
     * @return array<int, static>
     */
    public static function findByOwner(int $ownerId): array
    {
        return static::findAll(
            sprintf('project_owner = %d', $ownerId),
            'project_name ASC'
        );
    }

    /**
     * Find active projects
     * 
     * @return array<int, static>
     */
    public static function findActive(): array
    {
        return static::findAll('project_status = 3', 'project_name ASC');
    }
}
