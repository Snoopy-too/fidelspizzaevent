<?php
declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

$container = getServiceContainer();
$config = getSiteConfig();
$error = '';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect(isAdmin() ? 'admin/dashboard.php' : 'menu.php');
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $password = (string)($_POST['password'] ?? '');

    $authUseCase = $container->getAuthenticateUserUseCase();
    $request = new \FidelsPizza\Application\DTO\AuthenticateUserRequest(
        identifier: (string)$email,
        password: $password,
        allowAdminFallback: true
    );

    $result = $authUseCase->authenticateCustomer($request);

    if ($result->success && $result->user !== null) {
        $user = $result->user;

        if ($user->isCustomer()) {
            $_SESSION['user_id'] = $user->getId();
            $_SESSION['user_name'] = $user->getUsernameOrName();

            if (!empty($user->getPreferredLang()) && in_array($user->getPreferredLang(), ['ja', 'en'], true)) {
                $_SESSION['lang'] = $user->getPreferredLang();
            }

            $userTimeout = defined('USER_SESSION_TIMEOUT') ? USER_SESSION_TIMEOUT : 7200;
            $authUseCase->createSessionRecord(session_id(), $user, $userTimeout);
            redirect('menu.php');
        } elseif ($user->isAdmin()) {
            $_SESSION['admin_id'] = $user->getId();
            $_SESSION['admin_username'] = $user->getUsernameOrName();

            $adminTimeout = defined('ADMIN_SESSION_TIMEOUT') ? ADMIN_SESSION_TIMEOUT : 3600;
            $authUseCase->createSessionRecord(session_id(), $user, $adminTimeout);
            redirect('admin/dashboard.php');
        }
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
    <title><?= htmlspecialchars((string)__('login'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> - <?= htmlspecialchars((string)($config['site_title'] ?? __('site_title')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></title>
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
        
        .alert.success {
            background: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #81c784;
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
        <!-- Language Switcher -->
        <?php $currentLang = (string)($_SESSION['lang'] ?? 'ja'); ?>
        <div style="display: flex; justify-content: flex-end; margin-bottom: 10px;">
            <a href="?lang=<?= $currentLang === 'ja' ? 'en' : 'ja' ?>" style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; border-radius: 20px; font-size: 0.88em; font-weight: 700; text-decoration: none; color: #4b5563; background: #f3f4f6; border: 1px solid #d1d5db; transition: all 0.2s ease;">
                🌐 <?= $currentLang === 'ja' ? 'English' : '日本語' ?>
            </a>
        </div>

        <div class="pizza-icon">🍕</div>
        <h1><?= htmlspecialchars((string)__('welcome_back'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h1>
        
        <?php
        $flash = function_exists('getFlash') ? getFlash() : null;
        if ($flash && !empty($flash['message'])):
            $flashType = ($flash['type'] ?? '') === 'success' ? 'success' : 'error';
        ?>
            <div class="alert <?= $flashType ?>"><?= htmlspecialchars((string)$flash['message'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert"><?= htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="email"><?= htmlspecialchars((string)__('email_label'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></label>
                <input type="email" id="email" name="email" value="<?= htmlspecialchars((string)($_POST['email'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" required>
            </div>
            
            <div class="form-group">
                <label for="password"><?= htmlspecialchars((string)__('password_label'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <button type="submit" class="btn"><?= htmlspecialchars((string)__('login_button'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></button>
        </form>
        
        <div class="links">
            <a href="register.php"><?= htmlspecialchars((string)__('no_account'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></a>
            <a href="forgot_password.php"><?= htmlspecialchars((string)__('forgot_password'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></a>
            <a href="index.php"><?= htmlspecialchars((string)__('back_home'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></a>
        </div>
    </div>
</body>
</html>
