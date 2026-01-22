<?php
/**
 * DotProject Task Entity
 * 
 * Modern entity class for Task management.
 * 
 * @package DotProject\Entity
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Entity;

/**
 * Task Entity
 * 
 * @property string $task_name
 * @property int|null $task_parent
 * @property int|null $task_milestone
 * @property int $task_project
 * @property int $task_owner
 * @property string|null $task_start_date
 * @property float|null $task_duration
 * @property int|null $task_duration_type
 * @property float|null $task_hours_worked
 * @property string|null $task_end_date
 * @property int|null $task_status
 * @property int|null $task_priority
 * @property int|null $task_percent_complete
 * @property string|null $task_description
 * @property float|null $task_target_budget
 * @property string|null $task_related_url
 * @property int|null $task_creator
 * @property int|null $task_order
 * @property int|null $task_client_publish
 * @property int|null $task_dynamic
 * @property int|null $task_access
 * @property int|null $task_notify
 * @property string|null $task_departments
 * @property string|null $task_contacts
 * @property mixed $task_custom
 * @property int|null $task_type
 */
class Task extends BaseEntity
{
    public static function getTable(): string
    {
        return 'tasks';
    }

    public static function getPrimaryKey(): string
    {
        return 'task_id';
    }

    protected static function getFillable(): array
    {
        return [
            'task_name',
            'task_parent',
            'task_milestone',
            'task_project',
            'task_owner',
            'task_start_date',
            'task_duration',
            'task_duration_type',
            'task_hours_worked',
            'task_end_date',
            'task_status',
            'task_priority',
            'task_percent_complete',
            'task_description',
            'task_target_budget',
            'task_related_url',
            'task_creator',
            'task_order',
            'task_client_publish',
            'task_dynamic',
            'task_access',
            'task_notify',
            'task_departments',
            'task_contacts',
            'task_custom',
            'task_type'
        ];
    }

    /**
     * Get task name
     */
    public function getName(): string
    {
        return (string) $this->getAttribute('task_name');
    }

    /**
     * Set task name
     */
    public function setName(string $name): static
    {
        return $this->setAttribute('task_name', $name);
    }

    /**
     * Get project ID
     */
    public function getProjectId(): int
    {
        return (int) $this->getAttribute('task_project');
    }

    /**
     * Get start date
     */
    public function getStartDate(): ?string
    {
        return $this->getAttribute('task_start_date');
    }

    /**
     * Get end date
     */
    public function getEndDate(): ?string
    {
        return $this->getAttribute('task_end_date');
    }

    /**
     * Get percent complete
     */
    public function getPercentComplete(): int
    {
        return (int) ($this->getAttribute('task_percent_complete') ?? 0);
    }

    /**
     * Is milestone?
     */
    public function isMilestone(): bool
    {
        return (bool) $this->getAttribute('task_milestone');
    }
}
