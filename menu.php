<?php
require_once 'config.php';

// Ensure user is logged in
requireLogin();

// This check is redundant because requireLogin() already handles it, but it's harmless.
if (empty($_SESSION['user_id'])) {
    redirect('login.php');
    exit;
}

// Get user display name safely
$user_name = !empty($_SESSION['user_name']) ? $_SESSION['user_name'] : 'お客様';

$config = getSiteConfig();
$db = getDB();

// Get menu items
$stmt = $db->query("SELECT * FROM menu_items WHERE is_active = 1 ORDER BY sort_order, name");
$menu_items = $stmt->fetchAll();

// Handle order submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_order'])) {
    $order_items = [];
    $total_amount = 0;

    // Process each menu item
    foreach ($menu_items as $item) {
        $quantity = (int)($_POST['quantity_' . $item['id']] ?? 0);
        if ($quantity > 0 && $quantity <= 15) {
            $subtotal = $quantity * $item['price'];
            $order_items[] = [
                'menu_item_id' => $item['id'],
                'name' => $item['name'],
                'quantity' => $quantity,
                'unit_price' => $item['price'],
                'subtotal' => $subtotal
            ];
            $total_amount += $subtotal;
        }
    }

    if (empty($order_items)) {
        $error = '注文するにはピザを1つ以上選択してください。'; // TODO: Translate
    } else {
        try {
            $db->beginTransaction();

            $pickup_time = null;
            if (!empty($config['event_date'])) {
                $pickup_time = date('Y-m-d H:i:s', strtotime($config['event_date'] . ' 12:30'));
            }

            $order_number = generateOrderNumber();
            $stmt = $db->prepare("INSERT INTO orders (user_id, order_number, total_amount, pickup_time) VALUES (?, ?, ?, ?)");
            $stmt->execute([$_SESSION['user_id'], $order_number, $total_amount, $pickup_time]);
            $order_id = $db->lastInsertId();

            $stmt = $db->prepare("INSERT INTO order_items (order_id, menu_item_id, quantity, unit_price, subtotal) VALUES (?, ?, ?, ?, ?)");
            foreach ($order_items as $item) {
                $stmt->execute([
                    $order_id, $item['menu_item_id'], $item['quantity'], $item['unit_price'], $item['subtotal']
                ]);
            }

            $db->commit();

            // Send email notifications
            $emailSent = sendOrderEmailNotifications($order_id);

            // Get user's email to display on success page
            $userStmt = $db->prepare("SELECT email FROM users WHERE id = ?");
            $userStmt->execute([$_SESSION['user_id']]);
            $userEmail = $userStmt->fetchColumn();

            // Redirect to success page
            $_SESSION['order_success'] = [
                'order_number' => $order_number,
                'total_amount' => $total_amount,
                'pickup_time' => $pickup_time,
                'email_sent' => $emailSent,
                'user_email' => $userEmail
            ];
            redirect('order_success.php');

        } catch (Exception $e) {
            $db->rollBack();
            $error = '注文の送信に失敗しました。もう一度お試しください。'; // TODO: Translate
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?= $_SESSION['lang'] ?? 'ja' ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= __('menu_title') ?> - <?= htmlspecialchars($config['site_title'] ?? __('site_title')) ?></title>
<style>
/* Styling is complete and correct */
* {margin:0; padding:0; box-sizing:border-box;}
body {font-family:'Arial', sans-serif; background:linear-gradient(135deg,#ff6b6b,#feca57); min-height:100vh; padding:20px 0;}
.container {max-width:1200px; margin:0 auto; padding:0 20px;}
header {background:rgba(255,255,255,0.95); padding:30px; border-radius:15px; box-shadow:0 10px 30px rgba(0,0,0,0.2); margin-bottom:30px; text-align:center;}
h1 {color:#d32f2f; font-size:2.5em; margin-bottom:10px;}
.user-info {background:#e3f2fd; padding:15px; border-radius:8px; margin:20px 0; border-left:4px solid #2196f3;}
.menu-content {background:rgba(255,255,255,0.95); padding:30px; border-radius:15px; box-shadow:0 10px 30px rgba(0,0,0,0.2); margin-bottom:30px;}
.menu-grid {display:grid; grid-template-columns:repeat(auto-fit,minmax(350px,1fr)); gap:30px; margin-bottom:30px;}
.menu-item {background:#fff; border-radius:15px; padding:20px; box-shadow:0 5px 20px rgba(0,0,0,0.1); transition:transform 0.3s ease; display: flex; flex-direction: column;}
.menu-item:hover {transform:translateY(-5px);}
.menu-item .img-container { width: 100%; height: 250px; background-color: #f5f5f5; border-radius: 10px; margin-bottom: 15px; display: flex; align-items: center; justify-content: center; overflow: hidden; }
.menu-item img { width: 100%; height: 100%; object-fit: cover; border-radius: 10px; }
.menu-item-content { display: flex; flex-direction: column; flex-grow: 1; }
.menu-item h3 {color:#d32f2f; font-size:1.4em; margin-bottom:10px;}
.menu-item .description {color:#666; margin-bottom:15px; line-height:1.5; flex-grow: 1;}
.menu-item .price {font-size:1.5em; font-weight:bold; color:#2e7d32; margin-bottom:20px;}
.quantity-section {display:flex; align-items:center; gap:15px; margin-top: auto;}
.quantity-section label {font-weight:bold; color:#555;}
.quantity-controls {display:flex; align-items:center; gap:10px;}
.quantity-btn {background:#d32f2f; color:white; border:none; width:35px; height:35px; border-radius:50%; font-size:1.2em; cursor:pointer; transition:all 0.3s ease;}
.quantity-btn:hover {background:#b71c1c; transform:scale(1.1);}
.quantity-input {width:60px; padding:8px; text-align:center; border:2px solid #ddd; border-radius:5px; font-size:1.1em; font-weight:bold;}
.pizza-order-summary {background:#e8f5e8; padding:25px; border-radius:15px; margin:30px 0; border:2px solid #4caf50;}
.pizza-order-summary h3 {color:#2e7d32; font-size:1.5em; margin-bottom:20px; text-align:center;}
.order-item {display:flex; justify-content:space-between; padding:10px 0; border-bottom:1px solid #c8e6c9;}
.order-item:last-child {border-bottom:none; font-weight:bold; font-size:1.2em; color:#2e7d32;}
.submit-section {text-align:center; margin:30px 0;}
.btn {padding:15px 40px; background:#d32f2f; color:white; border:none; border-radius:25px; font-size:1.2em; font-weight:bold; cursor:pointer; transition:all 0.3s ease; text-decoration:none; display:inline-block; margin:10px;}
.btn:hover {background:#b71c1c; transform:translateY(-2px); box-shadow:0 8px 25px rgba(211,47,47,0.4);}
.btn.secondary {background:#ff9800;}
.btn.secondary:hover {background:#f57c00;}
.alert {padding:15px; margin-bottom:20px; border-radius:8px; font-weight:bold;}
.alert.error {background:#ffebee; color:#c62828; border:1px solid #e57373;}
.nav-links {text-align:center; margin:20px 0;}
.nav-links a {color:rgba(255,255,255,0.9); text-decoration:none; margin:0 15px; font-weight:bold;}
.nav-links a:hover {color:white; text-decoration:underline;}
@media(max-width:768px){.menu-grid{grid-template-columns:1fr;}.quantity-section{flex-direction:column; align-items:flex-start; gap:10px;}h1{font-size:2em;}}
</style>
</head>
<body>
<div class="container">
<header>
    <h1>🍕 <?= __('menu_title') ?></h1>
    <div class="user-info">
        <?= sprintf(__('welcome_user'), htmlspecialchars($user_name)) ?>
        <?php if (!empty($config['event_date'])): ?>
            <?= __('event_date_label') ?> <strong><?= date('Y年n月j日', strtotime($config['event_date'])) ?></strong>
        <?php endif; ?>
        <?php if (!empty($config['event_location'])): ?>
            | <?= __('location_label') ?> <strong><?= htmlspecialchars($config['event_location'] ?? '') ?></strong>
        <?php endif; ?>
    </div>

    <div class="nav-links">
        <a href="index.php"><?= __('home') ?></a>
        <a href="my_orders.php" class="btn secondary"><?= __('order_history') ?></a>
        <a href="logout.php"><?= __('logout') ?></a>
    </div>
</header>

<?php if (isset($error)): ?>
    <div class="alert error"><?= htmlspecialchars($error ?? '') ?></div>
<?php endif; ?>

<div class="menu-content">
    <?php if (!empty($config['menu_content'])): ?>
        <div style="text-align: center; margin-bottom: 30px; font-size: 1.1em; color: #555;">
            <?= nl2br(htmlspecialchars($config['menu_content'] ?? '')) ?>
        </div>
    <?php endif; ?>

    <form method="POST" id="orderForm">
        <div class="menu-grid">
            <?php foreach ($menu_items as $item): ?>
            <div class="menu-item">
                <div class="img-container">
                    <?php
                    $image_path = $item['image_path'];
                    if (!empty($image_path) && file_exists(ROOT_PATH . DIRECTORY_SEPARATOR . $image_path)):
                    ?>
                        <img src="<?= htmlspecialchars($image_path) ?>" alt="<?= htmlspecialchars($item['name'] ?? '') ?>">
                    <?php else: ?>
                        <span style="font-size: 3em;">🍕</span>
                    <?php endif; ?>
                </div>

                <div class="menu-item-content">
                    <h3><?= htmlspecialchars($item['name'] ?? '') ?></h3>

                    <?php if (!empty($item['description'])): ?>
                        <div class="description"><?= htmlspecialchars($item['description'] ?? '') ?></div>
                    <?php endif; ?>

                    <div class="price"><?= formatPrice($item['price'] ?? 0) ?></div>
                </div>

                <div class="quantity-section">
                    <label><?= __('quantity') ?></label>
                    <div class="quantity-controls">
                        <button type="button" class="quantity-btn" onclick="changeQuantity(<?= $item['id'] ?>, -1)">-</button>
                        <input type="number" class="quantity-input" id="quantity_<?= $item['id'] ?>" name="quantity_<?= $item['id'] ?>" value="0" min="0" max="15" onchange="updateOrder()">
                        <button type="button" class="quantity-btn" onclick="changeQuantity(<?= $item['id'] ?>, 1)">+</button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="pizza-order-summary" id="orderSummary" style="display: none;">
            <h3>🛒 <?= __('cart_contents') ?></h3>
            <div id="orderItems"></div>
        </div>

        <div class="submit-section">
            <button type="submit" name="submit_order" class="btn" id="submitBtn" style="display: none;">
                🍕 <?= __('submit_order') ?>
            </button>
            <a href="index.php" class="btn secondary">← <?= __('back_home') ?></a>
            <a href="my_orders.php" class="btn secondary">📄 <?= __('order_history') ?></a>
        </div>
    </form>
</div>
</div>

<script>
// --- FULL JAVASCRIPT CODE RESTORED ---
const menuItems = <?= json_encode(array_column($menu_items, null, 'id')) ?>;

function changeQuantity(itemId, change) {
    const input = document.getElementById('quantity_' + itemId);
    let newValue = parseInt(input.value) + change;
    newValue = Math.max(0, Math.min(15, newValue));
    input.value = newValue;
    updateOrder();
}

function updateOrder() {
    const orderItems = [];
    let totalAmount = 0;

    Object.keys(menuItems).forEach(itemId => {
        const quantity = parseInt(document.getElementById('quantity_' + itemId).value) || 0;
        if (quantity > 0) {
            const item = menuItems[itemId];
            const subtotal = quantity * parseFloat(item.price);
            orderItems.push({
                name: item.name,
                quantity: quantity,
                price: parseFloat(item.price),
                subtotal: subtotal
            });
            totalAmount += subtotal;
        }
    });

    const orderSummary = document.getElementById('orderSummary');
    const orderItemsDiv = document.getElementById('orderItems');
    const submitBtn = document.getElementById('submitBtn');

    if (orderItems.length > 0) {
        let html = '';
        orderItems.forEach(item => {
            html += `<div class="order-item">
                <span>${item.name} x ${item.quantity}</span>
                <span>${new Intl.NumberFormat('ja-JP', { style: 'currency', currency: 'JPY' }).format(item.subtotal)}</span>
            </div>`;
        });
        html += `<div class="order-item">
                <span><?= __('total_amount') ?></span>
            <span>${new Intl.NumberFormat('ja-JP', { style: 'currency', currency: 'JPY' }).format(totalAmount)}</span>
        </div>`;

        orderItemsDiv.innerHTML = html;
        orderSummary.style.display = 'block';
        submitBtn.style.display = 'inline-block';
    } else {
        orderSummary.style.display = 'none';
        submitBtn.style.display = 'none';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    Object.keys(menuItems).forEach(itemId => {
        const input = document.getElementById('quantity_' + itemId);
        input.addEventListener('input', updateOrder);
    });
});

document.getElementById('orderForm').addEventListener('submit', function(e) {
    let hasItems = false;
    Object.keys(menuItems).forEach(itemId => {
        const quantity = parseInt(document.getElementById('quantity_' + itemId).value) || 0;
        if (quantity > 0) {
            hasItems = true;
        }
    });

    if (!hasItems) {
        e.preventDefault();
        alert('注文するにはピザを1つ以上選択してください。');
    }
});
</script>
</body>
</html>