<?php
declare(strict_types=1);

// helpers.php - Core helper functions for security, flash messages, and status translation.
// Tracked in Git and loaded across all environments to ensure reliability.

use FidelsPizza\Domain\Service\TextFormatter;
use FidelsPizza\Infrastructure\Http\FlashMessenger;
use FidelsPizza\Infrastructure\Security\CsrfManager;

if (!function_exists('getCsrfToken')) {
    function getCsrfToken(): string {
        return CsrfManager::getToken();
    }
}

if (!function_exists('verifyCsrfToken')) {
    function verifyCsrfToken(?string $token): bool {
        return CsrfManager::verify($token);
    }
}

if (!function_exists('setFlash')) {
    function setFlash(string $type, string $message): void {
        FlashMessenger::set($type, $message);
    }
}

if (!function_exists('getFlash')) {
    /**
     * @return array{type: string, message: string}|null
     */
    function getFlash(): ?array {
        return FlashMessenger::get();
    }
}

if (!function_exists('translateStatus')) {
    function translateStatus(?string $status): string {
        return TextFormatter::translateStatus($status);
    }
}

if (!function_exists('formatPrice')) {
    function formatPrice(float|int|string $price): string {
        return TextFormatter::formatPrice($price);
    }
}

if (!function_exists('sanitize')) {
    function sanitize(string $text): string {
        return TextFormatter::sanitize($text);
    }
}

if (!function_exists('redirect')) {
    function redirect(string $url): void {
        header('Location: ' . $url);
        exit;
    }
}
