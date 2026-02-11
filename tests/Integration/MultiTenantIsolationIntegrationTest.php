<?php

declare(strict_types=1);

namespace DotProject\Tests\Integration;

use DotProject\Api\Controller\AuthController;
use DotProject\Api\Controller\ProjectController;
use DotProject\Api\Request;
use DotProject\Api\Response;
use DotProject\Core\Cache;
use DotProject\Core\Database;
use DotProject\Core\TenantContext;
use PHPUnit\Framework\TestCase;

class TenantScopedProjectController extends ProjectController
{
    protected function checkPermission(string $module, string $action): bool
    {
        return true;
    }
}

class MultiTenantIsolationIntegrationTest extends TestCase
{
    private Database $db;
    /** @var int[] */
    private array $createdTenantIds = [];
    /** @var int[] */
    private array $createdProjectIds = [];
    /** @var int[] */
    private array $createdUserIds = [];
    /** @var int[] */
    private array $createdContactIds = [];
    private string|false $previousTenancyEnabled = false;

    protected function setUp(): void
    {
        $this->db = Database::getInstance();
        $this->previousTenancyEnabled = getenv('TENANCY_ENABLED');
        putenv('TENANCY_ENABLED=true');
        TenantContext::clear();
        (new Cache())->clear();
    }

    protected function tearDown(): void
    {
        TenantContext::clear();

        if ($this->createdProjectIds !== []) {
            $ids = implode(',', array_map('intval', array_values(array_unique($this->createdProjectIds))));
            $this->db->execute("DELETE FROM dotp_projects WHERE project_id IN ({$ids})");
        }

        if ($this->createdUserIds !== []) {
            $ids = implode(',', array_map('intval', array_values(array_unique($this->createdUserIds))));
            $this->db->execute("DELETE FROM dotp_usuario_unidades WHERE vinculo_user_id IN ({$ids})");
            $this->db->execute("DELETE FROM dotp_users WHERE user_id IN ({$ids})");
        }

        if ($this->createdContactIds !== []) {
            $ids = implode(',', array_map('intval', array_values(array_unique($this->createdContactIds))));
            $this->db->execute("DELETE FROM dotp_contacts WHERE contact_id IN ({$ids})");
        }

        if ($this->createdTenantIds !== []) {
            $ids = implode(',', array_map('intval', array_values(array_unique($this->createdTenantIds))));
            $this->db->execute("DELETE FROM dotp_tenants WHERE tenant_id IN ({$ids})");
        }

        (new Cache())->clear();

        if ($this->previousTenancyEnabled === false) {
            putenv('TENANCY_ENABLED');
        } else {
            putenv('TENANCY_ENABLED=' . $this->previousTenancyEnabled);
        }
    }

    public function testLoginIsScopedByTenantId(): void
    {
        $this->requireTenantIsolationSupport();

        $tenantA = $this->createTenant('it-login-a');
        $tenantB = $this->createTenant('it-login-b');
        $user = $this->createTenantUser($tenantA, 'it_login_scope');

        $loginRequest = $this->loginRequest($user['username'], $user['password'], $tenantA);
        $loginResponse = new Response();
        $loginController = new AuthController($loginRequest, $loginResponse);
        $loginResult = $loginController->login();
        $loginBody = $this->responseBody($loginResult);

        $this->assertSame(200, $this->responseStatus($loginResult));
        $this->assertNotEmpty($loginBody['token'] ?? null);
        $this->assertNotEmpty($loginBody['refresh_token'] ?? null);

        $crossTenantRequest = $this->loginRequest($user['username'], $user['password'], $tenantB);
        $crossTenantResponse = new Response();
        $crossTenantController = new AuthController($crossTenantRequest, $crossTenantResponse);
        $crossTenantResult = $crossTenantController->login();
        $crossTenantBody = $this->responseBody($crossTenantResult);

        $this->assertSame(401, $this->responseStatus($crossTenantResult));
        $this->assertSame('Invalid credentials', (string) ($crossTenantBody['message'] ?? ''));
    }

