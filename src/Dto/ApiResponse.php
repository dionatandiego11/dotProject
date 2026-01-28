<?php
/**
 * API Response
 * 
 * Padronizacao de respostas da API.
 * 
 * @package DotProject\Dto
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Dto;

/**
 * Resposta padrao da API
 */
class ApiResponse
{
    public bool $success;
    public mixed $data;
    public ?string $message;
    public ?array $errors;
    public ?array $meta;
    
    public function __construct(
        bool $success = true,
        mixed $data = null,
        ?string $message = null,
        ?array $errors = null,
        ?array $meta = null
    ) {
        $this->success = $success;
        $this->data = $data;
        $this->message = $message;
        $this->errors = $errors;
        $this->meta = $meta;
    }
    
    /**
     * Cria resposta de sucesso
     */
    public static function success(mixed $data = null, ?string $message = null, ?array $meta = null): self
    {
        return new self(true, $data, $message, null, $meta);
    }
    
    /**
     * Cria resposta de erro
     */
    public static function error(string $message, ?array $errors = null, int $code = 400): self
    {
        return new self(false, null, $message, $errors, ['code' => $code]);
    }
    
    /**
     * Cria resposta de validacao
     */
    public static function validationError(array $errors): self
    {
        return new self(
            false, 
            null, 
            'Validation failed', 
            $errors, 
            ['code' => 422]
        );
    }
    
    /**
     * Cria resposta nao encontrado
     */
    public static function notFound(string $resource = 'Resource'): self
    {
        return new self(
            false,
            null,
            "{$resource} not found",
            null,
            ['code' => 404]
        );
    }
    
    /**
     * Cria resposta nao autorizado
     */
    public static function unauthorized(?string $message = null): self
    {
        return new self(
            false,
            null,
            $message ?? 'Unauthorized',
            null,
            ['code' => 401]
        );
    }
    
    /**
     * Cria resposta proibido
     */
    public static function forbidden(?string $message = null): self
    {
        return new self(
            false,
            null,
            $message ?? 'Forbidden',
            null,
            ['code' => 403]
        );
    }
    
    /**
     * Converte para array
     */
    public function toArray(): array
    {
        $response = [
            'success' => $this->success,
        ];
        
        if ($this->data !== null) {
            $response['data'] = $this->data;
        }
        
        if ($this->message !== null) {
            $response['message'] = $this->message;
        }
        
        if ($this->errors !== null) {
            $response['errors'] = $this->errors;
        }
        
        if ($this->meta !== null) {
            $response['meta'] = $this->meta;
        }
        
        return $response;
    }
    
    /**
     * Converte para JSON
     */
    public function toJson(): string
    {
        return json_encode($this->toArray());
    }
}
