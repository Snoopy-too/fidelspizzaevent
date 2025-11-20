<?php
require_once 'config.php';

$config = getSiteConfig();
$message = '';
$success = false;

$token = $_GET['token'] ?? '';

if (empty($token)) {
    $message = 'Invalid confirmation link.';
} else {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT id, first_name, is_confirmed FROM users WHERE confirmation_token = ?");
        $stmt->execute([$token]);
        $user = $stmt->fetch();
        
        if (!$user) {
            $message = 'Invalid or expired confirmation link.';
        } elseif ($user['is_confirmed']) {
            $message = 'Your account has already been confirmed. You can now log in.';
            $success = true;
        } else {
            // Confirm the user
            $stmt = $db->prepare("UPDATE users SET is_confirmed = 1, confirmation_token = NULL WHERE id = ?");
            $stmt->execute([$user['id']]);
            
            $message = 'Your account has been successfully confirmed! You can now log in and start ordering pizza.';
            $success = true;
        }
    } catch (Exception $e) {
        $message = 'An error occurred during confirmation. Please try again or contact support.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Confirmation - <?= htmlspecialchars($config['site_title'] ?? 'Fidel\'s Pizza Event') ?></title>
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
            max-width: 500px;
            width: 100%;
            text-align: center;
        }
        
        .icon {
            font-size: 4em;
            margin-bottom: 20px;
        }
        
        .success {
            color: #4caf50;
        }
        
        .error {
            color: #f44336;
        }
        
        h1 {
            color: #d32f2f;
            margin-bottom: 30px;
            font-size: 2.2em;
        }
        
        .message {
            font-size: 1.1em;
            line-height: 1.6;
            margin-bottom: 30px;
            color: #555;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 30px;
            background: #d32f2f;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
            font-size: 1.1em;
            transition: all 0.3s ease;
            margin: 10px;
        }
        
        .btn:hover {
            background: #b71c1c;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(211, 47, 47, 0.4);
        }
        
        .btn.secondary {
            background: #ff9800;
        }
        
        .btn.secondary:hover {
            background: #f57c00;
        }
        
        @media (max-width: 600px) {
            .container {
                padding: 30px 20px;
                margin: 10px;
            }
            
            h1 {
                font-size: 1.8em;
            }
            
            .btn {
                display: block;
                margin: 10px 0;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon <?= $success ? 'success' : 'error' ?>">
            <?= $success ? '✅' : '❌' ?>
        </div>
        
        <h1><?= $success ? 'Account Confirmed!' : 'Confirmation Failed' ?></h1>
        
        <div class="message">
            <?= htmlspecialchars($message) ?>
        </div>
        
        <?php if ($success): ?>
            <a href="login.php" class="btn">Login Now</a>
            <a href="menu.php" class="btn secondary">View Menu</a>
        <?php else: ?>
            <a href="register.php" class="btn">Try Registration Again</a>
        <?php endif; ?>
        
        <a href="index.php" class="btn secondary">Back to Home</a>
    </div>
</body>
</html>