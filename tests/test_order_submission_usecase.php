<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../helpers.php';

use FidelsPizza\Application\DTO\CreateOrderRequest;
use FidelsPizza\Application\DTO\UpdateOrderRequest;
use FidelsPizza\Application\UseCase\CreateOrderUseCase;
use FidelsPizza\Application\UseCase\ManagePickupTimeSlotsUseCase;
use FidelsPizza\Application\UseCase\UpdateOrderUseCase;
use FidelsPizza\Domain\Model\CustomerOrder;
use FidelsPizza\Domain\Model\PickupTimeSlot;
use FidelsPizza\Domain\Repository\OrderRepositoryInterface;
use FidelsPizza\Domain\Repository\PickupTimeSlotRepositoryInterface;
use FidelsPizza\Domain\Service\OrderWindowService;
use FidelsPizza\Domain\Service\TextFormatter;
use FidelsPizza\Infrastructure\Security\CsrfManager;

echo "===========================================\n";
echo "ORDER SUBMISSION & UPDATE USECASE VERIFICATION\n";
echo "===========================================\n\n";

$testsPassed = 0;
$testsFailed = 0;

function assertTrue(bool $condition, string $message): void {
    global $testsPassed, $testsFailed;
    if ($condition) {
        echo " [PASS] $message\n";
        $testsPassed++;
    } else {
        echo " [FAIL] $message\n";
        $testsFailed++;
    }
}

// 1. TextFormatter verification
assertTrue(TextFormatter::formatPrice(1500) === '¥1,500', "TextFormatter formats integer price to Japanese Yen");
assertTrue(TextFormatter::formatPrice(0) === '¥0', "TextFormatter formats 0 price");
assertTrue(TextFormatter::sanitize('<script>alert("xss")</script>') === '&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;', "TextFormatter sanitizes HTML tags");
$interpolated = TextFormatter::replacePlaceholders('Hello {{name}}!', ['name' => 'World']);
assertTrue($interpolated === 'Hello World!', "TextFormatter replaces template placeholders");

// 2. CsrfManager verification
$token = CsrfManager::getToken();
assertTrue(strlen($token) === 64, "CsrfManager generates 64-character hex token");
assertTrue(CsrfManager::verify($token) === true, "CsrfManager verifies valid token using hash_equals");
assertTrue(CsrfManager::verify('invalid_token') === false, "CsrfManager rejects invalid token");
assertTrue(CsrfManager::verify(null) === false, "CsrfManager rejects null token");

// 3. Mock in-memory Order Repository
class InMemoryOrderRepository implements OrderRepositoryInterface
{
    public array $menuItems = [
        ['id' => 1, 'name' => 'Margherita Pizza', 'price' => 1500.00, 'is_active' => 1, 'sort_order' => 1],
        ['id' => 2, 'name' => 'Pepperoni Pizza', 'price' => 1600.00, 'is_active' => 1, 'sort_order' => 2],
    ];

    /** @var array<int, array<string, mixed>> */
    public array $orders = [];
    /** @var array<int, array<string, mixed>> */
    public array $orderItems = [];
    public int $nextOrderId = 1;
    public int $nextItemId = 1;

    public function getActiveMenuItems(): array
    {
        return $this->menuItems;
    }

    public function findOrderByIdAndUserId(int $orderId, int $userId): ?array
    {
        if (isset($this->orders[$orderId])
            && $this->orders[$orderId]['user_id'] === $userId
            && in_array($this->orders[$orderId]['status'], ['pending', 'confirmed'], true)) {
            return $this->orders[$orderId];
        }
        return null;
    }

    public function findOrderItemsByOrderId(int $orderId): array
    {
        $items = [];
        foreach ($this->orderItems as $item) {
            if ($item['order_id'] === $orderId) {
                $items[] = $item;
            }
        }
        return $items;
    }

    public function getUserEmail(int $userId): ?string
    {
        return 'customer' . $userId . '@example.com';
    }

    public function saveOrder(CustomerOrder $order): int
    {
        $id = $this->nextOrderId++;
        $this->orders[$id] = [
            'id' => $id,
            'user_id' => $order->getUserId(),
            'order_number' => $order->getOrderNumber(),
            'total_amount' => $order->getTotalAmount(),
            'status' => $order->getStatus(),
            'notes' => $order->getNotes(),
            'pickup_time' => $order->getPickupTime(),
            'pickup_slot_id' => $order->getPickupSlotId(),
        ];

        foreach ($order->getItems() as $item) {
            $itemId = $this->nextItemId++;
            $this->orderItems[$itemId] = [
                'order_item_id' => $itemId,
                'order_id' => $id,
                'menu_item_id' => $item->getMenuItemId(),
                'name' => $item->getName(),
                'quantity' => $item->getQuantity(),
                'unit_price' => $item->getUnitPrice(),
                'price' => $item->getUnitPrice(),
                'subtotal' => $item->getSubtotal(),
            ];
        }

        return $id;
    }

