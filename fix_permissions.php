<?php
/**
 * Fix permissions table and give admin full access
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

$host = 'mariadb';
$user = 'dotproject';
$pass = 'dotproject123';
$db = 'dotproject';
$prefix = 'dotp_';

echo "<h1>Fix Permissions Tables</h1><pre>\n";

$mysqli = new mysqli($host, $user, $pass, $db);
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// 1. Create dotpermissions table
echo "1. Creating dotpermissions table...\n";
$sql = "CREATE TABLE IF NOT EXISTS `{$prefix}dotpermissions` (
  `acl_id` int(11) NOT NULL DEFAULT '0',
  `user_id` varchar(80) NOT NULL DEFAULT '',
  `section` varchar(80) NOT NULL DEFAULT '',
  `axo` varchar(80) NOT NULL DEFAULT '',
  `permission` varchar(80) NOT NULL DEFAULT '',
  `allow` int(11) NOT NULL DEFAULT '0',
  `priority` int(11) NOT NULL DEFAULT '0',
  `enabled` int(11) NOT NULL DEFAULT '0',
  KEY `user_id` (`user_id`,`section`,`permission`,`axo`)
)";

if ($mysqli->query($sql)) {
    echo "   ✅ dotpermissions table created\n";
} else {
    echo "   ❌ Error: " . $mysqli->error . "\n";
}

// 2. Give admin (user_id=1) full access to all modules
echo "\n2. Granting admin full access to all modules...\n";

// Delete old permissions for admin first
$mysqli->query("DELETE FROM {$prefix}dotpermissions WHERE user_id = '1'");

// Get all modules
$modules = [
    'projects',
    'tasks',
    'companies',
    'contacts',
    'calendar',
    'files',
    'forums',
    'ticketsmith',
    'admin',
    'system',
    'departments',
    'resources'
];

$permissions = ['access', 'view', 'add', 'edit', 'delete'];

$acl_id = 1;
foreach ($modules as $module) {
    foreach ($permissions as $perm) {
        // App level permission
        $sql = "INSERT INTO {$prefix}dotpermissions 
                (acl_id, user_id, section, axo, permission, allow, priority, enabled) 
                VALUES ($acl_id, '1', 'app', '$module', '$perm', 1, 0, 1)";
        $mysqli->query($sql);
        $acl_id++;
    }
}
echo "   ✅ Admin granted full access to all modules\n";

// 3. Verify permissions table
echo "\n3. Verifying...\n";
$result = $mysqli->query("SELECT COUNT(*) as cnt FROM {$prefix}dotpermissions WHERE user_id = '1'");
$row = $result->fetch_assoc();
echo "   Permissions for admin: " . $row['cnt'] . " entries\n";

$mysqli->close();

echo "\n</pre>";
echo "<p style='color:green;font-weight:bold'>✅ DONE! Now try accessing the system:</p>";
echo "<p><a href='index.php?m=system'>System Admin</a></p>";
echo "<p><a href='index.php?m=projects'>Projects</a></p>";
