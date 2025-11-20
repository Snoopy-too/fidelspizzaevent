<?php
require_once '../config.php';
requireAdmin();

$config = getSiteConfig();
$db = getDB();

// Fetch all users
$stmt = $db->query("
    SELECT id, first_name, last_name, email, is_confirmed, created_at
    FROM users 
    ORDER BY created_at DESC
");
$users = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="<?= $_SESSION['lang'] ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('user_management') ?> - <?= htmlspecialchars($config['site_title'] ?? 'Fidel\'s Pizza Event') ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Arial', sans-serif;
            background: #f5f5f5;
            color: #333;
        }
        .header {
            background: #2c3e50;
            color: white;
            padding: 20px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header h1 { font-size: 1.8em; }
        .nav-links { display: flex; align-items: center; }
        .nav-links a {
            color: white;
            text-decoration: none;
            margin-left: 20px;
            padding: 8px 16px;
            border-radius: 5px;
            transition: background 0.3s;
        }
        .nav-links a:hover { background: rgba(255,255,255,0.2); }
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
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            overflow-x: auto;
        }
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }
        .section h2 {
            color: #2c3e50;
            font-size: 1.5em;
            border-bottom: 3px solid #3498db;
            padding-bottom: 10px;
            margin-bottom: 20px;
            flex-grow: 1;
        }
        .add-user-btn {
            background:#27ae60;
            color:#fff;
            padding:10px 15px;
            text-decoration:none;
            border-radius:5px;
            font-weight: bold;
            transition: background 0.3s;
            white-space: nowrap;
        }
        .add-user-btn:hover {
            background:#219150;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            min-width: 800px;
        }
        .table th, .table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .table th {
            background: #34495e;
            color: white;
            font-weight: bold;
        }
        .table tr:hover { background: #f8f9fa; }
        .status-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8em;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-confirmed { background: #d4edda; color: #155724; }
        .status-pending { background: #fff3cd; color: #856404; }
        .action-links a {
            margin-right: 10px;
            text-decoration: none;
            font-weight: bold;
        }
        .action-links a:last-child {
            margin-right: 0;
        }
        .lang-selector { margin-left: 20px; }
        .lang-selector select { padding: 5px; border-radius: 5px; border: none; background: rgba(255,255,255,0.2); color: white; cursor: pointer; }
        .lang-selector select option { background: #2c3e50; color: white; }
        @media (max-width: 768px) {
            .header-content { flex-direction: column; gap: 15px; }
            .nav-links { flex-wrap: wrap; justify-content: center; }
            .nav-links a { margin: 5px 10px; }
            .section-header { flex-direction: column; gap: 15px; align-items: flex-start; }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1>👥 <?= __('user_management') ?></h1>
            <div class="nav-links">
                <a href="../index.php">🏠 <?= __('home') ?></a>
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
            <div class="section-header">
                <h2><?= __('registered_users') ?></h2>
                <a href="add_user.php" class="add-user-btn"><?= __('add_user_button') ?></a>
            </div>

            <?php if (!empty($users)): ?>
            <table class="table">
                <thead>
                    <tr>
                        <th><?= __('user_id') ?></th>
                        <th><?= __('full_name') ?></th>
                        <th><?= __('email_address') ?></th>
                        <th><?= __('status') ?></th>
                        <th><?= __('registration_date') ?></th>
                        <th><?= __('operations') ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?= htmlspecialchars($user['id']) ?></td>
                        <td><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></td>
                        <td><?= htmlspecialchars($user['email']) ?></td>
                        <td>
                            <?php if ($user['is_confirmed']): ?>
                                <span class="status-badge status-confirmed"><?= __('confirmed') ?></span>
                            <?php else: ?>
                                <span class="status-badge status-pending"><?= __('pending') ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?= date('Y/m/d', strtotime($user['created_at'])) ?></td>
                        <td class="action-links">
                            <a href="user_details.php?id=<?= $user['id'] ?>" style="color: #3498db;"><?= __('details') ?></a> | 
                            <a href="edit_user.php?id=<?= $user['id'] ?>" style="color: #f39c12;"><?= __('edit') ?></a> | 
                            <a href="delete_user.php?id=<?= $user['id'] ?>" style="color: #e74c3c;" onclick="return confirm('<?= __('confirm_delete_user') ?>');"><?= __('delete_user') ?></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
                <p><?= __('no_users_found') ?></p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>