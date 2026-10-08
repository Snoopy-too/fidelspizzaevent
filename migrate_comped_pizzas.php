<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

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
} catch (\Throwable $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
