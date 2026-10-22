<?php
declare(strict_types=1);

namespace FidelsPizza\Domain\Model;

final class CustomerOrderItem
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $menuItemId,
        private readonly string $name,
        private readonly int $quantity,
        private readonly float $unitPrice,
        private readonly float $subtotal
    ) {
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