    public function testProjectIndexReturnsOnlyRowsFromCurrentTenant(): void
    {
        $this->requireTenantIsolationSupport();

        $tenantA = $this->createTenant('it-projects-a');
        $tenantB = $this->createTenant('it-projects-b');
        $userA = $this->createTenantUser($tenantA, 'it_project_list_a');
        $userB = $this->createTenantUser($tenantB, 'it_project_list_b');

        $searchTag = 'it_tenant_list_' . bin2hex(random_bytes(4));
        $projectA = $this->createTenantProject($tenantA, (int) $userA['id'], $searchTag . '_a');
        $projectB = $this->createTenantProject($tenantB, (int) $userB['id'], $searchTag . '_b');

        TenantContext::setTenant($tenantA, 'tenant-a', null, 'tests');
        $responseA = new Response();
        $controllerA = new TenantScopedProjectController($this->projectIndexRequest((int) $userA['id'], $searchTag), $responseA);
        $resultA = $controllerA->index();
        $bodyA = $this->responseBody($resultA);
        $idsA = $this->extractProjectIds($bodyA);

        $this->assertSame(200, $this->responseStatus($resultA));
        $this->assertContains($projectA, $idsA);
        $this->assertNotContains($projectB, $idsA);

        TenantContext::setTenant($tenantB, 'tenant-b', null, 'tests');
        $responseB = new Response();
        $controllerB = new TenantScopedProjectController($this->projectIndexRequest((int) $userB['id'], $searchTag), $responseB);
        $resultB = $controllerB->index();
        $bodyB = $this->responseBody($resultB);
        $idsB = $this->extractProjectIds($bodyB);

        $this->assertSame(200, $this->responseStatus($resultB));
        $this->assertContains($projectB, $idsB);
        $this->assertNotContains($projectA, $idsB);
    }

    public function testCrossTenantProjectAccessIsDenied(): void
    {
        $this->requireTenantIsolationSupport();

        $tenantA = $this->createTenant('it-cross-a');
        $tenantB = $this->createTenant('it-cross-b');
        $userA = $this->createTenantUser($tenantA, 'it_cross_user_a');
        $userB = $this->createTenantUser($tenantB, 'it_cross_user_b');

        $projectA = $this->createTenantProject($tenantA, (int) $userA['id'], 'it_cross_project_a_' . bin2hex(random_bytes(3)));
        $projectB = $this->createTenantProject($tenantB, (int) $userB['id'], 'it_cross_project_b_' . bin2hex(random_bytes(3)));

        TenantContext::setTenant($tenantA, 'tenant-a', null, 'tests');

        $responseOwn = new Response();
        $controllerOwn = new TenantScopedProjectController($this->projectShowRequest((int) $userA['id'], $projectA), $responseOwn);
        $ownResult = $controllerOwn->show();
        $ownBody = $this->responseBody($ownResult);

        $this->assertSame(200, $this->responseStatus($ownResult));
        $this->assertSame($projectA, (int) ($ownBody['id'] ?? 0));

        $responseCross = new Response();
        $controllerCross = new TenantScopedProjectController($this->projectShowRequest((int) $userA['id'], $projectB), $responseCross);
        $crossResult = $controllerCross->show();
        $crossBody = $this->responseBody($crossResult);

        $this->assertSame(404, $this->responseStatus($crossResult));
        $this->assertSame('Project not found', (string) ($crossBody['message'] ?? ''));
    }

    private function requireTenantIsolationSupport(): void
    {
        if (!$this->hasTable('dotp_tenants')) {
            $this->markTestSkipped('Tabela dotp_tenants ausente para validar isolamento multi-tenant.');
        }

        if (!$this->hasColumn('dotp_users', 'tenant_id') || !$this->hasColumn('dotp_projects', 'tenant_id')) {
            $this->markTestSkipped('Colunas tenant_id ausentes em dotp_users/dotp_projects.');
        }
    }

    private function hasTable(string $table): bool
    {
        return (int) ($this->db->fetchValue(
            "SELECT COUNT(*)
             FROM information_schema.tables
             WHERE table_schema = DATABASE()
               AND table_name = ?",
            [$table]
        ) ?? 0) > 0;
    }

    private function hasColumn(string $table, string $column): bool
    {
        return (int) ($this->db->fetchValue(
            "SELECT COUNT(*)
             FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = ?
               AND column_name = ?",
            [$table, $column]
        ) ?? 0) > 0;
    }

    private function createTenant(string $prefix): int
    {
        $slug = strtolower($prefix . '-' . bin2hex(random_bytes(3)));
        $id = $this->db->insert('tenants', [
            'tenant_slug' => $slug,
            'tenant_name' => strtoupper($prefix),
            'tenant_domain' => $slug . '.integration.test',
            'tenant_active' => 1,
        ]);

        $this->assertNotFalse($id, 'Falha ao criar tenant de teste');

        $tenantId = (int) $id;
        $this->createdTenantIds[] = $tenantId;
        return $tenantId;
    }

