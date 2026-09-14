<?php
require_once '../config.php';
requireAdmin();

$db = getDB();

// Site config (for title)
$stmt = $db->query("SELECT site_title FROM site_config LIMIT 1");
$config = $stmt->fetch();

// --- 1. Fetch available event dates from orders ---
$events_stmt = $db->query("
    SELECT 
        DATE(pickup_time) AS event_date,
        COUNT(DISTINCT id) AS order_count,
        SUM(total_amount) AS total_revenue
    FROM orders
    WHERE pickup_time IS NOT NULL AND status != 'cancelled'
    GROUP BY DATE(pickup_time)
    ORDER BY event_date DESC
");
$available_events = $events_stmt->fetchAll(PDO::FETCH_ASSOC);

// Determine selected event date
$selected_event = null;
if (isset($_GET['event_date'])) {
    $requested_date = trim($_GET['event_date']);
    if ($requested_date === 'all') {
        $selected_event = 'all';
    } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $requested_date)) {
        $selected_event = $requested_date;
    }
}

// Default to the latest available event date if none is explicitly requested
if ($selected_event === null && !empty($available_events)) {
    $selected_event = $available_events[0]['event_date'];
}

// --- 2. Get total orders for each menu item for the selected event ---
$item_totals = [];
$event_order_count = 0;
$event_total_revenue = 0.0;

