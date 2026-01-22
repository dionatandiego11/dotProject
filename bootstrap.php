<?php
/**
 * DotProject Modern Bootstrap
 * 
 * This file initializes the modern PSR-4 autoloading and 
 * integrates new components with the legacy system.
 * 
 * Include this file after base.php to use modern components.
 * 
 * @package DotProject
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

// Check if Composer autoloader exists
$composerAutoload = __DIR__ . '/vendor/autoload.php';

if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
} else {
    // Fallback: Simple PSR-4 autoloader for development without Composer
    spl_autoload_register(function (string $class): void {
        $prefix = 'DotProject\\';
        $baseDir = __DIR__ . '/src/';

        // Check if the class uses the DotProject namespace
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }

        // Get the relative class name
        $relativeClass = substr($class, $len);

        // Replace namespace separators with directory separators
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

        if (file_exists($file)) {
            require $file;
        }
    });
}

// Helper function to get the Application instance
if (!function_exists('app')) {
    /**
     * Get the Application container instance
     * 
     * @return \DotProject\Core\Application
     */
    function app(): \DotProject\Core\Application
    {
        return \DotProject\Core\Application::getInstance();
    }
}

// Helper function to get the Database instance
if (!function_exists('db')) {
    /**
     * Get the Database instance
     * 
     * @return \DotProject\Core\Database
     */
    function db(): \DotProject\Core\Database
    {
        return \DotProject\Core\Database::getInstance();
    }
}

// Helper function to get the EventDispatcher instance
if (!function_exists('events')) {
    /**
     * Get the EventDispatcher instance
     * 
     * @return \DotProject\Core\EventDispatcher
     */
    function events(): \DotProject\Core\EventDispatcher
    {
        return \DotProject\Core\EventDispatcher::getInstance();
    }
}

