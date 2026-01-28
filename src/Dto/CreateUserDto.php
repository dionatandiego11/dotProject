<?php
/**
 * Create User DTO
 * 
 * DTO para criacao de usuarios.
 * 
 * @package DotProject\Dto
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Dto;

/**
 * DTO para criacao de usuario
 */
class CreateUserDto extends BaseDto
{
    public string $username;
    public string $password;
    public ?string $firstName = null;
    public ?string $lastName = null;
    public ?string $email = null;
    public ?string $phone = null;
    public ?int $companyId = null;
    public ?int $departmentId = null;
    public int $status = 0;
    
    /**
     * Valida os dados
     */
    public function validate(): array
    {
        $errors = [];
        
        if (empty($this->username)) {
            $errors['username'] = 'Username is required';
        } elseif (strlen($this->username) < 3) {
            $errors['username'] = 'Username must be at least 3 characters';
        } elseif (strlen($this->username) > 50) {
            $errors['username'] = 'Username must be at most 50 characters';
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $this->username)) {
            $errors['username'] = 'Username can only contain letters, numbers, and underscores';
        }
        
        if (empty($this->password)) {
            $errors['password'] = 'Password is required';
        } elseif (strlen($this->password) < 6) {
            $errors['password'] = 'Password must be at least 6 characters';
        }
        
        if ($this->email !== null && !filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Invalid email format';
        }
        
        return $errors;
    }
}
