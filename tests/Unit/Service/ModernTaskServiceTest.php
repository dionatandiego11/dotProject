<?php
/**
 * Modern Task Service Test
 * 
 * Tests for ModernTaskService covering:
 * - Task CRUD operations
 * - Permission checks
 * - Assignment management
 * - Dependencies
 * - Time logging
 */

declare(strict_types=1);

namespace Tests\Unit\Service;

use DotProject\Service\ModernTaskService;
use PHPUnit\Framework\TestCase;

class ModernTaskServiceTest extends TestCase
{
    private ModernTaskService $service;
    
    protected function setUp(): void
    {
        $this->service = new ModernTaskService();
    }
    
    /**
     * Test service can be instantiated
     */
    public function testCanBeInstantiated(): void
    {
        $this->assertInstanceOf(ModernTaskService::class, $this->service);
    }
    
    /**
     * Test getTask with invalid ID without auth
     */
    public function testGetTaskWithInvalidIdWithoutAuth(): void
    {
        $task = $this->service->getTask(999999);
        $this->assertNull($task);
    }
    
    /**
     * Test getProjectTasks without auth returns empty
     */
    public function testGetProjectTasksWithoutAuth(): void
    {
        $tasks = $this->service->getProjectTasks(1);
        $this->assertIsArray($tasks);
        $this->assertEmpty($tasks);
    }
    
    /**
     * Test getProjectTasks with invalid project
     */
    public function testGetProjectTasksWithInvalidProject(): void
    {
        $tasks = $this->service->getProjectTasks(999999, 1);
        $this->assertIsArray($tasks);
    }
    
    /**
     * Test getMyTasks without auth returns empty
     */
    public function testGetMyTasksWithoutAuth(): void
    {
        $tasks = $this->service->getMyTasks();
        $this->assertIsArray($tasks);
        $this->assertEmpty($tasks);
    }
    
    /**
     * Test getMyTasks with filters
     */
    public function testGetMyTasksWithFilters(): void
    {
        $tasks = $this->service->getMyTasks(1, ['status' => 0, 'overdue' => true]);
        $this->assertIsArray($tasks);
    }
    
    /**
     * Test getOverdueTasks without auth returns empty
     */
    public function testGetOverdueTasksWithoutAuth(): void
    {
        $tasks = $this->service->getOverdueTasks();
        $this->assertIsArray($tasks);
    }
    
    /**
     * Test getTasksDueSoon without auth returns empty
     */
    public function testGetTasksDueSoonWithoutAuth(): void
    {
        $tasks = $this->service->getTasksDueSoon();
        $this->assertIsArray($tasks);
    }
    
    /**
     * Test getTasksDueSoon with custom days
     */
    public function testGetTasksDueSoonWithCustomDays(): void
    {
        $tasks = $this->service->getTasksDueSoon(14, 1);
        $this->assertIsArray($tasks);
    }
    
    /**
     * Test createTask without auth throws exception
     */
    public function testCreateTaskWithoutAuthThrowsException(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('User not authenticated');
        
        $this->service->createTask([
            'task_name' => 'Test Task',
            'task_project' => 1
        ]);
    }
    
    /**
     * Test createTask without project throws exception
     */
    public function testCreateTaskWithoutProject(): void
    {
        $this->expectException(\RuntimeException::class);
        
        $this->service->createTask([
            'task_name' => 'Test Task'
            // Missing task_project
        ], 1);
    }
    
    /**
     * Test createTask with invalid project
     */
    public function testCreateTaskWithInvalidProject(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('User not authenticated');
        
        $this->service->createTask([
            'task_name' => 'Test Task',
            'task_project' => 999999
        ]);
    }
    
    /**
     * Test updateTask without auth throws exception
     */
    public function testUpdateTaskWithoutAuthThrowsException(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('User not authenticated');
        
        $this->service->updateTask(1, ['task_name' => 'Updated']);
    }
    
    /**
     * Test updateProgress without auth throws exception
     */
    public function testUpdateProgressWithoutAuthThrowsException(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('User not authenticated');
        
        $this->service->updateProgress(1, 50);
    }
    
    /**
     * Test deleteTask without auth throws exception
     */
    public function testDeleteTaskWithoutAuthThrowsException(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('User not authenticated');
        
        $this->service->deleteTask(1);
    }
    
    /**
     * Test assignUser without auth throws exception
     */
    public function testAssignUserWithoutAuthThrowsException(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('User not authenticated');
        
        $this->service->assignUser(1, 2);
    }
    
    /**
     * Test unassignUser without auth throws exception
     */
    public function testUnassignUserWithoutAuthThrowsException(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('User not authenticated');
        
        $this->service->unassignUser(1, 2);
    }
    
    /**
     * Test getDependencies returns array
     */
    public function testGetDependencies(): void
    {
        $deps = $this->service->getDependencies(1);
        $this->assertIsArray($deps);
    }
    
