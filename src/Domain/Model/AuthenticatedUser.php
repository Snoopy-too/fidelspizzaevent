<?php
declare(strict_types=1);

namespace FidelsPizza\Domain\Model;

final class AuthenticatedUser
{
    public const ROLE_CUSTOMER = 'customer';
    public const ROLE_ADMIN = 'admin';

    public function __construct(
        private readonly int $id,
        private readonly string $usernameOrName,
        private readonly string $email,
        private readonly string $role,
        private readonly bool $isConfirmed = true,
        private readonly ?string $preferredLang = null
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getUsernameOrName(): string
    {
        return $this->usernameOrName;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function isCustomer(): bool
    {
        return $this->role === self::ROLE_CUSTOMER;
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isConfirmed(): bool
    {
        return $this->isConfirmed;
    }

    public function getPreferredLang(): ?string
    {
        return $this->preferredLang;
    }
}
