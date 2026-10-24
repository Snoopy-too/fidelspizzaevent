<?php
declare(strict_types=1);

namespace FidelsPizza\Infrastructure\Persistence;

use FidelsPizza\Domain\Repository\UserRepositoryInterface;
use Override;
use PDO;

final class PdoUserRepository implements UserRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    #[Override]
    public function findCustomerByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare("SELECT id, first_name, last_name, email, password_hash, is_confirmed, preferred_lang FROM users WHERE LOWER(email) = LOWER(?) LIMIT 1");
        $stmt->execute([trim($email)]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return $user ?: null;
    }

    #[Override]
    public function findAdminByUsernameOrEmail(string $identifier): ?array
    {
        $clean = trim($identifier);
        $stmt = $this->pdo->prepare("SELECT id, username, email, password_hash FROM admins WHERE LOWER(username) = LOWER(?) OR LOWER(email) = LOWER(?) LIMIT 1");
        $stmt->execute([$clean, $clean]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        return $admin ?: null;
    }

    #[Override]
    public function findAdminByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare("SELECT id, username, email, password_hash FROM admins WHERE LOWER(email) = LOWER(?) LIMIT 1");
        $stmt->execute([trim($email)]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        return $admin ?: null;
    }

    #[Override]
    public function recordSession(string $sessionId, ?int $userId, ?int $adminId, int $ttlSeconds): void
    {
        try {
            $expiresAt = date('Y-m-d H:i:s', time() + $ttlSeconds);
            $stmt = $this->pdo->prepare("
                INSERT INTO user_sessions (id, user_id, admin_id, expires_at)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE expires_at = VALUES(expires_at)
            ");
            $stmt->execute([$sessionId, $userId, $adminId, $expiresAt]);
        } catch (\Throwable $e) {
            error_log('[PdoUserRepository] Failed to record session: ' . $e->getMessage());
        }
    }
}