    public function updateOrder(
        int $orderId,
        float $totalAmount,
        ?string $pickupTime,
        ?int $pickupSlotId,
        array $itemsToUpdate,
        array $itemsToDelete
    ): void {
        if (isset($this->orders[$orderId])) {
            $this->orders[$orderId]['total_amount'] = $totalAmount;
            $this->orders[$orderId]['pickup_time'] = $pickupTime;
            $this->orders[$orderId]['pickup_slot_id'] = $pickupSlotId;
        }

        foreach ($itemsToDelete as $delId) {
            unset($this->orderItems[$delId]);
        }

        foreach ($itemsToUpdate as $upd) {
            $id = $upd['order_item_id'];
            if (isset($this->orderItems[$id])) {
                $this->orderItems[$id]['quantity'] = $upd['quantity'];
                $this->orderItems[$id]['subtotal'] = $upd['subtotal'];
            }
        }
    }

    public function cancelOrder(int $orderId): void
    {
        if (isset($this->orders[$orderId])) {
            $this->orders[$orderId]['status'] = 'cancelled';
            $this->orders[$orderId]['total_amount'] = 0.0;
        }
    }
}

// Mock PickupSlotRepository
class InMemoryPickupSlotRepository implements PickupTimeSlotRepositoryInterface
{
    /** @var array<int, PickupTimeSlot> */
    public array $slots = [];

    public function findAll(int $configId = 1, ?string $eventDate = null, ?int $excludeOrderId = null): array
    {
        return array_values($this->slots);
    }

    public function findActiveWithAvailability(int $configId = 1, ?string $eventDate = null, ?int $excludeOrderId = null): array
    {
        return array_values($this->slots);
    }

    public function findById(int $id, ?string $eventDate = null, ?int $excludeOrderId = null): ?PickupTimeSlot
    {
        return $this->slots[$id] ?? null;
    }

    public function findForOrder(?int $slotId, ?string $pickupDatetime, int $configId = 1): ?PickupTimeSlot
    {
        return $slotId !== null ? ($this->slots[$slotId] ?? null) : null;
    }

    public function syncSlots(int $configId, array $slots): void {}
}

$mockOrderRepo = new InMemoryOrderRepository();
$mockSlotRepo = new InMemoryPickupSlotRepository();
$mockSlotRepo->slots[1] = new PickupTimeSlot(
    id: 1,
    configId: 1,
    label: '12:00 - 12:30',
    slotTime: '12:00:00',
    endTime: '12:30:00',
    maxPizzas: 20,
    maxOrders: 10,
    isActive: true,
    sortOrder: 1,
    bookedPizzas: 0,
    bookedOrders: 0
);

$manageSlotsUseCase = new ManagePickupTimeSlotsUseCase($mockSlotRepo);
$orderWindowService = new OrderWindowService();

$openConfig = [
    'event_date' => date('Y-m-d', strtotime('+7 days')),
    'order_deadline' => date('Y-m-d H:i:s', strtotime('+6 days')),
    'order_status_override' => 'auto',
];

$closedConfig = [
    'event_date' => date('Y-m-d', strtotime('+7 days')),
    'order_deadline' => date('Y-m-d H:i:s', strtotime('-1 day')),
    'order_status_override' => 'auto',
];

$createUseCaseOpen = new CreateOrderUseCase(
    $mockOrderRepo,
    $manageSlotsUseCase,
    $orderWindowService,
    null,
    $openConfig
);

$createUseCaseClosed = new CreateOrderUseCase(
    $mockOrderRepo,
    $manageSlotsUseCase,
    $orderWindowService,
    null,
    $closedConfig
);

// Test 1: Order blocked when order window closed
$req = new CreateOrderRequest(userId: 1, itemQuantities: [1 => 2], pickupSlotId: 1, csrfToken: $token);
$res = $createUseCaseClosed->execute($req);
assertTrue(!$res->success, "Order submission rejected when ordering deadline has passed");

