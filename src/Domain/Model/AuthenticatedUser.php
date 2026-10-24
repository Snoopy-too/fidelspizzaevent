<?php
declare(strict_types=1);

namespace FidelsPizza\Domain\Model;

final class AuthenticatedUser
{
    public const ROLE_CUSTOMER = 'customer';
    public const ROLE_ADMIN = 'admin';

    public function __construct(
        public private(set) int $id,
        public private(set) string $usernameOrName,
        public private(set) string $email,
        public private(set) string $role,
        public private(set) bool $isConfirmed = true,
        public private(set) ?string $preferredLang = null
    ) {
    }

    /**
     * PHP 8.5 Property Hook: Customer role check
     */
    public bool $isCustomer {
        get => $this->role === self::ROLE_CUSTOMER;
    }

    /**
     * PHP 8.5 Property Hook: Admin role check
     */
    public bool $isAdmin {
        get => $this->role === self::ROLE_ADMIN;
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
        return $this->isCustomer;
    }

    public function isAdmin(): bool
    {
        return $this->isAdmin;
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
