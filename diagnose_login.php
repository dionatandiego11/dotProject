<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

define("DP_BASE_DIR", "/var/www/html");
require_once "includes/config.php";

echo "<pre>";
echo "=== DIAGNÓSTICO DE LOGIN ===\n\n";

$db = new mysqli($dPconfig["dbhost"], $dPconfig["dbuser"], $dPconfig["dbpass"], $dPconfig["dbname"]);

if ($db->connect_error) {
    die("Conexão falhou: " . $db->connect_error);
}

echo "1. Conexão com banco: OK\n\n";

// Verificar usuário admin
$result = $db->query("SELECT user_id, user_username, user_password FROM dotp_users WHERE user_username = 'admin'");

if (!$result || $result->num_rows == 0) {
    echo "ERRO: Usuário 'admin' NÃO encontrado na tabela dotp_users!\n";

    // Listar todos os usuários
    echo "\nUsuários existentes:\n";
    $all = $db->query("SELECT user_username FROM dotp_users");
    if ($all) {
        while ($u = $all->fetch_assoc()) {
            echo "- " . $u['user_username'] . "\n";
        }
    }
    exit;
}

$row = $result->fetch_assoc();
echo "2. Usuário encontrado:\n";
echo "   - ID: " . $row['user_id'] . "\n";
echo "   - Username: " . $row['user_username'] . "\n";
echo "   - Hash atual: " . $row['user_password'] . "\n";
echo "   - Tamanho hash: " . strlen($row['user_password']) . " chars\n\n";

// Testar senha
$testPassword = "admin";
$storedHash = $row['user_password'];
$md5Calculated = md5($testPassword);

echo "3. Teste de senha 'admin':\n";
echo "   - MD5 calculado: $md5Calculated\n";
echo "   - Hash do banco: $storedHash\n";
echo "   - São iguais? " . ($md5Calculated === $storedHash ? "SIM" : "NÃO") . "\n\n";

// Verificar se é hash moderno
$info = password_get_info($storedHash);
echo "4. Tipo de hash:\n";
echo "   - Algoritmo: " . $info['algo'] . " (" . ($info['algo'] === 0 ? "Desconhecido/MD5" : $info['algoName']) . ")\n\n";

if ($info['algo'] !== 0) {
    echo "   - Testando password_verify... ";
    echo (password_verify($testPassword, $storedHash) ? "OK" : "FALHOU") . "\n";
}

// Forçar reset para MD5
echo "\n5. Resetando senha para MD5('admin')...\n";
if ($db->query("UPDATE dotp_users SET user_password = '$md5Calculated' WHERE user_id = " . $row['user_id'])) {
    echo "   Senha resetada com sucesso!\n";
} else {
    echo "   ERRO: " . $db->error . "\n";
}

// Verificar novamente
$result2 = $db->query("SELECT user_password FROM dotp_users WHERE user_id = " . $row['user_id']);
$row2 = $result2->fetch_assoc();
echo "   - Novo hash: " . $row2['user_password'] . "\n";
echo "   - Confere com MD5? " . ($row2['user_password'] === $md5Calculated ? "SIM" : "NÃO") . "\n";

echo "\n=== FIM ===\n";
echo "\n<a href='index.php'>Tentar login agora</a>";
echo "</pre>";
