<?php
/**
 * Test with PHP native session - bypassing dotProject session handling
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start PHP native session BEFORE loading dotProject
session_name('dotproject');
session_start();

echo "<h1>Direct Session Login Test</h1><pre>";

echo "Session ID: " . session_id() . "\n";
echo "Session status: " . (session_status() === PHP_SESSION_ACTIVE ? 'ACTIVE' : 'INACTIVE') . "\n\n";

// Check if already logged in
if (isset($_SESSION['AppUI']) && is_object($_SESSION['AppUI']) && isset($_SESSION['AppUI']->user_id) && $_SESSION['AppUI']->user_id > 0) {
    echo "✅ ALREADY LOGGED IN!\n";
    echo "User ID: " . $_SESSION['AppUI']->user_id . "\n";
    echo "User: " . ($_SESSION['AppUI']->user_first_name ?? '') . " " . ($_SESSION['AppUI']->user_last_name ?? '') . "\n";
    echo "</pre>";
    echo "<p><a href='index.php?m=system'>→ Go to System Admin</a></p>";
    echo "<p><a href='direct_login_test.php?logout=1'>Logout</a></p>";
    exit;
}

// Handle logout
if (isset($_GET['logout'])) {
    unset($_SESSION['AppUI']);
    session_destroy();
    echo "Logged out. <a href='direct_login_test.php'>Login again</a>";
    exit;
}

// Load dotProject (but session already started so it won't use database)
define('DP_BASE_DIR', __DIR__);
require_once __DIR__ . '/base.php';
require_once __DIR__ . '/bootstrap.php';
require_once DP_BASE_DIR . '/includes/config.php';
require_once DP_BASE_DIR . '/includes/main_functions.php';
require_once DP_BASE_DIR . '/includes/db_adodb.php';
require_once DP_BASE_DIR . '/includes/db_connect.php';
require_once DP_BASE_DIR . '/classes/query.class.php';
require_once DP_BASE_DIR . '/classes/ui.class.php';

echo "DotProject loaded.\n";
echo "session_handling config: " . (dPgetConfig('session_handling') ?? 'not set') . "\n\n";

// Handle login form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    echo "Processing login...\n";
    $AppUI = new CAppUI();

    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    echo "Username: $username\n";

    $result = $AppUI->login($username, $password);

    if ($result) {
        $_SESSION['AppUI'] = $AppUI;
        echo "\n✅ LOGIN SUCCESSFUL!\n";
        echo "User ID: " . $AppUI->user_id . "\n";
        echo "Name: " . $AppUI->user_first_name . " " . $AppUI->user_last_name . "\n";
        echo "</pre>";
        echo "<p><strong><a href='direct_login_test.php'>Click here to verify session persists</a></strong></p>";
        echo "<p><a href='index.php?m=system'>→ Go to System Admin</a></p>";
    } else {
        echo "\n❌ LOGIN FAILED\n";
        echo "</pre>";
    }
    exit;
}

echo "</pre>";
?>
<h2>Direct Login Test</h2>
<form method="POST">
    <p>Username: <input type="text" name="username" value="admin"></p>
    <p>Password: <input type="password" name="password" value="admin"></p>
    <p><button type="submit" name="login" value="1">Login</button></p>
</form>