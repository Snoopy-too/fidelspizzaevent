<?php
require_once 'config.php';

$config = getSiteConfig();
$error = '';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect(isAdmin() ? 'admin/dashboard.php' : 'menu.php');
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = __('error_email_password_required');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = __('error_invalid_email');
    } else {
        try {
            $db = getDB();
            $stmt = $db->prepare("SELECT id, password_hash, first_name, is_confirmed FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user && verifyPassword($password, $user['password_hash'])) {
                if (!$user['is_confirmed']) {
                    $error = __('error_email_confirm_required');
                } else {
                    // Set session
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['first_name'];
                    
                    // Create session record
                    $session_id = session_id();
                    $expires_at = date('Y-m-d H:i:s', time() + USER_SESSION_TIMEOUT);
                    $stmt = $db->prepare("INSERT INTO user_sessions (id, user_id, expires_at) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE expires_at = ?");
                    $stmt->execute([$session_id, $user['id'], $expires_at, $expires_at]);
                    
                    redirect('menu.php');
                }
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
    <title><?= __('login') ?> - <?= htmlspecialchars($config['site_title'] ?? __('site_title')) ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Arial', sans-serif;
            background: linear-gradient(135deg, #ff6b6b, #feca57);
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
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            max-width: 400px;
            width: 100%;
        }
        
        h1 {
            color: #d32f2f;
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
            border-color: #d32f2f;
            box-shadow: 0 0 0 3px rgba(211, 47, 47, 0.1);
        }
        
        .btn {
            width: 100%;
            padding: 12px;
            background: #d32f2f;
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
            background: #b71c1c;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(211, 47, 47, 0.4);
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
        
        .links {
            text-align: center;
            margin-top: 20px;
        }
        
        .links a {
            color: #d32f2f;
            text-decoration: none;
            font-weight: bold;
            display: block;
            margin: 10px 0;
        }
        
        .links a:hover {
            text-decoration: underline;
        }
        
        .pizza-icon {
            font-size: 2em;
            text-align: center;
            margin-bottom: 20px;
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
        <div class="pizza-icon">🍕</div>
        <h1><?= __('welcome_back') ?></h1>
        
        <?php if ($error): ?>
            <div class="alert"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="email"><?= __('email_label') ?></label>
                <input type="email" id="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
            </div>
            
            <div class="form-group">
                <label for="password"><?= __('password_label') ?></label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <button type="submit" class="btn"><?= __('login_button') ?></button>
        </form>
        
        <div class="links">
            <a href="register.php"><?= __('no_account') ?></a>
            <a href="forgot_password.php"><?= __('forgot_password') ?></a>
            <a href="index.php"><?= __('back_home') ?></a>
        </div>
    </div>
</body>
</html>