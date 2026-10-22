<?php
declare(strict_types=1);

namespace FidelsPizza\Application\UseCase;

use FidelsPizza\Application\DTO\UpdateOrderRequest;
use FidelsPizza\Application\DTO\UpdateOrderResult;
use FidelsPizza\Domain\Repository\OrderRepositoryInterface;
use FidelsPizza\Infrastructure\Security\CsrfManager;

final class UpdateOrderUseCase
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly ManagePickupTimeSlotsUseCase $manageSlotsUseCase,
        private readonly ?SendOrderNotificationUseCase $sendOrderNotificationUseCase = null,
        private readonly array $siteConfig = []
    ) {
    }

    public function execute(UpdateOrderRequest $request): UpdateOrderResult
    {
        // 1. Verify CSRF
        if ($request->csrfToken !== null && !CsrfManager::verify($request->csrfToken)) {
            $msg = function_exists('__') ? (string)__('invalid_csrf_token') : 'Invalid security token.';
            return new UpdateOrderResult(success: false, errorMessage: $msg);
        }

        // 2. Fetch order and check ownership
        $order = $this->orderRepository->findOrderByIdAndUserId($request->orderId, $request->userId);
        if ($order === null) {
            return new UpdateOrderResult(success: false, errorMessage: 'Order not found or cannot be modified.');
        }

        // 3. Fetch existing order items
        $orderItems = $this->orderRepository->findOrderItemsByOrderId($request->orderId);
        if (empty($orderItems)) {
            return new UpdateOrderResult(success: false, errorMessage: 'No items found for this order.');
        }

        $itemsToUpdate = [];
        $itemsToDelete = [];
        $totalAmount = 0.0;
        $totalPizzas = 0;

        foreach ($orderItems as $item) {
            $itemId = (int)$item['order_item_id'];
            $price = (float)$item['price'];

            $newQty = isset($request->itemQuantities[$itemId])
                ? max(0, min(15, (int)$request->itemQuantities[$itemId]))
                : (int)$item['quantity'];

            if ($newQty > 0) {
                $subtotal = $newQty * $price;
                $itemsToUpdate[] = [
                    'order_item_id' => $itemId,
                    'quantity' => $newQty,
                    'unit_price' => $price,
                    'subtotal' => $subtotal,
                ];
                $totalAmount += $subtotal;
                $totalPizzas += $newQty;
            } else {
                $itemsToDelete[] = $itemId;
            }
        }

        // 4. If all items were removed, cancel the order
        if ($totalPizzas <= 0) {
            $this->orderRepository->cancelOrder($request->orderId);
            if (!empty($itemsToDelete)) {
                $this->orderRepository->updateOrder(
                    $request->orderId,
                    0.0,
                    $order['pickup_time'] ?? null,
                    isset($order['pickup_slot_id']) ? (int)$order['pickup_slot_id'] : null,
                    [],
                    $itemsToDelete
                );
            }

            if ($this->sendOrderNotificationUseCase !== null) {
                $this->sendOrderNotificationUseCase->execute($request->orderId, 'updated');
            }

            return new UpdateOrderResult(
                success: true,
                isCancelled: true,
                totalAmount: 0.0,
                emailSent: true
            );
        }

        // 5. Validate pickup slot
        $eventDate = !empty($this->siteConfig['event_date'])
            ? (string)$this->siteConfig['event_date']
            : (!empty($order['pickup_time']) ? date('Y-m-d', strtotime((string)$order['pickup_time'])) : date('Y-m-d'));

        $availableSlots = $this->manageSlotsUseCase->getAvailableSlotsForOrder($eventDate, $request->orderId);

        $pickupTime = $order['pickup_time'] ?? null;
        $pickupSlotId = isset($order['pickup_slot_id']) ? (int)$order['pickup_slot_id'] : null;

        if (!empty($availableSlots) && $request->pickupSlotId !== null && $request->pickupSlotId > 0) {
            try {
                $validatedSlot = $this->manageSlotsUseCase->validateSlotForOrder(
                    $request->pickupSlotId,
                    $totalPizzas,
                    $eventDate,
                    $request->orderId
                );
                $pickupTime = $validatedSlot->toDatetimeString($eventDate);
                $pickupSlotId = $validatedSlot->getId();
            } catch (\InvalidArgumentException $e) {
                return new UpdateOrderResult(success: false, errorMessage: $e->getMessage());
            }
        }

        // 6. Update order in repository
        try {
            $this->orderRepository->updateOrder(
                $request->orderId,
                $totalAmount,
                $pickupTime,
                $pickupSlotId,
                $itemsToUpdate,
                $itemsToDelete
            );

            $emailSent = false;
            if ($this->sendOrderNotificationUseCase !== null) {
                $emailSent = $this->sendOrderNotificationUseCase->execute($request->orderId, 'updated');
            }

            return new UpdateOrderResult(
                success: true,
                isCancelled: false,
                totalAmount: $totalAmount,
                pickupTime: $pickupTime,
                emailSent: $emailSent
            );
        } catch (\Throwable $e) {
            return new UpdateOrderResult(
                success: false,
                errorMessage: '注文の更新に失敗しました。もう一度お試しください。'
            );
        }
    }
}
