<?php
/**
 * DotProject Base Entity Class
 * 
 * Modern base class for entities that provides a cleaner interface
 * while maintaining compatibility with CDpObject.
 * 
 * @package DotProject\Entity
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Entity;

use DotProject\Core\Application;
use DotProject\Core\Database;

/**
 * Abstract base entity class
 * 
 * New entities can extend this class for a modern API,
 * while maintaining compatibility with the legacy system.
 */
abstract class BaseEntity implements \ArrayAccess
{
    /** @var int|null Entity ID */
    protected ?int $id = null;

    /** @var array<string, mixed> Entity attributes */
    protected array $attributes = [];

    /** @var array<string, mixed> Original attributes (for dirty checking) */
    protected array $original = [];

    /** @var bool Whether the entity exists in the database */
    protected bool $exists = false;

    /**
     * Get the table name for this entity
     */
    abstract public static function getTable(): string;

    /**
     * Get the primary key column name
     */
    abstract public static function getPrimaryKey(): string;

    /**
     * Get fillable attributes
     * 
     * @return array<int, string>
     */
    abstract protected static function getFillable(): array;

    /**
     * Create a new entity instance
     * 
     * @param array<string, mixed> $attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->fill($attributes);
    }

    /**
     * Get the entity ID
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Check if entity exists in database
     */
    public function exists(): bool
    {
        return $this->exists;
    }

    /**
     * Fill entity with attributes
     * 
     * @param array<string, mixed> $attributes
     * @return static
     */
    public function fill(array $attributes): static
    {
        $fillable = static::getFillable();

        foreach ($attributes as $key => $value) {
            if ($key === static::getPrimaryKey()) {
                $this->id = (int) $value;
                continue;
            }

            if (in_array($key, $fillable, true)) {
                $this->attributes[$key] = $value;
            }
        }

        return $this;
    }

    /**
     * Get an attribute value
     */
    public function getAttribute(string $key): mixed
    {
        return $this->attributes[$key] ?? null;
    }

    /**
     * Set an attribute value
     */
    public function setAttribute(string $key, mixed $value): static
    {
        if (in_array($key, static::getFillable(), true)) {
            $this->attributes[$key] = $value;
        }
        return $this;
    }

    /**
     * Get all attributes as array
     * 
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = $this->attributes;
        if ($this->id !== null) {
            $data[static::getPrimaryKey()] = $this->id;
        }
        return $data;
    }

    /**
     * Get modified attributes (dirty)
     * 
     * @return array<string, mixed>
     */
    public function getDirty(): array
    {
        $dirty = [];
        foreach ($this->attributes as $key => $value) {
            if (!array_key_exists($key, $this->original) || $this->original[$key] !== $value) {
                $dirty[$key] = $value;
            }
        }
        return $dirty;
    }

    /**
     * Check if entity has been modified
     */
    public function isDirty(): bool
    {
        return !empty($this->getDirty());
    }

    /**
     * Save the entity to the database
     * 
     * @return bool Success
     */
    public function save(): bool
    {
        $db = Database::getInstance();

        if ($this->exists && $this->id !== null) {
            // Update existing record
            $dirty = $this->getDirty();
            if (empty($dirty)) {
                return true; // Nothing to update
            }

            $result = $db->update(
                static::getTable(),
                $dirty,
                sprintf('%s = %d', static::getPrimaryKey(), $this->id)
            );

            if ($result) {
                $this->syncOriginal();
            }

            return $result;
        }

        // Insert new record
        $id = $db->insert(static::getTable(), $this->attributes);

        if ($id !== false) {
            $this->id = $id;
            $this->exists = true;
            $this->syncOriginal();
            return true;
        }

        return false;
    }

    /**
     * Delete the entity from the database
     * 
     * @return bool Success
     */
    public function delete(): bool
    {
        if (!$this->exists || $this->id === null) {
            return false;
        }

        $db = Database::getInstance();
        $result = $db->delete(
            static::getTable(),
            sprintf('%s = %d', static::getPrimaryKey(), $this->id)
        );

        if ($result) {
            $this->exists = false;
        }

        return $result;
    }

    /**
     * Find an entity by ID
     * 
     * @param int $id Entity ID
     * @return static|null
     */
    public static function find(int $id): ?static
    {
        $db = Database::getInstance();

        $sql = sprintf(
            'SELECT * FROM `%s` WHERE %s = %d LIMIT 1',
            $db->table(static::getTable()),
            static::getPrimaryKey(),
            $id
        );

        $row = $db->fetchOne($sql);

        if ($row === null) {
            return null;
        }

        return static::fromArray($row);
    }

    /**
     * Find all entities matching conditions
     * 
     * @param string|null $where WHERE clause
     * @param string|null $order ORDER BY clause
     * @return array<int, static>
     */
    public static function findAll(?string $where = null, ?string $order = null): array
    {
        $db = Database::getInstance();

        $sql = sprintf('SELECT * FROM `%s`', $db->table(static::getTable()));

        if ($where) {
            $sql .= ' WHERE ' . $where;
        }

        if ($order) {
            $sql .= ' ORDER BY ' . $order;
        }

        $rows = $db->fetchAll($sql);

        return array_map(fn($row) => static::fromArray($row), $rows);
    }

    /**
     * Fill entity with attributes ignoring fillable array
     * 
     * @param array<string, mixed> $attributes
     * @return static
     */
    public function forceFill(array $attributes): static
    {
        foreach ($attributes as $key => $value) {
            if ($key === static::getPrimaryKey()) {
                $this->id = (int) $value;
            }
            $this->attributes[$key] = $value;
        }
        return $this;
    }

    /**
     * Create an entity from a database row
     * 
     * @param array<string, mixed> $row
     * @return static
     */
    public static function fromArray(array $row): static
    {
        $entity = new static([]);
        $entity->forceFill($row);
        $entity->exists = true;
        $entity->syncOriginal();
        return $entity;
    }

    /**
     * Sync original attributes with current attributes
     */
    protected function syncOriginal(): void
    {
        $this->original = $this->attributes;
    }

    /**
     * Magic getter for attributes
     */
    public function __get(string $name): mixed
    {
        return $this->getAttribute($name);
    }

    /**
     * Magic setter for attributes
     */
    public function __set(string $name, mixed $value): void
    {
        $this->setAttribute($name, $value);
    }

    /**
     * Magic isset for attributes
     */
    public function __isset(string $name): bool
    {
        return isset($this->attributes[$name]);
    }
    /**
     * ArrayAccess implementation
     */
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->attributes[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->getAttribute($offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->setAttribute($offset, $value);
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->attributes[$offset]);
    }
}
