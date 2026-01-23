<?php
/**
 * DotProject API Response
 * 
 * Abstração de respostas HTTP para a API REST.
 * 
 * @package DotProject\Api
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Api;

/**
 * Representa uma resposta HTTP da API
 */
class Response
{
    private int $statusCode = 200;
    private array $headers = [];
    private mixed $body = null;

    /**
     * Códigos de status HTTP comuns
     */
    public const HTTP_OK = 200;
    public const HTTP_CREATED = 201;
    public const HTTP_NO_CONTENT = 204;
    public const HTTP_BAD_REQUEST = 400;
    public const HTTP_UNAUTHORIZED = 401;
    public const HTTP_FORBIDDEN = 403;
    public const HTTP_NOT_FOUND = 404;
    public const HTTP_METHOD_NOT_ALLOWED = 405;
    public const HTTP_UNPROCESSABLE_ENTITY = 422;
    public const HTTP_INTERNAL_ERROR = 500;

    public function __construct()
    {
        $this->headers['Content-Type'] = 'application/json; charset=utf-8';
    }

    /**
     * Define o status code da resposta
     */
    public function setStatus(int $code): self
    {
        $this->statusCode = $code;
        return $this;
    }

    /**
     * Adiciona um header à resposta
     */
    public function setHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /**
     * Define o corpo da resposta
     */
    public function setBody(mixed $body): self
    {
        $this->body = $body;
        return $this;
    }

    /**
     * Resposta de sucesso com dados
     */
    public function json(mixed $data, int $status = self::HTTP_OK): self
    {
        $this->statusCode = $status;
        $this->body = $data;
        return $this;
    }

    /**
     * Resposta de sucesso para criação
     */
    public function created(mixed $data): self
    {
        return $this->json($data, self::HTTP_CREATED);
    }

    /**
     * Resposta sem conteúdo (delete bem-sucedido)
     */
    public function noContent(): self
    {
        $this->statusCode = self::HTTP_NO_CONTENT;
        $this->body = null;
        return $this;
    }

    /**
     * Resposta de erro
     */
    public function error(string $message, int $status = self::HTTP_BAD_REQUEST, ?array $errors = null): self
    {
        $this->statusCode = $status;
        $this->body = [
            'error' => true,
            'message' => $message,
        ];

        if ($errors !== null) {
            $this->body['errors'] = $errors;
        }

        return $this;
    }

    /**
     * Erro 401 - Não autenticado
     */
    public function unauthorized(string $message = 'Unauthorized'): self
    {
        return $this->error($message, self::HTTP_UNAUTHORIZED);
    }

    /**
     * Erro 403 - Sem permissão
     */
    public function forbidden(string $message = 'Forbidden'): self
    {
        return $this->error($message, self::HTTP_FORBIDDEN);
    }

    /**
     * Erro 404 - Não encontrado
     */
    public function notFound(string $message = 'Resource not found'): self
    {
        return $this->error($message, self::HTTP_NOT_FOUND);
    }

    /**
     * Erro de validação
     */
    public function validationError(array $errors): self
    {
        return $this->error('Validation failed', self::HTTP_UNPROCESSABLE_ENTITY, $errors);
    }

    /**
     * Erro interno
     */
    public function serverError(string $message = 'Internal server error'): self
    {
        return $this->error($message, self::HTTP_INTERNAL_ERROR);
    }

    /**
     * Envia a resposta ao cliente
     */
    public function send(): void
    {
        // Set status code
        http_response_code($this->statusCode);

        // Set headers
        foreach ($this->headers as $name => $value) {
            header("$name: $value");
        }

        // CORS headers
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');

        // Send body
        if ($this->body !== null) {
            echo json_encode($this->body, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }

        exit;
    }

    /**
     * Resposta paginada
     */
    public function paginated(array $items, int $total, int $page, int $perPage): self
    {
        $this->body = [
            'data' => $items,
            'meta' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => (int) ceil($total / $perPage),
            ],
        ];
        return $this;
    }
}
