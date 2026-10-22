<?php
declare(strict_types=1);

namespace FidelsPizza\Application\DTO;

final class AuthenticateUserRequest
{
    public function __construct(
        public readonly string $identifier,
        public readonly string $password,
        public readonly bool $allowAdminFallback = true
    ) {
    }
}
