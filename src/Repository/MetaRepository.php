<?php
/**
 * Repository de Metas do PPA.
 *
 * @package DotProject\Repository
 */

declare(strict_types=1);

namespace DotProject\Repository;

use DotProject\Core\Database;
use DotProject\Core\TenantContext;

class MetaRepository
{
    private Database $db;
    private TenantContext $tenant;

    public function __construct(Database $db, TenantContext $tenant)
    {
        $this->db = $db;
        $this->tenant = $tenant;
        $this->ensureTable();
    }

    /**
     * Lista metas com filtros opcionais.
     */
    public function findAll(array $filters = []): array
    {
        $where = ['1=1'];
        $params = [];

        $tenantId = $this->tenant->getTenantId();
        if ($tenantId !== null) {
            $where[] = 'm.tenant_id = :tenant_id';
            $params['tenant_id'] = $tenantId;
        }

        if (!empty($filters['acao_id'])) {
            $where[] = 'm.acao_id = :acao_id';
            $params['acao_id'] = (int) $filters['acao_id'];
        }

        if (!empty($filters['ano_referencia'])) {
            $where[] = 'm.ano_referencia = :ano';
            $params['ano'] = (int) $filters['ano_referencia'];
        }

        if (!empty($filters['search'])) {
            $where[] = 'm.descricao LIKE :search';
            $params['search'] = '%' . $filters['search'] . '%';
        }

        $whereSql = implode(' AND ', $where);

        $sql = "
            SELECT
                m.*,
                a.nome AS acao_nome,
                a.codigo AS acao_codigo,
                prog.nome AS programa_nome,
                ppa.nome AS ppa_nome
            FROM dotp_metas m
            LEFT JOIN dotp_acoes a ON a.id = m.acao_id
            LEFT JOIN dotp_programas prog ON prog.id = a.programa_id
            LEFT JOIN dotp_ppa ppa ON ppa.id = prog.ppa_id
            WHERE {$whereSql}
            ORDER BY m.ano_referencia DESC, m.acao_id, m.id
        ";

        $rows = $this->db->fetchAll($sql, $params);

        return array_map(function ($row) {
            $row['percent_realizado'] = $this->calcPercent($row);
            $row['atingida'] = ((float) ($row['valor_realizado'] ?? 0)) >= ((float) ($row['valor_previsto'] ?? 0))
                && ((float) ($row['valor_previsto'] ?? 0)) > 0;
            return $row;
        }, $rows);
    }

    /**
     * Busca uma meta por ID.
     */
    public function findById(int $id): ?array
    {
        $tenantCond = '';
        $params = ['id' => $id];

        $tenantId = $this->tenant->getTenantId();
        if ($tenantId !== null) {
            $tenantCond = ' AND m.tenant_id = :tenant_id';
            $params['tenant_id'] = $tenantId;
        }

        $sql = "
            SELECT
                m.*,
                a.nome AS acao_nome,
                a.codigo AS acao_codigo
            FROM dotp_metas m
            LEFT JOIN dotp_acoes a ON a.id = m.acao_id
            WHERE m.id = :id {$tenantCond}
        ";

        $row = $this->db->fetchOne($sql, $params);
        if (!$row) {
            return null;
        }

        $row['percent_realizado'] = $this->calcPercent($row);
        return $row;
    }

