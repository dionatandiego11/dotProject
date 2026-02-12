<?php

declare(strict_types=1);

namespace DotProject\Tests\Unit\Api\Formatter;

use DotProject\Api\Formatter\ProjectFormatter;
use PHPUnit\Framework\TestCase;

class ProjectFormatterTest extends TestCase
{
    private ProjectFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new ProjectFormatter();
    }

    public function testFormatsProjectSummaryPayload(): void
    {
        $result = $this->formatter->formatProject([
            'project_id' => 55,
            'project_name' => 'Projeto Capital',
            'project_short_name' => 'CAP',
            'project_company' => 11,
            'unidade_nome' => 'Secretaria A',
            'project_status' => 3,
            'project_percent_complete' => 71,
            'project_priority' => 2,
            'project_color_identifier' => '#112233',
            'project_start_date' => '2026-02-10',
            'project_end_date' => '2026-02-20',
        ]);

        $this->assertSame(55, $result['id']);
        $this->assertSame('Projeto Capital', $result['name']);
        $this->assertSame(11, $result['unidade_id']);
        $this->assertSame('Secretaria A', $result['unidade']['nome']);
        $this->assertSame(11, $result['company']['id']);
        $this->assertSame('Secretaria A', $result['company']['name']);
        $this->assertSame(3, $result['status']);
        $this->assertArrayNotHasKey('description', $result);
    }

    public function testFormatsProjectDetailedPayload(): void
    {
        $result = $this->formatter->formatProject([
            'project_id' => 9,
            'project_name' => 'Projeto Detalhado',
            'project_status' => 1,
            'project_description' => 'Descricao',
            'project_url' => 'https://example.test/project',
            'project_demo_url' => 'https://example.test/demo',
            'project_target_budget' => '1250.50',
            'project_actual_budget' => '900.10',
            'project_owner' => 7,
            'project_creator' => 8,
            'project_type' => 4,
            'project_parent' => 3,
        ], true);

        $this->assertSame('Descricao', $result['description']);
        $this->assertSame('https://example.test/project', $result['url']);
        $this->assertSame('https://example.test/demo', $result['demo_url']);
        $this->assertSame(1250.5, $result['budget']);
        $this->assertSame(900.1, $result['actual_budget']);
        $this->assertSame(7, $result['owner_id']);
        $this->assertSame(8, $result['creator_id']);
        $this->assertSame(4, $result['type']);
        $this->assertSame(3, $result['parent_id']);
    }

    public function testFormatsProjectTaskPayload(): void
    {
        $result = $this->formatter->formatTask([
            'task_id' => 12,
            'task_name' => 'Task A',
            'task_description' => 'Desc',
            'task_status' => 2,
            'task_priority' => 5,
            'task_percent_complete' => 33,
            'task_start_date' => '2026-02-11',
            'task_end_date' => '2026-02-12',
            'task_duration' => 4,
            'task_owner' => 2,
        ]);

        $this->assertSame(12, $result['id']);
        $this->assertSame('Task A', $result['name']);
        $this->assertSame('Desc', $result['description']);
        $this->assertSame(2, $result['status']);
        $this->assertSame(5, $result['priority']);
        $this->assertSame(33, $result['percent_complete']);
        $this->assertSame(2, $result['owner_id']);
    }
}
