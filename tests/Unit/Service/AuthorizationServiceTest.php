<?php
/**
 * Authorization Service Test
 * 
 * Tests for AuthorizationService covering:
 * - Permission checking
 * - Role-based access
 * - Project/Task access control
 * - Multi-tenant isolation
 */

declare(strict_types=1);

namespace Tests\Unit\Service;

use DotProject\Service\AuthorizationService;
use PHPUnit\Framework\TestCase;

class AuthorizationServiceTest extends TestCase
{
    private AuthorizationService $service;
    
    protected function setUp(): void
    {
        $this->service = AuthorizationService::getInstance();
    }
    
    /**
     * Test role constants are defined correctly
     */
    public function testRoleConstants(): void
    {
        $this->assertEquals(1, AuthorizationService::ROLE_ADMIN);
        $this->assertEquals(2, AuthorizationService::ROLE_DIRECTOR);
        $this->assertEquals(3, AuthorizationService::ROLE_MANAGER);
        $this->assertEquals(4, AuthorizationService::ROLE_SUPERVISOR);
        $this->assertEquals(5, AuthorizationService::ROLE_USER);
        $this->assertEquals(6, AuthorizationService::ROLE_GUEST);
    }
    
    /**
     * Test permission constants
     */
    public function testPermissionConstants(): void
    {
        $this->assertEquals('view', AuthorizationService::PERMISSION_VIEW);
        $this->assertEquals('create', AuthorizationService::PERMISSION_CREATE);
        $this->assertEquals('edit', AuthorizationService::PERMISSION_EDIT);
        $this->assertEquals('delete', AuthorizationService::PERMISSION_DELETE);
        $this->assertEquals('admin', AuthorizationService::PERMISSION_ADMIN);
    }
    
    /**
     * Test resource constants
     */
    public function testResourceConstants(): void
    {
        $this->assertEquals('project', AuthorizationService::RESOURCE_PROJECT);
        $this->assertEquals('task', AuthorizationService::RESOURCE_TASK);
        $this->assertEquals('user', AuthorizationService::RESOURCE_USER);
    }
    
    /**
     * Test singleton pattern
     */
    public function testSingleton(): void
    {
        $instance1 = AuthorizationService::getInstance();
        $instance2 = AuthorizationService::getInstance();
        
        $this->assertSame($instance1, $instance2);
    }
    
    /**
     * Test admin has all permissions
     */
    public function testAdminHasAllPermissions(): void
    {
        // Admin role should have all permissions
        $resources = [
            AuthorizationService::RESOURCE_PROJECT,
            AuthorizationService::RESOURCE_TASK,
            AuthorizationService::RESOURCE_USER,
            AuthorizationService::RESOURCE_COMPANY,
            AuthorizationService::RESOURCE_FILE,
        ];
        
        $permissions = [
            AuthorizationService::PERMISSION_VIEW,
            AuthorizationService::PERMISSION_CREATE,
            AuthorizationService::PERMISSION_EDIT,
            AuthorizationService::PERMISSION_DELETE,
            AuthorizationService::PERMISSION_ADMIN,
        ];
        
        foreach ($resources as $resource) {
            foreach ($permissions as $permission) {
                $this->assertTrue(
                    $this->service->can($resource, $permission, 1), // User ID 1 assumed admin
                    "Admin should have {$permission} permission on {$resource}"
                );
            }
        }
    }
    
    /**
     * Test isAdmin method
     */
    public function testIsAdmin(): void
    {
        // Without authenticated user
        $this->assertFalse($this->service->isAdmin());
        
        // With null user ID
        $this->assertFalse($this->service->isAdmin(null));
    }
    
    /**
     * Test isAuthenticated without user
     */
    public function testIsAuthenticatedWithoutUser(): void
    {
        $this->assertFalse($this->service->isAuthenticated());
    }
    
    /**
     * Test getCurrentUser without user
     */
    public function testGetCurrentUserWithoutUser(): void
    {
        $this->assertNull($this->service->getCurrentUser());
    }
    
    /**
     * Test getRoleName for all roles
     */
    public function testGetRoleName(): void
    {
        $this->assertEquals('Administrator', $this->service->getRoleName(AuthorizationService::ROLE_ADMIN));
        $this->assertEquals('Director', $this->service->getRoleName(AuthorizationService::ROLE_DIRECTOR));
        $this->assertEquals('Manager', $this->service->getRoleName(AuthorizationService::ROLE_MANAGER));
        $this->assertEquals('Supervisor', $this->service->getRoleName(AuthorizationService::ROLE_SUPERVISOR));
        $this->assertEquals('User', $this->service->getRoleName(AuthorizationService::ROLE_USER));
        $this->assertEquals('Guest', $this->service->getRoleName(AuthorizationService::ROLE_GUEST));
        $this->assertEquals('Unknown', $this->service->getRoleName(999));
    }
    
