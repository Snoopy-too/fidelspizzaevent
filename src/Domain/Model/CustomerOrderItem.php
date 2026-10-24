<?php
declare(strict_types=1);

namespace FidelsPizza\Domain\Model;

final class CustomerOrderItem
{
    public function __construct(
        public private(set) ?int $id,
        public private(set) int $menuItemId,
        public private(set) string $name,
        public private(set) int $quantity,
        public private(set) float $unitPrice,
        public private(set) float $subtotal
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
}
