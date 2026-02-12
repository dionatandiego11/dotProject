<?php
/**
 * Trait for tenant-aware database helpers.
 *
 * Eliminates duplicated tenant helper methods across controllers and services.
 *
 * @package DotProject\Core
 */

declare(strict_types=1);

namespace DotProject\Core;

trait TenantAwareTrait
{
    /** @var array<string, bool> */
    private array $_tenantColumnCache = [];

    /**
     * Build an AND condition for tenant isolation.
     *
     * Returns empty string when tenant context is disabled or the table
     * does not have a `tenant_id` column.
     */
    protected function tenantAndCondition(string $table, ?string $alias = null): string
    {
        $tenantId = $this->resolveTenantId();
        if ($tenantId === null || !$this->tableHasTenantColumn($table)) {
            return '';
        }

        $column = $alias !== null && $alias !== ''
            ? $alias . '.tenant_id'
            : 'tenant_id';

        return " AND {$column} = {$tenantId}";
    }

    /**
     * Check whether a table has the `tenant_id` column.
     */
    protected function tableHasTenantColumn(string $table): bool
    {
        return $this->tableHasColumn($table, 'tenant_id');
    }

    /**
     * Check whether a database table contains a given column.
     */
    protected function tableHasColumn(string $table, string $column): bool
    {
        $tableName = trim($table, '`');
        $cacheKey = $tableName . ':' . $column;
        if (array_key_exists($cacheKey, $this->_tenantColumnCache)) {
            return $this->_tenantColumnCache[$cacheKey];
        }

        $db = $this->getDatabase();
        $exists = (int) ($db->fetchValue(
            "SELECT COUNT(*)
             FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = ?
               AND column_name = ?",
            [$tableName, $column]
        ) ?? 0);

        $this->_tenantColumnCache[$cacheKey] = $exists > 0;
        return $this->_tenantColumnCache[$cacheKey];
    }

    /**
     * Check whether a table exists in the current schema.
     */
    protected function tableExists(string $table): bool
    {
        $db = $this->getDatabase();
        $exists = (int) ($db->fetchValue(
            "SELECT COUNT(*)
             FROM information_schema.tables
             WHERE table_schema = DATABASE()
               AND table_name = ?",
            [$db->table($table)]
        ) ?? 0);

        return $exists > 0;
    }

    /**
     * Resolve the current tenant ID, or null if not applicable.
     */
    protected function resolveTenantId(): ?int
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

    /**
     * Get the Database instance.
     *
     * Implementing classes must provide this.
     */
    abstract protected function getDatabase(): Database;
}
