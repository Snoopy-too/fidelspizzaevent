<?php
declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';
require_once __DIR__ . '/helpers.php';

requireLogin();

$container = getServiceContainer();
$config = getSiteConfig();
$db = $container->getPdo();
$manageSlotsUseCase = $container->getManagePickupTimeSlotsUseCase();

$error_message = '';

if (isset($_GET['error'])) {
    $error_message = (string)__('error_update_failed');
}

if (!isset($_GET['order_id'])) {
    redirect('my_orders.php');
}

$order_id = (int)$_GET['order_id'];

// Fetch order and ensure it belongs to the logged-in user and is editable
$stmt = $db->prepare("SELECT * FROM orders WHERE id=? AND user_id=? AND status IN ('pending','confirmed')");
$stmt->execute([$order_id, $_SESSION['user_id']]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    redirect('my_orders.php');
}

$eventDate = !empty($config['event_date'])
    ? (string)$config['event_date']
    : (!empty($order['pickup_time']) ? date('Y-m-d', strtotime((string)$order['pickup_time'])) : date('Y-m-d'));

$availableSlots = $manageSlotsUseCase->getAvailableSlotsForOrder($eventDate, $order_id);
$currentSlot = $manageSlotsUseCase->getSlotForOrder(
    isset($order['pickup_slot_id']) ? (int)$order['pickup_slot_id'] : null,
    isset($order['pickup_time']) ? (string)$order['pickup_time'] : null
);

$selectedSlotId = isset($_POST['pickup_slot_id'])
    ? (int)$_POST['pickup_slot_id']
    : ($currentSlot?->getId() ?? 0);

if ($selectedSlotId === 0 && !empty($availableSlots)) {
    foreach ($availableSlots as $slot) {
        if (!$slot->isFull()) {
            $selectedSlotId = (int)$slot->getId();
            break;
        }
    }
}

