<?php
require_once '../config.php';
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
?>
<!DOCTYPE html>
<html lang="<?= $_SESSION['lang'] ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= __('order_details') ?> #<?= htmlspecialchars($order['order_number']) ?> - <?= __('admin_dashboard') ?></title>

<!-- Flatpickr CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

<style>
    /* Copied all styles from dashboard.php for consistency */
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }
    
    body {
        font-family: 'Arial', 'Hiragino Sans', sans-serif;
        background: #f5f5f5;
        color: #333;
    }
    
    .header {
        background: #2c3e50;
        color: white;
        padding: 20px 0;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }
    
    .header-content {
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .header h1 {
        font-size: 1.8em;
    }
    
    .nav-links { display: flex; align-items: center; }
    .nav-links a {
        color: white;
        text-decoration: none;
        margin-left: 20px;
        padding: 8px 16px;
        border-radius: 5px;
        transition: background 0.3s;
    }
    
    .nav-links a:hover {
        background: rgba(255, 255, 255, 0.2);
    }
    
    .container {
        max-width: 1200px;
        margin: 30px auto;
        padding: 0 20px;
    }
    
    .section {
        background: white;
        border-radius: 10px;
        padding: 30px;
        margin-bottom: 30px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    }
    
    .section h2 {
        color: #2c3e50;
        margin-bottom: 20px;
        font-size: 1.5em;
        border-bottom: 3px solid #3498db;
        padding-bottom: 10px;
    }

    .table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
    }
    
    .table th,
    .table td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid #ddd;
    }
    
    .table th {
        background: #34495e;
        color: white;
        font-weight: bold;
    }
    
    .table tr:hover {
        background: #f8f9fa;
    }
    
    /* Styles for form elements specific to this page */
    label { 
        display: block; 
        margin-top: 20px;
        margin-bottom: 5px;
        font-weight: bold; 
        font-size: 1em; 
        color: #34495e;
    }

    input[type="number"],
    input[type="text"],
    select {
        padding: 10px;
        border-radius: 5px;
        border: 1px solid #ccc;
        font-size: 1em;
        transition: border-color 0.3s, box-shadow 0.3s;
    }

    input[type="number"] { width: 80px; }
    
    .form-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 20px;
    }

    .btn { 
        display: inline-block; 
        padding: 10px 20px; 
        border: none;
        border-radius: 8px; 
        color: white; 
        font-weight: bold; 
        text-decoration: none;
        cursor: pointer; 
        font-size: 1em; 
        transition: opacity 0.3s;
        margin-right: 10px;
        margin-top: 10px;
    }
    .btn-primary { background: #27ae60; }
    .btn-primary:hover { background: #2ecc71; }
    .btn-secondary { background: #7f8c8d; }
    .btn-secondary:hover { background: #95a5a6; }

    .alert { padding: 15px; margin-bottom: 20px; border-radius: 8px; font-weight: bold; }
    .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb;}
    .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;}
    
    .customer-info p { margin: 0 0 10px; font-size: 1.1em; }
    .customer-info p strong { color: #34495e; }
    .lang-selector { margin-left: 20px; }
    .lang-selector select { padding: 5px; border-radius: 5px; border: none; background: rgba(255,255,255,0.2); color: white; cursor: pointer; }
    .lang-selector select option { background: #2c3e50; color: white; }
</style>
</head>
<body>

    <div class="header">
        <div class="header-content">
            <h1>📋 <?= __('order_details') ?></h1>
            <div class="nav-links">
                <a href="dashboard.php">📊 <?= __('admin_dashboard') ?></a>
                <a href="orders.php">📋 <?= __('order_management') ?></a>
                <a href="users.php">👥 <?= __('user_management') ?></a>
                <a href="menu.php">🍕 <?= __('admin_menu_management') ?></a>
                <a href="settings.php">⚙️ <?= __('admin_settings') ?></a>
                <a href="../logout.php">🚪 <?= __('logout') ?></a>
                <div class="lang-selector">
                    <form method="GET" action="">
                        <select name="lang" onchange="this.form.submit()">
                            <option value="ja" <?= $_SESSION['lang'] === 'ja' ? 'selected' : '' ?>>🇯🇵 日本語</option>
                            <option value="en" <?= $_SESSION['lang'] === 'en' ? 'selected' : '' ?>>🇺🇸 English</option>
                        </select>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
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
            </form>
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
</body>
</html>