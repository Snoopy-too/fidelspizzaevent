<?php
declare(strict_types=1);

namespace FidelsPizza\Application\UseCase;

use FidelsPizza\Application\DTO\CreateOrderRequest;
use FidelsPizza\Application\DTO\CreateOrderResult;
use FidelsPizza\Domain\Model\CustomerOrder;
use FidelsPizza\Domain\Model\CustomerOrderItem;
use FidelsPizza\Domain\Repository\OrderRepositoryInterface;
use FidelsPizza\Domain\Service\OrderWindowService;
use FidelsPizza\Infrastructure\Security\CsrfManager;

final class CreateOrderUseCase
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly ManagePickupTimeSlotsUseCase $manageSlotsUseCase,
        private readonly OrderWindowService $orderWindowService,
        private readonly ?SendOrderNotificationUseCase $sendOrderNotificationUseCase = null,
        private readonly array $siteConfig = []
    ) {
    }

    public function execute(CreateOrderRequest $request): CreateOrderResult
    {
        // 1. Verify ordering deadline / window
        if (!$this->orderWindowService->isOrderingOpen($this->siteConfig)) {
            $msg = function_exists('__') ? (string)__('order_closed_error') : 'Ordering is currently closed.';
            return new CreateOrderResult(success: false, errorMessage: $msg);
        }

        // 2. Verify CSRF token
        if ($request->csrfToken !== null && !CsrfManager::verify($request->csrfToken)) {
            $msg = function_exists('__') ? (string)__('invalid_csrf_token') : 'Invalid security token.';
            return new CreateOrderResult(success: false, errorMessage: $msg);
        }

        if ($request->userId <= 0) {
            return new CreateOrderResult(success: false, errorMessage: 'User must be authenticated.');
        }

        // 3. Validate items and quantities
        $activeMenuItems = $this->orderRepository->getActiveMenuItems();
        $menuItemMap = [];
        foreach ($activeMenuItems as $item) {
            $menuItemMap[(int)$item['id']] = $item;
        }

        /** @var array<CustomerOrderItem> $items */
        $items = [];
        $totalAmount = 0.0;
        $totalPizzas = 0;

        foreach ($request->itemQuantities as $menuItemId => $rawQty) {
            $itemId = (int)$menuItemId;
            $qty = (int)$rawQty;

            if ($qty <= 0) {
                continue;
            }

            if ($qty > 15) {
                $qty = 15;
            }

            if (!isset($menuItemMap[$itemId])) {
                return new CreateOrderResult(success: false, errorMessage: "Selected menu item #$itemId does not exist.");
            }

            $menuItem = $menuItemMap[$itemId];
            $unitPrice = (float)$menuItem['price'];
            $subtotal = $qty * $unitPrice;

            $items[] = new CustomerOrderItem(
                id: null,
                menuItemId: $itemId,
                name: (string)$menuItem['name'],
                quantity: $qty,
                unitPrice: $unitPrice,
                subtotal: $subtotal
            );

            $totalAmount += $subtotal;
            $totalPizzas += $qty;
        }

        if (empty($items) || $totalPizzas <= 0) {
            $msg = function_exists('__') ? (string)__('select_at_least_one_pizza') : 'Please select at least one pizza.';
            if ($msg === 'select_at_least_one_pizza') {
                $msg = '注文するにはピザを1つ以上選択してください。';
            }
            return new CreateOrderResult(success: false, errorMessage: $msg);
        }

        // 4. Validate pickup time slot
        $eventDate = !empty($this->siteConfig['event_date']) ? (string)$this->siteConfig['event_date'] : date('Y-m-d');
        $availableSlots = $this->manageSlotsUseCase->getAvailableSlotsForOrder($eventDate);

        $pickupTime = null;
        $pickupSlotId = null;
        $pickupDisplay = null;

        if (!empty($availableSlots)) {
            $selectedSlotId = $request->pickupSlotId ?? 0;
            if ($selectedSlotId <= 0) {
                $msg = function_exists('__') ? (string)__('pickup_time_required') : 'Please select a pickup time.';
                return new CreateOrderResult(success: false, errorMessage: $msg);
            }

            try {
                $validatedSlot = $this->manageSlotsUseCase->validateSlotForOrder($selectedSlotId, $totalPizzas, $eventDate);
                $pickupTime = $validatedSlot->toDatetimeString($eventDate);
                $pickupSlotId = $validatedSlot->getId();
                $pickupDisplay = $validatedSlot->getDisplayName();
            } catch (\InvalidArgumentException $e) {
                return new CreateOrderResult(success: false, errorMessage: $e->getMessage());
            }
        } elseif (!empty($this->siteConfig['event_date'])) {
            $pickupTime = date('Y-m-d H:i:s', strtotime((string)$this->siteConfig['event_date'] . ' 12:30'));
            $pickupDisplay = '12:30';
        }

        // 5. Generate unique order number
        if (function_exists('generateOrderNumber')) {
            $orderNumber = generateOrderNumber();
        } else {
            $orderNumber = 'PZ' . date('Y') . str_pad((string)random_int(1, 9999), 4, '0', STR_PAD_LEFT);
        }

        $order = new CustomerOrder(
            id: null,
            userId: $request->userId,
            orderNumber: $orderNumber,
            totalAmount: $totalAmount,
            status: 'pending',
            notes: $request->notes,
            pickupTime: $pickupTime,
            pickupSlotId: $pickupSlotId,
            items: $items
        );

        // 6. Save order transactionally
        try {
            $orderId = $this->orderRepository->saveOrder($order);

            // 7. Dispatch notifications
            $emailSent = false;
            if ($this->sendOrderNotificationUseCase !== null) {
                $emailSent = $this->sendOrderNotificationUseCase->execute($orderId, 'created');
            }

            $userEmail = $this->orderRepository->getUserEmail($request->userId);

            return new CreateOrderResult(
                success: true,
                orderId: $orderId,
                orderNumber: $orderNumber,
                totalAmount: $totalAmount,
                pickupTime: $pickupTime,
                pickupDisplay: $pickupDisplay,
                emailSent: $emailSent,
                userEmail: $userEmail
            );
        } catch (\Throwable $e) {
            return new CreateOrderResult(
                success: false,
                errorMessage: '注文の送信に失敗しました。もう一度お試しください。'
            );
        }
    }
}
