<?php
/**
 * Authorization Middleware Test
 * 
 * Tests for AuthorizationMiddleware covering:
 * - Route permission checking
 * - Resource access verification
 * - HTTP method inference
 */

declare(strict_types=1);

namespace Tests\Unit\Api\Middleware;

use DotProject\Api\Middleware\AuthorizationMiddleware;
use PHPUnit\Framework\TestCase;

class AuthorizationMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        AuthorizationMiddleware::clearPermissions();
    }
    
    /**
     * Test permission registration
     */
    public function testRegisterPermission(): void
    {
        AuthorizationMiddleware::requirePermission(
            '/v1/admin/*',
            'admin',
            'admin'
        );
        
        $permissions = AuthorizationMiddleware::getRegisteredPermissions();
        
        $this->assertArrayHasKey('/v1/admin/*', $permissions);
        $this->assertEquals('admin', $permissions['/v1/admin/*']['resource']);
        $this->assertEquals('admin', $permissions['/v1/admin/*']['permission']);
    }
    
    /**
     * Test multiple permission registration
     */
    public function testRegisterMultiplePermissions(): void
    {
        AuthorizationMiddleware::registerPermissions([
            '/v1/projects' => ['resource' => 'project', 'permission' => 'view'],
            '/v1/tasks' => ['resource' => 'task', 'permission' => 'view'],
        ]);
        
        $permissions = AuthorizationMiddleware::getRegisteredPermissions();
        
        $this->assertCount(2, $permissions);
        $this->assertArrayHasKey('/v1/projects', $permissions);
    }
    
    /**
     * Test clear permissions
     */
    public function testClearPermissions(): void
    {
        AuthorizationMiddleware::requirePermission('/v1/test', 'test', 'view');
        $this->assertNotEmpty(AuthorizationMiddleware::getRegisteredPermissions());
        
        AuthorizationMiddleware::clearPermissions();
        $this->assertEmpty(AuthorizationMiddleware::getRegisteredPermissions());
    }
    
    /**
     * Test handle without user ID returns true
     */
    public function testHandleWithoutUserIdReturnsTrue(): void
    {
        $request = $this->createMock(\DotProject\Api\Request::class);
        $request->method('getParam')->with('_user_id')->willReturn(null);
        
        $response = $this->createMock(\DotProject\Api\Response::class);
        
        $result = AuthorizationMiddleware::handle($request, $response);
        $this->assertTrue($result);
    }
    
    /**
     * Test extract resource ID from URL
     */
    public function testExtractResourceId(): void
    {
        $id = $this->invokeExtractResourceId('/v1/projects/123');
        $this->assertEquals(123, $id);
        
        $id = $this->invokeExtractResourceId('/v1/tasks/456');
        $this->assertEquals(456, $id);
    }
    
    /**
     * Test extract resource ID returns null for invalid URL
     */
    public function testExtractResourceIdReturnsNull(): void
    {
        $id = $this->invokeExtractResourceId('/v1/projects');
        $this->assertNull($id);
    }
    
    /**
     * Helper to invoke private method
     */
    private function invokeExtractResourceId(string $uri): ?int
    {
        $reflection = new \ReflectionClass(AuthorizationMiddleware::class);
        $method = $reflection->getMethod('extractResourceId');
        $method->setAccessible(true);
        return $method->invoke(null, $uri);
    }
}
