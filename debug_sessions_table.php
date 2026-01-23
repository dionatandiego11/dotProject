<?php
/**
 * Test dotProject sessions table
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

$host = 'mariadb';
$user = 'dotproject';
$pass = 'dotproject123';
$db = 'dotproject';

echo "<h1>Sessions Table Debug</h1><pre>";

$mysqli = new mysqli($host, $user, $pass, $db);
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// Check if sessions table exists
$result = $mysqli->query("SHOW TABLES LIKE 'dotp_sessions'");
if ($result->num_rows > 0) {
    echo "✅ Table dotp_sessions EXISTS\n\n";

    // Show structure
    $result = $mysqli->query("DESCRIBE dotp_sessions");
    echo "Table structure:\n";
    while ($row = $result->fetch_assoc()) {
        echo "  - " . $row['Field'] . " (" . $row['Type'] . ")\n";
    }

    // Show contents
    echo "\nExisting sessions:\n";
    $result = $mysqli->query("SELECT session_id, session_user, session_created, session_updated FROM dotp_sessions LIMIT 10");
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            echo "  ID: " . substr($row['session_id'], 0, 20) . "... User: " . $row['session_user'] . " Created: " . $row['session_created'] . "\n";
        }
    } else {
        echo "  (no sessions found)\n";
    }
} else {
    echo "❌ Table dotp_sessions DOES NOT EXIST!\n";
    echo "\nThis is the problem! The sessions table needs to be created.\n";
}

// Check config for session handling
echo "\n\nChecking session config...\n";
define('DP_BASE_DIR', __DIR__);
require_once __DIR__ . '/base.php';
require_once __DIR__ . '/bootstrap.php';
require_once DP_BASE_DIR . '/includes/config.php';
echo "session_handling: " . (dPgetConfig('session_handling') ?? 'not set (defaults to php)') . "\n";

$mysqli->close();
echo "</pre>";
