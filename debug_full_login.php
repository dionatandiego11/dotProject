<?php
/**
 * Full login flow debug
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(30);

echo "<h1>Full Login Flow Debug</h1><pre>\n";

// Step 1: Load base
echo "1. Loading base.php...\n";
require_once __DIR__ . '/base.php';
echo "   OK - DP_BASE_DIR: " . DP_BASE_DIR . "\n";

// Step 2: Load bootstrap
echo "\n2. Loading bootstrap.php...\n";
require_once __DIR__ . '/bootstrap.php';
echo "   OK\n";

// Step 3: Load config
echo "\n3. Loading config.php...\n";
require_once DP_BASE_DIR . '/includes/config.php';
echo "   OK\n";

// Step 4: Load session
echo "\n4. Loading session.php...\n";
require_once DP_BASE_DIR . '/includes/session.php';
echo "   OK - Session ID: " . session_id() . "\n";

// Step 5: Create AppUI
echo "\n5. Creating AppUI...\n";
$AppUI = new CAppUI();
echo "   OK - User ID: " . ($AppUI->user_id ?? 'not set') . "\n";

// Step 6: Test authentication
echo "\n6. Testing login('admin', 'admin')...\n";
$result = $AppUI->login('admin', 'admin');
echo "   Result: " . ($result ? "TRUE" : "FALSE") . "\n";
echo "   User ID after login: " . ($AppUI->user_id ?? 'not set') . "\n";
echo "   User name: " . ($AppUI->user_first_name ?? '') . " " . ($AppUI->user_last_name ?? '') . "\n";

// Step 7: Check session
echo "\n7. Session status after login...\n";
$_SESSION['AppUI'] = $AppUI;
echo "   Session saved\n";
echo "   AppUI in session: " . (isset($_SESSION['AppUI']) ? 'YES' : 'NO') . "\n";

// Step 8: Check doLogin status
echo "\n8. Checking doLogin()...\n";
echo "   doLogin() returns: " . ($AppUI->doLogin() ? 'TRUE (should login)' : 'FALSE (already logged)') . "\n";

echo "\n</pre>";

if ($result && !empty($AppUI->user_id) && $AppUI->user_id > 0) {
    echo "<p style='color:green;font-weight:bold'>✅ LOGIN SUCCESSFUL! User ID: {$AppUI->user_id}</p>";
    echo "<p><a href='index.php?m=projects'>Go to Projects</a></p>";
} else {
    echo "<p style='color:red;font-weight:bold'>❌ LOGIN FAILED</p>";
}
