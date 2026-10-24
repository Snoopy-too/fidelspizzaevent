<?php
declare(strict_types=1);

namespace FidelsPizza\Domain\Model;

final class CompedOrderItem
{
    public function __construct(
        public readonly int $menuItemId,
        public readonly string $name,
        public readonly int $quantity
    ) {
    }

    public function isSpecialty(): bool
    {
        return stripos($this->name, 'special') !== false;
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
