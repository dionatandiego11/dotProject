<?php
define('DP_BASE_DIR', __DIR__);

// Load Composer autoloader
require_once DP_BASE_DIR . '/vendor/autoload.php';

// Load legacy config for constants
require_once DP_BASE_DIR . '/includes/config.php';
require_once DP_BASE_DIR . '/includes/db_adodb.php'; // Legacy link to init ADODB if needed, but we use our wrapper

use DotProject\Core\Database;

$db = Database::getInstance();

echo "Forcing theme update to 'modern_hybrid'...\n";

// Get all user IDs
$sql = "SELECT user_id FROM users";
$users = $db->fetchAll($sql);

$ids = [0]; // Include default user 0
foreach ($users as $u) {
    $ids[] = $u['user_id'];
}

foreach ($ids as $userId) {
    // Check if preference exists
    $checkSql = "SELECT count(*) FROM user_preferences WHERE pref_user = " . (int) $userId . " AND pref_name = 'UISTYLE'";
    $exists = $db->fetchOne($checkSql);
    $count = is_array($exists) ? reset($exists) : $exists;

    if ($count > 0) {
        $updateSql = "UPDATE user_preferences SET pref_value = 'modern_hybrid' WHERE pref_user = " . (int) $userId . " AND pref_name = 'UISTYLE'";
        $db->execute($updateSql);
        echo "Updated User ID $userId: UISTYLE -> modern_hybrid\n";
    } else {
        $insertSql = "INSERT INTO user_preferences (pref_user, pref_name, pref_value) VALUES (" . (int) $userId . ", 'UISTYLE', 'modern_hybrid')";
        $db->execute($insertSql);
        echo "Inserted User ID $userId: UISTYLE -> modern_hybrid\n";
    }
}

echo "Theme forced successfully for all users.\n";
