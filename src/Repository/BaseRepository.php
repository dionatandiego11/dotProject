<?php
/**
 * DotProject Base Repository
 * 
 * Abstract repository class providing common data access patterns.
 * Repositories handle database queries and return entities.
 * 
 * @package DotProject\Repository
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Repository;

use DotProject\Core\Database;
use DotProject\Entity\BaseEntity;

/**
 * Abstract Base Repository
 * 
 * Provides common CRUD operations for repositories.
 * 
 * @template T of BaseEntity
 */
abstract class BaseRepository
{
    protected Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    /**
     * Get the entity class name
     * 
     * @return class-string<T>
     */
    abstract protected function getEntityClass(): string;

    /**
     * Get the table name
     */
    abstract protected function getTable(): string;

    /**
     * Get the primary key column name
     */
    abstract protected function getPrimaryKey(): string;

    /**
     * Find entity by ID
     * 
     * @param int $id Entity ID
     * @return T|null
     */
    public function find(int $id): ?BaseEntity
    {
        $sql = sprintf(
            "SELECT * FROM `%s` WHERE %s = %d LIMIT 1",
            $this->db->table($this->getTable()),
            $this->getPrimaryKey(),
            $id
        );

        $row = $this->db->fetchOne($sql);

        if ($row === null) {
            return null;
        }

        $entityClass = $this->getEntityClass();
        return $entityClass::fromArray($row);
    }

    /**
     * Find all entities
     * 
     * @param string|null $orderBy Order clause
     * @return array<int, T>
     */
    public function findAll(?string $orderBy = null): array
    {
        $sql = sprintf("SELECT * FROM `%s`", $this->db->table($this->getTable()));

        if ($orderBy) {
            $sql .= ' ORDER BY ' . $orderBy;
        }

        $rows = $this->db->fetchAll($sql);
        $entityClass = $this->getEntityClass();

        return array_map(fn($row) => $entityClass::fromArray($row), $rows);
    }

    /**
     * Find entities by criteria
     * 
     * @param array<string, mixed> $criteria Column => value pairs
     * @param string|null $orderBy Order clause
     * @param int|null $limit Limit results
     * @return array<int, T>
     */
    public function findBy(array $criteria, ?string $orderBy = null, ?int $limit = null): array
    {
        $sql = sprintf("SELECT * FROM `%s`", $this->db->table($this->getTable()));

        if (!empty($criteria)) {
            $conditions = [];
            foreach ($criteria as $column => $value) {
                if ($value === null) {
                    $conditions[] = "`{$column}` IS NULL";
                } else {
                    $conditions[] = sprintf("`%s` = %s", $column, $this->db->quote($value));
                }
            }
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        if ($orderBy) {
            $sql .= ' ORDER BY ' . $orderBy;
        }

        if ($limit) {
            $sql .= ' LIMIT ' . $limit;
        }

        $rows = $this->db->fetchAll($sql);
        $entityClass = $this->getEntityClass();

        return array_map(fn($row) => $entityClass::fromArray($row), $rows);
    }

    /**
     * Find one entity by criteria
     * 
     * @param array<string, mixed> $criteria Column => value pairs
     * @return T|null
     */
    public function findOneBy(array $criteria): ?BaseEntity
    {
        $results = $this->findBy($criteria, null, 1);
        return !empty($results) ? $results[0] : null;
    }

    /**
     * Count entities
     * 
     * @param array<string, mixed>|null $criteria Optional filter criteria
     * @return int
     */
    public function count(?array $criteria = null): int
    {
        $sql = sprintf("SELECT COUNT(*) FROM `%s`", $this->db->table($this->getTable()));

        if (!empty($criteria)) {
            $conditions = [];
            foreach ($criteria as $column => $value) {
                if ($value === null) {
                    $conditions[] = "`{$column}` IS NULL";
                } else {
                    $conditions[] = sprintf("`%s` = %s", $column, $this->db->quote($value));
                }
            }
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        return (int) ($this->db->fetchValue($sql) ?? 0);
    }

    /**
     * Save an entity (insert or update)
     * 
     * @param T $entity Entity to save
     * @return bool Success
     */
    public function save(BaseEntity $entity): bool
    {
        return $entity->save();
    }

    /**
     * Delete an entity
     * 
     * @param T $entity Entity to delete
     * @return bool Success
     */
    public function delete(BaseEntity $entity): bool
    {
        return $entity->delete();
    }

    /**
     * Delete by ID
     * 
     * @param int $id Entity ID
     * @return bool Success
     */
    public function deleteById(int $id): bool
    {
        return $this->db->delete(
            $this->getTable(),
            sprintf('%s = %d', $this->getPrimaryKey(), $id)
        );
    }

    /**
     * Check if entity exists
     * 
     * @param int $id Entity ID
     * @return bool
     */
    public function exists(int $id): bool
    {
        $sql = sprintf(
            "SELECT 1 FROM `%s` WHERE %s = %d LIMIT 1",
            $this->db->table($this->getTable()),
            $this->getPrimaryKey(),
            $id
        );

        return $this->db->fetchValue($sql) !== null;
    }

    /**
     * Execute raw SQL and return entities
     * 
     * @param string $sql SQL query
     * @return array<int, T>
     */
    protected function query(string $sql): array
    {
        $rows = $this->db->fetchAll($sql);
        $entityClass = $this->getEntityClass();

        return array_map(fn($row) => $entityClass::fromArray($row), $rows);
    }
}
