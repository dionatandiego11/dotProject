<?php
/**
 * Project Entity Test
 * 
 * Unit tests for the Project entity class.
 * 
 * @package DotProject\Tests\Unit\Entity
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Tests\Unit\Entity;

use PHPUnit\Framework\TestCase;
use DotProject\Entity\Project;

/**
 * @covers \DotProject\Entity\Project
 */
class ProjectTest extends TestCase
{
    /**
     * Test that a Project can be instantiated with attributes
     */
    public function testCanCreateProjectWithAttributes(): void
    {
        $project = new Project([
            'project_name' => 'Test Project',
            'project_short_name' => 'TST',
            'project_status' => 3,
            'project_percent_complete' => 50,
        ]);

        $this->assertSame('Test Project', $project->getName());
        $this->assertSame(3, $project->getStatus());
        $this->assertSame(50, $project->getPercentComplete());
    }

    /**
     * Test getName method
     */
    public function testGetName(): void
    {
        $project = new Project(['project_name' => 'My Project']);
        $this->assertSame('My Project', $project->getName());
    }

    /**
     * Test setName method
     */
    public function testSetName(): void
    {
        $project = new Project([]);
        $project->setName('New Name');
        $this->assertSame('New Name', $project->getName());
    }

    /**
     * Test getStatus method with default value
     */
    public function testGetStatusDefaultsToZero(): void
    {
        $project = new Project([]);
        $this->assertSame(0, $project->getStatus());
    }

    /**
     * Test setStatus method
     */
    public function testSetStatus(): void
    {
        $project = new Project([]);
        $project->setStatus(3);
        $this->assertSame(3, $project->getStatus());
    }

    /**
     * Test isActive returns true for status 3
     */
    public function testIsActiveReturnsTrueForStatus3(): void
    {
        $project = new Project(['project_status' => 3]);
        $this->assertTrue($project->isActive());
    }

    /**
     * Test isActive returns false for other statuses
     */
    public function testIsActiveReturnsFalseForOtherStatuses(): void
    {
        $project = new Project(['project_status' => 1]);
        $this->assertFalse($project->isActive());

        $project->setStatus(5);
        $this->assertFalse($project->isActive());
    }

    /**
     * Test isComplete returns true when progress is 100 or more
     */
    public function testIsCompleteReturnsTrueAt100Percent(): void
    {
        $project = new Project(['project_percent_complete' => 100]);
        $this->assertTrue($project->isComplete());
    }

    /**
     * Test isComplete returns false when progress is less than 100
     */
    public function testIsCompleteReturnsFalseBelow100Percent(): void
    {
        $project = new Project(['project_percent_complete' => 99]);
        $this->assertFalse($project->isComplete());
    }

    /**
     * Test getOwnerId method
     */
    public function testGetOwnerId(): void
    {
        $project = new Project(['project_owner' => 42]);
        $this->assertSame(42, $project->getOwnerId());
    }

    /**
     * Test getOwnerId returns null when not set
     */
    public function testGetOwnerIdReturnsNullWhenNotSet(): void
    {
        $project = new Project([]);
        $this->assertNull($project->getOwnerId());
    }

    /**
     * Test toArray returns all attributes including id
     */
    public function testToArrayIncludesAllAttributes(): void
    {
        $project = new Project([
            'project_name' => 'Test',
            'project_status' => 3,
        ]);

        $array = $project->toArray();

        $this->assertArrayHasKey('project_name', $array);
        $this->assertArrayHasKey('project_status', $array);
        $this->assertSame('Test', $array['project_name']);
        $this->assertSame(3, $array['project_status']);
    }

    /**
     * Test getTable returns correct table name
     */
    public function testGetTableReturnsProjectsTable(): void
    {
        $this->assertSame('projects', Project::getTable());
    }

    /**
     * Test getPrimaryKey returns correct key
     */
    public function testGetPrimaryKeyReturnsProjectId(): void
    {
        $this->assertSame('project_id', Project::getPrimaryKey());
    }

    /**
     * Test array access
     */
    public function testArrayAccess(): void
    {
        $project = new Project(['project_name' => 'Test']);

        // offsetExists
        $this->assertTrue(isset($project['project_name']));
        $this->assertFalse(isset($project['nonexistent']));

        // offsetGet
        $this->assertSame('Test', $project['project_name']);

        // offsetSet
        $project['project_status'] = 3;
        $this->assertSame(3, $project['project_status']);
    }

    /**
     * Test magic getter
     */
    public function testMagicGetter(): void
    {
        $project = new Project(['project_name' => 'Magic Test']);
        $this->assertSame('Magic Test', $project->project_name);
    }

    /**
     * Test magic setter
     */
    public function testMagicSetter(): void
    {
        $project = new Project([]);
        $project->project_name = 'Set via magic';
        $this->assertSame('Set via magic', $project->getName());
    }

    /**
     * Test isDirty detects changes
     */
    public function testIsDirtyDetectsChanges(): void
    {
        $project = new Project(['project_name' => 'Original']);

        // New project is dirty (no original state synced)
        $project->setName('Changed');
        $this->assertTrue($project->isDirty());
    }
}
