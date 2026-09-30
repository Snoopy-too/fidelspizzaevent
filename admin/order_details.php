<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../helpers.php';
requireAdmin();

$container = getServiceContainer();
$db = $container->getPdo();
$manageSlotsUseCase = $container->getManagePickupTimeSlotsUseCase();

// Get site config
$stmt = $db->query("SELECT * FROM site_config LIMIT 1");
$config = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
$event_date = !empty($config['event_date']) ? (string)$config['event_date'] : date('Y-m-d');

// Get order ID
$order_id = (int)($_GET['id'] ?? 0);
if (!$order_id) {
    redirect('orders.php');
}

// Fetch order before handling POST to use its data
$stmt = $db->prepare("
    SELECT o.*, u.first_name, u.last_name, u.email
    FROM orders o
    JOIN users u ON o.user_id = u.id
    WHERE o.id = ?
");
$stmt->execute([$order_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    redirect('orders.php');
}

$allSlots = $manageSlotsUseCase->getAllSlots(1, $event_date);

// Handle order update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_order'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error_message = __('invalid_csrf_token') ?: 'Invalid CSRF token.';
    } else {
        $status = (string)($_POST['status'] ?? '');
        $quantities = isset($_POST['quantity']) && is_array($_POST['quantity']) ? $_POST['quantity'] : [];
        $pickup_time = trim((string)($_POST['pickup_time'] ?? ''));
        $notes = (string)($_POST['notes'] ?? '');

        $valid_statuses = ['pending', 'confirmed', 'preparing', 'ready', 'completed', 'cancelled'];
        if (!in_array($status, $valid_statuses, true)) {
            $error_message = __('invalid_order_status');
        } else {
            $db->beginTransaction();
            try {
                $matchedSlot = $manageSlotsUseCase->getSlotForOrder(null, $pickup_time !== '' ? $pickup_time : null, 1);
                $matchedSlotId = $matchedSlot?->getId();

                $colCheck = $db->query("SHOW COLUMNS FROM `orders` LIKE 'pickup_slot_id'");
                if ($colCheck && $colCheck->rowCount() > 0) {
                    $stmt = $db->prepare("UPDATE orders SET status = ?, pickup_time = ?, pickup_slot_id = ?, notes = ? WHERE id = ?");
                    $stmt->execute([$status, $pickup_time !== '' ? $pickup_time : null, $matchedSlotId, $notes, $order_id]);
                } else {
                    $stmt = $db->prepare("UPDATE orders SET status = ?, pickup_time = ?, notes = ? WHERE id = ?");
                    $stmt->execute([$status, $pickup_time !== '' ? $pickup_time : null, $notes, $order_id]);
                }

                $total = 0.0;
                $stmt = $db->prepare("SELECT oi.id, mi.price FROM order_items oi JOIN menu_items mi ON oi.menu_item_id = mi.id WHERE oi.order_id = ?");
                $stmt->execute([$order_id]);
                $item_prices = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

                foreach ($quantities as $item_id => $qty) {
                    $itemIdInt = (int)$item_id;
                    $qtyInt = max(0, (int)$qty);

                    if ($qtyInt === 0) {
                        $stmt = $db->prepare("DELETE FROM order_items WHERE id = ? AND order_id = ?");
                        $stmt->execute([$itemIdInt, $order_id]);
                    } else {
                        $unitPrice = isset($item_prices[$itemIdInt]) ? (float)$item_prices[$itemIdInt] : 0.0;
                        $subtotal = $unitPrice * $qtyInt;
                        $stmt = $db->prepare("UPDATE order_items SET quantity = ?, subtotal = ? WHERE id = ? AND order_id = ?");
                        $stmt->execute([$qtyInt, $subtotal, $itemIdInt, $order_id]);
                        $total += $subtotal;
                    }
                }

                $stmt = $db->prepare("UPDATE orders SET total_amount = ? WHERE id = ?");
                $stmt->execute([$total, $order_id]);

                $db->commit();
                header("Location: order_details.php?id=$order_id&success=1");
                exit;
            } catch (\Throwable $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                $error_message = __('order_update_failed') . ": " . $e->getMessage();
            }
        }
    }
}

