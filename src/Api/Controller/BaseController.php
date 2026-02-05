<?php
/**
 * DotProject Base API Controller
 * 
 * Classe base para todos os controllers da API.
 * 
 * @package DotProject\Api\Controller
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Api\Controller;

use DotProject\Api\Request;
use DotProject\Api\Response;
use DotProject\Core\Cache;
use DotProject\Core\Database;
use DotProject\Service\ValidationService;

/**
 * Controller base com funcionalidades comuns
 */
abstract class BaseController
{
    protected Request $request;
    protected Response $response;
    protected Database $db;
    protected ValidationService $validator;
    protected Cache $cache;

    public function __construct(Request $request, Response $response)
    {
        $this->request = $request;
        $this->response = $response;
        $this->db = Database::getInstance();
        $this->validator = new ValidationService();
        $this->cache = new Cache();
    }

    /**
     * Obtém o ID do usuário autenticado
     */
    protected function getUserId(): ?int
    {
        $userId = $this->request->getParam('_user_id');
        return $userId !== null ? (int) $userId : null;
    }

    /**
     * Obtém dados do usuário autenticado
     * 
     * @return array<string, mixed>
     */
    protected function getUserData(): array
    {
        return $this->request->getParam('_user_data', []);
    }

    /**
     * Valida campos obrigatórios no body da request
     * 
     * @param array<string> $fields
     * @return array<string, string>|null Erros ou null se válido
     */
    protected function validateRequired(array $fields): ?array
    {
        $errors = [];
        $body = $this->request->getBody();

        foreach ($fields as $field) {
            if (!isset($body[$field]) || $body[$field] === '') {
                $errors[$field] = "Field '$field' is required";
            }
        }

        return empty($errors) ? null : $errors;
    }

    /**
     * Obtém parâmetros de paginação
     * 
     * @return array{page: int, per_page: int, offset: int}
     */
    protected function getPagination(): array
    {
        $page = max(1, (int) $this->request->getQueryParam('page', 1));
        $perPage = min(100, max(1, (int) $this->request->getQueryParam('per_page', 20)));
        $offset = ($page - 1) * $perPage;

        return [
            'page' => $page,
            'per_page' => $perPage,
            'offset' => $offset,
        ];
    }

    /**
     * Verifica se o usuário tem permissão para acessar um recurso
     */
    protected function checkPermission(string $module, string $action): bool
    {
        // Usa o sistema de permissões existente do dotProject
        if (function_exists('getPermission')) {
            return getPermission($module, $action);
        }
        return true;
    }

    /**
     * Resposta JSON com dados
     */
    protected function json(mixed $data, int $status = Response::HTTP_OK): Response
    {
        return $this->response->json($data, $status);
    }

    /**
     * Resposta de erro
     */
    protected function error(string $message, int $status = Response::HTTP_BAD_REQUEST): Response
    {
        return $this->response->error($message, $status);
    }

    /**
     * Resposta de recurso criado
     */
    protected function created(mixed $data): Response
    {
        return $this->response->created($data);
    }

    /**
     * Resposta de recurso não encontrado
     */
    protected function notFound(string $message = 'Resource not found'): Response
    {
        return $this->response->notFound($message);
    }

    /**
     * Resposta de erro de validação
     */
    protected function validationError(array $errors): Response
    {
        return $this->response->validationError($errors);
    }

    /**
     * Return the validation service instance.
     */
    protected function validation(): ValidationService
    {
        return $this->validator;
    }

    /**
     * Gera chave de cache para o controller
     * @param mixed ...$parts Partes adicionais para compor a chave
     */
    protected function cacheKey(mixed ...$parts): string
    {
        $key = static::class . ':' . $this->request->getUri();
        foreach ($parts as $part) {
            $key .= ':' . (string) $part;
        }
        return $key;
    }

    /**
     * Limpa cache relacionado ao controller
     */
    protected function clearCache(?string $pattern = null): void
    {
        if ($pattern === null) {
            $pattern = static::class . ':*';
        }
        $this->cache->invalidate($pattern);
    }
}
