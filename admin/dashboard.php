<?php
require_once '../config.php';
requireAdmin();

$config = getSiteConfig();
$db = getDB();

// Helper function to translate order status
function translateStatus($status) {
    $key = 'status_' . $status;
    return __($key);
}

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
?>
<!DOCTYPE html>
<html lang="<?= $_SESSION['lang'] ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('admin_dashboard') ?> - <?= htmlspecialchars($config['site_title'] ?? 'Fidel\'s Pizza Event') ?></title>
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
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 40px; }
        .stat-card { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); text-align: center; transition: transform 0.3s ease; }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-icon { font-size: 3em; margin-bottom: 15px; }
        .stat-number { font-size: 2.5em; font-weight: bold; color: #2c3e50; margin-bottom: 10px; }
        .stat-label { color: #7f8c8d; font-weight: bold; text-transform: uppercase; font-size: 0.9em; }
        .section { background: white; border-radius: 10px; padding: 30px; margin-bottom: 30px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .section h2 { color: #2c3e50; margin-bottom: 20px; font-size: 1.5em; border-bottom: 3px solid #3498db; padding-bottom: 10px; }
        .admin-menu { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 40px; }
        .admin-menu-item { background: linear-gradient(135deg, #3498db, #2980b9); color: white; padding: 25px; border-radius: 10px; text-decoration: none; text-align: center; transition: all 0.3s ease; box-shadow: 0 5px 15px rgba(52, 152, 219, 0.3); }
        .admin-menu-item:hover { transform: translateY(-3px); box-shadow: 0 8px 25px rgba(52, 152, 219, 0.4); color: white; }
        .admin-menu-item .icon { font-size: 2.5em; display: block; margin-bottom: 10px; }
        .admin-menu-item .title { font-weight: bold; font-size: 1.1em; }
        .table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .table th, .table td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        .table th { background: #34495e; color: white; font-weight: bold; }
        .table tr:hover { background: #f8f9fa; }
        .status-badge { padding: 5px 10px; border-radius: 15px; font-size: 0.8em; font-weight: bold; text-transform: uppercase; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-confirmed { background: #d4edda; color: #155724; }
        .status-preparing { background: #cce5ff; color: #004085; }
        .status-ready { background: #e2e3e5; color: #383d41; }
        .status-completed { background: #d1ecf1; color: #0c5460; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        .status-archived { background: #ececec; color: #444; }
        .progress-bar { width: 100%; height: 20px; background: #e9ecef; border-radius: 10px; overflow: hidden; margin: 10px 0; }
        .progress-fill { height: 100%; background: linear-gradient(135deg, #3498db, #2980b9); transition: width 0.3s ease; }
        .item-stat { display: flex; justify-content: space-between; align-items: center; margin: 15px 0; }
        .lang-selector { margin-left: 20px; }
        .lang-selector select { padding: 5px; border-radius: 5px; border: none; background: rgba(255,255,255,0.2); color: white; cursor: pointer; }
        .lang-selector select option { background: #2c3e50; color: white; }
        @media (max-width: 768px) { .header-content { flex-direction: column; gap: 15px; } .nav-links { flex-wrap: wrap; justify-content: center; } .nav-links a { margin: 5px 10px; } .stats-grid { grid-template-columns: 1fr; } .admin-menu { grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); } .table { font-size: 0.9em; } }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1>🍕 <?= __('admin_dashboard') ?></h1>
            <div class="nav-links">
                <a href="../index.php">🏠 <?= __('home') ?></a>
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
        </div>
    </div>
</body>
</html>
