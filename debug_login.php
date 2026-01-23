<?php
/**
 * Debug script to test login directly
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>dotProject Login Debug</h1>";
echo "<pre>";

// Check config
require_once __DIR__ . '/base.php';
require_once __DIR__ . '/bootstrap.php';

echo "1. Base loaded OK\n";

if (is_file(DP_BASE_DIR . '/includes/config.php')) {
    require_once DP_BASE_DIR . '/includes/config.php';
    echo "2. Config loaded OK\n";
    echo "   DB Host: " . $dPconfig['dbhost'] . "\n";
    echo "   DB Name: " . $dPconfig['dbname'] . "\n";
} else {
    echo "2. ERROR: Config file not found!\n";
    exit;
}

// Test database connection
require_once DP_BASE_DIR . '/includes/db_adodb.php';
echo "3. Database connection OK\n";

// Test user table
$q = new DBQuery();
$q->addTable('users');
$q->addQuery('user_id, user_username');
$q->setLimit(5);
$users = $q->loadList();
echo "4. Users in database:\n";
if ($users) {
    foreach ($users as $u) {
        echo "   - ID: {$u['user_id']}, Username: {$u['user_username']}\n";
    }
} else {
    echo "   ERROR: No users found or query failed\n";
}

// Test authentication
echo "\n5. Testing authentication for 'admin'...\n";
require_once DP_BASE_DIR . '/classes/authenticator.class.php';
$auth = getauth('sql');
$result = $auth->authenticate('admin', 'admin');
echo "   Result: " . ($result ? "SUCCESS" : "FAILED") . "\n";

if ($result) {
    $user_id = $auth->userId('admin');
    echo "   User ID: $user_id\n";
}

// Check ACL tables
echo "\n6. Checking ACL tables...\n";
$q = new DBQuery();
$q->addQuery('1');
$q->addTable('gacl_aro');
$q->setLimit(1);
$oldErr = error_reporting(0);
$result = @$q->exec();
error_reporting($oldErr);
echo "   gacl_aro table exists: " . ($result ? "YES" : "NO") . "\n";
$q->clear();

echo "\n7. Checking ui.class.php bypass...\n";
$uiFile = file_get_contents(DP_BASE_DIR . '/classes/ui.class.php');
if (strpos($uiFile, 'TEMPORARY BYPASS') !== false) {
    echo "   Bypass code found: YES\n";
} else {
    echo "   Bypass code found: NO - File may not be synced!\n";
}

echo "\n</pre>";
echo "<p><strong>If all tests pass, try logging in at:</strong> <a href='index.php'>index.php</a></p>";
