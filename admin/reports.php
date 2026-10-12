<?php
declare(strict_types=1);

require_once '../config.php';
require_once __DIR__ . '/../helpers.php';
requireAdmin();

$db = getDB();

// Site config (for title & event date)
$config = getSiteConfig();

// --- 1. Fetch available event dates from orders ---
$events_stmt = $db->query("
    SELECT 
        DATE(pickup_time) AS event_date,
        COUNT(DISTINCT CASE WHEN (status != 'comped' AND total_amount > 0) THEN id END) AS order_count,
        SUM(total_amount) AS total_revenue
    FROM orders
    WHERE pickup_time IS NOT NULL AND status != 'cancelled'
    GROUP BY DATE(pickup_time)
    ORDER BY event_date DESC
");
$available_events = $events_stmt ? $events_stmt->fetchAll(PDO::FETCH_ASSOC) : [];

$active_event_date = !empty($config['event_date']) ? (string)$config['event_date'] : null;
if ($active_event_date !== null) {
    $has_active = false;
    foreach ($available_events as $ev) {
        if ($ev['event_date'] === $active_event_date) {
            $has_active = true;
            break;
        }
    }
    if (!$has_active) {
        array_unshift($available_events, [
            'event_date' => $active_event_date,
            'order_count' => 0,
            'total_revenue' => 0.0,
        ]);
    }
}

// Determine selected event date
$selected_event = null;
if (isset($_GET['event_date'])) {
    $requested_date = trim((string)$_GET['event_date']);
    if ($requested_date === 'all') {
        $selected_event = 'all';
    } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $requested_date)) {
        $selected_event = $requested_date;
    }
}

// Default to the active event date or latest available event date if none is explicitly requested
if ($selected_event === null) {
    if ($active_event_date !== null) {
        $selected_event = $active_event_date;
    } elseif (!empty($available_events)) {
        $selected_event = $available_events[0]['event_date'];
    }
}

// Determine status filter
$selected_status = isset($_GET['status']) ? trim((string)$_GET['status']) : 'all';
$valid_statuses = ['all', 'completed', 'pending', 'confirmed', 'preparing', 'ready', 'comped'];
if (!in_array($selected_status, $valid_statuses, true)) {
    $selected_status = 'all';
}

// Build base event date condition
$dateParams = [];
if ($selected_event === 'all') {
    $baseDateCondition = "1=1";
} else {
    $is_active_or_latest = ($selected_event === $active_event_date || (!empty($available_events) && $selected_event === $available_events[0]['event_date']));
    if ($is_active_or_latest) {
        $baseDateCondition = "(DATE(o.pickup_time) = ? OR (o.pickup_time IS NULL AND (o.status = 'comped' OR o.total_amount = 0)))";
    } else {
        $baseDateCondition = "DATE(o.pickup_time) = ?";
    }
    $dateParams = [$selected_event];
}

// --- 2. Calculate overall event metrics (independent of active status filter for full visibility) ---
$orderSumSql = "
    SELECT 
        COUNT(id) AS total_orders,
        COUNT(CASE WHEN (status = 'comped' OR total_amount = 0) THEN 1 END) AS comped_orders,
        COUNT(CASE WHEN status = 'completed' AND total_amount > 0 THEN 1 END) AS completed_orders,
        COUNT(CASE WHEN status = 'pending' THEN 1 END) AS pending_orders,
        COALESCE(SUM(total_amount), 0.0) AS total_revenue,
        COALESCE(SUM(CASE WHEN status = 'completed' THEN total_amount ELSE 0.0 END), 0.0) AS completed_revenue
    FROM orders o
    WHERE $baseDateCondition
      AND o.status != 'cancelled'
";
$orderSumStmt = $db->prepare($orderSumSql);
$orderSumStmt->execute($dateParams);
$eventMetrics = $orderSumStmt->fetch(PDO::FETCH_ASSOC) ?: [];

