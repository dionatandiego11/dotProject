<?php
/**
 * PHPUnit Test Bootstrap
 * 
 * Sets up the test environment for unit and integration tests.
 * 
 * @package DotProject\Tests
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

// Load Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Define base constants if not already defined
if (!defined('DP_BASE_DIR')) {
    define('DP_BASE_DIR', dirname(__DIR__));
}

if (!defined('DP_BASE_URL')) {
    define('DP_BASE_URL', '');
}

// Set error reporting for tests
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Global config placeholder for tests
$GLOBALS['dPconfig'] = [
    'daily_working_hours' => 8,
    'dbprefix' => '',
    'debug' => false,
];

// Mock dPgetConfig function for tests (legacy system compatibility)
if (!function_exists('dPgetConfig')) {
    function dPgetConfig($key, $default = null)
    {
        return $GLOBALS['dPconfig'][$key] ?? $default;
    }
}
