<?php
/**
 * Repository de PPA.
 *
 * Resolve automaticamente a tabela disponível (dotp_ppa/ppa) e mantém
 * agregados de execução usando programas vinculados quando o schema possui ppa_id.
 *
 * @package DotProject\Repository
 */

declare(strict_types=1);

namespace DotProject\Repository;

use DotProject\Core\Database;
use DotProject\Core\TenantContext;

class PpaRepository
{
    private Database $db;
    private string $table;
    private ?string $programTable;
    /** @var array<string, bool> */
    private array $tableExistsCache = [];
    /** @var array<string, bool> */
    private array $columnExistsCache = [];

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
        $this->table = $this->resolvePpaTable();
        $this->programTable = $this->resolveProgramTable();
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function list(array $filters = []): array
    {
        $where = ['1=1'];
        $params = [];

        $tenantCondition = $this->tenantAndCondition($this->table, 'p');
        if ($tenantCondition !== '') {
            $where[] = ltrim($tenantCondition, ' AND');
        }

        if (!empty($filters['estado'])) {
            $where[] = 'p.estado = ?';
            $params[] = (string) $filters['estado'];
        }

        if (!empty($filters['search'])) {
            $where[] = 'p.nome LIKE ?';
            $params[] = '%' . trim((string) $filters['search']) . '%';
        }

        $sql = sprintf(
            'SELECT p.*,%s FROM `%s` p WHERE %s ORDER BY p.periodo_inicio DESC, p.id DESC',
            $this->buildProgramAggregatesSql(),
            $this->table,
            implode(' AND ', $where)
        );

        return array_map([$this, 'normalizeRow'], $this->db->fetchAll($sql, $params));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        $sql = sprintf(
            'SELECT p.*,%s FROM `%s` p WHERE p.id = ?%s LIMIT 1',
            $this->buildProgramAggregatesSql(),
            $this->table,
            $this->tenantAndCondition($this->table, 'p')
        );

        $row = $this->db->fetchOne($sql, [$id]);
        return $row ? $this->normalizeRow($row) : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): int
    {
        $payload = $this->buildWritePayload($data, true);
        if ($payload === []) {
            throw new \InvalidArgumentException('Nenhum dado válido para criação do PPA.');
        }

        $columns = array_keys($payload);
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            $this->table,
            implode(', ', array_map(static fn(string $column): string => "`{$column}`", $columns)),
            $placeholders
        );

