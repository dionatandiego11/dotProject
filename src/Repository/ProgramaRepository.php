<?php
/**
 * Repository de Programas do PPA.
 *
 * @package DotProject\Repository
 */

declare(strict_types=1);

namespace DotProject\Repository;

use DotProject\Core\Database;
use DotProject\Core\TenantContext;

class ProgramaRepository
{
    private Database $db;
    private string $table;
    private ?string $projectsTable;
    private ?string $actionsTable;
    /** @var array<string, bool> */
    private array $tableExistsCache = [];
    /** @var array<string, bool> */
    private array $columnExistsCache = [];

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
        $this->table = $this->resolveProgramTable();
        $this->projectsTable = $this->resolveProjectsTable();
        $this->actionsTable = $this->resolveActionsTable();
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

        if (!empty($filters['unidade_id'])) {
            $where[] = 'p.unidade_id = ?';
            $params[] = (int) $filters['unidade_id'];
        }

        if (!empty($filters['ppa_id']) && $this->tableHasColumn($this->table, 'ppa_id')) {
            $where[] = 'p.ppa_id = ?';
            $params[] = (int) $filters['ppa_id'];
        }

        if (!empty($filters['search'])) {
            $where[] = '(p.nome LIKE ? OR p.codigo LIKE ?)';
            $like = '%' . trim((string) $filters['search']) . '%';
            $params[] = $like;
            $params[] = $like;
        }

        $sql = sprintf(
            'SELECT p.*, u.unidade_nome,%s,%s FROM `%s` p
             LEFT JOIN `dotp_unidades_organizacionais` u ON u.unidade_id = p.unidade_id%s
             WHERE %s
             ORDER BY p.nome ASC',
            $this->buildProjectCountSql(),
            $this->buildActionCountSql(),
            $this->table,
            $this->tenantAndCondition('dotp_unidades_organizacionais', 'u'),
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
            'SELECT p.*, u.unidade_nome,%s,%s FROM `%s` p
             LEFT JOIN `dotp_unidades_organizacionais` u ON u.unidade_id = p.unidade_id%s
             WHERE p.id = ?%s
             LIMIT 1',
            $this->buildProjectCountSql(),
            $this->buildActionCountSql(),
            $this->table,
            $this->tenantAndCondition('dotp_unidades_organizacionais', 'u'),
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
            throw new \InvalidArgumentException('Nenhum dado válido para criação do programa.');
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
            'ppa_id' => isset($row['ppa_id']) ? (int) $row['ppa_id'] : null,
            'codigo' => $row['codigo'] ?? null,
            'nome' => (string) ($row['nome'] ?? ''),
            'objetivo' => $row['objetivo'] ?? $row['objetivo_estrategico'] ?? null,
            'descricao' => $row['descricao'] ?? null,
            'unidade_id' => isset($row['unidade_id']) ? (int) $row['unidade_id'] : null,
            'unidade_nome' => $row['unidade_nome'] ?? null,
            'responsavel_politico_id' => isset($row['responsavel_politico_id']) ? (int) $row['responsavel_politico_id'] : null,
            'responsavel_tecnico_id' => isset($row['responsavel_tecnico_id']) ? (int) $row['responsavel_tecnico_id'] : null,
            'estado' => (string) ($row['estado'] ?? 'Planejamento'),
            'percent_execucao' => round((float) ($row['percent_execucao'] ?? 0), 2),
            'prioridade' => $row['prioridade'] ?? null,
            'eixo_ppa' => $row['eixo_ppa'] ?? null,
            'valor_orcamentario' => isset($row['valor_orcamentario']) ? (float) $row['valor_orcamentario'] : null,
            'data_inicio' => $row['data_inicio'] ?? null,
            'data_fim' => $row['data_fim'] ?? null,
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
            'total_projetos' => (int) ($row['total_projetos'] ?? 0),
            'total_acoes' => (int) ($row['total_acoes'] ?? 0),
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
            'ppa_id' => isset($data['ppa_id']) ? (int) $data['ppa_id'] : null,
            'codigo' => $data['codigo'] ?? null,
            'nome' => $data['nome'] ?? null,
            'objetivo' => $data['objetivo'] ?? null,
            'objetivo_estrategico' => $data['objetivo_estrategico'] ?? $data['objetivo'] ?? null,
            'descricao' => $data['descricao'] ?? null,
            'unidade_id' => isset($data['unidade_id']) ? (int) $data['unidade_id'] : null,
            'responsavel_politico_id' => isset($data['responsavel_politico_id']) ? (int) $data['responsavel_politico_id'] : null,
            'responsavel_tecnico_id' => isset($data['responsavel_tecnico_id']) ? (int) $data['responsavel_tecnico_id'] : null,
            'valor_orcamentario' => isset($data['valor_orcamentario']) ? (float) $data['valor_orcamentario'] : null,
            'data_inicio' => $data['data_inicio'] ?? null,
            'data_fim' => $data['data_fim'] ?? null,
            'estado' => $data['estado'] ?? null,
            'percent_execucao' => isset($data['percent_execucao']) ? (float) $data['percent_execucao'] : null,
            'prioridade' => $data['prioridade'] ?? null,
            'observacao_estrategica' => $data['observacao_estrategica'] ?? null,
            'eixo_ppa' => $data['eixo_ppa'] ?? null,
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
            } elseif ($isCreate && in_array($column, ['nome', 'unidade_id'], true)) {
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

    private function buildProjectCountSql(): string
    {
        if ($this->projectsTable === null) {
            return '0 AS total_projetos';
        }

        $tenantProjects = $this->tenantAndCondition($this->projectsTable, 'pr');
        $projectProgramColumn = $this->tableHasColumn($this->projectsTable, 'project_programa_id')
            ? 'project_programa_id'
            : 'programa_id';

        return sprintf(
            '(SELECT COUNT(*) FROM `%s` pr WHERE pr.%s = p.id%s) AS total_projetos',
            $this->projectsTable,
            $projectProgramColumn,
            $tenantProjects
        );
    }

    private function resolveProgramTable(): string
    {
        if ($this->tableExists('dotp_programas')) {
            return 'dotp_programas';
        }

        if ($this->tableExists('programas')) {
            return 'programas';
        }

        throw new \RuntimeException('Tabela de programas não encontrada.');
    }

    private function resolveProjectsTable(): ?string
    {
        if ($this->tableExists('dotp_projects') && $this->tableHasColumn('dotp_projects', 'project_programa_id')) {
            return 'dotp_projects';
        }

        if ($this->tableExists('projetos') && $this->tableHasColumn('projetos', 'programa_id')) {
            return 'projetos';
        }

        return null;
    }

    private function resolveActionsTable(): ?string
    {
        if ($this->tableExists('dotp_acoes') && $this->tableHasColumn('dotp_acoes', 'programa_id')) {
            return 'dotp_acoes';
        }

        if ($this->tableExists('acoes') && $this->tableHasColumn('acoes', 'programa_id')) {
            return 'acoes';
        }

        return null;
    }

    private function buildActionCountSql(): string
    {
        if ($this->actionsTable === null) {
            return '0 AS total_acoes';
        }

        $tenantActions = $this->tenantAndCondition($this->actionsTable, 'ac');
        return sprintf(
            '(SELECT COUNT(*) FROM `%s` ac WHERE ac.programa_id = p.id%s) AS total_acoes',
            $this->actionsTable,
            $tenantActions
        );
    }

    private function generateCode(): string
    {
        return 'PRG-' . date('Y') . '-' . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
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
