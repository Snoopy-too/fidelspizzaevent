<?php
declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

$container = getServiceContainer();
$config = getSiteConfig();
$error = '';

// Redirect if already logged in as admin
if (isAdmin()) {
    redirect('admin/dashboard.php');
}

// Handle admin login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loginInput = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    $authUseCase = $container->getAuthenticateUserUseCase();
    $result = $authUseCase->authenticateAdmin($loginInput, $password);

    if ($result->success && $result->user !== null) {
        $admin = $result->user;
        $_SESSION['admin_id'] = $admin->getId();
        $_SESSION['admin_username'] = $admin->getUsernameOrName();

        $authUseCase->createSessionRecord(session_id(), $admin, ADMIN_SESSION_TIMEOUT);
        redirect('admin/dashboard.php');
    } else {
        $errorCode = $result->errorCode ?? 'error_login_failed';
        $translated = (string)__($errorCode);
        $error = $translated !== $errorCode ? $translated : ($result->errorMessage ?? 'Login failed');
    }
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars((string)($_SESSION['lang'] ?? 'ja'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars((string)__('admin_login_title'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> - <?= htmlspecialchars((string)($config['site_title'] ?? __('site_title')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></title>
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
        <h1><?= htmlspecialchars((string)__('admin_login_title'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h1>
        
        <div class="security-note">
            <?= htmlspecialchars((string)__('admin_area_warning'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
        </div>
        
        <?php if ($error): ?>
            <div class="alert"><?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="username"><?= htmlspecialchars((string)__('username_label'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></label>
                <input type="text" id="username" name="username" value="<?= htmlspecialchars((string)($_POST['username'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" required autocomplete="username">
            </div>
            
            <div class="form-group">
                <label for="password"><?= htmlspecialchars((string)__('password_label'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></label>
                <input type="password" id="password" name="password" required autocomplete="current-password">
            </div>
            
            <button type="submit" class="btn"><?= htmlspecialchars((string)__('login_admin_panel'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></button>
        </form>
        
        <div class="back-link">
            <a href="index.php"><?= htmlspecialchars((string)__('back_home'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></a>
        </div>
    </div>
</body>
</html>
