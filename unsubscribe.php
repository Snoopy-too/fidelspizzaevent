<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/src/bootstrap.php';

$container = getServiceContainer();
$config = $container->getSiteConfig();

$token = trim((string)($_POST['token'] ?? $_GET['token'] ?? ''));
$isSubmitted = $_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['confirm']);

$user = null;
$result = null;

if ($token !== '') {
    $user = $container->getUserRepository()->findByUnsubscribeToken($token);
    if ($isSubmitted && $user) {
        $result = $container->getUnsubscribeUserUseCase()->execute($token);
    }
}

$siteTitle = htmlspecialchars((string)($config['site_title'] ?? "Fidel's Pizza Event"), ENT_QUOTES, 'UTF-8');
$currentLang = (string)($_SESSION['lang'] ?? 'ja');
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($currentLang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('unsubscribe_title') ?> - <?= $siteTitle ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            color: #333;
        }
        .card {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            max-width: 480px;
            width: 100%;
            padding: 35px 30px;
            text-align: center;
        }
        .icon {
            font-size: 48px;
            margin-bottom: 15px;
        }
        h1 {
            font-size: 22px;
            color: #2c3e50;
            margin-bottom: 15px;
        }
        p {
            font-size: 15px;
            line-height: 1.6;
            color: #555;
            margin-bottom: 20px;
        }
        .user-badge {
            display: inline-block;
            background: #f1f3f5;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 14px;
            color: #495057;
            margin-bottom: 25px;
            word-break: break-all;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            font-size: 15px;
            font-weight: 600;
            border-radius: 6px;
            text-decoration: none;
            cursor: pointer;
            border: none;
            transition: background-color 0.2s ease;
        }
        .btn-danger {
            background-color: #e74c3c;
            color: #ffffff;
            width: 100%;
        }
        .btn-danger:hover {
            background-color: #c0392b;
        }
        .btn-secondary {
            background-color: #6c757d;
            color: #ffffff;
            margin-top: 15px;
            width: 100%;
        }
        .btn-secondary:hover {
            background-color: #5a6268;
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            padding: 14px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            padding: 14px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">🍕</div>
        <h1><?= $siteTitle ?></h1>

        <?php if ($result !== null): ?>
            <?php if ($result['success']): ?>
                <div class="alert-success">
                    <strong>✓ <?= __('unsubscribe_success_msg') ?></strong>
                </div>
                <p><?= htmlspecialchars($result['email'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
                <a href="index.php" class="btn btn-secondary"><?= __('back_home') ?></a>
            <?php else: ?>
                <div class="alert-error">
                    <?= htmlspecialchars($result['message'], ENT_QUOTES, 'UTF-8') ?>
                </div>
                <a href="index.php" class="btn btn-secondary"><?= __('back_home') ?></a>
            <?php endif; ?>

        <?php elseif ($user !== null): ?>
            <?php if (!$user->acceptsMarketing()): ?>
                <div class="alert-success">
                    <?= __('unsubscribe_success_msg') ?>
                </div>
                <div class="user-badge"><?= htmlspecialchars($user->getEmail(), ENT_QUOTES, 'UTF-8') ?></div>
                <a href="index.php" class="btn btn-secondary"><?= __('back_home') ?></a>
            <?php else: ?>
                <p><?= __('unsubscribe_confirm_msg') ?></p>
                <div class="user-badge"><?= htmlspecialchars($user->getEmail(), ENT_QUOTES, 'UTF-8') ?></div>

                <form method="POST" action="unsubscribe.php">
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
                    <button type="submit" class="btn btn-danger"><?= __('unsubscribe_btn') ?></button>
                </form>
                <a href="index.php" class="btn btn-secondary"><?= __('back_home') ?></a>
            <?php endif; ?>

        <?php else: ?>
            <div class="alert-error">
                <?= __('unsubscribe_invalid_msg') ?>
            </div>
            <a href="index.php" class="btn btn-secondary"><?= __('back_home') ?></a>
        <?php endif; ?>
    </div>
</body>
</html>
