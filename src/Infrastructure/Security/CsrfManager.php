<?php
declare(strict_types=1);

namespace FidelsPizza\Infrastructure\Security;

final class CsrfManager
{
    private const SESSION_KEY = 'csrf_token';

    /**
     * Ensure the session is initialized with secure cookies.
     */
    public static function ensureSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start([
                'cookie_httponly' => true,
                'cookie_samesite' => 'Lax',
                'use_strict_mode' => true,
            ]);
        }
    }

    /**
     * Retrieve or generate the current session CSRF token.
     */
    public static function getToken(): string
    {
        self::ensureSession();
        if (empty($_SESSION[self::SESSION_KEY]) || !is_string($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }
        return (string)$_SESSION[self::SESSION_KEY];
    }

    /**
     * Verify a submitted CSRF token using constant-time string comparison.
     */
    public static function verify(?string $token): bool
    {
        self::ensureSession();
        if ($token === null || $token === '') {
            return false;
        }
        if (empty($_SESSION[self::SESSION_KEY]) || !is_string($_SESSION[self::SESSION_KEY])) {
            return false;
        }
        return hash_equals((string)$_SESSION[self::SESSION_KEY], $token);
    }
}
