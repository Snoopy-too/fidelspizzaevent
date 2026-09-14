<?php
require_once '../config.php';
require_once __DIR__ . '/../helpers.php';
requireAdmin();

$config = getSiteConfig();
$db = getDB();

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = __('invalid_request');
    } else {
        $first = trim($_POST['first_name'] ?? '');
        $last = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        $status = isset($_POST['is_confirmed']) ? 1 : 0;

        if (!$first || !$last || !$email || !$password) {
            $errors[] = __('error_all_fields_required');
        } else {
            // check if email already exists
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $errors[] = __('error_email_exists');
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);

                $stmt = $db->prepare("
                    INSERT INTO users (first_name, last_name, email, phone, password_hash, is_confirmed, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$first, $last, $email, $phone, $hash, $status]);

                $newId = (int)$db->lastInsertId();
                setFlash('success', __('user_saved') !== 'user_saved' ? __('user_saved') : 'User created successfully.');
                header("Location: user_details.php?id=$newId");
                exit;
            }
        }
    }
}

$pageTitle = __('add_user_title');
$pageIcon = '➕';
require_once __DIR__ . '/includes/header.php';
?>

        <div class="section">
            <h2><?= __('user_information') ?></h2>

            <?php if ($errors): ?>
                <div class="alert alert-error">
                    <?php foreach ($errors as $err): ?>
                        <p><?= htmlspecialchars($err) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="post" style="max-width: 600px;">
                <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">

                <label for="first_name"><?= __('first_name') ?></label>
                <input type="text" id="first_name" name="first_name" value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>" required>

                <label for="last_name"><?= __('last_name') ?></label>
                <input type="text" id="last_name" name="last_name" value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>" required>

                <label for="email"><?= __('email') ?></label>
                <input type="email" id="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>

                <label for="phone"><?= __('phone') ?></label>
                <input type="text" id="phone" name="phone" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">

                <label for="password"><?= __('password') ?></label>
                <input type="password" id="password" name="password" required>

                <label style="margin-top: 20px; font-weight: normal; display: flex; align-items: center; gap: 8px;">
                    <input type="checkbox" name="is_confirmed" <?= !empty($_POST['is_confirmed']) ? 'checked' : '' ?>> <?= __('email_confirmed') ?>
                </label>

                <div style="margin-top: 25px; display: flex; gap: 10px; align-items: center;">
                    <button type="submit" class="btn btn-primary" style="background:#27ae60;"><?= __('save_user') ?></button>
                    <a href="users.php" class="btn btn-back"><?= __('back_to_users') ?></a>
                </div>
            </form>
        </div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>