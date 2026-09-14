<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

try {
    $db = getDB();
    $db->exec("
        CREATE TABLE IF NOT EXISTS `promotional_templates` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `admin_id` INT(11) NOT NULL,
            `name` VARCHAR(150) NOT NULL,
            `subject` VARCHAR(255) NOT NULL,
            `body_content` MEDIUMTEXT NOT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            CONSTRAINT `fk_promotional_templates_admin` 
                FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) 
                ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "Table 'promotional_templates' created successfully.\n";
} catch (\Throwable $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
