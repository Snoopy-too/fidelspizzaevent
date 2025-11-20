<?php
require_once 'config.php';

requireLogin();

$config = getSiteConfig();
$db = getDB();

// Handle cancel order request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_order'])) {
    $order_id = $_POST['order_id'];
    $stmt = $db->prepare("UPDATE orders SET status='cancelled' WHERE id=? AND user_id=? AND status IN ('pending','confirmed')");
    $stmt->execute([$order_id, $_SESSION['user_id']]);
    header("Location: my_orders.php");
    exit;
}

// Get user's orders
$stmt = $db->prepare("
    SELECT o.*, 
           GROUP_CONCAT(CONCAT(mi.name, ' x', oi.quantity) SEPARATOR '<br>') as items
    FROM orders o
    LEFT JOIN order_items oi ON o.id = oi.order_id
    LEFT JOIN menu_items mi ON oi.menu_item_id = mi.id
    WHERE o.user_id = ?
    GROUP BY o.id
    ORDER BY o.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$orders = $stmt->fetchAll();

// Helper function for translating status
function translateStatus($status) {
    return __('status_' . $status);
}
?>
<!DOCTYPE html>
<html lang="<?= $_SESSION['lang'] ?? 'ja' ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= __('my_orders_title') ?> - <?= htmlspecialchars($config['site_title'] ?? __('site_title')) ?></title>
<style>
/* Keep all previous styles unchanged... */
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family:'Arial', sans-serif; background: linear-gradient(135deg,#ff6b6b,#feca57); min-height:100vh; padding:20px 0; }
.container { max-width:1000px; margin:0 auto; padding:0 20px; }
header { background: rgba(255,255,255,0.95); padding:30px; border-radius:15px; box-shadow:0 10px 30px rgba(0,0,0,0.2); margin-bottom:30px; text-align:center; }
h1 { color:#d32f2f; font-size:2.5em; margin-bottom:20px; }
.nav-links { display:flex; justify-content:center; gap:20px; flex-wrap:wrap; }
.nav-links a { color:#d32f2f; text-decoration:none; font-weight:bold; padding:10px 20px; background:#fff3e0; border-radius:20px; transition:all 0.3s ease; }
.nav-links a:hover { background:#d32f2f; color:white; }
.orders-container { background: rgba(255,255,255,0.95); padding:30px; border-radius:15px; box-shadow:0 10px 30px rgba(0,0,0,0.2); }
.order-card { background:#fff; border-radius:10px; padding:25px; margin-bottom:20px; box-shadow:0 5px 15px rgba(0,0,0,0.1); border-left:5px solid #d32f2f; }
.order-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:10px; }
.order-number { font-size:1.3em; font-weight:bold; color:#d32f2f; }
.order-date { color:#666; font-size:0.9em; }
.order-status { padding:8px 16px; border-radius:20px; font-size:0.9em; font-weight:bold; text-transform:uppercase; }
.status-pending { background:#fff3cd; color:#856404; }
.status-confirmed { background:#d4edda; color:#155724; }
.status-preparing { background:#cce5ff; color:#004085; }
.status-ready { background:#e2e3e5; color:#383d41; }
.status-completed { background:#d1ecf1; color:#0c5460; }
.status-cancelled { background:#f8d7da; color:#721c24; }
.order-details { display:grid; grid-template-columns:1fr auto; gap:20px; align-items:start; }
.order-items { line-height:1.6; color:#555; }
.order-total { font-size:1.5em; font-weight:bold; color:#2e7d32; text-align:right; }
.empty-state { text-align:center; padding:60px 20px; color:#666; }
.empty-state .icon { font-size:4em; margin-bottom:20px; color:#ddd; }
.empty-state h3 { font-size:1.5em; margin-bottom:10px; color:#888; }
.btn { display:inline-block; padding:12px 30px; background:#d32f2f; color:white; text-decoration:none; border-radius:25px; font-weight:bold; transition:all 0.3s ease; margin-top:20px; border:none; cursor:pointer;}
.btn:hover { background:#b71c1c; transform:translateY(-2px); }
.btn-secondary { background:#4caf50; }
.btn-secondary:hover { background:#388e3c; }
.btn-cancel { background:#e74c3c; }
.btn-cancel:hover { background:#c62828; }
.pickup-info { background:#e8f5e8; padding:20px; border-radius:10px; margin-top:15px; border-left:4px solid #4caf50; }
.pickup-info h4 { color:#2e7d32; margin-bottom:10px; }
@media(max-width:768px){ .order-header { flex-direction:column; align-items:flex-start; } .order-details { grid-template-columns:1fr; gap:15px; } .order-total { text-align:left; } .nav-links { flex-direction:column; align-items:center; } h1 { font-size:2em; } }
</style>
</head>
<body>
<div class="container">
<header>
    <h1>📋 <?= __('my_orders_title') ?></h1>
    <div class="nav-links">
        <a href="index.php">🏠 <?= __('home') ?></a>
        <a href="menu.php">🍕 <?= __('menu_title') ?></a>
        <a href="logout.php">🚪 <?= __('logout') ?></a>
    </div>
</header>

<div class="orders-container">
<?php if(empty($orders)): ?>
    <div class="empty-state">
        <div class="icon">🍕</div>
        <h3><?= __('no_orders_title') ?></h3>
        <p><?= __('no_orders_desc') ?></p>
        <a href="menu.php" class="btn"><?= __('order_first_pizza') ?></a>
    </div>
<?php else: ?>
    <?php foreach($orders as $order): ?>
    <div class="order-card">
        <div class="order-header">
            <div>
                <div class="order-number"><?= sprintf(__('order_number'), htmlspecialchars($order['order_number'])) ?></div>
                <div class="order-date"><?= date('Y年n月j日 H:i', strtotime($order['created_at'])) ?></div>
            </div>
            <div class="order-status status-<?= htmlspecialchars($order['status']) ?>">
                <?= translateStatus($order['status']) ?>
            </div>
        </div>

        <div class="order-details">
            <div class="order-items">
                <?= $order['items'] ? $order['items'] : __('item_not_found') ?>
            </div>
            <div class="order-total">
                <?= formatPrice($order['total_amount']) ?>
            </div>
        </div>

        <?php if(in_array($order['status'], ['pending','confirmed'])): ?>
        <form method="POST" style="margin-top:15px; display:flex; gap:10px; flex-wrap:wrap;">
            <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
            <a href="edit_order.php?order_id=<?= $order['id'] ?>" class="btn btn-secondary"><?= __('edit_order') ?></a>
            <button type="submit" name="cancel_order" class="btn btn-cancel" onclick="return confirm('<?= __('confirm_cancel') ?>')"><?= __('cancel_order') ?></button>
        </form>
        <?php endif; ?>

        <?php if(in_array($order['status'], ['confirmed','preparing','ready']) && !empty($config['event_date'])): ?>
        <div class="pickup-info">
            <h4>📍 <?= __('pickup_info') ?></h4>
            <p><strong><?= __('pickup_date') ?></strong> <?= date('Y年n月j日', strtotime($config['event_date'])) ?></p>
            <?php if(!empty($config['event_location'])): ?>
            <p><strong><?= __('pickup_location') ?></strong> <?= htmlspecialchars($config['event_location']) ?></p>
            <?php endif; ?>
            <p><strong><?= __('amount_to_bring') ?></strong> <?= formatPrice($order['total_amount']) ?></p>
            <p><strong><?= __('order_number_label') ?></strong> <?= htmlspecialchars($order['order_number']) ?></p>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
<?php endif; ?>
</div>
</div>
</body>
</html>