        $this->db->execute($sql, array_values($payload));
        return (int) $this->db->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): bool
    {
        $payload = $this->buildWritePayload($data, false);
        if ($payload === []) {
            return false;
        }

        $set = [];
        $params = [];
        foreach ($payload as $column => $value) {
            $set[] = "`{$column}` = ?";
            $params[] = $value;
        }
        $params[] = $id;

        $sql = sprintf(
            'UPDATE `%s` SET %s WHERE id = ?%s',
            $this->table,
            implode(', ', $set),
            $this->tenantAndCondition($this->table)
        );

        $this->db->execute($sql, $params);
        return $this->db->affectedRows() > 0;
    }

    public function delete(int $id): bool
    {
        $sql = sprintf(
            'DELETE FROM `%s` WHERE id = ?%s',
            $this->table,
            $this->tenantAndCondition($this->table)
        );
        $this->db->execute($sql, [$id]);
        return $this->db->affectedRows() > 0;
    }

    public function getTable(): string
    {
        return $this->table;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizeRow(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'nome' => (string) ($row['nome'] ?? ''),
            'periodo_inicio' => isset($row['periodo_inicio']) ? (int) $row['periodo_inicio'] : null,
            'periodo_fim' => isset($row['periodo_fim']) ? (int) $row['periodo_fim'] : null,
            'estado' => (string) ($row['estado'] ?? 'Rascunho'),
            'objetivo_geral' => $row['objetivo_geral'] ?? null,
            'prefeito_id' => isset($row['prefeito_id']) ? (int) $row['prefeito_id'] : null,
            'data_publicacao' => $row['data_publicacao'] ?? null,
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
            'total_programas' => (int) ($row['total_programas'] ?? 0),
            'percent_execucao' => round((float) ($row['percent_execucao'] ?? 0), 2),
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function buildWritePayload(array $data, bool $isCreate): array
    {
        $payload = [];
        $inputMap = [
            'nome' => $data['nome'] ?? null,
            'periodo_inicio' => isset($data['periodo_inicio']) ? (int) $data['periodo_inicio'] : null,
            'periodo_fim' => isset($data['periodo_fim']) ? (int) $data['periodo_fim'] : null,
            'estado' => $data['estado'] ?? null,
            'objetivo_geral' => $data['objetivo_geral'] ?? null,
            'prefeito_id' => isset($data['prefeito_id']) ? (int) $data['prefeito_id'] : null,
            'data_publicacao' => $data['data_publicacao'] ?? null,
        ];

        foreach ($inputMap as $column => $value) {
            if (!$this->tableHasColumn($this->table, $column)) {
                continue;
            }

            if (!$isCreate && !array_key_exists($column, $data)) {
                continue;
            }

            if ($value !== null && $value !== '') {
                $payload[$column] = $value;
            } elseif ($isCreate && in_array($column, ['nome', 'periodo_inicio', 'periodo_fim'], true)) {
                // Campos obrigatórios devem seguir para o banco e falhar se vierem inválidos.
                $payload[$column] = $value;
            } else {
                $payload[$column] = null;
            }
        }

        if ($isCreate && $this->tableHasColumn($this->table, 'estado') && !array_key_exists('estado', $payload)) {
            $payload['estado'] = 'Rascunho';
        }

        if ($this->tableHasColumn($this->table, 'updated_at')) {
            $payload['updated_at'] = date('Y-m-d H:i:s');
        }
        if ($isCreate && $this->tableHasColumn($this->table, 'created_at')) {
            $payload['created_at'] = date('Y-m-d H:i:s');
        }

        if ($this->tableHasColumn($this->table, 'tenant_id')) {
            $tenantId = TenantContext::getTenantId();
            if ($tenantId !== null && $tenantId > 0) {
                $payload['tenant_id'] = $tenantId;
            }
        }

        return $payload;
    }

    private function buildProgramAggregatesSql(): string
    {
        if ($this->programTable === null || !$this->tableHasColumn($this->programTable, 'ppa_id')) {
            return '0 AS total_programas, 0 AS percent_execucao';
        }

        $tenantPrograms = $this->tenantAndCondition($this->programTable, 'prog');
        $percentColumn = $this->tableHasColumn($this->programTable, 'percent_execucao')
            ? 'percent_execucao'
            : '0';

        return sprintf(
            '(SELECT COUNT(*) FROM `%s` prog WHERE prog.ppa_id = p.id%s) AS total_programas, ' .
            '(SELECT COALESCE(ROUND(AVG(prog.%s), 2), 0) FROM `%s` prog WHERE prog.ppa_id = p.id%s) AS percent_execucao',
            $this->programTable,
            $tenantPrograms,
            $percentColumn,
            $this->programTable,
            $tenantPrograms
        );
    }

    private function resolvePpaTable(): string
    {
        if ($this->tableExists('dotp_ppa')) {
            return 'dotp_ppa';
        }

        if ($this->tableExists('ppa')) {
            return 'ppa';
        }

        $this->createDefaultPpaTable();
        return 'dotp_ppa';
    }

    private function resolveProgramTable(): ?string
    {
        if ($this->tableExists('dotp_programas')) {
            return 'dotp_programas';
        }

        if ($this->tableExists('programas')) {
            return 'programas';
        }

        return null;
    }

    private function createDefaultPpaTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS `dotp_ppa` (
            `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `nome` VARCHAR(255) NOT NULL,
            `periodo_inicio` SMALLINT NOT NULL,
            `periodo_fim` SMALLINT NOT NULL,
            `estado` VARCHAR(40) NOT NULL DEFAULT 'Rascunho',
            `objetivo_geral` TEXT NULL,
            `prefeito_id` INT(11) NULL,
            `data_publicacao` DATE NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `tenant_id` INT(11) NULL,
            PRIMARY KEY (`id`),
            KEY `idx_ppa_periodo` (`periodo_inicio`, `periodo_fim`),
            KEY `idx_ppa_estado` (`estado`),
            KEY `idx_ppa_tenant` (`tenant_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->db->execute($sql);
        $this->tableExistsCache['dotp_ppa'] = true;
    }

    private function tableExists(string $table): bool
    {
        if (array_key_exists($table, $this->tableExistsCache)) {
            return $this->tableExistsCache[$table];
        }

        $count = (int) ($this->db->fetchValue(
            "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?",
            [$table]
        ) ?? 0);

        $this->tableExistsCache[$table] = $count > 0;
        return $this->tableExistsCache[$table];
    }

    private function tableHasColumn(string $table, string $column): bool
    {
        $cacheKey = $table . ':' . $column;
        if (array_key_exists($cacheKey, $this->columnExistsCache)) {
            return $this->columnExistsCache[$cacheKey];
        }

        $count = (int) ($this->db->fetchValue(
            "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?",
            [$table, $column]
        ) ?? 0);

        $this->columnExistsCache[$cacheKey] = $count > 0;
        return $this->columnExistsCache[$cacheKey];
    }

    private function tenantAndCondition(string $table, ?string $alias = null): string
    {
        if (!TenantContext::isEnabled() || !$this->tableHasColumn($table, 'tenant_id')) {
            return '';
        }

        $tenantId = TenantContext::getTenantId();
        if ($tenantId === null || $tenantId <= 0) {
            return '';
        }

        $column = $alias !== null && $alias !== ''
            ? $alias . '.tenant_id'
            : 'tenant_id';

        return ' AND ' . $column . ' = ' . (int) $tenantId;
    }
}

