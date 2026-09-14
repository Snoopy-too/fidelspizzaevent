<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

echo "Starting Promotional Emails database migration...\n";

try {
    $db = getDB();

    // 1. Check if accepts_marketing column exists in users
    $stmt = $db->query("SHOW COLUMNS FROM `users` LIKE 'accepts_marketing'");
    if (!$stmt->fetch()) {
        echo "Adding 'accepts_marketing' and 'unsubscribe_token' columns to 'users' table...\n";
        $db->exec("
            ALTER TABLE `users` 
            ADD COLUMN `accepts_marketing` TINYINT(1) NOT NULL DEFAULT 1 AFTER `is_confirmed`,
            ADD COLUMN `unsubscribe_token` VARCHAR(64) NULL AFTER `confirmation_token`,
            ADD INDEX `idx_users_marketing` (`accepts_marketing`, `is_confirmed`)
        ");
        echo "Columns added to 'users'.\n";
    } else {
        echo "'accepts_marketing' column already exists in 'users'.\n";
    }

    // 2. Generate unsubscribe tokens for users missing one
    $usersStmt = $db->query("SELECT id FROM `users` WHERE `unsubscribe_token` IS NULL OR `unsubscribe_token` = ''");
    $usersToUpdate = $usersStmt->fetchAll(PDO::FETCH_COLUMN);
    if (!empty($usersToUpdate)) {
        $updateTokenStmt = $db->prepare("UPDATE `users` SET `unsubscribe_token` = ? WHERE `id` = ?");
        foreach ($usersToUpdate as $userId) {
            $token = bin2hex(random_bytes(32));
            $updateTokenStmt->execute([$token, $userId]);
        }
        echo "Generated unsubscribe tokens for " . count($usersToUpdate) . " users.\n";
    }

    // 3. Create promotional_campaigns table (InnoDB with FK to admins)
    $db->exec("
        CREATE TABLE IF NOT EXISTS `promotional_campaigns` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `admin_id` INT(11) NOT NULL,
            `subject` VARCHAR(255) NOT NULL,
            `body_content` MEDIUMTEXT NOT NULL,
            `total_recipients` INT(11) NOT NULL DEFAULT 0,
            `sent_count` INT(11) NOT NULL DEFAULT 0,
            `failed_count` INT(11) NOT NULL DEFAULT 0,
            `status` ENUM('draft', 'sending', 'completed', 'failed') NOT NULL DEFAULT 'draft',
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `completed_at` TIMESTAMP NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            CONSTRAINT `fk_promotional_campaigns_admin` 
                FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) 
                ON DELETE RESTRICT ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "Table 'promotional_campaigns' verified/created.\n";

    // 4. Create promotional_campaign_recipients table (InnoDB with FK to promotional_campaigns and users)
    $db->exec("
        CREATE TABLE IF NOT EXISTS `promotional_campaign_recipients` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `campaign_id` INT(11) NOT NULL,
            `user_id` INT(11) NOT NULL,
            `email_sent_to` VARCHAR(255) NOT NULL,
            `status` ENUM('pending', 'sent', 'failed', 'opted_out') NOT NULL DEFAULT 'pending',
            `error_message` VARCHAR(500) NULL DEFAULT NULL,
            `sent_at` TIMESTAMP NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            INDEX `idx_pcr_campaign` (`campaign_id`),
            INDEX `idx_pcr_user` (`user_id`),
            CONSTRAINT `fk_pcr_campaign` 
                FOREIGN KEY (`campaign_id`) REFERENCES `promotional_campaigns` (`id`) 
                ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT `fk_pcr_user` 
                FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) 
                ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "Table 'promotional_campaign_recipients' verified/created.\n";

    echo "Migration completed successfully!\n";
} catch (Throwable $e) {
    echo "Migration error: " . $e->getMessage() . "\n";
    exit(1);
}
