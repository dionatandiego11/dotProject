<?php
/**
 * DotProject User Entity
 * 
 * Modern entity class for User management.
 * 
 * @package DotProject\Entity
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Entity;

use DotProject\Core\Database;

/**
 * User Entity
 * 
 * @property string|null $user_username
 * @property int|null $user_contact
 * @property int|null $user_company
 * @property int|null $user_department
 * @property int $user_type
 */
class User extends BaseEntity
{
    public static function getTable(): string
    {
        return 'users';
    }

    public static function getPrimaryKey(): string
    {
        return 'user_id';
    }

    protected static function getFillable(): array
    {
        return [
            'user_contact',
            'user_username',
            'user_password',
            'user_parent',
            'user_type',
            'user_company',
            'user_department',
            'user_owner',
            'user_signature',
        ];
    }

    /**
     * Get username
     */
    public function getUsername(): ?string
    {
        return $this->getAttribute('user_username');
    }

    /**
     * Get user type
     */
    public function getType(): int
    {
        return (int) ($this->getAttribute('user_type') ?? 0);
    }

    /**
     * Check if user is admin
     */
    public function isAdmin(): bool
    {
        return $this->getType() === 1;
    }

    /**
     * Get user's company ID
     */
    public function getCompanyId(): ?int
    {
        $company = $this->getAttribute('user_company');
        return $company !== null ? (int) $company : null;
    }

    /**
     * Set password (automatically hashes)
     */
    public function setPassword(string $password): static
    {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        return $this->setAttribute('user_password', $hash);
    }

    /**
     * Verify password
     */
    public function verifyPassword(string $password): bool
    {
        $storedHash = $this->getAttribute('user_password');
        if ($storedHash === null) {
            return false;
        }

        // Check for modern password hash
        if (password_get_info($storedHash)['algo'] !== 0) {
            return password_verify($password, $storedHash);
        }

        // Legacy MD5 check with automatic upgrade
        if (md5($password) === $storedHash) {
            $this->setPassword($password);
            $this->save();
            return true;
        }

        return false;
    }

    /**
     * Find user by username
     */
    public static function findByUsername(string $username): ?static
    {
        $db = Database::getInstance();

        $sql = sprintf(
            "SELECT * FROM `%s` WHERE user_username = '%s' LIMIT 1",
            $db->table(static::getTable()),
            $db->escape($username)
        );

        $row = $db->fetchOne($sql);

        if ($row === null) {
            return null;
        }

        return static::fromArray($row);
    }

    /**
     * Find users by company
     * 
     * @param int $companyId
     * @return array<int, static>
     */
    public static function findByCompany(int $companyId): array
    {
        return static::findAll(
            sprintf('user_company = %d', $companyId),
            'user_username ASC'
        );
    }

    /**
     * Get the user's full name from contact
     */
    public function getFullName(): string
    {
        $db = Database::getInstance();
        $contactId = $this->getAttribute('user_contact');

        if ($contactId === null) {
            return $this->getUsername() ?? '';
        }

        $sql = sprintf(
            "SELECT CONCAT(contact_first_name, ' ', contact_last_name) as full_name 
             FROM `%s` WHERE contact_id = %d",
            $db->table('contacts'),
            (int) $contactId
        );

        $name = $db->fetchValue($sql);
        return $name ?: ($this->getUsername() ?? '');
    }
}
