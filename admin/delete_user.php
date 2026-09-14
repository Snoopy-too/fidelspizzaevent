<?php
declare(strict_types=1);

require_once '../config.php';
require_once __DIR__ . '/../helpers.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', __('invalid_csrf_token') ?: 'Invalid CSRF token.');
        redirect('users.php');
    }

    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    if ($id) {
        try {
            $db = getDB();
            $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$id]);
            setFlash('success', __('user_deleted_success') !== 'user_deleted_success' ? __('user_deleted_success') : 'User deleted successfully.');
        } catch (Throwable $e) {
            error_log("Failed to delete user ID {$id}: " . $e->getMessage());
            setFlash('error', 'Failed to delete user: ' . $e->getMessage());
        }
    }
}

redirect('users.php');


