<?php
require_once '../config.php';
require_once __DIR__ . '/../helpers.php';
requireAdmin();

$db = getDB();

// Get site config
$stmt = $db->query("SELECT * FROM site_config LIMIT 1");
$config = $stmt->fetch();
$event_date = $config['event_date'] ?? date('Y-m-d'); // fallback to today

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
$order = $stmt->fetch();

if (!$order) {
    redirect('orders.php');
}

// Handle order update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_order'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error_message = __('invalid_csrf_token') ?: 'Invalid CSRF token.';
    } else {
        $status = $_POST['status'] ?? '';
    $quantities = $_POST['quantity'] ?? [];
    $pickup_time = $_POST['pickup_time'] ?? null;
    $notes = $_POST['notes'] ?? '';

    $valid_statuses = ['pending','confirmed','preparing','ready','completed','cancelled'];
    if (!in_array($status, $valid_statuses)) {
        $error_message = __('invalid_order_status');
    } else {
        $db->beginTransaction();
        try {
            $stmt = $db->prepare("UPDATE orders SET status = ?, pickup_time = ?, notes = ? WHERE id = ?");
            $stmt->execute([$status, $pickup_time ?: null, $notes, $order_id]);

            $total = 0;
            // Fetch prices for all items in the order once
            $stmt = $db->prepare("SELECT oi.id, mi.price FROM order_items oi JOIN menu_items mi ON oi.menu_item_id = mi.id WHERE oi.order_id = ?");
            $stmt->execute([$order_id]);
            $item_prices = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

            foreach ($quantities as $item_id => $qty) {
                $qty = (int)$qty;
                if ($qty < 0) $qty = 0;

                if ($qty === 0) {
                    $stmt = $db->prepare("DELETE FROM order_items WHERE id = ?");
                    $stmt->execute([$item_id]);
                } else {
                    $stmt = $db->prepare("UPDATE order_items SET quantity = ? WHERE id = ?");
                    $stmt->execute([$qty, $item_id]);

                    if (isset($item_prices[$item_id])) {
                        $total += $item_prices[$item_id] * $qty;
                    }
                }
            }

            $stmt = $db->prepare("UPDATE orders SET total_amount = ? WHERE id = ?");
            $stmt->execute([$total, $order_id]);

            $db->commit();
            header("Location: order_details.php?id=$order_id&success=1");
            exit;
        } catch (Exception $e) {
            $db->rollBack();
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
$order = $stmt->fetch();

$stmt = $db->prepare("
    SELECT oi.*, mi.name, mi.price
    FROM order_items oi
    JOIN menu_items mi ON oi.menu_item_id = mi.id
    WHERE oi.order_id = ?
");
$stmt->execute([$order_id]);
$order_items = $stmt->fetchAll();

// Determine default pickup time
$default_pickup = $order['pickup_time'] 
    ? date('Y-m-d H:i', strtotime($order['pickup_time'])) 
    : ($event_date ? $event_date . ' 12:00' : date('Y-m-d H:i'));
$page_title = __('order_details');
require_once __DIR__ . '/includes/header.php';
?>
<!-- Flatpickr CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

        <div class="section">
            <h2><?= __('order') ?> #<?= htmlspecialchars($order['order_number']) ?></h2>
            
            <?php if (isset($error_message)): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error_message) ?></div>
            <?php elseif (isset($_GET['success'])): ?>
                <div class="alert alert-success"><?= __('order_update_success') ?></div>
            <?php endif; ?>

            <div class="customer-info">
                <p><strong><?= __('customer') ?>:</strong> <?= htmlspecialchars($order['first_name'] . ' ' . $order['last_name']) ?></p>
                <p><strong><?= __('email') ?>:</strong> <?= htmlspecialchars($order['email']) ?></p>
                <p><strong><?= __('order_date') ?>:</strong> <?= date('Y/m/d H:i', strtotime($order['created_at'])) ?></p>
            </div>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
                <h3><?= __('edit_order_details') ?></h3>
                <div class="form-grid">
                    <div>
                        <label for="status"><?= __('order_status') ?></label>
                        <select name="status" id="status">
                            <?php foreach (['pending','confirmed','preparing','ready','completed','cancelled'] as $status): ?>
                            <option value="<?= $status ?>" <?= $order['status']===$status?'selected':'' ?>><?= __('status_' . $status) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="pickup_time"><?= __('pickup_time') ?></label>
                        <input type="text" name="pickup_time" id="pickup_time" value="<?= $default_pickup ?>" placeholder="<?= __('select_date_time') ?>">
                    </div>
                </div>

                <div>
                    <label for="notes"><?= __('notes') ?></label>
                    <textarea name="notes" id="notes" style="width: 100%; min-height: 100px; padding: 10px; border-radius: 5px; border: 1px solid #ccc; font-size: 1em;" placeholder="<?= __('notes_placeholder') ?>"><?= htmlspecialchars($order['notes'] ?? '') ?></textarea>
                </div>

                <h3><?= __('items') ?></h3>
                <?php if (empty($order_items)): ?>
                    <p><?= __('no_items_in_order') ?></p>
                <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th><?= __('item_name') ?></th>
                            <th><?= __('quantity') ?></th>
                            <th><?= __('unit_price') ?></th>
                            <th><?= __('subtotal') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $total = 0;
                    foreach ($order_items as $item):
                        $subtotal = $item['price'] * $item['quantity'];
                        $total += $subtotal;
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($item['name']) ?></td>
                        <td><input type="number" name="quantity[<?= $item['id'] ?>]" value="<?= $item['quantity'] ?>" min="0"></td>
                        <td><?= formatPrice($item['price']) ?></td>
                        <td><?= formatPrice($subtotal) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr style="background-color: #f8f9fa;">
                        <td colspan="3" style="text-align: right; font-weight: bold; font-size: 1.1em;"><?= __('total_amount') ?></td>
                        <td style="font-weight: bold; font-size: 1.1em;"><?= formatPrice($total) ?></td>
                    </tr>
                    </tbody>
                </table>
                <?php endif; ?>
                
                <div style="margin-top: 30px;">
                    <button type="submit" name="update_order" class="btn btn-primary"><?= __('save_changes') ?></button>
                    <a href="orders.php" class="btn btn-secondary"><?= __('back_to_orders') ?></a>
                </div>
        </div>

<!-- Flatpickr JS -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
flatpickr("#pickup_time", {
    enableTime: true,
    dateFormat: "Y-m-d H:i",
    minDate: "<?= $event_date ?>",
    time_24hr: true
});
</script>
<?php
require_once __DIR__ . '/includes/footer.php';