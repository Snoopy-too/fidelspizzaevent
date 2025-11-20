<?php
require_once '../config.php';
requireAdmin();

$config = getSiteConfig();
$db = getDB();

// The handleImageUpload() function has been removed from this file.
// It will now use the centralized function from config.php.

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_item'])) {
        $imagePath = handleImageUpload('image_file');
        $stmt = $db->prepare("INSERT INTO menu_items (name, description, price, image_path, is_active) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['name'],
            $_POST['description'],
            $_POST['price'],
            $imagePath,
            isset($_POST['is_active']) ? 1 : 0
        ]);
    } elseif (isset($_POST['update_item'])) {
        $imagePath = handleImageUpload('image_file', $_POST['image_path'] ?? null);
        $stmt = $db->prepare("UPDATE menu_items SET name=?, description=?, price=?, image_path=?, is_active=? WHERE id=?");
        $stmt->execute([
            $_POST['name'],
            $_POST['description'],
            $_POST['price'],
            $imagePath,
            isset($_POST['is_active']) ? 1 : 0,
            $_POST['id']
        ]);
    } elseif (isset($_POST['delete_item'])) {
        $stmt = $db->prepare("DELETE FROM menu_items WHERE id=?");
        $stmt->execute([$_POST['id']]);
    }
    header("Location: menu.php");
    exit;
}

