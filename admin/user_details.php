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

$id = $_GET['id'] ?? null;
if (!$id) {
    header("Location: users.php");
    exit;
}

// Get user
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch();

if (!$user) {
    header("Location: users.php?error=notfound");
    exit;
}

// Fetch user orders
$stmt = $db->prepare("
    SELECT o.id, o.order_number, o.total_amount, o.status, o.created_at
    FROM orders o
    WHERE o.user_id = ?
    ORDER BY o.created_at DESC
");
$stmt->execute([$id]);
$orders = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="<?= $_SESSION['lang'] ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('user_details_title') ?> - <?= htmlspecialchars($config['site_title']) ?></title>
    <style>
        /* Consistent Admin Panel Styles */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Arial', sans-serif; background: #f5f5f5; color: #333; }
        .header { background: #2c3e50; color: white; padding: 20px 0; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header-content { max-width: 1200px; margin: 0 auto; padding: 0 20px; display: flex; justify-content: space-between; align-items: center; }
        .header h1 { font-size: 1.8em; }
        .nav-links { display: flex; align-items: center; }
        .nav-links a { color: white; text-decoration: none; margin-left: 20px; padding: 8px 16px; border-radius: 5px; transition: background 0.3s; }
        .nav-links a:hover { background: rgba(255,255,255,0.2); }
        .container { max-width: 1200px; margin: 30px auto; padding: 0 20px; }
        .section { background: white; border-radius: 10px; padding: 30px; margin-bottom: 30px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .section h2, .section h3 { color: #2c3e50; margin-bottom: 20px; font-size: 1.5em; border-bottom: 3px solid #3498db; padding-bottom: 10px; }
        .section h3 { font-size: 1.3em; margin-top: 30px; }

        .user-details p { margin-bottom: 10px; font-size: 1.1em; color: #555; }
        .user-details strong { color: #2c3e50; min-width: 120px; display: inline-block; }

        /* Table styles */
        .table { width:100%; border-collapse:collapse; margin-top:20px; }
        .table th, .table td { padding:12px; border-bottom:1px solid #ddd; text-align:left; }
        .table th { background:#34495e; color:#fff; }
        .table tr:hover { background: #f8f9fa; }

        /* Status Badges */
        .status-badge { padding: 5px 10px; border-radius: 15px; font-size: 0.8em; font-weight: bold; text-transform: uppercase; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-confirmed { background: #d4edda; color: #155724; }
        .status-preparing { background: #cce5ff; color: #004085; }
        .status-ready { background: #e2e3e5; color: #383d41; }
        .status-completed { background: #d1ecf1; color: #0c5460; }
        .status-cancelled { background: #f8d7da; color: #721c24; }

        /* Action Buttons */
        .action-links { margin-top: 30px; display: flex; gap: 10px; flex-wrap: wrap; }
        .btn { display: inline-block; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: bold; transition: all 0.3s; }
        .btn-edit { background: #f39c12; color: #fff; }
        .btn-edit:hover { background: #e67e22; }
        .btn-delete { background: #e74c3c; color: #fff; }
        .btn-delete:hover { background: #c0392b; }
        .btn-back { background: #7f8c8d; color: #fff; }
        .btn-back:hover { background: #95a5a6; }
        .lang-selector { margin-left: 20px; }
        .lang-selector select { padding: 5px; border-radius: 5px; border: none; background: rgba(255,255,255,0.2); color: white; cursor: pointer; }
        .lang-selector select option { background: #2c3e50; color: white; }
        @media (max-width: 768px) {
            .header-content { flex-direction: column; gap: 15px; }
            .nav-links { flex-wrap: wrap; justify-content: center; }
            .nav-links a { margin: 5px 10px; }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1>👤 <?= __('user_details_title') ?></h1>
            <div class="nav-links">
                <a href="dashboard.php"><?= __('admin_dashboard') ?></a>
                <a href="orders.php"><?= __('order_management') ?></a>
                <a href="users.php"><?= __('user_management') ?></a>
                <a href="menu.php"><?= __('admin_menu_management') ?></a>
                <a href="settings.php"><?= __('admin_settings') ?></a>
                <a href="reports.php"><?= __('admin_reports') ?></a>
                <a href="../logout.php"><?= __('logout') ?></a>
                <div class="lang-selector">
                    <form method="GET" action="">
                        <input type="hidden" name="id" value="<?= $id ?>">
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
            <h2><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></h2>
            <div class="user-details">
                <p><strong><?= __('email_address') ?>:</strong> <?= htmlspecialchars($user['email']) ?></p>
                <p><strong><?= __('phone') ?>:</strong> <?= htmlspecialchars($user['phone']) ?: '-' ?></p>
                <p><strong><?= __('status') ?>:</strong>
                    <?php if ($user['is_confirmed']): ?>
                        <span class="status-badge status-confirmed"><?= __('confirmed') ?></span>
                    <?php else: ?>
                        <span class="status-badge status-pending"><?= __('pending') ?></span>
                    <?php endif; ?>
                </p>
                <p><strong><?= __('registration_date') ?>:</strong> <?= date('Y/m/d H:i', strtotime($user['created_at'])) ?></p>
            </div>

            <h3><?= __('order_history_title') ?></h3>
            <?php if ($orders): ?>
            <table class="table">
                <thead>
                    <tr>
                        <th><?= __('order_number') ?></th>
                        <th><?= __('amount') ?></th>
                        <th><?= __('status') ?></th>
                        <th><?= __('date') ?></th>
                        <th><?= __('action') ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($orders as $o): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($o['order_number']) ?></strong></td>
                        <td><?= formatPrice($o['total_amount']) ?></td>
                        <td>
                            <span class="status-badge status-<?= htmlspecialchars($o['status']) ?>">
                                <?= translateStatus($o['status']) ?>
                            </span>
                        </td>
                        <td><?= date('Y/m/d H:i', strtotime($o['created_at'])) ?></td>
                        <td>
                            <a href="order_details.php?id=<?= $o['id'] ?>" style="color:#3498db; font-weight:bold;"><?= __('view_details') ?></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
                <p><?= __('no_orders_for_user') ?></p>
            <?php endif; ?>

            <div class="action-links">
                <a href="edit_user.php?id=<?= $user['id'] ?>" class="btn btn-edit">✏️ <?= __('edit') ?></a>
                <a href="delete_user.php?id=<?= $user['id'] ?>" onclick="return confirm('<?= __('confirm_delete_user') ?>')" class="btn btn-delete">🗑 <?= __('delete_user') ?></a>
                <a href="users.php" class="btn btn-back"><?= __('back_to_users') ?></a>
            </div>
        </div>
    </div>
</body>
</html>