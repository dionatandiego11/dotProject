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
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
ini_set('display_errors', '1');

// Global config for legacy compatibility and database bootstrap
$GLOBALS['dPconfig'] = [
    'dbtype' => getenv('DB_TYPE') ?: 'mysqli',
    'dbhost' => getenv('DB_HOST') ?: 'mariadb',
    'dbname' => getenv('DB_NAME') ?: 'dotproject',
    'dbuser' => getenv('DB_USER') ?: 'dotproject',
    'dbpass' => getenv('DB_PASS') ?: 'dotproject123',
    'dbpersist' => false,
    'dbprefix' => getenv('DB_PREFIX') ?: 'dotp_',
    'daily_working_hours' => 8,
    'debug' => false,
];

// Legacy helper used in several services/repositories
if (!function_exists('dPgetConfig')) {
    function dPgetConfig($key, $default = null)
    {
        return $GLOBALS['dPconfig'][$key] ?? $default;
    }
}

/**
 * Bootstrap ADODB connection used by legacy wrappers.
 */
function bootstrapLegacyDatabase(): void
{
    $adodbPath = DP_BASE_DIR . '/lib/adodb/adodb.inc.php';
    if (!file_exists($adodbPath)) {
        return;
    }

    require_once $adodbPath;
    if (!function_exists('NewADOConnection')) {
        return;
    }

    $dbType = (string) dPgetConfig('dbtype', 'mysqli');
    $dbUser = (string) dPgetConfig('dbuser', 'dotproject');
    $dbPass = (string) dPgetConfig('dbpass', 'dotproject123');
    $dbPort = (string) (getenv('DB_PORT') ?: '');

    $hostCandidates = [];
    foreach ([
        getenv('DB_HOST') ?: null,
        dPgetConfig('dbhost', null),
        'mariadb',
        '127.0.0.1',
        'localhost',
    ] as $host) {
        if ($host === null || $host === '') {
            continue;
        }
        $hostCandidates[] = $host;
        if ($dbPort !== '' && strpos((string) $host, ':') === false) {
            $hostCandidates[] = $host . ':' . $dbPort;
        }
    }
    $hostCandidates = array_values(array_unique($hostCandidates));

    $dbCandidates = [];
    foreach ([getenv('DB_NAME') ?: null, dPgetConfig('dbname', null), 'dotproject'] as $dbName) {
        if ($dbName === null || $dbName === '') {
            continue;
        }
        $dbCandidates[] = $dbName;
        if (str_ends_with($dbName, '_test')) {
            $dbCandidates[] = substr($dbName, 0, -5);
        }
    }
    $dbCandidates = array_values(array_unique($dbCandidates));

    foreach ($hostCandidates as $host) {
        foreach ($dbCandidates as $dbName) {
            $connection = \NewADOConnection($dbType);
            if (@$connection->Connect($host, $dbUser, $dbPass, $dbName)) {
                $GLOBALS['db'] = $connection;
                $GLOBALS['dPconfig']['dbhost'] = $host;
                $GLOBALS['dPconfig']['dbname'] = $dbName;
                return;
            }
        }
    }
}

bootstrapLegacyDatabase();
