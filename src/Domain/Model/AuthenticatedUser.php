<?php
declare(strict_types=1);

namespace FidelsPizza\Domain\Model;

final class AuthenticatedUser
{
    public const ROLE_CUSTOMER = 'customer';
    public const ROLE_ADMIN = 'admin';

    public function __construct(
        public readonly int $id,
        public readonly string $usernameOrName,
        public readonly string $email,
        public readonly string $role,
        public readonly bool $isConfirmed = true,
        public readonly ?string $preferredLang = null
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

    public function __get(string $name): mixed
    {
        return match ($name) {
            'isCustomer' => $this->isCustomer(),
            'isAdmin' => $this->isAdmin(),
            default => null,
        };
    }

    public function __isset(string $name): bool
    {
        return in_array($name, ['isCustomer', 'isAdmin'], true);
    }
}
