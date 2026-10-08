<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

if (php_sapi_name() !== 'cli') {
    requireAdmin();
    header('Content-Type: text/plain; charset=utf-8');
}

try {
    $db = getDB();

    // Check existing enum definition for status column on orders table
    $stmt = $db->query("SHOW COLUMNS FROM `orders` LIKE 'status'");
    $column = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($column && isset($column['Type'])) {
        $type = (string)$column['Type'];
        if (!str_contains($type, "'comped'")) {
            // Update enum to include 'comped'
            $db->exec("
                ALTER TABLE `orders` 
                MODIFY COLUMN `status` ENUM('pending','confirmed','preparing','ready','completed','cancelled','archived','comped') NOT NULL DEFAULT 'pending'
            ");
            echo "Successfully updated `orders.status` ENUM to include 'comped'.\n";
        } else {
            echo "`orders.status` already contains 'comped'. No change needed.\n";
        }
    } else {
        echo "Could not find `status` column on `orders` table.\n";
    }

    // Report existing comped orders count
    $countStmt = $db->query("
        SELECT 
            COUNT(DISTINCT o.id) AS comped_orders,
            COALESCE(SUM(oi.quantity), 0) AS comped_pizzas
        FROM orders o
        LEFT JOIN order_items oi ON o.id = oi.order_id
        WHERE o.status = 'comped'
    ");
    $stats = $countStmt->fetch(PDO::FETCH_ASSOC);
    echo "Current comp'd orders in DB: " . (int)($stats['comped_orders'] ?? 0) . "\n";
    echo "Current comp'd pizzas in DB: " . (int)($stats['comped_pizzas'] ?? 0) . "\n";

} catch (\Throwable $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
