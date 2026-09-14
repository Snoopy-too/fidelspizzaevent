<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helpers.php';

header('Content-Type: application/json; charset=utf-8');

// Ensure admin authentication
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$userId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$userId || $userId <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid user ID provided.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $db = getDB();

    // Fetch user details
    $stmt = $db->prepare("
        SELECT id, first_name, last_name, email, phone, is_confirmed, accepts_marketing, created_at
        FROM users
        WHERE id = ?
    ");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => __('user_not_found') !== 'user_not_found' ? __('user_not_found') : 'User not found.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Fetch recent order history for this user
    $orderStmt = $db->prepare("
        SELECT id, order_number, total_amount, status, created_at
        FROM orders
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT 50
    ");
    $orderStmt->execute([$userId]);
    $ordersRaw = $orderStmt->fetchAll(PDO::FETCH_ASSOC);

    $orders = [];
    foreach ($ordersRaw as $ord) {
        $orders[] = [
            'id'           => (int)$ord['id'],
            'order_number' => (string)$ord['order_number'],
            'total_amount' => (float)$ord['total_amount'],
            'total_formatted' => formatPrice((float)$ord['total_amount']),
            'status'       => (string)$ord['status'],
            'status_label' => translateStatus((string)$ord['status']),
            'created_at'   => !empty($ord['created_at']) ? date('Y/m/d H:i', strtotime((string)$ord['created_at'])) : '-'
        ];
    }

    echo json_encode([
        'success' => true,
        'user' => [
            'id'                => (int)$user['id'],
            'first_name'        => (string)$user['first_name'],
            'last_name'         => (string)$user['last_name'],
            'full_name'         => trim((string)$user['first_name'] . ' ' . (string)$user['last_name']),
            'email'             => (string)$user['email'],
            'phone'             => (string)($user['phone'] ?? ''),
            'is_confirmed'      => (int)$user['is_confirmed'] === 1,
            'accepts_marketing' => (int)($user['accepts_marketing'] ?? 1) === 1,
            'created_at'        => !empty($user['created_at']) ? date('Y/m/d H:i', strtotime((string)$user['created_at'])) : '-'
        ],
        'orders' => $orders
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    error_log('ajax_user_details error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An internal database error occurred.'
    ], JSON_UNESCAPED_UNICODE);
}