    /**
     * Cria uma nova meta.
     */
    public function create(array $data): int
    {
        $tenantId = $this->tenant->getTenantId();

        $sql = "
            INSERT INTO dotp_metas
            (acao_id, descricao, unidade_medida, valor_previsto, valor_realizado, ano_referencia, observacao, tenant_id)
            VALUES
            (:acao_id, :descricao, :unidade_medida, :valor_previsto, :valor_realizado, :ano_referencia, :observacao, :tenant_id)
        ";

        $this->db->execute($sql, [
            'acao_id' => (int) $data['acao_id'],
            'descricao' => $data['descricao'],
            'unidade_medida' => $data['unidade_medida'] ?? 'unidades',
            'valor_previsto' => (float) ($data['valor_previsto'] ?? 0),
            'valor_realizado' => (float) ($data['valor_realizado'] ?? 0),
            'ano_referencia' => (int) $data['ano_referencia'],
            'observacao' => $data['observacao'] ?? null,
            'tenant_id' => $tenantId,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Atualiza uma meta.
     */
    public function update(int $id, array $data): bool
    {
        $tenantCond = '';
        $params = ['id' => $id];

        $tenantId = $this->tenant->getTenantId();
        if ($tenantId !== null) {
            $tenantCond = ' AND tenant_id = :tenant_id';
            $params['tenant_id'] = $tenantId;
        }

        $sets = [];

        $allowedFields = [
            'acao_id' => 'int',
            'descricao' => 'string',
            'unidade_medida' => 'string',
            'valor_previsto' => 'float',
            'valor_realizado' => 'float',
            'ano_referencia' => 'int',
            'observacao' => 'string',
        ];

        foreach ($allowedFields as $field => $type) {
            if (array_key_exists($field, $data)) {
                $sets[] = "{$field} = :{$field}";
                $value = $data[$field];
                $params[$field] = match ($type) {
                    'int' => (int) $value,
                    'float' => (float) $value,
                    default => $value,
                };
            }
        }

        if (empty($sets)) {
            return false;
        }

        $setSql = implode(', ', $sets);
        $sql = "UPDATE dotp_metas SET {$setSql} WHERE id = :id {$tenantCond}";

        return $this->db->execute($sql, $params) > 0;
    }

    /**
     * Exclui uma meta.
     */
    public function delete(int $id): bool
    {
        $tenantCond = '';
        $params = ['id' => $id];

        $tenantId = $this->tenant->getTenantId();
        if ($tenantId !== null) {
            $tenantCond = ' AND tenant_id = :tenant_id';
            $params['tenant_id'] = $tenantId;
        }

        return $this->db->execute(
            "DELETE FROM dotp_metas WHERE id = :id {$tenantCond}",
            $params
        ) > 0;
    }

    /**
     * Resumo de metas por ação (previsto vs realizado agrupado por ano).
     */
    public function resumoPorAcao(int $acaoId): array
    {
        $params = ['acao_id' => $acaoId];

        $sql = "
            SELECT
                ano_referencia,
                COUNT(*) AS total_metas,
                SUM(valor_previsto) AS total_previsto,
                SUM(valor_realizado) AS total_realizado,
                CASE
                    WHEN SUM(valor_previsto) > 0
                    THEN ROUND(SUM(valor_realizado) / SUM(valor_previsto) * 100, 2)
                    ELSE 0
                END AS percent_realizado
            FROM dotp_metas
            WHERE acao_id = :acao_id
            GROUP BY ano_referencia
            ORDER BY ano_referencia
        ";

        return $this->db->fetchAll($sql, $params);
    }

    // ========================================================================
    // Helpers
    // ========================================================================

    private function calcPercent(array $row): float
    {
        $previsto = (float) ($row['valor_previsto'] ?? 0);
        if ($previsto <= 0) {
            return 0.0;
        }

        return round(((float) ($row['valor_realizado'] ?? 0)) / $previsto * 100, 2);
    }

    /**
     * Garante que a tabela dotp_metas existe (criação defensiva).
     */
    private function ensureTable(): void
    {
        try {
            $this->db->fetchOne('SELECT 1 FROM dotp_metas LIMIT 1');
        } catch (\Throwable $e) {
            $sql = "
                CREATE TABLE IF NOT EXISTS dotp_metas (
                    id              INT AUTO_INCREMENT PRIMARY KEY,
                    acao_id         INT NOT NULL,
                    descricao       VARCHAR(300) NOT NULL,
                    unidade_medida  VARCHAR(50) NOT NULL DEFAULT 'unidades',
                    valor_previsto  DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                    valor_realizado DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                    ano_referencia  SMALLINT NOT NULL,
                    observacao      TEXT NULL,
                    tenant_id       INT(11) NULL DEFAULT NULL,
                    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_metas_acao_ano (acao_id, ano_referencia),
                    INDEX idx_metas_tenant (tenant_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ";
            $this->db->execute($sql);
        }
    }
}
