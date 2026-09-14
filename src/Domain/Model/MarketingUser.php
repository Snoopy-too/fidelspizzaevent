<?php
declare(strict_types=1);

namespace FidelsPizza\Domain\Model;

final class MarketingUser
{
    public function __construct(
        private readonly int $id,
        private readonly string $email,
        private readonly string $firstName,
        private readonly string $lastName,
        private readonly ?string $phone,
        private readonly bool $isConfirmed,
        private readonly bool $acceptsMarketing,
        private readonly ?string $unsubscribeToken
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
}
