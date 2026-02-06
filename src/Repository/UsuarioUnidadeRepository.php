<?php
/**
 * Repository para Vinculos Usuario-Unidade
 * 
 * @package DotProject\Repository
 */

declare(strict_types=1);

namespace DotProject\Repository;

use DotProject\Core\Database;

class UsuarioUnidadeRepository
{
    protected Database $db;
    /** @var string */
    protected $table = 'dotp_usuario_unidades';
    private ?string $vinculoStatusColumn = null;
    private ?string $unidadeStatusColumn = null;
    private ?string $unidadeNivelColumn = null;
    private ?string $userStatusColumn = null;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Busca todos os vinculos ativos de um usuario
     */
    public function findByUsuario(int $userId): array
    {
        $vinculoStatusColumn = $this->resolveVinculoStatusColumn();
        $unidadeStatusColumn = $this->resolveUnidadeStatusColumn();
        $unidadeNivelColumn = $this->resolveUnidadeNivelColumn();
        $vinculoStatusValue = $this->vinculoStatusValueForSql(true, $vinculoStatusColumn);
        $unidadeStatusValue = $this->unidadeStatusValueForSql(true, $unidadeStatusColumn);

        $sql = "SELECT v.*, 
                    u.unidade_nome, u.unidade_sigla, u.{$unidadeNivelColumn} as unidade_nivel,
                    n.nivel_nome as nivel_tipo
                FROM {$this->table} v
                JOIN dotp_unidades_organizacionais u ON u.unidade_id = v.vinculo_unidade_id
                LEFT JOIN dotp_niveis_hierarquicos n ON n.nivel_id = u.{$unidadeNivelColumn}
                WHERE v.vinculo_user_id = ? AND v.{$vinculoStatusColumn} = ? AND u.{$unidadeStatusColumn} = ?
                ORDER BY n.nivel_ordem, u.unidade_nome";

        $rows = $this->db->fetchAllParams($sql, [$userId, $vinculoStatusValue, $unidadeStatusValue]);
        if (!empty($rows)) {
            return $rows;
        }

        // Fallback: ignore status filters if schema/data are inconsistent.
        $sqlFallback = "SELECT v.*, 
                    u.unidade_nome, u.unidade_sigla, u.{$unidadeNivelColumn} as unidade_nivel,
                    n.nivel_nome as nivel_tipo
                FROM {$this->table} v
                JOIN dotp_unidades_organizacionais u ON u.unidade_id = v.vinculo_unidade_id
                LEFT JOIN dotp_niveis_hierarquicos n ON n.nivel_id = u.{$unidadeNivelColumn}
                WHERE v.vinculo_user_id = ?
                ORDER BY n.nivel_ordem, u.unidade_nome";

        return $this->db->fetchAllParams($sqlFallback, [$userId]);
    }

    /**
     * Busca todos os usuarios vinculados a uma unidade
     */
    public function findByUnidade(int $unidadeId): array
    {
        $vinculoStatusColumn = $this->resolveVinculoStatusColumn();
        $vinculoStatusValue = $this->vinculoStatusValueForSql(true, $vinculoStatusColumn);

        $sql = "SELECT v.*, 
                    u.user_username,
                    c.contact_first_name, c.contact_last_name, c.contact_email
                FROM {$this->table} v
                JOIN dotp_users u ON u.user_id = v.vinculo_user_id
                LEFT JOIN dotp_contacts c ON c.contact_id = u.user_contact
                WHERE v.vinculo_unidade_id = ? AND v.{$vinculoStatusColumn} = ?
                ORDER BY u.user_username";

        $rows = $this->db->fetchAllParams($sql, [$unidadeId, $vinculoStatusValue]);
        if (!empty($rows)) {
            return $rows;
        }

        // Fallback: ignore status filter if data uses unexpected values.
        $sqlFallback = "SELECT v.*, 
                    u.user_username,
                    c.contact_first_name, c.contact_last_name, c.contact_email
                FROM {$this->table} v
                JOIN dotp_users u ON u.user_id = v.vinculo_user_id
                LEFT JOIN dotp_contacts c ON c.contact_id = u.user_contact
                WHERE v.vinculo_unidade_id = ?
                ORDER BY u.user_username";

        return $this->db->fetchAllParams($sqlFallback, [$unidadeId]);
    }

