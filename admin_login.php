<?php
require_once 'config.php';

$config = getSiteConfig();
$error = '';

// Redirect if already logged in as admin
if (isAdmin()) {
    redirect('admin/dashboard.php');
}

// Handle admin login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = __('error_username_password_required');
    } else {
        try {
            $db = getDB();
            $stmt = $db->prepare("SELECT id, password_hash FROM admins WHERE username = ?");
            $stmt->execute([$username]);
            $admin = $stmt->fetch();
            
            if ($admin && verifyPassword($password, $admin['password_hash'])) {
                // Set admin session
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_username'] = $username;
                
                // Create session record
                $session_id = session_id();
                $expires_at = date('Y-m-d H:i:s', time() + ADMIN_SESSION_TIMEOUT);
                $stmt = $db->prepare("INSERT INTO user_sessions (id, admin_id, expires_at) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE expires_at = ?");
                $stmt->execute([$session_id, $admin['id'], $expires_at, $expires_at]);
                
                redirect('admin/dashboard.php');
            } else {
                $error = __('error_invalid_credentials');
            }
        } catch (Exception $e) {
            $error = __('error_login_failed');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?= $_SESSION['lang'] ?? 'ja' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('admin_login_title') ?> - <?= htmlspecialchars($config['site_title'] ?? __('site_title')) ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Arial', sans-serif;
            background: linear-gradient(135deg, #2c3e50, #34495e);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            background: rgba(255, 255, 255, 0.95);
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3);
            max-width: 400px;
            width: 100%;
        }
        
        .admin-icon {
            font-size: 3em;
            text-align: center;
            margin-bottom: 20px;
            color: #2c3e50;
        }
        
        h1 {
            color: #2c3e50;
            text-align: center;
            margin-bottom: 30px;
            font-size: 2.2em;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            color: #555;
            font-weight: bold;
        }
        
        input {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 1em;
            transition: border-color 0.3s ease;
        }
        
        input:focus {
            outline: none;
            border-color: #2c3e50;
            box-shadow: 0 0 0 3px rgba(44, 62, 80, 0.1);
        }
        
        .btn {
            width: 100%;
            padding: 12px;
            background: #2c3e50;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1.1em;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 15px;
        }
        
        .btn:hover {
            background: #1a252f;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(44, 62, 80, 0.4);
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-weight: bold;
            background: #ffebee;
            color: #c62828;
            border: 1px solid #e57373;
        }
        
        .back-link {
            text-align: center;
            margin-top: 20px;
        }
        
        .back-link a {
            color: #2c3e50;
            text-decoration: none;
            font-weight: bold;
        }
        
        .back-link a:hover {
            text-decoration: underline;
        }
        
        .security-note {
            background: #e3f2fd;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9em;
            color: #1565c0;
            border-left: 4px solid #2196f3;
        }
        
        @media (max-width: 600px) {
            .container {
                padding: 30px 20px;
                margin: 10px;
            }
            
            h1 {
                font-size: 1.8em;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="admin-icon">👨‍💼</div>
        <h1><?= __('admin_login_title') ?></h1>
        
        <div class="security-note">
            <?= __('admin_area_warning') ?>
        </div>
        
        <?php if ($error): ?>
            <div class="alert"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="username"><?= __('username_label') ?></label>
                <input type="text" id="username" name="username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required autocomplete="username">
            </div>
            
            <div class="form-group">
                <label for="password"><?= __('password_label') ?></label>
                <input type="password" id="password" name="password" required autocomplete="current-password">
            </div>
            
            <button type="submit" class="btn"><?= __('login_admin_panel') ?></button>
        </form>
        
        <div class="back-link">
            <a href="index.php"><?= __('back_home') ?></a>
        </div>
    </div>
</body>
</html>