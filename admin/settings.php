<?php
require_once '../config.php';

requireLogin();
requireAdmin();

$db = getDB();

// Fetch current config
$stmt = $db->prepare("SELECT * FROM site_config WHERE id = 1");
$stmt->execute();
$config = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $db->prepare("UPDATE site_config SET site_title=?, event_location=?, event_date=?, registration_code=?, landing_content=?, menu_content=?, admin_email=? WHERE id=1");
    $stmt->execute([
        $_POST['site_title'],
        $_POST['event_location'],
        $_POST['event_date'],
        $_POST['registration_code'],
        $_POST['landing_content'],
        $_POST['menu_content'],
        $_POST['admin_email']
    ]);

    // Refresh config
    header("Location: settings.php?success=1");
    exit;
}
?>
<!DOCTYPE html>
<html lang="<?= $_SESSION['lang'] ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('settings_title') ?> - <?= htmlspecialchars($config['site_title'] ?? 'Fidel\'s Pizza Event') ?></title>
    <style>
        /* Copied all styles from dashboard.php for consistency */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Arial', sans-serif;
            background: #f5f5f5;
            color: #333;
        }
        
        .header {
            background: #2c3e50;
            color: white;
            padding: 20px 0;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header h1 {
            font-size: 1.8em;
        }
        
        .nav-links { display: flex; align-items: center; }
        .nav-links a {
            color: white;
            text-decoration: none;
            margin-left: 20px;
            padding: 8px 16px;
            border-radius: 5px;
            transition: background 0.3s;
        }
        
        .nav-links a:hover {
            background: rgba(255, 255, 255, 0.2);
        }
        
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
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .section h2 {
            color: #2c3e50;
            margin-bottom: 20px;
            font-size: 1.5em;
            border-bottom: 3px solid #3498db;
            padding-bottom: 10px;
        }
        
        /* Styles for form elements, integrated from original settings page */
        label { 
            display: block; 
            margin-top: 20px;
            margin-bottom: 5px;
            font-weight: bold; 
            font-size: 1em; 
            color: #34495e;
        }
        
        input[type="text"], 
        input[type="email"], 
        input[type="date"], 
        textarea {
            width: 100%; 
            padding: 12px; 
            border-radius: 8px; 
            border: 1px solid #ccc; 
            font-size: 1em;
            font-family: 'Arial', sans-serif;
            transition: border-color 0.3s, box-shadow 0.3s;
        }

        input[type="text"]:focus, 
        input[type="email"]:focus, 
        input[type="date"]:focus, 
        textarea:focus {
            border-color: #3498db;
            box-shadow: 0 0 5px rgba(52, 152, 219, 0.5);
            outline: none;
        }
        
        textarea { 
            height: 120px; 
            resize: vertical; 
        }
        
        button[type="submit"] { 
            margin-top: 30px; 
            padding: 12px 25px; 
            border: none; 
            border-radius: 8px; 
            background: #27ae60; 
            color: white; 
            font-weight: bold; 
            cursor: pointer; 
            font-size: 1.1em; 
            transition: background-color 0.3s;
        }
        
        button[type="submit"]:hover { 
            background: #2ecc71; 
        }
        
        .success { 
            background: #d4edda; 
            color: #155724; 
            padding: 15px; 
            border-radius: 8px; 
            margin-bottom: 20px; 
            font-weight: bold; 
            border: 1px solid #c3e6cb;
        }

        .lang-selector { margin-left: 20px; }
        .lang-selector select { padding: 5px; border-radius: 5px; border: none; background: rgba(255,255,255,0.2); color: white; cursor: pointer; }
        .lang-selector select option { background: #2c3e50; color: white; }

        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 15px;
            }
            .nav-links { flex-wrap: wrap; justify-content: center; }
            .nav-links a {
                margin: 5px 10px;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1>⚙️ <?= __('settings_title') ?></h1>
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
            <h2><?= __('settings_title') ?></h2>
            
            <?php if (isset($_GET['success'])): ?>
                <div class="success"><?= __('settings_updated') ?></div>
            <?php endif; ?>

            <form method="POST">
                <label for="site_title"><?= __('site_title_label') ?></label>
                <input type="text" name="site_title" id="site_title" value="<?= htmlspecialchars($config['site_title'] ?? '') ?>" required>

                <label for="event_location"><?= __('event_location_label') ?></label>
                <textarea name="event_location" id="event_location"><?= htmlspecialchars($config['event_location'] ?? '') ?></textarea>

                <label for="event_date"><?= __('event_date') ?></label>
                <input type="date" name="event_date" id="event_date" value="<?= htmlspecialchars($config['event_date'] ?? '') ?>">

                <label for="registration_code"><?= __('registration_code') ?></label>
                <input type="text" name="registration_code" id="registration_code" value="<?= htmlspecialchars($config['registration_code'] ?? '') ?>">

                <label for="landing_content"><?= __('landing_page_content') ?></label>
                <textarea name="landing_content" id="landing_content"><?= htmlspecialchars($config['landing_content'] ?? '') ?></textarea>

                <label for="menu_content"><?= __('menu_page_content') ?></label>
                <textarea name="menu_content" id="menu_content"><?= htmlspecialchars($config['menu_content'] ?? '') ?></textarea>
                
                <label for="admin_email"><?= __('admin_email_notification') ?></label>
                <input type="email" name="admin_email" id="admin_email" value="<?= htmlspecialchars($config['admin_email'] ?? '') ?>">

                <button type="submit"><?= __('save_settings') ?></button>
            </form>
        </div>
    </div>
</body>
</html>