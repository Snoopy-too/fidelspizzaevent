<?php
declare(strict_types=1);

namespace FidelsPizza\Infrastructure\Persistence;

use DateTimeImmutable;
use FidelsPizza\Domain\Model\PasswordResetToken;
use FidelsPizza\Domain\Repository\PasswordResetRepositoryInterface;
use PDO;
use PDOException;

final class PdoPasswordResetRepository implements PasswordResetRepositoryInterface
{
    private static bool $schemaEnsured = false;

    public function __construct(private readonly PDO $pdo)
    {
        $this->ensureSchema();
    }

    public function ensureSchema(): void
    {
        if (self::$schemaEnsured || $this->pdo->inTransaction()) {
            return;
        }

        try {
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
                    `id` INT(11) NOT NULL AUTO_INCREMENT,
                    `user_id` INT(11) NOT NULL,
                    `selector` CHAR(16) NOT NULL,
                    `token_hash` CHAR(64) NOT NULL,
                    `requested_ip` VARCHAR(45) DEFAULT NULL,
                    `expires_at` DATETIME NOT NULL,
                    `used_at` DATETIME DEFAULT NULL,
                    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `idx_prt_selector` (`selector`),
                    KEY `idx_prt_user_created` (`user_id`, `created_at`),
                    KEY `idx_prt_ip_created` (`requested_ip`, `created_at`),
                    CONSTRAINT `fk_password_reset_tokens_user`
                        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
                        ON DELETE CASCADE ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");

            $tplCheck = $this->pdo->prepare("SELECT COUNT(*) FROM `email_templates` WHERE `template_name` = ?");
            $tplCheck->execute(['password_reset']);
            if ((int)$tplCheck->fetchColumn() === 0) {
                $ins = $this->pdo->prepare("
                    INSERT INTO `email_templates` (`template_name`, `subject`, `body`)
                    VALUES (?, ?, ?)
                ");
                $ins->execute([
                    'password_reset',
                    "Password Reset Request - {{site_title}}",
                    "Hello {{first_name}},\n\nWe received a request to reset the password for your account ({{email}}) at {{site_title}}.\n\nTo set a new password, please click the link below (valid for {{expiry_minutes}} minutes):\n{{reset_link}}\n\nIf you did not request a password reset, you can safely ignore this email and your password will remain unchanged.\n\nBest regards,\nFidel's Pizza Team"
                ]);
            }

            self::$schemaEnsured = true;
        } catch (PDOException) {
            // Ignore if DB user lacks DDL privileges in restricted environments
        }
    }

    public function findUserByEmail(string $email): ?array
    {
        $cleanEmail = trim($email);
        if ($cleanEmail === '') {
            return null;
        }

        $stmt = $this->pdo->prepare("
            SELECT `id`, `email`, `first_name`, `last_name`, `is_confirmed`
            FROM `users`
            WHERE LOWER(`email`) = LOWER(?)
            LIMIT 1
        ");
        $stmt->execute([$cleanEmail]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return [
            'id' => (int)$row['id'],
            'email' => (string)$row['email'],
            'first_name' => (string)$row['first_name'],
            'last_name' => (string)$row['last_name'],
            'is_confirmed' => (bool)$row['is_confirmed'],
        ];
    }

    public function findUserById(int $userId): ?array
    {
        if ($userId <= 0) {
            return null;
        }

        $stmt = $this->pdo->prepare("
            SELECT `id`, `email`, `first_name`, `last_name`, `is_confirmed`
            FROM `users`
            WHERE `id` = ?
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return [
            'id' => (int)$row['id'],
            'email' => (string)$row['email'],
            'first_name' => (string)$row['first_name'],
            'last_name' => (string)$row['last_name'],
            'is_confirmed' => (bool)$row['is_confirmed'],
        ];
    }

    public function countRecentRequestsForUser(int $userId, DateTimeImmutable $since): int
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM `password_reset_tokens`
            WHERE `user_id` = ? AND `created_at` >= ?
        ");
        $stmt->execute([$userId, $since->format('Y-m-d H:i:s')]);
        return (int)$stmt->fetchColumn();
    }

    public function countRecentRequestsForIp(string $ipAddress, DateTimeImmutable $since): int
    {
        $cleanIp = trim($ipAddress);
        if ($cleanIp === '') {
            return 0;
        }

        $stmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM `password_reset_tokens`
            WHERE `requested_ip` = ? AND `created_at` >= ?
        ");
        $stmt->execute([$cleanIp, $since->format('Y-m-d H:i:s')]);
        return (int)$stmt->fetchColumn();
    }

    public function createToken(PasswordResetToken $token): PasswordResetToken
    {
        $nowStr = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        // Invalidate any previous unused tokens for this user
        $invalidateStmt = $this->pdo->prepare("
            UPDATE `password_reset_tokens`
            SET `used_at` = ?
            WHERE `user_id` = ? AND `used_at` IS NULL
        ");
        $invalidateStmt->execute([$nowStr, $token->getUserId()]);

        $insertStmt = $this->pdo->prepare("
            INSERT INTO `password_reset_tokens`
                (`user_id`, `selector`, `token_hash`, `requested_ip`, `expires_at`, `used_at`, `created_at`)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $insertStmt->execute([
            $token->getUserId(),
            $token->getSelector(),
            $token->getTokenHash(),
            $token->getRequestedIp(),
            $token->getExpiresAt()->format('Y-m-d H:i:s'),
            $token->getUsedAt()?->format('Y-m-d H:i:s'),
            $token->getCreatedAt()->format('Y-m-d H:i:s'),
        ]);

        $id = (int)$this->pdo->lastInsertId();

        return new PasswordResetToken(
            userId: $token->getUserId(),
            selector: $token->getSelector(),
            tokenHash: $token->getTokenHash(),
            expiresAt: $token->getExpiresAt(),
            requestedIp: $token->getRequestedIp(),
            usedAt: $token->getUsedAt(),
            createdAt: $token->getCreatedAt(),
            id: $id
        );
    }

    public function findBySelector(string $selector): ?PasswordResetToken
    {
        $cleanSelector = strtolower(trim($selector));
        if (!preg_match('/^[a-f0-9]{16}$/', $cleanSelector)) {
            return null;
        }

        $stmt = $this->pdo->prepare("
            SELECT `id`, `user_id`, `selector`, `token_hash`, `requested_ip`, `expires_at`, `used_at`, `created_at`
            FROM `password_reset_tokens`
            WHERE `selector` = ?
            LIMIT 1
        ");
        $stmt->execute([$cleanSelector]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return new PasswordResetToken(
            userId: (int)$row['user_id'],
            selector: (string)$row['selector'],
            tokenHash: (string)$row['token_hash'],
            expiresAt: new DateTimeImmutable((string)$row['expires_at']),
            requestedIp: $row['requested_ip'] !== null ? (string)$row['requested_ip'] : null,
            usedAt: $row['used_at'] !== null ? new DateTimeImmutable((string)$row['used_at']) : null,
            createdAt: new DateTimeImmutable((string)$row['created_at']),
            id: (int)$row['id']
        );
    }

    public function consumeTokenAndResetPassword(int $tokenId, int $userId, string $newPasswordHash): bool
    {
        $startedTransaction = false;
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
            $startedTransaction = true;
        }

        try {
            $nowStr = (new DateTimeImmutable())->format('Y-m-d H:i:s');

            // Atomically mark token as used only if it has not already been used
            $consumeStmt = $this->pdo->prepare("
                UPDATE `password_reset_tokens`
                SET `used_at` = ?
                WHERE `id` = ? AND `user_id` = ? AND `used_at` IS NULL
            ");
            $consumeStmt->execute([$nowStr, $tokenId, $userId]);

            if ($consumeStmt->rowCount() !== 1) {
                if ($startedTransaction && $this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                return false;
            }

            // Invalidate any other remaining tokens for this user
            $invalidateOthers = $this->pdo->prepare("
                UPDATE `password_reset_tokens`
                SET `used_at` = ?
                WHERE `user_id` = ? AND `used_at` IS NULL
            ");
            $invalidateOthers->execute([$nowStr, $userId]);

            // Update password_hash and mark account confirmed (since email ownership was proven)
            $updateUser = $this->pdo->prepare("
                UPDATE `users`
                SET `password_hash` = ?, `is_confirmed` = 1, `confirmation_token` = NULL
                WHERE `id` = ?
            ");
            $updateUser->execute([$newPasswordHash, $userId]);

            // Revoke all existing active sessions for this user
            $revokeSessions = $this->pdo->prepare("
                DELETE FROM `user_sessions`
                WHERE `user_id` = ?
            ");
            $revokeSessions->execute([$userId]);

            if ($startedTransaction && $this->pdo->inTransaction()) {
                $this->pdo->commit();
            }
            return true;
        } catch (\Throwable $e) {
            if ($startedTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function updateUserPassword(int $userId, string $newPasswordHash): bool
    {
        $startedTransaction = false;
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
            $startedTransaction = true;
        }

        try {
            $nowStr = (new DateTimeImmutable())->format('Y-m-d H:i:s');

            $updateUser = $this->pdo->prepare("
                UPDATE `users`
                SET `password_hash` = ?
                WHERE `id` = ?
            ");
            $updateUser->execute([$newPasswordHash, $userId]);

            $invalidateTokens = $this->pdo->prepare("
                UPDATE `password_reset_tokens`
                SET `used_at` = ?
                WHERE `user_id` = ? AND `used_at` IS NULL
            ");
            $invalidateTokens->execute([$nowStr, $userId]);

            $revokeSessions = $this->pdo->prepare("
                DELETE FROM `user_sessions`
                WHERE `user_id` = ?
            ");
            $revokeSessions->execute([$userId]);

            if ($startedTransaction && $this->pdo->inTransaction()) {
                $this->pdo->commit();
            }
            return true;
        } catch (\Throwable $e) {
            if ($startedTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function getPasswordResetEmailTemplate(): ?array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT `subject`, `body`
                FROM `email_templates`
                WHERE `template_name` = 'password_reset'
                LIMIT 1
            ");
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && !empty($row['subject']) && !empty($row['body'])) {
                return [
                    'subject' => (string)$row['subject'],
                    'body' => str_replace('\n', "\n", (string)$row['body']),
                ];
            }
        } catch (PDOException) {
            // Fall back to default template in UseCase
        }
        return null;
    }
}
