<?php
/**
 * DotProject Contact Entity
 * 
 * Modern entity class for Contact management.
 * 
 * @package DotProject\Entity
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Entity;

use DotProject\Core\Database;

/**
 * Contact Entity
 * 
 * @property string|null $contact_first_name
 * @property string|null $contact_last_name
 * @property int|null $contact_company
 * @property int|null $contact_department
 * @property string|null $contact_email
 * @property string|null $contact_phone
 * @property string|null $contact_mobile
 * @property string|null $contact_address1
 * @property string|null $contact_city
 * @property string|null $contact_state
 * @property string|null $contact_zip
 * @property string|null $contact_country
 * @property string|null $contact_notes
 * @property int $contact_owner
 */
class Contact extends BaseEntity
{
    public static function getTable(): string
    {
        return 'contacts';
    }

    public static function getPrimaryKey(): string
    {
        return 'contact_id';
    }

    protected static function getFillable(): array
    {
        return [
            'contact_first_name',
            'contact_last_name',
            'contact_company',
            'contact_department',
            'contact_title',
            'contact_job',
            'contact_email',
            'contact_email2',
            'contact_phone',
            'contact_phone2',
            'contact_mobile',
            'contact_fax',
            'contact_address1',
            'contact_address2',
            'contact_city',
            'contact_state',
            'contact_zip',
            'contact_country',
            'contact_notes',
            'contact_project',
            'contact_icon',
            'contact_owner',
            'contact_private',
        ];
    }

    /**
     * Get full name
     */
    public function getFullName(): string
    {
        $first = $this->getAttribute('contact_first_name') ?? '';
        $last = $this->getAttribute('contact_last_name') ?? '';
        return trim("{$first} {$last}");
    }

    /**
     * Get first name
     */
    public function getFirstName(): ?string
    {
        return $this->getAttribute('contact_first_name');
    }

    /**
     * Set first name
     */
    public function setFirstName(string $name): static
    {
        return $this->setAttribute('contact_first_name', $name);
    }

    /**
     * Get last name
     */
    public function getLastName(): ?string
    {
        return $this->getAttribute('contact_last_name');
    }

    /**
     * Set last name
     */
    public function setLastName(string $name): static
    {
        return $this->setAttribute('contact_last_name', $name);
    }

    /**
     * Get email
     */
    public function getEmail(): ?string
    {
        return $this->getAttribute('contact_email');
    }

    /**
     * Set email
     */
    public function setEmail(string $email): static
    {
        return $this->setAttribute('contact_email', $email);
    }

    /**
     * Get phone
     */
    public function getPhone(): ?string
    {
        return $this->getAttribute('contact_phone');
    }

    /**
     * Get company ID
     */
    public function getCompanyId(): ?int
    {
        $company = $this->getAttribute('contact_company');
        return $company !== null ? (int) $company : null;
    }

    /**
     * Find contacts by company
     * 
     * @param int $companyId
     * @return array<int, static>
     */
    public static function findByCompany(int $companyId): array
    {
        return static::findAll(
            sprintf('contact_company = %d', $companyId),
            'contact_last_name ASC, contact_first_name ASC'
        );
    }

    /**
     * Find contacts by owner
     * 
     * @param int $ownerId
     * @return array<int, static>
     */
    public static function findByOwner(int $ownerId): array
    {
        return static::findAll(
            sprintf('contact_owner = %d', $ownerId),
            'contact_last_name ASC, contact_first_name ASC'
        );
    }

    /**
     * Search contacts by name or email
     * 
     * @param string $query Search query
     * @return array<int, static>
     */
    public static function search(string $query): array
    {
        $db = Database::getInstance();
        $escaped = $db->escape($query);

        return static::findAll(
            sprintf(
                "contact_first_name LIKE '%%%s%%' OR contact_last_name LIKE '%%%s%%' OR contact_email LIKE '%%%s%%'",
                $escaped,
                $escaped,
                $escaped
            ),
            'contact_last_name ASC, contact_first_name ASC'
        );
    }
}
