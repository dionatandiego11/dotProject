<?php
/**
 * DotProject JWT Manager
 *
 * Gerenciador de tokens JWT para autenticacao na API.
 *
 * @package DotProject\Auth
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Auth;

/**
 * Gerenciador de tokens JWT
 *
 * Implementacao simples de JWT sem dependencias externas.
 * Para producao, considere usar firebase/php-jwt.
 */
class JwtManager
{
    private string $secret;
    private int $ttl; // Time to live in seconds

    private static ?JwtManager $instance = null;

    private function __construct()
    {
        $envSecret = trim((string) (getenv('JWT_SECRET') ?: ''));
        $configSecret = function_exists('dPgetConfig')
            ? trim((string) dPgetConfig('jwt_secret', ''))
            : '';

        $baseSecret = $envSecret !== '' ? $envSecret : $configSecret;
        if ($baseSecret === '') {
            $appEnv = strtolower((string) (getenv('APP_ENV') ?: 'development'));
            if ($appEnv === 'production') {
                throw new \RuntimeException('JWT_SECRET must be configured in production.');
            }

            $baseSecret = 'dotproject_dev_only_change_me';
            error_log('WARNING: JWT_SECRET is not configured. Using development fallback secret.');
        }

        $this->secret = $baseSecret;
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
     * @param array<string, mixed> $payload Dados do usuario
     */
    public function generate(array $payload): string
    {
        $header = [
            'typ' => 'JWT',
            'alg' => 'HS256',
        ];

        $now = time();

        if (!isset($payload['iat']) || !is_numeric($payload['iat'])) {
            $payload['iat'] = $now;
        } else {
            $payload['iat'] = (int) $payload['iat'];
        }

        if (!isset($payload['exp']) || !is_numeric($payload['exp'])) {
            $payload['exp'] = $payload['iat'] + $this->ttl;
        } else {
            $payload['exp'] = (int) $payload['exp'];
        }

        if ($payload['exp'] <= $payload['iat']) {
            $payload['exp'] = $payload['iat'] + $this->ttl;
        }

        $base64Header = $this->base64UrlEncode((string) json_encode($header));
        $base64Payload = $this->base64UrlEncode((string) json_encode($payload));

        $signature = $this->sign($base64Header . '.' . $base64Payload);

        return $base64Header . '.' . $base64Payload . '.' . $signature;
    }

    /**
     * Valida e decodifica um token JWT
     *
     * @return array<string, mixed>|null Payload ou null se invalido
     */
    public function verify(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$base64Header, $base64Payload, $signature] = $parts;

        // Verifica assinatura
        $expectedSignature = $this->sign($base64Header . '.' . $base64Payload);
        if (!hash_equals($expectedSignature, $signature)) {
            return null;
        }

        // Decodifica payload
        $payload = json_decode($this->base64UrlDecode($base64Payload), true);
        if (!is_array($payload)) {
            return null;
        }

        // Verifica expiracao
        if (!isset($payload['exp']) || !is_numeric($payload['exp'])) {
            return null;
        }

        if ((int) $payload['exp'] < time()) {
            return null;
        }

        return $payload;
    }

    /**
     * Gera um token de refresh (mais longo)
     */
    public function generateRefreshToken(int $userId): string
    {
        $now = time();

        return $this->generate([
            'user_id' => $userId,
            'type' => 'refresh',
            'jti' => bin2hex(random_bytes(16)),
            'iat' => $now,
            'exp' => $now + (3600 * 24 * 30), // 30 dias
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
        return (string) base64_decode(strtr($data, '-_', '+/'));
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
