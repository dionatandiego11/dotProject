<?php

declare(strict_types=1);

namespace DotProject\Tests\Integration;

use DotProject\Api\Controller\AdminController;
use DotProject\Api\Controller\KanbanController;
use DotProject\Api\Controller\ProjectController;
use DotProject\Api\Controller\TaskController;
use DotProject\Api\Request;
use DotProject\Api\Response;
use DotProject\Core\Cache;
use DotProject\Core\Database;
use DotProject\Entity\Notification;
use DotProject\Repository\NotificationRepository;
use DotProject\Repository\ProjectRepository;
use DotProject\Service\KanbanService;
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

class IntegrationTaskController extends TaskController
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
    private array $taskIds = [];
    /** @var int[] */
    private array $notificationIds = [];
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
        $this->cleanupTasks();
        $this->cleanupProjects();
        $this->cleanupNotifications();
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

    public function testNotificationCountUnreadUsesFreshValueAfterSaveMutations(): void
    {
        $userId = (int) ($this->db->fetchValue(
            sprintf('SELECT user_id FROM `%s` ORDER BY user_id LIMIT 1', $this->db->table('users'))
        ) ?? 0);

        if ($userId <= 0) {
            $this->markTestSkipped('Base sem usuarios para teste de cache de notificacoes.');
        }

        $repo = new NotificationRepository($this->db, new Cache());
        $baselineUnread = $repo->countUnread($userId);

        $notification = (new Notification())
            ->setUserId($userId)
            ->setType(Notification::TYPE_SYSTEM)
            ->setTitle('IT notif ' . bin2hex(random_bytes(4)))
            ->setMessage('cache invalidation integration test')
            ->setChannel(Notification::CHANNEL_IN_APP)
            ->setIsRead(false)
            ->setIsSent(false);

        $notificationId = $repo->save($notification);
        $this->assertGreaterThan(0, $notificationId);
        $this->notificationIds[] = $notificationId;

        $unreadAfterCreate = $repo->countUnread($userId);
        $this->assertSame($baselineUnread + 1, $unreadAfterCreate);

        $notification->setIsRead(true);
        $notification->setReadAt(new \DateTime());
        $updatedId = $repo->save($notification);
        $this->assertSame($notificationId, $updatedId);

        $unreadAfterRead = $repo->countUnread($userId);
        $this->assertSame($baselineUnread, $unreadAfterRead);
    }

    public function testProjectUpdatePercentCompleteInvalidatesFindByCache(): void
    {
        $unidade = $this->pickAnyUnidade();
        if ($unidade === null) {
            $this->markTestSkipped('Base sem unidade para teste de cache em projetos.');
        }

        $unidadeId = (int) $unidade['id'];
        $this->ensureCompanyExistsForUnidade($unidadeId, (string) $unidade['nome']);

        $projectId = $this->insertProject($unidadeId, 'it_project_cache_' . bin2hex(random_bytes(4)));
        $this->projectIds[] = $projectId;

        $repo = new ProjectRepository($this->db, new Cache());
        $before = $repo->findBy(['project_id' => $projectId], null, 1);
        $this->assertNotEmpty($before);
        $this->assertSame(0, $before[0]->getPercentComplete());

        $updated = $repo->updatePercentComplete($projectId, 67);
        $this->assertTrue($updated);

        $after = $repo->findBy(['project_id' => $projectId], null, 1);
        $this->assertNotEmpty($after);
        $this->assertSame(67, $after[0]->getPercentComplete());
    }

    public function testTaskShowReturnsAssignedToWithOwnerFallback(): void
    {
        $unidade = $this->pickAnyUnidade();
        if ($unidade === null) {
            $this->markTestSkipped('Base sem unidade para teste de assignee em tarefa.');
        }

        $unidadeId = (int) $unidade['id'];
        $this->ensureCompanyExistsForUnidade($unidadeId, (string) $unidade['nome']);

        $projectId = $this->insertProject($unidadeId, 'it_task_assignee_' . bin2hex(random_bytes(4)));
        $this->projectIds[] = $projectId;

        $taskId = $this->insertTask($projectId, 'IT Assignee Task ' . bin2hex(random_bytes(3)));
        $this->taskIds[] = $taskId;

        $request = $this->createMock(Request::class);
        $request->method('getParam')
            ->willReturnCallback(function (string $key, mixed $default = null) use ($taskId) {
                return match ($key) {
                    'id' => $taskId,
                    '_user_id' => 1,
                    default => $default,
                };
            });

        $response = new Response();
        $controller = new IntegrationTaskController($request, $response);
        $result = $controller->show();
        $body = $this->responseBody($result);

        $this->assertSame($taskId, (int) ($body['id'] ?? 0));
        $this->assertSame(1, (int) ($body['owner_id'] ?? 0));
        $this->assertSame(1, (int) ($body['assigned_to'] ?? 0));
    }

    public function testTaskStoreStatusDoneDefaultsPercentToHundred(): void
    {
        $unidade = $this->pickAnyUnidade();
        if ($unidade === null) {
            $this->markTestSkipped('Base sem unidade para teste de consistencia status/percent na criacao.');
        }

        $unidadeId = (int) $unidade['id'];
        $this->ensureCompanyExistsForUnidade($unidadeId, (string) $unidade['nome']);

        $projectId = $this->insertProject($unidadeId, 'it_task_store_done_' . bin2hex(random_bytes(4)));
        $this->projectIds[] = $projectId;

        $request = $this->createMock(Request::class);
        $request->method('getBody')->willReturn([
            'name' => 'Task store done ' . bin2hex(random_bytes(3)),
            'project_id' => $projectId,
            'status' => 3,
        ]);
        $request->method('getParam')
            ->willReturnCallback(fn(string $key, mixed $default = null) => $key === '_user_id' ? 1 : $default);

        $response = new Response();
        $controller = new IntegrationTaskController($request, $response);
        $result = $controller->store();
        $body = $this->responseBody($result);

        $taskId = (int) ($body['id'] ?? 0);
        $this->assertGreaterThan(0, $taskId);
        $this->taskIds[] = $taskId;

        $row = $this->db->fetchOne(
            sprintf(
                "SELECT task_status, task_percent_complete FROM `%s` WHERE task_id = %d LIMIT 1",
                $this->db->table('tasks'),
                $taskId
            )
        );

        $this->assertNotNull($row);
        $this->assertSame(3, (int) ($row['task_status'] ?? -1));
        $this->assertSame(100, (int) ($row['task_percent_complete'] ?? -1));
    }

    public function testTaskUpdateStatusOnlyKeepsPercentCoherent(): void
    {
        $unidade = $this->pickAnyUnidade();
        if ($unidade === null) {
            $this->markTestSkipped('Base sem unidade para teste de consistencia status/percent na edicao.');
        }

        $unidadeId = (int) $unidade['id'];
        $this->ensureCompanyExistsForUnidade($unidadeId, (string) $unidade['nome']);

        $projectId = $this->insertProject($unidadeId, 'it_task_update_done_' . bin2hex(random_bytes(4)));
        $this->projectIds[] = $projectId;

        $taskId = $this->insertTask($projectId, 'Task update done ' . bin2hex(random_bytes(3)));
        $this->taskIds[] = $taskId;

        $updated = $this->db->update('tasks', [
            'task_status' => 2,
            'task_percent_complete' => 40,
        ], sprintf('task_id = %d', $taskId));
        $this->assertTrue($updated);

        $requestDone = $this->createMock(Request::class);
        $requestDone->method('getBody')->willReturn(['status' => 3]);
        $requestDone->method('getParam')
            ->willReturnCallback(function (string $key, mixed $default = null) use ($taskId) {
                return match ($key) {
                    'id' => $taskId,
                    '_user_id' => 1,
                    default => $default,
                };
            });

        $responseDone = new Response();
        $controllerDone = new IntegrationTaskController($requestDone, $responseDone);
        $controllerDone->update();

        $rowDone = $this->db->fetchOne(
            sprintf(
                "SELECT task_status, task_percent_complete FROM `%s` WHERE task_id = %d LIMIT 1",
                $this->db->table('tasks'),
                $taskId
            )
        );
        $this->assertNotNull($rowDone);
        $this->assertSame(3, (int) ($rowDone['task_status'] ?? -1));
        $this->assertSame(100, (int) ($rowDone['task_percent_complete'] ?? -1));

        $requestBacklog = $this->createMock(Request::class);
        $requestBacklog->method('getBody')->willReturn(['status' => 0]);
        $requestBacklog->method('getParam')
            ->willReturnCallback(function (string $key, mixed $default = null) use ($taskId) {
                return match ($key) {
                    'id' => $taskId,
                    '_user_id' => 1,
                    default => $default,
                };
            });

        $responseBacklog = new Response();
        $controllerBacklog = new IntegrationTaskController($requestBacklog, $responseBacklog);
        $controllerBacklog->update();

        $rowBacklog = $this->db->fetchOne(
            sprintf(
                "SELECT task_status, task_percent_complete FROM `%s` WHERE task_id = %d LIMIT 1",
                $this->db->table('tasks'),
                $taskId
            )
        );
        $this->assertNotNull($rowBacklog);
        $this->assertSame(0, (int) ($rowBacklog['task_status'] ?? -1));
        $this->assertSame(0, (int) ($rowBacklog['task_percent_complete'] ?? -1));
    }

    public function testTaskUpdateStatusAndPercentWithoutNameDoesNotFailValidation(): void
    {
        $unidade = $this->pickAnyUnidade();
        if ($unidade === null) {
            $this->markTestSkipped('Base sem unidade para teste de validacao parcial de tarefa.');
        }

        $unidadeId = (int) $unidade['id'];
        $this->ensureCompanyExistsForUnidade($unidadeId, (string) $unidade['nome']);

        $projectId = $this->insertProject($unidadeId, 'it_task_update_percent_' . bin2hex(random_bytes(4)));
        $this->projectIds[] = $projectId;

        $taskId = $this->insertTask($projectId, 'Task update percent ' . bin2hex(random_bytes(3)));
        $this->taskIds[] = $taskId;

        $seeded = $this->db->update('tasks', [
            'task_status' => 2,
            'task_percent_complete' => 20,
        ], sprintf('task_id = %d', $taskId));
        $this->assertTrue($seeded);

        $request = $this->createMock(Request::class);
        $request->method('getBody')->willReturn([
            'status' => 2,
            'percent_complete' => 60,
        ]);
        $request->method('getParam')
            ->willReturnCallback(function (string $key, mixed $default = null) use ($taskId) {
                return match ($key) {
                    'id' => $taskId,
                    '_user_id' => 1,
                    default => $default,
                };
            });

        $response = new Response();
        $controller = new IntegrationTaskController($request, $response);
        $result = $controller->update();
        $body = $this->responseBody($result);

        $this->assertSame($taskId, (int) ($body['id'] ?? 0));

        $row = $this->db->fetchOne(
            sprintf(
                "SELECT task_status, task_percent_complete FROM `%s` WHERE task_id = %d LIMIT 1",
                $this->db->table('tasks'),
                $taskId
            )
        );

        $this->assertNotNull($row);
        $this->assertSame(2, (int) ($row['task_status'] ?? -1));
        $this->assertSame(60, (int) ($row['task_percent_complete'] ?? -1));
    }

    public function testKanbanBoardReturnsProjectTasksWithLegacyTaskSchema(): void
    {
        $unidade = $this->pickAnyUnidade();
        if ($unidade === null) {
            $this->markTestSkipped('Base sem unidade para teste de tarefas no Kanban.');
        }

        $unidadeId = (int) $unidade['id'];
        $this->ensureCompanyExistsForUnidade($unidadeId, (string) $unidade['nome']);

        $projectId = $this->insertProject($unidadeId, 'it_kanban_schema_' . bin2hex(random_bytes(4)));
        $this->projectIds[] = $projectId;
        $this->taskIds[] = $this->insertTask($projectId, 'IT Task ' . bin2hex(random_bytes(3)));

        $service = new KanbanService($this->db, null, new Cache());
        $board = $service->createBoard([
            'name' => 'IT Board ' . bin2hex(random_bytes(4)),
            'project_id' => $projectId,
            'company_id' => $unidadeId,
        ], 1);

        $boardId = (int) ($board->getId() ?? 0);
        $this->assertGreaterThan(0, $boardId);
        $this->boardIds[] = $boardId;

        $payload = $service->getBoard($boardId, 1);
        $this->assertIsArray($payload);

        $columns = $payload['columns'] ?? [];
        $this->assertNotEmpty($columns);

        $taskTotal = 0;
        foreach ($columns as $column) {
            $taskTotal += count($column['tasks'] ?? []);
        }
        $this->assertGreaterThanOrEqual(1, $taskTotal);
    }

    public function testProjectStoreStatusDoneDefaultsPercentToHundred(): void
    {
        $unidade = $this->pickAnyUnidade();
        if ($unidade === null) {
            $this->markTestSkipped('Base sem unidade para teste de consistencia de progresso em projeto.');
        }

        $unidadeId = (int) $unidade['id'];
        $this->ensureCompanyExistsForUnidade($unidadeId, (string) $unidade['nome']);

        $request = $this->createMock(Request::class);
        $request->method('getBody')->willReturn([
            'name' => 'Project done store ' . bin2hex(random_bytes(3)),
            'unidade_id' => $unidadeId,
            'status' => 5,
        ]);
        $request->method('getParam')
            ->willReturnCallback(fn(string $key, mixed $default = null) => $key === '_user_id' ? 1 : $default);

        $response = new Response();
        $controller = new IntegrationProjectController($request, $response);
        $result = $controller->store();
        $body = $this->responseBody($result);

        $projectId = (int) ($body['id'] ?? 0);
        $this->assertGreaterThan(0, $projectId);
        $this->projectIds[] = $projectId;

        $row = $this->db->fetchOne(
            sprintf(
                "SELECT project_status, project_percent_complete FROM `%s` WHERE project_id = %d LIMIT 1",
                $this->db->table('projects'),
                $projectId
            )
        );

        $this->assertNotNull($row);
        $this->assertSame(5, (int) ($row['project_status'] ?? -1));
        $this->assertSame(100, (int) ($row['project_percent_complete'] ?? -1));
    }

    public function testProjectUpdateStatusAndPercentRemainCoherent(): void
    {
        $unidade = $this->pickAnyUnidade();
        if ($unidade === null) {
            $this->markTestSkipped('Base sem unidade para teste de consistencia de progresso em projeto.');
        }

        $unidadeId = (int) $unidade['id'];
        $this->ensureCompanyExistsForUnidade($unidadeId, (string) $unidade['nome']);

        $projectId = $this->insertProject($unidadeId, 'it_project_progress_' . bin2hex(random_bytes(4)));
        $this->projectIds[] = $projectId;

        $requestProgress = $this->createMock(Request::class);
        $requestProgress->method('getBody')->willReturn([
            'status' => 3,
            'percent_complete' => 65,
        ]);
        $requestProgress->method('getParam')
            ->willReturnCallback(function (string $key, mixed $default = null) use ($projectId) {
                return match ($key) {
                    'id' => $projectId,
                    '_user_id' => 1,
                    default => $default,
                };
            });

        $responseProgress = new Response();
        $controllerProgress = new IntegrationProjectController($requestProgress, $responseProgress);
        $controllerProgress->update();

        $afterProgress = $this->db->fetchOne(
            sprintf(
                "SELECT project_status, project_percent_complete FROM `%s` WHERE project_id = %d LIMIT 1",
                $this->db->table('projects'),
                $projectId
            )
        );
        $this->assertNotNull($afterProgress);
        $this->assertSame(3, (int) ($afterProgress['project_status'] ?? -1));
        $this->assertSame(65, (int) ($afterProgress['project_percent_complete'] ?? -1));

        $requestDone = $this->createMock(Request::class);
        $requestDone->method('getBody')->willReturn(['status' => 5]);
        $requestDone->method('getParam')
            ->willReturnCallback(function (string $key, mixed $default = null) use ($projectId) {
                return match ($key) {
                    'id' => $projectId,
                    '_user_id' => 1,
                    default => $default,
                };
            });

        $responseDone = new Response();
        $controllerDone = new IntegrationProjectController($requestDone, $responseDone);
        $controllerDone->update();

        $afterDone = $this->db->fetchOne(
            sprintf(
                "SELECT project_status, project_percent_complete FROM `%s` WHERE project_id = %d LIMIT 1",
                $this->db->table('projects'),
                $projectId
            )
        );
        $this->assertNotNull($afterDone);
        $this->assertSame(5, (int) ($afterDone['project_status'] ?? -1));
        $this->assertSame(100, (int) ($afterDone['project_percent_complete'] ?? -1));
    }

    public function testProjectUpdateRejectsInvalidStatusTransitionFromProposedToArchived(): void
    {
        $unidade = $this->pickAnyUnidade();
        if ($unidade === null) {
            $this->markTestSkipped('Base sem unidade para teste de transicao invalida de status.');
        }

        $unidadeId = (int) $unidade['id'];
        $this->ensureCompanyExistsForUnidade($unidadeId, (string) $unidade['nome']);

        $projectId = $this->insertProject($unidadeId, 'it_project_invalid_transition_' . bin2hex(random_bytes(4)));
        $this->projectIds[] = $projectId;

        $request = $this->createMock(Request::class);
        $request->method('getBody')->willReturn([
            'status' => 6,
        ]);
        $request->method('getParam')
            ->willReturnCallback(function (string $key, mixed $default = null) use ($projectId) {
                return match ($key) {
                    'id' => $projectId,
                    '_user_id' => 1,
                    default => $default,
                };
            });

        $response = new Response();
        $controller = new IntegrationProjectController($request, $response);
        $result = $controller->update();
        $body = $this->responseBody($result);

        $this->assertTrue((bool) ($body['error'] ?? false));
        $this->assertSame('Validation failed', (string) ($body['message'] ?? ''));
        $this->assertArrayHasKey('status', (array) ($body['errors'] ?? []));

        $row = $this->db->fetchOne(
            sprintf(
                "SELECT project_status FROM `%s` WHERE project_id = %d LIMIT 1",
                $this->db->table('projects'),
                $projectId
            )
        );
        $this->assertNotNull($row);
        $this->assertSame(1, (int) ($row['project_status'] ?? -1));
    }

    public function testProjectUpdateStatusEndpointTransitionsFromInProgressToDone(): void
    {
        $unidade = $this->pickAnyUnidade();
        if ($unidade === null) {
            $this->markTestSkipped('Base sem unidade para teste do endpoint de status.');
        }

        $unidadeId = (int) $unidade['id'];
        $this->ensureCompanyExistsForUnidade($unidadeId, (string) $unidade['nome']);

        $projectId = $this->insertProject($unidadeId, 'it_project_status_endpoint_' . bin2hex(random_bytes(4)));
        $this->projectIds[] = $projectId;

        $updated = $this->db->update('projects', [
            'project_status' => 3,
            'project_percent_complete' => 60,
        ], sprintf('project_id = %d', $projectId));
        $this->assertTrue($updated);

        $request = $this->createMock(Request::class);
        $request->method('getBody')->willReturn([
            'status' => 5,
        ]);
        $request->method('getParam')
            ->willReturnCallback(function (string $key, mixed $default = null) use ($projectId) {
                return match ($key) {
                    'id' => $projectId,
                    '_user_id' => 1,
                    default => $default,
                };
            });

        $response = new Response();
        $controller = new IntegrationProjectController($request, $response);
        $result = $controller->updateStatus();
        $body = $this->responseBody($result);

        $this->assertSame($projectId, (int) ($body['id'] ?? 0));
        $this->assertSame(5, (int) ($body['status'] ?? -1));
        $this->assertSame(100, (int) ($body['percent_complete'] ?? -1));

        $row = $this->db->fetchOne(
            sprintf(
                "SELECT project_status, project_percent_complete FROM `%s` WHERE project_id = %d LIMIT 1",
                $this->db->table('projects'),
                $projectId
            )
        );
        $this->assertNotNull($row);
        $this->assertSame(5, (int) ($row['project_status'] ?? -1));
        $this->assertSame(100, (int) ($row['project_percent_complete'] ?? -1));
    }

    public function testProjectUpdateStatusEndpointRejectsInvalidTransitionFromProposedToArchived(): void
    {
        $unidade = $this->pickAnyUnidade();
        if ($unidade === null) {
            $this->markTestSkipped('Base sem unidade para teste de transicao invalida no endpoint de status.');
        }

        $unidadeId = (int) $unidade['id'];
        $this->ensureCompanyExistsForUnidade($unidadeId, (string) $unidade['nome']);

        $projectId = $this->insertProject($unidadeId, 'it_project_status_endpoint_invalid_' . bin2hex(random_bytes(4)));
        $this->projectIds[] = $projectId;

        $request = $this->createMock(Request::class);
        $request->method('getBody')->willReturn([
            'status' => 6,
        ]);
        $request->method('getParam')
            ->willReturnCallback(function (string $key, mixed $default = null) use ($projectId) {
                return match ($key) {
                    'id' => $projectId,
                    '_user_id' => 1,
                    default => $default,
                };
            });

        $response = new Response();
        $controller = new IntegrationProjectController($request, $response);
        $result = $controller->updateStatus();
        $body = $this->responseBody($result);

        $this->assertTrue((bool) ($body['error'] ?? false));
        $this->assertSame('Validation failed', (string) ($body['message'] ?? ''));
        $this->assertArrayHasKey('status', (array) ($body['errors'] ?? []));

        $row = $this->db->fetchOne(
            sprintf(
                "SELECT project_status FROM `%s` WHERE project_id = %d LIMIT 1",
                $this->db->table('projects'),
                $projectId
            )
        );
        $this->assertNotNull($row);
        $this->assertSame(1, (int) ($row['project_status'] ?? -1));
    }

    public function testProjectStatusHistoryEndpointReturnsLatestTransition(): void
    {
        if (!$this->hasProjectStatusHistoryTable()) {
            $this->markTestSkipped(
                'Tabela dotp_project_status_history ausente. Execute a migration 20260210_create_project_status_history.sql.'
            );
        }

        $unidade = $this->pickAnyUnidade();
        if ($unidade === null) {
            $this->markTestSkipped('Base sem unidade para teste de historico de status.');
        }

        $unidadeId = (int) $unidade['id'];
        $this->ensureCompanyExistsForUnidade($unidadeId, (string) $unidade['nome']);

        $projectId = $this->insertProject($unidadeId, 'it_project_status_history_' . bin2hex(random_bytes(4)));
        $this->projectIds[] = $projectId;

        $updateRequest = $this->createMock(Request::class);
        $updateRequest->method('getBody')->willReturn([
            'status' => 3,
            'status_change_source' => 'tests.status_endpoint',
        ]);
        $updateRequest->method('getParam')
            ->willReturnCallback(function (string $key, mixed $default = null) use ($projectId) {
                return match ($key) {
                    'id' => $projectId,
                    '_user_id' => 1,
                    default => $default,
                };
            });

        $updateResponse = new Response();
        $updateController = new IntegrationProjectController($updateRequest, $updateResponse);
        $updateController->updateStatus();

        $historyRequest = $this->createMock(Request::class);
        $historyRequest->method('getQueryParam')
            ->willReturnCallback(fn(string $key, mixed $default = null) => $key === 'limit' ? 20 : $default);
        $historyRequest->method('getParam')
            ->willReturnCallback(function (string $key, mixed $default = null) use ($projectId) {
                return match ($key) {
                    'id' => $projectId,
                    '_user_id' => 1,
                    default => $default,
                };
            });

        $historyResponse = new Response();
        $historyController = new IntegrationProjectController($historyRequest, $historyResponse);
        $result = $historyController->statusHistory();
        $body = $this->responseBody($result);

        $this->assertArrayHasKey('data', $body);
        $this->assertNotEmpty($body['data']);

        $latest = $body['data'][0];
        $this->assertSame($projectId, (int) ($latest['project_id'] ?? 0));
        $this->assertSame(1, (int) ($latest['from_status'] ?? -1));
        $this->assertSame(3, (int) ($latest['to_status'] ?? -1));
        $this->assertSame('tests.status_endpoint', (string) ($latest['source'] ?? ''));
        $this->assertSame(1, (int) ($latest['changed_by_user_id'] ?? 0));
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

    private function hasProjectStatusHistoryTable(): bool
    {
        return (int) ($this->db->fetchValue(
            "SELECT COUNT(*)
             FROM information_schema.tables
             WHERE table_schema = DATABASE()
               AND table_name = 'dotp_project_status_history'"
        ) ?? 0) > 0;
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

    private function insertTask(int $projectId, string $name): int
    {
        $id = $this->db->insert('tasks', [
            'task_name' => $name,
            'task_project' => $projectId,
            'task_parent' => 0,
            'task_milestone' => 0,
            'task_owner' => 1,
            'task_creator' => 1,
            'task_start_date' => date('Y-m-d H:i:s'),
            'task_duration' => 1,
            'task_duration_type' => 1,
            'task_hours_worked' => 0,
            'task_end_date' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'task_status' => 0,
            'task_priority' => 1,
            'task_percent_complete' => 0,
            'task_description' => 'integration task',
            'task_order' => 1,
            'task_access' => 0,
            'task_type' => 0,
        ]);

        $this->assertNotFalse($id, 'Falha ao inserir tarefa de integraÃ§Ã£o');
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

    private function cleanupTasks(): void
    {
        foreach ($this->taskIds as $taskId) {
            $this->db->delete('tasks', sprintf('task_id = %d', (int) $taskId));
        }
        $this->taskIds = [];
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

    private function cleanupNotifications(): void
    {
        foreach ($this->notificationIds as $notificationId) {
            $this->db->delete('notifications', sprintf('notification_id = %d', (int) $notificationId));
        }

        $this->notificationIds = [];
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
