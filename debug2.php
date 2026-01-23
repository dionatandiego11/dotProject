<?php
error_reporting(E_ALL);
ini_set("display_errors", 1);
echo "<pre>";
try {
    chdir("/var/www/html");
    include "/var/www/html/index.php";
} catch (Throwable $e) {
    echo "\n\nERRO: " . $e->getMessage() . "\n";
    echo "Arquivo: " . $e->getFile() . "\n";
    echo "Linha: " . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString();
}
echo "</pre>";
