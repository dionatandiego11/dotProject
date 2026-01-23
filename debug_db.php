<?php
/**
 * Simple database connection debug
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(10); // 10 second timeout

echo "<h1>Database Connection Debug</h1><pre>\n";

$host = 'mariadb';
$user = 'dotproject';
$pass = 'dotproject123';
$db = 'dotproject';

echo "Attempting to connect to:\n";
echo "  Host: $host\n";
echo "  User: $user\n";
echo "  Database: $db\n\n";

try {
    echo "1. Testing mysqli connection...\n";
    $mysqli = new mysqli($host, $user, $pass, $db);

    if ($mysqli->connect_error) {
        echo "   ERROR: " . $mysqli->connect_error . "\n";
    } else {
        echo "   SUCCESS! Connected to MySQL\n";
        echo "   Server info: " . $mysqli->server_info . "\n";

        echo "\n2. Testing query...\n";
        $result = $mysqli->query("SELECT COUNT(*) as cnt FROM dotp_users");
        if ($result) {
            $row = $result->fetch_assoc();
            echo "   SUCCESS! Users in database: " . $row['cnt'] . "\n";
        } else {
            echo "   ERROR: " . $mysqli->error . "\n";
        }

        echo "\n3. Testing user authentication...\n";
        $result = $mysqli->query("SELECT user_id, user_username, user_password FROM dotp_users WHERE user_username = 'admin'");
        if ($result && $row = $result->fetch_assoc()) {
            echo "   Found user: " . $row['user_username'] . "\n";
            echo "   User ID: " . $row['user_id'] . "\n";
            echo "   Password hash: " . substr($row['user_password'], 0, 20) . "...\n";
        } else {
            echo "   ERROR: Admin user not found\n";
        }

        $mysqli->close();
    }
} catch (Exception $e) {
    echo "EXCEPTION: " . $e->getMessage() . "\n";
}

echo "\n</pre>";
echo "<p>Done!</p>";
