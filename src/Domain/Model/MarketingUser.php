<?php
declare(strict_types=1);

namespace FidelsPizza\Domain\Model;

final class MarketingUser
{
    public function __construct(
        public readonly int $id,
        public readonly string $email,
        public readonly string $firstName,
        public readonly string $lastName,
        public readonly ?string $phone,
        public readonly bool $isConfirmed,
        public readonly bool $acceptsMarketing,
        public readonly ?string $unsubscribeToken
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function getFullName(): string
    {
        $fullName = trim($this->firstName . ' ' . $this->lastName);
        return $fullName !== '' ? $fullName : $this->email;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function isConfirmed(): bool
    {
        return $this->isConfirmed;
    }

    public function acceptsMarketing(): bool
    {
        return $this->acceptsMarketing;
    }

    public function getUnsubscribeToken(): ?string
    {
        return $this->unsubscribeToken;
    }

    public function canReceivePromotions(): bool
    {
        return $this->isConfirmed && $this->acceptsMarketing;
    }

    public function __get(string $name): mixed
    {
        return match ($name) {
            'fullName' => $this->getFullName(),
            'canReceivePromotions' => $this->canReceivePromotions(),
            default => null,
        };
    }

    public function __isset(string $name): bool
    {
        return in_array($name, ['fullName', 'canReceivePromotions'], true);
    }
}
