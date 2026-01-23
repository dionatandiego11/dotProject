<?php
error_reporting(E_ALL);
define("DP_BASE_DIR", "/var/www/html");
require_once "includes/config.php";

$db = new mysqli($dPconfig["dbhost"], $dPconfig["dbuser"], $dPconfig["dbpass"], $dPconfig["dbname"]);

if ($db->connect_error) {
    die("Conexão falhou: " . $db->connect_error);
}

// 1. Renomear tabelas
$result = $db->query("SHOW TABLES LIKE \"%dbprefix%%\"");
echo "<pre>Renomeando tabelas...\n";

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_array()) {
        $oldName = $row[0];
        $newName = str_replace("%dbprefix%", "dotp_", $oldName);

        echo "Renomeando $oldName para $newName... ";

        $sql = "RENAME TABLE `$oldName` TO `$newName`";

        if ($db->query($sql)) {
            echo "OK\n";
        } else {
            echo "ERRO: " . $db->error . "\n";
        }
    }
} else {
    echo "Nenhuma tabela com %dbprefix% encontrada.\n";
}

// 2. Corrigir config
echo "\nCorrigindo configs...\n";
$configTable = "dotp_config";
$check = $db->query("SHOW TABLES LIKE 'dotp_config'");
if ($check->num_rows == 0) {
    $configTable = "%dbprefix%config";
}

$sql = "UPDATE $configTable SET config_value = REPLACE(config_value, '%dbprefix%', 'dotp_') WHERE config_value LIKE '%dbprefix%%'";
if ($db->query($sql)) {
    echo "Configurações atualizadas.\n";
} else {
    echo "Tentando atualizar config (tabela original)...\n";
    $db->query("UPDATE `%dbprefix%config` SET config_value = REPLACE(config_value, '%dbprefix%', 'dotp_') WHERE config_value LIKE '%dbprefix%%'");
}

echo "\nConcluído!</pre>";
