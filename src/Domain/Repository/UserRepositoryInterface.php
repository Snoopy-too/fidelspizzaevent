<?php
declare(strict_types=1);

namespace FidelsPizza\Domain\Repository;

use FidelsPizza\Domain\Model\AuthenticatedUser;

interface UserRepositoryInterface
{
    /**
     * @return array{id: int, first_name: string, last_name: string, email: string, password_hash: string, is_confirmed: int|bool, preferred_lang: ?string}|null
     */
    public function findCustomerByEmail(string $email): ?array;

    /**
     * @return array{id: int, username: string, email: string, password_hash: string}|null
     */
    public function findAdminByUsernameOrEmail(string $identifier): ?array;

    /**
     * @return array{id: int, username: string, email: string, password_hash: string}|null
     */
    public function findAdminByEmail(string $email): ?array;

    /**
     * Record session token and expiry in database.
     */
    public function recordSession(string $sessionId, ?int $userId, ?int $adminId, int $ttlSeconds): void;
}
