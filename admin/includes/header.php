<?php
if (!isset($config)) {
    $config = getSiteConfig();
}
$flash = getFlash();
$currentLang = (string)($_SESSION['lang'] ?? 'ja');
$displayTitle = isset($page_title) ? (string)$page_title : __('admin_dashboard');
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($currentLang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($displayTitle) ?> - <?= htmlspecialchars((string)($config['site_title'] ?? "Fidel's Pizza Event")) ?></title>
    <link rel="stylesheet" href="css/admin.css">
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1>🍕 <?= htmlspecialchars($displayTitle) ?></h1>
            <div class="nav-links">
                <a href="../index.php">🏠 <?= __('home') ?></a>
                <a href="dashboard.php">📊 <?= __('admin_dashboard') ?></a>
                <a href="orders.php">📋 <?= __('order_management') ?></a>
                <a href="users.php">👥 <?= __('user_management') ?></a>
                <a href="menu.php">🍕 <?= __('admin_menu_management') ?></a>
                <a href="settings.php">⚙️ <?= __('admin_settings') ?></a>
                <a href="reports.php">📈 <?= __('admin_reports') ?></a>
                <a href="admins.php">🛡️ Admins</a>
                <a href="../logout.php">🚪 <?= __('logout') ?></a>
                <div class="lang-selector">
                    <form method="GET" action="">
                        <select name="lang" onchange="this.form.submit()">
                            <option value="ja" <?= $currentLang === 'ja' ? 'selected' : '' ?>>🇯🇵 日本語</option>
                            <option value="en" <?= $currentLang === 'en' ? 'selected' : '' ?>>🇺🇸 English</option>
                        </select>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <div class="container">
        <?php if ($flash): ?>
            <div class="alert alert-<?= htmlspecialchars((string)$flash['type']) ?>">
                <?= htmlspecialchars((string)$flash['message']) ?>
            </div>
        <?php endif; ?>
