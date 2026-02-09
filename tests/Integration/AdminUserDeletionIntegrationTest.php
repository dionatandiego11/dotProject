<?php

declare(strict_types=1);

namespace DotProject\Tests\Integration;

use DotProject\Api\Controller\AdminController;
use DotProject\Api\Request;
use DotProject\Api\Response;
use DotProject\Core\Cache;
use DotProject\Core\Database;
use PHPUnit\Framework\TestCase;

class LegacyFallbackAdminController extends AdminController
{
    protected function ensureUserStatusColumn(): bool
    {
        return false;
    }
}

class AdminUserDeletionIntegrationTest extends TestCase
{
    private Database $db;
    /** @var int[] */
    private array $createdUserIds = [];
    /** @var int[] */
    private array $createdContactIds = [];

    protected function setUp(): void
    {
        $this->db = Database::getInstance();
        (new Cache())->clear();
    }

    protected function tearDown(): void
    {
        if ($this->createdUserIds !== []) {
            $userIds = implode(', ', array_map('intval', $this->createdUserIds));
            $this->db->execute("DELETE FROM dotp_usuario_unidades WHERE vinculo_user_id IN ({$userIds})");
            $this->db->execute("DELETE FROM dotp_users WHERE user_id IN ({$userIds})");
        }

        if ($this->createdContactIds !== []) {
            $contactIds = implode(', ', array_map('intval', $this->createdContactIds));
            $this->db->execute("DELETE FROM dotp_contacts WHERE contact_id IN ({$contactIds})");
        }

        (new Cache())->clear();
    }

    public function testDeleteUsuarioSoftDeletesAndDeactivatesVinculos(): void
    {
        $user = $this->createUserWithContact('it_soft_delete');
        $unidadeId = $this->pickAnyUnidadeId();
        if ($unidadeId === null) {
            $this->markTestSkipped('Base sem unidade para validar desativacao de vinculos.');
        }

        $this->db->insert('usuario_unidades', [
            'vinculo_user_id' => $user['user_id'],
            'vinculo_unidade_id' => $unidadeId,
            'vinculo_role' => 'TECNICO',
            'vinculo_status' => 'ativo',
            'vinculo_is_principal' => 1,
            'vinculo_data_inicio' => date('Y-m-d'),
        ]);

        $request = $this->requestWithUserId(1);
        $response = new Response();
        $controller = new AdminController($request, $response);

        $result = $controller->deleteUsuario($user['user_id']);
        $body = $this->responseBody($result);

        $this->assertSame(200, $this->responseStatus($result));
        $this->assertSame('Usuario desativado com sucesso', (string) ($body['message'] ?? ''));

        $status = $this->db->fetchValue(
            'SELECT user_status FROM dotp_users WHERE user_id = ?',
            [$user['user_id']]
        );
        $this->assertSame(1, (int) $status);

        $ativos = $this->db->fetchValue(
            "SELECT COUNT(*) FROM dotp_usuario_unidades WHERE vinculo_user_id = ? AND vinculo_status = 'ativo'",
            [$user['user_id']]
        );
        $this->assertSame(0, (int) $ativos);
    }

    public function testDeleteUsuarioBlocksSelfDeactivation(): void
    {
        $user = $this->createUserWithContact('it_self_delete');

        $request = $this->requestWithUserId($user['user_id']);
        $response = new Response();
        $controller = new AdminController($request, $response);

        $result = $controller->deleteUsuario($user['user_id']);
        $body = $this->responseBody($result);

        $this->assertSame(422, $this->responseStatus($result));
        $this->assertSame('Validation failed', (string) ($body['message'] ?? ''));
        $this->assertSame(
            'Nao e permitido desativar o proprio usuario',
            (string) ($body['errors']['user'] ?? '')
        );

        $status = $this->db->fetchValue(
            'SELECT user_status FROM dotp_users WHERE user_id = ?',
            [$user['user_id']]
        );
        $this->assertSame(0, (int) $status);
    }

