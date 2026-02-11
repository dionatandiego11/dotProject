<?php

declare(strict_types=1);

namespace DotProject\Tests\Integration;

use DotProject\Api\Controller\Admin\AdminDashboardController;
use DotProject\Api\Request;
use DotProject\Api\Response;
use PHPUnit\Framework\TestCase;

class AdminOnboardingReadinessIntegrationTest extends TestCase
{
    public function testOnboardingReadinessReturnsCanonicalChecklistStructure(): void
    {
        $request = $this->createMock(Request::class);
        $request->method('getParam')
            ->willReturnCallback(
                static fn(string $key, mixed $default = null): mixed => $key === '_user_id' ? 1 : $default
            );

        $response = new Response();
        $controller = new AdminDashboardController($request, $response);

        $result = $controller->onboardingReadiness();
        $body = $this->responseBody($result);

        $this->assertSame(200, $this->responseStatus($result));
        $this->assertIsArray($body['data'] ?? null);

        $data = $body['data'];
        $this->assertArrayHasKey('summary', $data);
        $this->assertArrayHasKey('progress', $data);
        $this->assertArrayHasKey('checklist', $data);
        $this->assertArrayHasKey('next_steps', $data);

        $summary = $data['summary'];
        $this->assertArrayHasKey('niveis_ativos', $summary);
        $this->assertArrayHasKey('unidades_ativas', $summary);
        $this->assertArrayHasKey('usuarios_ativos', $summary);
        $this->assertArrayHasKey('vinculos_ativos', $summary);
        $this->assertArrayHasKey('projetos_cadastrados', $summary);
        $this->assertArrayHasKey('tarefas_cadastradas', $summary);

        $progress = $data['progress'];
        $this->assertArrayHasKey('completed', $progress);
        $this->assertArrayHasKey('total', $progress);
        $this->assertArrayHasKey('percentage', $progress);
        $this->assertIsInt($progress['completed']);
        $this->assertIsInt($progress['total']);
        $this->assertIsInt($progress['percentage']);
        $this->assertGreaterThanOrEqual(0, $progress['percentage']);
        $this->assertLessThanOrEqual(100, $progress['percentage']);

        $checklist = $data['checklist'];
        $this->assertIsArray($checklist);
        $this->assertNotEmpty($checklist);

        foreach ($checklist as $item) {
            $this->assertIsArray($item);
            $this->assertArrayHasKey('key', $item);
            $this->assertArrayHasKey('label', $item);
            $this->assertArrayHasKey('status', $item);
            $this->assertArrayHasKey('current', $item);
            $this->assertArrayHasKey('target', $item);
            $this->assertArrayHasKey('hint', $item);
            $this->assertContains($item['status'], ['done', 'pending']);
        }

        $this->assertIsArray($data['next_steps']);
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
