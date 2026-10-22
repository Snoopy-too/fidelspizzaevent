<?php
declare(strict_types=1);

namespace FidelsPizza\Application\DTO;

use FidelsPizza\Domain\Model\AuthenticatedUser;

final class AuthenticateUserResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?AuthenticatedUser $user = null,
        public readonly ?string $errorCode = null,
        public readonly ?string $errorMessage = null
    ) {
    }
}
