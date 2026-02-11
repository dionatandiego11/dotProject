<?php
/**
 * Shared helpers for Dashboard controllers.
 *
 * @package DotProject\Api\Controller\Dashboard
 */

declare(strict_types=1);

namespace DotProject\Api\Controller\Dashboard;

use DotProject\Core\TenantContext;

trait DashboardHelperTrait
{
    private ?string $unidadeNivelColumn = null;
    private ?string $unidadeStatusColumn = null;
    private ?bool $hasModernTables = null;
    /**
     * @var array<string, bool>
     */
    private array $tableHasTenantColumn = [];

    protected function checkModernTables(): bool
    {
        if ($this->hasModernTables !== null) {
            return $this->hasModernTables;
        }

        try {
            $db = \DotProject\Core\Database::getInstance();
            $result = $db->fetchValue("SELECT COUNT(*) FROM information_schema.tables
                WHERE table_schema = DATABASE()
                AND table_name IN ('dotp_programas', 'dotp_projects', 'dotp_etapas')");
            $this->hasModernTables = ((int) $result) >= 3;
        } catch (\Exception $e) {
            $this->hasModernTables = false;
        }

        return $this->hasModernTables;
    }

    protected function getUnidadeNivelColumn(): string
    {
        if ($this->unidadeNivelColumn !== null) {
            return $this->unidadeNivelColumn;
        }

        $db = \DotProject\Core\Database::getInstance();
        $exists = $db->fetchValue(
            "SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = 'dotp_unidades_organizacionais'
               AND column_name = 'unidade_nivel_id'"
        );

        $this->unidadeNivelColumn = ((int) $exists > 0) ? 'unidade_nivel_id' : 'unidade_nivel';
        return $this->unidadeNivelColumn;
    }

    protected function getUnidadeStatusColumn(): ?string
    {
        if ($this->unidadeStatusColumn !== null) {
            return $this->unidadeStatusColumn;
        }

        $db = \DotProject\Core\Database::getInstance();
        $hasStatus = $db->fetchValue(
            "SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = 'dotp_unidades_organizacionais'
               AND column_name = 'unidade_status'"
        );

        if ((int) $hasStatus > 0) {
            $this->unidadeStatusColumn = 'unidade_status';
            return $this->unidadeStatusColumn;
        }

        $hasAtiva = $db->fetchValue(
            "SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = 'dotp_unidades_organizacionais'
               AND column_name = 'unidade_ativa'"
        );

        $this->unidadeStatusColumn = ((int) $hasAtiva > 0) ? 'unidade_ativa' : null;
        return $this->unidadeStatusColumn;
    }

    protected function getUnidadeStatusFilter(string $alias): string
    {
        $column = $this->getUnidadeStatusColumn();
        if ($column === 'unidade_status') {
            return "{$alias}.unidade_status = 'ativo'";
        }
        if ($column === 'unidade_ativa') {
            return "{$alias}.unidade_ativa = 1";
        }
        return '1=1';
    }

    protected function tenantAndCondition(string $table, ?string $alias = null): string
    {
        $tenantId = $this->getTenantId();
        if ($tenantId === null || !$this->tableHasTenantColumn($table)) {
            return '';
        }

        $column = $alias !== null && $alias !== ''
            ? $alias . '.tenant_id'
            : 'tenant_id';

        return " AND {$column} = {$tenantId}";
    }

    protected function tenantWhereCondition(string $table, ?string $alias = null): string
    {
        $tenantId = $this->getTenantId();
        if ($tenantId === null || !$this->tableHasTenantColumn($table)) {
            return '';
        }

        $column = $alias !== null && $alias !== ''
            ? $alias . '.tenant_id'
            : 'tenant_id';

        return " WHERE {$column} = {$tenantId}";
    }

    private function getTenantId(): ?int
    {
        if (!TenantContext::isEnabled()) {
            return null;
        }

        $tenantId = TenantContext::getTenantId();
        if ($tenantId === null || $tenantId <= 0) {
            return null;
        }

        return $tenantId;
    }

    private function tableHasTenantColumn(string $table): bool
    {
        $table = trim($table, '`');
        if (array_key_exists($table, $this->tableHasTenantColumn)) {
            return $this->tableHasTenantColumn[$table];
        }

        try {
            $db = \DotProject\Core\Database::getInstance();
            $exists = (int) $db->fetchValue(
                "SELECT COUNT(*) FROM information_schema.columns
                 WHERE table_schema = DATABASE()
                   AND table_name = ?
                   AND column_name = 'tenant_id'",
                [$table]
            );
            $this->tableHasTenantColumn[$table] = $exists > 0;
        } catch (\Throwable $e) {
            $this->tableHasTenantColumn[$table] = false;
        }

        return $this->tableHasTenantColumn[$table];
    }
}
