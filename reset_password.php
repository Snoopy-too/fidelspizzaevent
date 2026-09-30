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
$resetUseCase = $container->getResetPasswordUseCase();

$rawToken = trim((string)($_POST['token'] ?? $_GET['token'] ?? ''));
$error = '';
$validated = $rawToken !== '' ? $resetUseCase->validateToken($rawToken) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken((string)($_POST['csrf_token'] ?? ''))) {
        $error = (string)__('invalid_csrf_token');
    } elseif ($validated === null) {
        $error = (string)__('error_invalid_or_expired_reset_token');
    } else {
        $newPassword = (string)($_POST['password'] ?? '');
        $confirmPassword = (string)($_POST['confirm_password'] ?? '');

        $result = $resetUseCase->execute($rawToken, $newPassword, $confirmPassword);
        if ($result['success']) {
            setFlash('success', (string)__('success_password_reset'));
            redirect('login.php');
        } else {
            $errorCode = $result['error_code'] ?? 'error_invalid_or_expired_reset_token';
            $error = (string)__($errorCode);
            // Re-validate token state in case it expired or was consumed
            $validated = $resetUseCase->validateToken($rawToken);
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
    <title><?= htmlspecialchars((string)__('reset_password_title'), ENT_QUOTES, 'UTF-8') ?> - <?= $siteTitle ?></title>
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
            word-break: break-word;
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

        .password-wrapper {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        input[type="password"],
        input[type="text"] {
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

        .toggle-btn {
            padding: 11px 14px;
            background: #f1f5f9;
            color: #475569;
            border: 2px solid #cbd5e1;
            border-radius: 8px;
            font-size: 0.85em;
            font-weight: bold;
            cursor: pointer;
            white-space: nowrap;
        }

        .toggle-btn:hover {
            background: #e2e8f0;
        }

        .password-strength {
            font-size: 0.88em;
            color: #666;
            margin-top: 5px;
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
            text-align: center;
            text-decoration: none;
            display: inline-block;
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
        <div class="pizza-icon">🔒</div>
        <h1><?= htmlspecialchars((string)__('reset_password_title'), ENT_QUOTES, 'UTF-8') ?></h1>

        <?php if ($error !== ''): ?>
            <div class="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <?php if ($validated !== null): ?>
            <?php
            $userEmailSafe = htmlspecialchars($validated['user']['email'], ENT_QUOTES, 'UTF-8');
            $descText = sprintf((string)__('reset_password_desc'), $userEmailSafe);
            ?>
            <p class="description"><?= $descText ?></p>

            <form method="POST" action="reset_password.php" id="resetPasswordForm">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="token" value="<?= htmlspecialchars($rawToken, ENT_QUOTES, 'UTF-8') ?>">

                <div class="form-group">
                    <label for="password"><?= htmlspecialchars((string)__('new_password_label'), ENT_QUOTES, 'UTF-8') ?></label>
                    <div class="password-wrapper">
                        <input type="password"
                               id="password"
                               name="password"
                               minlength="6"
                               autocomplete="new-password"
                               required>
                        <button type="button" class="toggle-btn" onclick="toggleResetPasswordVisibility('password', this)">
                            <?= htmlspecialchars((string)__('show_password'), ENT_QUOTES, 'UTF-8') ?>
                        </button>
                    </div>
                    <div class="password-strength"><?= htmlspecialchars((string)__('password_min_length_hint'), ENT_QUOTES, 'UTF-8') ?></div>
                </div>

                <div class="form-group">
                    <label for="confirm_password"><?= htmlspecialchars((string)__('confirm_new_password_label'), ENT_QUOTES, 'UTF-8') ?></label>
                    <input type="password"
                           id="confirm_password"
                           name="confirm_password"
                           minlength="6"
                           autocomplete="new-password"
                           required>
                </div>

                <button type="submit" class="btn"><?= htmlspecialchars((string)__('reset_password_button'), ENT_QUOTES, 'UTF-8') ?></button>
            </form>
        <?php else: ?>
            <?php if ($error === ''): ?>
                <div class="alert"><?= htmlspecialchars((string)__('error_invalid_or_expired_reset_token'), ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
            <a href="forgot_password.php" class="btn"><?= htmlspecialchars((string)__('request_new_reset_link'), ENT_QUOTES, 'UTF-8') ?></a>
        <?php endif; ?>

        <div class="links">
            <a href="login.php"><?= htmlspecialchars((string)__('back_to_login'), ENT_QUOTES, 'UTF-8') ?></a>
            <a href="index.php"><?= htmlspecialchars((string)__('back_home'), ENT_QUOTES, 'UTF-8') ?></a>
        </div>
    </div>

    <script>
        const showLabel = <?= json_encode((string)__('show_password')) ?>;
        const hideLabel = <?= json_encode((string)__('hide_password')) ?>;
        const mismatchMsg = <?= json_encode((string)__('error_password_mismatch')) ?>;

        function toggleResetPasswordVisibility(inputId, btn) {
            const input = document.getElementById(inputId);
            if (!input) return;
            if (input.type === 'password') {
                input.type = 'text';
                btn.textContent = hideLabel;
            } else {
                input.type = 'password';
                btn.textContent = showLabel;
            }
        }

        const pwdInput = document.getElementById('password');
        const confirmInput = document.getElementById('confirm_password');

        function validatePasswordMatch() {
            if (!pwdInput || !confirmInput) return;
            if (confirmInput.value && pwdInput.value !== confirmInput.value) {
                confirmInput.setCustomValidity(mismatchMsg);
            } else {
                confirmInput.setCustomValidity('');
            }
        }

        pwdInput?.addEventListener('input', validatePasswordMatch);
        confirmInput?.addEventListener('input', validatePasswordMatch);
    </script>
</body>
</html>
