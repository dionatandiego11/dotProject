<?php

declare(strict_types=1);

namespace DotProject\Tests\Unit\Entity;

use DotProject\Entity\TaskEntity;
use PHPUnit\Framework\TestCase;

/**
 * @covers \DotProject\Entity\TaskEntity
 */
class TaskEntityTest extends TestCase
{
    public function testCanSetAndReadCoreFields(): void
    {
        $task = (new TaskEntity())
            ->setId(15)
            ->setName('Tarefa Moderna')
            ->setDescription('Descricao')
            ->setProjectId(3)
            ->setOwnerId(8)
            ->setAssignedTo(9)
            ->setParentTaskId(2)
            ->setStatus(0)
            ->setPriority(2)
            ->setPercentComplete(45)
            ->setEstimatedHours(12.5)
            ->setActualHours(3.0)
            ->setStartDate(new \DateTime('2026-02-10'))
            ->setEndDate(new \DateTime('2026-02-20'));

        $this->assertSame(15, $task->getId());
        $this->assertSame('Tarefa Moderna', $task->getName());
        $this->assertSame('Descricao', $task->getDescription());
        $this->assertSame(3, $task->getProjectId());
        $this->assertSame(8, $task->getOwnerId());
        $this->assertSame(9, $task->getAssignedTo());
        $this->assertSame(2, $task->getParentTaskId());
        $this->assertSame(0, $task->getStatus());
        $this->assertSame(2, $task->getPriority());
        $this->assertSame(45, $task->getPercentComplete());
        $this->assertSame(12.5, $task->getEstimatedHours());
        $this->assertSame(3.0, $task->getActualHours());
        $this->assertInstanceOf(\DateTime::class, $task->getStartDate());
        $this->assertInstanceOf(\DateTime::class, $task->getEndDate());
    }

    public function testSetEstadoAdjustsPercentAndFlags(): void
    {
        $task = (new TaskEntity())
            ->setName('Estado')
            ->setProjectId(1)
            ->setEstado('A_Fazer');

        $this->assertSame(0, $task->getPercentComplete());
        $this->assertFalse($task->isConcluida());

        $task->setEstado('Em_Andamento');
        $this->assertGreaterThanOrEqual(50, $task->getPercentComplete());
        $this->assertFalse($task->isConcluida());

        $task->setEstado('Concluida');
        $this->assertSame(100, $task->getPercentComplete());
        $this->assertTrue($task->isConcluida());
        $this->assertTrue($task->isCompleted());
    }

    public function testConcluirSetsEvidenceAndCompletionState(): void
    {
        $task = (new TaskEntity())
            ->setName('Conclusao')
            ->setProjectId(1)
            ->setEstado('Em_Andamento')
            ->setPercentComplete(65);

        $task->concluir('https://example.local/evidencia.pdf');

        $this->assertTrue($task->isConcluida());
        $this->assertSame(100, $task->getPercentComplete());
        $this->assertSame('https://example.local/evidencia.pdf', $task->getEvidenciaAnexo());
    }

    public function testOverdueAndDaysRemaining(): void
    {
        $task = (new TaskEntity())
            ->setName('Prazo')
            ->setProjectId(1)
            ->setEstado('Em_Andamento')
            ->setEndDate(new \DateTime('-1 day'));

        $this->assertTrue($task->isAtrasada());
        $this->assertTrue($task->isOverdue());
        $this->assertLessThan(0, $task->getDaysRemaining());
    }

    public function testToArrayContainsLegacyCompatibilityFields(): void
    {
        $task = (new TaskEntity())
            ->setId(30)
            ->setName('Compatibilidade')
            ->setDescription('Teste')
            ->setProjectId(10)
            ->setOwnerId(5)
            ->setAssignedTo(6)
            ->setParentTaskId(1)
            ->setStatus(2)
            ->setPriority(3)
            ->setPercentComplete(80)
            ->setEstimatedHours(8)
            ->setActualHours(4)
            ->setStartDate(new \DateTime('2026-02-11'))
            ->setEndDate(new \DateTime('2026-02-13'));

        $array = $task->toArray();

        $this->assertSame(30, $array['id']);
        $this->assertSame('Compatibilidade', $array['nome']);
        $this->assertSame(30, $array['task_id']);
        $this->assertSame('Compatibilidade', $array['task_name']);
        $this->assertSame(10, $array['task_project']);
        $this->assertSame(80, $array['task_percent_complete']);
        $this->assertSame(6, $array['task_assigned_to']);
    }
}
