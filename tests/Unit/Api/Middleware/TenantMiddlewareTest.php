<?php

declare(strict_types=1);

namespace Tests\Unit\Api\Middleware;

use DotProject\Api\Middleware\TenantMiddleware;
use DotProject\Api\Request;
use DotProject\Api\Response;
use DotProject\Core\TenantContext;
use PHPUnit\Framework\TestCase;

class TenantMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        putenv('TENANCY_ENABLED=true');
        putenv('TENANT_DEFAULT_ID=1');
        putenv('TENANCY_DEFAULT_FALLBACK=true');
        putenv('TENANT_ALLOW_HEADER_OVERRIDE=false');
        TenantContext::clear();
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        putenv('TENANCY_ENABLED');
        putenv('TENANT_DEFAULT_ID');
        putenv('TENANCY_DEFAULT_FALLBACK');
        putenv('TENANT_ALLOW_HEADER_OVERRIDE');
    }

    public function testResolvesDefaultTenantForLocalHost(): void
    {
        $request = new class('localhost') extends Request {
            private string $host;
            private array $params = [];

            public function __construct(string $host)
            {
                $this->host = $host;
            }

            public function getHost(): string
            {
                return $this->host;
            }

            public function getHeader(string $name): ?string
            {
                return null;
            }

            public function getParams(): array
            {
                return $this->params;
            }

            public function getParam(string $key, mixed $default = null): mixed
            {
                return $this->params[$key] ?? $default;
            }

            public function setParams(array $params): void
            {
                $this->params = $params;
            }
        };

        $response = new Response();
        $response->setTerminateOnSend(false);

        $result = TenantMiddleware::handle($request, $response);

        $this->assertTrue($result);
        $this->assertSame(1, TenantContext::getTenantId());
        $this->assertSame(1, $request->getParam('_tenant_id'));
    }

    public function testHeaderOverrideUsesExplicitTenantWhenAllowed(): void
    {
        putenv('TENANT_ALLOW_HEADER_OVERRIDE=true');

        $request = new class extends Request {
            private array $params = [];

            public function __construct()
            {
            }

            public function getHost(): string
            {
                return 'api.dotproject.app';
            }

            public function getHeader(string $name): ?string
            {
                return match (strtolower($name)) {
                    'x-tenant-id' => '9',
                    'x-tenant-slug' => 'tenant-nove',
                    default => null,
                };
            }

            public function getParams(): array
            {
                return $this->params;
            }

            public function getParam(string $key, mixed $default = null): mixed
            {
                return $this->params[$key] ?? $default;
            }

            public function setParams(array $params): void
            {
                $this->params = $params;
            }
        };

        $response = new Response();
        $response->setTerminateOnSend(false);

        $result = TenantMiddleware::handle($request, $response);

        $this->assertTrue($result);
        $this->assertSame(9, TenantContext::getTenantId());
        $this->assertSame('tenant-nove', TenantContext::getTenantSlug());
        $this->assertSame(9, $request->getParam('_tenant_id'));
        $this->assertSame('tenant-nove', $request->getParam('_tenant_slug'));
    }
}
