<?php

declare(strict_types=1);

namespace DotProject\Tests\Unit\Api\Controllers;

use DotProject\Api\Controller\Dashboard\AlertaDashboardController;
use DotProject\Api\Controller\Dashboard\ControladorDashboardController;
use DotProject\Api\Controller\Dashboard\CoordenadorDashboardController;
use DotProject\Api\Controller\Dashboard\DashboardRouterController;
use DotProject\Api\Controller\Dashboard\PrefeitoDashboardController;
use DotProject\Api\Controller\Dashboard\SecretarioDashboardController;
use DotProject\Api\Controller\Dashboard\TecnicoDashboardController;
use DotProject\Api\Request;
use DotProject\Api\Response;
use DotProject\Core\Database;
use DotProject\Repository\AlertaRepository;
use DotProject\Service\PermissionService;
use PHPUnit\Framework\TestCase;

class DashboardControllerContractTest extends TestCase
{
    private static ?Database $originalDatabaseInstance = null;

    public static function setUpBeforeClass(): void
    {
        self::$originalDatabaseInstance = self::getDatabaseInstance();
    }

    public static function tearDownAfterClass(): void
    {
        self::setDatabaseInstance(self::$originalDatabaseInstance);
    }

    protected function setUp(): void
    {
        self::setDatabaseInstance(new FakeDashboardDatabase());
    }

    public function testPrefeitoDashboardReturnsContractShape(): void
    {
        $controller = new PrefeitoDashboardController(
            $this->createRequest(101, '/api/v1/dashboard/prefeito'),
            new Response()
        );

        $result = $controller->prefeito();
        $body = $this->responseBody($result);
        $data = $body['data'] ?? [];

        $this->assertSame(Response::HTTP_OK, $this->responseStatus($result));
        $this->assertSame('prefeito', $data['perfil'] ?? null);
        $this->assertIsArray($data['ppa_execucao'] ?? null);
        $this->assertIsArray($data['projetos_status'] ?? null);
        $this->assertIsArray($data['por_secretaria'] ?? null);
        $this->assertIsArray($data['obras_atrasadas'] ?? null);
        $this->assertIsArray($data['convenios_vencer'] ?? null);
        $this->assertIsArray($data['emendas'] ?? null);
        $this->assertIsArray($data['timeline_30dias'] ?? null);
        $this->assertIsArray($data['orcamento'] ?? null);
        $this->assertIsInt((int) (($data['ppa_execucao']['total_programas'] ?? 0)));
    }

    public function testSecretarioDashboardReturnsContractShape(): void
    {
        $controller = new SecretarioDashboardController(
            $this->createRequest(202, '/api/v1/dashboard/secretario'),
            new Response()
        );

        $permissionService = $this->createMock(PermissionService::class);
        $permissionService->method('getEscopoDados')->willReturn([
            'user_id' => 202,
            'role' => PermissionService::ROLE_SECRETARIO,
            'unidade_id' => 2,
            'nivel' => 2,
            'unidades_escopo' => [2],
            'eh_gestor' => true,
        ]);
        $this->setPrivateProperty($controller, 'permissionService', $permissionService);

        $result = $controller->secretario();
        $body = $this->responseBody($result);
        $data = $body['data'] ?? [];

        $this->assertSame(Response::HTTP_OK, $this->responseStatus($result));
        $this->assertSame('secretario', $data['perfil'] ?? null);
        $this->assertArrayHasKey('unidade_id', $data);
        $this->assertIsArray($data['programas'] ?? null);
        $this->assertIsArray($data['projetos_resumo'] ?? null);
        $this->assertIsArray($data['projetos_atencao'] ?? null);
        $this->assertIsArray($data['coordenadores'] ?? null);
        $this->assertIsArray($data['alertas'] ?? null);
    }

    public function testCoordenadorDashboardReturnsContractShape(): void
    {
        $controller = new CoordenadorDashboardController(
            $this->createRequest(303, '/api/v1/dashboard/coordenador'),
            new Response()
        );

        $permissionService = $this->createMock(PermissionService::class);
        $permissionService->method('getEscopoDados')->willReturn([
            'user_id' => 303,
            'role' => PermissionService::ROLE_COORDENADOR,
            'unidade_id' => 3,
            'nivel' => 3,
            'unidades_escopo' => [3],
            'eh_gestor' => true,
        ]);
        $this->setPrivateProperty($controller, 'permissionService', $permissionService);

        $result = $controller->coordenador();
        $body = $this->responseBody($result);
        $data = $body['data'] ?? [];

        $this->assertSame(Response::HTTP_OK, $this->responseStatus($result));
        $this->assertSame('coordenador', $data['perfil'] ?? null);
        $this->assertIsArray($data['resumo'] ?? null);
        $this->assertIsArray($data['projetos'] ?? null);
        $this->assertIsArray($data['etapas_atencao'] ?? null);
        $this->assertIsArray($data['proximas_etapas'] ?? null);
        $this->assertIsArray($data['equipe'] ?? null);
    }

