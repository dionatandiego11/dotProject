<?php
/**
 * DotProject Event Dispatcher
 * 
 * Simple event dispatcher for implementing hooks and extensibility.
 * Allows modules to subscribe to events and react to system actions.
 * 
 * @package DotProject\Core
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Core;

/**
 * Event Dispatcher
 * 
 * Manages event listeners and dispatches events throughout the application.
 */
class EventDispatcher
{
    private static ?EventDispatcher $instance = null;

    /** @var array<string, array<int, callable>> Event listeners */
    private array $listeners = [];

    /** @var array<string, array<string, mixed>> Dispatched events log */
    private array $dispatchedEvents = [];

    /** @var bool Enable event logging */
    private bool $logging = false;

    /**
     * Private constructor for singleton pattern
     */
    private function __construct()
    {
    }

    /**
     * Get singleton instance
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Reset instance (useful for testing)
     */
    public static function resetInstance(): void
    {
        self::$instance = null;
    }

    /**
     * Register an event listener
     * 
     * @param string $eventName Event name to listen for
     * @param callable $listener Callback to execute when event is dispatched
     * @param int $priority Listener priority (higher = runs first)
     * @return static
     */
    public function on(string $eventName, callable $listener, int $priority = 0): static
    {
        if (!isset($this->listeners[$eventName])) {
            $this->listeners[$eventName] = [];
        }

        $this->listeners[$eventName][] = [
            'callback' => $listener,
            'priority' => $priority,
        ];

        // Sort by priority (descending)
        usort($this->listeners[$eventName], fn($a, $b) => $b['priority'] <=> $a['priority']);

        return $this;
    }

    /**
     * Remove an event listener
     * 
     * @param string $eventName Event name
     * @param callable $listener Listener to remove
     * @return static
     */
    public function off(string $eventName, callable $listener): static
    {
        if (!isset($this->listeners[$eventName])) {
            return $this;
        }

        $this->listeners[$eventName] = array_filter(
            $this->listeners[$eventName],
            fn($item) => $item['callback'] !== $listener
        );

        return $this;
    }

    /**
     * Remove all listeners for an event
     * 
     * @param string $eventName Event name
     * @return static
     */
    public function removeAllListeners(string $eventName): static
    {
        unset($this->listeners[$eventName]);
        return $this;
    }

    /**
     * Dispatch an event
     * 
     * @param string $eventName Event name
     * @param Event|null $event Event object with data
     * @return Event The event (possibly modified by listeners)
     */
    public function dispatch(string $eventName, ?Event $event = null): Event
    {
        $event ??= new Event($eventName);
        $event->setName($eventName);

        if ($this->logging) {
            $this->dispatchedEvents[] = [
                'name' => $eventName,
                'time' => microtime(true),
                'data' => $event->getData(),
            ];
        }

        if (!isset($this->listeners[$eventName])) {
            return $event;
        }

        foreach ($this->listeners[$eventName] as $listener) {
            if ($event->isPropagationStopped()) {
                break;
            }

            call_user_func($listener['callback'], $event);
        }

        return $event;
    }

    /**
     * Check if event has listeners
     * 
     * @param string $eventName Event name
     * @return bool
     */
    public function hasListeners(string $eventName): bool
    {
        return !empty($this->listeners[$eventName]);
    }

    /**
     * Get all listeners for an event
     * 
     * @param string $eventName Event name
     * @return array<int, callable>
     */
    public function getListeners(string $eventName): array
    {
        if (!isset($this->listeners[$eventName])) {
            return [];
        }

        return array_map(fn($item) => $item['callback'], $this->listeners[$eventName]);
    }

    /**
     * Enable/disable event logging
     */
    public function setLogging(bool $enabled): static
    {
        $this->logging = $enabled;
        return $this;
    }

    /**
     * Get dispatched events log
     * 
     * @return array<int, array<string, mixed>>
     */
    public function getDispatchedEvents(): array
    {
        return $this->dispatchedEvents;
    }

    /**
     * Clear dispatched events log
     */
    public function clearLog(): static
    {
        $this->dispatchedEvents = [];
        return $this;
    }
}
