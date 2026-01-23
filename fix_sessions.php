<?php
/**
 * Fix sessions table and config
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

$host = 'mariadb';
$user = 'dotproject';
$pass = 'dotproject123';
$db = 'dotproject';
$prefix = 'dotp_';

echo "<h1>Fix Sessions Table</h1><pre>\n";

$mysqli = new mysqli($host, $user, $pass, $db);
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// 1. Create sessions table
echo "1. Creating sessions table...\n";
$sql = "CREATE TABLE IF NOT EXISTS `{$prefix}sessions` (
    `session_id` varchar(60) NOT NULL default '',
    `session_user` INT DEFAULT '0' NOT NULL,
    `session_data` LONGBLOB,
    `session_updated` TIMESTAMP,
    `session_created` DATETIME NOT NULL default '0000-00-00 00:00:00',
    PRIMARY KEY (`session_id`),
    KEY (`session_updated`),
    KEY (`session_created`)
)";

if ($mysqli->query($sql)) {
    echo "   ✅ Sessions table created/exists\n";
} else {
    echo "   ❌ Error: " . $mysqli->error . "\n";
}

// 2. Update session_handling config to 'php' in database
echo "\n2. Updating session_handling config in database...\n";
$result = $mysqli->query("SELECT config_id, config_value FROM {$prefix}config WHERE config_name = 'session_handling'");
if ($result && $row = $result->fetch_assoc()) {
    echo "   Current value: " . $row['config_value'] . "\n";

    $mysqli->query("UPDATE {$prefix}config SET config_value = 'php' WHERE config_name = 'session_handling'");
    echo "   ✅ Updated to 'php'\n";
} else {
    echo "   Config not found in database, will use file config\n";
}

// 3. Verify
echo "\n3. Verifying changes...\n";
$result = $mysqli->query("SHOW TABLES LIKE '{$prefix}sessions'");
echo "   Sessions table exists: " . ($result->num_rows > 0 ? "YES" : "NO") . "\n";

$result = $mysqli->query("SELECT config_value FROM {$prefix}config WHERE config_name = 'session_handling'");
if ($result && $row = $result->fetch_assoc()) {
    echo "   session_handling config: " . $row['config_value'] . "\n";
}

$mysqli->close();

echo "\n</pre>";
echo "<p style='color:green;font-weight:bold'>✅ DONE! Now clear your browser cookies and try logging in at:</p>";
echo "<p><a href='index.php'>index.php</a></p>";
