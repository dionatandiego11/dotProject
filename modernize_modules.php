<?php
define('DP_BASE_DIR', dirname(__FILE__));
$baseDir = DP_BASE_DIR;
$baseUrl = 'http://localhost';
require_once DP_BASE_DIR . '/includes/config.php';
require_once DP_BASE_DIR . '/includes/main_functions.php';
require_once DP_BASE_DIR . '/includes/db_adodb.php';
require_once DP_BASE_DIR . '/includes/db_connect.php';

// Conectar ao banco
db_connect($dPconfig['dbhost'], $dPconfig['dbname'], $dPconfig['dbuser'], $dPconfig['dbpass']);

$modules_to_disable = ['files', 'forums', 'calendar', 'help'];
$sql = "UPDATE " . $dPconfig['dbprefix'] . "modules SET mod_active = 0, mod_ui_active = 0 WHERE mod_directory IN ('" . implode("','", $modules_to_disable) . "')";

echo "Executando: $sql\n";
db_exec($sql);

if (db_error()) {
    echo "Erro ao desativar módulos: " . db_error() . "\n";
} else {
    echo "Módulos desativados com sucesso: " . implode(', ', $modules_to_disable) . "\n";
}
?>