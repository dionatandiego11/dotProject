<?php
/**
 * DotProject User Service
 * 
 * Modern service for user management using Entity/Repository pattern.
 * Handles authentication, user CRUD operations, and profile management.
 * 
 * @package DotProject\Service
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Service;

use DotProject\Core\Cache;
use DotProject\Core\Database;
use DotProject\Entity\UserEntity;
use DotProject\Repository\UserRepository;

/**
 * User Service
 * 
 * Modern user management with:
 * - Entity/Repository pattern
 * - Password security (bcrypt with MD5 fallback)
 * - JWT token management
 * - Profile validation
 * - Multi-tenant awareness (company-based)
 */
class UserService
{
    private UserRepository $repository;
    private Database $db;
    private Cache $cache;
    private ValidationService $validator;
    
    public function __construct(
        ?UserRepository $repository = null,
        ?Database $db = null,
        ?Cache $cache = null,
        ?ValidationService $validator = null
    ) {
        $this->repository = $repository ?? new UserRepository();
        $this->db = $db ?? Database::getInstance();
        $this->cache = $cache ?? new Cache(prefix: 'user:');
        $this->validator = $validator ?? new ValidationService();
    }
    
    /**
     * Authenticate user by username and password
     * 
     * @param string $username Username
     * @param string $password Plain text password
     * @return array{user: UserEntity, token: string}|null Authentication result or null on failure
     */
    public function authenticate(string $username, string $password): ?array
    {
        // Find user by username
        $user = $this->repository->findByUsername($username);
        
        if ($user === null) {
            return null;
        }
        
        // Check if user is active
        if (!$user->isActive()) {
            return null;
        }
        
        // Verify password
        if (!$user->verifyPassword($password)) {
            return null;
        }
        
        // Update password hash if still using MD5 (upgrade to bcrypt)
        $storedPassword = $user->getPassword();
        if (strlen($storedPassword) === 32) { // MD5 hash length
            $this->upgradePasswordHash($user->getId() ?? 0, $password);
        }
        
        // Update last login
        $user->touchLastLogin();
        $this->repository->updateLastLogin($user->getId() ?? 0);
        
        // Generate JWT token
        $token = $this->generateToken($user);
        
        return [
            'user' => $user,
            'token' => $token,
        ];
    }
    
    /**
     * Create new user
     * 
     * @param array<string, mixed> $data User data
     * @param int $createdBy User ID who is creating
     * @return UserEntity|null Created user or null on failure
     * @throws \InvalidArgumentException If validation fails
     */
    public function createUser(array $data, int $createdBy): ?UserEntity
    {
        // Validate input
        $validation = $this->validator->validateUser($data);
        
        if ($validation->fails()) {
            throw new \InvalidArgumentException($validation->firstError() ?? 'Validation failed');
        }
        
        // Check if username already exists
        if ($this->repository->findByUsername($data['user_username']) !== null) {
            throw new \InvalidArgumentException('Username already exists');
        }
        
        // Check if email already exists (if provided)
        if (!empty($data['contact_email'])) {
            $existing = $this->findByEmail($data['contact_email']);
            if ($existing !== null) {
                throw new \InvalidArgumentException('Email already exists');
            }
        }
        
        // Create contact first (if contact data provided)
        $contactId = null;
        if (!empty($data['contact_first_name']) || !empty($data['contact_email'])) {
            $contactId = $this->createContact([
                'contact_first_name' => $data['contact_first_name'] ?? '',
                'contact_last_name' => $data['contact_last_name'] ?? '',
                'contact_email' => $data['contact_email'] ?? '',
                'contact_phone' => $data['contact_phone'] ?? '',
                'contact_company' => $data['user_company'] ?? null,
            ]);
        }
        
        // Create user entity
        $user = new UserEntity();
        $user->setUsername($data['user_username']);
        $user->setPassword($this->hashPassword($data['user_password'] ?? $this->generateRandomPassword()));
        $user->setContactId($contactId);
        $user->setCompanyId($data['user_company'] ?? null);
        $user->setDepartmentId($data['user_department'] ?? null);
        $user->setStatus($data['user_status'] ?? 0);
        
        if ($contactId) {
            $user->setFirstName($data['contact_first_name'] ?? null);
            $user->setLastName($data['contact_last_name'] ?? null);
            $user->setEmail($data['contact_email'] ?? null);
            $user->setPhone($data['contact_phone'] ?? null);
        }
        
        // Save user
        if (!$this->repository->save($user)) {
            return null;
        }
        
        // Clear cache
        $this->cache->invalidate('users:*');
        
        return $user;
    }
    
