<?php
/**
 * Configure system to use Brazilian Portuguese
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

$host = 'mariadb';
$user = 'dotproject';
$pass = 'dotproject123';
$db = 'dotproject';
$prefix = 'dotp_';

echo "<h1>Configurar Português Brasileiro</h1><pre>\n";

$mysqli = new mysqli($host, $user, $pass, $db);
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// 1. Update host_locale config
echo "1. Atualizando idioma padrão do sistema...\n";
$mysqli->query("UPDATE {$prefix}config SET config_value = 'pt_br' WHERE config_name = 'host_locale'");
echo "   ✅ host_locale = 'pt_br'\n";

// 2. Update user preferences default locale
echo "\n2. Atualizando preferência padrão de usuários...\n";
$mysqli->query("UPDATE {$prefix}user_preferences SET pref_value = 'pt_br' WHERE pref_name = 'LOCALE'");
echo "   ✅ LOCALE = 'pt_br' para todos os usuários\n";

// 3. Update date format to Brazilian format
echo "\n3. Atualizando formato de data para BR...\n";
$mysqli->query("UPDATE {$prefix}user_preferences SET pref_value = '%d/%m/%Y' WHERE pref_name = 'SHDATEFORMAT'");
echo "   ✅ SHDATEFORMAT = '%d/%m/%Y'\n";

// 4. Update time format
echo "\n4. Atualizando formato de hora...\n";
$mysqli->query("UPDATE {$prefix}user_preferences SET pref_value = '%H:%M' WHERE pref_name = 'TIMEFORMAT'");
echo "   ✅ TIMEFORMAT = '%H:%M' (24h)\n";

// 5. Verify changes
echo "\n5. Verificando alterações...\n";
$result = $mysqli->query("SELECT config_name, config_value FROM {$prefix}config WHERE config_name = 'host_locale'");
if ($row = $result->fetch_assoc()) {
    echo "   host_locale: " . $row['config_value'] . "\n";
}

$result = $mysqli->query("SELECT pref_name, pref_value FROM {$prefix}user_preferences WHERE pref_name IN ('LOCALE', 'SHDATEFORMAT', 'TIMEFORMAT')");
while ($row = $result->fetch_assoc()) {
    echo "   " . $row['pref_name'] . ": " . $row['pref_value'] . "\n";
}

$mysqli->close();

echo "\n</pre>";
echo "<p style='color:green;font-weight:bold'>✅ Sistema configurado para Português Brasileiro!</p>";
echo "<p><strong>IMPORTANTE:</strong> Faça logout e login novamente para aplicar as mudanças.</p>";
echo "<p><a href='index.php?doLogout=1'>Fazer Logout</a></p>";
echo "<p><a href='index.php'>Ir para o sistema</a></p>";