$pizzaSumSql = "
    SELECT 
        COALESCE(SUM(oi.quantity), 0) AS total_pizzas,
        COALESCE(SUM(CASE WHEN (o.status = 'comped' OR o.total_amount = 0) THEN oi.quantity ELSE 0 END), 0) AS comped_pizzas,
        COALESCE(SUM(CASE WHEN o.status = 'completed' AND o.total_amount > 0 THEN oi.quantity ELSE 0 END), 0) AS completed_pizzas,
        COALESCE(SUM(CASE WHEN o.status = 'pending' AND o.total_amount > 0 THEN oi.quantity ELSE 0 END), 0) AS pending_pizzas
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    WHERE $baseDateCondition
      AND o.status != 'cancelled'
";
$pizzaSumStmt = $db->prepare($pizzaSumSql);
$pizzaSumStmt->execute($dateParams);
$pizzaMetrics = $pizzaSumStmt->fetch(PDO::FETCH_ASSOC) ?: [];

$all_total_orders    = (int)($eventMetrics['total_orders'] ?? 0);
$all_total_pizzas    = (int)($pizzaMetrics['total_pizzas'] ?? 0);
$all_comped_pizzas   = (int)($pizzaMetrics['comped_pizzas'] ?? 0);
$all_completed_pizzas = (int)($pizzaMetrics['completed_pizzas'] ?? 0);
$all_pending_pizzas  = (int)($pizzaMetrics['pending_pizzas'] ?? 0);
$all_total_revenue   = (float)($eventMetrics['total_revenue'] ?? 0.0);
$all_completed_revenue = (float)($eventMetrics['completed_revenue'] ?? 0.0);

// --- 3. Filter condition for menu items, ingredients, and itemized orders ---
$statusFilterSql = match ($selected_status) {
    'completed' => "o.status = 'completed' AND o.total_amount > 0",
    'pending'   => "o.status = 'pending'",
    'confirmed' => "o.status = 'confirmed'",
    'preparing' => "o.status = 'preparing'",
    'ready'     => "o.status = 'ready'",
    'comped'    => "(o.status = 'comped' OR o.total_amount = 0) AND o.status != 'cancelled'",
    default     => "o.status != 'cancelled'",
};

