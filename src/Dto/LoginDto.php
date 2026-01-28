<?php
/**
 * Login DTO
 * 
 * DTO para autenticacao.
 * 
 * @package DotProject\Dto
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Dto;

/**
 * DTO para login
 */
class LoginDto extends BaseDto
{
    public string $username;
    public string $password;
    
    /**
     * Valida os dados
     */
    public function validate(): array
    {
        $errors = [];
        
        if (empty($this->username)) {
            $errors['username'] = 'Username is required';
        }
        
        if (empty($this->password)) {
            $errors['password'] = 'Password is required';
        }
        
        return $errors;
    }
}
