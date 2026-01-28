<?php
/**
 * Testes unitários para ProjectService
 */

namespace DotProject\Tests\Unit\Api\Services;

use PHPUnit\Framework\TestCase;
use DotProject\Service\ProjectService;
use DotProject\Core\Database;

class ProjectServiceTest extends TestCase
{
    public function testServiceCanBeInstantiated(): void
    {
        $service = new ProjectService();
        $this->assertInstanceOf(ProjectService::class, $service);
    }
    
    public function testValidateProjectData(): void
    {
        $service = new ProjectService();
        
        // Dados válidos
        $validData = [
            'project_name' => 'Novo Projeto',
            'project_short_name' => 'PROJ-001',
            'project_start_date' => '2026-01-27',
            'project_status' => '0'
        ];
        
        $errors = $service->validate($validData);
        $this->assertEmpty($errors);
        
        // Dados inválidos - nome vazio
        $invalidData = [
            'project_name' => '',
            'project_short_name' => 'PROJ-002',
        ];
        
        $errors = $service->validate($invalidData);
        $this->assertNotEmpty($errors);
        $this->assertArrayHasKey('project_name', $errors);
    }
    
    public function testCalculateProjectProgress(): void
    {
        $service = new ProjectService();
        
        // Mock de tarefas
        $tasks = [
            ['task_percent_complete' => 100],
            ['task_percent_complete' => 50],
            ['task_percent_complete' => 0],
            ['task_percent_complete' => 100],
        ];
        
        $progress = $service->calculateProgress($tasks);
        
        // Média: (100 + 50 + 0 + 100) / 4 = 62.5
        $this->assertEquals(62.5, $progress);
    }
    
    public function testCalculateProgressWithEmptyTasks(): void
    {
        $service = new ProjectService();
        
        $progress = $service->calculateProgress([]);
        
        $this->assertEquals(0, $progress);
    }
}