// Item totals for menu items table & ingredients based on filter
$stmt = $db->prepare("
    SELECT 
        mi.name, 
        SUM(oi.quantity) as total_quantity
    FROM order_items oi
    JOIN menu_items mi ON oi.menu_item_id = mi.id
    JOIN orders o ON oi.order_id = o.id
    WHERE $baseDateCondition
      AND $statusFilterSql
    GROUP BY mi.id, mi.name
    ORDER BY total_quantity DESC
");
$stmt->execute($dateParams);
$item_totals = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Filtered summary
$filteredSumSql = "
    SELECT 
        COUNT(DISTINCT o.id) as order_count, 
        COALESCE(SUM(o.total_amount), 0.0) as total_revenue
    FROM orders o
    WHERE $baseDateCondition
      AND $statusFilterSql
";
$filteredSumStmt = $db->prepare($filteredSumSql);
$filteredSumStmt->execute($dateParams);
$filteredSummary = $filteredSumStmt->fetch(PDO::FETCH_ASSOC) ?: [];

$filtered_order_count = (int)($filteredSummary['order_count'] ?? 0);
$filtered_revenue     = (float)($filteredSummary['total_revenue'] ?? 0.0);

// --- 4. Calculate required ingredients ---
$shredded_mozzarella = 0;
$fresh_mozzarella = 0;
$pepperoni_grams = 0;
$fresh_basil_fractional = 0.0;
$filtered_pizzas = 0;

foreach ($item_totals as $item) {
    $quantity = (int)$item['total_quantity'];
    $filtered_pizzas += $quantity;

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

// Canned tomatoes (50g per pizza)
$canned_tomato = 50 * $filtered_pizzas;

// Flour (185g per pizza)
$flour_grams = 185 * $filtered_pizzas;

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

// --- 5. Fetch itemized order records for the breakdown table ---
$ordersQuerySql = "
    SELECT 
        o.id,
        o.order_number,
        o.status,
        o.total_amount,
        o.pickup_time,
        o.notes,
        u.first_name,
        u.last_name,
        u.email,
        GROUP_CONCAT(CONCAT(mi.name, ' x', oi.quantity) SEPARATOR ', ') AS items_summary,
        COALESCE(SUM(oi.quantity), 0) AS order_pizza_count
    FROM orders o
    JOIN users u ON o.user_id = u.id
    LEFT JOIN order_items oi ON o.id = oi.order_id
    LEFT JOIN menu_items mi ON oi.menu_item_id = mi.id
    WHERE $baseDateCondition
      AND $statusFilterSql
    GROUP BY o.id
    ORDER BY o.pickup_time ASC, o.id ASC
";
$ordersStmt = $db->prepare($ordersQuerySql);
$ordersStmt->execute($dateParams);
$event_orders = $ordersStmt->fetchAll(PDO::FETCH_ASSOC);

// g → kg conversion
function formatWeight(float|int $grams): string {
    if ($grams >= 1000) {
        return number_format($grams / 1000, 2) . ' kg';
    }
    return number_format($grams) . ' g';
}

$page_title = __('admin_reports');
require_once __DIR__ . '/includes/header.php';
?>
        <style>
            .badge-pill.success {
                background: #e8f8f5;
                color: #27ae60;
                border: 1px solid #a3e4d7;
            }
            .badge-pill.warning {
                background: #fef9e7;
                color: #d35400;
                border: 1px solid #f9e79f;
            }
            .badge-pill.purple {
                background: #f4ecf7;
                color: #8e44ad;
                border: 1px solid #d2b4de;
            }
            .badge-pill.info {
                background: #eaf2f8;
                color: #2980b9;
                border: 1px solid #aed6f1;
            }
            .status-badge-inline {
                display: inline-block;
                padding: 3px 8px;
                border-radius: 4px;
                font-size: 0.82em;
                font-weight: bold;
                text-transform: capitalize;
            }
            .status-badge-inline.completed { background: #d4edda; color: #155724; }
            .status-badge-inline.pending { background: #fff3cd; color: #856404; }
            .status-badge-inline.confirmed { background: #cce5ff; color: #004085; }
            .status-badge-inline.preparing { background: #e2e3e5; color: #383d41; }
            .status-badge-inline.ready { background: #d1ecf1; color: #0c5460; }
            .status-badge-inline.comped { background: #f3e5f5; color: #4a148c; }
            .status-badge-inline.cancelled { background: #f8d7da; color: #721c24; }
        </style>

        <!-- Event Selector & Status Filters -->
        <div class="filter-card">
            <form method="GET" action="" class="filter-form" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: center;">
                <div style="display: flex; align-items: center; gap: 8px;">
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
                                <option value="<?= htmlspecialchars((string)$ev['event_date']) ?>" <?= $is_selected ? 'selected' : '' ?>>
                                    <?= date('Y/m/d', strtotime((string)$ev['event_date'])) ?><?= $label_suffix ?> — <?= (int)$ev['order_count'] ?> <?= __('orders_count') ?>
                                </option>
                            <?php endforeach; ?>
                            <option value="all" <?= $selected_event === 'all' ? 'selected' : '' ?>>
                                🌐 <?= __('all_events') ?>
                            </option>
                        <?php endif; ?>
                    </select>
                </div>

                <div style="display: flex; align-items: center; gap: 8px;">
                    <label for="report_status">🔍 <?= __('status') ?>:</label>
                    <select name="status" id="report_status" onchange="this.form.submit()">
                        <option value="all" <?= $selected_status === 'all' ? 'selected' : '' ?>>
                            <?= __('all_statuses') ?> (<?= $all_total_pizzas ?> <?= __('total_pizzas_to_prep') ?>)
                        </option>
                        <option value="completed" <?= $selected_status === 'completed' ? 'selected' : '' ?>>
                            ✓ <?= __('status_completed') ?> (<?= $all_completed_pizzas ?> <?= __('pizzas_sold') ?>)
                        </option>
                        <?php if ($all_pending_pizzas > 0): ?>
                        <option value="pending" <?= $selected_status === 'pending' ? 'selected' : '' ?>>
                            ⏳ <?= __('status_pending') ?> (<?= $all_pending_pizzas ?>)
                        </option>
                        <?php endif; ?>
                        <?php if ($all_comped_pizzas > 0): ?>
                        <option value="comped" <?= $selected_status === 'comped' ? 'selected' : '' ?>>
                            🎁 <?= __('status_comped') ?> (<?= $all_comped_pizzas ?>)
                        </option>
                        <?php endif; ?>
                    </select>
                </div>
                <noscript><button type="submit" style="padding: 8px 12px; cursor: pointer;"><?= __('apply') ?></button></noscript>
            </form>

            <?php if ($selected_event !== null): ?>
            <div class="event-summary-badge">
                <span class="badge-pill primary">
                    📅 <?= $selected_event === 'all' ? __('all_events') : date('Y/m/d', strtotime($selected_event)) ?>
                </span>

                <?php if ($selected_status === 'completed'): ?>
                    <span class="badge-pill success">
                        ✓ <?= number_format($filtered_pizzas) ?> <?= __('pizzas_sold') ?>
                    </span>
                    <span class="badge-pill">
                        📋 <?= number_format($filtered_order_count) ?> <?= __('orders_count') ?>
                    </span>
                    <span class="badge-pill">
                        💰 ¥<?= number_format($filtered_revenue, 0) ?>
                    </span>
                <?php else: ?>
                    <!-- Overall Event Breakdown -->
                    <span class="badge-pill success" title="Completed orders that generated revenue">
                        ✓ <?= number_format($all_completed_pizzas) ?> <?= __('pizzas_sold') ?> (<?= __('status_completed') ?>)
                    </span>

                    <?php if ($all_pending_pizzas > 0): ?>
                    <span class="badge-pill warning" title="Pending orders still awaiting completion">
                        ⏳ <?= number_format($all_pending_pizzas) ?> <?= __('status_pending') ?>
                    </span>
                    <?php endif; ?>

                    <?php if ($all_comped_pizzas > 0): ?>
                    <span class="badge-pill purple" title="Free / complimentary pizzas">
                        🎁 <?= number_format($all_comped_pizzas) ?> <?= __('comped_pizzas') ?>
                    </span>
                    <?php endif; ?>

                    <span class="badge-pill info" style="font-weight: bold;" title="Grand total of all pizzas to bake/prepare">
                        📦 <?= number_format($all_total_pizzas) ?> <?= __('total_pizzas_to_prep') ?>
                    </span>

                    <span class="badge-pill">
                        📋 <?= number_format($all_total_orders) ?> <?= __('orders_count') ?>
                    </span>
                    <span class="badge-pill">
                        💰 ¥<?= number_format($all_total_revenue, 0) ?>
                    </span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="reports-grid">
            <!-- Menu Totals -->
            <div class="section">
                <h2>
                    <?= __('menu_totals') ?>
                    <?php if ($selected_status !== 'all'): ?>
                        <small style="font-size: 0.7em; color: #7f8c8d; font-weight: normal;">(<?= translateStatus($selected_status) ?>)</small>
                    <?php endif; ?>
                </h2>
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
                                    <td><?= htmlspecialchars((string)$item['name']) ?></td>
                                    <td style="text-align: right; font-weight: bold;"><?= number_format((float)$item['total_quantity']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr style="background: #f8fafc; font-weight: bold; border-top: 2px solid #cbd5e1;">
                                <td><?= __('total_column') ?></td>
                                <td style="text-align: right; font-size: 1.05em; color: #2c3e50;"><?= number_format($filtered_pizzas) ?></td>
                            </tr>
                        </tfoot>
                    </table>
                <?php else: ?>
                    <p class="no-data"><?= __('no_report_data') ?></p>
                <?php endif; ?>
            </div>

            <!-- Required Ingredients -->
            <div class="section">
                <h2>
                    <?= __('required_ingredients') ?>
                    <?php if ($selected_status !== 'all'): ?>
                        <small style="font-size: 0.7em; color: #7f8c8d; font-weight: normal;">(<?= translateStatus($selected_status) ?>)</small>
                    <?php endif; ?>
                </h2>
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
                                    <td><?= htmlspecialchars((string)$name) ?></td>
                                    <td style="text-align: right; font-weight: bold;">
                                        <?php 
                                        if ($data['unit'] === 'weight') {
                                            echo htmlspecialchars(formatWeight((float)$data['amount']));
                                        } else {
                                            echo number_format((float)$data['amount']) . ' ' . htmlspecialchars((string)$data['unit']);
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

        <!-- Itemized Event Orders Breakdown Table -->
        <div class="section" style="margin-top: 30px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 10px;">
                <h2 style="margin: 0;">
                    📋 <?= __('order_management') ?> — <?= $selected_event === 'all' ? __('all_events') : date('Y/m/d', strtotime((string)$selected_event)) ?>
                    <span style="font-size: 0.75em; color: #64748b; font-weight: normal;">(<?= count($event_orders) ?> <?= __('orders_count') ?>)</span>
                </h2>
                <a href="orders.php<?= $selected_event !== 'all' ? '?pickup_time=' . urlencode((string)$selected_event) : '' ?>" class="btn" style="background: #3498db; color: white; text-decoration: none; padding: 6px 12px; font-size: 0.88em; border-radius: 4px;">
                    🔍 <?= __('order_management') ?>
                </a>
            </div>

            <?php if (empty($event_orders)): ?>
                <p class="no-data"><?= __('no_orders_found') ?></p>
            <?php else: ?>
                <table class="table" style="font-size: 0.95em;">
                    <thead>
                        <tr>
                            <th><?= __('order_number') ?></th>
                            <th><?= __('customer') ?></th>
                            <th><?= __('items') ?></th>
                            <th style="text-align: center;"><?= __('quantity') ?></th>
                            <th style="text-align: right;"><?= __('total_amount') ?></th>
                            <th style="text-align: center;"><?= __('status') ?></th>
                            <th><?= __('pickup_time') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($event_orders as $ord): 
                            $isCompedOrd = ($ord['status'] === 'comped' || (float)$ord['total_amount'] === 0.0);
                            $badgeClass = $isCompedOrd ? 'comped' : htmlspecialchars((string)$ord['status']);
                        ?>
                            <tr>
                                <td>
                                    <a href="order_details.php?id=<?= (int)$ord['id'] ?>" style="color: #2980b9; font-weight: bold; text-decoration: none;">
                                        <?= htmlspecialchars((string)$ord['order_number']) ?>
                                    </a>
                                </td>
                                <td>
                                    <?= htmlspecialchars((string)$ord['first_name'] . ' ' . (string)$ord['last_name']) ?><br>
                                    <small style="color: #64748b;"><?= htmlspecialchars((string)$ord['email']) ?></small>
                                </td>
                                <td><?= htmlspecialchars((string)($ord['items_summary'] ?? '')) ?></td>
                                <td style="text-align: center; font-weight: bold;"><?= (int)$ord['order_pizza_count'] ?></td>
                                <td style="text-align: right; font-weight: bold;">
                                    <?php if ($isCompedOrd): ?>
                                        <span style="color: #8e44ad;">¥0 (Comp)</span>
                                    <?php else: ?>
                                        ¥<?= number_format((float)$ord['total_amount'], 0) ?>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: center;">
                                    <span class="status-badge-inline <?= $badgeClass ?>">
                                        <?= $isCompedOrd && $ord['status'] !== 'comped' ? "Comp'd (" . htmlspecialchars((string)$ord['status']) . ")" : htmlspecialchars(translateStatus((string)$ord['status'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <?= !empty($ord['pickup_time']) ? date('Y/m/d H:i', strtotime((string)$ord['pickup_time'])) : '<em style="color:#94a3b8;">Unscheduled</em>' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
<?php
require_once __DIR__ . '/includes/footer.php';
