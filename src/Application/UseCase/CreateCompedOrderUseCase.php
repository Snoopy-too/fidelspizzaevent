<?php
declare(strict_types=1);

namespace FidelsPizza\Application\UseCase;

use FidelsPizza\Application\DTO\CompedOrderResult;
use FidelsPizza\Application\DTO\CreateCompedOrderRequest;
use FidelsPizza\Domain\Model\CompedOrder;
use FidelsPizza\Domain\Model\CompedOrderItem;
use FidelsPizza\Domain\Repository\CompedOrderRepositoryInterface;
use FidelsPizza\Domain\Repository\PickupTimeSlotRepositoryInterface;

final class CreateCompedOrderUseCase
{
    public function __construct(
        private readonly CompedOrderRepositoryInterface $compedOrderRepository,
        private readonly ?PickupTimeSlotRepositoryInterface $pickupSlotRepository = null,
        private readonly array $siteConfig = []
    ) {
    }

    public function execute(CreateCompedOrderRequest $request): CompedOrderResult
    {
        if ($request->adminId <= 0) {
            return new CompedOrderResult(false, null, null, 0, 'Invalid admin authentication.');
        }

        // Validate quantities: must have at least one pizza with quantity > 0
        $activeMenuItems = $this->compedOrderRepository->getActiveMenuItems();
        $menuItemMap = [];
        foreach ($activeMenuItems as $item) {
            $menuItemMap[(int)$item['id']] = (string)$item['name'];
        }

        /** @var array<CompedOrderItem> $items */
        $items = [];
        $totalPizzas = 0;

        foreach ($request->itemQuantities as $menuItemId => $rawQty) {
            $itemId = (int)$menuItemId;
            $qty = (int)$rawQty;

            if ($qty <= 0) {
                continue;
            }

            if (!isset($menuItemMap[$itemId])) {
                return new CompedOrderResult(false, null, null, 0, "Selected menu item #$itemId does not exist or is inactive.");
            }

            $items[] = new CompedOrderItem($itemId, $menuItemMap[$itemId], $qty);
            $totalPizzas += $qty;
        }

        if (empty($items) || $totalPizzas <= 0) {
            return new CompedOrderResult(false, null, null, 0, 'At least one pizza must be selected with a quantity greater than 0.');
        }

        // Resolve admin user ID to satisfy database foreign key constraint
        $userId = $this->compedOrderRepository->resolveAdminUserId($request->adminId);

        // Resolve pickup time
        $pickupTime = $request->pickupTime !== null && trim($request->pickupTime) !== '' ? trim($request->pickupTime) : null;
        $slotId = $request->pickupSlotId !== null && $request->pickupSlotId > 0 ? $request->pickupSlotId : null;

        if ($pickupTime === null && $slotId !== null && $this->pickupSlotRepository !== null) {
            $slot = $this->pickupSlotRepository->findById($slotId);
            if ($slot !== null) {
                $eventDate = !empty($this->siteConfig['event_date']) ? (string)$this->siteConfig['event_date'] : date('Y-m-d');
                $pickupTime = $eventDate . ' ' . $slot->getSlotTime();
            }
        }

        // Generate unique order number
        if (function_exists('generateOrderNumber')) {
            $orderNumber = generateOrderNumber();
        } else {
            $orderNumber = 'PZ' . date('Y') . str_pad((string)random_int(1, 9999), 4, '0', STR_PAD_LEFT);
        }

        $notes = $request->notes !== null ? trim($request->notes) : null;

        $order = new CompedOrder(
            id: null,
            userId: $userId,
            orderNumber: $orderNumber,
            totalAmount: 0.0,
            status: 'comped',
            notes: $notes,
            pickupTime: $pickupTime,
            pickupSlotId: $slotId,
            items: $items
        );

        try {
            $orderId = $this->compedOrderRepository->save($order);
            return new CompedOrderResult(
                success: true,
                orderId: $orderId,
                orderNumber: $orderNumber,
                totalPizzas: $totalPizzas
            );
        } catch (\Throwable $e) {
            return new CompedOrderResult(
                success: false,
                errorMessage: 'Failed to create comped order: ' . $e->getMessage()
            );
        }
    }
}
