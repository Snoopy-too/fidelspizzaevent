<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/src/bootstrap.php';

if (isLoggedIn()) {
    redirect(isAdmin() ? 'admin/dashboard.php' : 'menu.php');
}

$container = getServiceContainer();
$config = $container->getSiteConfig();

$error = '';
$success = '';
$submittedEmail = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedEmail = trim((string)($_POST['email'] ?? ''));

    if (!verifyCsrfToken((string)($_POST['csrf_token'] ?? ''))) {
        $error = (string)__('invalid_csrf_token');
    } elseif ($submittedEmail === '' || !filter_var($submittedEmail, FILTER_VALIDATE_EMAIL)) {
        $error = (string)__('error_invalid_email');
    } else {
        $ipAddress = isset($_SERVER['REMOTE_ADDR']) ? (string)$_SERVER['REMOTE_ADDR'] : null;
        $lang = (string)($_SESSION['lang'] ?? 'ja');

        $result = $container->getRequestPasswordResetUseCase()->execute($submittedEmail, $ipAddress, $lang);

        if (!$result['success'] && $result['error_code'] !== null) {
            $error = (string)__($result['error_code']);
        } else {
            $success = (string)__('forgot_password_sent_msg');
            $submittedEmail = '';
        }
    }
}

$siteTitle = htmlspecialchars((string)($config['site_title'] ?? __('site_title')), ENT_QUOTES, 'UTF-8');
$currentLang = htmlspecialchars((string)($_SESSION['lang'] ?? 'ja'), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="<?= $currentLang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars((string)__('forgot_password_title'), ENT_QUOTES, 'UTF-8') ?> - <?= $siteTitle ?></title>
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
            max-width: 440px;
            width: 100%;
        }

        .pizza-icon {
            font-size: 2.2em;
            text-align: center;
            margin-bottom: 15px;
        }

        h1 {
            color: #d32f2f;
            text-align: center;
            margin-bottom: 15px;
            font-size: 2em;
        }

        .description {
            color: #555;
            text-align: center;
            line-height: 1.6;
            margin-bottom: 25px;
            font-size: 0.95em;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 6px;
            color: #555;
            font-weight: bold;
        }

        input[type="email"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 1em;
            transition: border-color 0.3s ease;
        }

        input[type="email"]:focus {
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
            line-height: 1.5;
        }

        .alert.error {
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

        @media (max-width: 600px) {
            .container {
                padding: 30px 20px;
                margin: 10px;
            }

            h1 {
                font-size: 1.7em;
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

        <div class="pizza-icon">🔑</div>
        <h1><?= htmlspecialchars((string)__('forgot_password_title'), ENT_QUOTES, 'UTF-8') ?></h1>

        <?php if ($error !== ''): ?>
            <div class="alert error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <?php if ($success !== ''): ?>
            <div class="alert success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div>
        <?php else: ?>
            <p class="description"><?= htmlspecialchars((string)__('forgot_password_desc'), ENT_QUOTES, 'UTF-8') ?></p>

            <form method="POST" action="forgot_password.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">

                <div class="form-group">
                    <label for="email"><?= htmlspecialchars((string)__('email_label'), ENT_QUOTES, 'UTF-8') ?></label>
                    <input type="email"
                           id="email"
                           name="email"
                           value="<?= htmlspecialchars($submittedEmail, ENT_QUOTES, 'UTF-8') ?>"
                           autocomplete="email"
                           required>
                </div>

                <button type="submit" class="btn"><?= htmlspecialchars((string)__('send_reset_link_btn'), ENT_QUOTES, 'UTF-8') ?></button>
            </form>
        <?php endif; ?>

        <div class="links">
            <a href="login.php"><?= htmlspecialchars((string)__('back_to_login'), ENT_QUOTES, 'UTF-8') ?></a>
            <a href="index.php"><?= htmlspecialchars((string)__('back_home'), ENT_QUOTES, 'UTF-8') ?></a>
        </div>
    </div>
</body>
</html>
