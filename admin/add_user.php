<?php
require_once '../config.php';
requireAdmin();

$config = getSiteConfig();
$db = getDB();

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first = trim($_POST['first_name']);
    $last = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];
    $status = isset($_POST['is_confirmed']) ? 1 : 0;

    if (!$first || !$last || !$email || !$password) {
        $errors[] = __('error_all_fields_required');
    } else {
        // check if email already exists
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = __('error_email_exists');
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $db->prepare("
                INSERT INTO users (first_name, last_name, email, phone, password_hash, is_confirmed, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$first, $last, $email, $phone, $hash, $status]);

            $newId = $db->lastInsertId();
            header("Location: user_details.php?id=$newId");
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?= $_SESSION['lang'] ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('add_user_title') ?> - <?= htmlspecialchars($config['site_title']) ?></title>
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
        .section h2 { color: #2c3e50; margin-bottom: 20px; font-size: 1.5em; border-bottom: 3px solid #3498db; padding-bottom: 10px; }

        /* Form Specific Styles */
        label { display: block; margin-top: 15px; margin-bottom: 5px; font-weight: bold; color: #34495e; }
        input[type=text], input[type=email], input[type=password] { width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 8px; font-size: 1em; }
        input[type=checkbox] { margin-right: 10px; transform: scale(1.2); }
        button { margin-top: 25px; padding: 12px 25px; background: #27ae60; color: #fff; border: none; border-radius: 8px; cursor: pointer; font-size: 1.1em; font-weight: bold; transition: background 0.3s; }
        button:hover { background: #2ecc71; }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 8px; font-weight: bold; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;}
        .back-link { margin-top: 20px; display: inline-block; color: #3498db; text-decoration: none; font-weight: bold; }
        .back-link:hover { text-decoration: underline; }
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
            <h1>➕ <?= __('add_user_title') ?></h1>
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
            <h2><?= __('user_information') ?></h2>

            <?php if ($errors): ?>
                <div class="alert alert-error">
                    <?php foreach ($errors as $err): ?>
                        <p><?= htmlspecialchars($err) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="post">
                <label for="first_name"><?= __('first_name') ?></label>
                <input type="text" id="first_name" name="first_name" required>

                <label for="last_name"><?= __('last_name') ?></label>
                <input type="text" id="last_name" name="last_name" required>

                <label for="email"><?= __('email') ?></label>
                <input type="email" id="email" name="email" required>

                <label for="phone"><?= __('phone') ?></label>
                <input type="text" id="phone" name="phone">

                <label for="password"><?= __('password') ?></label>
                <input type="password" id="password" name="password" required>

                <label style="margin-top: 20px; font-weight: normal;">
                    <input type="checkbox" name="is_confirmed"> <?= __('email_confirmed') ?>
                </label>

                <button type="submit"><?= __('save_user') ?></button>
            </form>

            <p><a href="users.php" class="back-link"><?= __('back_to_users') ?></a></p>
        </div>
    </div>
</body>
</html>