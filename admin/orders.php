<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../helpers.php';

requireAdmin();

$container = getServiceContainer();
$config = getSiteConfig();
$db = $container->getPdo();
$manageSlotsUseCase = $container->getManagePickupTimeSlotsUseCase();

$allSlots = $manageSlotsUseCase->getAllSlots(1);
$slotsById = [];
$slotsByStartTime = [];
foreach ($allSlots as $slot) {
    if ($slot->getId() !== null) {
        $slotsById[$slot->getId()] = $slot;
    }
    $slotsByStartTime[$slot->getSlotTime()] = $slot;
}

function formatAdminPickupDisplay(?string $pickupDatetime, ?int $slotId, array $slotsById, array $slotsByStartTime): string {
    if ($pickupDatetime === null || trim($pickupDatetime) === '') {
        return 'N/A';
    }
    $ts = strtotime($pickupDatetime);
    if ($ts === false) {
        return $pickupDatetime;
    }
    $timeKey = date('H:i', $ts);
    $matchedSlot = ($slotId !== null && $slotId > 0 && isset($slotsById[$slotId]))
        ? $slotsById[$slotId]
        : ($slotsByStartTime[$timeKey] ?? null);

    $timeRange = $matchedSlot !== null ? $matchedSlot->getDisplayName() : $timeKey;
    return date('Y/m/d', $ts) . ' ' . $timeRange;
}

function getOrdersSortUrl(string $column, string $activeSort, string $activeOrder, string $status, string $search): string {
    $nextOrder = ($activeSort === $column && $activeOrder === 'ASC') ? 'DESC' : 'ASC';
    if ($column === 'pickup_time' && $activeSort !== 'pickup_time') {
        $nextOrder = 'ASC';
    }
    $query = [
        'status' => $status,
        'search' => $search,
        'sort'   => $column,
        'order'  => $nextOrder,
    ];
    return 'orders.php?' . http_build_query(array_filter($query, static fn($v) => $v !== ''));
}

// Handle POST actions (status update / bulk actions)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken((string)($_POST['csrf_token'] ?? ''))) {
        $error_message = __('invalid_csrf_token') ?: 'Invalid CSRF token.';
    } elseif (isset($_POST['update_status'])) {
        $order_id = (int)($_POST['order_id'] ?? 0);
        $new_status = (string)($_POST['status'] ?? '');
        
        if ($order_id && in_array($new_status, ['pending', 'confirmed', 'preparing', 'ready', 'completed', 'cancelled'], true)) {
            try {
                $prevStmt = $db->prepare("SELECT status FROM orders WHERE id = ?");
                $prevStmt->execute([$order_id]);
                $prevStatus = (string)$prevStmt->fetchColumn();

                $stmt = $db->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$new_status, $order_id]);
                if ($new_status === 'cancelled' && $prevStatus !== 'cancelled') {
                    $container->getSendOrderNotificationUseCase()->execute($order_id, 'cancelled');
                }
                $success_message = __('status_update_success');
            } catch (Exception $e) {
                $error_message = __('status_update_error');
            }
        }
    } elseif (isset($_POST['bulk_action'])) {
        $action = (string)$_POST['bulk_action'];
        $selected_orders = isset($_POST['selected_orders']) && is_array($_POST['selected_orders']) ? $_POST['selected_orders'] : [];
        
        if (!empty($selected_orders) && in_array($action, ['confirmed', 'preparing', 'ready', 'completed', 'cancelled'], true)) {
            try {
                $placeholders = str_repeat('?,', count($selected_orders) - 1) . '?';
                $stmt = $db->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id IN ($placeholders)");
                $stmt->execute(array_merge([$action], $selected_orders));
                if ($action === 'cancelled') {
                    $notificationUseCase = $container->getSendOrderNotificationUseCase();
                    foreach ($selected_orders as $cancelledOrderId) {
                        $notificationUseCase->execute((int)$cancelledOrderId, 'cancelled');
                    }
                }
                $success_message = __('bulk_action_success');
            } catch (Exception $e) {
                $error_message = __('bulk_action_error');
            }
        }
    }
}

