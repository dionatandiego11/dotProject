<?php
/**
 * DotProject Department Entity
 * 
 * Modern entity class for Department management.
 * 
 * @package DotProject\Entity
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Entity;

use DotProject\Core\Database;

/**
 * Department Entity
 * 
 * @property string|null $dept_name
 * @property int|null $dept_company
 * @property int|null $dept_parent
 * @property string|null $dept_phone
 * @property string|null $dept_fax
 * @property string|null $dept_address1
 * @property string|null $dept_city
 * @property string|null $dept_state
 * @property string|null $dept_zip
 * @property string|null $dept_country
 * @property string|null $dept_desc
 * @property int $dept_owner
 */
class Department extends BaseEntity
{
    public static function getTable(): string
    {
        return 'departments';
    }

    public static function getPrimaryKey(): string
    {
        return 'dept_id';
    }

    protected static function getFillable(): array
    {
        return [
            'dept_company',
            'dept_name',
            'dept_phone',
            'dept_fax',
            'dept_address1',
            'dept_address2',
            'dept_city',
            'dept_state',
            'dept_zip',
            'dept_url',
            'dept_desc',
            'dept_parent',
            'dept_owner',
            'dept_country',
        ];
    }

    /**
     * Get department name
     */
    public function getName(): ?string
    {
        return $this->getAttribute('dept_name');
    }

    /**
     * Set department name
     */
    public function setName(string $name): static
    {
        return $this->setAttribute('dept_name', $name);
    }

    /**
     * Get company ID
     */
    public function getCompanyId(): ?int
    {
        $company = $this->getAttribute('dept_company');
        return $company !== null ? (int) $company : null;
    }

    /**
     * Get parent department ID
     */
    public function getParentId(): ?int
    {
        $parent = $this->getAttribute('dept_parent');
        return $parent !== null ? (int) $parent : null;
    }

    /**
     * Check if this is a root department
     */
    public function isRoot(): bool
    {
        return $this->getParentId() === 0 || $this->getParentId() === null;
    }

    /**
     * Find departments by company
     * 
     * @param int $companyId
     * @return array<int, static>
     */
    public static function findByCompany(int $companyId): array
    {
        return static::findAll(
            sprintf('dept_company = %d', $companyId),
            'dept_parent ASC, dept_name ASC'
        );
    }

    /**
     * Find root departments (no parent)
     * 
     * @param int|null $companyId Optional company filter
     * @return array<int, static>
     */
    public static function findRoots(?int $companyId = null): array
    {
        $where = 'dept_parent = 0 OR dept_parent IS NULL';

        if ($companyId !== null) {
            $where = sprintf('(%s) AND dept_company = %d', $where, $companyId);
        }

        return static::findAll($where, 'dept_name ASC');
    }

    /**
     * Find child departments
     * 
     * @param int $parentId Parent department ID
     * @return array<int, static>
     */
    public static function findChildren(int $parentId): array
    {
        return static::findAll(
            sprintf('dept_parent = %d', $parentId),
            'dept_name ASC'
        );
    }

    /**
     * Get department tree for a company
     * 
     * @param int $companyId Company ID
     * @return array<int, array<string, mixed>>
     */
    public static function getTree(int $companyId): array
    {
        $db = Database::getInstance();

        $sql = sprintf(
            "SELECT * FROM `%s` WHERE dept_company = %d ORDER BY dept_parent, dept_name",
            $db->table('departments'),
            $companyId
        );

        $rows = $db->fetchAll($sql);

        // Build tree structure
        $tree = [];
        $lookup = [];

        foreach ($rows as $row) {
            $row['children'] = [];
            $lookup[$row['dept_id']] = $row;
        }

        foreach ($lookup as $id => $dept) {
            $parentId = $dept['dept_parent'] ?? 0;
            if ($parentId === 0 || !isset($lookup[$parentId])) {
                $tree[] = &$lookup[$id];
            } else {
                $lookup[$parentId]['children'][] = &$lookup[$id];
            }
        }

        return $tree;
    }
}
