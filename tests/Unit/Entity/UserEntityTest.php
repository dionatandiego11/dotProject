<?php

declare(strict_types=1);

namespace DotProject\Tests\Unit\Entity;

use DateTime;
use DotProject\Entity\UserEntity;
use PHPUnit\Framework\TestCase;

/**
 * @covers \DotProject\Entity\UserEntity
 */
class UserEntityTest extends TestCase
{
    public function testCanSetAndReadContactFields(): void
    {
        $user = (new UserEntity())
            ->setUsername('john.doe')
            ->setPassword(md5('secret'))
            ->setContactId(12)
            ->setFirstName('John')
            ->setLastName('Doe')
            ->setEmail('john@example.com')
            ->setPhone('(31) 99999-9999');

        $this->assertSame(12, $user->getContactId());
        $this->assertSame('John', $user->getFirstName());
        $this->assertSame('Doe', $user->getLastName());
        $this->assertSame('john@example.com', $user->getEmail());
        $this->assertSame('(31) 99999-9999', $user->getPhone());
    }

    public function testGetFullNameFallsBackToUsername(): void
    {
        $user = (new UserEntity())
            ->setUsername('fallback.user')
            ->setPassword(md5('secret'));

        $this->assertSame('fallback.user', $user->getFullName());
    }

    public function testGetFullNameUsesFirstAndLastNameWhenAvailable(): void
    {
        $user = (new UserEntity())
            ->setUsername('ignored.user')
            ->setPassword(md5('secret'))
            ->setFirstName('Maria')
            ->setLastName('Silva');

        $this->assertSame('Maria Silva', $user->getFullName());
    }

    public function testVerifyPasswordSupportsMd5AndPasswordHash(): void
    {
        $legacyUser = (new UserEntity())
            ->setUsername('legacy.user')
            ->setPassword(md5('legacy-secret'));

        $modernUser = (new UserEntity())
            ->setUsername('modern.user')
            ->setPassword(password_hash('modern-secret', PASSWORD_DEFAULT));

        $this->assertTrue($legacyUser->verifyPassword('legacy-secret'));
        $this->assertFalse($legacyUser->verifyPassword('wrong-password'));
        $this->assertTrue($modernUser->verifyPassword('modern-secret'));
        $this->assertFalse($modernUser->verifyPassword('wrong-password'));
    }

    public function testToArrayIncludesComputedFields(): void
    {
        $lastLogin = new DateTime('2026-02-10 09:30:00');
        $createdAt = new DateTime('2026-02-01 08:00:00');

        $user = (new UserEntity())
            ->setId(7)
            ->setUsername('maria.silva')
            ->setPassword(md5('secret'))
            ->setStatus(0)
            ->setFirstName('Maria')
            ->setLastName('Silva')
            ->setEmail('maria@prefeitura.gov.br')
            ->setLastLogin($lastLogin)
            ->setCreatedAt($createdAt);

        $array = $user->toArray();

        $this->assertSame(7, $array['id']);
        $this->assertSame('maria.silva', $array['username']);
        $this->assertSame('Maria Silva', $array['full_name']);
        $this->assertSame('maria@prefeitura.gov.br', $array['email']);
        $this->assertTrue($array['is_active']);
        $this->assertSame('2026-02-10 09:30:00', $array['last_login']);
        $this->assertSame('2026-02-01 08:00:00', $array['created_at']);
    }
}
