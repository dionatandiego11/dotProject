<?php
/**
 * Entity User - Modern
 * 
 * @package DotProject\Entity
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Entity;

use DateTime;

class UserEntity
{
    private ?int $id = null;
    private string $username;
    private string $password;
    private ?int $contactId = null;
    private ?int $companyId = null;
    private ?int $departmentId = null;
    private int $status = 0; // 0=ativo, 1=inativo
    private ?string $token = null;
    private ?DateTime $tokenExpiry = null;
    private ?DateTime $lastLogin = null;
    private ?DateTime $createdAt = null;
    private ?DateTime $updatedAt = null;

    // Dados do contato (joined)
    private ?string $firstName = null;
    private ?string $lastName = null;
    private ?string $email = null;
    private ?string $phone = null;

    public function getId(): ?int { return $this->id; }
    public function setId(int $id): self { $this->id = $id; return $this; }

    public function getUsername(): string { return $this->username; }
    public function setUsername(string $username): self { $this->username = $username; return $this; }

    public function getPassword(): string { return $this->password; }
    public function setPassword(string $password): self { $this->password = $password; return $this; }

    public function getContactId(): ?int { return $this->contactId; }
    public function setContactId(?int $contactId): self { $this->contactId = $contactId; return $this; }

    public function getCompanyId(): ?int { return $this->companyId; }
    public function setCompanyId(?int $companyId): self { $this->companyId = $companyId; return $this; }

    public function getDepartmentId(): ?int { return $this->departmentId; }
    public function setDepartmentId(?int $departmentId): self { $this->departmentId = $departmentId; return $this; }

    public function getStatus(): int { return $this->status; }
    public function setStatus(int $status): self { $this->status = $status; return $this; }
    public function isActive(): bool { return $this->status === 0; }

    public function getToken(): ?string { return $this->token; }
    public function setToken(?string $token): self { $this->token = $token; return $this; }

    public function getTokenExpiry(): ?DateTime { return $this->tokenExpiry; }
    public function setTokenExpiry(?DateTime $tokenExpiry): self { $this->tokenExpiry = $tokenExpiry; return $this; }

    public function getLastLogin(): ?DateTime { return $this->lastLogin; }
    public function setLastLogin(?DateTime $lastLogin): self { $this->lastLogin = $lastLogin; return $this; }

    public function getCreatedAt(): ?DateTime { return $this->createdAt; }
    public function setCreatedAt(?DateTime $createdAt): self { $this->createdAt = $createdAt; return $this; }

    public function getUpdatedAt(): ?DateTime { return $this->updatedAt; }
    public function setUpdatedAt(?DateTime $updatedAt): self { $this->updatedAt = $updatedAt; return $this; }

    // Contact data
    public function getFirstName(): ?string { return $this->firstName; }
    public function setFirstName(?string $firstName): self { $this->firstName = $firstName; return $this; }

    public function getLastName(): ?string { return $this->lastName; }
    public function setLastName(?string $lastName): self { $this->lastName = $lastName; return $this; }

    public function getEmail(): ?string { return $this->email; }
    public function setEmail(?string $email): self { $this->email = $email; return $this; }

    public function getPhone(): ?string { return $this->phone; }
    public function setPhone(?string $phone): self { $this->phone = $phone; return $this; }

    /**
     * Retorna nome completo
     */
    public function getFullName(): string
    {
        return trim(($this->firstName ?? '') . ' ' . ($this->lastName ?? '')) ?: $this->username;
    }

    /**
     * Verifica se token está expirado
     */
    public function isTokenExpired(): bool
    {
        if ($this->tokenExpiry === null) {
            return true;
        }
        return new DateTime() > $this->tokenExpiry;
    }

    /**
     * Verifica senha (compatível com MD5 legado e password_hash moderno)
     */
    public function verifyPassword(string $password): bool
    {
        $stored = trim($this->password);
        
        // MD5 legado
        if (strlen($stored) === 32 && md5($password) === $stored) {
            return true;
        }
        
        // Moderno password_hash
        if (password_get_info($stored)['algo'] !== 0) {
            return password_verify($password, $stored);
        }
        
        return false;
    }

    /**
     * Atualiza último login
     */
    public function touchLastLogin(): self
    {
        $this->lastLogin = new DateTime();
        return $this;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'contact_id' => $this->contactId,
            'company_id' => $this->companyId,
            'department_id' => $this->departmentId,
            'status' => $this->status,
            'is_active' => $this->isActive(),
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'full_name' => $this->getFullName(),
            'email' => $this->email,
            'phone' => $this->phone,
            'last_login' => $this->lastLogin?->format('Y-m-d H:i:s'),
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
        ];
    }
}
