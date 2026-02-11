<?php
/**
 * DotProject API Request
 * 
 * Abstração de requisições HTTP para a API REST.
 * 
 * @package DotProject\Api
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Api;

/**
 * Representa uma requisição HTTP para a API
 */
class Request
{
    private string $method;
    private string $uri;
    private array $params;
    private array $queryParams;
    private array $body;
    private array $headers;

    public function __construct()
    {
        $this->method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $this->uri = $this->parseUri();
        $this->queryParams = $_GET;
        $this->body = $this->parseBody();
        $this->headers = $this->parseHeaders();
        $this->params = [];
    }

    /**
     * Parse the request URI
     */
    private function parseUri(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';

        // Remove query string
        if (($pos = strpos($uri, '?')) !== false) {
            $uri = substr($uri, 0, $pos);
        }

        // Remove base path if present
        $scriptName = dirname($_SERVER['SCRIPT_NAME'] ?? '');
        if ($scriptName !== '/' && strpos($uri, $scriptName) === 0) {
            $uri = substr($uri, strlen($scriptName));
        }

        // Remove api.php if present
        $uri = preg_replace('#^/api\.php#', '', $uri);
        
        // Remove /api prefix (when using nginx rewrite)
        $uri = preg_replace('#^/api#', '', $uri);

        return $uri ?: '/';
    }

    /**
     * Parse request body (JSON)
     */
    private function parseBody(): array
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        $input = file_get_contents('php://input');
        $input = is_string($input) ? trim($input) : '';

        if (strpos($contentType, 'application/json') !== false) {
            $data = json_decode($input, true);
            return is_array($data) ? $data : [];
        }

        // Fallback: try parse JSON even without content-type (common in some proxies)
        if ($input !== '' && (str_starts_with($input, '{') || str_starts_with($input, '['))) {
            $data = json_decode($input, true);
            if (is_array($data)) {
                return $data;
            }
        }

        return $_POST;
    }

    /**
     * Parse HTTP headers
     */
    private function parseHeaders(): array
    {
        $headers = [];

        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $header = str_replace('_', '-', substr($key, 5));
                $headers[$header] = $value;
            }
        }

        // Authorization header special case
        if (isset($_SERVER['Authorization'])) {
            $headers['AUTHORIZATION'] = $_SERVER['Authorization'];
        } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $headers['AUTHORIZATION'] = $_SERVER['HTTP_AUTHORIZATION'];
        } elseif (function_exists('apache_request_headers')) {
            $apacheHeaders = apache_request_headers();
            if (isset($apacheHeaders['Authorization'])) {
                $headers['AUTHORIZATION'] = $apacheHeaders['Authorization'];
            }
        }

        return $headers;
    }

    public function getMethod(): string
    {
        return strtoupper($this->method);
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    public function getQueryParam(string $key, mixed $default = null): mixed
    {
        return $this->queryParams[$key] ?? $default;
    }

    /**
     * Alias for getQueryParam to preserve controller expectations.
     */
    public function getQuery(string $key, mixed $default = null): mixed
    {
        return $this->getQueryParam($key, $default);
    }

    public function getQueryParams(): array
    {
        return $this->queryParams;
    }

    public function getBody(): array
    {
        return $this->body;
    }

    /**
     * Get JSON request body (alias for getBody)
     */
    public function getJsonBody(): array
    {
        return $this->body;
    }

    public function getBodyParam(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $default;
    }

    public function getHeader(string $name): ?string
    {
        $normalized = strtoupper(str_replace('-', '_', $name));
        $dashVariant = strtoupper(str_replace('_', '-', $normalized));

        return $this->headers[$normalized]
            ?? $this->headers[$dashVariant]
            ?? null;
    }

    /**
     * Returns request host without port.
     */
    public function getHost(): string
    {
        $host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? '';
        if (!is_string($host) || $host === '') {
            return '';
        }

        $host = trim(explode(',', $host)[0]);
        $host = preg_replace('/:\d+$/', '', $host);

        return is_string($host) ? strtolower($host) : '';
    }

    public function getBearerToken(): ?string
    {
        $auth = $this->getHeader('Authorization');
        if ($auth && preg_match('/Bearer\s+(.+)/i', $auth, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Set route parameters (from Router)
     */
    public function setParams(array $params): void
    {
        $this->params = $params;
    }

    public function getParam(string $key, mixed $default = null): mixed
    {
        return $this->params[$key] ?? $default;
    }

    public function getParams(): array
    {
        return $this->params;
    }

    public function isMethod(string $method): bool
    {
        return $this->getMethod() === strtoupper($method);
    }
    
    /**
     * Get uploaded file
     */
    public function getUploadedFile(string $name): ?array
    {
        if (!isset($_FILES[$name]) || $_FILES[$name]['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        
        if ($_FILES[$name]['error'] !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Upload error: ' . $this->getUploadErrorMessage($_FILES[$name]['error']));
        }
        
        return $_FILES[$name];
    }
    
    /**
     * Get upload error message
     */
    private function getUploadErrorMessage(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE => 'File too large (exceeds upload_max_filesize)',
            UPLOAD_ERR_FORM_SIZE => 'File too large (exceeds MAX_FILE_SIZE)',
            UPLOAD_ERR_PARTIAL => 'File partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file',
            UPLOAD_ERR_EXTENSION => 'Upload stopped by extension',
            default => 'Unknown upload error'
        };
    }
}