    /**
     * Update user
     * 
     * @param int $userId User ID
     * @param array<string, mixed> $data Update data
     * @return UserEntity|null Updated user or null on failure
     * @throws \InvalidArgumentException If validation fails
     */
    public function updateUser(int $userId, array $data): ?UserEntity
    {
        $user = $this->repository->find($userId);
        
        if ($user === null) {
            return null;
        }
        
        // Update username if provided
        if (!empty($data['user_username']) && $data['user_username'] !== $user->getUsername()) {
            $existing = $this->repository->findByUsername($data['user_username']);
            if ($existing !== null && $existing->getId() !== $userId) {
                throw new \InvalidArgumentException('Username already exists');
            }
            $user->setUsername($data['user_username']);
        }
        
        // Update password if provided
        if (!empty($data['user_password'])) {
            $user->setPassword($this->hashPassword($data['user_password']));
        }
        
        // Update company/department
        if (isset($data['user_company'])) {
            $user->setCompanyId($data['user_company'] ?: null);
        }
        if (isset($data['user_department'])) {
            $user->setDepartmentId($data['user_department'] ?: null);
        }
        if (isset($data['user_status'])) {
            $user->setStatus((int) $data['user_status']);
        }
        
        // Update contact info
        if (!empty($data['contact_first_name'])) {
            $user->setFirstName($data['contact_first_name']);
        }
        if (!empty($data['contact_last_name'])) {
            $user->setLastName($data['contact_last_name']);
        }
        if (!empty($data['contact_email'])) {
            $user->setEmail($data['contact_email']);
        }
        if (!empty($data['contact_phone'])) {
            $user->setPhone($data['contact_phone']);
        }
        
        // Save user
        if (!$this->repository->save($user)) {
            return null;
        }
        
        // Update contact in database if exists
        if ($user->getContactId()) {
            $this->updateContact($user->getContactId(), [
                'contact_first_name' => $user->getFirstName(),
                'contact_last_name' => $user->getLastName(),
                'contact_email' => $user->getEmail(),
                'contact_phone' => $user->getPhone(),
            ]);
        }
        
        // Clear cache
        $this->cache->delete("user:{$userId}");
        $this->cache->invalidate('users:*');
        
        return $user;
    }
    
    /**
     * Change user password
     * 
     * @param int $userId User ID
     * @param string $currentPassword Current password (for verification)
     * @param string $newPassword New password
     * @return bool Success
     * @throws \InvalidArgumentException If current password is wrong
     */
    public function changePassword(int $userId, string $currentPassword, string $newPassword): bool
    {
        $user = $this->repository->find($userId);
        
        if ($user === null) {
            return false;
        }
        
        // Verify current password
        if (!$user->verifyPassword($currentPassword)) {
            throw new \InvalidArgumentException('Current password is incorrect');
        }
        
        // Validate new password
        if (strlen($newPassword) < 6) {
            throw new \InvalidArgumentException('Password must be at least 6 characters');
        }
        
        // Update password
        $user->setPassword($this->hashPassword($newPassword));
        
        return $this->repository->save($user);
    }
    
    /**
     * Reset user password (admin only)
     * 
     * @param int $userId User ID
     * @return string New random password
     */
    public function resetPassword(int $userId): string
    {
        $user = $this->repository->find($userId);
        
        if ($user === null) {
            throw new \InvalidArgumentException('User not found');
        }
        
        $newPassword = $this->generateRandomPassword();
        $user->setPassword($this->hashPassword($newPassword));
        
        if (!$this->repository->save($user)) {
            throw new \RuntimeException('Failed to reset password');
        }
        
        return $newPassword;
    }
    
    /**
     * Delete user
     * 
     * @param int $userId User ID to delete
     * @param int $deletedBy User ID who is deleting (cannot delete self)
     * @return bool Success
     */
    public function deleteUser(int $userId, int $deletedBy): bool
    {
        if ($userId === $deletedBy) {
            throw new \InvalidArgumentException('Cannot delete your own account');
        }
        
        $user = $this->repository->find($userId);
        
        if ($user === null) {
            return false;
        }
        
        // Delete user
        $result = $this->repository->delete($userId);
        
        if ($result) {
            // Delete contact if exists
            if ($user->getContactId()) {
                $this->db->delete('contacts', "contact_id = {$user->getContactId()}");
            }
            
            // Clear cache
            $this->cache->delete("user:{$userId}");
            $this->cache->invalidate('users:*');
        }
        
        return $result;
    }
    
