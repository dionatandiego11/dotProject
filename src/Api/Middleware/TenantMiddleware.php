<?php
/**
 * Resolves tenant for each API request (shared schema strategy).
 */

declare(strict_types=1);

namespace DotProject\Api\Middleware;

use DotProject\Api\Request;
use DotProject\Api\Response;
use DotProject\Core\Database;
use DotProject\Core\Logger;
use DotProject\Core\TenantContext;

class TenantMiddleware
{
    /**
     * Resolve tenant and attach it to request context.
     */
    public static function handle(Request $request, Response $response): bool
    {
        if (!TenantContext::isEnabled()) {
            return true;
        }

        try {
            $tenant = self::resolveTenant($request);
        } catch (\Throwable $e) {
            Logger::warning('Tenant resolution failed, using fallback', [
                'error' => $e->getMessage(),
            ]);
            $tenant = self::fallbackTenant($request, 'exception_fallback');
        }

        if ($tenant === null) {
            $strict = filter_var(getenv('TENANCY_STRICT_MODE') ?: 'false', FILTER_VALIDATE_BOOL);
            if ($strict) {
                $response->error('Unable to resolve tenant for request host.', Response::HTTP_BAD_REQUEST)->send();
                return false;
            }

            $tenant = self::fallbackTenant($request, 'lenient_fallback');
        }

        if ($tenant === null) {
            return true;
        }

        TenantContext::setTenant(
            $tenant['id'],
            $tenant['slug'] ?? null,
            $tenant['host'] ?? null,
            $tenant['source'] ?? 'runtime'
        );

        $request->setParams(array_merge($request->getParams(), [
            '_tenant_id' => $tenant['id'],
            '_tenant_slug' => $tenant['slug'] ?? null,
        ]));

        $response->setHeader('X-Tenant-Id', (string) $tenant['id']);
        if (!empty($tenant['slug'])) {
            $response->setHeader('X-Tenant-Slug', (string) $tenant['slug']);
        }

        return true;
    }

    /**
     * @return array{id:int, slug:?string, host:?string, source:string}|null
     */
    private static function resolveTenant(Request $request): ?array
    {
        $host = $request->getHost();

        if (self::allowHeaderOverride()) {
            $headerTenantId = (int) ($request->getHeader('X-Tenant-Id') ?? 0);
            if ($headerTenantId > 0) {
                return [
                    'id' => $headerTenantId,
                    'slug' => $request->getHeader('X-Tenant-Slug'),
                    'host' => $host !== '' ? $host : null,
                    'source' => 'header_override',
                ];
            }
        }

        if ($host === '' || self::isLocalHost($host)) {
            return self::fallbackTenant($request, 'local_host_default');
        }

        $slug = self::extractTenantSlug($host);
        if ($slug === null) {
            return self::fallbackTenant($request, 'host_without_slug');
        }

        $tenant = self::findTenantInDatabase($host, $slug);
        if ($tenant !== null) {
            return $tenant;
        }

        $allowUnknownHostFallback = filter_var(
            getenv('TENANCY_ALLOW_UNKNOWN_HOST_FALLBACK') ?: 'true',
            FILTER_VALIDATE_BOOL
        );
        if ($allowUnknownHostFallback) {
            return self::fallbackTenant($request, 'unknown_host_default');
        }

        return null;
    }

    private static function allowHeaderOverride(): bool
    {
        return filter_var(getenv('TENANT_ALLOW_HEADER_OVERRIDE') ?: 'false', FILTER_VALIDATE_BOOL);
    }

    private static function isLocalHost(string $host): bool
    {
        $localHosts = [
            'localhost',
            '127.0.0.1',
            '::1',
            'phpfpm',
            'nginx',
            'mariadb',
        ];

        if (in_array($host, $localHosts, true)) {
            return true;
        }

        return str_ends_with($host, '.local') || str_ends_with($host, '.test');
    }

    private static function extractTenantSlug(string $host): ?string
    {
        $parts = array_values(array_filter(explode('.', strtolower($host))));
        if (count($parts) < 3) {
            return null;
        }

        $slug = trim($parts[0]);
        if ($slug === '' || !preg_match('/^[a-z0-9][a-z0-9-]{1,62}$/', $slug)) {
            return null;
        }

        return $slug;
    }

    /**
     * @return array{id:int, slug:?string, host:?string, source:string}|null
     */
    private static function findTenantInDatabase(string $host, string $slug): ?array
    {
        $db = Database::getInstance();
        if (!self::tableExists($db, 'dotp_tenants')) {
            return null;
        }

        $hasDomainColumn = self::columnExists($db, 'dotp_tenants', 'tenant_domain');
        if ($hasDomainColumn) {
            $row = $db->fetchOne(
                "SELECT tenant_id, tenant_slug
                 FROM dotp_tenants
                 WHERE tenant_active = 1
                   AND (tenant_domain = ? OR tenant_slug = ?)
                 ORDER BY CASE WHEN tenant_domain = ? THEN 0 ELSE 1 END
                 LIMIT 1",
                [$host, $slug, $host]
            );
        } else {
            $row = $db->fetchOne(
                "SELECT tenant_id, tenant_slug
                 FROM dotp_tenants
                 WHERE tenant_active = 1
                   AND tenant_slug = ?
                 LIMIT 1",
                [$slug]
            );
        }

        if ($row === null || empty($row['tenant_id'])) {
            return null;
        }

        return [
            'id' => (int) $row['tenant_id'],
            'slug' => isset($row['tenant_slug']) ? (string) $row['tenant_slug'] : $slug,
            'host' => $host,
            'source' => 'subdomain',
        ];
    }

    /**
     * @return array{id:int, slug:?string, host:?string, source:string}|null
     */
    private static function fallbackTenant(Request $request, string $source): ?array
    {
        $tenantId = (int) (getenv('TENANT_DEFAULT_ID') ?: 1);
        if ($tenantId <= 0) {
            return null;
        }

        $defaultSlug = trim((string) (getenv('TENANT_DEFAULT_SLUG') ?: 'default'));
        return [
            'id' => $tenantId,
            'slug' => $defaultSlug !== '' ? $defaultSlug : null,
            'host' => $request->getHost() ?: null,
            'source' => $source,
        ];
    }

    private static function tableExists(Database $db, string $table): bool
    {
        try {
            $count = (int) ($db->fetchColumn(
                "SELECT COUNT(*)
                 FROM information_schema.tables
                 WHERE table_schema = DATABASE()
                   AND table_name = ?",
                [$table]
            ) ?? 0);
            return $count > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private static function columnExists(Database $db, string $table, string $column): bool
    {
        try {
            $count = (int) ($db->fetchColumn(
                "SELECT COUNT(*)
                 FROM information_schema.columns
                 WHERE table_schema = DATABASE()
                   AND table_name = ?
                   AND column_name = ?",
                [$table, $column]
            ) ?? 0);
            return $count > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
