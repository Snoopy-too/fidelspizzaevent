<?php
declare(strict_types=1);

namespace FidelsPizza\Domain\Repository;

use FidelsPizza\Domain\Model\MarketingUser;

interface PromotionalUserRepositoryInterface
{
    /**
     * Fetch users by an array of IDs.
     * @param int[] $userIds
     * @return MarketingUser[]
     */
    public function findByIds(array $userIds): array;

    /**
     * Fetch all users who are confirmed and have marketing consent.
     * @return MarketingUser[]
     */
    public function findAllEligible(): array;

    /**
     * Fetch all registered users for admin selection view.
     * @return array<int, array<string, mixed>>
     */
    public function findAllUsersForSelection(): array;

    /**
     * Find a user by their unique unsubscribe token.
     */
    public function findByUnsubscribeToken(string $token): ?MarketingUser;

    /**
     * Set marketing consent to false for user matching the unsubscribe token.
     */
    public function optOutByToken(string $token): bool;
}
