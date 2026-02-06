<?php

declare(strict_types=1);

namespace DotProject\Tests\Integration;

use DotProject\Api\Controller\ProjetoController;
use DotProject\Api\Request;
use DotProject\Api\Response;
use DotProject\Core\Database;
use PHPUnit\Framework\TestCase;

class ProjetoControllerIntegrationTest extends TestCase
{
    private Database $db;
    /** @var int[] */
    private array $projectIds = [];

    protected function setUp(): void
    {
        $this->db = Database::getInstance();
    }

    protected function tearDown(): void
    {
        foreach ($this->projectIds as $projectId) {
            $this->db->delete('projects', sprintf('project_id = %d', (int) $projectId));
        }
        $this->projectIds = [];
    }

    public function testIndexReturnsUnauthorizedWhenUserIsMissing(): void
    {
        $request = $this->createMock(Request::class);
        $request->method('getParam')->willReturn(null);
        $request->method('getQueryParams')->willReturn([]);

        [$response, $state] = $this->mockResponse();
        $controller = new ProjetoController($request, $response);
        $controller->index();

        $this->assertSame(401, $state->status);
        $this->assertIsArray($state->payload);
    }

    public function testUpdateReturnsUnauthorizedWhenUserIsMissing(): void
    {
        $request = $this->createMock(Request::class);
        $request->method('getParam')
            ->willReturnCallback(static fn(string $key, mixed $default = null) => $key === 'id' ? 999 : $default);
        $request->method('getBody')->willReturn([]);

        [$response, $state] = $this->mockResponse();
        $controller = new ProjetoController($request, $response);
        $controller->update();

        $this->assertSame(401, $state->status);
        $this->assertIsArray($state->payload);
    }

    public function testShowWithAuthenticatedUserDoesNotCrash(): void
    {
        $projectId = $this->insertProject('it_proj_ctrl_' . bin2hex(random_bytes(4)));
        $this->projectIds[] = $projectId;

        $request = $this->createMock(Request::class);
        $request->method('getParam')->willReturnCallback(
            static fn(string $key, mixed $default = null) => match ($key) {
                '_user_id' => 1,
                'id' => $projectId,
                default => $default,
            }
        );

        [$response, $state] = $this->mockResponse();
        $controller = new ProjetoController($request, $response);
        $controller->show();

        $this->assertIsArray($state->payload);
        $this->assertNotSame(500, $state->status, 'Resposta 500: ' . var_export($state->payload, true));
        $this->assertNotSame(0, $state->status);
    }

    /**
     * @return array{0: Response, 1: object}
     */
    private function mockResponse(): array
    {
        $state = (object) [
            'payload' => null,
            'status' => 0,
        ];

        $response = $this->getMockBuilder(Response::class)
            ->onlyMethods(['json', 'error', 'unauthorized', 'forbidden', 'send'])
            ->getMock();

        $response->method('json')
            ->willReturnCallback(function (mixed $data, int $status = 200) use ($state, $response) {
                $state->payload = is_array($data) ? $data : ['data' => $data];
                $state->status = $status;
                return $response;
            });

        $response->method('error')
            ->willReturnCallback(function (string $message, int $status = 400) use ($state, $response) {
                $state->payload = ['error' => true, 'message' => $message];
                $state->status = $status;
                return $response;
            });

        $response->method('unauthorized')
            ->willReturnCallback(function (string $message = 'Unauthorized') use ($state, $response) {
                $state->payload = ['error' => true, 'message' => $message];
                $state->status = 401;
                return $response;
            });

        $response->method('forbidden')
            ->willReturnCallback(function (string $message = 'Forbidden') use ($state, $response) {
                $state->payload = ['error' => true, 'message' => $message];
                $state->status = 403;
                return $response;
            });

        $response->method('send')->willReturnCallback(static function (): void {
        });

        return [$response, $state];
    }

    private function insertProject(string $name): int
    {
        $id = $this->db->insert('projects', [
            'project_company' => 40,
            'project_company_internal' => 0,
            'project_department' => 0,
            'project_name' => $name,
            'project_short_name' => substr(preg_replace('/[^A-Za-z0-9]/', '', $name) ?: 'PJCTRL', 0, 10),
            'project_owner' => 1,
            'project_creator' => 1,
            'project_status' => 1,
            'project_percent_complete' => 0,
            'project_color_identifier' => '#4A90D9',
            'project_priority' => 1,
            'project_type' => 0,
            'project_start_date' => date('Y-m-d H:i:s'),
            'project_programa_id' => null,
            'project_tipo' => 'Outro',
            'project_estado' => 'Cadastrado',
            'project_etapa_atual' => 1,
            'project_percent_execucao' => 0,
            'project_coordenador_id' => 1,
        ]);

        $this->assertNotFalse($id, 'Falha ao inserir projeto para teste de controller');
        return (int) $id;
    }
}
