<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

echo "Starting user preferred_lang column migration...\n";

try {
    $db = getDB();

    $stmt = $db->query("SHOW COLUMNS FROM `users` LIKE 'preferred_lang'");
    if (!$stmt->fetch()) {
        echo "Adding 'preferred_lang' column to 'users' table...\n";
        $db->exec("
            ALTER TABLE `users` 
            ADD COLUMN `preferred_lang` VARCHAR(10) NOT NULL DEFAULT 'ja' AFTER `accepts_marketing`
        ");
        echo "Column 'preferred_lang' added successfully.\n";
    } else {
        echo "'preferred_lang' column already exists in 'users'.\n";
    }

    echo "Migration completed successfully!\n";
} catch (Throwable $e) {
    echo "Migration error: " . $e->getMessage() . "\n";
    exit(1);
}
