<?php

declare(strict_types=1);

namespace DotProject\Tests\Integration;

use DotProject\Core\Database;
use DotProject\Service\AuthorizationService;
use PHPUnit\Framework\TestCase;

class AuthorizationServiceIntegrationTest extends TestCase
{
    private Database $db;
    private AuthorizationService $auth;
    /** @var int[] */
    private array $createdProjectIds = [];
    /** @var array<int, array{user_company: int|null, user_type: int}> */
    private array $userSnapshots = [];

    protected function setUp(): void
    {
        $this->db = Database::getInstance();
        $this->auth = AuthorizationService::getInstance();
    }

    protected function tearDown(): void
    {
        foreach ($this->createdProjectIds as $projectId) {
            $this->db->delete('projects', sprintf('project_id = %d', (int) $projectId));
        }
        $this->createdProjectIds = [];

        foreach ($this->userSnapshots as $userId => $snapshot) {
            $this->db->update('users', [
                'user_company' => $snapshot['user_company'],
                'user_type' => $snapshot['user_type'],
            ], sprintf('user_id = %d', (int) $userId));
            $this->auth->clearUserCache((int) $userId);
        }
        $this->userSnapshots = [];
    }

    public function testGetUserRoleNormalizesLegacyRoleValues(): void
    {
        $userId = (int) ($this->db->fetchValue(
            "SELECT user_id FROM dotp_users WHERE user_type = 0 ORDER BY user_id LIMIT 1"
        ) ?? 0);

        if ($userId <= 0) {
            $this->markTestSkipped('Base sem usuario com role legado 0 para validar normalizacao.');
        }

        $this->auth->clearUserCache($userId);
        $role = $this->auth->getUserRole($userId);

        $this->assertSame(AuthorizationService::ROLE_USER, $role);
    }

    public function testCanAccessProjectThroughActiveVinculoWhenUserCompanyIsNull(): void
    {
        [$statusColumn, $activeValue] = $this->resolveVinculoStatusSchema();
        $row = $this->db->fetchOne(
            "SELECT v.vinculo_user_id AS user_id, v.vinculo_unidade_id AS unidade_id
             FROM dotp_usuario_unidades v
             WHERE v.{$statusColumn} = ?
             ORDER BY v.vinculo_user_id
             LIMIT 1",
            [$activeValue]
        );

        if (!$row) {
            $this->markTestSkipped('Base sem vinculo ativo para validar acesso por unidade.');
        }

        $userId = (int) ($row['user_id'] ?? 0);
        $unidadeId = (int) ($row['unidade_id'] ?? 0);
        if ($userId <= 0 || $unidadeId <= 0) {
            $this->markTestSkipped('Vinculo ativo invalido para teste.');
        }

        $this->ensureCompanyExistsForUnidade($unidadeId);
        $this->snapshotUser($userId);
        $updated = $this->db->update('users', [
            'user_company' => null,
            'user_type' => AuthorizationService::ROLE_USER,
        ], sprintf('user_id = %d', $userId));
        $this->assertTrue($updated, 'Falha ao preparar usuario para teste de acesso por vinculo');

        $projectId = $this->insertProject($unidadeId, 'it_auth_vinc_' . bin2hex(random_bytes(3)));
        $this->createdProjectIds[] = $projectId;
        $this->auth->clearUserCache($userId);

        $canAccess = $this->auth->canAccessProject($projectId, AuthorizationService::PERMISSION_VIEW, $userId);
        $this->assertTrue($canAccess);

        $projectIds = $this->auth->getAccessibleProjectIds($userId);
        $this->assertContains($projectId, $projectIds);
    }

    private function snapshotUser(int $userId): void
    {
        if (isset($this->userSnapshots[$userId])) {
            return;
        }

        $row = $this->db->fetchOne(
            "SELECT user_company, user_type FROM dotp_users WHERE user_id = ?",
            [$userId]
        );
        if (!$row) {
            return;
        }

        $this->userSnapshots[$userId] = [
            'user_company' => isset($row['user_company']) && (int) $row['user_company'] > 0 ? (int) $row['user_company'] : null,
            'user_type' => (int) ($row['user_type'] ?? 0),
        ];
    }

    private function insertProject(int $unidadeId, string $name): int
    {
        $id = $this->db->insert('projects', [
            'project_company' => $unidadeId,
            'project_company_internal' => 0,
            'project_department' => 0,
            'project_name' => $name,
            'project_short_name' => substr(preg_replace('/[^A-Za-z0-9]/', '', $name) ?: 'AUTHTEST', 0, 10),
            'project_owner' => 1,
            'project_creator' => 1,
            'project_status' => 1,
            'project_percent_complete' => 0,
            'project_color_identifier' => '#4A90D9',
            'project_priority' => 1,
            'project_type' => 0,
            'project_start_date' => date('Y-m-d H:i:s'),
        ]);

        $this->assertNotFalse($id, 'Falha ao inserir projeto de integração');
        return (int) $id;
    }

    private function ensureCompanyExistsForUnidade(int $unidadeId): void
    {
        $exists = $this->db->fetchValue(
            "SELECT company_id FROM dotp_companies WHERE company_id = ?",
            [$unidadeId]
        );
        if ($exists !== null) {
            return;
        }

        $unidadeNome = (string) ($this->db->fetchValue(
            "SELECT unidade_nome FROM dotp_unidades_organizacionais WHERE unidade_id = ?",
            [$unidadeId]
        ) ?? "Company {$unidadeId}");

        $inserted = $this->db->insert('companies', [
            'company_id' => $unidadeId,
            'company_module' => 0,
            'company_name' => $unidadeNome,
            'company_owner' => 0,
            'company_type' => 0,
        ]);

        $this->assertNotFalse($inserted, 'Falha ao criar company para unidade de teste');
    }

    /**
     * @return array{0: string, 1: int|string}
     */
    private function resolveVinculoStatusSchema(): array
    {
        $hasStatus = (int) ($this->db->fetchValue(
            "SELECT COUNT(*)
             FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = 'dotp_usuario_unidades'
               AND column_name = 'vinculo_status'"
        ) ?? 0);

        if ($hasStatus > 0) {
            return ['vinculo_status', 'ativo'];
        }

        return ['vinculo_ativo', 1];
    }
}
