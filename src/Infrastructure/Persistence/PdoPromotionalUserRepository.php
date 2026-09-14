<?php
declare(strict_types=1);

namespace FidelsPizza\Infrastructure\Persistence;

use FidelsPizza\Domain\Model\MarketingUser;
use FidelsPizza\Domain\Repository\PromotionalUserRepositoryInterface;
use PDO;

final class PdoPromotionalUserRepository implements PromotionalUserRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function findByIds(array $userIds): array
    {
        if (empty($userIds)) {
            return [];
        }

        $cleanIds = array_filter(array_map('intval', $userIds), fn(int $id) => $id > 0);
        if (empty($cleanIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($cleanIds), '?'));
        $stmt = $this->pdo->prepare("
            SELECT id, email, first_name, last_name, phone, is_confirmed, accepts_marketing, unsubscribe_token
            FROM `users`
            WHERE id IN ($placeholders)
            ORDER BY id ASC
        ");
        $stmt->execute(array_values($cleanIds));
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'hydrateUser'], $rows);
    }

    public function findAllEligible(): array
    {
        $stmt = $this->pdo->query("
            SELECT id, email, first_name, last_name, phone, is_confirmed, accepts_marketing, unsubscribe_token
            FROM `users`
            WHERE is_confirmed = 1 AND accepts_marketing = 1
            ORDER BY id ASC
        ");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'hydrateUser'], $rows);
    }

    public function findAllUsersForSelection(): array
    {
        $stmt = $this->pdo->query("
            SELECT id, email, first_name, last_name, phone, is_confirmed, accepts_marketing, created_at
            FROM `users`
            ORDER BY id DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findByUnsubscribeToken(string $token): ?MarketingUser
    {
        $cleanToken = trim($token);
        if ($cleanToken === '') {
            return null;
        }

        $stmt = $this->pdo->prepare("
            SELECT id, email, first_name, last_name, phone, is_confirmed, accepts_marketing, unsubscribe_token
            FROM `users`
            WHERE unsubscribe_token = ?
            LIMIT 1
        ");
        $stmt->execute([$cleanToken]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->hydrateUser($row) : null;
    }

    public function optOutByToken(string $token): bool
    {
        $cleanToken = trim($token);
        if ($cleanToken === '') {
            return false;
        }

        $stmt = $this->pdo->prepare("
            UPDATE `users`
            SET accepts_marketing = 0
            WHERE unsubscribe_token = ?
        ");
        $stmt->execute([$cleanToken]);

        return $stmt->rowCount() > 0 || $this->findByUnsubscribeToken($cleanToken)?->acceptsMarketing() === false;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrateUser(array $row): MarketingUser
    {
        return new MarketingUser(
            id: (int)$row['id'],
            email: (string)$row['email'],
            firstName: (string)$row['first_name'],
            lastName: (string)$row['last_name'],
            phone: isset($row['phone']) ? (string)$row['phone'] : null,
            isConfirmed: (bool)$row['is_confirmed'],
            acceptsMarketing: (bool)$row['accepts_marketing'],
            unsubscribeToken: isset($row['unsubscribe_token']) ? (string)$row['unsubscribe_token'] : null
        );
    }
}