    /**
     * Busca o vinculo principal de um usuario
     */
    public function findPrincipal(int $userId): ?array
    {
        $vinculoStatusColumn = $this->resolveVinculoStatusColumn();
        $unidadeStatusColumn = $this->resolveUnidadeStatusColumn();
        $unidadeNivelColumn = $this->resolveUnidadeNivelColumn();
        $vinculoStatusValue = $this->vinculoStatusValueForSql(true, $vinculoStatusColumn);
        $unidadeStatusValue = $this->unidadeStatusValueForSql(true, $unidadeStatusColumn);

        $sql = "SELECT v.*, 
                    u.unidade_nome, u.unidade_sigla, u.{$unidadeNivelColumn} as unidade_nivel,
                    n.nivel_nome as nivel_tipo
                FROM {$this->table} v
                JOIN dotp_unidades_organizacionais u ON u.unidade_id = v.vinculo_unidade_id
                LEFT JOIN dotp_niveis_hierarquicos n ON n.nivel_id = u.{$unidadeNivelColumn}
                WHERE v.vinculo_user_id = ? AND v.{$vinculoStatusColumn} = ? AND u.{$unidadeStatusColumn} = ?
                ORDER BY v.vinculo_is_principal DESC, v.vinculo_data_inicio DESC
                LIMIT 1";

        $result = $this->db->fetchAllParams($sql, [$userId, $vinculoStatusValue, $unidadeStatusValue]);
        return $result[0] ?? null;
    }

