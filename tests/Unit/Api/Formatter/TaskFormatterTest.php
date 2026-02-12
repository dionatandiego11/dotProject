<?php

declare(strict_types=1);

namespace DotProject\Tests\Unit\Api\Formatter;

use DotProject\Api\Formatter\TaskFormatter;
use PHPUnit\Framework\TestCase;

class TaskFormatterTest extends TestCase
{
    private TaskFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new TaskFormatter();
    }

    public function testFormatsSummaryPayload(): void
    {
        $result = $this->formatter->format([
            'task_id' => 11,
            'task_name' => 'Implementar API',
            'task_project' => 7,
            'project_name' => 'Projeto A',
            'task_status' => 2,
            'task_priority' => 4,
            'task_percent_complete' => 45,
            'task_start_date' => '2026-02-10',
            'task_end_date' => '2026-02-20',
            'task_duration' => 5,
            'task_owner' => 3,
            'task_assigned_to' => 9,
            'task_milestone' => 1,
        ]);

        $this->assertSame(11, $result['id']);
        $this->assertSame('Implementar API', $result['name']);
        $this->assertSame(7, $result['project']['id']);
        $this->assertSame('Projeto A', $result['project']['name']);
        $this->assertSame(9, $result['assigned_to']);
        $this->assertTrue($result['milestone']);
        $this->assertArrayNotHasKey('description', $result);
    }

    public function testFormatsDetailedPayload(): void
    {
        $result = $this->formatter->format([
            'task_id' => 20,
            'task_name' => 'Task detalhada',
            'task_owner' => 2,
            'task_description' => 'Descricao',
            'task_hours_worked' => '4.5',
            'task_creator' => 12,
            'task_parent' => 5,
            'task_order' => 13,
            'task_type' => 1,
            'task_access' => 0,
            'task_created' => '2026-02-12 10:00:00',
            'task_updated' => '2026-02-12 11:00:00',
        ], true);

        $this->assertSame('Descricao', $result['description']);
        $this->assertSame(4.5, $result['hours_worked']);
        $this->assertSame(12, $result['creator_id']);
        $this->assertSame(5, $result['parent_id']);
        $this->assertSame(13, $result['order']);
        $this->assertSame(1, $result['type']);
        $this->assertSame('2026-02-12 10:00:00', $result['created']);
        $this->assertSame('2026-02-12 11:00:00', $result['updated']);
        $this->assertSame(2, $result['assigned_to']);
    }
}
