<?php
declare(strict_types=1);

namespace FidelsPizza\Domain\Model;

final class CustomerOrderItem
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $menuItemId,
        public readonly string $name,
        public readonly int $quantity,
        public readonly float $unitPrice,
        public readonly float $subtotal
    ) {
    }

    public function isSpecialty(): bool
    {
        return stripos($this->name, 'special') !== false;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMenuItemId(): int
    {
        return $this->menuItemId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getUnitPrice(): float
    {
        return $this->unitPrice;
    }

    public function getSubtotal(): float
    {
        return $this->subtotal;
    }

    public function __get(string $name): mixed
    {
        return match ($name) {
            'isSpecialty' => $this->isSpecialty(),
            default => null,
        };
    }

    public function __isset(string $name): bool
    {
        return $name === 'isSpecialty';
    }
}