// Fetch order items with menu item info
$stmt = $db->prepare("
    SELECT oi.id AS order_item_id, oi.quantity, mi.id AS menu_item_id, mi.name, mi.price
    FROM order_items oi
    JOIN menu_items mi ON oi.menu_item_id = mi.id
    WHERE oi.order_id=?
");
$stmt->execute([$order_id]);
$order_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission to update quantities and pickup time
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error_message = (string)__('invalid_csrf_token');
    } else {
        $db->beginTransaction();
        try {
            $total_amount = 0.0;
            $total_pizzas = 0;
            $has_items = false;
            $cancelled_lines = [];

            foreach ($order_items as $item) {
                $prev_qty = (int)$item['quantity'];
                $prev_sub = $prev_qty * (float)$item['price'];
                $cancelled_lines[] = "{$item['name']} x{$prev_qty} (¥" . number_format($prev_sub) . ")";

                $field_name = "quantity_{$item['order_item_id']}";
                $new_qty = max(0, min(15, (int)($_POST[$field_name] ?? $item['quantity'])));

                if ($new_qty > 0) {
                    $stmt = $db->prepare("UPDATE order_items SET quantity=?, subtotal=? WHERE id=? AND order_id=?");
                    $subtotal = $new_qty * (float)$item['price'];
                    $stmt->execute([$new_qty, $subtotal, $item['order_item_id'], $order_id]);
                    $total_amount += $subtotal;
                    $total_pizzas += $new_qty;
                    $has_items = true;
                } else {
                    $stmt = $db->prepare("DELETE FROM order_items WHERE id=? AND order_id=?");
                    $stmt->execute([$item['order_item_id'], $order_id]);
                }
            }

            if (!$has_items) {
                $stmt = $db->prepare("UPDATE orders SET total_amount=0, status='cancelled', updated_at=NOW() WHERE id=?");
                $stmt->execute([$order_id]);
            } else {
                $pickup_time = $order['pickup_time'] ?? null;
                $pickup_slot_id = $order['pickup_slot_id'] ?? null;

                if (!empty($availableSlots)) {
                    $validatedSlot = $manageSlotsUseCase->validateSlotForOrder(
                        $selectedSlotId,
                        $total_pizzas,
                        $eventDate,
                        $order_id
                    );
                    $pickup_time = $validatedSlot->toDatetimeString($eventDate);
                    $pickup_slot_id = $validatedSlot->getId();
                }

                $colCheck = $db->query("SHOW COLUMNS FROM `orders` LIKE 'pickup_slot_id'");
                if ($colCheck && $colCheck->rowCount() > 0) {
                    $stmt = $db->prepare("UPDATE orders SET total_amount=?, pickup_time=?, pickup_slot_id=?, updated_at=NOW() WHERE id=?");
                    $stmt->execute([$total_amount, $pickup_time, $pickup_slot_id, $order_id]);
                } else {
                    $stmt = $db->prepare("UPDATE orders SET total_amount=?, pickup_time=?, updated_at=NOW() WHERE id=?");
                    $stmt->execute([$total_amount, $pickup_time, $order_id]);
                }
            }

            $db->commit();

            $notificationUseCase = $container->getSendOrderNotificationUseCase();
            if ($has_items) {
                $notificationUseCase->execute($order_id, 'updated');
            } else {
                $notificationUseCase->execute($order_id, 'cancelled', implode("\n", $cancelled_lines));
            }

            redirect('my_orders.php');
        } catch (\InvalidArgumentException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $error_message = $e->getMessage();
            $availableSlots = $manageSlotsUseCase->getAvailableSlotsForOrder($eventDate, $order_id);
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            redirect('edit_order.php?order_id=' . $order_id . '&error=1');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars((string)($_SESSION['lang'] ?? 'ja')) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= sprintf(htmlspecialchars((string)__('edit_order_title')), htmlspecialchars((string)$order['order_number'])) ?> - <?= htmlspecialchars((string)($config['site_title'] ?? __('site_title'))) ?></title>
<style>
body { font-family:'Arial',sans-serif; background:#fff8f0; padding:20px; }
.container { max-width:800px; margin:0 auto; background:#fff; padding:30px; border-radius:15px; box-shadow:0 5px 20px rgba(0,0,0,0.1); }
h1 { color:#d32f2f; margin-bottom:20px; }
form table { width:100%; border-collapse:collapse; margin-bottom:20px; }
form th, form td { padding:12px; border-bottom:1px solid #ddd; text-align:left; }
input[type=number] { width:65px; padding:6px; border-radius: 5px; border: 1px solid #ccc; font-size: 1em; }
.pickup-edit-box {
    background: #fff8e1;
    border: 2px solid #ffb300;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 24px;
}
.pickup-edit-box h3 {
    margin: 0 0 12px 0;
    color: #e65100;
    font-size: 1.2em;
}
.pickup-slots-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 12px;
}
.pickup-slot-card {
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 14px 12px;
    background: #fff;
    border: 2px solid #e0e0e0;
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.2s ease;
    text-align: center;
}
.pickup-slot-card:hover:not(.disabled) {
    border-color: #ff9800;
}
.pickup-slot-card input[type="radio"] {
    position: absolute;
    opacity: 0;
    pointer-events: none;
}
.pickup-slot-card.selected {
    border-color: #d32f2f;
    background: #ffebee;
}
.pickup-slot-card.disabled {
    opacity: 0.55;
    background: #f5f5f5;
    cursor: not-allowed;
}
.slot-card-label {
    font-size: 0.9em;
    font-weight: bold;
    color: #555;
    margin-bottom: 2px;
}
.slot-card-time {
    font-size: 1.4em;
    font-weight: 800;
    color: #d32f2f;
    margin-bottom: 4px;
}
.slot-card-capacity {
    font-size: 0.78em;
    font-weight: bold;
    padding: 2px 8px;
    border-radius: 999px;
    background: #e8f5e9;
    color: #2e7d32;
}
.pickup-slot-card.disabled .slot-card-capacity {
    background: #ffcdd2;
    color: #c62828;
}
.btn { padding:12px 25px; background:#d32f2f; color:white; text-decoration:none; border-radius:25px; font-weight:bold; border:none; cursor:pointer; font-size: 1em; }
.btn:hover { background:#b71c1c; }
.nav-links { margin-bottom:20px; }
.nav-links a { margin-right:15px; color:#d32f2f; text-decoration:none; font-weight:bold; }
.nav-links a:hover { text-decoration:underline; }
.alert-error { padding: 15px; margin-bottom: 20px; border-radius: 8px; font-weight: bold; background: #ffebee; color: #c62828; border: 1px solid #e57373; }
</style>
</head>
<body>
<div class="container">
    <div class="nav-links">
        <a href="my_orders.php"><?= htmlspecialchars((string)__('back_to_history')) ?></a>
        <a href="menu.php"><?= htmlspecialchars((string)__('add_more_pizza')) ?></a>
    </div>
    <h1><?= sprintf(htmlspecialchars((string)__('edit_order_title')), htmlspecialchars((string)$order['order_number'])) ?></h1>

    <?php if ($error_message !== ''): ?>
        <div class="alert-error"><?= htmlspecialchars($error_message) ?></div>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken()) ?>">

        <?php if (!empty($availableSlots)): ?>
        <div class="pickup-edit-box">
            <h3>⏰ <?= htmlspecialchars((string)__('change_pickup_time')) ?></h3>
            <div class="pickup-slots-grid">
                <?php foreach ($availableSlots as $slot): ?>
                    <?php
                    $slotId = (int)$slot->getId();
                    $isFull = $slot->isFull();
                    $isSelected = !$isFull && ($selectedSlotId === $slotId);
                    $remPizzas = $slot->getRemainingPizzas();
                    $remOrders = $slot->getRemainingOrders();
                    ?>
                    <label class="pickup-slot-card <?= $isSelected ? 'selected' : '' ?> <?= $isFull ? 'disabled' : '' ?>"
                           id="slot_card_<?= $slotId ?>"
                           onclick="selectEditSlot(<?= $slotId ?>)">
                        <input type="radio"
                               name="pickup_slot_id"
                               id="pickup_slot_<?= $slotId ?>"
                               value="<?= $slotId ?>"
                               <?= $isSelected ? 'checked' : '' ?>
                               <?= $isFull ? 'disabled' : '' ?>>
                        <?php if ($slot->getLabel() !== ''): ?>
                            <span class="slot-card-label"><?= htmlspecialchars($slot->getLabel()) ?></span>
                        <?php endif; ?>
                        <span class="slot-card-time"><?= htmlspecialchars($slot->getTimeRange()) ?></span>
                        <?php if ($isFull): ?>
                            <span class="slot-card-capacity"><?= htmlspecialchars((string)__('slot_full_badge')) ?></span>
                        <?php elseif ($remPizzas !== null): ?>
                            <span class="slot-card-capacity"><?= htmlspecialchars(sprintf((string)__('slot_remaining_pizzas'), $remPizzas)) ?></span>
                        <?php elseif ($remOrders !== null): ?>
                            <span class="slot-card-capacity"><?= htmlspecialchars(sprintf((string)__('slot_remaining_orders'), $remOrders)) ?></span>
                        <?php endif; ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <table>
            <thead>
                <tr>
                    <th><?= htmlspecialchars((string)__('pizza_column')) ?></th>
                    <th><?= htmlspecialchars((string)__('unit_price_column')) ?></th>
                    <th><?= htmlspecialchars((string)__('quantity')) ?></th>
                    <th><?= htmlspecialchars((string)__('subtotal_column')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php
                $total = 0.0;
                foreach ($order_items as $item):
                    $currentQty = isset($_POST['quantity_' . $item['order_item_id']])
                        ? max(0, min(15, (int)$_POST['quantity_' . $item['order_item_id']]))
                        : (int)$item['quantity'];
                    $subtotal = $currentQty * (float)$item['price'];
                    $total += $subtotal;
                ?>
                <tr>
                    <td><?= htmlspecialchars((string)$item['name']) ?></td>
                    <td><?= htmlspecialchars(formatPrice($item['price'])) ?></td>
                    <td>
                        <input type="number" name="quantity_<?= (int)$item['order_item_id'] ?>" value="<?= $currentQty ?>" min="0" max="15">
                    </td>
                    <td><?= htmlspecialchars(formatPrice($subtotal)) ?></td>
                </tr>
                <?php endforeach; ?>
                <tr style="background-color: #f9f9f9;">
                    <td colspan="3" style="text-align:right; font-weight:bold;"><?= htmlspecialchars((string)__('total_label')) ?></td>
                    <td style="font-weight:bold;"><?= htmlspecialchars(formatPrice($total)) ?></td>
                </tr>
            </tbody>
        </table>
        <button type="submit" class="btn"><?= htmlspecialchars((string)__('update_order_button')) ?></button>
    </form>
</div>
<script>
function selectEditSlot(slotId) {
    const radio = document.getElementById('pickup_slot_' + slotId);
    if (!radio || radio.disabled) {
        return;
    }
    radio.checked = true;
    document.querySelectorAll('.pickup-slot-card').forEach(card => {
        card.classList.remove('selected');
    });
    const card = document.getElementById('slot_card_' + slotId);
    if (card) {
        card.classList.add('selected');
    }
}
</script>
</body>
</html>