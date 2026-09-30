<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/src/bootstrap.php';

try {
    $container = getServiceContainer();
    $repo = $container->getPasswordResetRepository();
    if (method_exists($repo, 'ensureSchema')) {
        $repo->ensureSchema();
    }
    echo "Table 'password_reset_tokens' and 'password_reset' email template migrated successfully.\n";
} catch (\Throwable $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
