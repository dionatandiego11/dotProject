<?php

declare(strict_types=1);

namespace DotProject\Tests\Integration;

use DotProject\Api\Controller\AdminController;
use DotProject\Api\Controller\KanbanController;
use DotProject\Api\Controller\ProjectController;
use DotProject\Api\Request;
use DotProject\Api\Response;
use DotProject\Core\Cache;
use DotProject\Core\Database;
use PHPUnit\Framework\TestCase;

/**
 * Controller-only wrapper to avoid legacy permission coupling in tests.
 */
class IntegrationProjectController extends ProjectController
{
    protected function checkPermission(string $module, string $action): bool
    {
        return true;
    }
}

class CriticalFlowsIntegrationTest extends TestCase
{
    private Database $db;
    /** @var int[] */
    private array $projectIds = [];
    /** @var int[] */
    private array $boardIds = [];
    /** @var int[] */
    private array $createdCompanyIds = [];

    protected function setUp(): void
    {
        $this->db = Database::getInstance();
        (new Cache())->clear();
    }

    protected function tearDown(): void
    {
        $this->cleanupBoards();
        $this->cleanupProjects();
        $this->cleanupCompanies();
        (new Cache())->clear();
    }

    public function testProjectsIndexReturnsCanonicalUnidadePayloadAndFiltersByUnidadeId(): void
    {
        [$unidadeA, $unidadeB] = $this->pickTwoUnidades();
        if ($unidadeA === null || $unidadeB === null) {
            $this->markTestSkipped('Base sem duas unidades para teste de filtro de projetos.');
        }

        $this->ensureCompanyExistsForUnidade((int) $unidadeA['id'], (string) $unidadeA['nome']);
        $this->ensureCompanyExistsForUnidade((int) $unidadeB['id'], (string) $unidadeB['nome']);

        $prefix = 'it_proj_' . bin2hex(random_bytes(4));
        $this->projectIds[] = $this->insertProject((int) $unidadeA['id'], $prefix . '_a');
        $this->projectIds[] = $this->insertProject((int) $unidadeB['id'], $prefix . '_b');

        $request = $this->createMock(Request::class);
        $request->method('getQueryParam')
            ->willReturnCallback(function (string $key, mixed $default = null) use ($prefix, $unidadeA) {
                return match ($key) {
                    'search' => $prefix,
                    'unidade_id' => (int) $unidadeA['id'],
                    'page' => 1,
                    'per_page' => 50,
                    default => $default,
                };
            });
        $request->method('getParam')->willReturn(null);

        $response = new Response();
        $controller = new IntegrationProjectController($request, $response);

        $result = $controller->index();
        $body = $this->responseBody($result);

        $this->assertArrayHasKey('data', $body);
        $this->assertNotEmpty($body['data']);

        foreach ($body['data'] as $item) {
            $this->assertSame((int) $unidadeA['id'], (int) ($item['unidade_id'] ?? 0));
            $this->assertSame((int) $unidadeA['id'], (int) (($item['unidade']['id'] ?? 0)));
            $this->assertNotEmpty($item['unidade']['nome'] ?? null);
            $this->assertSame($item['unidade']['nome'] ?? null, $item['company']['name'] ?? null);
        }
    }

    public function testKanbanCreateBoardAcceptsUnidadeIdAndPersistsBoardCompany(): void
    {
        $unidade = $this->pickAnyUnidade();
        if ($unidade === null) {
            $this->markTestSkipped('Base sem unidade para teste de criação de board.');
        }

        $unidadeId = (int) $unidade['id'];
        $this->ensureCompanyExistsForUnidade($unidadeId, (string) $unidade['nome']);

        $request = $this->createMock(Request::class);
        $request->method('getBody')->willReturn([
            'name' => 'IT Kanban ' . bin2hex(random_bytes(4)),
            'unidade_id' => $unidadeId,
        ]);
        $request->method('getParam')
            ->willReturnCallback(fn(string $key, mixed $default = null) => $key === '_user_id' ? 1 : $default);

        $capturedPayload = null;
        $capturedStatus = null;
        $response = $this->getMockBuilder(Response::class)
            ->onlyMethods(['json', 'error', 'send'])
            ->getMock();

        $response->method('json')
            ->willReturnCallback(function (mixed $data, int $status = 200) use (&$capturedPayload, &$capturedStatus, $response) {
                $capturedPayload = $data;
                $capturedStatus = $status;
                return $response;
            });
        $response->method('error')
            ->willReturnCallback(function (string $message, int $status = 400) use (&$capturedPayload, &$capturedStatus, $response) {
                $capturedPayload = ['error' => true, 'message' => $message];
                $capturedStatus = $status;
                return $response;
            });
        $response->method('send')->willReturnCallback(static function (): void {
        });

        $controller = new KanbanController($request, $response);
        $controller->createBoard();

        $this->assertSame(201, $capturedStatus);
        $this->assertIsArray($capturedPayload);
        $this->assertTrue((bool) ($capturedPayload['success'] ?? false));
        $this->assertSame($unidadeId, (int) ($capturedPayload['data']['company_id'] ?? 0));
        $this->assertSame($unidadeId, (int) ($capturedPayload['data']['unidade_id'] ?? 0));
        $this->assertSame($unidadeId, (int) ($capturedPayload['data']['unidade']['id'] ?? 0));

        $boardId = (int) ($capturedPayload['data']['id'] ?? 0);
        $this->assertGreaterThan(0, $boardId);
        $this->boardIds[] = $boardId;

        $row = $this->db->fetchOne(
            sprintf('SELECT board_company FROM `%s` WHERE board_id = %d', $this->db->table('kanban_boards'), $boardId)
        );
        $this->assertNotNull($row);
        $this->assertSame($unidadeId, (int) ($row['board_company'] ?? 0));
    }