    /**
     * Get user by ID
     */
    public function getUser(int $userId): ?UserEntity
    {
        return $this->repository->find($userId);
    }
    
    /**
     * Get user by username
     */
    public function getUserByUsername(string $username): ?UserEntity
    {
        return $this->repository->findByUsername($username);
    }
    
    /**
     * Find user by email
     */
    public function findByEmail(string $email): ?UserEntity
    {
        $sql = sprintf(
            "SELECT u.* FROM `%s` u 
             JOIN `%s` c ON c.contact_id = u.user_contact 
             WHERE c.contact_email = ? AND u.user_status = 0",
            $this->db->table('users'),
            $this->db->table('contacts')
        );
        
        $data = $this->db->fetchOne($sql, [$email]);
        
        if ($data === null) {
            return null;
        }
        
        return $this->repository->hydrate($data);
    }
    
    /**
     * Get all active users
     * 
     * @return array<UserEntity>
     */
    public function getActiveUsers(): array
    {
        return $this->repository->findActive();
    }
    
    /**
     * Get users by company
     * 
     * @param int $companyId Company ID
     * @return array<UserEntity>
     */
    public function getUsersByCompany(int $companyId): array
    {
        return $this->repository->findByCompany($companyId);
    }
    
    /**
     * Search users by name or email
     * 
     * @param string $query Search query
     * @return array<UserEntity>
     */
    public function searchUsers(string $query): array
    {
        $sql = sprintf(
            "SELECT u.*, c.contact_first_name, c.contact_last_name, c.contact_email 
             FROM `%s` u 
             LEFT JOIN `%s` c ON c.contact_id = u.user_contact 
             WHERE (u.user_username LIKE ? 
                OR c.contact_first_name LIKE ? 
                OR c.contact_last_name LIKE ? 
                OR c.contact_email LIKE ?)
             AND u.user_status = 0
             ORDER BY u.user_username
             LIMIT 20",
            $this->db->table('users'),
            $this->db->table('contacts')
        );
        
        $pattern = '%' . $query . '%';
        $results = $this->db->fetchAll($sql, [$pattern, $pattern, $pattern, $pattern]);
        
        return array_map(fn($row) => $this->repository->hydrate($row), $results);
    }
    
    /**
     * Get user profile with full details
     * 
     * @return array<string, mixed>|null
     */
    public function getUserProfile(int $userId): ?array
    {
        $user = $this->repository->find($userId);
        
        if ($user === null) {
            return null;
        }
        
        $profile = $user->toArray();
        
        // Add statistics
        $profile['stats'] = [
            'total_tasks' => $this->getUserTaskCount($userId),
            'completed_tasks' => $this->getUserCompletedTaskCount($userId),
            'active_projects' => $this->getUserActiveProjectCount($userId),
        ];
        
        // Add role info
        $authService = AuthorizationService::getInstance();
        $profile['role'] = [
            'id' => $authService->getUserRole($userId),
            'name' => $authService->getRoleName($authService->getUserRole($userId)),
        ];
        
        // Add permissions
        $profile['permissions'] = $authService->getUserPermissions($userId);
        
        return $profile;
    }
    
    /**
     * Generate JWT token for user
     */
    private function generateToken(UserEntity $user): string
    {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $time = time();
        $payload = json_encode([
            'iss' => 'dotproject',
            'iat' => $time,
            'exp' => $time + (24 * 60 * 60), // 24 hours
            'sub' => $user->getId(),
            'username' => $user->getUsername(),
            'company' => $user->getCompanyId(),
        ]);
        
        $base64Header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64Payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
        
        $secret = getenv('JWT_SECRET') ?: 'default-secret-change-in-production';
        $signature = hash_hmac('sha256', $base64Header . "." . $base64Payload, $secret, true);
        $base64Signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        
        return $base64Header . "." . $base64Payload . "." . $base64Signature;
    }
    
