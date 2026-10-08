<?php
declare(strict_types=1);

namespace FidelsPizza\Domain\Repository;

use FidelsPizza\Domain\Model\CompedOrder;

interface CompedOrderRepositoryInterface
{
    /**
     * Persists a comped order and its line items.
     * @param CompedOrder $order
     * @return int Created order ID
     */
    public function save(CompedOrder $order): int;

    /**
     * Resolves or provisions a users record for the given admin ID to satisfy Foreign Key constraints.
     * @param int $adminId
     * @return int The user ID in the users table
     */
    public function resolveAdminUserId(int $adminId): int;

    /**
     * Retrieves all comped orders optionally filtered by event date.
     * @param string|null $eventDate
     * @return array<CompedOrder>
     */
    public function getCompedOrders(?string $eventDate = null): array;

    /**
     * Returns summary metrics for comped orders.
     * @param string|null $eventDate
     * @return array{total_comped_pizzas: int, total_comped_orders: int, items_breakdown: array<string, int>}
     */
    public function getCompedSummary(?string $eventDate = null): array;

    /**
     * Retrieves active menu items for building order forms.
     * @return array<array{id: int, name: string, price: float, image_path: ?string}>
     */
    public function getActiveMenuItems(): array;
}
