<?php
/**
 * Repository de Acoes do PPA.
 *
 * @package DotProject\Repository
 */

declare(strict_types=1);

namespace DotProject\Repository;

use DotProject\Core\Database;
use DotProject\Core\TenantContext;

class AcaoRepository
{
    private Database $db;
    private string $table;
    private ?string $programTable;
    private ?string $ppaTable;
    private ?string $projectsTable;
    private ?string $projectAcaoColumn;
    /** @var array<string, bool> */
    private array $tableExistsCache = [];
    /** @var array<string, bool> */
    private array $columnExistsCache = [];

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
        $this->table = $this->resolveAcaoTable();
        $this->programTable = $this->resolveProgramTable();
        $this->ppaTable = $this->resolvePpaTable();
        [$this->projectsTable, $this->projectAcaoColumn] = $this->resolveProjectLink();
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function list(array $filters = []): array
    {
        $where = ['1=1'];
        $params = [];

        $tenantCondition = $this->tenantAndCondition($this->table, 'a');
        if ($tenantCondition !== '') {
            $where[] = ltrim($tenantCondition, ' AND');
        }

        if (!empty($filters['estado'])) {
            $where[] = 'a.estado = ?';
            $params[] = (string) $filters['estado'];
        }

        if (!empty($filters['programa_id'])) {
            $where[] = 'a.programa_id = ?';
            $params[] = (int) $filters['programa_id'];
        }

        if (
            !empty($filters['ppa_id']) &&
            $this->programTable !== null &&
            $this->tableHasColumn($this->programTable, 'ppa_id')
        ) {
            $where[] = 'p.ppa_id = ?';
            $params[] = (int) $filters['ppa_id'];
        }

        if (!empty($filters['search'])) {
            $where[] = '(a.nome LIKE ? OR a.codigo LIKE ?)';
            $like = '%' . trim((string) $filters['search']) . '%';
            $params[] = $like;
            $params[] = $like;
        }

        $sql = sprintf(
            'SELECT a.%s%s%s,%s FROM `%s` a%s%s WHERE %s ORDER BY a.nome ASC',
            $this->tableSelectFields(),
            $this->programSelectSql(),
            $this->ppaSelectSql(),
            $this->buildProjectAggregatesSql(),
            $this->table,
            $this->programJoinSql(),
            $this->ppaJoinSql(),
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
            'SELECT a.%s%s%s,%s FROM `%s` a%s%s WHERE a.id = ?%s LIMIT 1',
            $this->tableSelectFields(),
            $this->programSelectSql(),
            $this->ppaSelectSql(),
            $this->buildProjectAggregatesSql(),
            $this->table,
            $this->programJoinSql(),
            $this->ppaJoinSql(),
            $this->tenantAndCondition($this->table, 'a')
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
            throw new \InvalidArgumentException('Nenhum dado valido para criacao da acao.');
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

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizeRow(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'programa_id' => isset($row['programa_id']) ? (int) $row['programa_id'] : null,
            'programa_nome' => $row['programa_nome'] ?? null,
            'ppa_id' => isset($row['ppa_id']) ? (int) $row['ppa_id'] : null,
            'ppa_nome' => $row['ppa_nome'] ?? null,
            'codigo' => $row['codigo'] ?? null,
            'nome' => (string) ($row['nome'] ?? ''),
            'objetivo' => $row['objetivo'] ?? null,
            'descricao' => $row['descricao'] ?? null,
            'estado' => (string) ($row['estado'] ?? 'Planejamento'),
            'percent_execucao' => round((float) ($row['percent_execucao'] ?? 0), 2),
            'valor_orcamentario' => isset($row['valor_orcamentario']) ? (float) $row['valor_orcamentario'] : null,
            'data_inicio' => $row['data_inicio'] ?? null,
            'data_fim' => $row['data_fim'] ?? null,
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
            'total_projetos' => (int) ($row['total_projetos'] ?? 0),
            'link_projetos_habilitado' => ((int) ($row['link_projetos_habilitado'] ?? 0)) === 1,
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function buildWritePayload(array $data, bool $isCreate): array
    {
        $payload = [];

        $possibleValues = [
            'programa_id' => isset($data['programa_id']) ? (int) $data['programa_id'] : null,
            'codigo' => isset($data['codigo']) ? strtoupper(trim((string) $data['codigo'])) : null,
            'nome' => isset($data['nome']) ? trim((string) $data['nome']) : null,
            'objetivo' => $data['objetivo'] ?? null,
            'descricao' => $data['descricao'] ?? null,
            'estado' => $data['estado'] ?? null,
            'percent_execucao' => isset($data['percent_execucao']) ? (float) $data['percent_execucao'] : null,
            'valor_orcamentario' => isset($data['valor_orcamentario']) ? (float) $data['valor_orcamentario'] : null,
            'data_inicio' => $data['data_inicio'] ?? null,
            'data_fim' => $data['data_fim'] ?? null,
        ];

        foreach ($possibleValues as $column => $value) {
            if (!$this->tableHasColumn($this->table, $column)) {
                continue;
            }

            if (!$isCreate && !array_key_exists($column, $data)) {
                continue;
            }

            if ($value !== null && $value !== '') {
                $payload[$column] = $value;
            } elseif ($isCreate && in_array($column, ['programa_id', 'nome'], true)) {
                $payload[$column] = $value;
            } elseif ($isCreate || array_key_exists($column, $data)) {
                $payload[$column] = null;
            }
        }

        if ($isCreate && $this->tableHasColumn($this->table, 'estado') && !array_key_exists('estado', $payload)) {
            $payload['estado'] = 'Planejamento';
        }

        if ($isCreate && $this->tableHasColumn($this->table, 'codigo') && empty($payload['codigo'])) {
            $payload['codigo'] = $this->generateCode();
        }

        if ($isCreate && $this->tableHasColumn($this->table, 'percent_execucao') && !array_key_exists('percent_execucao', $payload)) {
            $payload['percent_execucao'] = 0;
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

    private function buildProjectAggregatesSql(): string
    {
        if ($this->projectsTable === null || $this->projectAcaoColumn === null) {
            return '0 AS total_projetos, 0 AS link_projetos_habilitado';
        }

        $tenantProjects = $this->tenantAndCondition($this->projectsTable, 'pr');
        return sprintf(
            '(SELECT COUNT(*) FROM `%s` pr WHERE pr.%s = a.id%s) AS total_projetos, 1 AS link_projetos_habilitado',
            $this->projectsTable,
            $this->projectAcaoColumn,
            $tenantProjects
        );
    }

    private function resolveAcaoTable(): string
    {
        if ($this->tableExists('dotp_acoes')) {
            return 'dotp_acoes';
        }

        if ($this->tableExists('acoes')) {
            return 'acoes';
        }

        $this->createDefaultAcaoTable();
        return 'dotp_acoes';
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

    private function resolvePpaTable(): ?string
    {
        if ($this->tableExists('dotp_ppa')) {
            return 'dotp_ppa';
        }

        if ($this->tableExists('ppa')) {
            return 'ppa';
        }

        return null;
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function resolveProjectLink(): array
    {
        if ($this->tableExists('dotp_projects') && $this->tableHasColumn('dotp_projects', 'project_acao_id')) {
            return ['dotp_projects', 'project_acao_id'];
        }

        if ($this->tableExists('projetos') && $this->tableHasColumn('projetos', 'acao_id')) {
            return ['projetos', 'acao_id'];
        }

        return [null, null];
    }

    private function createDefaultAcaoTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS `dotp_acoes` (
            `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `programa_id` INT(11) UNSIGNED NOT NULL,
            `codigo` VARCHAR(60) NULL,
            `nome` VARCHAR(255) NOT NULL,
            `objetivo` TEXT NULL,
            `descricao` TEXT NULL,
            `estado` VARCHAR(40) NOT NULL DEFAULT 'Planejamento',
            `percent_execucao` DECIMAL(5,2) NOT NULL DEFAULT 0,
            `valor_orcamentario` DECIMAL(14,2) NULL,
            `data_inicio` DATE NULL,
            `data_fim` DATE NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `tenant_id` INT(11) NULL,
            PRIMARY KEY (`id`),
            KEY `idx_acao_programa` (`programa_id`),
            KEY `idx_acao_estado` (`estado`),
            KEY `idx_acao_tenant` (`tenant_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->db->execute($sql);
        $this->tableExistsCache['dotp_acoes'] = true;
    }

    private function tableSelectFields(): string
    {
        return '*';
    }

    private function programSelectSql(): string
    {
        if ($this->programTable === null) {
            return ', NULL AS programa_nome, NULL AS ppa_id';
        }

        if ($this->tableHasColumn($this->programTable, 'ppa_id')) {
            return ', p.nome AS programa_nome, p.ppa_id AS ppa_id';
        }

        return ', p.nome AS programa_nome, NULL AS ppa_id';
    }

    private function ppaSelectSql(): string
    {
        if (
            $this->programTable === null ||
            $this->ppaTable === null ||
            !$this->tableHasColumn($this->programTable, 'ppa_id')
        ) {
            return ', NULL AS ppa_nome';
        }

        return ', ppa.nome AS ppa_nome';
    }

    private function programJoinSql(): string
    {
        if ($this->programTable === null) {
            return '';
        }

        return sprintf(
            ' LEFT JOIN `%s` p ON p.id = a.programa_id%s',
            $this->programTable,
            $this->tenantAndCondition($this->programTable, 'p')
        );
    }

    private function ppaJoinSql(): string
    {
        if (
            $this->programTable === null ||
            $this->ppaTable === null ||
            !$this->tableHasColumn($this->programTable, 'ppa_id')
        ) {
            return '';
        }

        return sprintf(
            ' LEFT JOIN `%s` ppa ON ppa.id = p.ppa_id%s',
            $this->ppaTable,
            $this->tenantAndCondition($this->ppaTable, 'ppa')
        );
    }

    private function generateCode(): string
    {
        return 'ACA-' . date('Y') . '-' . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
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
