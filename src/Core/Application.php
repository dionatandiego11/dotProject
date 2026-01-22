<?php
/**
 * DotProject Application Container
 * 
 * Simple dependency injection container for the application.
 * Provides centralized access to services without relying on globals.
 * 
 * @package DotProject\Core
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Core;

/**
 * Simple service container for dependency injection
 */
class Application
{
    private static ?Application $instance = null;

    /** @var array<string, object> Registered services */
    private array $services = [];

    /** @var array<string, callable> Service factories */
    private array $factories = [];

    /**
     * Private constructor for singleton pattern
     */
    private function __construct()
    {
        $this->registerCoreServices();
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
     * Register core services
     */
    private function registerCoreServices(): void
    {
        $this->singleton('database', fn() => Database::getInstance());
    }

    /**
     * Register a service as a singleton
     * 
     * @param string $name Service name
     * @param callable $factory Factory function
     */
    public function singleton(string $name, callable $factory): void
    {
        $this->factories[$name] = $factory;
    }

    /**
     * Get a service
     * 
     * @param string $name Service name
     * @return object|null
     */
    public function get(string $name): ?object
    {
        // Return cached service if exists
        if (isset($this->services[$name])) {
            return $this->services[$name];
        }

        // Create and cache if factory exists
        if (isset($this->factories[$name])) {
            $this->services[$name] = ($this->factories[$name])();
            return $this->services[$name];
        }

        return null;
    }

    /**
     * Check if a service is registered
     */
    public function has(string $name): bool
    {
        return isset($this->services[$name]) || isset($this->factories[$name]);
    }

    /**
     * Get the database service
     */
    public function db(): Database
    {
        return $this->get('database');
    }

    /**
     * Get legacy AppUI for backwards compatibility
     * 
     * @return \CAppUI
     */
    public function ui(): \CAppUI
    {
        global $AppUI;
        return $AppUI;
    }
}
