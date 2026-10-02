<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

echo "Starting Order Deadline & Status Override database migration...\n";

try {
    $db = getDB();

    // 1. Check if order_deadline column exists in site_config
    $stmt = $db->query("SHOW COLUMNS FROM `site_config` LIKE 'order_deadline'");
    if (!$stmt->fetch()) {
        echo "Adding 'order_deadline' column to 'site_config' table...\n";
        $db->exec("
            ALTER TABLE `site_config` 
            ADD COLUMN `order_deadline` DATETIME NULL DEFAULT NULL AFTER `event_date`
        ");
        echo "Column 'order_deadline' added.\n";
    } else {
        echo "'order_deadline' column already exists in 'site_config'.\n";
    }

    // 2. Check if order_status_override column exists in site_config
    $stmt2 = $db->query("SHOW COLUMNS FROM `site_config` LIKE 'order_status_override'");
    if (!$stmt2->fetch()) {
        echo "Adding 'order_status_override' column to 'site_config' table...\n";
        $db->exec("
            ALTER TABLE `site_config` 
            ADD COLUMN `order_status_override` ENUM('auto', 'force_open', 'force_closed') NOT NULL DEFAULT 'auto' AFTER `order_deadline`
        ");
        echo "Column 'order_status_override' added.\n";
    } else {
        echo "'order_status_override' column already exists in 'site_config'.\n";
    }

    echo "Migration completed successfully!\n";
} catch (Throwable $e) {
    echo "Migration error: " . $e->getMessage() . "\n";
    exit(1);
}
