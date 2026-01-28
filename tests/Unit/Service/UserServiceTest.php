<?php
/**
 * User Service Test
 * 
 * Tests for UserService covering:
 * - Authentication
 * - User CRUD operations
 * - Password management
 * - Profile operations
 */

declare(strict_types=1);

namespace Tests\Unit\Service;

use DotProject\Service\UserService;
use DotProject\Service\ValidationService;
use PHPUnit\Framework\TestCase;

class UserServiceTest extends TestCase
{
    private UserService $service;
    
    protected function setUp(): void
    {
        $this->service = new UserService();
    }
    
    /**
     * Test service can be instantiated
     */
    public function testCanBeInstantiated(): void
    {
        $this->assertInstanceOf(UserService::class, $this->service);
    }
    
    /**
     * Test authenticate with empty credentials
     */
    public function testAuthenticateWithEmptyCredentials(): void
    {
        $result = $this->service->authenticate('', '');
        $this->assertNull($result);
    }
    
    /**
     * Test authenticate with invalid username
     */
    public function testAuthenticateWithInvalidUsername(): void
    {
        $result = $this->service->authenticate('nonexistent_user_xyz', 'password');
        $this->assertNull($result);
    }
    
    /**
     * Test getUser with invalid ID
     */
    public function testGetUserWithInvalidId(): void
    {
        $user = $this->service->getUser(999999);
        $this->assertNull($user);
    }
    
    /**
     * Test getUserByUsername with invalid username
     */
    public function testGetUserByUsernameWithInvalidUsername(): void
    {
        $user = $this->service->getUserByUsername('nonexistent_user_xyz');
        $this->assertNull($user);
    }
    
    /**
     * Test findByEmail with invalid email
     */
    public function testFindByEmailWithInvalidEmail(): void
    {
        $user = $this->service->findByEmail('nonexistent@example.com');
        $this->assertNull($user);
    }
    
    /**
     * Test createUser with empty data throws exception
     */
    public function testCreateUserWithEmptyDataThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->createUser([], 1);
    }
    
    /**
     * Test createUser without username throws exception
     */
    public function testCreateUserWithoutUsernameThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->createUser([
            'user_password' => 'password123'
        ], 1);
    }
    
    /**
     * Test updateUser with invalid ID returns null
     */
    public function testUpdateUserWithInvalidId(): void
    {
        $result = $this->service->updateUser(999999, ['user_username' => 'test']);
        $this->assertNull($result);
    }
    
    /**
     * Test changePassword with invalid user ID
     */
    public function testChangePasswordWithInvalidUserId(): void
    {
        $result = $this->service->changePassword(999999, 'old', 'new');
        $this->assertFalse($result);
    }
    
    /**
     * Test changePassword with wrong current password throws exception
     */
    public function testChangePasswordWithWrongCurrentPassword(): void
    {
        // This test assumes user 1 exists in test database
        // If not, it will fail with "Failed to update project" exception
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Current password is incorrect');
        
        $this->service->changePassword(1, 'wrong_password', 'newpassword');
    }
    
    /**
     * Test deleteUser with same user ID throws exception
     */
    public function testDeleteUserWithSameUserIdThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot delete your own account');
        
        $this->service->deleteUser(1, 1);
    }
    
    /**
     * Test deleteUser with invalid ID
     */
    public function testDeleteUserWithInvalidId(): void
    {
        $result = $this->service->deleteUser(999999, 1);
        $this->assertFalse($result);
    }
    
    /**
     * Test resetPassword with invalid user throws exception
     */
    public function testResetPasswordWithInvalidUser(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('User not found');
        
        $this->service->resetPassword(999999);
    }
    
    /**
     * Test getActiveUsers returns array
     */
    public function testGetActiveUsers(): void
    {
        $users = $this->service->getActiveUsers();
        $this->assertIsArray($users);
    }
    
    /**
     * Test getUsersByCompany with invalid company
     */
    public function testGetUsersByCompany(): void
    {
        $users = $this->service->getUsersByCompany(999999);
        $this->assertIsArray($users);
    }
    
    /**
     * Test searchUsers returns array
     */
    public function testSearchUsers(): void
    {
        $users = $this->service->searchUsers('test');
        $this->assertIsArray($users);
    }
    
    /**
     * Test getUserProfile with invalid ID returns null
     */
    public function testGetUserProfileWithInvalidId(): void
    {
        $profile = $this->service->getUserProfile(999999);
        $this->assertNull($profile);
    }
    
    /**
     * Test getTotalUsers returns int
     */
    public function testGetTotalUsers(): void
    {
        $count = $this->service->getTotalUsers();
        $this->assertIsInt($count);
        $this->assertGreaterThanOrEqual(0, $count);
    }
    
    /**
     * Test getRecentUsersCount returns int
     */
    public function testGetRecentUsersCount(): void
    {
        $count = $this->service->getRecentUsersCount();
        $this->assertIsInt($count);
        $this->assertGreaterThanOrEqual(0, $count);
    }
    
    /**
     * Test getLoginStats returns array
     */
    public function testGetLoginStats(): void
    {
        $stats = $this->service->getLoginStats();
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('active_last_30_days', $stats);
        $this->assertArrayHasKey('today', $stats);
    }
    
    /**
     * Test getUser returns null for invalid ID
     */
    public function testGetUserReturnsNullForInvalidId(): void
    {
        $this->assertNull($this->service->getUser(-1));
        $this->assertNull($this->service->getUser(0));
    }
    
    /**
     * Test getUserByUsername returns null for empty username
     */
    public function testGetUserByUsernameWithEmpty(): void
    {
        $this->assertNull($this->service->getUserByUsername(''));
    }
    
    /**
     * Test searchUsers with empty query
     */
    public function testSearchUsersWithEmptyQuery(): void
    {
        $users = $this->service->searchUsers('');
        $this->assertIsArray($users);
    }
    
    /**
     * Test updateUser with invalid data throws exception
     */
    public function testUpdateUserWithInvalidData(): void
    {
        // Create a mock scenario where user exists but validation fails
        // Since we can't guarantee user 1 exists, we test the exception path
        $this->expectException(\RuntimeException::class);
        
        // Try to update non-existent user
        $this->service->updateUser(999999, ['user_username' => 'a']); // Too short
    }
    
    /**
     * Test user validation integration
     */
    public function testUserValidationIntegration(): void
    {
        $validator = new ValidationService();
        $result = $validator->validateUser([
            'user_username' => 'ab', // Too short
        ]);
        
        $this->assertFalse($result->passes());
        $this->assertTrue($result->fails());
    }
    
    /**
     * Test service dependencies are properly initialized
     */
    public function testServiceDependencies(): void
    {
        // Service should work with default constructor
        $service = new UserService();
        $this->assertInstanceOf(UserService::class, $service);
        
        // Service should work with null dependencies
        $service2 = new UserService(null, null, null, null);
        $this->assertInstanceOf(UserService::class, $service2);
    }
}
