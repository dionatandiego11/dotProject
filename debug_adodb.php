<?php
/**
 * Test ADOdb connection
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(10);

echo "<h1>ADOdb Connection Debug</h1><pre>\n";

define('DP_BASE_DIR', __DIR__);

echo "1. Loading ADOdb...\n";
require_once(DP_BASE_DIR . '/lib/adodb/adodb.inc.php');
echo "   OK\n";

echo "2. Creating connection object (mysqli)...\n";
$db = NewADOConnection('mysqli');
echo "   OK\n";

echo "3. Connecting to database...\n";
$ret = $db->Connect('mariadb', 'dotproject', 'dotproject123', 'dotproject');
if ($ret) {
    echo "   SUCCESS!\n";
    echo "   Server info: " . $db->ServerInfo()['description'] . "\n";
} else {
    echo "   FAILED: " . $db->ErrorMsg() . "\n";
}

echo "4. Testing query...\n";
$result = $db->Execute("SELECT user_id, user_username FROM dotp_users LIMIT 1");
if ($result) {
    $row = $result->FetchRow();
    echo "   User: " . $row['user_username'] . " (ID: " . $row['user_id'] . ")\n";
} else {
    echo "   FAILED: " . $db->ErrorMsg() . "\n";
}

echo "\n</pre><p>Done!</p>";