    public function testKanbanCreateBoardResolvesCompanyFromPrincipalVinculoWhenUserCompanyIsMissing(): void
    {
        $userId = 1;
        $vinculo = $this->db->fetchOne(
            "SELECT v.vinculo_unidade_id
             FROM dotp_usuario_unidades v
             JOIN dotp_unidades_organizacionais un ON un.unidade_id = v.vinculo_unidade_id
             WHERE v.vinculo_user_id = 1
               AND v.vinculo_status = 'ativo'
             ORDER BY v.vinculo_is_principal DESC, v.vinculo_id ASC
             LIMIT 1"
        );

        if (!$vinculo || empty($vinculo['vinculo_unidade_id'])) {
            $this->markTestSkipped('Usuario sem vinculo ativo para validar fallback de unidade no Kanban.');
        }

        $expectedUnidade = (int) $vinculo['vinculo_unidade_id'];
        $originalCompany = (int) ($this->db->fetchValue(
            sprintf('SELECT user_company FROM `%s` WHERE user_id = %d', $this->db->table('users'), $userId)
        ) ?? 0);

        $updated = $this->db->update('users', ['user_company' => null], sprintf('user_id = %d', $userId));
        $this->assertTrue($updated, 'Falha ao preparar usuario para teste de fallback de unidade');

        try {
            $request = $this->createMock(Request::class);
            $request->method('getBody')->willReturn([
                'name' => 'IT Kanban fallback ' . bin2hex(random_bytes(4)),
            ]);
            $request->method('getParam')
                ->willReturnCallback(fn(string $key, mixed $default = null) => $key === '_user_id' ? $userId : $default);

            $capturedPayload = null;
            $capturedStatus = null;
            $response = $this->getMockBuilder(Response::class)
                ->onlyMethods(['json', 'error', 'send'])
                ->getMock();

            $response->method('json')
                ->willReturnCallback(function (mixed $data, int $status = 200) use (&$capturedPayload, &$capturedStatus, $response) {
                    $capturedPayload = $data;
                    $capturedStatus = $status;
                    return $response;
                });
            $response->method('error')
                ->willReturnCallback(function (string $message, int $status = 400) use (&$capturedPayload, &$capturedStatus, $response) {
                    $capturedPayload = ['error' => true, 'message' => $message];
                    $capturedStatus = $status;
                    return $response;
                });
            $response->method('send')->willReturnCallback(static function (): void {
            });

            $controller = new KanbanController($request, $response);
            $controller->createBoard();

            $this->assertSame(201, $capturedStatus);
            $this->assertIsArray($capturedPayload);
            $this->assertTrue((bool) ($capturedPayload['success'] ?? false));
            $this->assertSame($expectedUnidade, (int) ($capturedPayload['data']['company_id'] ?? 0));
            $this->assertSame($expectedUnidade, (int) ($capturedPayload['data']['unidade_id'] ?? 0));
            $this->assertSame($expectedUnidade, (int) ($capturedPayload['data']['unidade']['id'] ?? 0));

            $boardId = (int) ($capturedPayload['data']['id'] ?? 0);
            $this->assertGreaterThan(0, $boardId);
            $this->boardIds[] = $boardId;

            $row = $this->db->fetchOne(
                sprintf('SELECT board_company FROM `%s` WHERE board_id = %d', $this->db->table('kanban_boards'), $boardId)
            );
            $this->assertNotNull($row);
            $this->assertSame($expectedUnidade, (int) ($row['board_company'] ?? 0));
        } finally {
            $this->db->update('users', ['user_company' => $originalCompany], sprintf('user_id = %d', $userId));
        }
    }

