<?php
require_once 'config.php';

$config = getSiteConfig();
$landing_images = json_decode($config['landing_images'] ?? '[]', true) ?: [];
?>
<!DOCTYPE html>
<html lang="<?= $_SESSION['lang'] ?? 'ja' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($config['site_title'] ?? __('site_title')) ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Arial', sans-serif;
            line-height: 1.6;
            color: #333;
            background: linear-gradient(135deg, #ff6b6b, #feca57);
            min-height: 100vh;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        header {
            text-align: center;
            margin-bottom: 40px;
            background: rgba(255, 255, 255, 0.95);
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            position: relative; /* Added for positioning the language selector */
        }

        /* --- Language Selector Styles --- */
        .language-selector {
            position: absolute;
            top: 20px;
            right: 20px;
            display: flex;
            align-items: center;
        }

        .language-selector span {
            font-size: 1.5em; /* Globe icon size */
            margin-right: 8px;
        }

        .language-selector select {
            background-color: rgba(255, 255, 255, 0.8);
            border: 1px solid #ccc;
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 1em;
            font-weight: bold;
            cursor: pointer;
            appearance: none;
            -webkit-appearance: none;
            padding-right: 30px; /* Space for arrow */
            background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%20256%20256%22%3E%3Cpath%20fill%3D%22%23333%22%20d%3D%22M128%20180a12.1%2012.1%200%2001-8.5-3.5l-80-80a12%2012%200%200117-17L128%20151l71.5-71.5a12%2012%200%200117%2017l-80%2080a12.1%2012.1%200%2001-8.5%203.5z%22%2F%3E%3C%2Fsvg%3E');
            background-repeat: no-repeat;
            background-position: right 10px center;
            background-size: 1em;
        }
        /* --- End of Styles --- */
        
        h1 {
            color: #d32f2f;
            font-size: 3em;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .event-details {
            background: #fff3e0;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            border-left: 5px solid #ff9800;
        }
        
        .event-details h3 {
            color: #e65100;
            margin-bottom: 10px;
        }
        
        .content-section {
            background: rgba(255, 255, 255, 0.95);
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }
        
        .gallery {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        
        .gallery img {
            width: 100%;
            height: 250px;
            object-fit: cover;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            transition: transform 0.3s ease;
        }
        
        .gallery img:hover {
            transform: scale(1.05);
        }
        
        .cta-buttons {
            text-align: center;
            margin: 40px 0;
        }
        
        .btn {
            display: inline-block;
            padding: 15px 30px;
            margin: 10px;
            background: #d32f2f;
            color: white;
            text-decoration: none;
            border-radius: 25px;
            font-weight: bold;
            font-size: 1.1em;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(211, 47, 47, 0.4);
        }
        
        .btn:hover {
            background: #b71c1c;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(211, 47, 47, 0.6);
        }
        
        .btn.secondary {
            background: #ff9800;
            box-shadow: 0 5px 15px rgba(255, 152, 0, 0.4);
        }
        
        .btn.secondary:hover {
            background: #f57c00;
            box-shadow: 0 8px 25px rgba(255, 152, 0, 0.6);
        }
        
        .pizza-icon {
            font-size: 2em;
            margin-bottom: 20px;
        }
        
        footer {
            text-align: center;
            margin-top: 40px;
            color: rgba(255, 255, 255, 0.8);
        }
        
        .admin-link {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 10px 15px;
            text-decoration: none;
            border-radius: 20px;
            font-size: 0.9em;
            transition: background 0.3s ease;
        }
        
        .admin-link:hover {
            background: rgba(0, 0, 0, 0.9);
        }
        
        @media (max-width: 768px) {
            h1 {
                font-size: 2em;
            }
            .container {
                padding: 10px;
            }
            .btn {
                display: block;
                margin: 10px auto;
                max-width: 300px;
            }
            .language-selector {
                position: static; /* Let it flow naturally on small screens */
                justify-content: center;
                margin-bottom: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <!-- Language Selector -->
            <div class="language-selector">
                <span>🌐</span>
                <select onchange="window.location.href='?lang='+this.value;">
                    <option value="ja" <?= ($_SESSION['lang'] ?? 'ja') === 'ja' ? 'selected' : '' ?>>日本語</option>
                    <option value="en" <?= ($_SESSION['lang'] ?? 'ja') === 'en' ? 'selected' : '' ?>>English</option>
                </select>
            </div>

            <div class="pizza-icon">🍕</div>
            <h1><?= htmlspecialchars($config['site_title'] ?? __('site_title')) ?></h1>
            
            <?php if (!empty($config['event_location']) || !empty($config['event_date'])): ?>
            <div class="event-details">
                <?php if (!empty($config['event_location'])): ?>
                <h3>📍 <?= __('location_label') ?></h3>
                <p><?= htmlspecialchars($config['event_location']) ?></p>
                <?php endif; ?>
                
                <?php if (!empty($config['event_date'])): ?>
                <h3>📅 <?= __('date_label') ?></h3>
                <p><?= date('Y年n月j日', strtotime($config['event_date'])) ?></p>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </header>
        
        <div class="content-section">
            <?php if (!empty($config['landing_content'])): ?>
                <p style="font-size: 1.2em; text-align: center; margin-bottom: 20px;">
                    <?= nl2br(htmlspecialchars($config['landing_content'])) ?>
                </p>
            <?php else: ?>
                <p style="font-size: 1.2em; text-align: center; margin-bottom: 20px;">
                    <?= nl2br(htmlspecialchars(__('default_landing_content'))) ?>
                </p>
            <?php endif; ?>
            
            <?php if (!empty($landing_images)): ?>
            <div class="gallery">
                <?php foreach ($landing_images as $image): ?>
                    <?php if (file_exists($image)): ?>
                    <img src="<?= htmlspecialchars($image) ?>" alt="<?= __('gallery_alt') ?>" loading="lazy">
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="cta-buttons">
            <?php if (isLoggedIn()): ?>
                <?php if (isAdmin()): ?>
                    <a href="admin/dashboard.php" class="btn"><?= __('admin_dashboard') ?></a>
                    <a href="menu.php" class="btn secondary"><?= __('view_menu') ?></a>
                <?php else: ?>
                    <a href="menu.php" class="btn"><?= __('order_now') ?></a>
                    <a href="logout.php" class="btn secondary"><?= __('logout') ?></a>
                <?php endif; ?>
            <?php else: ?>
                <a href="register.php" class="btn"><?= __('register_event') ?></a>
                <a href="login.php" class="btn secondary"><?= __('login') ?></a>
            <?php endif; ?>
        </div>
        
        <footer>
            <p>&copy; <?= date('Y') ?> <?= __('footer_text') ?></p>
        </footer>
    </div>
    
    <a href="admin_login.php" class="admin-link"><?= __('admin_link') ?></a>
</body>
</html>