<?php
/**
 * Testes unitários para ProjectController
 */

namespace DotProject\Tests\Unit\Api\Controllers;

use DotProject\Api\Controller\ProjectController;
use DotProject\Api\Request;
use DotProject\Api\Response;
use PHPUnit\Framework\TestCase;

class ProjectControllerTest extends TestCase
{
    public function testFormatProjectUsesUnidadeAsSourceOfTruth(): void
    {
        $controller = new ProjectController($this->createMock(Request::class), new Response());

        $method = new \ReflectionMethod(ProjectController::class, 'formatProject');
        $method->setAccessible(true);

        $row = [
            'project_id' => 7,
            'project_name' => 'Projeto Teste',
            'project_short_name' => 'PT',
            'project_company' => 40,
            'unidade_nome' => 'Gestao de Projetos',
            'company_name' => 'Empresa 40',
            'project_status' => 3,
            'project_percent_complete' => 55,
            'project_priority' => 2,
            'project_color_identifier' => '#4A90D9',
            'project_start_date' => '2026-01-01',
            'project_end_date' => '2026-12-31',
        ];

        $data = $method->invoke($controller, $row, false);

        $this->assertSame(40, $data['unidade_id']);
        $this->assertSame(40, $data['unidade']['id']);
        $this->assertSame('Gestao de Projetos', $data['unidade']['nome']);
        $this->assertSame(40, $data['company_id']);
        $this->assertSame(40, $data['company']['id']);
        $this->assertSame('Gestao de Projetos', $data['company']['name']);
    }

    public function testResolveUnidadeFromBodyPrioritizesUnidadeId(): void
    {
        $controller = new ProjectController($this->createMock(Request::class), new Response());

        $method = new \ReflectionMethod(ProjectController::class, 'resolveUnidadeFromBody');
        $method->setAccessible(true);

        $this->assertSame(40, $method->invoke($controller, [
            'unidade_id' => 40,
            'company_id' => 22,
        ]));
        $this->assertSame(22, $method->invoke($controller, [
            'company_id' => 22,
        ]));
        $this->assertNull($method->invoke($controller, [
            'unidade_id' => '',
            'company_id' => null,
        ]));
    }
}
