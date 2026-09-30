<?php
declare(strict_types=1);

namespace FidelsPizza\Application\UseCase;

use DateTimeImmutable;
use FidelsPizza\Domain\Model\PasswordResetToken;
use FidelsPizza\Domain\Repository\PasswordResetRepositoryInterface;

final class ResetPasswordUseCase
{
    public const MIN_PASSWORD_LENGTH = 6;

    public function __construct(
        private readonly PasswordResetRepositoryInterface $repository
    ) {
    }

    /**
     * Validate a raw 80-character hex password reset token and return the token + user if valid.
     *
     * @return array{token: PasswordResetToken, user: array{id: int, email: string, first_name: string, last_name: string, is_confirmed: bool}}|null
     */
    public function validateToken(string $rawToken, ?DateTimeImmutable $now = null): ?array
    {
        $parsed = PasswordResetToken::parseRawToken($rawToken);
        if ($parsed === null) {
            return null;
        }

        $token = $this->repository->findBySelector($parsed['selector']);
        if ($token === null) {
            return null;
        }

        if (!$token->isValid($parsed['verifier'], $now)) {
            return null;
        }

        $user = $this->repository->findUserById($token->getUserId());
        if ($user === null) {
            return null;
        }

        return [
            'token' => $token,
            'user' => $user,
        ];
    }

    /**
     * Consume a valid password reset token and update the customer's password.
     *
     * @return array{success: bool, error_code: ?string}
     */
    public function execute(
        string $rawToken,
        string $newPassword,
        string $confirmPassword,
        ?DateTimeImmutable $now = null
    ): array {
        if ($newPassword === '' || $confirmPassword === '') {
            return [
                'success' => false,
                'error_code' => 'error_all_fields_required',
            ];
        }

        if (strlen($newPassword) < self::MIN_PASSWORD_LENGTH) {
            return [
                'success' => false,
                'error_code' => 'error_password_length',
            ];
        }

        if (!hash_equals($newPassword, $confirmPassword)) {
            return [
                'success' => false,
                'error_code' => 'error_password_mismatch',
            ];
        }

        $validated = $this->validateToken($rawToken, $now);
        if ($validated === null) {
            return [
                'success' => false,
                'error_code' => 'error_invalid_or_expired_reset_token',
            ];
        }

        $token = $validated['token'];
        $user = $validated['user'];
        $tokenId = $token->getId();
        if ($tokenId === null) {
            return [
                'success' => false,
                'error_code' => 'error_invalid_or_expired_reset_token',
            ];
        }

        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT, ['cost' => 10]);
        $updated = $this->repository->consumeTokenAndResetPassword($tokenId, $user['id'], $passwordHash);

        if (!$updated) {
            return [
                'success' => false,
                'error_code' => 'error_invalid_or_expired_reset_token',
            ];
        }

        return [
            'success' => true,
            'error_code' => null,
        ];
    }

    /**
     * Allow an administrator to directly set a customer's password and revoke existing sessions/tokens.
     *
     * @return array{success: bool, error_code: ?string}
     */
    public function adminResetPassword(int $userId, string $newPassword): array
    {
        if (strlen($newPassword) < self::MIN_PASSWORD_LENGTH) {
            return [
                'success' => false,
                'error_code' => 'error_password_length',
            ];
        }

        $user = $this->repository->findUserById($userId);
        if ($user === null) {
            return [
                'success' => false,
                'error_code' => 'user_not_found',
            ];
        }

        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT, ['cost' => 10]);
        $updated = $this->repository->updateUserPassword($userId, $passwordHash);

        return [
            'success' => $updated,
            'error_code' => $updated ? null : 'user_update_error',
        ];
    }
}
