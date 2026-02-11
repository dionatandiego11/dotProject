<?php
/**
 * Sincroniza unidade organizacional com company legada.
 *
 * @package DotProject\Service
 */

declare(strict_types=1);

namespace DotProject\Service;

use DotProject\Core\Database;
use DotProject\Core\TenantContext;

class UnidadeCompanySyncService
{
    private Database $db;
    /** @var array<string, bool> */
    private array $columnPresenceCache = [];

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    public function ensureCompanyForUnidade(int $unidadeId, ?string $unidadeNome = null): bool
    {
        if ($unidadeId <= 0) {
            return false;
        }

        $nome = trim((string) ($unidadeNome ?? ''));
        if ($nome === '') {
            $nome = $this->resolveUnidadeNome($unidadeId);
        }

        if ($nome === '') {
            return false;
        }

        $companiesTable = $this->db->table('companies');
        $existing = $this->db->fetchOne(
            sprintf(
                "SELECT company_id, company_name
                 FROM `%s`
                 WHERE company_id = ?%s
                 LIMIT 1",
                $companiesTable,
                $this->tenantAndCondition($companiesTable)
            ),
            [$unidadeId]
        );

        if ($existing === null) {
            $insertData = [
                'company_id' => $unidadeId,
                'company_module' => 0,
                'company_name' => $nome,
                'company_owner' => 0,
                'company_type' => 0,
            ];
            $tenantId = $this->getTenantId();
            if ($tenantId !== null && $this->hasTableColumn($companiesTable, 'tenant_id')) {
                $insertData['tenant_id'] = $tenantId;
            }
            $inserted = $this->db->insert('companies', $insertData);

            return $inserted !== false;
        }

        $currentName = trim((string) ($existing['company_name'] ?? ''));
        $isLegacyPlaceholder = preg_match('/^Empresa\s+\d+$/i', $currentName) === 1;
        $mustUpdateName = $currentName === '' || $isLegacyPlaceholder || $currentName !== $nome;

        if ($mustUpdateName) {
            return $this->db->update(
                'companies',
                ['company_name' => $nome],
                'company_id = ' . (int) $unidadeId . $this->tenantAndCondition($companiesTable)
            );
        }

        return true;
    }

    private function resolveUnidadeNome(int $unidadeId): string
    {
        $unidadesTable = $this->db->table('unidades_organizacionais');
        $nome = $this->db->fetchValue(
            sprintf(
                "SELECT unidade_nome
                 FROM `%s`
                 WHERE unidade_id = ?%s
                 LIMIT 1",
                $unidadesTable,
                $this->tenantAndCondition($unidadesTable)
            ),
            [$unidadeId]
        );

        return trim((string) ($nome ?? ''));
    }

    private function tenantAndCondition(string $table, ?string $alias = null): string
    {
        $tenantId = $this->getTenantId();
        if ($tenantId === null || !$this->hasTableColumn($table, 'tenant_id')) {
            return '';
        }

        $column = $alias !== null && $alias !== ''
            ? $alias . '.tenant_id'
            : 'tenant_id';

        return " AND {$column} = {$tenantId}";
    }

    private function hasTableColumn(string $table, string $column): bool
    {
        $tableName = trim($table, '`');
        $cacheKey = $tableName . ':' . $column;
        if (array_key_exists($cacheKey, $this->columnPresenceCache)) {
            return $this->columnPresenceCache[$cacheKey];
        }

        $exists = (int) ($this->db->fetchValue(
            "SELECT COUNT(*)
             FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = ?
               AND column_name = ?",
            [$tableName, $column]
        ) ?? 0);

        $this->columnPresenceCache[$cacheKey] = $exists > 0;
        return $this->columnPresenceCache[$cacheKey];
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
}
