#!/usr/bin/env php
<?php
/**
 * Script para execução manual do job de verificação de prazos
 * 
 * Uso: php scripts/executar_job.php
 * Cron: 0 6 * * * /usr/bin/php /var/www/dotproject/scripts/executar_job.php >> /var/log/dotproject_jobs.log 2>&1
 */

require_once __DIR__ . '/../base.php';
require_once __DIR__ . '/../bootstrap.php';

use DotProject\Job\VerificaPrazosJob;
use DotProject\Core\Logger;

// Configura timezone
date_default_timezone_set('America/Sao_Paulo');

$logger = Logger::getInstance();
$logger->info('Iniciando script de job manual');

try {
    $job = new VerificaPrazosJob();
    $job->executar();
    
    echo "✅ Job executado com sucesso!\n";
    echo "Data/Hora: " . date('Y-m-d H:i:s') . "\n";
    
    $logger->info('Job executado com sucesso');
    exit(0);
    
} catch (Exception $e) {
    $mensagem = "❌ ERRO: " . $e->getMessage();
    echo $mensagem . "\n";
    
    $logger->error('Erro no job: ' . $e->getMessage());
    $logger->error($e->getTraceAsString());
    
    exit(1);
}
