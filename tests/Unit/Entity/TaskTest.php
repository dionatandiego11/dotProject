<?php
/**
 * Task Entity Test
 * 
 * Unit tests for the Task entity class.
 * 
 * @package DotProject\Tests\Unit\Entity
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Tests\Unit\Entity;

use PHPUnit\Framework\TestCase;
use DotProject\Entity\Task;

/**
 * @covers \DotProject\Entity\Task
 */
class TaskTest extends TestCase
{
    /**
     * Test that a Task can be instantiated with attributes
     */
    public function testCanCreateTaskWithAttributes(): void
    {
        $task = new Task([
            'task_name' => 'Test Task',
            'task_project' => 1,
            'task_percent_complete' => 50,
            'task_milestone' => 1,
        ]);

        $this->assertSame('Test Task', $task->getName());
        $this->assertSame(1, $task->getProjectId());
        $this->assertSame(50, $task->getPercentComplete());
        $this->assertTrue($task->isMilestone());
    }

    /**
     * Test getName method
     */
    public function testGetName(): void
    {
        $task = new Task(['task_name' => 'My Task']);
        $this->assertSame('My Task', $task->getName());
    }

    /**
     * Test setName method
     */
    public function testSetName(): void
    {
        $task = new Task([]);
        $task->setName('New Task Name');
        $this->assertSame('New Task Name', $task->getName());
    }

    /**
     * Test getProjectId method
     */
    public function testGetProjectId(): void
    {
        $task = new Task(['task_project' => 42]);
        $this->assertSame(42, $task->getProjectId());
    }

    /**
     * Test getPercentComplete method with default value
     */
    public function testGetPercentCompleteDefaultsToZero(): void
    {
        $task = new Task([]);
        $this->assertSame(0, $task->getPercentComplete());
    }

    /**
     * Test getPercentComplete method with value
     */
    public function testGetPercentComplete(): void
    {
        $task = new Task(['task_percent_complete' => 75]);
        $this->assertSame(75, $task->getPercentComplete());
    }

    /**
     * Test isMilestone returns true when task is a milestone
     */
    public function testIsMilestoneReturnsTrue(): void
    {
        $task = new Task(['task_milestone' => 1]);
        $this->assertTrue($task->isMilestone());
    }

    /**
     * Test isMilestone returns false when task is not a milestone
     */
    public function testIsMilestoneReturnsFalse(): void
    {
        $task = new Task(['task_milestone' => 0]);
        $this->assertFalse($task->isMilestone());

        $task = new Task([]);
        $this->assertFalse($task->isMilestone());
    }

    /**
     * Test getStartDate method
     */
    public function testGetStartDate(): void
    {
        $task = new Task(['task_start_date' => '2026-01-15 09:00:00']);
        $this->assertSame('2026-01-15 09:00:00', $task->getStartDate());
    }

    /**
     * Test getStartDate returns null when not set
     */
    public function testGetStartDateReturnsNullWhenNotSet(): void
    {
        $task = new Task([]);
        $this->assertNull($task->getStartDate());
    }

    /**
     * Test getEndDate method
     */
    public function testGetEndDate(): void
    {
        $task = new Task(['task_end_date' => '2026-01-20 17:00:00']);
        $this->assertSame('2026-01-20 17:00:00', $task->getEndDate());
    }

    /**
     * Test getEndDate returns null when not set
     */
    public function testGetEndDateReturnsNullWhenNotSet(): void
    {
        $task = new Task([]);
        $this->assertNull($task->getEndDate());
    }

    /**
     * Test getTable returns correct table name
     */
    public function testGetTableReturnsTasksTable(): void
    {
        $this->assertSame('tasks', Task::getTable());
    }

    /**
     * Test getPrimaryKey returns correct key
     */
    public function testGetPrimaryKeyReturnsTaskId(): void
    {
        $this->assertSame('task_id', Task::getPrimaryKey());
    }

    /**
     * Test toArray returns all attributes
     */
    public function testToArrayIncludesAllAttributes(): void
    {
        $task = new Task([
            'task_name' => 'Test Task',
            'task_project' => 1,
            'task_percent_complete' => 25,
        ]);

        $array = $task->toArray();

        $this->assertArrayHasKey('task_name', $array);
        $this->assertArrayHasKey('task_project', $array);
        $this->assertArrayHasKey('task_percent_complete', $array);
        $this->assertSame('Test Task', $array['task_name']);
        $this->assertSame(1, $array['task_project']);
        $this->assertSame(25, $array['task_percent_complete']);
    }

    /**
     * Test array access
     */
    public function testArrayAccess(): void
    {
        $task = new Task(['task_name' => 'Test']);

        // offsetExists
        $this->assertTrue(isset($task['task_name']));
        $this->assertFalse(isset($task['nonexistent']));

        // offsetGet
        $this->assertSame('Test', $task['task_name']);

        // offsetSet
        $task['task_percent_complete'] = 50;
        $this->assertSame(50, $task['task_percent_complete']);
    }

    /**
     * Test magic getter
     */
    public function testMagicGetter(): void
    {
        $task = new Task(['task_name' => 'Magic Test']);
        $this->assertSame('Magic Test', $task->task_name);
    }

    /**
     * Test magic setter
     */
    public function testMagicSetter(): void
    {
        $task = new Task([]);
        $task->task_name = 'Set via magic';
        $this->assertSame('Set via magic', $task->getName());
    }

    /**
     * Test exists returns false for new entities
     */
    public function testExistsReturnsFalseForNewEntities(): void
    {
        $task = new Task(['task_name' => 'New Task']);
        $this->assertFalse($task->exists());
    }

    /**
     * Test getId returns null for new entities
     */
    public function testGetIdReturnsNullForNewEntities(): void
    {
        $task = new Task(['task_name' => 'New Task']);
        $this->assertNull($task->getId());
    }
}
