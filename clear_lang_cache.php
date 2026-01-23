<?php
/**
 * Clear session languages cache and verify translation
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/base.php';
require_once __DIR__ . '/bootstrap.php';
require_once DP_BASE_DIR . '/includes/config.php';
require_once DP_BASE_DIR . '/includes/main_functions.php';
require_once DP_BASE_DIR . '/includes/db_adodb.php';
require_once DP_BASE_DIR . '/includes/db_connect.php';
require_once DP_BASE_DIR . '/classes/query.class.php';
require_once DP_BASE_DIR . '/classes/ui.class.php';

session_name('dotproject');
session_start();

// Clear LANGUAGES cache to force reload
unset($_SESSION['LANGUAGES']);
echo "<h1>Clearing Languages Cache</h1>";
echo "<p>✅ Session cache cleared.</p>";

// Force set locale again just to be sure
$AppUI = isset($_SESSION['AppUI']) && is_object($_SESSION['AppUI']) ? $_SESSION['AppUI'] : new CAppUI();
$AppUI->setUserLocale('pt_br', true);
$_SESSION['AppUI'] = $AppUI;
echo "<p>✅ Locale set to pt_br.</p>";

// Verify
echo "<h2>Verification</h2>";
require_once DP_BASE_DIR . '/locales/core.php';

echo "<pre>";
echo "Current Translation Examples:\n";
echo "Projects -> " . ($AppUI->_('Projects')) . "\n";
echo "Tasks -> " . ($AppUI->_('Tasks')) . "\n";
echo "Companies -> " . ($AppUI->_('Companies')) . "\n";
echo "</pre>";

echo "<p><a href='index.php'>Go to System</a></p>";