$stmt = $db->query("SELECT * FROM menu_items ORDER BY name");
$menu_items = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="<?= $_SESSION['lang'] ?>">
<head>
    <meta charset="UTF-8">
    <title>🍕 <?= __('admin_menu_management') ?></title>
    <style>
        /* All styles remain the same */
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
        .section h2 { color: #2c3e50; margin-bottom: 20px; font-size: 1.5em; border-bottom: 3px solid #3498db; padding-bottom: 10px; }
        .menu-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; }
        .menu-card { background: white; border-radius: 10px; padding: 20px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); display: flex; flex-direction: column; }
        .menu-card img { max-width: 100%; border-radius: 8px; margin-bottom: 15px; }
        .menu-card h3 { margin-bottom: 10px; font-size: 1.2em; color: #2c3e50; }
        .menu-card p { flex: 1; margin-bottom: 10px; color: #555; }
        .menu-card .price { font-weight: bold; margin-bottom: 10px; color: #3498db; }
        .menu-card form { display: flex; flex-direction: column; gap: 10px; }
        .menu-card input[type="text"],.menu-card input[type="number"],.menu-card textarea { width: 100%; padding: 10px 12px; border-radius: 8px; border: 1px solid #ccc; font-family: 'Arial', sans-serif; font-size: 0.95em; transition: border-color 0.3s; }
        .menu-card input[type="text"]:focus,.menu-card input[type="number"]:focus,.menu-card textarea:focus { border-color: #3498db; outline: none; }
        .switch-container { display: flex; align-items: center; gap: 8px; }
        .switch { position: relative; display: inline-block; width: 50px; height: 28px; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; border-radius: 28px; }
        .slider:before { position: absolute; content: ""; height: 22px; width: 22px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; }
        input:checked + .slider { background-color: #27ae60; }
        input:checked + .slider:before { transform: translateX(22px); }
        .menu-card button { padding: 10px 15px; border: none; border-radius: 8px; cursor: pointer; color: #fff; font-weight: bold; font-family: 'Arial', sans-serif; transition: opacity 0.3s; }
        .btn-save { background: #27ae60; }
        .btn-delete { background: #e74c3c; }
        .btn-save:hover, .btn-delete:hover { opacity: 0.9; }
        .add-card { display: flex; flex-direction: column; gap: 10px; }
        .add-card button { background: #2980b9; padding: 12px; font-weight: bold; }
        input[type="file"] { font-family: 'Arial', sans-serif; font-size: 0.95em; color: #555; padding: 6px 10px; border-radius: 8px; border: 1px solid #ccc; cursor: pointer; transition: border-color 0.3s, background 0.3s; }
        input[type="file"]::file-selector-button { padding: 8px 12px; margin-right: 10px; border-radius: 8px; border: 1px solid #3498db; background-color: #3498db; color: white; cursor: pointer; font-family: 'Arial', sans-serif; font-size: 0.95em; transition: background 0.3s; }
        input[type="file"]::file-selector-button:hover { background-color: #2980b9; }
        .lang-selector { margin-left: 20px; }
        .lang-selector select { padding: 5px; border-radius: 5px; border: none; background: rgba(255,255,255,0.2); color: white; cursor: pointer; }
        .lang-selector select option { background: #2c3e50; color: white; }
        @media (max-width: 768px) { .header-content { flex-direction: column; gap: 15px; } .nav-links { flex-wrap: wrap; justify-content: center; } .nav-links a { margin: 5px 10px; } }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1>🍕 <?= __('admin_menu_management') ?></h1>
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
        <div class="section">
            <h2><?= __('menu_items_list') ?></h2>
            <div class="menu-grid">
                <?php foreach ($menu_items as $item): ?>
                <div class="menu-card">
                    <?php 
                    // --- CORRECTED IMAGE PATH LOGIC ---
                    // The path in DB is 'images/file.jpg'. This file is in '/admin', so we go up one level.
                    $image_web_path = '../' . $item['image_path'];
                    if (!empty($item['image_path']) && file_exists(ROOT_PATH . DIRECTORY_SEPARATOR . $item['image_path'])): 
                    ?>
                        <img src="<?= htmlspecialchars($image_web_path) ?>" alt="<?= htmlspecialchars($item['name']) ?>">
                    <?php endif; ?>

                    <h3><?= htmlspecialchars($item['name']) ?></h3>
                    <p><?= htmlspecialchars($item['description']) ?></p>
                    <div class="price"><?= number_format($item['price']) ?> 円</div>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="id" value="<?= $item['id'] ?>">
                        <input type="text" name="name" value="<?= htmlspecialchars($item['name']) ?>" placeholder="<?= __('item_name') ?>">
                        <textarea name="description"><?= htmlspecialchars($item['description']) ?></textarea>
                        <input type="number" name="price" value="<?= (int)$item['price'] ?>" placeholder="<?= __('item_price') ?>">
                        <label><?= __('change_image') ?>: <input type="file" name="image_file"></label>
                        <input type="hidden" name="image_path" value="<?= htmlspecialchars($item['image_path'] ?? '') ?>">
                        
                        <div class="switch-container">
                            <label class="switch">
                                <input type="checkbox" name="is_active" <?= !empty($item['is_active']) ? 'checked' : '' ?>>
                                <span class="slider"></span>
                            </label>
                            <span><?= __('display_in_menu') ?></span>
                        </div>

                        <button type="submit" name="update_item" class="btn-save"><?= __('save') ?></button>
                        <button type="submit" name="delete_item" class="btn-delete" onclick="return confirm('<?= __('confirm_delete') ?>')"><?= __('delete') ?></button>
                    </form>
                </div>
                <?php endforeach; ?>

                <!-- Add New Item Card -->
                <div class="menu-card add-card">
                    <h3>➕ <?= __('add_new_item') ?></h3>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="text" name="name" placeholder="<?= __('item_name') ?>" required>
                        <textarea name="description" placeholder="<?= __('item_description') ?>"></textarea>
                        <input type="number" name="price" placeholder="<?= __('item_price') ?>" required>
                        <label><?= __('upload_image') ?>: <input type="file" name="image_file"></label>
                        
                        <div class="switch-container">
                            <label class="switch">
                                <input type="checkbox" name="is_active" checked>
                                <span class="slider"></span>
                            </label>
                            <span><?= __('display_in_menu') ?></span>
                        </div>

                        <button type="submit" name="add_item"><?= __('add_item') ?></button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>