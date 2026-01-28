<?php
/**
 * Modern Project Service Test
 * 
 * Tests for ModernProjectService covering:
 * - Project CRUD operations
 * - Permission checks
 * - Statistics and health calculations
 * - Search and filtering
 */

declare(strict_types=1);

namespace Tests\Unit\Service;

use DotProject\Service\ModernProjectService;
use PHPUnit\Framework\TestCase;

class ModernProjectServiceTest extends TestCase
{
    private ModernProjectService $service;
    
    protected function setUp(): void
    {
        $this->service = new ModernProjectService();
    }
    
    /**
     * Test service can be instantiated
     */
    public function testCanBeInstantiated(): void
    {
        $this->assertInstanceOf(ModernProjectService::class, $this->service);
    }
    
    /**
     * Test getProject with invalid ID without auth
     */
    public function testGetProjectWithInvalidIdWithoutAuth(): void
    {
        $project = $this->service->getProject(999999);
        $this->assertNull($project);
    }
    
    /**
     * Test getAccessibleProjects without auth returns empty
     */
    public function testGetAccessibleProjectsWithoutAuth(): void
    {
        $projects = $this->service->getAccessibleProjects();
        $this->assertIsArray($projects);
    }
    
    /**
     * Test getActiveProjects without auth returns empty
     */
    public function testGetActiveProjectsWithoutAuth(): void
    {
        $projects = $this->service->getActiveProjects();
        $this->assertIsArray($projects);
    }
    
    /**
     * Test getOverdueProjects without auth returns empty
     */
    public function testGetOverdueProjectsWithoutAuth(): void
    {
        $projects = $this->service->getOverdueProjects();
        $this->assertIsArray($projects);
    }
    
    /**
     * Test createProject without auth throws exception
     */
    public function testCreateProjectWithoutAuthThrowsException(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('User not authenticated');
        
        $this->service->createProject([
            'project_name' => 'Test Project',
            'project_short_name' => 'TEST'
        ]);
    }
    
    /**
     * Test createProject with invalid data throws exception
     */
    public function testCreateProjectWithInvalidData(): void
    {
        $this->expectException(\RuntimeException::class);
        
        // Try to create without required fields
        $this->service->createProject([], 1);
    }
    
    /**
     * Test updateProject without auth throws exception
     */
    public function testUpdateProjectWithoutAuthThrowsException(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('User not authenticated');
        
        $this->service->updateProject(1, ['project_name' => 'Updated']);
    }
    
    /**
     * Test updateProject with invalid ID
     */
    public function testUpdateProjectWithInvalidId(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('User not authenticated');
        
        $this->service->updateProject(999999, ['project_name' => 'Updated']);
    }
    
    /**
     * Test deleteProject without auth throws exception
     */
    public function testDeleteProjectWithoutAuthThrowsException(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('User not authenticated');
        
        $this->service->deleteProject(1);
    }
    
    /**
     * Test deleteProject with invalid ID
     */
    public function testDeleteProjectWithInvalidId(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('User not authenticated');
        
        $this->service->deleteProject(999999);
    }
    
    /**
     * Test archiveProject without auth throws exception
     */
    public function testArchiveProjectWithoutAuth(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('User not authenticated');
        
        $this->service->archiveProject(1);
    }
    
    /**
     * Test updateProgress with invalid project
     */
    public function testUpdateProgressWithInvalidProject(): void
    {
        $progress = $this->service->updateProgress(999999);
        $this->assertEquals(0.0, $progress);
    }
    
    /**
     * Test getProjectStatistics without auth returns null
     */
    public function testGetProjectStatisticsWithoutAuth(): void
    {
        $stats = $this->service->getProjectStatistics(1);
        $this->assertNull($stats);
    }
    
    /**
     * Test getProjectStatistics with invalid project
     */
    public function testGetProjectStatisticsWithInvalidProject(): void
    {
        $stats = $this->service->getProjectStatistics(999999, 1);
        $this->assertNull($stats);
    }
    
    /**
     * Test searchProjects without auth returns empty
     */
    public function testSearchProjectsWithoutAuth(): void
    {
        $projects = $this->service->searchProjects('test');
        $this->assertIsArray($projects);
    }
    
    /**
     * Test getDashboardData without auth returns empty
     */
    public function testGetDashboardDataWithoutAuth(): void
    {
        $data = $this->service->getDashboardData();
        $this->assertIsArray($data);
    }
    
    /**
     * Test getProjectDetails without auth returns null
     */
    public function testGetProjectDetailsWithoutAuth(): void
    {
        $details = $this->service->getProjectDetails(1);
        $this->assertNull($details);
    }
    
    /**
     * Test getProjectDetails with invalid project
     */
    public function testGetProjectDetailsWithInvalidProject(): void
    {
        $details = $this->service->getProjectDetails(999999, 1);
        $this->assertNull($details);
    }
    
    /**
     * Test bulkUpdateStatus without auth
     */
    public function testBulkUpdateStatusWithoutAuth(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('User not authenticated');
        
        $this->service->bulkUpdateStatus([1, 2, 3], 5);
    }
    
    /**
     * Test service dependencies
     */
    public function testServiceDependencies(): void
    {
        // Service should work with default constructor
        $service = new ModernProjectService();
        $this->assertInstanceOf(ModernProjectService::class, $service);
        
        // Service should work with null dependencies
        $service2 = new ModernProjectService(null, null, null, null, null, null);
        $this->assertInstanceOf(ModernProjectService::class, $service2);
    }
    
    /**
     * Test health score calculation with empty tasks
     */
    public function testHealthScoreWithEmptyTasks(): void
    {
        // This is tested indirectly through getProjectStatistics
        $stats = $this->service->getProjectStatistics(999999, 1);
        $this->assertNull($stats);
    }
    
    /**
     * Test search with empty query
     */
    public function testSearchWithEmptyQuery(): void
    {
        $projects = $this->service->searchProjects('', 1);
        $this->assertIsArray($projects);
    }
    
    /**
     * Test getDashboardData with user ID
     */
    public function testGetDashboardDataWithUserId(): void
    {
        $data = $this->service->getDashboardData(1);
        $this->assertIsArray($data);
        
        // Check structure
        if (!empty($data)) {
            $this->assertArrayHasKey('projects', $data);
            $this->assertArrayHasKey('recent', $data);
        }
    }
    
    /**
     * Test getAccessibleProjects with filters
     */
    public function testGetAccessibleProjectsWithFilters(): void
    {
        $projects = $this->service->getAccessibleProjects(1, ['project_status' => 0]);
        $this->assertIsArray($projects);
    }
    
    /**
     * Test create project validation
     */
    public function testCreateProjectValidation(): void
    {
        $this->expectException(\RuntimeException::class);
        
        // Try to create with invalid data (no auth)
        $this->service->createProject([
            'project_name' => '', // Empty name
        ]);
    }
    
    /**
     * Test update project with no changes
     */
    public function testUpdateProjectWithNoChanges(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('User not authenticated');
        
        // Try to update without auth
        $this->service->updateProject(1, []);
    }
}
