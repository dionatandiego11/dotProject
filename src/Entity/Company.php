<?php
/**
 * DotProject Company Entity
 * 
 * Modern entity class for Company management.
 * 
 * @package DotProject\Entity
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Entity;

/**
 * Company Entity
 * 
 * @property string $company_name
 * @property string|null $company_phone1
 * @property string|null $company_phone2
 * @property string|null $company_fax
 * @property string|null $company_address1
 * @property string|null $company_address2
 * @property string|null $company_city
 * @property string|null $company_state
 * @property string|null $company_zip
 * @property string|null $company_email
 * @property string|null $company_primary_url
 * @property int|null $company_owner
 * @property string|null $company_description
 * @property int|null $company_type
 * @property mixed $company_custom
 */
class Company extends BaseEntity
{
    public static function getTable(): string
    {
        return 'companies';
    }

    public static function getPrimaryKey(): string
    {
        return 'company_id';
    }

    protected static function getFillable(): array
    {
        return [
            'company_name',
            'company_phone1',
            'company_phone2',
            'company_fax',
            'company_address1',
            'company_address2',
            'company_city',
            'company_state',
            'company_zip',
            'company_email',
            'company_primary_url',
            'company_owner',
            'company_description',
            'company_type',
            'company_custom'
        ];
    }

    /**
     * Get company name
     */
    public function getName(): string
    {
        return (string) $this->getAttribute('company_name');
    }

    /**
     * Set company name
     */
    public function setName(string $name): static
    {
        return $this->setAttribute('company_name', $name);
    }

    /**
     * Get description
     */
    public function getDescription(): string
    {
        return (string) ($this->getAttribute('company_description') ?? '');
    }

    /**
     * Get owner ID
     */
    public function getOwnerId(): ?int
    {
        $owner = $this->getAttribute('company_owner');
        return $owner !== null ? (int) $owner : null;
    }

    /**
     * Get email
     */
    public function getEmail(): ?string
    {
        return $this->getAttribute('company_email');
    }

    /**
     * Find active companies
     * 
     * @return array<int, static>
     */
    public static function findActive(): array
    {
        // Assuming there isn't a simple "active" flag but relying on business logic
        // For now returning all, or we could filter by specific types if we knew them
        return static::findAll(null, 'company_name ASC');
    }
}
