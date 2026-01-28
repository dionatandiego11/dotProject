<?php
/**
 * DotProject Logger
 *
 * Minimal structured logger with basic masking.
 *
 * @package DotProject\Core
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace DotProject\Core;

class Logger
{
    /** @var array<string> */
    private const SENSITIVE_KEYS = [
        'password',
        'pass',
        'token',
        'refresh_token',
        'authorization',
        'auth',
    ];

    /**
     * Log a message with context.
     *
     * @param array<string, mixed> $context
     */
    public static function log(string $level, string $message, array $context = []): void
    {
        $payload = [
            'ts' => date('c'),
            'level' => strtolower($level),
            'message' => $message,
            'context' => self::sanitize($context),
        ];

        error_log(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /**
     * Log an error.
     *
     * @param array<string, mixed> $context
     */
    public static function error(string $message, array $context = []): void
    {
        self::log('error', $message, $context);
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    private static function sanitize(mixed $value): mixed
    {
        if (is_array($value)) {
            $clean = [];
            foreach ($value as $key => $val) {
                if (is_string($key) && in_array(strtolower($key), self::SENSITIVE_KEYS, true)) {
                    $clean[$key] = '[redacted]';
                    continue;
                }
                $clean[$key] = self::sanitize($val);
            }
            return $clean;
        }

        return $value;
    }
}
