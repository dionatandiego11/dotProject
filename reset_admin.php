<?php
error_reporting(E_ALL);
define("DP_BASE_DIR", "/var/www/html");
require_once "includes/config.php";

$db = new mysqli($dPconfig["dbhost"], $dPconfig["dbuser"], $dPconfig["dbpass"], $dPconfig["dbname"]);

if ($db->connect_error) {
    die("Conexão falhou: " . $db->connect_error);
}

// Verificar se usuário admin existe
$result = $db->query("SELECT user_id, user_username, user_password FROM dotp_users WHERE user_username = 'admin'");
if ($result && $row = $result->fetch_assoc()) {
    echo "Usuário admin encontrado. ID: " . $row["user_id"] . "<br>";
    echo "Senha atual (hash): " . $row["user_password"] . "<br>";

    // Resetar para md5("admin")
    $newPass = md5("admin");
    if ($db->query("UPDATE dotp_users SET user_password = '$newPass' WHERE user_id = " . $row["user_id"])) {
        echo "Senha resetada para 'admin' (MD5: $newPass)<br>";
    } else {
        echo "Erro ao resetar senha: " . $db->error;
    }
} else {
    echo "Usuário admin NÃO encontrado!<br>";

    // Listar todos os usuários
    echo "Usuários existentes:<br>";
    $all = $db->query("SELECT user_username FROM dotp_users");
    while ($u = $all->fetch_assoc()) {
        echo "- " . $u['user_username'] . "<br>";
    }
}
echo "<br><a href='index.php'>Ir para Login</a>";
