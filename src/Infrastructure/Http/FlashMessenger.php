<?php
declare(strict_types=1);

namespace FidelsPizza\Infrastructure\Http;

use FidelsPizza\Infrastructure\Security\CsrfManager;

final class FlashMessenger
{
    private const SESSION_KEY = 'flash_message';

    /**
     * Store a flash notification message in the session.
     */
    public static function set(string $type, string $message): void
    {
        CsrfManager::ensureSession();
        $_SESSION[self::SESSION_KEY] = [
            'type' => $type,
            'message' => $message,
        ];
    }

    /**
     * Retrieve and clear the flash notification message if present.
     *
     * @return array{type: string, message: string}|null
     */
    public static function get(): ?array
    {
        CsrfManager::ensureSession();
        if (isset($_SESSION[self::SESSION_KEY]) && is_array($_SESSION[self::SESSION_KEY])) {
            /** @var array{type: string, message: string} $flash */
            $flash = $_SESSION[self::SESSION_KEY];
            unset($_SESSION[self::SESSION_KEY]);
            return $flash;
        }
        return null;
    }
}
