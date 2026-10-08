<?php
declare(strict_types=1);

namespace FidelsPizza\Application\UseCase;

use FidelsPizza\Domain\Model\CompedOrder;
use FidelsPizza\Domain\Repository\CompedOrderRepositoryInterface;

final class GetCompedOrdersUseCase
{
    public function __construct(
        private readonly CompedOrderRepositoryInterface $compedOrderRepository
    ) {
    }

    /**
     * @param string|null $eventDate
     * @return array<CompedOrder>
     */
    public function getOrders(?string $eventDate = null): array
    {
        return $this->compedOrderRepository->getCompedOrders($eventDate);
    }

    /**
     * @param string|null $eventDate
     * @return array{total_comped_pizzas: int, total_comped_orders: int, items_breakdown: array<string, int>}
     */
    public function getSummary(?string $eventDate = null): array
    {
        return $this->compedOrderRepository->getCompedSummary($eventDate);
    }

    /**
     * @return array<array{id: int, name: string, price: float, image_path: ?string}>
     */
    public function getActiveMenuItems(): array
    {
        return $this->compedOrderRepository->getActiveMenuItems();
    }
}
