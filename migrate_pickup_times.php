<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

try {
    $db = getDB();

    // 1. Create pickup_time_slots table with InnoDB and Foreign Key constraint to site_config(id)
    $db->exec("
        CREATE TABLE IF NOT EXISTS `pickup_time_slots` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `config_id` INT(11) NOT NULL DEFAULT 1,
            `label` VARCHAR(100) NOT NULL DEFAULT '',
            `slot_time` TIME NOT NULL,
            `max_pizzas` INT(11) DEFAULT NULL,
            `max_orders` INT(11) DEFAULT NULL,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `sort_order` INT(11) NOT NULL DEFAULT 0,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_pts_config` (`config_id`),
            KEY `idx_pts_active_sort` (`is_active`, `sort_order`, `slot_time`),
            CONSTRAINT `fk_pickup_time_slots_config`
                FOREIGN KEY (`config_id`) REFERENCES `site_config` (`id`)
                ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // 2. Add pickup_slot_id column and foreign key to orders table if not already present
    $colCheck = $db->query("SHOW COLUMNS FROM `orders` LIKE 'pickup_slot_id'");
    if ($colCheck->rowCount() === 0) {
        $db->exec("
            ALTER TABLE `orders`
                ADD COLUMN `pickup_slot_id` INT(11) DEFAULT NULL AFTER `pickup_time`,
                ADD KEY `idx_orders_pickup_slot` (`pickup_slot_id`),
                ADD CONSTRAINT `fk_orders_pickup_slot`
                    FOREIGN KEY (`pickup_slot_id`) REFERENCES `pickup_time_slots` (`id`)
                    ON DELETE SET NULL ON UPDATE CASCADE;
        ");
    }

    // 3. Seed default Pickup Time A, B, C slots if the table is currently empty
    $count = (int)$db->query("SELECT COUNT(*) FROM `pickup_time_slots`")->fetchColumn();
    if ($count === 0) {
        $stmt = $db->prepare("
            INSERT INTO `pickup_time_slots` (`config_id`, `label`, `slot_time`, `max_pizzas`, `max_orders`, `is_active`, `sort_order`)
            VALUES
                (1, 'Pickup Time A', '11:30:00', NULL, NULL, 1, 0),
                (1, 'Pickup Time B', '12:30:00', NULL, NULL, 1, 1),
                (1, 'Pickup Time C', '13:30:00', NULL, NULL, 1, 2)
        ");
        $stmt->execute();
    }

    echo "Migration for 'pickup_time_slots' and 'orders.pickup_slot_id' completed successfully.\n";
} catch (\Throwable $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