    public function testKanbanGetBoardReturnsCanonicalUnidadePayload(): void
    {
        $unidade = $this->pickAnyUnidade();
        if ($unidade === null) {
            $this->markTestSkipped('Base sem unidade para teste de leitura de board.');
        }

        $unidadeId = (int) $unidade['id'];
        $this->ensureCompanyExistsForUnidade($unidadeId, (string) $unidade['nome']);

        $createRequest = $this->createMock(Request::class);
        $createRequest->method('getBody')->willReturn([
            'name' => 'IT Kanban getBoard ' . bin2hex(random_bytes(4)),
            'unidade_id' => $unidadeId,
        ]);
        $createRequest->method('getParam')
            ->willReturnCallback(fn(string $key, mixed $default = null) => $key === '_user_id' ? 1 : $default);

        $createPayload = null;
        $createStatus = null;
        $createResponse = $this->getMockBuilder(Response::class)
            ->onlyMethods(['json', 'error', 'send'])
            ->getMock();
        $createResponse->method('json')
            ->willReturnCallback(function (mixed $data, int $status = 200) use (&$createPayload, &$createStatus, $createResponse) {
                $createPayload = $data;
                $createStatus = $status;
                return $createResponse;
            });
        $createResponse->method('error')
            ->willReturnCallback(function (string $message, int $status = 400) use (&$createPayload, &$createStatus, $createResponse) {
                $createPayload = ['error' => true, 'message' => $message];
                $createStatus = $status;
                return $createResponse;
            });
        $createResponse->method('send')->willReturnCallback(static function (): void {
        });

        $createController = new KanbanController($createRequest, $createResponse);
        $createController->createBoard();

        $this->assertSame(201, $createStatus);
        $boardId = (int) ($createPayload['data']['id'] ?? 0);
        $this->assertGreaterThan(0, $boardId);
        $this->boardIds[] = $boardId;

        $getRequest = $this->createMock(Request::class);
        $getRequest->method('getParam')
            ->willReturnCallback(fn(string $key, mixed $default = null) => $key === '_user_id' ? 1 : $default);

        $getPayload = null;
        $getStatus = null;
        $getResponse = $this->getMockBuilder(Response::class)
            ->onlyMethods(['json', 'error', 'send'])
            ->getMock();
        $getResponse->method('json')
            ->willReturnCallback(function (mixed $data, int $status = 200) use (&$getPayload, &$getStatus, $getResponse) {
                $getPayload = $data;
                $getStatus = $status;
                return $getResponse;
            });
        $getResponse->method('error')
            ->willReturnCallback(function (string $message, int $status = 400) use (&$getPayload, &$getStatus, $getResponse) {
                $getPayload = ['error' => true, 'message' => $message];
                $getStatus = $status;
                return $getResponse;
            });
        $getResponse->method('send')->willReturnCallback(static function (): void {
        });

        $controller = new KanbanController($getRequest, $getResponse);
        $controller->getBoard($boardId);

        $this->assertSame(200, $getStatus);
        $this->assertIsArray($getPayload);
        $this->assertTrue((bool) ($getPayload['success'] ?? false));
        $boardData = $getPayload['data']['board'] ?? [];
        $this->assertSame($unidadeId, (int) ($boardData['company_id'] ?? 0));
        $this->assertSame($unidadeId, (int) ($boardData['unidade_id'] ?? 0));
        $this->assertSame($unidadeId, (int) ($boardData['unidade']['id'] ?? 0));
        $this->assertSame($unidadeId, (int) ($boardData['company']['id'] ?? 0));
    }

    public function testAdminGetArvoreReturnsNodeWhenRaizIdIsNotGlobalRoot(): void
    {
        $target = $this->db->fetchOne(
            "SELECT unidade_id
             FROM dotp_unidades_organizacionais
             WHERE unidade_pai_id IS NOT NULL
               AND unidade_status = 'ativo'
             LIMIT 1"
        );

        if (!$target || empty($target['unidade_id'])) {
            $this->markTestSkipped('Base sem unidade não-raiz para teste de subárvore.');
        }

        $raizId = (int) $target['unidade_id'];
        $request = $this->createMock(Request::class);
        $request->method('getQuery')
            ->willReturnCallback(fn(string $key, mixed $default = null) => $key === 'raiz_id' ? $raizId : $default);

        $response = new Response();
        $controller = new AdminController($request, $response);

        $result = $controller->getArvore();
        $body = $this->responseBody($result);

        $this->assertArrayHasKey('data', $body);
        $this->assertNotEmpty($body['data']);
        $this->assertSame($raizId, (int) ($body['data'][0]['id'] ?? 0));
    }

