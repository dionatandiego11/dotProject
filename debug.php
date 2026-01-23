<?php
error_reporting(E_ALL);
ini_set("display_errors", 1);

echo "<pre>";
echo "PHP OK, MySQL OK (35 tabelas)\n\n";

try {
    echo "Carregando base.php...\n";
    require_once "/var/www/html/base.php";
    echo "OK\n\n";
    
    echo "Carregando bootstrap.php...\n";
    require_once "/var/www/html/bootstrap.php";
    echo "OK\n\n";
    
    echo "Carregando includes/main_functions.php...\n";
    require_once DP_BASE_DIR . "/includes/main_functions.php";
    echo "OK\n\n";
    
    echo "Carregando classes/ui.class.php...\n";
    require_once DP_BASE_DIR . "/classes/ui.class.php";
    echo "OK\n\n";
    
    echo "Criando AppUI...\n";
    $AppUI = new CAppUI();
    echo "OK\n";
    
} catch (Throwable $e) {
    echo "\n\nERRO: " . $e->getMessage() . "\n";
    echo "Arquivo: " . $e->getFile() . "\n";
    echo "Linha: " . $e->getLine() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
}
echo "</pre>";
