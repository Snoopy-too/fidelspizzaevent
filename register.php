<?php
require_once 'config.php';

$config = getSiteConfig();
$error = '';
$success = '';
$code_verified = false;

// Check if access code is provided and valid
if ($_POST['access_code'] ?? '' === $config['registration_code']) {
    $code_verified = true;
}

// Handle registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    if (!$code_verified) {
        $error = __('error_invalid_access_code');
    } else {
        $first_name = sanitize($_POST['first_name'] ?? '');
        $last_name = sanitize($_POST['last_name'] ?? '');
        $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
        $phone = sanitize($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // Validation
        if (empty($first_name) || empty($last_name) || empty($email) || empty($password)) {
            $error = __('error_all_fields_required');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = __('error_invalid_email');
        } elseif (strlen($password) < 6) {
            $error = __('error_password_length');
        } elseif ($password !== $confirm_password) {
            $error = __('error_password_mismatch');
        } else {
            try {
                $db = getDB();
                
                // Check if email already exists
                $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $error = __('error_email_exists');
                } else {
                    // Create new user
                    $password_hash = hashPassword($password);
                    $confirmation_token = generateToken();
                    
                    $stmt = $db->prepare("INSERT INTO users (first_name, last_name, email, phone, password_hash, confirmation_token) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$first_name, $last_name, $email, $phone, $password_hash, $confirmation_token]);
                    
                    // Send confirmation email
                    $template = getEmailTemplate('confirmation_email');
                    if ($template) {
                        $confirmation_link = SITE_URL . "/confirm.php?token=" . $confirmation_token;
                        $placeholders = [
                            'first_name' => $first_name,
                            'confirmation_link' => $confirmation_link
                        ];
                        
                        $subject = replacePlaceholders($template['subject'], $placeholders);
                        $body = replacePlaceholders($template['body'], $placeholders);
                        
                        if (sendEmail($email, $subject, $body)) {
                            $success = __('success_registration');
                        } else {
                            $error = __('error_email_send_failed');
                        }
                    }
                }
            } catch (Exception $e) {
                $error = __('error_registration_failed');
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?= $_SESSION['lang'] ?? 'ja' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('register_title') ?> - <?= htmlspecialchars($config['site_title'] ?? __('site_title')) ?></title>
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
        
        input:disabled {
            background-color: #f5f5f5;
            cursor: not-allowed;
            opacity: 0.6;
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
        
        .btn:hover:not(:disabled) {
            background: #b71c1c;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(211, 47, 47, 0.4);
        }
        
        .btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        .btn.secondary {
            background: #ff9800;
        }
        
        .btn.secondary:hover:not(:disabled) {
            background: #f57c00;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-weight: bold;
        }
        
        .alert.error {
            background: #ffebee;
            color: #c62828;
            border: 1px solid #e57373;
        }
        
        .alert.success {
            background: #e8f5e8;
            color: #2e7d32;
            border: 1px solid #81c784;
        }
        
        .access-code-section {
            background: #fff3e0;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            border-left: 4px solid #ff9800;
        }
        
        .access-code-section h3 {
            color: #e65100;
            margin-bottom: 15px;
        }
        
        .back-link {
            text-align: center;
            margin-top: 20px;
        }
        
        .back-link a {
            color: #d32f2f;
            text-decoration: none;
            font-weight: bold;
        }
        
        .back-link a:hover {
            text-decoration: underline;
        }
        
        .password-strength {
            font-size: 0.9em;
            color: #666;
            margin-top: 5px;
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
        <h1>🍕 <?= __('register_title') ?></h1>
        
        <?php if ($error): ?>
            <div class="alert error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        
        <?php if (!$code_verified): ?>
            <div class="access-code-section">
                <h3><?= __('access_code_required_title') ?></h3>
                <p><?= __('access_code_required_desc') ?></p>
                <form method="POST">
                    <div class="form-group">
                        <label for="access_code"><?= __('access_code_label') ?></label>
                        <input type="password" id="access_code" name="access_code" maxlength="4" required>
                    </div>
                    <button type="submit" class="btn secondary"><?= __('verify_code_button') ?></button>
                </form>
            </div>
        <?php else: ?>
            <form method="POST" id="registrationForm">
                <input type="hidden" name="access_code" value="<?= htmlspecialchars($_POST['access_code']) ?>">
                
                <div class="form-group">
                    <label for="first_name"><?= __('first_name_label') ?></label>
                    <input type="text" id="first_name" name="first_name" required>
                </div>
                
                <div class="form-group">
                    <label for="last_name"><?= __('last_name_label') ?></label>
                    <input type="text" id="last_name" name="last_name" required>
                </div>
                
                <div class="form-group">
                    <label for="email"><?= __('email_label') ?></label>
                    <input type="email" id="email" name="email" required>
                </div>
                
                <div class="form-group">
                    <label for="phone"><?= __('phone_label') ?></label>
                    <input type="tel" id="phone" name="phone">
                </div>
                
                <div class="form-group">
                    <label for="password"><?= __('password_label') ?></label>
                    <input type="password" id="password" name="password" minlength="6" required>
                    <div class="password-strength">6文字以上</div>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password"><?= __('confirm_password_label') ?></label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
                
                <button type="submit" name="register" class="btn"><?= __('register_button') ?></button>
            </form>
        <?php endif; ?>
        
        <div class="back-link">
            <a href="index.php"><?= __('back_home') ?></a>
            <?php if (!$code_verified): ?>
            | <a href="login.php"><?= __('already_have_account') ?></a>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Password confirmation validation
        document.getElementById('confirm_password')?.addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (confirmPassword && password !== confirmPassword) {
                this.setCustomValidity('<?= __('error_password_mismatch') ?>');
            } else {
                this.setCustomValidity('');
            }
        });
        
        // Access code input formatting
        document.getElementById('access_code')?.addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '');
        });
    </script>
</body>
</html>