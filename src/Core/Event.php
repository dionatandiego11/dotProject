<?php
/**
 * DotProject Event
 * 
 * Event object for passing data between event dispatcher and listeners.
 * 
 * @package DotProject\Core
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Core;

/**
 * Event
 * 
 * Represents an event that can be dispatched and modified by listeners.
 */
class Event
{
    /** @var string Event name */
    private string $name;

    /** @var array<string, mixed> Event data */
    private array $data;

    /** @var bool Whether propagation is stopped */
    private bool $propagationStopped = false;

    /** @var mixed Result from listeners */
    private mixed $result = null;

    /**
     * Create a new event
     * 
     * @param string $name Event name
     * @param array<string, mixed> $data Event data
     */
    public function __construct(string $name = '', array $data = [])
    {
        $this->name = $name;
        $this->data = $data;
    }

    /**
     * Get event name
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Set event name
     */
    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Get all event data
     * 
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Set all event data
     * 
     * @param array<string, mixed> $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;
        return $this;
    }

    /**
     * Get a specific data value
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * Set a specific data value
     */
    public function set(string $key, mixed $value): static
    {
        $this->data[$key] = $value;
        return $this;
    }

    /**
     * Check if data key exists
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    /**
     * Remove a data key
     */
    public function remove(string $key): static
    {
        unset($this->data[$key]);
        return $this;
    }

    /**
     * Stop event propagation
     */
    public function stopPropagation(): static
    {
        $this->propagationStopped = true;
        return $this;
    }

    /**
     * Check if propagation is stopped
     */
    public function isPropagationStopped(): bool
    {
        return $this->propagationStopped;
    }

    /**
     * Set event result
     */
    public function setResult(mixed $result): static
    {
        $this->result = $result;
        return $this;
    }

    /**
     * Get event result
     */
    public function getResult(): mixed
    {
        return $this->result;
    }

    /**
     * Check if event has a result
     */
    public function hasResult(): bool
    {
        return $this->result !== null;
    }
}