    private function responseBody(Response $response): array
    {
        $prop = new \ReflectionProperty(Response::class, 'body');
        $prop->setAccessible(true);
        $body = $prop->getValue($response);
        return is_array($body) ? $body : [];
    }

    private function insertProject(int $unidadeId, string $name): int
    {
        $id = $this->db->insert('projects', [
            'project_company' => $unidadeId,
            'project_company_internal' => 0,
            'project_department' => 0,
            'project_name' => $name,
            'project_short_name' => substr(preg_replace('/[^A-Za-z0-9]/', '', $name) ?: 'ITPROJ', 0, 10),
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

    /**
     * @return array{id: int, nome: string}|null
     */
    private function pickAnyUnidade(): ?array
    {
        $row = $this->db->fetchOne(
            "SELECT unidade_id, unidade_nome
             FROM dotp_unidades_organizacionais
             WHERE unidade_status = 'ativo'
             ORDER BY unidade_id
             LIMIT 1"
        );

        if (!$row) {
            return null;
        }

        return ['id' => (int) $row['unidade_id'], 'nome' => (string) $row['unidade_nome']];
    }

    /**
     * @return array{0: array{id: int, nome: string}|null, 1: array{id: int, nome: string}|null}
     */
    private function pickTwoUnidades(): array
    {
        $rows = $this->db->fetchAll(
            "SELECT unidade_id, unidade_nome
             FROM dotp_unidades_organizacionais
             WHERE unidade_status = 'ativo'
             ORDER BY unidade_id
             LIMIT 2"
        );

        if (count($rows) < 2) {
            return [null, null];
        }

        return [
            ['id' => (int) $rows[0]['unidade_id'], 'nome' => (string) $rows[0]['unidade_nome']],
            ['id' => (int) $rows[1]['unidade_id'], 'nome' => (string) $rows[1]['unidade_nome']],
        ];
    }

    private function ensureCompanyExistsForUnidade(int $unidadeId, string $unidadeNome): void
    {
        $exists = $this->db->fetchValue(
            sprintf('SELECT company_id FROM `%s` WHERE company_id = %d', $this->db->table('companies'), $unidadeId)
        );

        if ($exists !== null) {
            return;
        }

        $inserted = $this->db->insert('companies', [
            'company_id' => $unidadeId,
            'company_module' => 0,
            'company_name' => $unidadeNome,
            'company_owner' => 0,
            'company_type' => 0,
        ]);

        $this->assertNotFalse($inserted, 'Falha ao criar company compatível para unidade');
        $this->createdCompanyIds[] = $unidadeId;
    }

    private function cleanupProjects(): void
    {
        foreach ($this->projectIds as $projectId) {
            $this->db->delete('projects', sprintf('project_id = %d', (int) $projectId));
        }
        $this->projectIds = [];
    }

    private function cleanupBoards(): void
    {
        foreach ($this->boardIds as $boardId) {
            $boardId = (int) $boardId;
            $columnRows = $this->db->fetchAll(sprintf(
                'SELECT column_id FROM `%s` WHERE column_board_id = %d',
                $this->db->table('kanban_columns'),
                $boardId
            ));

            $columnIds = array_map(fn(array $r) => (int) ($r['column_id'] ?? 0), $columnRows);
            if (!empty($columnIds)) {
                $ids = implode(',', $columnIds);
                $this->db->execute(
                    sprintf('DELETE FROM `%s` WHERE kanban_task_column_id IN (%s)', $this->db->table('kanban_tasks'), $ids)
                );
            }

            $this->db->delete('kanban_columns', sprintf('column_board_id = %d', $boardId));
            $this->db->delete('kanban_boards', sprintf('board_id = %d', $boardId));
        }

        $this->boardIds = [];
    }

    private function cleanupCompanies(): void
    {
        foreach ($this->createdCompanyIds as $companyId) {
            $companyId = (int) $companyId;
            $projectRef = (int) ($this->db->fetchValue(
                sprintf('SELECT COUNT(*) FROM `%s` WHERE project_company = %d', $this->db->table('projects'), $companyId)
            ) ?? 0);
            $boardRef = (int) ($this->db->fetchValue(
                sprintf('SELECT COUNT(*) FROM `%s` WHERE board_company = %d', $this->db->table('kanban_boards'), $companyId)
            ) ?? 0);

            if ($projectRef === 0 && $boardRef === 0) {
                $this->db->delete('companies', sprintf('company_id = %d', $companyId));
            }
        }

        $this->createdCompanyIds = [];
    }
}