    /**
     * Test getAvailableRoles
     */
    public function testGetAvailableRoles(): void
    {
        $roles = $this->service->getAvailableRoles();
        
        $this->assertIsArray($roles);
        $this->assertArrayHasKey(AuthorizationService::ROLE_ADMIN, $roles);
        $this->assertArrayHasKey(AuthorizationService::ROLE_USER, $roles);
        $this->assertEquals('Administrator', $roles[AuthorizationService::ROLE_ADMIN]);
    }
    
    /**
     * Test user permissions without authentication
     */
    public function testGetUserPermissionsWithoutAuth(): void
    {
        $permissions = $this->service->getUserPermissions();
        $this->assertEmpty($permissions);
    }
    
    /**
     * Test getAccessibleProjectIds without auth
     */
    public function testGetAccessibleProjectIdsWithoutAuth(): void
    {
        $ids = $this->service->getAccessibleProjectIds();
        $this->assertEmpty($ids);
    }
    
    /**
     * Test enforce throws exception
     */
    public function testEnforceThrowsException(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(403);
        
        $this->service->enforce(
            AuthorizationService::RESOURCE_PROJECT,
            AuthorizationService::PERMISSION_DELETE
        );
    }
    
    /**
     * Test enforceProjectAccess throws exception
     */
    public function testEnforceProjectAccessThrowsException(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(403);
        
        $this->service->enforceProjectAccess(1, AuthorizationService::PERMISSION_EDIT);
    }
    
    /**
     * Test enforceTaskAccess throws exception
     */
    public function testEnforceTaskAccessThrowsException(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(403);
        
        $this->service->enforceTaskAccess(1, AuthorizationService::PERMISSION_DELETE);
    }
    
    /**
     * Test can returns false without user
     */
    public function testCanReturnsFalseWithoutUser(): void
    {
        $result = $this->service->can(
            AuthorizationService::RESOURCE_PROJECT,
            AuthorizationService::PERMISSION_VIEW
        );
        
        $this->assertFalse($result);
    }
    
    /**
     * Test canView helper method
     */
    public function testCanView(): void
    {
        // Without auth should return false
        $this->assertFalse($this->service->canView(AuthorizationService::RESOURCE_PROJECT));
    }
    
    /**
     * Test canCreate helper method
     */
    public function testCanCreate(): void
    {
        $this->assertFalse($this->service->canCreate(AuthorizationService::RESOURCE_PROJECT));
    }
    
    /**
     * Test canEdit helper method
     */
    public function testCanEdit(): void
    {
        $this->assertFalse($this->service->canEdit(AuthorizationService::RESOURCE_PROJECT));
    }
    
    /**
     * Test canDelete helper method
     */
    public function testCanDelete(): void
    {
        $this->assertFalse($this->service->canDelete(AuthorizationService::RESOURCE_PROJECT));
    }
    
    /**
     * Test permission matrix structure
     */
    public function testPermissionMatrixStructure(): void
    {
        // Get all available roles
        $roles = $this->service->getAvailableRoles();
        $resources = [
            AuthorizationService::RESOURCE_PROJECT,
            AuthorizationService::RESOURCE_TASK,
            AuthorizationService::RESOURCE_USER,
            AuthorizationService::RESOURCE_COMPANY,
            AuthorizationService::RESOURCE_FILE,
            AuthorizationService::RESOURCE_CALENDAR,
            AuthorizationService::RESOURCE_REPORT,
        ];
        $permissions = [
            AuthorizationService::PERMISSION_VIEW,
            AuthorizationService::PERMISSION_CREATE,
            AuthorizationService::PERMISSION_EDIT,
            AuthorizationService::PERMISSION_DELETE,
            AuthorizationService::PERMISSION_ADMIN,
        ];
        
        foreach ($roles as $roleId => $roleName) {
            $userPerms = $this->service->getUserPermissions(999); // Unknown role returns guest permissions
            $this->assertIsArray($userPerms);
        }
    }
    
    /**
     * Test clearUserCache
     */
    public function testClearUserCache(): void
    {
        // Should not throw exception
        $this->service->clearUserCache(1);
        $this->assertTrue(true); // If we get here, no exception was thrown
    }
    
    /**
     * Test canAccessProject without auth
     */
    public function testCanAccessProjectWithoutAuth(): void
    {
        $result = $this->service->canAccessProject(1);
        $this->assertFalse($result);
    }
    
    /**
     * Test canAccessTask without auth
     */
    public function testCanAccessTaskWithoutAuth(): void
    {
        $result = $this->service->canAccessTask(1);
        $this->assertFalse($result);
    }
}