    /**
     * Cria um novo vinculo
     */
    public function criarVinculo(array $dados): int
    {
        $vinculoStatusColumn = $this->resolveVinculoStatusColumn();
        $statusValue = $this->vinculoStatusValueForSql(true, $vinculoStatusColumn);

        if (!empty($dados['is_principal'])) {
            $this->db->execute(
                "UPDATE {$this->table} SET vinculo_is_principal = 0 WHERE vinculo_user_id = ?",
                [$dados['user_id']]
            );
        }

        $sql = "INSERT INTO {$this->table} 
                (vinculo_user_id, vinculo_unidade_id, vinculo_role, vinculo_cargo, 
                 vinculo_nivel_acesso, vinculo_is_principal, vinculo_data_inicio, {$vinculoStatusColumn})
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                vinculo_role = VALUES(vinculo_role),
                vinculo_cargo = VALUES(vinculo_cargo),
                vinculo_nivel_acesso = VALUES(vinculo_nivel_acesso),
                vinculo_is_principal = VALUES(vinculo_is_principal),
                vinculo_data_inicio = VALUES(vinculo_data_inicio),
                vinculo_data_fim = NULL,
                {$vinculoStatusColumn} = VALUES({$vinculoStatusColumn})";

        $this->db->execute($sql, [
            $dados['user_id'],
            $dados['unidade_id'],
            $dados['role'],
            $dados['cargo'] ?? null,
            $dados['nivel_acesso'] ?? 3,
            $dados['is_principal'] ?? 0,
            $dados['data_inicio'] ?? date('Y-m-d'),
            $statusValue,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Atualiza um vinculo
     */
    public function atualizarVinculo(int $vinculoId, array $dados): void
    {
        $vinculoStatusColumn = $this->resolveVinculoStatusColumn();
        $camposPermitidos = [
            'vinculo_role',
            'vinculo_cargo',
            'vinculo_nivel_acesso',
            'vinculo_is_principal',
            'vinculo_data_fim',
            $vinculoStatusColumn,
        ];

        $sets = [];
        $values = [];

        foreach ($dados as $campo => $valor) {
            $dbCampo = str_starts_with($campo, 'vinculo_') ? $campo : 'vinculo_' . $campo;
            if ($campo === 'status' || $campo === 'ativo') {
                $dbCampo = $vinculoStatusColumn;
                $valor = $this->normalizeVinculoStatusValue($valor, $vinculoStatusColumn);
            }
            if (in_array($dbCampo, $camposPermitidos, true)) {
                $sets[] = "$dbCampo = ?";
                $values[] = $valor;
            }
        }

        if (empty($sets)) {
            return;
        }

        if (isset($dados['is_principal']) && $dados['is_principal']) {
            $vinculo = $this->findById($vinculoId);
            if ($vinculo) {
                $this->db->execute(
                    "UPDATE {$this->table} SET vinculo_is_principal = 0 WHERE vinculo_user_id = ?",
                    [$vinculo['vinculo_user_id']]
                );
            }
        }

        $values[] = $vinculoId;
        $sql = "UPDATE {$this->table} SET " . implode(', ', $sets) . " WHERE vinculo_id = ?";

        $this->db->execute($sql, $values);
    }

    /**
     * Desativa um vinculo (demissao/transferencia)
     */
    public function desativarVinculo(int $vinculoId, ?string $dataFim = null): void
    {
        $vinculoStatusColumn = $this->resolveVinculoStatusColumn();
        $inactiveValue = $this->vinculoStatusValueForSql(false, $vinculoStatusColumn);

        $sql = "UPDATE {$this->table} 
                SET {$vinculoStatusColumn} = ?, vinculo_data_fim = ?
                WHERE vinculo_id = ?";

        $this->db->execute($sql, [$inactiveValue, $dataFim ?? date('Y-m-d'), $vinculoId]);
    }

    /**
     * Busca um vinculo especifico
     */
    public function findById(int $vinculoId): ?array
    {
        $unidadeNivelColumn = $this->resolveUnidadeNivelColumn();
        $sql = "SELECT v.*, 
                    u.unidade_nome, u.unidade_sigla, u.{$unidadeNivelColumn} as unidade_nivel,
                    c.contact_first_name, c.contact_last_name, c.contact_email
                FROM {$this->table} v
                JOIN dotp_unidades_organizacionais u ON u.unidade_id = v.vinculo_unidade_id
                JOIN dotp_users usr ON usr.user_id = v.vinculo_user_id
                LEFT JOIN dotp_contacts c ON c.contact_id = usr.user_contact
                WHERE v.vinculo_id = ?";

        $result = $this->db->fetchAll($sql, [$vinculoId]);
        return $result[0] ?? null;
    }

    /**
     * Busca vinculo especifico usuario-unidade
     */
    public function findByUsuarioEUnidade(int $userId, int $unidadeId): ?array
    {
        $vinculoStatusColumn = $this->resolveVinculoStatusColumn();
        $sql = "SELECT * FROM {$this->table} 
                WHERE vinculo_user_id = ? AND vinculo_unidade_id = ?
                ORDER BY {$vinculoStatusColumn} DESC, vinculo_data_inicio DESC
                LIMIT 1";

        $result = $this->db->fetchAll($sql, [$userId, $unidadeId]);
        return $result[0] ?? null;
    }

    /**
     * Remove todos os vinculos de um usuario
     */
    public function removerTodosVinculos(int $userId): void
    {
        $vinculoStatusColumn = $this->resolveVinculoStatusColumn();
        $inactiveValue = $this->vinculoStatusValueForSql(false, $vinculoStatusColumn);
        $activeValue = $this->vinculoStatusValueForSql(true, $vinculoStatusColumn);

        $this->db->execute(
            "UPDATE {$this->table} SET {$vinculoStatusColumn} = ?, vinculo_data_fim = ? WHERE vinculo_user_id = ? AND {$vinculoStatusColumn} = ?",
            [$inactiveValue, date('Y-m-d'), $userId, $activeValue]
        );
    }

    /**
     * Define um vinculo como principal
     */
    public function definirPrincipal(int $vinculoId): void
    {
        $vinculo = $this->findById($vinculoId);
        if (!$vinculo) {
            throw new \InvalidArgumentException('Vinculo nao encontrado');
        }

        $this->db->execute(
            "UPDATE {$this->table} SET vinculo_is_principal = 0 WHERE vinculo_user_id = ?",
            [$vinculo['vinculo_user_id']]
        );

        $this->db->execute(
            "UPDATE {$this->table} SET vinculo_is_principal = 1 WHERE vinculo_id = ?",
            [$vinculoId]
        );
    }

    /**
     * Busca usuarios sem vinculo
     */
    public function findUsuariosSemVinculo(): array
    {
        $vinculoStatusColumn = $this->resolveVinculoStatusColumn();
        $vinculoStatusValue = $this->vinculoStatusValueForSql(true, $vinculoStatusColumn);
        $userStatusFilter = $this->getUserStatusFilterSql('u');
        $sql = "SELECT
                    u.user_id,
                    u.user_username,
                    COALESCE(c.contact_first_name, '') AS user_first_name,
                    COALESCE(c.contact_last_name, '') AS user_last_name,
                    c.contact_email AS user_email
                FROM dotp_users u
                LEFT JOIN dotp_contacts c ON c.contact_id = u.user_contact
                WHERE NOT EXISTS (
                    SELECT 1 FROM {$this->table} v 
                    WHERE v.vinculo_user_id = u.user_id AND v.{$vinculoStatusColumn} = ?
                )
                {$userStatusFilter}
                ORDER BY COALESCE(c.contact_first_name, u.user_username), COALESCE(c.contact_last_name, ''), u.user_username";

        return $this->db->fetchAllParams($sql, [$vinculoStatusValue]);
    }

    /**
     * Busca estatisticas de vinculos
     */
    public function getEstatisticas(): array
    {
        $vinculoStatusColumn = $this->resolveVinculoStatusColumn();
        $vinculoStatusValue = $this->vinculoStatusValueForSql(true, $vinculoStatusColumn);
        $unidadeStatusColumn = $this->resolveUnidadeStatusColumn();
        $unidadeStatusValue = $this->unidadeStatusValueForSql(true, $unidadeStatusColumn);

        return [
            'total_vinculos' => (int) $this->db->fetchColumn("SELECT COUNT(*) FROM {$this->table} WHERE {$vinculoStatusColumn} = {$this->db->quote($vinculoStatusValue)}"),
            'usuarios_vinculados' => (int) $this->db->fetchColumn("SELECT COUNT(DISTINCT vinculo_user_id) FROM {$this->table} WHERE {$vinculoStatusColumn} = {$this->db->quote($vinculoStatusValue)}"),
            'por_unidade' => $this->db->fetchAll(
                "SELECT v.vinculo_unidade_id as unidade_id, u.unidade_nome, COUNT(*) as total 
                 FROM {$this->table} v
                 JOIN dotp_unidades_organizacionais u ON u.unidade_id = v.vinculo_unidade_id
                 WHERE v.{$vinculoStatusColumn} = {$this->db->quote($vinculoStatusValue)} AND u.{$unidadeStatusColumn} = {$this->db->quote($unidadeStatusValue)}
                 GROUP BY v.vinculo_unidade_id
                 ORDER BY total DESC"
            ),
            'por_role' => $this->db->fetchAll(
                "SELECT vinculo_role as role, COUNT(*) as total FROM {$this->table} WHERE {$vinculoStatusColumn} = {$this->db->quote($vinculoStatusValue)} GROUP BY vinculo_role"
            ),
        ];
    }

    private function resolveVinculoStatusColumn(): string
    {
        if ($this->vinculoStatusColumn !== null) {
            return $this->vinculoStatusColumn;
        }
        $exists = (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?",
            [$this->table, 'vinculo_status']
        );
        $this->vinculoStatusColumn = $exists > 0 ? 'vinculo_status' : 'vinculo_ativo';
        return $this->vinculoStatusColumn;
    }

    private function vinculoStatusValueForSql(bool $ativo, string $column): int|string
    {
        if ($column === 'vinculo_status') {
            return $ativo ? 'ativo' : 'inativo';
        }
        return $ativo ? 1 : 0;
    }

    private function resolveUnidadeStatusColumn(): string
    {
        if ($this->unidadeStatusColumn !== null) {
            return $this->unidadeStatusColumn;
        }
        $exists = (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?",
            ['dotp_unidades_organizacionais', 'unidade_ativa']
        );
        $this->unidadeStatusColumn = $exists > 0 ? 'unidade_ativa' : 'unidade_status';
        return $this->unidadeStatusColumn;
    }

    private function unidadeStatusValueForSql(bool $ativo, string $column): int|string
    {
        if ($column === 'unidade_status') {
            return $ativo ? 'ativo' : 'inativo';
        }
        return $ativo ? 1 : 0;
    }

    private function resolveUnidadeNivelColumn(): string
    {
        if ($this->unidadeNivelColumn !== null) {
            return $this->unidadeNivelColumn;
        }
        $exists = (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?",
            ['dotp_unidades_organizacionais', 'unidade_nivel_id']
        );
        $this->unidadeNivelColumn = $exists > 0 ? 'unidade_nivel_id' : 'unidade_nivel';
        return $this->unidadeNivelColumn;
    }

    private function resolveUserStatusColumn(): string
    {
        if ($this->userStatusColumn !== null) {
            return $this->userStatusColumn;
        }

        $hasUserStatus = (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?",
            ['dotp_users', 'user_status']
        );
        if ($hasUserStatus > 0) {
            $this->userStatusColumn = 'user_status';
            return $this->userStatusColumn;
        }

        $hasUserActive = (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?",
            ['dotp_users', 'user_active']
        );
        $this->userStatusColumn = $hasUserActive > 0 ? 'user_active' : '';
        return $this->userStatusColumn;
    }

    private function getUserStatusFilterSql(string $alias): string
    {
        $column = $this->resolveUserStatusColumn();
        if ($column === 'user_status') {
            return "AND {$alias}.user_status = 0";
        }
        if ($column === 'user_active') {
            return "AND {$alias}.user_active = 1";
        }
        return '';
    }

    private function normalizeVinculoStatusValue(mixed $value, string $column): int|string
    {
        if ($column !== 'vinculo_status') {
            return filter_var($value, FILTER_VALIDATE_BOOL) ? 1 : 0;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if (in_array($normalized, ['ativo', 'afastado', 'substituto', 'inativo'], true)) {
                return $normalized;
            }
            if (in_array($normalized, ['1', 'true', 'sim', 'yes'], true)) {
                return 'ativo';
            }
            if (in_array($normalized, ['0', 'false', 'nao', 'no'], true)) {
                return 'inativo';
            }
        }

        if (is_int($value) || is_bool($value)) {
            return ((int) $value) === 1 ? 'ativo' : 'inativo';
        }

        return 'inativo';
    }
}
