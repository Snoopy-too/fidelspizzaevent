<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../src/autoload.php';

use FidelsPizza\Application\DTO\CreateCompedOrderRequest;
use FidelsPizza\Application\UseCase\CreateCompedOrderUseCase;
use FidelsPizza\Application\UseCase\GetCompedOrdersUseCase;
use FidelsPizza\Domain\Model\CompedOrder;
use FidelsPizza\Domain\Model\CompedOrderItem;
use FidelsPizza\Infrastructure\Persistence\PdoCompedOrderRepository;

echo "===========================================\n";
echo "COMP'D PIZZAS FEATURE VERIFICATION\n";
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

// 1. Establish database connection (SQLite in-memory test environment to isolate test execution)
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

// Define test schema
$pdo->exec("
    CREATE TABLE admins (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL,
        password_hash TEXT NOT NULL,
        email TEXT NOT NULL
    );

    CREATE TABLE users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        email TEXT NOT NULL UNIQUE,
        password_hash TEXT NOT NULL,
        first_name TEXT NOT NULL,
        last_name TEXT NOT NULL,
        phone TEXT DEFAULT '',
        is_confirmed INTEGER DEFAULT 1,
        accepts_marketing INTEGER DEFAULT 0,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE menu_items (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        price REAL NOT NULL,
        image_path TEXT DEFAULT NULL,
        is_active INTEGER DEFAULT 1,
        sort_order INTEGER DEFAULT 0
    );

    CREATE TABLE orders (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        order_number TEXT NOT NULL UNIQUE,
        total_amount REAL NOT NULL,
        status TEXT NOT NULL DEFAULT 'pending',
        notes TEXT DEFAULT NULL,
        pickup_time TEXT DEFAULT NULL,
        pickup_slot_id INTEGER DEFAULT NULL,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE order_items (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        order_id INTEGER NOT NULL,
        menu_item_id INTEGER NOT NULL,
        quantity INTEGER NOT NULL,
        unit_price REAL NOT NULL,
        subtotal REAL NOT NULL
    );

    CREATE TABLE pickup_time_slots (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        config_id INTEGER DEFAULT 1,
        label TEXT DEFAULT '',
        slot_time TEXT NOT NULL,
        end_time TEXT DEFAULT '12:30:00',
        max_pizzas INTEGER DEFAULT NULL,
        max_orders INTEGER DEFAULT NULL,
        is_active INTEGER DEFAULT 1,
        sort_order INTEGER DEFAULT 0
    );
");

// Seed test fixtures
$pdo->exec("
    INSERT INTO admins (id, username, password_hash, email) VALUES (1, 'FidelAdmin', 'hash', 'fidel@test.com');
    INSERT INTO menu_items (id, name, price, is_active, sort_order) VALUES (1, 'Margherita Pizza', 1500.0, 1, 1);
    INSERT INTO menu_items (id, name, price, is_active, sort_order) VALUES (2, 'Pepperoni Pizza', 1500.0, 1, 2);
    INSERT INTO menu_items (id, name, price, is_active, sort_order) VALUES (3, 'Cheese Pizza', 1500.0, 1, 3);
    INSERT INTO pickup_time_slots (id, label, slot_time, end_time) VALUES (1, 'Pickup Slot A', '11:30:00', '12:30:00');
");

$repo = new PdoCompedOrderRepository($pdo);
$createUseCase = new CreateCompedOrderUseCase($repo, null, ['event_date' => '2026-11-02']);
$getUseCase = new GetCompedOrdersUseCase($repo);

// Test 1: Validation - Disallow empty quantities
$emptyRequest = new CreateCompedOrderRequest(
    adminId: 1,
    itemQuantities: [1 => 0, 2 => 0],
    notes: 'Zero quantity test'
);
$resEmpty = $createUseCase->execute($emptyRequest);
assertTrue($resEmpty->success === false, "Validation fails if total pizzas selected is 0");

// Test 2: Successful comped order creation
$validRequest = new CreateCompedOrderRequest(
    adminId: 1,
    itemQuantities: [
        1 => 2, // 2 Margherita
        2 => 3  // 3 Pepperoni
    ],
    notes: 'Volunteers and event oven assistants',
    pickupSlotId: 1
);
$resValid = $createUseCase->execute($validRequest);
assertTrue($resValid->success === true, "Comped order successfully created");
assertTrue($resValid->totalPizzas === 5, "Total pizzas correctly identified as 5");
assertTrue(!empty($resValid->orderNumber), "Unique order number generated: " . ($resValid->orderNumber ?? ''));

// Test 3: Database row verification for 0 yen total and 'comped' status
$orderRow = $pdo->query("SELECT * FROM orders WHERE id = " . (int)$resValid->orderId)->fetch(PDO::FETCH_ASSOC);
assertTrue($orderRow['status'] === 'comped', "Order status is strictly 'comped'");
assertTrue((float)$orderRow['total_amount'] === 0.0, "Total amount is 0.00 yen");
assertTrue($orderRow['notes'] === 'Volunteers and event oven assistants', "Notes preserved");

// Test 4: Line items unit price and subtotal are 0 yen
$itemRows = $pdo->query("SELECT * FROM order_items WHERE order_id = " . (int)$resValid->orderId)->fetchAll(PDO::FETCH_ASSOC);
assertTrue(count($itemRows) === 2, "2 distinct menu items saved in order_items");
$allZero = true;
$totalQty = 0;
foreach ($itemRows as $row) {
    if ((float)$row['unit_price'] !== 0.0 || (float)$row['subtotal'] !== 0.0) {
        $allZero = false;
    }
    $totalQty += (int)$row['quantity'];
}
assertTrue($allZero, "All order items have unit_price = 0 and subtotal = 0");
assertTrue($totalQty === 5, "Sum of order_items quantities equals 5");

// Test 5: Customer identity is the admin user in users table
$userRow = $pdo->query("SELECT * FROM users WHERE id = " . (int)$orderRow['user_id'])->fetch(PDO::FETCH_ASSOC);
assertTrue($userRow['email'] === 'fidel@test.com', "User record email matches admin email fidel@test.com");
assertTrue($userRow['first_name'] === 'FidelAdmin', "User record first name matches admin username FidelAdmin");

// Test 6: Summary use case metrics
$summary = $getUseCase->getSummary();
assertTrue($summary['total_comped_pizzas'] === 5, "Summary reports 5 comped pizzas");
assertTrue($summary['total_comped_orders'] === 1, "Summary reports 1 comped order");
assertTrue(($summary['items_breakdown']['Pepperoni Pizza'] ?? 0) === 3, "Breakdown: 3 Pepperoni");
assertTrue(($summary['items_breakdown']['Margherita Pizza'] ?? 0) === 2, "Breakdown: 2 Margherita");

// Test 7: Integration - Required Ingredients counts comped pizzas, but Total Orders excludes comped orders
// Add a customer order for 2 Cheese Pizzas at 3000 yen
$pdo->exec("
    INSERT INTO users (id, email, password_hash, first_name, last_name) VALUES (2, 'customer@test.com', 'h', 'Customer', 'One');
    INSERT INTO orders (id, user_id, order_number, total_amount, status, pickup_time) VALUES (100, 2, 'PZ20261001', 3000.0, 'confirmed', '2026-11-02 12:00:00');
    INSERT INTO order_items (order_id, menu_item_id, quantity, unit_price, subtotal) VALUES (100, 3, 2, 1500.0, 3000.0);
");

// Total Orders query (excluding comped and cancelled):
$orderCountStmt = $pdo->query("
    SELECT COUNT(DISTINCT CASE WHEN status != 'comped' THEN id END) as customer_orders_count,
           SUM(total_amount) as total_revenue
    FROM orders
    WHERE status != 'cancelled'
");
$stats = $orderCountStmt->fetch(PDO::FETCH_ASSOC);
assertTrue((int)$stats['customer_orders_count'] === 1, "Total Orders count is 1 (comped order is NOT counted in Total Orders)");
assertTrue((float)$stats['total_revenue'] === 3000.0, "Total Revenue is 3000.0 yen (comped order does not add to revenue)");

// Required Ingredients query (includes comped pizzas because status != 'cancelled'):
$ingStmt = $pdo->query("
    SELECT mi.name, SUM(oi.quantity) as total_quantity
    FROM order_items oi
    JOIN menu_items mi ON oi.menu_item_id = mi.id
    JOIN orders o ON oi.order_id = o.id
    WHERE o.status != 'cancelled'
    GROUP BY mi.id, mi.name
");
$allItemTotals = $ingStmt->fetchAll(PDO::FETCH_ASSOC);
$totalPizzasToPrep = 0;
foreach ($allItemTotals as $it) {
    $totalPizzasToPrep += (int)$it['total_quantity'];
}
// 5 comped + 2 customer = 7 total pizzas to prep
assertTrue($totalPizzasToPrep === 7, "Required Ingredients counts ALL 7 pizzas (5 comped + 2 customer pizzas)");

// Test 8: Status translation helper works for 'comped'
$translatedCompedJa = translateStatus('comped');
assertTrue(!empty($translatedCompedJa), "translateStatus('comped') returns localized label: $translatedCompedJa");

// Test 9: Unscheduled buffer comped order (pickupSlotId = null)
$bufferRequest = new CreateCompedOrderRequest(
    adminId: 1,
    itemQuantities: [
        3 => 4 // 4 Cheese Pizzas buffer
    ],
    notes: 'General event buffer dough',
    pickupSlotId: null
);
$resBuffer = $createUseCase->execute($bufferRequest);
assertTrue($resBuffer->success === true, "Unscheduled buffer comped order successfully created");
assertTrue($resBuffer->totalPizzas === 4, "Buffer order quantity is 4 pizzas");

$bufferOrderRow = $pdo->query("SELECT * FROM orders WHERE id = " . (int)$resBuffer->orderId)->fetch(PDO::FETCH_ASSOC);
assertTrue($bufferOrderRow['pickup_time'] === null, "Buffer order pickup_time is NULL (unscheduled)");
assertTrue($bufferOrderRow['pickup_slot_id'] === null, "Buffer order pickup_slot_id is NULL");

// Test 10: Date-filtered summary includes both scheduled (5) and unscheduled (4) comped orders = 9 total
$dateSummary = $getUseCase->getSummary('2026-11-02');
assertTrue($dateSummary['total_comped_pizzas'] === 9, "Date-filtered getSummary('2026-11-02') includes both scheduled and unscheduled buffer orders (5 + 4 = 9 pizzas)");
assertTrue($dateSummary['total_comped_orders'] === 2, "Date-filtered getSummary reports 2 comped orders");

// Test 11: Reports page event query math matches all 9 comped pizzas
$is_active_or_latest = true;
$selected_event = '2026-11-02';
$eventDateCondition = $is_active_or_latest
    ? "(DATE(o.pickup_time) = ? OR (o.pickup_time IS NULL AND o.status = 'comped'))"
    : "DATE(o.pickup_time) = ?";
$compedCondition = $is_active_or_latest
    ? "(DATE(o.pickup_time) = ? OR o.pickup_time IS NULL)"
    : "DATE(o.pickup_time) = ?";

// Comped pizzas count for event:
$compedReportStmt = $pdo->prepare("
    SELECT COALESCE(SUM(oi.quantity), 0) as comped_qty
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    WHERE $compedCondition
      AND o.status = 'comped'
");
$compedReportStmt->execute([$selected_event]);
$reportCompedPizzas = (int)$compedReportStmt->fetchColumn();
assertTrue($reportCompedPizzas === 9, "Reports page event query correctly counts 9 comped pizzas (4 buffer + 5 scheduled)");

// Required ingredients prep total for event:
$eventItemStmt = $pdo->prepare("
    SELECT mi.name, SUM(oi.quantity) as total_quantity
    FROM order_items oi
    JOIN menu_items mi ON oi.menu_item_id = mi.id
    JOIN orders o ON oi.order_id = o.id
    WHERE $eventDateCondition
      AND o.status != 'cancelled'
    GROUP BY mi.id, mi.name
");
$eventItemStmt->execute([$selected_event]);
$eventItemTotals = $eventItemStmt->fetchAll(PDO::FETCH_ASSOC);
$eventTotalPrep = 0;
foreach ($eventItemTotals as $row) {
    $eventTotalPrep += (int)$row['total_quantity'];
}
// 9 comped + 2 customer = 11 total pizzas
assertTrue($eventTotalPrep === 11, "Event Required Ingredients counts all 11 pizzas (9 comped + 2 customer pizzas)");

// Customer orders count for event remains 1, revenue remains 3000
$eventSumStmt = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT CASE WHEN status != 'comped' THEN id END) as order_count, 
        SUM(total_amount) as total_revenue
    FROM orders
    WHERE DATE(pickup_time) = ?
      AND status != 'cancelled'
");
$eventSumStmt->execute([$selected_event]);
$eventSum = $eventSumStmt->fetch(PDO::FETCH_ASSOC);
assertTrue((int)$eventSum['order_count'] === 1, "Reports page customer order count is strictly 1");
assertTrue((float)$eventSum['total_revenue'] === 3000.0, "Reports page revenue is strictly ¥3,000");

echo "\n===========================================\n";
echo "SUMMARY: $testsPassed passed, $testsFailed failed\n";
echo "===========================================\n";

if ($testsFailed > 0) {
    exit(1);
}
