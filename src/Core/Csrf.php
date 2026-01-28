<?php
namespace DotProject\Core;

class Csrf
{
    private const SESSION_KEY = 'csrf_token';

    /**
     * Generate or retrieve CSRF token
     */
    public static function getToken(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return '';
        }

        if (empty($_SESSION[self::SESSION_KEY])) {
            try {
                $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
            } catch (\Exception $e) {
                $_SESSION[self::SESSION_KEY] = bin2hex(openssl_random_pseudo_bytes(32));
            }
        }

        return $_SESSION[self::SESSION_KEY];
    }

    /**
     * Verify token from request
     */
    public static function verify(?string $token): bool
    {
        if (empty($token) || empty($_SESSION[self::SESSION_KEY])) {
            return false;
        }

        return hash_equals($_SESSION[self::SESSION_KEY], $token);
    }

    /**
     * Get HTML input field for forms
     */
    public static function getFormField(): string
    {
        $token = self::getToken();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '" />';
    }
}