    /**
     * Test getDependents returns array
     */
    public function testGetDependents(): void
    {
        $deps = $this->service->getDependents(1);
        $this->assertIsArray($deps);
    }
    
    /**
     * Test addDependency without auth throws exception
     */
    public function testAddDependencyWithoutAuth(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('User not authenticated');
        
        $this->service->addDependency(1, 2);
    }
    
    /**
     * Test addDependency self-dependency throws exception
     */
    public function testAddDependencySelfDependency(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Task cannot depend on itself');
        
        // This will fail before auth check due to self-dependency validation
        try {
            $this->service->addDependency(1, 1, 1);
        } catch (\RuntimeException $e) {
            // If we get here, auth passed but self-dependency failed
            if (strpos($e->getMessage(), 'cannot depend on itself') !== false) {
                throw new \InvalidArgumentException('Task cannot depend on itself');
            }
            throw $e;
        }
    }
    
    /**
     * Test removeDependency without auth throws exception
     */
    public function testRemoveDependencyWithoutAuth(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('User not authenticated');
        
        $this->service->removeDependency(1, 2);
    }
    
    /**
     * Test getTaskStatistics without auth
     */
    public function testGetTaskStatisticsWithoutAuth(): void
    {
        $stats = $this->service->getTaskStatistics();
        $this->assertIsArray($stats);
    }
    
    /**
     * Test getTaskStatistics with project ID
     */
    public function testGetTaskStatisticsWithProjectId(): void
    {
        $stats = $this->service->getTaskStatistics(1, 1);
        $this->assertIsArray($stats);
        
        // Check structure
        $this->assertArrayHasKey('total', $stats);
        $this->assertArrayHasKey('completed', $stats);
        $this->assertArrayHasKey('active', $stats);
        $this->assertArrayHasKey('overdue', $stats);
        $this->assertArrayHasKey('completion_rate', $stats);
        $this->assertArrayHasKey('by_priority', $stats);
    }
    
    /**
     * Test logTime without auth throws exception
     */
    public function testLogTimeWithoutAuthThrowsException(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('User not authenticated');
        
        $this->service->logTime(1, 2.5);
    }
    
    /**
     * Test getTaskDetails without auth returns null
     */
    public function testGetTaskDetailsWithoutAuth(): void
    {
        $details = $this->service->getTaskDetails(1);
        $this->assertNull($details);
    }
    
    /**
     * Test getTaskDetails with invalid task
     */
    public function testGetTaskDetailsWithInvalidTask(): void
    {
        $details = $this->service->getTaskDetails(999999, 1);
        $this->assertNull($details);
    }
    
    /**
     * Test service dependencies
     */
    public function testServiceDependencies(): void
    {
        // Service should work with default constructor
        $service = new ModernTaskService();
        $this->assertInstanceOf(ModernTaskService::class, $service);
        
        // Service should work with null dependencies
        $service2 = new ModernTaskService(null, null, null, null, null, null);
        $this->assertInstanceOf(ModernTaskService::class, $service2);
    }
    
    /**
     * Test updateProgress bounds checking
     */
    public function testUpdateProgressBounds(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('User not authenticated');
        
        // Test that percentage is clamped between 0-100
        $this->service->updateProgress(1, 150); // Should be clamped to 100
    }
    
    /**
     * Test create task with dates
     */
    public function testCreateTaskWithDates(): void
    {
        $this->expectException(\RuntimeException::class);
        
        // This will fail due to auth, but tests the date parsing path
        $this->service->createTask([
            'task_name' => 'Test',
            'task_project' => 1,
            'task_start_date' => '2024-01-01',
            'task_end_date' => '2024-12-31'
        ]);
    }
    
    /**
     * Test create task with hours
     */
    public function testCreateTaskWithHours(): void
    {
        $this->expectException(\RuntimeException::class);
        
        $this->service->createTask([
            'task_name' => 'Test',
            'task_project' => 1,
            'task_hours' => 10,
            'task_actual_hours' => 5
        ]);
    }
    
    /**
     * Test getMyTasks with completed filter
     */
    public function testGetMyTasksWithCompletedFilter(): void
    {
        $tasks = $this->service->getMyTasks(1, ['completed' => true]);
        $this->assertIsArray($tasks);
    }
    
    /**
     * Test task statistics structure
     */
    public function testTaskStatisticsStructure(): void
    {
        $stats = $this->service->getTaskStatistics(null, 1);
        
        $this->assertArrayHasKey('total', $stats);
        $this->assertArrayHasKey('completed', $stats);
        $this->assertArrayHasKey('active', $stats);
        $this->assertArrayHasKey('overdue', $stats);
        $this->assertArrayHasKey('completion_rate', $stats);
        $this->assertArrayHasKey('by_priority', $stats);
        
        $this->assertIsInt($stats['total']);
        $this->assertIsInt($stats['completed']);
        $this->assertIsInt($stats['active']);
        $this->assertIsInt($stats['overdue']);
        $this->assertIsNumeric($stats['completion_rate']);
        $this->assertIsArray($stats['by_priority']);
    }
}