// Test 2: Order rejected when CSRF token is invalid
$reqBadCsrf = new CreateOrderRequest(userId: 1, itemQuantities: [1 => 2], pickupSlotId: 1, csrfToken: 'fake_token');
$resBadCsrf = $createUseCaseOpen->execute($reqBadCsrf);
assertTrue(!$resBadCsrf->success, "Order submission rejected when CSRF token is invalid");

// Test 3: Order rejected when 0 pizzas selected
$reqZero = new CreateOrderRequest(userId: 1, itemQuantities: [1 => 0], pickupSlotId: 1, csrfToken: $token);
$resZero = $createUseCaseOpen->execute($reqZero);
assertTrue(!$resZero->success, "Order submission rejected when 0 pizzas are selected");

// Test 4: Successful order creation
$reqValid = new CreateOrderRequest(userId: 5, itemQuantities: [1 => 2, 2 => 1], pickupSlotId: 1, csrfToken: $token);
$resValid = $createUseCaseOpen->execute($reqValid);
assertTrue($resValid->success === true, "Valid order creation succeeds");
assertTrue($resValid->orderId !== null && $resValid->orderId > 0, "Generated orderId returned");
assertTrue(str_starts_with((string)$resValid->orderNumber, 'PZ'), "Order number starts with PZ prefix");
assertTrue($resValid->totalAmount === 4600.0, "Total amount accurately calculated: (2 x 1500) + (1 x 1600) = 4600");
assertTrue(str_contains((string)$resValid->pickupTime, '12:00:00'), "Pickup time matches selected slot time");

// Test 5: Update Order UseCase
$updateUseCase = new UpdateOrderUseCase(
    $mockOrderRepo,
    $manageSlotsUseCase,
    null,
    $openConfig
);

// Fetch line item IDs for order 1
$items = $mockOrderRepo->findOrderItemsByOrderId($resValid->orderId);
assertTrue(count($items) === 2, "2 order items persisted in repository");

$itemId1 = (int)$items[0]['order_item_id'];
$itemId2 = (int)$items[1]['order_item_id'];

// Update quantities: change item1 to 3 pizzas, item2 to 0 (delete)
$updateReq = new UpdateOrderRequest(
    orderId: $resValid->orderId,
    userId: 5,
    itemQuantities: [$itemId1 => 3, $itemId2 => 0],
    pickupSlotId: 1,
    csrfToken: $token
);

$updateRes = $updateUseCase->execute($updateReq);
assertTrue($updateRes->success === true, "Order update succeeds");
assertTrue($updateRes->isCancelled === false, "Order is not cancelled");
assertTrue($updateRes->totalAmount === 4500.0, "Total amount updated to 3 x 1500 = 4500");

// Verify repository state
$updatedItems = $mockOrderRepo->findOrderItemsByOrderId($resValid->orderId);
assertTrue(count($updatedItems) === 1, "Deleted 0-qty item removed from order items");
assertTrue((int)$updatedItems[0]['quantity'] === 3, "Updated quantity persisted as 3");

// Test 6: Auto-cancel when all quantities are 0
$cancelReq = new UpdateOrderRequest(
    orderId: $resValid->orderId,
    userId: 5,
    itemQuantities: [$itemId1 => 0],
    pickupSlotId: 1,
    csrfToken: $token
);
$cancelRes = $updateUseCase->execute($cancelReq);
assertTrue($cancelRes->success === true, "Zero quantity update completes");
assertTrue($cancelRes->isCancelled === true, "Order automatically marked as cancelled when all items removed");
assertTrue($cancelRes->totalAmount === 0.0, "Total amount zeroed out on cancellation");

$persistedOrder = $mockOrderRepo->findOrderByIdAndUserId($resValid->orderId, 5);
// findOrderByIdAndUserId filters status IN ('pending', 'confirmed'), so cancelled order is no longer returned
assertTrue($persistedOrder === null, "Cancelled order no longer returned in editable pending/confirmed queries");
assertTrue($mockOrderRepo->orders[$resValid->orderId]['status'] === 'cancelled', "Persisted order status is strictly 'cancelled'");

// Test 7: Unauthorized user cannot edit someone else's order
$tamperReq = new UpdateOrderRequest(
    orderId: $resValid->orderId,
    userId: 999, // Wrong user
    itemQuantities: [$itemId1 => 1],
    pickupSlotId: 1,
    csrfToken: $token
);
$tamperRes = $updateUseCase->execute($tamperReq);
assertTrue(!$tamperRes->success, "Unauthorized user cannot modify another user's order");

echo "\n===========================================\n";
echo "SUMMARY: $testsPassed passed, $testsFailed failed\n";
echo "===========================================\n";

if ($testsFailed > 0) {
    exit(1);
}
