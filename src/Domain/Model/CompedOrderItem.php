<?php
declare(strict_types=1);

namespace FidelsPizza\Domain\Model;

final class CompedOrderItem
{
    public function __construct(
        public private(set) int $menuItemId,
        public private(set) string $name,
        public private(set) int $quantity
    ) {
    }

    /**
     * PHP 8.5 Property Hook: Check if item is a specialty pizza
     */
    public bool $isSpecialty {
        get => stripos($this->name, 'special') !== false;
    }

    public function isSpecialty(): bool
    {
        return $this->isSpecialty;
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
}
