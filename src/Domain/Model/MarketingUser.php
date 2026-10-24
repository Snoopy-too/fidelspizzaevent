<?php
declare(strict_types=1);

namespace FidelsPizza\Domain\Model;

final class MarketingUser
{
    public function __construct(
        public private(set) int $id,
        public private(set) string $email,
        public private(set) string $firstName,
        public private(set) string $lastName,
        public private(set) ?string $phone,
        public private(set) bool $isConfirmed,
        public private(set) bool $acceptsMarketing,
        public private(set) ?string $unsubscribeToken
    ) {
    }

    /**
     * PHP 8.5 Property Hook: Formatted full name
     */
    public string $fullName {
        get => $this->getFullName();
    }

    /**
     * PHP 8.5 Property Hook: Promotion delivery eligibility
     */
    public bool $canReceivePromotions {
        get => $this->canReceivePromotions();
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
