<?php
/**
 * DotProject JWT Manager
 * 
 * Gerenciador de tokens JWT para autenticação na API.
 * 
 * @package DotProject\Auth
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Auth;

/**
 * Gerenciador de tokens JWT
 * 
 * Implementação simples de JWT sem dependências externas.
 * Para produção, considere usar firebase/php-jwt.
 */
class JwtManager
{
    private string $secret;
    private int $ttl; // Time to live in seconds

    private static ?JwtManager $instance = null;

    private function __construct()
    {
        // Usa a senha do banco como base para o secret (ou configure um específico)
        $envSecret = getenv('JWT_SECRET') ?: null;
        $configSecret = dPgetConfig('jwt_secret', null);
        $baseSecret = $configSecret ?? $envSecret ?? dPgetConfig('dbpass', 'dotproject_secret_key');
        $this->secret = $baseSecret . '_jwt_secret';
        $this->ttl = 3600 * 24; // 24 horas
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Gera um token JWT
     * 
     * @param array<string, mixed> $payload Dados do usuário
     */
    public function generate(array $payload): string
    {
        $header = [
            'typ' => 'JWT',
            'alg' => 'HS256'
        ];

        $now = time();
        $payload['iat'] = $now; // Issued at
        $payload['exp'] = $now + $this->ttl; // Expiration

        $base64Header = $this->base64UrlEncode(json_encode($header));
        $base64Payload = $this->base64UrlEncode(json_encode($payload));

        $signature = $this->sign("$base64Header.$base64Payload");

        return "$base64Header.$base64Payload.$signature";
    }

    /**
     * Valida e decodifica um token JWT
     * 
     * @return array<string, mixed>|null Payload ou null se inválido
     */
    public function verify(string $token): ?array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            return null;
        }

        [$base64Header, $base64Payload, $signature] = $parts;

        // Verifica assinatura
        $expectedSignature = $this->sign("$base64Header.$base64Payload");
        if (!hash_equals($expectedSignature, $signature)) {
            return null;
        }

        // Decodifica payload
        $payload = json_decode($this->base64UrlDecode($base64Payload), true);

        if (!is_array($payload)) {
            return null;
        }

        // Verifica expiração
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return null;
        }

        return $payload;
    }

    /**
     * Gera um token de refresh (mais longo)
     */
    public function generateRefreshToken(int $userId): string
    {
        return $this->generate([
            'user_id' => $userId,
            'type' => 'refresh',
            'jti' => bin2hex(random_bytes(16)),
            'exp' => time() + (3600 * 24 * 30), // 30 dias
        ]);
    }

    /**
     * Cria uma assinatura HMAC
     */
    private function sign(string $input): string
    {
        return $this->base64UrlEncode(
            hash_hmac('sha256', $input, $this->secret, true)
        );
    }

    /**
     * Base64 URL-safe encode
     */
    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Base64 URL-safe decode
     */
    private function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }

    /**
     * Define o TTL do token
     */
    public function setTtl(int $seconds): self
    {
        $this->ttl = $seconds;
        return $this;
    }
}
