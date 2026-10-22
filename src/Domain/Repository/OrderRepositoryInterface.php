<?php
declare(strict_types=1);

namespace FidelsPizza\Domain\Repository;

use FidelsPizza\Domain\Model\CustomerOrder;

interface OrderRepositoryInterface
{
    /**
     * Retrieve all active menu items sorted by sort_order and name.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getActiveMenuItems(): array;

    /**
     * Find an order by ID and user ID.
     *
     * @return array<string, mixed>|null
     */
    public function findOrderByIdAndUserId(int $orderId, int $userId): ?array;

    /**
     * Find items for a given order ID.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findOrderItemsByOrderId(int $orderId): array;

    /**
     * Get user email by user ID.
     */
    public function getUserEmail(int $userId): ?string;

    /**
     * Save a new customer order with its line items in a database transaction.
     */
    public function saveOrder(CustomerOrder $order): int;

    /**
     * Update an existing order's totals, slot, and items transactionally.
     *
     * @param int $orderId
     * @param float $totalAmount
     * @param string|null $pickupTime
     * @param int|null $pickupSlotId
     * @param array<int, array{order_item_id: int, quantity: int, unit_price: float, subtotal: float}> $itemsToUpdate
     * @param array<int> $itemsToDelete
     */
    public function updateOrder(
        int $orderId,
        float $totalAmount,
        ?string $pickupTime,
        ?int $pickupSlotId,
        array $itemsToUpdate,
        array $itemsToDelete
    ): void;

    /**
     * Cancel an existing order by marking its status as cancelled and zeroing out total amount.
     */
    public function cancelOrder(int $orderId): void;
}
