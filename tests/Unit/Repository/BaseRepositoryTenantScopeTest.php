<?php

declare(strict_types=1);

namespace Tests\Unit\Repository;

use DotProject\Core\Cache;
use DotProject\Core\Database;
use DotProject\Core\TenantContext;
use DotProject\Repository\BaseRepository;
use PHPUnit\Framework\TestCase;

class BaseRepositoryTenantScopeTest extends TestCase
{
    protected function setUp(): void
    {
        putenv('TENANCY_ENABLED=true');
        putenv('TENANCY_DEFAULT_FALLBACK=false');
        TenantContext::clear();
        TenantContext::setTenant(7, 'tenant-7', 'tenant-7.dotproject.app', 'test');
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        putenv('TENANCY_ENABLED');
        putenv('TENANCY_DEFAULT_FALLBACK');
    }

    public function testFindAddsTenantScopeWhenTableHasTenantColumn(): void
    {
        $db = $this->createMock(Database::class);
        $cache = $this->createMock(Cache::class);

        $cache->expects($this->once())
            ->method('get')
            ->willReturn(null);
        $cache->expects($this->once())
            ->method('set')
            ->with(
                $this->stringContains('tenant:7:find:10'),
                $this->isInstanceOf(\stdClass::class),
                $this->anything()
            )
            ->willReturn(true);

        $db->expects($this->once())
            ->method('fetchColumn')
            ->willReturn(1);

        $db->expects($this->once())
            ->method('fetchOne')
            ->with(
                $this->stringContains('tenant_id = ?'),
                [10, 7]
            )
            ->willReturn([
                'project_id' => 10,
                'project_name' => 'Projeto Tenant 7',
                'tenant_id' => 7,
            ]);

        $repository = new class($db, $cache) extends BaseRepository {
            protected string $table = 'dotp_projects';
            protected string $primaryKey = 'project_id';

            protected function hydrate(array $data): object
            {
                return (object) $data;
            }

            protected function extract(object $entity): array
            {
                return (array) $entity;
            }
        };

        $result = $repository->find(10);

        $this->assertInstanceOf(\stdClass::class, $result);
        $this->assertSame(7, $result->tenant_id);
    }

    public function testFindByInjectsTenantCriteriaAutomatically(): void
    {
        $db = $this->createMock(Database::class);
        $cache = $this->createMock(Cache::class);

        $cache->expects($this->once())
            ->method('get')
            ->willReturn(null);
        $cache->expects($this->once())
            ->method('set')
            ->with(
                $this->stringContains('tenant:7:findBy:'),
                $this->isType('array'),
                $this->anything()
            )
            ->willReturn(true);

        $db->expects($this->once())
            ->method('fetchColumn')
            ->willReturn(1);

        $db->expects($this->once())
            ->method('fetchAllParams')
            ->with(
                $this->stringContains('tenant_id = ?'),
                ['active', 7]
            )
            ->willReturn([
                ['project_id' => 1, 'project_name' => 'A', 'status' => 'active', 'tenant_id' => 7],
            ]);

        $repository = new class($db, $cache) extends BaseRepository {
            protected string $table = 'dotp_projects';
            protected string $primaryKey = 'project_id';

            protected function hydrate(array $data): object
            {
                return (object) $data;
            }

            protected function extract(object $entity): array
            {
                return (array) $entity;
            }
        };

        $result = $repository->findBy(['status' => 'active']);

        $this->assertCount(1, $result);
        $this->assertSame(7, $result[0]->tenant_id);
    }
}
