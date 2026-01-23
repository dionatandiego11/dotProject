<?php
/**
 * Simple PHP session test - no dotProject code
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start simple session
session_name('testsession');
session_start();

echo "<h1>Simple PHP Session Test</h1><pre>";

echo "Session ID: " . session_id() . "\n";
echo "Session name: " . session_name() . "\n";

// Check for counter
if (!isset($_SESSION['counter'])) {
    $_SESSION['counter'] = 0;
    echo "\nThis is your FIRST visit (session just created)\n";
} else {
    $_SESSION['counter']++;
    echo "\nSession is WORKING! Visit count: " . $_SESSION['counter'] . "\n";
}

echo "\nSession data: ";
print_r($_SESSION);

echo "\nCookie headers sent:\n";
foreach (headers_list() as $h) {
    if (stripos($h, 'cookie') !== false || stripos($h, 'set-cookie') !== false) {
        echo "  $h\n";
    }
}

echo "</pre>";
echo "<p><strong>Refresh this page</strong> - if counter increases, PHP sessions are working!</p>";
echo "<p><a href='simple_session_test.php'>Click to refresh</a></p>";
