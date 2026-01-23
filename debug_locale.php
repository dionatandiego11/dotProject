<?php
/**
 * Debug and fix locale settings
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

echo "<h1>Debug Locale</h1><pre>";

// Create AppUI
$AppUI = isset($_SESSION['AppUI']) && is_object($_SESSION['AppUI']) ? $_SESSION['AppUI'] : new CAppUI();

echo "1. Current locale settings:\n";
echo "   user_locale: " . ($AppUI->user_locale ?? 'not set') . "\n";
echo "   base_locale: " . ($AppUI->base_locale ?? 'not set') . "\n";
echo "   user_lang: " . print_r($AppUI->user_lang ?? [], true) . "\n";

// Check config
echo "\n2. Config values:\n";
echo "   host_locale (config): " . (dPgetConfig('host_locale') ?? 'not set') . "\n";

// Check database config
$q = new DBQuery();
$q->addTable('config');
$q->addQuery('config_value');
$q->addWhere("config_name = 'host_locale'");
$result = $q->loadHash();
echo "   host_locale (database): " . ($result['config_value'] ?? 'not found') . "\n";
$q->clear();

// Check user preference
$q->addTable('user_preferences');
$q->addQuery('pref_value');
$q->addWhere("pref_name = 'LOCALE' AND pref_user = 0");
$result = $q->loadHash();
echo "   LOCALE preference (default): " . ($result['pref_value'] ?? 'not found') . "\n";
$q->clear();

// Force set locale
echo "\n3. Setting locale to pt_br...\n";
$AppUI->setUserLocale('pt_br', true);
echo "   After setUserLocale: " . ($AppUI->user_locale ?? 'not set') . "\n";

// Save to session
$_SESSION['AppUI'] = $AppUI;

// Test translation
echo "\n4. Testing translation:\n";
require_once DP_BASE_DIR . '/locales/core.php';
echo "   \$GLOBALS['translate'] entries: " . count($GLOBALS['translate'] ?? []) . "\n";
echo "   'Projects' => '" . ($GLOBALS['translate']['Projects'] ?? 'NOT FOUND') . "'\n";
echo "   'Tasks' => '" . ($GLOBALS['translate']['Tasks'] ?? 'NOT FOUND') . "'\n";
echo "   'Companies' => '" . ($GLOBALS['translate']['Companies'] ?? 'NOT FOUND') . "'\n";

echo "\n</pre>";
echo "<p style='color:green;'>✅ Locale configurado para: <strong>" . ($AppUI->user_locale ?? 'unknown') . "</strong></p>";
echo "<p><a href='index.php'>Ir para o sistema</a></p>";