    /**
     * Hash password using bcrypt
     */
    private function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }
    
    /**
     * Upgrade password hash from MD5 to bcrypt
     */
    private function upgradePasswordHash(int $userId, string $plainPassword): void
    {
        $newHash = $this->hashPassword($plainPassword);
        
        $this->db->update(
            'users',
            ['user_password' => $newHash],
            "user_id = {$userId}"
        );
    }
    
    /**
     * Generate random password
     */
    private function generateRandomPassword(int $length = 12): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
        $password = '';
        $max = strlen($chars) - 1;
        
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, $max)];
        }
        
        return $password;
    }
    
    /**
     * Create contact record
     */
    private function createContact(array $data): ?int
    {
        if (empty($data['contact_first_name']) && empty($data['contact_email'])) {
            return null;
        }
        
        $result = $this->db->insert('contacts', [
            'contact_first_name' => $data['contact_first_name'] ?? '',
            'contact_last_name' => $data['contact_last_name'] ?? '',
            'contact_email' => $data['contact_email'] ?? '',
            'contact_phone' => $data['contact_phone'] ?? '',
            'contact_company' => $data['contact_company'] ?? null,
            'contact_type' => 1, // Internal user
        ]);
        
        if (!$result) {
            return null;
        }
        
        return (int) $this->db->lastInsertId();
    }
    
    /**
     * Update contact record
     */
    private function updateContact(int $contactId, array $data): bool
    {
        $updateData = [];
        
        if (isset($data['contact_first_name'])) {
            $updateData['contact_first_name'] = $data['contact_first_name'];
        }
        if (isset($data['contact_last_name'])) {
            $updateData['contact_last_name'] = $data['contact_last_name'];
        }
        if (isset($data['contact_email'])) {
            $updateData['contact_email'] = $data['contact_email'];
        }
        if (isset($data['contact_phone'])) {
            $updateData['contact_phone'] = $data['contact_phone'];
        }
        
        if (empty($updateData)) {
            return true;
        }
        
        return $this->db->update('contacts', $updateData, "contact_id = {$contactId}");
    }
    
    /**
     * Get user's task count
     */
    private function getUserTaskCount(int $userId): int
    {
        $sql = sprintf(
            "SELECT COUNT(*) FROM `%s` WHERE task_owner = %d",
            $this->db->table('tasks'),
            $userId
        );
        
        return (int) $this->db->fetchValue($sql);
    }
    
    /**
     * Get user's completed task count
     */
    private function getUserCompletedTaskCount(int $userId): int
    {
        $sql = sprintf(
            "SELECT COUNT(*) FROM `%s` WHERE task_owner = %d AND task_percent_complete = 100",
            $this->db->table('tasks'),
            $userId
        );
        
        return (int) $this->db->fetchValue($sql);
    }
    
    /**
     * Get user's active project count
     */
    private function getUserActiveProjectCount(int $userId): int
    {
        $sql = sprintf(
            "SELECT COUNT(DISTINCT p.project_id) FROM `%s` p 
             LEFT JOIN `%s` t ON t.task_project = p.project_id 
             WHERE p.project_status = 0 AND (p.project_owner = %d OR t.task_owner = %d)",
            $this->db->table('projects'),
            $this->db->table('tasks'),
            $userId,
            $userId
        );
        
        return (int) $this->db->fetchValue($sql);
    }
    
    /**
     * Get total user count
     */
    public function getTotalUsers(): int
    {
        return $this->repository->count();
    }
    
    /**
     * Get users created in last N days
     * 
     * @param int $days Number of days
     * @return int Count
     */
    public function getRecentUsersCount(int $days = 30): int
    {
        $sql = sprintf(
            "SELECT COUNT(*) FROM `%s` WHERE user_regdate >= DATE_SUB(CURDATE(), INTERVAL %d DAY)",
            $this->db->table('users'),
            $days
        );
        
        return (int) $this->db->fetchValue($sql);
    }
    
    /**
     * Get user login statistics
     * 
     * @return array<string, mixed>
     */
    public function getLoginStats(): array
    {
        // Active users (logged in last 30 days)
        $activeSql = sprintf(
            "SELECT COUNT(DISTINCT user_id) FROM `%s` 
             WHERE user_last_login >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
            $this->db->table('user_access_log')
        );
        
        // Today's logins
        $todaySql = sprintf(
            "SELECT COUNT(DISTINCT user_id) FROM `%s` 
             WHERE DATE(access_date) = CURDATE()",
            $this->db->table('user_access_log')
        );
        
        return [
            'active_last_30_days' => (int) $this->db->fetchValue($activeSql),
            'today' => (int) $this->db->fetchValue($todaySql),
        ];
    }
}
