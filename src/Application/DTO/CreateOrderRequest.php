<?php
declare(strict_types=1);

namespace FidelsPizza\Application\DTO;

final class CreateOrderRequest
{
    /**
     * @param int $userId
     * @param array<int, int> $itemQuantities Mapping of menu_item_id => quantity
     * @param int|null $pickupSlotId
     * @param string|null $csrfToken
     * @param string|null $notes
     */
    public function __construct(
        public readonly int $userId,
        public readonly array $itemQuantities,
        public readonly ?int $pickupSlotId = null,
        public readonly ?string $csrfToken = null,
        public readonly ?string $notes = null
    ) {
    }
}