if ($selected_event === 'all') {
    $stmt = $db->query("
        SELECT 
            mi.name, 
            SUM(oi.quantity) as total_quantity
        FROM order_items oi
        JOIN menu_items mi ON oi.menu_item_id = mi.id
        JOIN orders o ON oi.order_id = o.id
        WHERE o.status != 'cancelled'
        GROUP BY mi.id, mi.name
        ORDER BY total_quantity DESC
    ");
    $item_totals = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $sum_stmt = $db->query("
        SELECT COUNT(DISTINCT id) as order_count, SUM(total_amount) as total_revenue
        FROM orders
        WHERE status != 'cancelled'
    ");
    $summary = $sum_stmt->fetch(PDO::FETCH_ASSOC);
    $event_order_count = (int)($summary['order_count'] ?? 0);
    $event_total_revenue = (float)($summary['total_revenue'] ?? 0);
} elseif ($selected_event !== null) {
    $stmt = $db->prepare("
        SELECT 
            mi.name, 
            SUM(oi.quantity) as total_quantity
        FROM order_items oi
        JOIN menu_items mi ON oi.menu_item_id = mi.id
        JOIN orders o ON oi.order_id = o.id
        WHERE DATE(o.pickup_time) = ?
          AND o.status != 'cancelled'
        GROUP BY mi.id, mi.name
        ORDER BY total_quantity DESC
    ");
    $stmt->execute([$selected_event]);
    $item_totals = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $sum_stmt = $db->prepare("
        SELECT COUNT(DISTINCT id) as order_count, SUM(total_amount) as total_revenue
        FROM orders
        WHERE DATE(pickup_time) = ?
          AND status != 'cancelled'
    ");
    $sum_stmt->execute([$selected_event]);
    $summary = $sum_stmt->fetch(PDO::FETCH_ASSOC);
    $event_order_count = (int)($summary['order_count'] ?? 0);
    $event_total_revenue = (float)($summary['total_revenue'] ?? 0);
}

// --- 2. Calculate required ingredients ---
$shredded_mozzarella = 0;
$fresh_mozzarella = 0;
$pepperoni_grams = 0;
$fresh_basil_fractional = 0.0;
$total_pizzas = 0;

foreach ($item_totals as $item) {
    $quantity = (int)$item['total_quantity'];
    $total_pizzas += $quantity;

    switch ($item['name']) {
        case 'Pepperoni Pizza':
            $shredded_mozzarella += (85 * $quantity);
            $pepperoni_grams += (24 * $quantity);
            $fresh_basil_fractional += (1/74 * $quantity);
            break;
        case 'Cheese Pizza':
            $shredded_mozzarella += (100 * $quantity);
            $fresh_basil_fractional += (1/74 * $quantity);
            break;
        case 'Margherita Pizza':
            $fresh_mozzarella += (85 * $quantity);
            $fresh_basil_fractional += (1/9 * $quantity);
            break;
    }
}

// Canned tomatoes (total pizzas × 50g)
$canned_tomato = 50 * $total_pizzas;

// Flour (185g per pizza)
$flour_grams = 185 * $total_pizzas;

// Basil rounded up to package units
$fresh_basil_packages = (int)ceil($fresh_basil_fractional);

// Ingredient report
$ingredient_report = [
    __('shredded_mozzarella') => ['amount' => $shredded_mozzarella, 'unit' => 'weight'],
    __('fresh_mozzarella') => ['amount' => $fresh_mozzarella, 'unit' => 'weight'],
    __('canned_tomato') => ['amount' => $canned_tomato, 'unit' => 'weight'],
    __('flour') => ['amount' => $flour_grams, 'unit' => 'weight'],
    __('pepperoni') => ['amount' => $pepperoni_grams, 'unit' => 'weight'],
    __('fresh_basil') => ['amount' => $fresh_basil_packages, 'unit' => __('packages')]
];

// g → kg conversion
function formatWeight($grams) {
    if ($grams >= 1000) {
        return number_format($grams / 1000, 2) . ' kg';
    }
    return number_format($grams) . ' g';
}
?>
<!DOCTYPE html>
<html lang="<?= $_SESSION['lang'] ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('admin_reports') ?> - <?= htmlspecialchars($config['site_title'] ?? 'Admin') ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Arial', sans-serif; background: #f5f5f5; color: #333; }
        .header { background: #2c3e50; color: white; padding: 20px 0; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header-content { max-width: 1200px; margin: 0 auto; padding: 0 20px; display: flex; justify-content: space-between; align-items: center; }
        .header h1 { font-size: 1.8em; }
        .nav-links { display: flex; align-items: center; }
        .nav-links a { color: white; text-decoration: none; margin-left: 20px; padding: 8px 16px; border-radius: 5px; transition: background 0.3s; }
        .nav-links a:hover { background: rgba(255,255,255,0.2); }
        .container { max-width: 1200px; margin: 30px auto; padding: 0 20px; }
        .reports-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 30px; }
        .section { background: white; border-radius: 10px; padding: 30px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .section h2 { color: #2c3e50; margin-bottom: 20px; font-size: 1.5em; border-bottom: 3px solid #3498db; padding-bottom: 10px; }
        .table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .table th, .table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #ddd; }
        .table th { background: #34495e; color: white; font-weight: bold; }
        .table tr:last-child td { border-bottom: none; }
        .table tr:hover { background: #f8f9fa; }
        .table td:last-child { font-weight: bold; text-align: right; color: #2c3e50; }
        .no-data { text-align: center; padding: 40px; color: #7f8c8d; font-style: italic; }
        .lang-selector { margin-left: 20px; }
        .lang-selector select { padding: 5px; border-radius: 5px; border: none; background: rgba(255,255,255,0.2); color: white; cursor: pointer; }
        .lang-selector select option { background: #2c3e50; color: white; }
        .filter-card {
            background: white;
            border-radius: 10px;
            padding: 20px 25px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        .filter-form {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 1.05em;
        }
        .filter-form label {
            color: #2c3e50;
            font-weight: bold;
        }
        .filter-form select {
            padding: 9px 14px;
            border-radius: 6px;
            border: 1px solid #bdc3c7;
            font-size: 1em;
            background: #fff;
            color: #2c3e50;
            font-weight: 500;
            cursor: pointer;
            outline: none;
            transition: border-color 0.2s;
        }
        .filter-form select:focus {
            border-color: #3498db;
        }
        .event-summary-badge {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }
        .badge-pill {
            background: #ecf0f1;
            color: #2c3e50;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.92em;
            font-weight: 600;
        }
        .badge-pill.primary {
            background: #e8f4fd;
            color: #2980b9;
        }
        @media (max-width: 768px) {
            .header-content { flex-direction: column; gap: 15px; }
            .nav-links { flex-wrap: wrap; justify-content: center; }
            .nav-links a { margin: 5px 10px; }
            .filter-card { flex-direction: column; align-items: flex-start; }
            .event-summary-badge { width: 100%; }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1>📊 <?= __('admin_reports') ?></h1>
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
        <!-- Event Selector -->
        <div class="filter-card">
            <form method="GET" action="" class="filter-form">
                <label for="event_date">📅 <?= __('select_event') ?>:</label>
                <select name="event_date" id="event_date" onchange="this.form.submit()">
                    <?php if (empty($available_events)): ?>
                        <option value=""><?= __('no_events_found') ?></option>
                    <?php else: ?>
                        <?php foreach ($available_events as $index => $ev): ?>
                            <?php 
                                $is_selected = ($selected_event === $ev['event_date']);
                                $label_suffix = ($index === 0) ? ' (' . __('latest_event') . ')' : '';
                            ?>
                            <option value="<?= htmlspecialchars($ev['event_date']) ?>" <?= $is_selected ? 'selected' : '' ?>>
                                <?= date('Y/m/d', strtotime($ev['event_date'])) ?><?= $label_suffix ?> — <?= $ev['order_count'] ?> <?= __('orders_count') ?>
                            </option>
                        <?php endforeach; ?>
                        <option value="all" <?= $selected_event === 'all' ? 'selected' : '' ?>>
                            🌐 <?= __('all_events') ?>
                        </option>
                    <?php endif; ?>
                </select>
                <noscript><button type="submit" style="padding: 8px 12px; cursor: pointer;"><?= __('apply') ?></button></noscript>
            </form>

            <?php if ($selected_event !== null): ?>
            <div class="event-summary-badge">
                <span class="badge-pill primary">
                    📅 <?= $selected_event === 'all' ? __('all_events') : date('Y/m/d', strtotime($selected_event)) ?>
                </span>
                <span class="badge-pill">
                    🍕 <?= number_format($total_pizzas) ?> <?= __('pizzas_sold') ?>
                </span>
                <span class="badge-pill">
                    📋 <?= number_format($event_order_count) ?> <?= __('orders_count') ?>
                </span>
                <span class="badge-pill">
                    💰 ¥<?= number_format($event_total_revenue, 0) ?>
                </span>
            </div>
            <?php endif; ?>
        </div>

        <div class="reports-grid">
            <!-- Menu Totals -->
            <div class="section">
                <h2><?= __('menu_totals') ?></h2>
                <?php if (!empty($item_totals)): ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th><?= __('menu_item') ?></th>
                                <th style="text-align: right;"><?= __('total_orders_count') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($item_totals as $item): ?>
                                <tr>
                                    <td><?= htmlspecialchars($item['name']) ?></td>
                                    <td><?= number_format($item['total_quantity']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="no-data"><?= __('no_report_data') ?></p>
                <?php endif; ?>
            </div>

            <!-- Required Ingredients -->
            <div class="section">
                <h2><?= __('required_ingredients') ?></h2>
                <?php if (!empty($item_totals)): ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th><?= __('ingredient') ?></th>
                                <th style="text-align: right;"><?= __('required_amount') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ingredient_report as $name => $data): ?>
                                <tr>
                                    <td><?= htmlspecialchars($name) ?></td>
                                    <td>
                                        <?php 
                                        if ($data['unit'] === 'weight') {
                                            echo htmlspecialchars(formatWeight($data['amount']));
                                        } else {
                                            echo number_format($data['amount']) . ' ' . $data['unit'];
                                        }
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="no-data"><?= __('no_ingredient_data') ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
