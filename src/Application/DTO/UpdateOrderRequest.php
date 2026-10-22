<?php
declare(strict_types=1);

namespace FidelsPizza\Application\DTO;

final class UpdateOrderRequest
{
    /**
     * @param int $orderId
     * @param int $userId
     * @param array<int, int> $itemQuantities Mapping of order_item_id => quantity
     * @param int|null $pickupSlotId
     * @param string|null $csrfToken
     */
    public function __construct(
        public readonly int $orderId,
        public readonly int $userId,
        public readonly array $itemQuantities,
        public readonly ?int $pickupSlotId = null,
        public readonly ?string $csrfToken = null
    ) {
    }
}
