<?php
declare(strict_types=1);

namespace FidelsPizza\Application\UseCase;

use FidelsPizza\Application\DTO\AuthenticateUserRequest;
use FidelsPizza\Application\DTO\AuthenticateUserResult;
use FidelsPizza\Domain\Model\AuthenticatedUser;
use FidelsPizza\Domain\Repository\UserRepositoryInterface;

final class AuthenticateUserUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository
    ) {
    }

    /**
     * Authenticate customer credentials with fallback to admin if configured.
     */
    public function authenticateCustomer(AuthenticateUserRequest $request): AuthenticateUserResult
    {
        $identifier = trim($request->identifier);
        $password = $request->password;

        if ($identifier === '' || $password === '') {
            return new AuthenticateUserResult(
                success: false,
                errorCode: 'error_email_password_required',
                errorMessage: 'Email and password are required.'
            );
        }

        if (!filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            return new AuthenticateUserResult(
                success: false,
                errorCode: 'error_invalid_email',
                errorMessage: 'Invalid email address.'
            );
        }

        // 1. Check customer record
        $customer = $this->userRepository->findCustomerByEmail($identifier);
        if ($customer !== null && password_verify($password, (string)$customer['password_hash'])) {
            $isConfirmed = !empty($customer['is_confirmed']);
            if (!$isConfirmed) {
                return new AuthenticateUserResult(
                    success: false,
                    errorCode: 'error_email_confirm_required',
                    errorMessage: 'Please confirm your email address before logging in.'
                );
            }

            $user = new AuthenticatedUser(
                id: (int)$customer['id'],
                usernameOrName: (string)$customer['first_name'],
                email: (string)$customer['email'],
                role: AuthenticatedUser::ROLE_CUSTOMER,
                isConfirmed: true,
                preferredLang: !empty($customer['preferred_lang']) ? (string)$customer['preferred_lang'] : null
            );

            return new AuthenticateUserResult(success: true, user: $user);
        }

        // 2. Fallback to admin if email matches an administrator account
        if ($request->allowAdminFallback) {
            $admin = $this->userRepository->findAdminByEmail($identifier);
            if ($admin !== null && password_verify($password, (string)$admin['password_hash'])) {
                $user = new AuthenticatedUser(
                    id: (int)$admin['id'],
                    usernameOrName: (string)$admin['username'],
                    email: (string)$admin['email'],
                    role: AuthenticatedUser::ROLE_ADMIN,
                    isConfirmed: true,
                    preferredLang: null
                );
                return new AuthenticateUserResult(success: true, user: $user);
            }
        }

        return new AuthenticateUserResult(
            success: false,
            errorCode: 'error_invalid_credentials',
            errorMessage: 'Invalid email or password.'
        );
    }

    /**
     * Authenticate administrator credentials (by username or admin email).
     */
    public function authenticateAdmin(string $identifier, string $password): AuthenticateUserResult
    {
        $cleanIdentifier = trim($identifier);
        if ($cleanIdentifier === '' || $password === '') {
            return new AuthenticateUserResult(
                success: false,
                errorCode: 'error_username_password_required',
                errorMessage: 'Username and password are required.'
            );
        }

        $admin = $this->userRepository->findAdminByUsernameOrEmail($cleanIdentifier);
        if ($admin !== null && password_verify($password, (string)$admin['password_hash'])) {
            $user = new AuthenticatedUser(
                id: (int)$admin['id'],
                usernameOrName: (string)$admin['username'],
                email: (string)$admin['email'],
                role: AuthenticatedUser::ROLE_ADMIN,
                isConfirmed: true,
                preferredLang: null
            );
            return new AuthenticateUserResult(success: true, user: $user);
        }

        return new AuthenticateUserResult(
            success: false,
            errorCode: 'error_invalid_credentials',
            errorMessage: 'Invalid credentials.'
        );
    }

    /**
     * Record authenticated user session in database.
     */
    public function createSessionRecord(string $sessionId, AuthenticatedUser $user, int $ttlSeconds): void
    {
        $userId = $user->isCustomer() ? $user->getId() : null;
        $adminId = $user->isAdmin() ? $user->getId() : null;
        $this->userRepository->recordSession($sessionId, $userId, $adminId, $ttlSeconds);
    }
}
