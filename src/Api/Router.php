<?php
/**
 * DotProject API Router
 * 
 * Roteador simples para a API REST.
 * 
 * @package DotProject\Api
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Api;

use DotProject\Core\Logger;

/**
 * Roteador para requisições da API
 */
class Router
{
    /** @var array<string, array<string, callable>> */
    private array $routes = [];

    /** @var array<callable> */
    private array $middleware = [];

    private Request $request;
    private Response $response;

    public function __construct(?Request $request = null, ?Response $response = null)
    {
        $this->request = $request ?? new Request();
        $this->response = $response ?? new Response();
    }

    /**
     * Adiciona middleware global
     */
    public function use(callable $middleware): self
    {
        $this->middleware[] = $middleware;
        return $this;
    }

    /**
     * Registra uma rota GET
     */
    public function get(string $path, callable $handler): self
    {
        return $this->addRoute('GET', $path, $handler);
    }

    /**
     * Registra uma rota POST
     */
    public function post(string $path, callable $handler): self
    {
        return $this->addRoute('POST', $path, $handler);
    }

    /**
     * Registra uma rota PUT
     */
    public function put(string $path, callable $handler): self
    {
        return $this->addRoute('PUT', $path, $handler);
    }

    /**
     * Registra uma rota DELETE
     */
    public function delete(string $path, callable $handler): self
    {
        return $this->addRoute('DELETE', $path, $handler);
    }

    /**
     * Adiciona uma rota ao registro
     */
    private function addRoute(string $method, string $path, callable $handler): self
    {
        // Normaliza o path
        $path = '/' . trim($path, '/');

        if (!isset($this->routes[$method])) {
            $this->routes[$method] = [];
        }

        $this->routes[$method][$path] = $handler;
        return $this;
    }

    /**
     * Executa o roteador
     */
    public function run(): void
    {
        $method = $this->request->getMethod();
        $uri = $this->request->getUri();
        $requestId = $_SERVER['HTTP_X_REQUEST_ID'] ?? bin2hex(random_bytes(8));
        $this->response->setHeader('X-Request-Id', $requestId);
        $start = microtime(true);
        $hasLoggedRequest = false;
        $logRequest = function (Response $response) use (&$hasLoggedRequest, $requestId, $method, $uri, $start): void {
            if ($hasLoggedRequest) {
                return;
            }

            $hasLoggedRequest = true;
            Logger::log('info', 'API Request', [
                'request_id' => $requestId,
                'method' => $method,
                'uri' => $uri,
                'status_code' => $response->getStatusCode(),
                'duration_ms' => (int) round((microtime(true) - $start) * 1000),
            ]);
        };
        $this->response->onSend($logRequest);

        // Handle OPTIONS for CORS
        if ($method === 'OPTIONS') {
            $this->response->setStatus(Response::HTTP_NO_CONTENT)->send();
            return;
        }

        // Executa middlewares globais
        foreach ($this->middleware as $middleware) {
            $result = $middleware($this->request, $this->response);
            if ($result === false) {
                $logRequest($this->response);
                return; // Middleware interrompeu a execução
            }
        }

        // Encontra a rota
        $handler = $this->matchRoute($method, $uri);

        if ($handler === null) {
            // Tenta verificar se existe a rota em outro método
            $methodAllowed = false;
            foreach ($this->routes as $routeMethod => $routes) {
                if ($this->matchRoute($routeMethod, $uri) !== null) {
                    $methodAllowed = true;
                    break;
                }
            }

            if ($methodAllowed) {
                $this->response->error('Method not allowed', Response::HTTP_METHOD_NOT_ALLOWED)->send();
            } else {
                $this->response->notFound('Endpoint not found')->send();
            }
            return;
        }

        try {
            $result = $handler($this->request, $this->response);

            // Se o handler retornou um Response, envia
            if ($result instanceof Response) {
                if ($result !== $this->response) {
                    $result->onSend($logRequest);
                }
                $result->send();
            } elseif ($result !== null) {
                // Se retornou dados, assume JSON
                $this->response->json($result)->send();
            }
        } catch (\Throwable $e) {
            // Log do erro
            Logger::error('API Error', [
                'message' => $e->getMessage(),
                'type' => $e::class,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'request_id' => $requestId,
                'method' => $method,
                'uri' => $uri,
            ]);

            // Em desenvolvimento, mostra detalhes
            if (defined('DP_DEBUG') && DP_DEBUG) {
                $this->response->error($e->getMessage(), Response::HTTP_INTERNAL_ERROR, [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ])->send();
            } else {
                $this->response->serverError()->send();
            }
        }
        $logRequest($this->response);
    }

    /**
     * Encontra um handler para a rota
     */
    private function matchRoute(string $method, string $uri): ?callable
    {
        $routes = $this->routes[$method] ?? [];

        // Normaliza URI
        $uri = '/' . trim($uri, '/');

        foreach ($routes as $pattern => $handler) {
            $params = $this->matchPattern($pattern, $uri);
            if ($params !== null) {
                // Merge com params existentes (preserva _user_id setado pelo middleware)
                $existingParams = $this->request->getParams();
                $this->request->setParams(array_merge($existingParams, $params));
                return $handler;
            }
        }

        return null;
    }

    /**
     * Faz match de um padrão com a URI, extraindo parâmetros
     */
    private function matchPattern(string $pattern, string $uri): ?array
    {
        // Converte {param} para regex
        $regex = preg_replace('#\{([a-zA-Z_]+)\}#', '(?P<$1>[^/]+)', $pattern);
        $regex = '#^' . $regex . '$#';

        if (preg_match($regex, $uri, $matches)) {
            // Filtra apenas parâmetros nomeados
            return array_filter($matches, fn($key) => !is_numeric($key), ARRAY_FILTER_USE_KEY);
        }

        return null;
    }

    /**
     * Retorna o Request atual
     */
    public function getRequest(): Request
    {
        return $this->request;
    }

    /**
     * Retorna o Response atual
     */
    public function getResponse(): Response
    {
        return $this->response;
    }
}
