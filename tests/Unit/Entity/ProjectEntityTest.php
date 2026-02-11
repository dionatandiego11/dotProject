<?php

declare(strict_types=1);

namespace DotProject\Tests\Unit\Entity;

use DotProject\Entity\ProjectEntity;
use PHPUnit\Framework\TestCase;

/**
 * @covers \DotProject\Entity\ProjectEntity
 */
class ProjectEntityTest extends TestCase
{
    public function testCanSetAndReadCoreFields(): void
    {
        $project = (new ProjectEntity())
            ->setId(12)
            ->setName('Projeto Moderno')
            ->setShortName('PMOD')
            ->setDescription('Descricao')
            ->setStatus(0)
            ->setPriority(2)
            ->setPercentComplete(35)
            ->setOwnerId(7)
            ->setCompanyId(4)
            ->setColorIdentifier('#4A90D9')
            ->setUrl('https://example.local/projeto');

        $this->assertSame(12, $project->getId());
        $this->assertSame('Projeto Moderno', $project->getName());
        $this->assertSame('PMOD', $project->getShortName());
        $this->assertSame('Descricao', $project->getDescription());
        $this->assertSame(0, $project->getStatus());
        $this->assertSame(2, $project->getPriority());
        $this->assertSame(35, $project->getPercentComplete());
        $this->assertSame(7, $project->getOwnerId());
        $this->assertSame(4, $project->getCompanyId());
        $this->assertSame('#4A90D9', $project->getColorIdentifier());
        $this->assertSame('https://example.local/projeto', $project->getUrl());
    }

    public function testStatusFlags(): void
    {
        $project = (new ProjectEntity())->setName('Status');

        $project->setStatus(0);
        $this->assertTrue($project->isActive());
        $this->assertFalse($project->isCompleted());

        $project->setStatus(1);
        $this->assertFalse($project->isActive());
        $this->assertTrue($project->isCompleted());
    }

    public function testOverdueAndDaysRemaining(): void
    {
        $project = (new ProjectEntity())
            ->setName('Prazos')
            ->setStatus(0)
            ->setEndDate(new \DateTime('-2 days'));

        $this->assertTrue($project->isOverdue());
        $this->assertLessThan(0, $project->getDaysRemaining());

        $project->setEndDate(new \DateTime('+5 days'));
        $this->assertFalse($project->isOverdue());
        $this->assertGreaterThanOrEqual(0, $project->getDaysRemaining());
    }

    public function testToArrayIncludesComputedFields(): void
    {
        $project = (new ProjectEntity())
            ->setId(20)
            ->setName('Projeto Array')
            ->setShortName('PARR')
            ->setDescription('Teste')
            ->setStartDate(new \DateTime('2026-02-11'))
            ->setEndDate(new \DateTime('+1 day'))
            ->setStatus(0)
            ->setPriority(3)
            ->setPercentComplete(50)
            ->setOwnerId(9)
            ->setCompanyId(2)
            ->setColorIdentifier('#123456')
            ->setUrl('https://example.local/array');

        $array = $project->toArray();

        $this->assertSame(20, $array['id']);
        $this->assertSame('Projeto Array', $array['name']);
        $this->assertSame('PARR', $array['short_name']);
        $this->assertSame(0, $array['status']);
        $this->assertSame(50, $array['percent_complete']);
        $this->assertTrue($array['is_active']);
        $this->assertFalse($array['is_completed']);
        $this->assertIsInt($array['days_remaining']);
    }
}
