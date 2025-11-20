<?php
require_once '../config.php';

requireAdmin();

$config = getSiteConfig();
$db = getDB();

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $order_id = (int)($_POST['order_id'] ?? 0);
    $new_status = $_POST['status'] ?? '';
    
    if ($order_id && in_array($new_status, ['pending', 'confirmed', 'preparing', 'ready', 'completed', 'cancelled'])) {
        try {
            $stmt = $db->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$new_status, $order_id]);
            $success_message = __('status_update_success');
        } catch (Exception $e) {
            $error_message = __('status_update_error');
        }
    }
}

// Handle bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $action = $_POST['bulk_action'];
    $selected_orders = $_POST['selected_orders'] ?? [];
    
    if (!empty($selected_orders) && in_array($action, ['confirmed', 'preparing', 'ready', 'completed', 'cancelled'])) {
        try {
            $placeholders = str_repeat('?,', count($selected_orders) - 1) . '?';
            $stmt = $db->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id IN ($placeholders)");
            $stmt->execute(array_merge([$action], $selected_orders));
            $success_message = __('bulk_action_success');
        } catch (Exception $e) {
            $error_message = __('bulk_action_error');
        }
    }
}

// Filtering and sorting
$filter_status = $_GET['status'] ?? '';
$sort_by = $_GET['sort'] ?? 'created_at';
$sort_order = strtoupper($_GET['order'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
$search = $_GET['search'] ?? '';

// Build query
$where_conditions = [];
$params = [];

if ($filter_status) {
    $where_conditions[] = "o.status = ?";
    $params[] = $filter_status;
}

if ($search) {
    $where_conditions[] = "(o.order_number LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Valid sort columns and their table aliases
$valid_sorts = [
    'created_at'   => 'o.created_at',
    'order_number' => 'o.order_number',
    'total_amount' => 'o.total_amount',
    'status'       => 'o.status',
    'first_name'   => 'u.first_name',
    'pickup_time'  => 'o.pickup_time'
];

$sort_by_column = $valid_sorts[$sort_by] ?? 'o.created_at';

// Get pick-up time orders summary (pending orders only)
$stmt = $db->query("
    SELECT 
        o.pickup_time,
        mi.name AS pizza_name,
        SUM(oi.quantity) AS total_quantity
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    JOIN menu_items mi ON oi.menu_item_id = mi.id
    WHERE o.status = 'pending'
    GROUP BY o.pickup_time, mi.name
    ORDER BY o.pickup_time ASC, mi.name ASC
");
$pickup_data = $stmt->fetchAll();

// Organize into an array for display
$pickup_orders = [];
$pizza_types = [];
foreach ($pickup_data as $row) {
    if($row['pickup_time']) {
        $pickup_orders[$row['pickup_time']][$row['pizza_name']] = $row['total_quantity'];
        $pizza_types[$row['pizza_name']] = true;
    }
}
$pizza_types = array_keys($pizza_types);

// Get orders
$stmt = $db->prepare("
    SELECT o.*, u.first_name, u.last_name, u.email,
           GROUP_CONCAT(CONCAT(mi.name, ' x', oi.quantity) SEPARATOR ', ') as items
    FROM orders o
    JOIN users u ON o.user_id = u.id
    LEFT JOIN order_items oi ON o.id = oi.order_id
    LEFT JOIN menu_items mi ON oi.menu_item_id = mi.id
    $where_clause
    GROUP BY o.id
    ORDER BY $sort_by_column $sort_order
");
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Get status counts for filters
$stmt = $db->query("SELECT status, COUNT(*) as count FROM orders GROUP BY status");
$status_counts = [];
while ($row = $stmt->fetch()) {
    $status_counts[$row['status']] = $row['count'];
}
?>
<!DOCTYPE html>
<html lang="<?= $_SESSION['lang'] ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📋 <?= __('order_management') ?> - <?= __('admin_dashboard') ?></title>
    <style>
        /* Existing CSS for this specific page is preserved */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Arial', 'Hiragino Sans', sans-serif; background: #f5f5f5; color: #333; }
        .header { background: #2c3e50; color: white; padding: 20px 0; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header-content { max-width: 1200px; margin: 0 auto; padding: 0 20px; display: flex; justify-content: space-between; align-items: center; }
        .header h1 { font-size: 1.8em; }
        .nav-links { display: flex; align-items: center; }
        .nav-links a { color: white; text-decoration: none; margin-left: 20px; padding: 8px 16px; border-radius: 5px; transition: background 0.3s; }
        .nav-links a:hover { background: rgba(255,255,255,0.2); }
        .container { max-width: 1200px; margin: 30px auto; padding: 0 20px; }
        .controls { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); margin-bottom: 30px; display: grid; grid-template-columns: repeat(auto-fit, minmax(200px,1fr)); gap: 20px; align-items: end; }
        .form-group { display: flex; flex-direction: column; }
        .form-group label { margin-bottom: 5px; font-weight: bold; color: #555; }
        .form-group input, .form-group select { padding: 10px; border: 2px solid #ddd; border-radius: 5px; font-size: 1em; }
        .btn { padding: 10px 20px; background: #3498db; color: white; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; text-decoration: none; display: inline-block; transition: all 0.3s ease; }
        .btn:hover { background: #2980b9; transform: translateY(-2px); }
        .btn.success { background: #27ae60; }
        .btn.success:hover { background: #229954; }
        .btn.danger { background: #e74c3c; }
        .btn.danger:hover { background: #c0392b; }
        .status-filters { display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; }
        .status-filter { padding: 10px 15px; background: #ecf0f1; border-radius: 20px; text-decoration: none; color: #2c3e50; font-weight: bold; font-size: 0.9em; transition: all 0.3s ease; }
        .status-filter.active { background: #3498db; color: white; }
        .status-filter:hover { background: #d5dbdb; }
        .status-filter.active:hover { background: #2980b9; }
        .orders-table { background: white; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); overflow: hidden; margin-bottom: 40px; }
        .table { width: 100%; border-collapse: collapse; }
        .table th, .table td { padding: 15px; text-align: left; border-bottom: 1px solid #eee; }
        .table th { background: #34495e; color: white; font-weight: bold; position: sticky; top: 0; }
        .table tr:hover { background: #f8f9fa; }
        .status-badge { padding: 5px 12px; border-radius: 15px; font-size: 0.8em; font-weight: bold; text-transform: uppercase; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-confirmed { background: #d4edda; color: #155724; }
        .status-preparing { background: #cce5ff; color: #004085; }
        .status-ready { background: #e2e3e5; color: #383d41; }
        .status-completed { background: #d1ecf1; color: #0c5460; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        .status-select { padding: 5px 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 0.9em; }
        .bulk-actions { padding: 20px; background: #f8f9fa; border-bottom: 1px solid #eee; display: flex; align-items: center; gap: 10px; }
        .messages { margin-bottom: 20px; }
        .message { padding: 10px 15px; border-radius: 5px; margin-bottom: 10px; }
        .message.success { background: #d4edda; color: #155724; }
        .message.error { background: #f8d7da; color: #721c24; }
        .lang-selector { margin-left: 20px; }
        .lang-selector select { padding: 5px; border-radius: 5px; border: none; background: rgba(255,255,255,0.2); color: white; cursor: pointer; }
        .lang-selector select option { background: #2c3e50; color: white; }
        @media (max-width: 768px) { .header-content { flex-direction: column; gap: 15px; } .nav-links { flex-wrap: wrap; justify-content: center; } .nav-links a { margin: 5px 10px; } }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1>📋 <?= __('order_management') ?></h1>
            <div class="nav-links">
                <a href="dashboard.php">📊 <?= __('admin_dashboard') ?></a>
                <a href="orders.php">📋 <?= __('order_management') ?></a>
                <a href="users.php">👥 <?= __('user_management') ?></a>
                <a href="menu.php">🍕 <?= __('admin_menu_management') ?></a>
                <a href="settings.php">⚙️ <?= __('admin_settings') ?></a>
                <a href="reports.php">📊 <?= __('admin_reports') ?></a>
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

        <?php if (!empty($success_message)): ?>
            <div class="messages"><div class="message success"><?= $success_message ?></div></div>
        <?php endif; ?>
        <?php if (!empty($error_message)): ?>
            <div class="messages"><div class="message error"><?= $error_message ?></div></div>
        <?php endif; ?>

        <!-- PICK-UP TIME ORDERS SUMMARY TABLE -->
        <div class="orders-table">
            <h2 style="padding: 15px; background: #34495e; color: white; border-radius: 10px 10px 0 0;">📦 <?= __('pickup_schedule_summary') ?></h2>
            <table class="table">
                <thead>
                    <tr>
                        <th><?= __('pickup_time') ?></th>
                        <?php foreach ($pizza_types as $pizza): ?>
                            <th><?= htmlspecialchars($pizza) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pickup_orders)): ?>
                        <tr>
                            <td colspan="<?= count($pizza_types) + 1 ?>" style="text-align:center; padding: 20px; color: #999;">
                                <?= __('no_scheduled_pickups') ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($pickup_orders as $pickup_time => $pizzas): ?>
                            <tr>
                                <td><strong><?= date('Y/m/d H:i', strtotime($pickup_time)) ?></strong></td>
                                <?php foreach ($pizza_types as $pizza): ?>
                                    <td><?= isset($pizzas[$pizza]) ? $pizzas[$pizza] : 0 ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- FILTERS & SEARCH -->
        <div class="controls">
            <form method="get" class="form-group">
                <label><?= __('status') ?></label>
                <select name="status" onchange="this.form.submit()">
                    <option value=""><?= __('all_statuses') ?></option>
                    <?php foreach ($status_counts as $status => $count): ?>
                        <option value="<?= $status ?>" <?= $filter_status === $status ? 'selected' : '' ?>><?= __('status_' . $status) ?> (<?= $count ?>)</option>
                    <?php endforeach; ?>
                </select>
            </form>
            <form method="get" class="form-group">
                <label><?= __('search') ?></label>
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="<?= __('search_placeholder') ?>">
            </form>
            <form method="get" class="form-group">
                <label><?= __('sort_by') ?></label>
                <select name="sort" onchange="this.form.submit()">
                    <?php foreach ($valid_sorts as $key => $col): ?>
                        <option value="<?= $key ?>" <?= $sort_by === $key ? 'selected' : '' ?>><?= __('sort_' . $key) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
            <form method="get" class="form-group">
                <label><?= __('order_direction') ?></label>
                <select name="order" onchange="this.form.submit()">
                    <option value="ASC" <?= $sort_order === 'ASC' ? 'selected' : '' ?>><?= __('sort_asc') ?></option>
                    <option value="DESC" <?= $sort_order === 'DESC' ? 'selected' : '' ?>><?= __('sort_desc') ?></option>
                </select>
            </form>
        </div>

        <!-- BULK ACTIONS & ORDERS TABLE -->
        <form method="post">
            <div class="orders-table">
                <div class="bulk-actions">
                    <select name="bulk_action" class="status-select">
                        <option value=""><?= __('bulk_action') ?></option>
                        <option value="confirmed"><?= __('mark_confirmed') ?></option>
                        <option value="preparing"><?= __('mark_preparing') ?></option>
                        <option value="ready"><?= __('mark_ready') ?></option>
                        <option value="completed"><?= __('mark_completed') ?></option>
                        <option value="cancelled"><?= __('mark_cancelled') ?></option>
                    </select>
                    <button type="submit" class="btn"><?= __('apply') ?></button>
                </div>

                <table class="table">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="select-all" onclick="toggleAll(this)"></th>
                            <th><?= __('order_number') ?></th>
                            <th><?= __('customer') ?></th>
                            <th><?= __('notes') ?></th>
                            <th><?= __('items') ?></th>
                            <th><?= __('total_amount') ?></th>
                            <th><?= __('pickup_time') ?></th>
                            <th><?= __('status') ?></th>
                            <th><?= __('actions') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($orders)): ?>
                            <tr>
                                <td colspan="9" style="text-align:center; padding: 20px; color: #999;"><?= __('no_orders_found') ?></td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td><input type="checkbox" name="selected_orders[]" value="<?= $order['id'] ?>"></td>
                                    <td><strong><?= htmlspecialchars($order['order_number']) ?></strong></td>
                                    <td>
                                        <?= htmlspecialchars($order['first_name'] . ' ' . $order['last_name']) ?><br>
                                        <small><?= htmlspecialchars($order['email']) ?></small>
                                    </td>
                                    <td><small><?= htmlspecialchars($order['notes'] ?? '') ?: '<em style="color: #999;">' . __('no_notes') . '</em>' ?></small></td>
                                    <td><?= htmlspecialchars($order['items']) ?></td>
                                    <td>¥<?= number_format($order['total_amount'], 0) ?></td>
                                    <td><?= $order['pickup_time'] ? date('Y/m/d H:i', strtotime($order['pickup_time'])) : 'N/A' ?></td>
                                    <td>
                                        <form method="post" style="display:inline;">
                                            <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                            <select name="status" class="status-select" onchange="this.form.submit()">
                                                <?php foreach (['pending','confirmed','preparing','ready','completed','cancelled'] as $status): ?>
                                                    <option value="<?= $status ?>" <?= $order['status'] === $status ? 'selected' : '' ?>><?= __('status_' . $status) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <input type="hidden" name="update_status" value="1">
                                        </form>
                                    </td>
                                    <td>
                                        <a href="order_details.php?id=<?= $order['id'] ?>" class="btn"><?= __('view_details') ?></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </form>
    </div>

    <script>
    function toggleAll(source) {
        const checkboxes = document.querySelectorAll('input[name="selected_orders[]"]');
        checkboxes.forEach(checkbox => checkbox.checked = source.checked);
    }
    </script>
</body>
</html>