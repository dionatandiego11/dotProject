<?php

namespace DotProject\Tests\Integration;

use DotProject\Api\Controller\Dashboard\SecretarioDashboardController;
use DotProject\Api\Controller\Dashboard\TecnicoDashboardController;
use DotProject\Api\Request;
use DotProject\Api\Response;
use PHPUnit\Framework\TestCase;

class DashboardIntegrationTest extends TestCase
{
    private $request;
    private $response;

    protected function setUp(): void
    {
        // Mock Request and Response
        $this->request = $this->createMock(Request::class);
        $this->response = $this->createMock(Response::class);

        // Setup Response to behave nicely (chaining)
        $this->response->method('json')->willReturn($this->response);
        $this->response->method('error')->willReturn($this->response);
    }

    public function testDashboardSecretarioCycle()
    {
        // Setup for user ID (arbitrary int)
        // We use willReturnMap to handle potential multiple calls
        $this->request->method('getParam')
            ->willReturnMap([
                ['_user_id', null, 123],
                ['_user_data', [], []]
            ]);

        $this->request->method('getUri')->willReturn('/api/v1/dashboard/secretario');

        // Instantiate Controller
        $controller = new SecretarioDashboardController($this->request, $this->response);

        // Attempt to call secretario method
        // We expect this to potentially fail if the bug exists
        try {
            $controller->secretario();
            $this->assertTrue(true, "Secretario dashboard executed without fatal error");
        } catch (\Error $e) {
            $this->fail("Fatal Error in Secretario Dashboard: " . $e->getMessage());
        } catch (\Exception $e) {
            $this->fail("Exception in Secretario Dashboard: " . $e->getMessage());
        }
    }

    public function testDashboardTecnicoCacheKey()
    {
        $this->request->method('getParam')
            ->willReturnMap([
                ['_user_id', null, 456],
                ['_user_data', [], []]
            ]);

        $this->request->method('getUri')->willReturn('/api/v1/dashboard/tecnico');

        $controller = new TecnicoDashboardController($this->request, $this->response);

        try {
            $controller->tecnico();
            $this->assertTrue(true, "Tecnico dashboard executed without fatal error");
        } catch (\TypeError $e) {
            if (str_contains($e->getMessage(), 'must be of type string')) {
                $this->fail("Reproduced CacheKey Type Error: " . $e->getMessage());
            }
            throw $e;
        }
    }
}
