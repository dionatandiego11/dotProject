<?php
/**
 * Validation Service Test
 * 
 * Unit tests for the ValidationService class.
 * 
 * @package DotProject\Tests\Unit\Service
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use DotProject\Service\ValidationService;

/**
 * @covers \DotProject\Service\ValidationService
 */
class ValidationServiceTest extends TestCase
{
    private ValidationService $validator;

    protected function setUp(): void
    {
        $this->validator = new ValidationService();
    }

    /**
     * Test required validation passes
     */
    public function testRequiredPasses(): void
    {
        $result = $this->validator
            ->validate(['name' => 'Test'])
            ->required('name');

        $this->assertTrue($result->passes());
        $this->assertEmpty($result->errors());
    }

    /**
     * Test required validation fails for empty string
     */
    public function testRequiredFailsForEmptyString(): void
    {
        $result = $this->validator
            ->validate(['name' => ''])
            ->required('name');

        $this->assertTrue($result->fails());
        $this->assertNotEmpty($result->errors());
    }

    /**
     * Test required validation fails for null
     */
    public function testRequiredFailsForNull(): void
    {
        $result = $this->validator
            ->validate(['name' => null])
            ->required('name');

        $this->assertTrue($result->fails());
    }

    /**
     * Test required validation fails for missing field
     */
    public function testRequiredFailsForMissingField(): void
    {
        $result = $this->validator
            ->validate([])
            ->required('name');

        $this->assertTrue($result->fails());
    }

    /**
     * Test minLength validation passes
     */
    public function testMinLengthPasses(): void
    {
        $result = $this->validator
            ->validate(['name' => 'Test'])
            ->minLength('name', 3);

        $this->assertTrue($result->passes());
    }

    /**
     * Test minLength validation fails
     */
    public function testMinLengthFails(): void
    {
        $result = $this->validator
            ->validate(['name' => 'Te'])
            ->minLength('name', 3);

        $this->assertTrue($result->fails());
    }

    /**
     * Test maxLength validation passes
     */
    public function testMaxLengthPasses(): void
    {
        $result = $this->validator
            ->validate(['name' => 'Test'])
            ->maxLength('name', 10);

        $this->assertTrue($result->passes());
    }

    /**
     * Test maxLength validation fails
     */
    public function testMaxLengthFails(): void
    {
        $result = $this->validator
            ->validate(['name' => 'This is too long'])
            ->maxLength('name', 10);

        $this->assertTrue($result->fails());
    }

    /**
     * Test email validation passes
     */
    public function testEmailPasses(): void
    {
        $result = $this->validator
            ->validate(['email' => 'test@example.com'])
            ->email('email');

        $this->assertTrue($result->passes());
    }

    /**
     * Test email validation fails
     */
    public function testEmailFails(): void
    {
        $result = $this->validator
            ->validate(['email' => 'invalid-email'])
            ->email('email');

        $this->assertTrue($result->fails());
    }

    /**
     * Test numeric validation passes
     */
    public function testNumericPasses(): void
    {
        $result = $this->validator
            ->validate(['age' => '25'])
            ->numeric('age');

        $this->assertTrue($result->passes());
    }

    /**
     * Test numeric validation fails
     */
    public function testNumericFails(): void
    {
        $result = $this->validator
            ->validate(['age' => 'not a number'])
            ->numeric('age');

        $this->assertTrue($result->fails());
    }

    /**
     * Test integer validation passes
     */
    public function testIntegerPasses(): void
    {
        $result = $this->validator
            ->validate(['count' => '42'])
            ->integer('count');

        $this->assertTrue($result->passes());
    }

    /**
     * Test integer validation fails
     */
    public function testIntegerFails(): void
    {
        $result = $this->validator
            ->validate(['count' => '3.14'])
            ->integer('count');

        $this->assertTrue($result->fails());
    }

    /**
     * Test between validation passes
     */
    public function testBetweenPasses(): void
    {
        $result = $this->validator
            ->validate(['percent' => 50])
            ->between('percent', 0, 100);

        $this->assertTrue($result->passes());
    }

    /**
     * Test between validation fails for value too low
     */
    public function testBetweenFailsForTooLow(): void
    {
        $result = $this->validator
            ->validate(['percent' => -1])
            ->between('percent', 0, 100);

        $this->assertTrue($result->fails());
    }

