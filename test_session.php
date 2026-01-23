<?php
/**
 * Browser session test - open this in browser!
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/base.php';
require_once __DIR__ . '/bootstrap.php';
require_once DP_BASE_DIR . '/includes/config.php';
require_once DP_BASE_DIR . '/includes/session.php';

echo "<h1>Browser Session Test</h1>";
echo "<pre>";

// Check if already logged in
if (isset($_SESSION['AppUI']) && is_object($_SESSION['AppUI']) && $_SESSION['AppUI']->user_id > 0) {
    echo "✅ ALREADY LOGGED IN!\n";
    echo "User ID: " . $_SESSION['AppUI']->user_id . "\n";
    echo "User: " . $_SESSION['AppUI']->user_first_name . " " . $_SESSION['AppUI']->user_last_name . "\n";
    echo "\n<a href='index.php?m=system'>Go to System Admin</a>";
    echo "</pre>";
    exit;
}

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
        echo "✅ LOGIN SUCCESSFUL!\n";
        echo "User ID: " . $AppUI->user_id . "\n";
        echo "Session ID: " . session_id() . "\n";
        echo "\n<a href='test_session.php'>Refresh to verify session</a>";
        echo "\n<a href='index.php?m=system'>Go to System Admin</a>";
    } else {
        echo "❌ LOGIN FAILED\n";
    }
    echo "</pre>";
    exit;
}

// Show login form
echo "Session ID: " . session_id() . "\n";
echo "Session status: " . (session_status() === PHP_SESSION_ACTIVE ? 'ACTIVE' : 'INACTIVE') . "\n";
echo "</pre>";

?>
<h2>Test Login</h2>
<form method="POST">
    <p>Username: <input type="text" name="username" value="admin"></p>
    <p>Password: <input type="password" name="password" value="admin"></p>
    <p><button type="submit" name="login" value="1">Login</button></p>
</form>