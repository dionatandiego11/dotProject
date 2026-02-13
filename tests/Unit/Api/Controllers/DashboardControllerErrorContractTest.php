<?php

declare(strict_types=1);

namespace DotProject\Tests\Unit\Api\Controllers;

use DotProject\Api\Controller\Dashboard\AlertaDashboardController;
use DotProject\Api\Controller\Dashboard\CoordenadorDashboardController;
use DotProject\Api\Controller\Dashboard\SecretarioDashboardController;
use DotProject\Api\Request;
use DotProject\Api\Response;
use DotProject\Core\Database;
use DotProject\Repository\AlertaRepository;
use DotProject\Service\PermissionService;
use PHPUnit\Framework\TestCase;

class DashboardControllerErrorContractTest extends TestCase
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
        self::setDatabaseInstance(new FakeDashboardErrorDatabase());
    }

    public function testSecretarioWithoutScopeReturnsCanonicalForbiddenError(): void
    {
        $request = $this->createRequestWithUserId(321);
        $response = new Response();
        $controller = new SecretarioDashboardController($request, $response);

        $permissionService = $this->createMock(PermissionService::class);
        $permissionService->method('getEscopoDados')->willReturn(null);
        $this->setPrivateProperty($controller, 'permissionService', $permissionService);

        $result = $controller->secretario();
        $body = $this->responseBody($result);

        $this->assertSame(Response::HTTP_FORBIDDEN, $this->responseStatus($result));
        $this->assertTrue((bool) ($body['error'] ?? false));
        $this->assertSame('Escopo não encontrado', (string) ($body['message'] ?? ''));
    }

    public function testCoordenadorWithoutScopeReturnsCanonicalForbiddenError(): void
    {
        $request = $this->createRequestWithUserId(654);
        $response = new Response();
        $controller = new CoordenadorDashboardController($request, $response);

        $permissionService = $this->createMock(PermissionService::class);
        $permissionService->method('getEscopoDados')->willReturn(null);
        $this->setPrivateProperty($controller, 'permissionService', $permissionService);

        $result = $controller->coordenador();
        $body = $this->responseBody($result);

        $this->assertSame(Response::HTTP_FORBIDDEN, $this->responseStatus($result));
        $this->assertTrue((bool) ($body['error'] ?? false));
        $this->assertSame('Escopo não encontrado', (string) ($body['message'] ?? ''));
    }

    public function testMarcarAlertaLidoRepositoryFailureReturnsCanonicalServerError(): void
    {
        $request = $this->createRequestWithUserId(777);
        $response = new Response();
        $controller = new AlertaDashboardController($request, $response);

        $alertaRepo = $this->createMock(AlertaRepository::class);
        $alertaRepo->method('find')->willThrowException(new \RuntimeException('DB unavailable'));
        $this->setPrivateProperty($controller, 'alertaRepo', $alertaRepo);

        $result = $controller->marcarAlertaLido(10);
        $body = $this->responseBody($result);

        $this->assertSame(Response::HTTP_INTERNAL_ERROR, $this->responseStatus($result));
        $this->assertTrue((bool) ($body['error'] ?? false));
        $this->assertSame('Erro ao processar alerta', (string) ($body['message'] ?? ''));
    }

    public function testMarcarTodosLidosRepositoryFailureReturnsCanonicalServerError(): void
    {
        $request = $this->createRequestWithUserId(888);
        $response = new Response();
        $controller = new AlertaDashboardController($request, $response);

        $alertaRepo = $this->createMock(AlertaRepository::class);
        $alertaRepo->method('marcarTodosComoLidos')->willThrowException(new \RuntimeException('DB unavailable'));
        $this->setPrivateProperty($controller, 'alertaRepo', $alertaRepo);

        $result = $controller->marcarTodosLidos();
        $body = $this->responseBody($result);

        $this->assertSame(Response::HTTP_INTERNAL_ERROR, $this->responseStatus($result));
        $this->assertTrue((bool) ($body['error'] ?? false));
        $this->assertSame('Erro ao processar alertas', (string) ($body['message'] ?? ''));
    }

    private function createRequestWithUserId(int $userId): Request
    {
        $request = $this->createMock(Request::class);
        $request->method('getParam')
            ->willReturnCallback(
                fn(string $key, mixed $default = null): mixed => $key === '_user_id' ? $userId : $default
            );

        return $request;
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
}

class FakeDashboardErrorDatabase extends Database
{
    public function __construct()
    {
        // noop
    }

    public function fetchAll(string $sql, array $params = []): array
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
}
