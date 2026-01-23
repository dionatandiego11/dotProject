<?php
error_reporting(E_ALL);
define("DP_BASE_DIR", "/var/www/html");
require_once "includes/config.php";

$db = new mysqli($dPconfig["dbhost"], $dPconfig["dbuser"], $dPconfig["dbpass"], $dPconfig["dbname"]);

if ($db->connect_error) {
    die("Conexão falhou: " . $db->connect_error);
}

echo "<pre>";
echo "=== CORREÇÃO DE SCHEMA E SENHA ===\n\n";

// 1. Verificar tamanho da coluna
$result = $db->query("SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = 'dotproject' AND TABLE_NAME = 'dotp_users' AND COLUMN_NAME = 'user_password'");
$row = $result->fetch_assoc();
$len = $row['CHARACTER_MAXIMUM_LENGTH'];

echo "Tamanho atual da coluna user_password: " . $len . "\n";

if ($len < 60) {
    echo "Aumentando coluna para VARCHAR(255)...\n";
    if ($db->query("ALTER TABLE dotp_users MODIFY user_password VARCHAR(255) NOT NULL DEFAULT ''")) {
        echo "Coluna alterada com sucesso!\n";
    } else {
        echo "ERRO ao alterar coluna: " . $db->error . "\n";
    }
} else {
    echo "Tamanho da coluna já é suficiente.\n";
}

// 2. Resetar senha para MD5('admin') pois o hash atual deve estar corrompido (truncado)
echo "\nResetando senha do admin para 'admin' (MD5)...\n";
$md5pass = md5("admin");
if ($db->query("UPDATE dotp_users SET user_password = '$md5pass' WHERE user_username = 'admin'")) {
    echo "Senha resetada com sucesso.\n";
} else {
    echo "Erro ao resetar senha: " . $db->error . "\n";
}

echo "\nConcluído! Tente logar novamente.";
echo "</pre>";
