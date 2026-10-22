<?php
declare(strict_types=1);

// helpers.php - Core helper functions for security, flash messages, and status translation.
// Tracked in Git and loaded across all environments to ensure reliability
// even when config.php is preserved on remote servers.

if (file_exists(__DIR__ . '/src/autoload.php')) {
    require_once __DIR__ . '/src/autoload.php';
}

if (!function_exists('getCsrfToken')) {
    function getCsrfToken(): string {
        if (class_exists(\FidelsPizza\Infrastructure\Security\CsrfManager::class)) {
            return \FidelsPizza\Infrastructure\Security\CsrfManager::getToken();
        }
        if (session_status() === PHP_SESSION_NONE) {
            session_start([
                'cookie_httponly' => true,
                'cookie_samesite' => 'Lax',
                'use_strict_mode' => true,
            ]);
        }
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return (string)$_SESSION['csrf_token'];
    }
}

if (!function_exists('verifyCsrfToken')) {
    function verifyCsrfToken(?string $token): bool {
        if (class_exists(\FidelsPizza\Infrastructure\Security\CsrfManager::class)) {
            return \FidelsPizza\Infrastructure\Security\CsrfManager::verify($token);
        }
        if (session_status() === PHP_SESSION_NONE) {
            session_start([
                'cookie_httponly' => true,
                'cookie_samesite' => 'Lax',
                'use_strict_mode' => true,
            ]);
        }
        if (empty($token) || empty($_SESSION['csrf_token'])) {
            return false;
        }
        return hash_equals((string)$_SESSION['csrf_token'], (string)$token);
    }
}

if (!function_exists('setFlash')) {
    function setFlash(string $type, string $message): void {
        if (class_exists(\FidelsPizza\Infrastructure\Http\FlashMessenger::class)) {
            \FidelsPizza\Infrastructure\Http\FlashMessenger::set($type, $message);
            return;
        }
        if (session_status() === PHP_SESSION_NONE) {
            session_start([
                'cookie_httponly' => true,
                'cookie_samesite' => 'Lax',
                'use_strict_mode' => true,
            ]);
        }
        $_SESSION['flash_message'] = [
            'type' => $type,
            'message' => $message
        ];
    }
}

if (!function_exists('getFlash')) {
    /**
     * @return array{type: string, message: string}|null
     */
    function getFlash(): ?array {
        if (class_exists(\FidelsPizza\Infrastructure\Http\FlashMessenger::class)) {
            return \FidelsPizza\Infrastructure\Http\FlashMessenger::get();
        }
        if (session_status() === PHP_SESSION_NONE) {
            session_start([
                'cookie_httponly' => true,
                'cookie_samesite' => 'Lax',
                'use_strict_mode' => true,
            ]);
        }
        if (isset($_SESSION['flash_message'])) {
            $flash = $_SESSION['flash_message'];
            unset($_SESSION['flash_message']);
            return (array)$flash;
        }
        return null;
    }
}

if (!function_exists('translateStatus')) {
    function translateStatus(?string $status): string {
        if (class_exists(\FidelsPizza\Domain\Service\TextFormatter::class)) {
            return \FidelsPizza\Domain\Service\TextFormatter::translateStatus($status);
        }
        if ($status === null || $status === '') {
            return '';
        }
        $key = 'status_' . $status;
        if (function_exists('__')) {
            $translated = (string)__($key);
            return $translated !== $key ? $translated : ucfirst($status);
        }
        return ucfirst($status);
    }
}

if (!function_exists('formatPrice')) {
    function formatPrice(float|int|string $price): string {
        if (class_exists(\FidelsPizza\Domain\Service\TextFormatter::class)) {
            return \FidelsPizza\Domain\Service\TextFormatter::formatPrice($price);
        }
        return '¥' . number_format((float)$price, 0);
    }
}

if (!function_exists('sanitize')) {
    function sanitize(string $text): string {
        if (class_exists(\FidelsPizza\Domain\Service\TextFormatter::class)) {
            return \FidelsPizza\Domain\Service\TextFormatter::sanitize($text);
        }
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('redirect')) {
    function redirect(string $url): void {
        header('Location: ' . $url);
        exit;
    }
}
