<?php
require_once 'config.php';

requireLogin();

$config = getSiteConfig();
$db = getDB();
$error_message = '';

// Check for an error message from a failed redirect
if (isset($_GET['error'])) {
    $error_message = __('error_update_failed');
}

// Get order ID from query
if (!isset($_GET['order_id'])) {
    redirect('my_orders.php');
}

$order_id = (int)$_GET['order_id'];

// Fetch order and ensure it belongs to the logged-in user and is editable
$stmt = $db->prepare("SELECT * FROM orders WHERE id=? AND user_id=? AND status IN ('pending','confirmed')");
$stmt->execute([$order_id, $_SESSION['user_id']]);
$order = $stmt->fetch();

if (!$order) {
    redirect('my_orders.php');
}

// Fetch order items with menu item info
$stmt = $db->prepare("
    SELECT oi.id AS order_item_id, oi.quantity, mi.id AS menu_item_id, mi.name, mi.price
    FROM order_items oi
    JOIN menu_items mi ON oi.menu_item_id = mi.id
    WHERE oi.order_id=?
");
$stmt->execute([$order_id]);
$order_items = $stmt->fetchAll();

// Handle form submission to update quantities
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db->beginTransaction();
    try {
        $total_amount = 0;
        $has_items = false; // Check if any items remain after update

        foreach ($order_items as $item) {
            $field_name = "quantity_{$item['order_item_id']}";
            $new_qty = max(0, (int)($_POST[$field_name] ?? $item['quantity']));

            if ($new_qty > 0) {
                // Update items with quantity > 0
                $stmt = $db->prepare("UPDATE order_items SET quantity=?, subtotal=? WHERE id=? AND order_id=?");
                $subtotal = $new_qty * $item['price'];
                $stmt->execute([$new_qty, $subtotal, $item['order_item_id'], $order_id]);
                $total_amount += $subtotal;
                $has_items = true;
            } else {
                // Delete items where quantity is 0
                $stmt = $db->prepare("DELETE FROM order_items WHERE id=? AND order_id=?");
                $stmt->execute([$item['order_item_id'], $order_id]);
            }
        }

        // If all items are removed, cancel the order. Otherwise, update the total.
        if (!$has_items) {
            $stmt = $db->prepare("UPDATE orders SET total_amount=0, status='cancelled', updated_at=NOW() WHERE id=?");
            $stmt->execute([$order_id]);
        } else {
            $stmt = $db->prepare("UPDATE orders SET total_amount=?, updated_at=NOW() WHERE id=?");
            $stmt->execute([$total_amount, $order_id]);
        }

        $db->commit();

        // --- ADDED: Send email notification for the update, but only if the order wasn't cancelled ---
        if ($has_items) {
            sendOrderEmailNotifications($order_id, true); // The 'true' flag indicates an update
        }

        redirect('my_orders.php');

    } catch (Exception $e) {
        $db->rollBack();
        // Redirect with an error flag if something goes wrong
        redirect('edit_order.php?order_id=' . $order_id . '&error=1');
    }
}
?>
<!DOCTYPE html>
<html lang="<?= $_SESSION['lang'] ?? 'ja' ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= sprintf(__('edit_order_title'), htmlspecialchars($order['order_number'])) ?> - <?= htmlspecialchars($config['site_title'] ?? __('site_title')) ?></title>
<style>
body { font-family:'Arial',sans-serif; background:#fff8f0; padding:20px; }
.container { max-width:800px; margin:0 auto; background:#fff; padding:30px; border-radius:15px; box-shadow:0 5px 20px rgba(0,0,0,0.1); }
h1 { color:#d32f2f; margin-bottom:20px; }
form table { width:100%; border-collapse:collapse; margin-bottom:20px; }
form th, form td { padding:12px; border-bottom:1px solid #ddd; text-align:left; }
input[type=number] { width:60px; padding:5px; border-radius: 5px; border: 1px solid #ccc;}
.btn { padding:12px 25px; background:#d32f2f; color:white; text-decoration:none; border-radius:25px; font-weight:bold; border:none; cursor:pointer; }
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
        <a href="my_orders.php"><?= __('back_to_history') ?></a>
        <a href="menu.php"><?= __('add_more_pizza') ?></a>
    </div>
    <h1><?= sprintf(__('edit_order_title'), htmlspecialchars($order['order_number'])) ?></h1>

    <?php if ($error_message): ?>
        <div class="alert-error"><?= htmlspecialchars($error_message) ?></div>
    <?php endif; ?>

    <form method="POST">
        <table>
            <thead>
                <tr>
                    <th><?= __('pizza_column') ?></th>
                    <th><?= __('unit_price_column') ?></th>
                    <th><?= __('quantity') ?></th>
                    <th><?= __('subtotal_column') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $total = 0;
                foreach ($order_items as $item): 
                    $subtotal = $item['quantity'] * $item['price'];
                    $total += $subtotal;
                ?>
                <tr>
                    <td><?= htmlspecialchars($item['name']) ?></td>
                    <td><?= formatPrice($item['price']) ?></td>
                    <td>
                        <input type="number" name="quantity_<?= $item['order_item_id'] ?>" value="<?= $item['quantity'] ?>" min="0" max="15">
                    </td>
                    <td><?= formatPrice($subtotal) ?></td>
                </tr>
                <?php endforeach; ?>
                <tr style="background-color: #f9f9f9;">
                    <td colspan="3" style="text-align:right; font-weight:bold;"><?= __('total_label') ?></td>
                    <td style="font-weight:bold;"><?= formatPrice($total) ?></td>
                </tr>
            </tbody>
        </table>
        <button type="submit" class="btn"><?= __('update_order_button') ?></button>
    </form>
</div>
</body>
</html>