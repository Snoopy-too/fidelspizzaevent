<?php
declare(strict_types=1);

namespace FidelsPizza\Domain\Model;

final class CompedOrderItem
{
    public function __construct(
        private readonly int $menuItemId,
        private readonly string $name,
        private readonly int $quantity
    ) {
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