    public function testTecnicoDashboardReturnsContractShape(): void
    {
        $controller = new TecnicoDashboardController(
            $this->createRequest(404, '/api/v1/dashboard/tecnico'),
            new Response()
        );

        $result = $controller->tecnico();
        $body = $this->responseBody($result);
        $data = $body['data'] ?? [];

        $this->assertSame(Response::HTTP_OK, $this->responseStatus($result));
        $this->assertSame('tecnico', $data['perfil'] ?? null);
        $this->assertIsArray($data['resumo'] ?? null);
        $this->assertIsArray($data['tarefas_prioritarias'] ?? null);
        $this->assertIsArray($data['concluidas_semana'] ?? null);
        $this->assertIsArray($data['projetos'] ?? null);
        $this->assertIsArray($data['produtividade_30d'] ?? null);
    }

    public function testControladorDashboardReturnsContractShape(): void
    {
        $controller = new ControladorDashboardController(
            $this->createRequest(505, '/api/v1/dashboard/controlador'),
            new Response()
        );

        $result = $controller->controlador();
        $body = $this->responseBody($result);
        $data = $body['data'] ?? [];

        $this->assertSame(Response::HTTP_OK, $this->responseStatus($result));
        $this->assertSame('controlador', $data['perfil'] ?? null);
        $this->assertIsArray($data['alertas_conformidade'] ?? null);
        $this->assertIsArray($data['panorama_secretarias'] ?? null);
        $this->assertIsArray($data['irregularidades'] ?? null);
        $this->assertIsArray($data['convenios_prestacao_pendente'] ?? null);
    }

    public function testAlertaDashboardReturnsContractShape(): void
    {
        $controller = new AlertaDashboardController(
            $this->createRequest(606, '/api/v1/dashboard/alertas', ['nao_lidos' => 'false', 'limit' => 10]),
            new Response()
        );

        $alertaRepo = $this->createMock(AlertaRepository::class);
        $alertaRepo->method('findComDetalhes')->willReturn([
            [
                'id' => 1,
                'titulo' => 'Pendencia de prestacao',
                'prioridade' => 'alta',
            ],
        ]);
        $alertaRepo->method('getEstatisticas')->willReturn([
            'total' => 1,
            'nao_lidos' => 1,
        ]);
        $this->setPrivateProperty($controller, 'alertaRepo', $alertaRepo);

        $result = $controller->alertas();
        $body = $this->responseBody($result);

        $this->assertSame(Response::HTTP_OK, $this->responseStatus($result));
        $this->assertIsArray($body['data'] ?? null);
        $this->assertIsArray($body['estatisticas'] ?? null);
        $this->assertArrayHasKey('total', $body['estatisticas']);
        $this->assertArrayHasKey('nao_lidos', $body['estatisticas']);
    }

    public function testDashboardRouterStatusReturnsContractShape(): void
    {
        $controller = new DashboardRouterController(
            $this->createRequest(707, '/api/v1/dashboard/status'),
            new Response()
        );

        $permissionService = $this->createMock(PermissionService::class);
        $permissionService->method('getDashboardType')->willReturn('secretario');
        $this->setPrivateProperty($controller, 'permissionService', $permissionService);

        $result = $controller->status();
        $body = $this->responseBody($result);

        $this->assertSame(Response::HTTP_OK, $this->responseStatus($result));
        $this->assertSame('ok', $body['status'] ?? null);
        $this->assertSame('secretario', $body['perfil'] ?? null);
        $this->assertIsBool($body['modern_tables'] ?? null);
        $this->assertIsString($body['timestamp'] ?? null);
    }

    private function createRequest(int $userId, string $uri, array $query = []): Request
    {
        $request = $this->createMock(Request::class);
        $request->method('getParam')
            ->willReturnCallback(
                static fn(string $key, mixed $default = null): mixed => match ($key) {
                    '_user_id' => $userId,
                    '_user_data' => [],
                    default => $default,
                }
            );
        $request->method('getQuery')
            ->willReturnCallback(
                static fn(string $key, mixed $default = null): mixed => $query[$key] ?? $default
            );
        $request->method('getQueryParam')
            ->willReturnCallback(
                static fn(string $key, mixed $default = null): mixed => $query[$key] ?? $default
            );
        $request->method('getUri')->willReturn($uri);

        return $request;
    }

    private static function getDatabaseInstance(): ?Database
    {
        $property = new \ReflectionProperty(Database::class, 'instance');
        $property->setAccessible(true);

        $instance = $property->getValue();
        return $instance instanceof Database ? $instance : null;
    }

    private static function setDatabaseInstance(?Database $instance): void
    {
        $property = new \ReflectionProperty(Database::class, 'instance');
        $property->setAccessible(true);
        $property->setValue(null, $instance);
    }

    private function setPrivateProperty(object $target, string $property, mixed $value): void
    {
        $ref = new \ReflectionProperty($target, $property);
        $ref->setAccessible(true);
        $ref->setValue($target, $value);
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

    private function responseStatus(Response $response): int
    {
        $prop = new \ReflectionProperty(Response::class, 'statusCode');
        $prop->setAccessible(true);

        return (int) $prop->getValue($response);
    }
}

class FakeDashboardDatabase extends Database
{
    public function __construct()
    {
        // noop
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        return [];
    }

    public function fetchAllParams(string $sql, array $params): array
    {
        return [];
    }

    public function fetchValue(string $sql, array $params = []): mixed
    {
        return 0;
    }

    public function fetchColumn(string $sql, array $params = []): mixed
    {
        return 0;
    }

    public function table(string $name): string
    {
        return str_starts_with($name, 'dotp_') ? $name : 'dotp_' . $name;
    }
}
