<?php

declare(strict_types=1);

namespace DotProject\Tests\Integration;

use DotProject\Core\Database;
use DotProject\Repository\UsuarioUnidadeRepository;
use PHPUnit\Framework\TestCase;

class UsuarioUnidadeRepositoryIntegrationTest extends TestCase
{
    private Database $db;
    private UsuarioUnidadeRepository $repository;

    protected function setUp(): void
    {
        $this->db = Database::getInstance();
        $this->repository = new UsuarioUnidadeRepository();
    }

    public function testFindUsuariosSemVinculoMatchesReferenceCountAcrossSchemaVariants(): void
    {
        $vinculoStatusColumn = $this->resolveVinculoStatusColumn();
        $activeCondition = $vinculoStatusColumn === 'vinculo_status'
            ? "v.{$vinculoStatusColumn} = 'ativo'"
            : "v.{$vinculoStatusColumn} = 1";

        $userStatusFilter = '';
        $userStatusColumn = $this->resolveUserStatusColumn();
        if ($userStatusColumn === 'user_status') {
            $userStatusFilter = 'AND u.user_status = 0';
        } elseif ($userStatusColumn === 'user_active') {
            $userStatusFilter = 'AND u.user_active = 1';
        }

        $expected = (int) ($this->db->fetchValue(
            "SELECT COUNT(*)
             FROM dotp_users u
             WHERE NOT EXISTS (
                 SELECT 1 FROM dotp_usuario_unidades v
                 WHERE v.vinculo_user_id = u.user_id
                   AND {$activeCondition}
             )
             {$userStatusFilter}"
        ) ?? 0);

        $rows = $this->repository->findUsuariosSemVinculo();
        $this->assertCount($expected, $rows);
    }

    public function testFindUsuariosSemVinculoReturnsCompatibleFields(): void
    {
        $rows = $this->repository->findUsuariosSemVinculo();
        $this->assertIsArray($rows);

        if (empty($rows)) {
            $this->markTestSkipped('Base sem usuários sem vínculo para validar payload.');
        }

        $first = $rows[0];
        $this->assertArrayHasKey('user_id', $first);
        $this->assertArrayHasKey('user_username', $first);
        $this->assertArrayHasKey('user_first_name', $first);
        $this->assertArrayHasKey('user_last_name', $first);
        $this->assertArrayHasKey('user_email', $first);
    }

    private function resolveVinculoStatusColumn(): string
    {
        $hasStatus = (int) ($this->db->fetchValue(
            "SELECT COUNT(*)
             FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = 'dotp_usuario_unidades'
               AND column_name = 'vinculo_status'"
        ) ?? 0);

        return $hasStatus > 0 ? 'vinculo_status' : 'vinculo_ativo';
    }

    private function resolveUserStatusColumn(): string
    {
        $hasUserStatus = (int) ($this->db->fetchValue(
            "SELECT COUNT(*)
             FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = 'dotp_users'
               AND column_name = 'user_status'"
        ) ?? 0);
        if ($hasUserStatus > 0) {
            return 'user_status';
        }

        $hasUserActive = (int) ($this->db->fetchValue(
            "SELECT COUNT(*)
             FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = 'dotp_users'
               AND column_name = 'user_active'"
        ) ?? 0);

        return $hasUserActive > 0 ? 'user_active' : '';
    }
}
