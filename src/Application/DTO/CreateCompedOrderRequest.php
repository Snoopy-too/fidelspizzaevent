<?php
declare(strict_types=1);

namespace FidelsPizza\Application\DTO;

final class CreateCompedOrderRequest
{
    /**
     * @param int $adminId
     * @param array<int, int> $itemQuantities Associative array [menu_item_id => quantity]
     * @param string|null $notes Recipient note or buffer reason
     * @param int|null $pickupSlotId Optional slot ID
     * @param string|null $pickupTime Optional exact pickup datetime string
     */
    public function __construct(
        public readonly int $adminId,
        public readonly array $itemQuantities,
        public readonly ?string $notes = null,
        public readonly ?int $pickupSlotId = null,
        public readonly ?string $pickupTime = null
    ) {
    }
}
