<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../src/bootstrap.php';
requireAdmin();

$container = getServiceContainer();
$config = $container->getSiteConfig();
$db = $container->getPdo();

$id = isset($_GET['id']) ? (int)$_GET['id'] : (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    header("Location: users.php");
    exit;
}

$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    setFlash('error', __('user_not_found') !== 'user_not_found' ? (string)__('user_not_found') : 'User not found.');
    header("Location: users.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $returnTo = (string)($_POST['return_to'] ?? '');
    $redirectUrl = ($returnTo === 'users.php') ? 'users.php' : "user_details.php?id=$id";

    if (!verifyCsrfToken((string)($_POST['csrf_token'] ?? ''))) {
        setFlash('error', (string)__('invalid_request'));
        header("Location: edit_user.php?id=" . $id);
        exit;
    }

    if (isset($_POST['send_reset_email'])) {
        $ipAddress = isset($_SERVER['REMOTE_ADDR']) ? (string)$_SERVER['REMOTE_ADDR'] : null;
        $lang = (string)($_SESSION['lang'] ?? 'ja');
        $sent = $container->getRequestPasswordResetUseCase()->sendResetForUserId($id, $ipAddress, $lang);

        if ($sent) {
            setFlash('success', sprintf((string)__('password_reset_email_sent_admin'), (string)$user['email']));
        } else {
            setFlash('error', sprintf((string)__('password_reset_email_failed_admin'), (string)$user['email']));
        }
        header("Location: " . $redirectUrl);
        exit;
    }

    $first = trim((string)($_POST['first_name'] ?? ''));
    $last = trim((string)($_POST['last_name'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));
    $newPassword = (string)($_POST['new_password'] ?? '');
    $status = isset($_POST['is_confirmed']) ? 1 : 0;
    $marketing = isset($_POST['accepts_marketing']) ? 1 : 0;

    if ($newPassword !== '' && strlen($newPassword) < 6) {
        setFlash('error', (string)__('error_password_length'));
        header("Location: " . ($returnTo === 'users.php' ? 'users.php' : "edit_user.php?id=$id"));
        exit;
    }

    $stmt = $db->prepare("UPDATE users SET first_name=?, last_name=?, email=?, phone=?, is_confirmed=?, accepts_marketing=? WHERE id=?");
    $stmt->execute([$first, $last, $email, $phone, $status, $marketing, $id]);

    if ($newPassword !== '') {
        $container->getResetPasswordUseCase()->adminResetPassword($id, $newPassword);
    }

    setFlash('success', __('user_updated_success') !== 'user_updated_success' ? (string)__('user_updated_success') : 'User updated successfully.');
    header("Location: " . $redirectUrl);
    exit;
}

$pageTitle = __('edit_user_title');
$pageIcon = '✏️';
require_once __DIR__ . '/includes/header.php';
?>

        <div class="section">
            <h2><?= htmlspecialchars((string)__('user_information'), ENT_QUOTES, 'UTF-8') ?></h2>
            <form method="post" style="max-width: 600px;" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="id" value="<?= (int)$id ?>">

                <label for="first_name"><?= htmlspecialchars((string)__('first_name'), ENT_QUOTES, 'UTF-8') ?></label>
                <input type="text" id="first_name" name="first_name" value="<?= htmlspecialchars((string)$user['first_name'], ENT_QUOTES, 'UTF-8') ?>" required>

                <label for="last_name"><?= htmlspecialchars((string)__('last_name'), ENT_QUOTES, 'UTF-8') ?></label>
                <input type="text" id="last_name" name="last_name" value="<?= htmlspecialchars((string)$user['last_name'], ENT_QUOTES, 'UTF-8') ?>" required>

                <label for="email"><?= htmlspecialchars((string)__('email'), ENT_QUOTES, 'UTF-8') ?></label>
                <input type="email" id="email" name="email" value="<?= htmlspecialchars((string)$user['email'], ENT_QUOTES, 'UTF-8') ?>" required>

                <label for="phone"><?= htmlspecialchars((string)__('phone'), ENT_QUOTES, 'UTF-8') ?></label>
                <input type="text" id="phone" name="phone" value="<?= htmlspecialchars((string)($user['phone'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">

                <label for="new_password"><?= htmlspecialchars((string)__('new_password_optional'), ENT_QUOTES, 'UTF-8') ?></label>
                <div style="display: flex; gap: 8px; align-items: center;">
                    <input type="password" id="new_password" name="new_password" minlength="6" autocomplete="new-password" placeholder="<?= htmlspecialchars((string)__('leave_blank_keep_password'), ENT_QUOTES, 'UTF-8') ?>">
                    <button type="button" class="btn btn-secondary" style="padding: 10px 14px; font-size: 0.85em;" onclick="toggleCustomerPasswordVisibility('new_password', this)">
                        <?= htmlspecialchars((string)__('show_password'), ENT_QUOTES, 'UTF-8') ?>
                    </button>
                </div>
                <p style="font-size: 0.85em; color: #64748b; margin-top: 4px;"><?= htmlspecialchars((string)__('leave_blank_keep_password'), ENT_QUOTES, 'UTF-8') ?></p>

                <label style="margin-top: 20px; font-weight: normal; display: flex; align-items: center; gap: 8px;">
                    <input type="checkbox" name="is_confirmed" <?= !empty($user['is_confirmed']) ? 'checked' : '' ?>> <?= htmlspecialchars((string)__('email_confirmed'), ENT_QUOTES, 'UTF-8') ?>
                </label>

                <label style="margin-top: 10px; font-weight: normal; display: flex; align-items: center; gap: 8px;">
                    <input type="checkbox" name="accepts_marketing" <?= !empty($user['accepts_marketing']) ? 'checked' : '' ?>> <?= htmlspecialchars((string)__('opted_in'), ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars((string)__('marketing_consent'), ENT_QUOTES, 'UTF-8') ?>)
                </label>

                <div style="margin-top: 25px; display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                    <button type="submit" class="btn btn-primary"><?= htmlspecialchars((string)__('save_user'), ENT_QUOTES, 'UTF-8') ?></button>
                    <button type="submit" name="send_reset_email" value="1" class="btn btn-secondary" formnovalidate><?= htmlspecialchars((string)__('send_password_reset_email_btn'), ENT_QUOTES, 'UTF-8') ?></button>
                    <a href="user_details.php?id=<?= (int)$id ?>" class="btn btn-back"><?= htmlspecialchars((string)__('back_to_users'), ENT_QUOTES, 'UTF-8') ?></a>
                </div>
            </form>
        </div>

        <script>
        function toggleCustomerPasswordVisibility(inputId, btn) {
            const input = document.getElementById(inputId);
            if (!input) return;
            if (input.type === 'password') {
                input.type = 'text';
                btn.textContent = <?= json_encode((string)__('hide_password')) ?>;
            } else {
                input.type = 'password';
                btn.textContent = <?= json_encode((string)__('show_password')) ?>;
            }
        }
        </script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>