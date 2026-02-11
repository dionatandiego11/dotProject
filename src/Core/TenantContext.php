<?php
/**
 * Tenant runtime context for shared-schema multi-tenancy.
 */

declare(strict_types=1);

namespace DotProject\Core;

final class TenantContext
{
    private static ?int $tenantId = null;
    private static ?string $tenantSlug = null;
    private static ?string $tenantHost = null;
    private static string $tenantSource = 'unset';

    private function __construct()
    {
    }

    public static function isEnabled(): bool
    {
        return filter_var(getenv('TENANCY_ENABLED') ?: 'true', FILTER_VALIDATE_BOOL);
    }

    public static function setTenant(
        int $tenantId,
        ?string $tenantSlug = null,
        ?string $tenantHost = null,
        string $source = 'runtime'
    ): void {
        if ($tenantId <= 0) {
            throw new \InvalidArgumentException('Tenant ID must be greater than zero.');
        }

        self::$tenantId = $tenantId;
        self::$tenantSlug = $tenantSlug !== null ? trim($tenantSlug) : null;
        self::$tenantHost = $tenantHost !== null ? trim($tenantHost) : null;
        self::$tenantSource = $source;
    }

    public static function clear(): void
    {
        self::$tenantId = null;
        self::$tenantSlug = null;
        self::$tenantHost = null;
        self::$tenantSource = 'unset';
    }

    public static function hasTenant(): bool
    {
        return self::getTenantId() !== null;
    }

    public static function getTenantId(): ?int
    {
        if (self::$tenantId !== null) {
            return self::$tenantId;
        }

        $allowFallback = filter_var(getenv('TENANCY_DEFAULT_FALLBACK') ?: 'true', FILTER_VALIDATE_BOOL);
        if (!$allowFallback) {
            return null;
        }

        $defaultTenant = (int) (getenv('TENANT_DEFAULT_ID') ?: 1);
        return $defaultTenant > 0 ? $defaultTenant : null;
    }

    public static function getTenantSlug(): ?string
    {
        return self::$tenantSlug;
    }

    public static function getTenantHost(): ?string
    {
        return self::$tenantHost;
    }

    public static function getSource(): string
    {
        return self::$tenantSource;
    }
}
