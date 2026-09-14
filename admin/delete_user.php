<?php
require_once '../config.php';
require_once __DIR__ . '/../helpers.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', __('invalid_csrf_token') ?: 'Invalid CSRF token');
        redirect('users.php');
    }

    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    if ($id) {
        $db = getDB();
        $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);
        setFlash('success', 'User deleted successfully.');
    }
}

redirect('users.php');

