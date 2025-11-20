<?php
require_once '../config.php';
requireAdmin();

$db = getDB();
$id = $_GET['id'] ?? null;

if ($id) {
    // Delete user orders first (to avoid foreign key constraint issues)
    $stmt = $db->prepare("DELETE FROM orders WHERE user_id = ?");
    $stmt->execute([$id]);

    // Delete the user
    $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$id]);
}

header("Location: users.php");
exit;