// Filtering and sorting (default to 'pending' when visiting without a status parameter)
$filter_status = isset($_GET['status']) ? (string)$_GET['status'] : 'pending';
$search = (string)($_GET['search'] ?? '');

$raw_sort = (string)($_GET['sort'] ?? 'created_at');
$raw_order = isset($_GET['order']) && in_array(strtoupper((string)$_GET['order']), ['ASC', 'DESC'], true)
    ? strtoupper((string)$_GET['order'])
    : null;

if ($raw_sort === 'pickup_time_desc') {
    $sort_by = 'pickup_time';
    $sort_order = 'DESC';
} elseif ($raw_sort === 'pickup_time_asc') {
    $sort_by = 'pickup_time';
    $sort_order = 'ASC';
} elseif ($raw_sort === 'pickup_time') {
    $sort_by = 'pickup_time';
    $sort_order = $raw_order ?? 'ASC';
} else {
    $valid_columns = ['created_at', 'order_number', 'total_amount', 'status', 'first_name'];
    $sort_by = in_array($raw_sort, $valid_columns, true) ? $raw_sort : 'created_at';
    $sort_order = $raw_order ?? 'DESC';
}

// Build query
$where_conditions = [];
$params = [];

if ($filter_status !== '') {
    $where_conditions[] = "o.status = ?";
    $params[] = $filter_status;
}