    /**
     * Test between validation fails for value too high
     */
    public function testBetweenFailsForTooHigh(): void
    {
        $result = $this->validator
            ->validate(['percent' => 101])
            ->between('percent', 0, 100);

        $this->assertTrue($result->fails());
    }

    /**
     * Test in validation passes
     */
    public function testInPasses(): void
    {
        $result = $this->validator
            ->validate(['status' => 'active'])
            ->in('status', ['active', 'inactive', 'pending']);

        $this->assertTrue($result->passes());
    }

    /**
     * Test in validation fails
     */
    public function testInFails(): void
    {
        $result = $this->validator
            ->validate(['status' => 'unknown'])
            ->in('status', ['active', 'inactive', 'pending']);

        $this->assertTrue($result->fails());
    }

    /**
     * Test regex validation passes
     */
    public function testRegexPasses(): void
    {
        $result = $this->validator
            ->validate(['username' => 'user_123'])
            ->regex('username', '/^[a-zA-Z0-9_]+$/');

        $this->assertTrue($result->passes());
    }

    /**
     * Test regex validation fails
     */
    public function testRegexFails(): void
    {
        $result = $this->validator
            ->validate(['username' => 'user@123'])
            ->regex('username', '/^[a-zA-Z0-9_]+$/');

        $this->assertTrue($result->fails());
    }

    /**
     * Test custom validation
     */
    public function testCustomValidation(): void
    {
        $result = $this->validator
            ->validate(['password' => 'secret123'])
            ->custom('password', fn($v) => strlen($v) >= 8, 'Password too short');

        $this->assertTrue($result->passes());
    }

    /**
     * Test chained validations
     */
    public function testChainedValidations(): void
    {
        $result = $this->validator
            ->validate(['name' => 'Test Project', 'status' => 3])
            ->required('name')
            ->minLength('name', 3)
            ->maxLength('name', 100)
            ->between('status', 0, 10);

        $this->assertTrue($result->passes());
    }

    /**
     * Test multiple errors
     */
    public function testMultipleErrors(): void
    {
        $result = $this->validator
            ->validate(['name' => '', 'email' => 'invalid'])
            ->required('name')
            ->email('email');

        $this->assertTrue($result->fails());
        $this->assertCount(2, $result->errors());
    }

    /**
     * Test firstError returns first error
     */
    public function testFirstError(): void
    {
        $result = $this->validator
            ->validate(['name' => ''])
            ->required('name', 'Name is required');

        $this->assertSame('Name is required', $result->firstError());
    }

    /**
     * Test error returns specific field error
     */
    public function testErrorForField(): void
    {
        $result = $this->validator
            ->validate(['name' => '', 'email' => 'invalid'])
            ->required('name', 'Name error')
            ->email('email', 'Email error');

        $this->assertSame('Name error', $result->error('name'));
        $this->assertSame('Email error', $result->error('email'));
    }

    /**
     * Test validateProject method
     */
    public function testValidateProjectPasses(): void
    {
        $result = $this->validator->validateProject([
            'project_name' => 'Test Project',
            'project_short_name' => 'TST',
            'project_company' => 1,
            'project_status' => 3,
            'project_priority' => 1,
        ]);

        $this->assertTrue($result->passes());
    }

    /**
     * Test validateProject fails for missing name
     */
    public function testValidateProjectFailsForMissingName(): void
    {
        $result = $this->validator->validateProject([
            'project_short_name' => 'TST',
        ]);

        $this->assertTrue($result->fails());
        $this->assertNotNull($result->error('project_name'));
    }

    /**
     * Test validateTask method
     */
    public function testValidateTaskPasses(): void
    {
        $result = $this->validator->validateTask([
            'task_name' => 'Test Task',
            'task_project' => 1,
            'task_percent_complete' => 50,
            'task_duration' => 8,
        ]);

        $this->assertTrue($result->passes());
    }

    /**
     * Test validateTask fails for invalid percentage
     */
    public function testValidateTaskFailsForInvalidPercent(): void
    {
        $result = $this->validator->validateTask([
            'task_name' => 'Test Task',
            'task_project' => 1,
            'task_percent_complete' => 150,
        ]);

        $this->assertTrue($result->fails());
        $this->assertNotNull($result->error('task_percent_complete'));
    }
}