// Re-fetch order and items after potential update to show fresh data
$stmt = $db->prepare("
    SELECT o.*, u.first_name, u.last_name, u.email
    FROM orders o
    JOIN users u ON o.user_id = u.id
    WHERE o.id = ?
");
$stmt->execute([$order_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $db->prepare("
    SELECT oi.*, mi.name, mi.price
    FROM order_items oi
    JOIN menu_items mi ON oi.menu_item_id = mi.id
    WHERE oi.order_id = ?
");
$stmt->execute([$order_id]);
$order_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Determine default pickup time
$default_pickup = !empty($order['pickup_time'])
    ? date('Y-m-d H:i', strtotime((string)$order['pickup_time']))
    : ($event_date ? $event_date . ' 12:00' : date('Y-m-d H:i'));

$page_title = __('order_details');
require_once __DIR__ . '/includes/header.php';
?>
<!-- Flatpickr CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<style>
.quick-slot-pills {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 8px;
    align-items: center;
}
.quick-slot-btn {
    padding: 4px 10px;
    border-radius: 999px;
    border: 1px solid #3498db;
    background: #ebf5fb;
    color: #2980b9;
    font-size: 0.82em;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s ease;
}
.quick-slot-btn:hover {
    background: #3498db;
    color: #fff;
}
</style>

        <div class="section">
            <h2><?= htmlspecialchars((string)__('order')) ?> #<?= htmlspecialchars((string)$order['order_number']) ?></h2>

            <?php if (isset($error_message)): ?>
                <div class="alert alert-error"><?= htmlspecialchars((string)$error_message) ?></div>
            <?php elseif (isset($_GET['success'])): ?>
                <div class="alert alert-success"><?= htmlspecialchars((string)__('order_update_success')) ?></div>
            <?php endif; ?>

            <div class="customer-info">
                <p><strong><?= htmlspecialchars((string)__('customer')) ?>:</strong> <?= htmlspecialchars((string)$order['first_name'] . ' ' . (string)$order['last_name']) ?></p>
                <p><strong><?= htmlspecialchars((string)__('email')) ?>:</strong> <?= htmlspecialchars((string)$order['email']) ?></p>
                <p><strong><?= htmlspecialchars((string)__('order_date')) ?>:</strong> <?= date('Y/m/d H:i', strtotime((string)$order['created_at'])) ?></p>
            </div>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken()) ?>">
                <h3><?= htmlspecialchars((string)__('edit_order_details')) ?></h3>
                <div class="form-grid">
                    <div>
                        <label for="status"><?= htmlspecialchars((string)__('order_status')) ?></label>
                        <select name="status" id="status">
                            <?php foreach (['pending','confirmed','preparing','ready','completed','cancelled'] as $status): ?>
                            <option value="<?= htmlspecialchars($status) ?>" <?= $order['status'] === $status ? 'selected' : '' ?>><?= htmlspecialchars((string)__('status_' . $status)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="pickup_time"><?= htmlspecialchars((string)__('pickup_time')) ?></label>
                        <input type="text" name="pickup_time" id="pickup_time" value="<?= htmlspecialchars($default_pickup) ?>" placeholder="<?= htmlspecialchars((string)__('select_date_time')) ?>">
                        <?php if (!empty($allSlots)): ?>
                            <div class="quick-slot-pills">
                                <small style="color: #64748b; font-weight: 600;"><?= htmlspecialchars((string)__('quick_select_slot')) ?></small>
                                <?php foreach ($allSlots as $slot): ?>
                                    <button type="button"
                                            class="quick-slot-btn"
                                            onclick="setOrderPickupSlot('<?= htmlspecialchars($event_date . ' ' . $slot->getSlotTime(), ENT_QUOTES) ?>')">
                                        <?= htmlspecialchars($slot->getDisplayName()) ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div>
                    <label for="notes"><?= htmlspecialchars((string)__('notes')) ?></label>
                    <textarea name="notes" id="notes" style="width: 100%; min-height: 100px; padding: 10px; border-radius: 5px; border: 1px solid #ccc; font-size: 1em;" placeholder="<?= htmlspecialchars((string)__('notes_placeholder')) ?>"><?= htmlspecialchars((string)($order['notes'] ?? '')) ?></textarea>
                </div>

                <h3><?= htmlspecialchars((string)__('items')) ?></h3>
                <?php if (empty($order_items)): ?>
                    <p><?= htmlspecialchars((string)__('no_items_in_order')) ?></p>
                <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th><?= htmlspecialchars((string)__('item_name')) ?></th>
                            <th><?= htmlspecialchars((string)__('quantity')) ?></th>
                            <th><?= htmlspecialchars((string)__('unit_price')) ?></th>
                            <th><?= htmlspecialchars((string)__('subtotal')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $total = 0.0;
                    foreach ($order_items as $item):
                        $subtotal = (float)$item['price'] * (int)$item['quantity'];
                        $total += $subtotal;
                    ?>
                    <tr>
                        <td><?= htmlspecialchars((string)$item['name']) ?></td>
                        <td><input type="number" name="quantity[<?= (int)$item['id'] ?>]" value="<?= (int)$item['quantity'] ?>" min="0"></td>
                        <td><?= htmlspecialchars(formatPrice($item['price'])) ?></td>
                        <td><?= htmlspecialchars(formatPrice($subtotal)) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr style="background-color: #f8f9fa;">
                        <td colspan="3" style="text-align: right; font-weight: bold; font-size: 1.1em;"><?= htmlspecialchars((string)__('total_amount')) ?></td>
                        <td style="font-weight: bold; font-size: 1.1em;"><?= htmlspecialchars(formatPrice($total)) ?></td>
                    </tr>
                    </tbody>
                </table>
                <?php endif; ?>

                <div style="margin-top: 30px;">
                    <button type="submit" name="update_order" class="btn btn-primary"><?= htmlspecialchars((string)__('save_changes')) ?></button>
                    <a href="orders.php" class="btn btn-secondary"><?= htmlspecialchars((string)__('back_to_orders')) ?></a>
                </div>
            </form>
        </div>

<!-- Flatpickr JS -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
const fpInstance = flatpickr("#pickup_time", {
    enableTime: true,
    dateFormat: "Y-m-d H:i",
    minDate: "<?= htmlspecialchars($event_date, ENT_QUOTES) ?>",
    time_24hr: true
});

function setOrderPickupSlot(dateTimeStr) {
    if (fpInstance) {
        fpInstance.setDate(dateTimeStr, true);
    } else {
        document.getElementById('pickup_time').value = dateTimeStr;
    }
}
</script>
<?php
require_once __DIR__ . '/includes/footer.php';