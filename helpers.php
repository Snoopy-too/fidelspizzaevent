<?php
// helpers.php - Core helper functions for security, flash messages, and status translation.
// This file is tracked in Git and loaded across all environments to ensure reliability
// even when config.php is preserved on remote servers.

if (!function_exists('getCsrfToken')) {
    function getCsrfToken(): string {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return (string)$_SESSION['csrf_token'];
    }
}

if (!function_exists('verifyCsrfToken')) {
    function verifyCsrfToken(?string $token): bool {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($token) || empty($_SESSION['csrf_token'])) {
            return false;
        }
        return hash_equals((string)$_SESSION['csrf_token'], (string)$token);
    }
}

if (!function_exists('setFlash')) {
    function setFlash(string $type, string $message): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['flash_message'] = [
            'type' => $type,
            'message' => $message
        ];
    }
}

if (!function_exists('getFlash')) {
    function getFlash(): ?array {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
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
        if ($status === null || $status === '') {
            return '';
        }
        $key = 'status_' . $status;
        if (function_exists('__')) {
            $translated = __($key);
            return $translated !== $key ? $translated : ucfirst($status);
        }
        return ucfirst($status);
    }
}
