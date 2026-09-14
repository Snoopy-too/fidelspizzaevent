<?php
declare(strict_types=1);

namespace FidelsPizza\Application\UseCase;

use FidelsPizza\Domain\Repository\PromotionalUserRepositoryInterface;

final class UnsubscribeUserUseCase
{
    public function __construct(
        private readonly PromotionalUserRepositoryInterface $userRepository
    ) {
    }

    /**
     * @return array{success: bool, email: ?string, name: ?string, message: string}
     */
    public function execute(string $token): array
    {
        $cleanToken = trim($token);
        if ($cleanToken === '') {
            return [
                'success' => false,
                'email' => null,
                'name' => null,
                'message' => 'Invalid or missing unsubscribe token.'
            ];
        }

        $user = $this->userRepository->findByUnsubscribeToken($cleanToken);
        if (!$user) {
            return [
                'success' => false,
                'email' => null,
                'name' => null,
                'message' => 'The unsubscribe link is invalid or has expired.'
            ];
        }

        $optedOut = $this->userRepository->optOutByToken($cleanToken);
        if (!$optedOut) {
            return [
                'success' => false,
                'email' => $user->getEmail(),
                'name' => $user->getFullName(),
                'message' => 'Could not update your subscription preferences. Please try again or contact support.'
            ];
        }

        return [
            'success' => true,
            'email' => $user->getEmail(),
            'name' => $user->getFullName(),
            'message' => 'You have been successfully unsubscribed from promotional emails.'
        ];
    }
}
