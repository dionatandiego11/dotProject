<?php

declare(strict_types=1);

namespace DotProject\Tests\Unit\Service;

use DotProject\Service\TaskStatusService;
use PHPUnit\Framework\TestCase;

class TaskStatusServiceTest extends TestCase
{
    private TaskStatusService $service;

    protected function setUp(): void
    {
        $this->service = new TaskStatusService();
    }

    public function testValidatesStatusRange(): void
    {
        $this->assertTrue($this->service->isValidStatus(0));
        $this->assertTrue($this->service->isValidStatus(7));
        $this->assertFalse($this->service->isValidStatus(-1));
        $this->assertFalse($this->service->isValidStatus(8));
    }

    public function testNormalizeSetsDonePercentToHundred(): void
    {
        $payload = ['status' => 3, 'percent_complete' => 20];
        $this->service->normalizeStatusPercent($payload, 2, 50);

        $this->assertSame(3, $payload['status']);
        $this->assertSame(100, $payload['percent_complete']);
    }

    public function testNormalizeSetsUndefinedStatusPercentToZero(): void
    {
        $payload = ['status' => 0, 'percent_complete' => 65];
        $this->service->normalizeStatusPercent($payload, 2, 50);

        $this->assertSame(0, $payload['status']);
        $this->assertSame(0, $payload['percent_complete']);
    }

    public function testNormalizeAppliesDefaultPercentForInProgress(): void
    {
        $payload = ['status' => 2, 'percent_complete' => 0];
        $this->service->normalizeStatusPercent($payload, 1, 10);

        $this->assertSame(2, $payload['status']);
        $this->assertSame(50, $payload['percent_complete']);
    }
}