if ($search !== '') {
    $where_conditions[] = "(o.order_number LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

$valid_sorts = [
    'created_at'   => 'o.created_at',
    'order_number' => 'o.order_number',
    'total_amount' => 'o.total_amount',
    'status'       => 'o.status',
    'first_name'   => 'u.first_name',
    'pickup_time'  => 'o.pickup_time'
];

$orderByClause = match ($sort_by) {
    'pickup_time'  => "CASE WHEN o.pickup_time IS NULL THEN 1 ELSE 0 END, o.pickup_time $sort_order, o.id ASC",
    'first_name'   => "u.first_name $sort_order, u.last_name $sort_order, o.id DESC",
    'total_amount' => "o.total_amount $sort_order, o.id DESC",
    'status'       => "o.status $sort_order, o.created_at DESC",
    'order_number' => "o.order_number $sort_order",
    default        => "o.created_at $sort_order, o.id $sort_order",
};

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
    if ($row['pickup_time']) {
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
    ORDER BY $orderByClause
");
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Get status counts for filters (ensure 'pending' is always present so the default filter option is selectable)
$stmt = $db->query("SELECT status, COUNT(*) as count FROM orders GROUP BY status");
$status_counts = ['pending' => 0];
while ($row = $stmt->fetch()) {
    $status_counts[(string)$row['status']] = (int)$row['count'];
}
$activeFilterParts = [];
if ($filter_status !== '') {
    $activeFilterParts[] = __('status') . ': ' . __('status_' . $filter_status);
} else {
    $activeFilterParts[] = __('status') . ': ' . __('all_statuses');
}
if ($search !== '') {
    $activeFilterParts[] = __('search') . ': "' . $search . '"';
}
if ($sort_by === 'pickup_time') {
    $activeFilterParts[] = __('sort_by') . ': ' . ($sort_order === 'ASC' ? __('sort_pickup_time_asc') : __('sort_pickup_time_desc'));
} elseif (isset($valid_sorts[$sort_by])) {
    $activeFilterParts[] = __('sort_by') . ': ' . __('sort_' . $sort_by) . ' (' . ($sort_order === 'ASC' ? __('sort_asc') : __('sort_desc')) . ')';
}
$activeFilterSummary = implode(' | ', $activeFilterParts);

$page_title = __('order_management');
require_once __DIR__ . '/includes/header.php';
?>

        <?php if (!empty($success_message)): ?>
            <div class="messages"><div class="message success"><?= htmlspecialchars((string)$success_message) ?></div></div>
        <?php endif; ?>
        <?php if (!empty($error_message)): ?>
            <div class="messages"><div class="message error"><?= htmlspecialchars((string)$error_message) ?></div></div>
        <?php endif; ?>

        <!-- PICK-UP TIME ORDERS SUMMARY TABLE -->
        <div class="orders-table">
            <h2 style="padding: 15px; background: #34495e; color: white; border-radius: 10px 10px 0 0;">📦 <?= htmlspecialchars((string)__('pickup_schedule_summary')) ?></h2>
            <table class="table">
                <thead>
                    <tr>
                        <th><?= htmlspecialchars((string)__('pickup_time')) ?></th>
                        <?php foreach ($pizza_types as $pizza): ?>
                            <th><?= htmlspecialchars((string)$pizza) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pickup_orders)): ?>
                        <tr>
                            <td colspan="<?= count($pizza_types) + 1 ?>" style="text-align:center; padding: 20px; color: #999;">
                                <?= htmlspecialchars((string)__('no_scheduled_pickups')) ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($pickup_orders as $pickup_time => $pizzas): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars(formatAdminPickupDisplay((string)$pickup_time, null, $slotsById, $slotsByStartTime)) ?></strong></td>
                                <?php foreach ($pizza_types as $pizza): ?>
                                    <td><?= isset($pizzas[$pizza]) ? (int)$pizzas[$pizza] : 0 ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <style>
            .table-sort-link {
                color: #ecf0f1 !important;
                text-decoration: none;
                display: inline-flex;
                align-items: center;
                gap: 4px;
            }
            .table-sort-link:hover {
                text-decoration: underline;
                color: #3498db !important;
            }
            .table-sort-link .sort-arrow {
                font-size: 0.85em;
                color: #f39c12;
            }
        </style>

        <!-- FILTERS & SEARCH -->
        <form method="get" class="controls" id="orderFilterForm">
            <div class="form-group">
                <label><?= htmlspecialchars((string)__('status')) ?></label>
                <select name="status" onchange="this.form.submit()">
                    <option value="" <?= $filter_status === '' ? 'selected' : '' ?>><?= htmlspecialchars((string)__('all_statuses')) ?></option>
                    <?php foreach ($status_counts as $status => $count): ?>
                        <option value="<?= htmlspecialchars((string)$status) ?>" <?= $filter_status === (string)$status ? 'selected' : '' ?>><?= htmlspecialchars((string)__('status_' . $status)) ?> (<?= (int)$count ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label><?= htmlspecialchars((string)__('search')) ?></label>
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="<?= htmlspecialchars((string)__('search_placeholder')) ?>">
            </div>
            <div class="form-group">
                <label><?= htmlspecialchars((string)__('sort_by')) ?></label>
                <select name="sort" id="sortSelect" onchange="handleSortSelectChange(this)">
                    <option value="created_at" <?= $sort_by === 'created_at' ? 'selected' : '' ?>><?= htmlspecialchars((string)__('sort_created_at')) ?></option>
                    <option value="pickup_time" <?= $sort_by === 'pickup_time' && $sort_order === 'ASC' ? 'selected' : '' ?>><?= htmlspecialchars((string)__('sort_pickup_time_asc')) ?></option>
                    <option value="pickup_time_desc" <?= $sort_by === 'pickup_time' && $sort_order === 'DESC' ? 'selected' : '' ?>><?= htmlspecialchars((string)__('sort_pickup_time_desc')) ?></option>
                    <option value="order_number" <?= $sort_by === 'order_number' ? 'selected' : '' ?>><?= htmlspecialchars((string)__('sort_order_number')) ?></option>
                    <option value="total_amount" <?= $sort_by === 'total_amount' ? 'selected' : '' ?>><?= htmlspecialchars((string)__('sort_total_amount')) ?></option>
                    <option value="status" <?= $sort_by === 'status' ? 'selected' : '' ?>><?= htmlspecialchars((string)__('sort_status')) ?></option>
                    <option value="first_name" <?= $sort_by === 'first_name' ? 'selected' : '' ?>><?= htmlspecialchars((string)__('sort_first_name')) ?></option>
                </select>
            </div>
            <div class="form-group">
                <label><?= htmlspecialchars((string)__('order_direction')) ?></label>
                <select name="order" id="orderSelect" onchange="this.form.submit()">
                    <option value="ASC" <?= $sort_order === 'ASC' ? 'selected' : '' ?>><?= htmlspecialchars((string)__('sort_asc')) ?></option>
                    <option value="DESC" <?= $sort_order === 'DESC' ? 'selected' : '' ?>><?= htmlspecialchars((string)__('sort_desc')) ?></option>
                </select>
            </div>
        </form>

        <!-- BULK ACTIONS & ORDERS TABLE -->
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken()) ?>">
            <div class="orders-table">
                <div class="bulk-actions" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                    <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                        <select name="bulk_action" class="status-select">
                            <option value=""><?= htmlspecialchars((string)__('bulk_action')) ?></option>
                            <option value="confirmed"><?= htmlspecialchars((string)__('mark_confirmed')) ?></option>
                            <option value="preparing"><?= htmlspecialchars((string)__('mark_preparing')) ?></option>
                            <option value="ready"><?= htmlspecialchars((string)__('mark_ready')) ?></option>
                            <option value="completed"><?= htmlspecialchars((string)__('mark_completed')) ?></option>
                            <option value="cancelled"><?= htmlspecialchars((string)__('mark_cancelled')) ?></option>
                        </select>
                        <button type="submit" class="btn"><?= htmlspecialchars((string)__('apply')) ?></button>
                    </div>
                    <?php if (!empty($orders)): ?>
                    <div>
                        <button type="button" 
                                class="btn btn-export-pdf" 
                                id="exportOrdersPdfBtn"
                                data-table-id="orders-table"
                                data-report-title="<?= htmlspecialchars((string)__('order_management')) ?>"
                                data-site-title="<?= htmlspecialchars((string)($config['site_title'] ?? "Fidel's Pizza Event")) ?>"
                                data-lang="<?= htmlspecialchars((string)($currentLang ?? 'ja')) ?>"
                                data-label-generating="<?= htmlspecialchars((string)__('generating_pdf')) ?>"
                                data-label-generated-at="<?= htmlspecialchars((string)__('pdf_generated_at')) ?>"
                                data-label-total-records="<?= htmlspecialchars((string)__('pdf_total_records')) ?>"
                                data-label-filters="<?= htmlspecialchars((string)__('pdf_active_filters')) ?>"
                                data-filter-info="<?= htmlspecialchars((string)$activeFilterSummary) ?>"
                                data-orientation="portrait"
                                data-filename="orders_<?= date('Y-m-d') ?>.pdf">
                            📄 <?= htmlspecialchars((string)__('export_pdf')) ?>
                        </button>
                    </div>
                    <?php endif; ?>
                </div>

                <table class="table" id="orders-table">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="select-all" onclick="toggleAll(this)"></th>
                            <th><a href="<?= htmlspecialchars(getOrdersSortUrl('order_number', $sort_by, $sort_order, $filter_status, $search)) ?>" class="table-sort-link" title="<?= htmlspecialchars((string)__('order_number')) ?>"><?= htmlspecialchars((string)__('order_number')) ?><?= $sort_by === 'order_number' ? '<span class="sort-arrow">' . ($sort_order === 'ASC' ? ' ▲' : ' ▼') . '</span>' : '' ?></a></th>
                            <th><a href="<?= htmlspecialchars(getOrdersSortUrl('first_name', $sort_by, $sort_order, $filter_status, $search)) ?>" class="table-sort-link" title="<?= htmlspecialchars((string)__('customer')) ?>"><?= htmlspecialchars((string)__('customer')) ?><?= $sort_by === 'first_name' ? '<span class="sort-arrow">' . ($sort_order === 'ASC' ? ' ▲' : ' ▼') . '</span>' : '' ?></a></th>
                            <th><?= htmlspecialchars((string)__('notes')) ?></th>
                            <th><?= htmlspecialchars((string)__('items')) ?></th>
                            <th><a href="<?= htmlspecialchars(getOrdersSortUrl('total_amount', $sort_by, $sort_order, $filter_status, $search)) ?>" class="table-sort-link" title="<?= htmlspecialchars((string)__('total_amount')) ?>"><?= htmlspecialchars((string)__('total_amount')) ?><?= $sort_by === 'total_amount' ? '<span class="sort-arrow">' . ($sort_order === 'ASC' ? ' ▲' : ' ▼') . '</span>' : '' ?></a></th>
                            <th><a href="<?= htmlspecialchars(getOrdersSortUrl('pickup_time', $sort_by, $sort_order, $filter_status, $search)) ?>" class="table-sort-link" title="<?= htmlspecialchars((string)__('pickup_time')) ?>"><?= htmlspecialchars((string)__('pickup_time')) ?><?= $sort_by === 'pickup_time' ? '<span class="sort-arrow">' . ($sort_order === 'ASC' ? ' ▲' : ' ▼') . '</span>' : '' ?></a></th>
                            <th><a href="<?= htmlspecialchars(getOrdersSortUrl('status', $sort_by, $sort_order, $filter_status, $search)) ?>" class="table-sort-link" title="<?= htmlspecialchars((string)__('status')) ?>"><?= htmlspecialchars((string)__('status')) ?><?= $sort_by === 'status' ? '<span class="sort-arrow">' . ($sort_order === 'ASC' ? ' ▲' : ' ▼') . '</span>' : '' ?></a></th>
                            <th><?= htmlspecialchars((string)__('actions')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($orders)): ?>
                            <tr>
                                <td colspan="9" style="text-align:center; padding: 20px; color: #999;"><?= htmlspecialchars((string)__('no_orders_found')) ?></td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td><input type="checkbox" name="selected_orders[]" value="<?= (int)$order['id'] ?>"></td>
                                    <td><strong><?= htmlspecialchars((string)$order['order_number']) ?></strong></td>
                                    <td>
                                        <?= htmlspecialchars((string)$order['first_name'] . ' ' . (string)$order['last_name']) ?><br>
                                        <small><?= htmlspecialchars((string)$order['email']) ?></small>
                                    </td>
                                    <td><small><?= htmlspecialchars((string)($order['notes'] ?? '')) ?: '<em style="color: #999;">' . htmlspecialchars((string)__('no_notes')) . '</em>' ?></small></td>
                                    <td><?= htmlspecialchars((string)($order['items'] ?? '')) ?></td>
                                    <td>¥<?= number_format((float)$order['total_amount'], 0) ?></td>
                                    <td><?= htmlspecialchars(formatAdminPickupDisplay(isset($order['pickup_time']) ? (string)$order['pickup_time'] : null, isset($order['pickup_slot_id']) ? (int)$order['pickup_slot_id'] : null, $slotsById, $slotsByStartTime)) ?></td>
                                    <td>
                                        <form method="post" style="display:inline;">
                                             <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken()) ?>">
                                            <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">
                                            <select name="status" class="status-select" onchange="this.form.submit()">
                                                <?php foreach (['pending','confirmed','preparing','ready','completed','cancelled'] as $status): ?>
                                                    <option value="<?= htmlspecialchars($status) ?>" <?= $order['status'] === $status ? 'selected' : '' ?>><?= htmlspecialchars((string)__('status_' . $status)) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <input type="hidden" name="update_status" value="1">
                                        </form>
                                    </td>
                                    <td>
                                        <a href="order_details.php?id=<?= (int)$order['id'] ?>" class="btn"><?= htmlspecialchars((string)__('view_details')) ?></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </form>

    <script>
    function toggleAll(source) {
        const checkboxes = document.querySelectorAll('input[name="selected_orders[]"]');
        checkboxes.forEach(checkbox => checkbox.checked = source.checked);
    }

    function handleSortSelectChange(select) {
        const orderSelect = document.getElementById('orderSelect');
        if (select.value === 'pickup_time_desc') {
            select.value = 'pickup_time';
            if (orderSelect) orderSelect.value = 'DESC';
        } else if (select.value === 'pickup_time') {
            if (orderSelect) orderSelect.value = 'ASC';
        }
        select.form.submit();
    }
    </script>
    <script src="js/html2pdf.bundle.min.js"></script>
    <script src="js/order-pdf-export.js"></script>
<?php
require_once __DIR__ . '/includes/footer.php';