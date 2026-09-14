<?php
declare(strict_types=1);

require_once '../config.php';
requireAdmin();

$config = getSiteConfig();
$db = getDB();

// Get dashboard statistics
$stats = [];

// Total orders (pending only)
$stmt = $db->query("SELECT COUNT(*) as total_orders FROM orders WHERE status = 'pending'");
$stats['total_orders'] = $stmt->fetchColumn();

// Total revenue (pending orders only)
$stmt = $db->query("
    SELECT SUM(total_amount) as total_revenue 
    FROM orders 
    WHERE status = 'pending'
");
$stats['total_revenue'] = $stmt->fetchColumn() ?: 0;

// Total users
$stmt = $db->query("SELECT COUNT(*) as total_users FROM users WHERE is_confirmed = 1");
$stats['total_users'] = $stmt->fetchColumn();

// Current orders (pending only)
$stmt = $db->query("
    SELECT o.*, u.first_name, u.last_name, u.email 
    FROM orders o 
    JOIN users u ON o.user_id = u.id 
    WHERE o.status = 'pending'
    ORDER BY o.created_at DESC 
    LIMIT 10
");
$current_orders = $stmt->fetchAll();

// Orders by status
$stmt = $db->query("
    SELECT status, COUNT(*) as count 
    FROM orders 
    GROUP BY status 
    ORDER BY count DESC
");
$orders_by_status = $stmt->fetchAll();

// Popular menu items (pending orders only)
$stmt = $db->query("
    SELECT mi.name, SUM(oi.quantity) as total_quantity 
    FROM order_items oi 
    JOIN menu_items mi ON oi.menu_item_id = mi.id 
    JOIN orders o ON oi.order_id = o.id 
    WHERE o.status = 'pending'
    GROUP BY mi.id, mi.name 
    ORDER BY total_quantity DESC 
    LIMIT 5
");
$popular_items = $stmt->fetchAll();

$page_title = __('admin_dashboard');
require_once __DIR__ . '/includes/header.php';
?>
        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">📋</div>
                <div class="stat-number"><?= number_format($stats['total_orders']) ?></div>
                <div class="stat-label"><?= __('total_orders') ?></div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">💰</div>
                <div class="stat-number"><?= formatPrice($stats['total_revenue']) ?></div>
                <div class="stat-label"><?= __('total_revenue') ?></div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">👥</div>
                <div class="stat-number"><?= number_format($stats['total_users']) ?></div>
                <div class="stat-label"><?= __('total_users') ?></div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">🍕</div>
                <div class="stat-number"><?= count($popular_items) ?></div>
                <div class="stat-label"><?= __('menu_items_count') ?></div>
            </div>
        </div>
        
        <!-- Admin Menu -->
        <div class="admin-menu">
            <a href="orders.php" class="admin-menu-item"><span class="icon">📋</span><span class="title"><?= __('order_management') ?></span></a>
            <a href="users.php" class="admin-menu-item"><span class="icon">👥</span><span class="title"><?= __('user_management') ?></span></a>
            <a href="menu.php" class="admin-menu-item"><span class="icon">🍕</span><span class="title"><?= __('admin_menu_management') ?></span></a>
            <a href="settings.php" class="admin-menu-item"><span class="icon">⚙️</span><span class="title"><?= __('site_settings') ?></span></a>
            <a href="reports.php" class="admin-menu-item"><span class="icon">📊</span><span class="title"><?= __('admin_reports') ?></span></a>
        </div>
        
        <!-- Orders by Status -->
        <?php if (!empty($orders_by_status)): ?>
        <div class="section">
            <h2><?= __('orders_by_status') ?></h2>
            <?php foreach ($orders_by_status as $status): ?>
            <div class="item-stat">
                <span>
                    <span class="status-badge status-<?= htmlspecialchars($status['status']) ?>">
                        <?= translateStatus($status['status']) ?>
                    </span>
                </span>
                <span><strong><?= number_format($status['count']) ?> <?= __('quantity') ?></strong></span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <!-- Popular Items -->
        <?php if (!empty($popular_items)): ?>
        <div class="section">
            <h2><?= __('popular_menu_items') ?></h2>
            <?php 
            $max_quantity = max(array_column($popular_items, 'total_quantity')) ?: 1;
            foreach ($popular_items as $item): 
                $percentage = ($item['total_quantity'] / $max_quantity) * 100;
            ?>
            <div class="item-stat">
                <div style="flex: 1;">
                    <div><strong><?= htmlspecialchars($item['name']) ?></strong></div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?= $percentage ?>%"></div>
                    </div>
                </div>
                <div style="margin-left: 20px;">
                    <strong><?= number_format($item['total_quantity']) ?> <?= __('quantity') ?></strong>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <!-- Current Orders -->
        <?php if (!empty($current_orders)): ?>
        <div class="section">
            <h2><?= __('current_orders') ?></h2>
            <table class="table">
                <thead>
                    <tr>
                        <th><?= __('order_number_label') ?></th>
                        <th><?= __('customer') ?></th>
                        <th><?= __('amount') ?></th>
                        <th><?= __('status') ?></th>
                        <th><?= __('date') ?></th>
                        <th><?= __('action') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($current_orders as $order): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($order['order_number']) ?></strong></td>
                        <td><?= htmlspecialchars($order['first_name'] . ' ' . $order['last_name']) ?><br><small><?= htmlspecialchars($order['email']) ?></small></td>
                        <td><?= formatPrice($order['total_amount']) ?></td>
                        <td><span class="status-badge status-<?= htmlspecialchars($order['status']) ?>"><?= translateStatus($order['status']) ?></span></td>
                        <td><?= date('Y/m/d H:i', strtotime($order['created_at'])) ?></td>
                        <td><a href="order_details.php?id=<?= $order['id'] ?>" style="color: #3498db; text-decoration: none;"><?= __('view_details') ?></a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div style="text-align: center; margin-top: 20px;">
                <a href="orders.php" class="admin-menu-item" style="display: inline-block; padding: 15px 30px;">
                    <span class="title"><?= __('view_all_orders') ?></span>
                </a>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Quick Actions -->
        <div class="section">
            <h2><?= __('quick_actions') ?></h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                <a href="settings.php" style="background: #e74c3c; color: white; padding: 20px; border-radius: 8px; text-decoration: none; text-align: center; font-weight: bold;">🔧 <?= __('update_event_settings') ?></a>
                <a href="menu.php" style="background: #f39c12; color: white; padding: 20px; border-radius: 8px; text-decoration: none; text-align: center; font-weight: bold;">🍕 <?= __('add_menu_item') ?></a>
                <a href="orders.php?status=pending" style="background: #27ae60; color: white; padding: 20px; border-radius: 8px; text-decoration: none; text-align: center; font-weight: bold;">⏳ <?= __('view_pending_orders') ?></a>
                <a href="reports.php" style="background: #8e44ad; color: white; padding: 20px; border-radius: 8px; text-decoration: none; text-align: center; font-weight: bold;">📊 <?= __('generate_reports') ?></a>
        </div>
<?php
require_once __DIR__ . '/includes/footer.php';
