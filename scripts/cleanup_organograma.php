<?php
require_once __DIR__ . '/../base.php';
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../classes/csscolor.class.php';
require_once __DIR__ . '/../includes/main_functions.php';
require_once __DIR__ . '/../includes/db_adodb.php';
require_once __DIR__ . '/../includes/db_connect.php';

use DotProject\Core\Database;

set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    echo "ERROR [$errno]: $errstr in $errfile:$errline\n";
    return true;
});

function columnExists(Database $db, string $table, string $column): bool
{
    $sql = "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?";
    return ((int) ($db->fetchValue($sql, [$table, $column]) ?? 0)) > 0;
}

function findUnitId(Database $db, string $table, string $statusColumn, int|string $statusValue, string $name, ?int $parentId): ?int
{
    $whereParent = $parentId === null ? "unidade_pai_id IS NULL" : "unidade_pai_id = ?";
    $params = $parentId === null ? [$name, $statusValue] : [$name, $parentId, $statusValue];
    $sql = "SELECT unidade_id FROM {$table} WHERE unidade_nome = ? AND {$whereParent} AND {$statusColumn} = ? LIMIT 1";
    $row = $db->fetchOne($sql, $params);
    return $row ? (int) $row['unidade_id'] : null;
}

function deactivateUnit(Database $db, string $table, string $statusColumn, int|string $inactiveValue, int $id): void
{
    $db->execute("UPDATE {$table} SET {$statusColumn} = ? WHERE unidade_id = ?", [$inactiveValue, $id]);
}

try {
    $db = Database::getInstance();
    $table = $db->table('unidades_organizacionais');

    $statusColumn = columnExists($db, $table, 'unidade_ativa') ? 'unidade_ativa' : 'unidade_status';
    $activeValue = $statusColumn === 'unidade_ativa' ? 1 : 'ativo';
    $inactiveValue = $statusColumn === 'unidade_ativa' ? 0 : 'inativo';

    $pairs = [
        ['Coordenação', 'Secretaria de Administracao'],
        ['Equipe', 'Posturas'],
        ['Equipe', 'STRANSB (Transito)'],
        ['Coordenação', 'Secretaria de Educação'],
        ['Coordenação', 'Secretaria de Desenvolvimento Social'],
        ['Equipe', 'Patrimonio'],
        ['Equipe', 'Coordenador de Engenharia'],
        ['Equipe', 'Almoxarifado'],
        ['Coordenação', 'Secretaria de Fazenda'],
        ['Coordenação', 'Secretaria de Desenvolvimento Economico'],
        ['Equipe', 'Recepcao'],
        ['Equipe', 'Fundiario'],
        ['Equipe', 'Fiscalizacao'],
        ['Coordenação', 'Controladoria'],
        ['Coordenação', 'Secretaria de Agricultura, Pecuaria e Abastecimento'],
        ['Equipe', 'Turismo'],
        ['Equipe', 'Nucleo de Atracao de Empresas e Investimentos'],
        ['Equipe', 'Protecao Social Especial'],
        ['Coordenação', 'Secretaria de Desenvolvimento Social'],
        ['Equipe', 'Centro de Bem Estar Animal (Canil)'],
        ['Equipe', 'Centro de Bem Estar Animal (Canil)'],
        ['Coordenação', 'Secretaria de Administracao'],
        ['Coordenação', 'Secretaria de Administracao'],
        ['Coordenação', 'Secretaria de Meio Ambiente'],
        ['Equipe', 'Projetos'],
        ['Equipe', 'Fundiario'],
        ['Coordenação', 'Secretaria de Desenvolvimento Economico'],
        ['Equipe', 'Educacao Ambiental'],
        ['Equipe', 'Central de Compras, Licitacoes e Contratos'],
        ['Equipe', 'Diretoria Administrativa e Financeira'],
        ['Coordenação', 'Secretaria de Fazenda'],
        ['Equipe', 'Licitacao / Contratos'],
        ['Equipe', 'Central de Compras, Licitacoes e Contratos'],
        ['Secretaria', 'Prefeitura Municipal'],
    ];

    $deleted = 0;
    foreach ($pairs as [$childName, $parentName]) {
        $parentId = findUnitId($db, $table, $statusColumn, $activeValue, $parentName, null);
        if ($parentId === null) {
            echo "Pai nao encontrado: {$parentName}\n";
            continue;
        }
        $childId = findUnitId($db, $table, $statusColumn, $activeValue, $childName, $parentId);
        if ($childId === null) {
            echo "Filha nao encontrada: {$childName} (pai {$parentName})\n";
            continue;
        }
        deactivateUnit($db, $table, $statusColumn, $inactiveValue, $childId);
        $deleted++;
        echo "Removida: {$childName} (pai {$parentName})\n";
    }

    echo "Total removidas: {$deleted}\n";
} catch (Throwable $e) {
    echo "EXCEPTION: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