    public function testDeleteUsuarioUsesHardDeleteFallbackWhenStatusSupportFails(): void
    {
        $user = $this->createUserWithContact('it_legacy_delete');

        $request = $this->requestWithUserId(1);
        $response = new Response();
        $controller = new LegacyFallbackAdminController($request, $response);

        $result = $controller->deleteUsuario($user['user_id']);
        $body = $this->responseBody($result);

        $this->assertSame(200, $this->responseStatus($result));
        $this->assertSame('Usuario excluido com sucesso', (string) ($body['message'] ?? ''));

        $userStillExists = $this->db->fetchValue(
            'SELECT COUNT(*) FROM dotp_users WHERE user_id = ?',
            [$user['user_id']]
        );
        $this->assertSame(0, (int) $userStillExists);

        $contactStillExists = $this->db->fetchValue(
            'SELECT COUNT(*) FROM dotp_contacts WHERE contact_id = ?',
            [$user['contact_id']]
        );
        $this->assertSame(0, (int) $contactStillExists);
    }

    public function testCreateUsuarioReturnsPersistedIdForFollowUpVinculoCreation(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $username = 'it_create_user_' . $suffix;

        $request = $this->createMock(Request::class);
        $request->method('getJsonBody')->willReturn([
            'user_username' => $username,
            'user_password' => 'dotproject123',
            'contact_first_name' => 'Integration',
            'contact_last_name' => 'CreateUser',
            'contact_email' => $username . '@integration.test',
            'user_status' => 0,
        ]);
        $request->method('getParam')
            ->willReturnCallback(
                static fn(string $key, mixed $default = null): mixed => $key === '_user_id' ? 1 : $default
            );

        $response = new Response();
        $controller = new AdminController($request, $response);

        $result = $controller->createUsuario();
        $body = $this->responseBody($result);

        $this->assertSame(201, $this->responseStatus($result));
        $userId = (int) ($body['data']['id'] ?? 0);
        $this->assertGreaterThan(0, $userId);

        $this->createdUserIds[] = $userId;
        $contactId = (int) ($this->db->fetchValue(
            'SELECT user_contact FROM dotp_users WHERE user_id = ?',
            [$userId]
        ) ?? 0);
        if ($contactId > 0) {
            $this->createdContactIds[] = $contactId;
        }
    }

    /**
     * @return array{user_id:int, contact_id:int}
     */
    private function createUserWithContact(string $tag): array
    {
        $suffix = bin2hex(random_bytes(4));
        $username = $tag . '_' . $suffix;
        $email = $username . '@integration.test';

        $contactId = $this->db->insert('contacts', [
            'contact_first_name' => 'IT',
            'contact_last_name' => strtoupper($tag),
            'contact_email' => $email,
            'contact_order_by' => 'IT',
            'contact_company' => 'Integration',
            'contact_owner' => 1,
        ]);
        $this->assertNotFalse($contactId, 'Falha ao criar contato de teste');

        $userId = $this->db->insert('users', [
            'user_contact' => (int) $contactId,
            'user_username' => $username,
            'user_password' => password_hash('dotproject123', PASSWORD_BCRYPT),
            'user_parent' => 0,
            'user_type' => 1,
            'user_company' => null,
            'user_department' => 0,
            'user_status' => 0,
            'user_owner' => 1,
        ]);
        $this->assertNotFalse($userId, 'Falha ao criar usuario de teste');

        $this->createdContactIds[] = (int) $contactId;
        $this->createdUserIds[] = (int) $userId;

        return [
            'user_id' => (int) $userId,
            'contact_id' => (int) $contactId,
        ];
    }

    private function requestWithUserId(int $userId): Request
    {
        $request = $this->createMock(Request::class);
        $request->method('getParam')
            ->willReturnCallback(
                static fn(string $key, mixed $default = null): mixed => $key === '_user_id' ? $userId : $default
            );
        return $request;
    }

    private function pickAnyUnidadeId(): ?int
    {
        $value = $this->db->fetchValue(
            "SELECT unidade_id FROM dotp_unidades_organizacionais WHERE unidade_status = 'ativo' ORDER BY unidade_id LIMIT 1"
        );
        return $value === null ? null : (int) $value;
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