    /**
     * @return array{id:int, username:string, password:string}
     */
    private function createTenantUser(int $tenantId, string $tag): array
    {
        $suffix = bin2hex(random_bytes(4));
        $username = $tag . '_' . $tenantId . '_' . $suffix;
        $email = $username . '@integration.test';
        $password = 'DotProject#123';

        $contactData = [
            'contact_first_name' => 'IT',
            'contact_last_name' => strtoupper($tag),
            'contact_email' => $email,
            'contact_order_by' => 'IT',
            'contact_company' => 'Integration',
            'contact_owner' => 1,
        ];
        if ($this->hasColumn('dotp_contacts', 'tenant_id')) {
            $contactData['tenant_id'] = $tenantId;
        }

        $contactId = $this->db->insert('contacts', $contactData);
        $this->assertNotFalse($contactId, 'Falha ao criar contato de tenant');

        $this->createdContactIds[] = (int) $contactId;

        $userData = [
            'user_contact' => (int) $contactId,
            'user_username' => $username,
            'user_password' => password_hash($password, PASSWORD_BCRYPT),
            'user_parent' => 0,
            'user_type' => 1,
            'user_company' => null,
            'user_department' => 0,
            'user_owner' => 1,
            'user_signature' => '',
        ];

        if ($this->hasColumn('dotp_users', 'user_status')) {
            $userData['user_status'] = 0;
        }

        if ($this->hasColumn('dotp_users', 'tenant_id')) {
            $userData['tenant_id'] = $tenantId;
        }

        $userId = $this->db->insert('users', $userData);
        $this->assertNotFalse($userId, 'Falha ao criar usuario de tenant');

        $this->createdUserIds[] = (int) $userId;

        return [
            'id' => (int) $userId,
            'username' => $username,
            'password' => $password,
        ];
    }

    private function createTenantProject(int $tenantId, int $ownerId, string $name): int
    {
        $projectData = [
            'project_company' => 0,
            'project_company_internal' => 0,
            'project_department' => 0,
            'project_name' => $name,
            'project_short_name' => substr(preg_replace('/[^A-Za-z0-9]/', '', $name) ?: 'ITTENANT', 0, 10),
            'project_owner' => $ownerId,
            'project_creator' => $ownerId,
            'project_status' => 1,
            'project_percent_complete' => 0,
            'project_color_identifier' => '#4A90D9',
            'project_priority' => 1,
            'project_type' => 0,
            'project_start_date' => date('Y-m-d H:i:s'),
        ];

        if ($this->hasColumn('dotp_projects', 'tenant_id')) {
            $projectData['tenant_id'] = $tenantId;
        }

        $projectId = $this->db->insert('projects', $projectData);
        $this->assertNotFalse($projectId, 'Falha ao criar projeto de tenant');

        $id = (int) $projectId;
        $this->createdProjectIds[] = $id;
        return $id;
    }

    private function loginRequest(string $username, string $password, int $tenantId): Request
    {
        $body = [
            'username' => $username,
            'password' => $password,
        ];

        $request = $this->createMock(Request::class);
        $request->method('getBody')->willReturn($body);
        $request->method('getBodyParam')
            ->willReturnCallback(static fn(string $key, mixed $default = null): mixed => $body[$key] ?? $default);
        $request->method('getParam')
            ->willReturnCallback(
                static fn(string $key, mixed $default = null): mixed => $key === '_tenant_id' ? $tenantId : $default
            );

        return $request;
    }

    private function projectIndexRequest(int $userId, string $search): Request
    {
        $request = $this->createMock(Request::class);
        $request->method('getParam')
            ->willReturnCallback(
                static fn(string $key, mixed $default = null): mixed => $key === '_user_id' ? $userId : $default
            );
        $request->method('getQueryParam')
            ->willReturnCallback(static function (string $key, mixed $default = null) use ($search): mixed {
                return match ($key) {
                    'search' => $search,
                    'page' => 1,
                    'per_page' => 50,
                    default => $default,
                };
            });

        return $request;
    }

    private function projectShowRequest(int $userId, int $projectId): Request
    {
        $request = $this->createMock(Request::class);
        $request->method('getParam')
            ->willReturnCallback(static function (string $key, mixed $default = null) use ($userId, $projectId): mixed {
                return match ($key) {
                    '_user_id' => $userId,
                    'id' => $projectId,
                    default => $default,
                };
            });

        return $request;
    }

    /**
     * @param array<string, mixed> $body
     * @return int[]
     */
    private function extractProjectIds(array $body): array
    {
        $data = $body['data'] ?? [];
        if (!is_array($data)) {
            return [];
        }

        $ids = [];
        foreach ($data as $item) {
            if (is_array($item) && isset($item['id'])) {
                $ids[] = (int) $item['id'];
            }
        }

        return $ids;
    }

    private function responseStatus(Response $response): int
    {
        $prop = new \ReflectionProperty(Response::class, 'statusCode');
        $prop->setAccessible(true);
        return (int) $prop->getValue($response);
    }

    /**
     * @return array<string, mixed>
     */
    private function responseBody(Response $response): array
    {
        $prop = new \ReflectionProperty(Response::class, 'body');
        $prop->setAccessible(true);
        $body = $prop->getValue($response);
        return is_array($body) ? $body : [];
    }
}
