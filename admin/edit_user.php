<?php
require_once '../config.php';
require_once __DIR__ . '/../helpers.php';
requireAdmin();

$config = getSiteConfig();
$db = getDB();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    header("Location: users.php");
    exit;
}

$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch();

if (!$user) {
    setFlash('error', __('user_not_found') !== 'user_not_found' ? __('user_not_found') : 'User not found.');
    header("Location: users.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', __('invalid_request'));
        header("Location: edit_user.php?id=" . $id);
        exit;
    }

    $first = trim($_POST['first_name'] ?? '');
    $last = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $status = isset($_POST['is_confirmed']) ? 1 : 0;

    $stmt = $db->prepare("UPDATE users SET first_name=?, last_name=?, email=?, phone=?, is_confirmed=? WHERE id=?");
    $stmt->execute([$first, $last, $email, $phone, $status, $id]);

    setFlash('success', __('user_saved') !== 'user_saved' ? __('user_saved') : 'User updated successfully.');
    header("Location: user_details.php?id=$id");
    exit;
}

$pageTitle = __('edit_user_title');
$pageIcon = '✏️';
require_once __DIR__ . '/includes/header.php';
?>

        <div class="section">
            <h2><?= __('user_information') ?></h2>
            <form method="post" style="max-width: 600px;">
                <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">

                <label for="first_name"><?= __('first_name') ?></label>
                <input type="text" id="first_name" name="first_name" value="<?= htmlspecialchars($user['first_name']) ?>" required>

                <label for="last_name"><?= __('last_name') ?></label>
                <input type="text" id="last_name" name="last_name" value="<?= htmlspecialchars($user['last_name']) ?>" required>

                <label for="email"><?= __('email') ?></label>
                <input type="email" id="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>

                <label for="phone"><?= __('phone') ?></label>
                <input type="text" id="phone" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">

                <label style="margin-top: 20px; font-weight: normal; display: flex; align-items: center; gap: 8px;">
                    <input type="checkbox" name="is_confirmed" <?= $user['is_confirmed'] ? 'checked' : '' ?>> <?= __('email_confirmed') ?>
                </label>

                <div style="margin-top: 25px; display: flex; gap: 10px; align-items: center;">
                    <button type="submit" class="btn btn-primary"><?= __('save_user') ?></button>
                    <a href="user_details.php?id=<?= $id ?>" class="btn btn-back"><?= __('back_to_users') ?></a>
                </div>
            </form>
        </div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>