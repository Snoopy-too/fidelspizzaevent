<?php
declare(strict_types=1);

namespace FidelsPizza\Domain\Repository;

use DateTimeImmutable;
use FidelsPizza\Domain\Model\PasswordResetToken;

interface PasswordResetRepositoryInterface
{
    /**
     * Find customer user details by email address.
     *
     * @return array{id: int, email: string, first_name: string, last_name: string, is_confirmed: bool}|null
     */
    public function findUserByEmail(string $email): ?array;

    /**
     * Find customer user details by ID.
     *
     * @return array{id: int, email: string, first_name: string, last_name: string, is_confirmed: bool}|null
     */
    public function findUserById(int $userId): ?array;

    /**
     * Count reset tokens created for a user since the given timestamp.
     */
    public function countRecentRequestsForUser(int $userId, DateTimeImmutable $since): int;

    /**
     * Count reset tokens created from an IP address since the given timestamp.
     */
    public function countRecentRequestsForIp(string $ipAddress, DateTimeImmutable $since): int;

    /**
     * Invalidate any existing unused tokens for the user and persist a new reset token.
     */
    public function createToken(PasswordResetToken $token): PasswordResetToken;

    /**
     * Look up a password reset token by its 16-char public selector.
     */
    public function findBySelector(string $selector): ?PasswordResetToken;

    /**
     * Atomically consume the token, update the user's password hash, confirm their email if unconfirmed,
     * invalidate all other tokens for the user, and revoke active user sessions.
     */
    public function consumeTokenAndResetPassword(int $tokenId, int $userId, string $newPasswordHash): bool;

    /**
     * Directly update a user's password hash (e.g. by an administrator), invalidate reset tokens,
     * and revoke active user sessions.
     */
    public function updateUserPassword(int $userId, string $newPasswordHash): bool;

    /**
     * Fetch the password_reset email template subject and body if configured in email_templates.
     *
     * @return array{subject: string, body: string}|null
     */
    public function getPasswordResetEmailTemplate(): ?array;
}
