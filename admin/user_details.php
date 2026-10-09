<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helpers.php';
requireAdmin();

$config = getSiteConfig();
$db = getDB();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header("Location: users.php");
    exit;
}

// Get user
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header("Location: users.php?error=notfound");
    exit;
}

// Fetch user orders
$stmt = $db->prepare("
    SELECT o.id, o.order_number, o.total_amount, o.status, o.created_at
    FROM orders o
    WHERE o.user_id = ?
    ORDER BY o.created_at DESC
");
$stmt->execute([$id]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = __('user_details_title');
$pageIcon = '👤';
require_once __DIR__ . '/includes/header.php';
?>

        <div class="section">
            <h2><?= htmlspecialchars((string)$user['first_name'] . ' ' . (string)$user['last_name'], ENT_QUOTES, 'UTF-8') ?></h2>
            <div class="user-details">
                <p><strong><?= htmlspecialchars((string)__('email_address'), ENT_QUOTES, 'UTF-8') ?>:</strong> <?= htmlspecialchars((string)$user['email'], ENT_QUOTES, 'UTF-8') ?></p>
                <p><strong><?= htmlspecialchars((string)__('phone'), ENT_QUOTES, 'UTF-8') ?>:</strong> <?= htmlspecialchars((string)($user['phone'] ?? ''), ENT_QUOTES, 'UTF-8') ?: '-' ?></p>
                <p><strong><?= htmlspecialchars((string)__('status'), ENT_QUOTES, 'UTF-8') ?>:</strong>
                    <?php if (!empty($user['is_confirmed'])): ?>
                        <span class="status-badge status-confirmed"><?= htmlspecialchars((string)__('confirmed'), ENT_QUOTES, 'UTF-8') ?></span>
                    <?php else: ?>
                        <span class="status-badge status-pending"><?= htmlspecialchars((string)__('pending'), ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endif; ?>
                </p>
                <p><strong><?= htmlspecialchars((string)__('preferred_language'), ENT_QUOTES, 'UTF-8') ?>:</strong>
                    <?php if (($user['preferred_lang'] ?? 'ja') === 'en'): ?>
                        <span class="status-badge" style="background:#e0f2fe; color:#0369a1; font-weight:700;">🇺🇸 English</span>
                    <?php else: ?>
                        <span class="status-badge" style="background:#fef3c7; color:#92400e; font-weight:700;">🇯🇵 日本語</span>
                    <?php endif; ?>
                </p>
                <p><strong><?= htmlspecialchars((string)__('registration_date'), ENT_QUOTES, 'UTF-8') ?>:</strong> <?= date('Y/m/d H:i', strtotime((string)$user['created_at'])) ?></p>
            </div>

            <!-- Dedicated Admin Change Customer Password Card -->
            <div style="margin-top: 28px; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 10px; padding: 20px; max-width: 560px;">
                <h3 style="margin-top: 0; margin-bottom: 8px; color: #1e293b; font-size: 1.15em;">
                    🔑 <?= htmlspecialchars((string)__('change_customer_password_title'), ENT_QUOTES, 'UTF-8') ?>
                </h3>
                <p style="font-size: 0.9em; color: #475569; margin-bottom: 16px; line-height: 1.5;">
                    <?= htmlspecialchars((string)__('change_customer_password_desc'), ENT_QUOTES, 'UTF-8') ?>
                </p>
                <form method="POST" action="edit_user.php?id=<?= (int)$user['id'] ?>" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="id" value="<?= (int)$user['id'] ?>">
                    <input type="hidden" name="admin_change_password" value="1">

                    <label for="details_new_password"><?= htmlspecialchars((string)__('new_password_label'), ENT_QUOTES, 'UTF-8') ?></label>
                    <div style="display: flex; gap: 8px; align-items: center; margin-bottom: 12px;">
                        <input type="password"
                               id="details_new_password"
                               name="new_password"
                               minlength="6"
                               autocomplete="new-password"
                               placeholder="<?= htmlspecialchars((string)__('password_min_length_hint'), ENT_QUOTES, 'UTF-8') ?>"
                               style="margin-bottom: 0;"
                               required>
                        <button type="button"
                                id="details_toggle_pwd_btn"
                                class="btn btn-secondary"
                                style="padding: 10px 14px; font-size: 0.85em; white-space: nowrap;"
                                onclick="toggleDetailsPasswordVisibility()">
                            <?= htmlspecialchars((string)__('show_password'), ENT_QUOTES, 'UTF-8') ?>
                        </button>
                        <button type="button"
                                class="btn btn-secondary"
                                style="padding: 10px 14px; font-size: 0.85em; white-space: nowrap;"
                                onclick="generateDetailsCustomerPassword()">
                            <?= htmlspecialchars((string)__('generate_password_btn'), ENT_QUOTES, 'UTF-8') ?>
                        </button>
                    </div>

                    <label for="details_confirm_password"><?= htmlspecialchars((string)__('confirm_new_password_label'), ENT_QUOTES, 'UTF-8') ?></label>
                    <input type="password"
                           id="details_confirm_password"
                           name="confirm_password"
                           minlength="6"
                           autocomplete="new-password"
                           placeholder="<?= htmlspecialchars((string)__('password_min_length_hint'), ENT_QUOTES, 'UTF-8') ?>"
                           required>

                    <label style="margin-top: 12px; font-weight: normal; display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" name="confirm_email" value="1" checked>
                        <?= htmlspecialchars((string)__('also_confirm_email_label'), ENT_QUOTES, 'UTF-8') ?>
                    </label>

                    <div style="margin-top: 16px; display: flex; gap: 10px; flex-wrap: wrap;">
                        <button type="submit" class="btn btn-primary"><?= htmlspecialchars((string)__('update_password_btn'), ENT_QUOTES, 'UTF-8') ?></button>
                    </div>
                </form>
            </div>

            <h3 style="margin-top: 30px; font-size: 1.3em; color: #2c3e50; border-bottom: 2px solid #3498db; padding-bottom: 8px;"><?= htmlspecialchars((string)__('order_history_title'), ENT_QUOTES, 'UTF-8') ?></h3>
            <?php if ($orders): ?>
            <table class="table">
                <thead>
                    <tr>
                        <th><?= htmlspecialchars((string)__('order_number'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars((string)__('amount'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars((string)__('status'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars((string)__('date'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars((string)__('action'), ENT_QUOTES, 'UTF-8') ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($orders as $o): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars((string)$o['order_number'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                        <td><?= formatPrice((float)$o['total_amount']) ?></td>
                        <td>
                            <span class="status-badge status-<?= htmlspecialchars((string)$o['status'], ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars(translateStatus((string)$o['status']), ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </td>
                        <td><?= date('Y/m/d H:i', strtotime((string)$o['created_at'])) ?></td>
                        <td>
                            <a href="order_details.php?id=<?= (int)$o['id'] ?>" style="color:#3498db; font-weight:bold;"><?= htmlspecialchars((string)__('view_details'), ENT_QUOTES, 'UTF-8') ?></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
                <p style="margin-top: 15px; color: #7f8c8d;"><?= htmlspecialchars((string)__('no_orders_for_user'), ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>

            <div class="action-links" style="margin-top: 30px; display: flex; gap: 10px; flex-wrap: wrap;">
                <a href="edit_user.php?id=<?= (int)$user['id'] ?>" class="btn btn-edit">✏️ <?= htmlspecialchars((string)__('edit'), ENT_QUOTES, 'UTF-8') ?></a>
                <form method="POST" action="delete_user.php" style="display:inline;" onsubmit="return confirm(<?= htmlspecialchars(json_encode((string)__('confirm_delete_user')), ENT_QUOTES, 'UTF-8') ?>);">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="id" value="<?= (int)$user['id'] ?>">
                    <button type="submit" class="btn btn-delete" style="cursor:pointer; border:none; font-family:inherit; font-size:inherit;">🗑 <?= htmlspecialchars((string)__('delete_user'), ENT_QUOTES, 'UTF-8') ?></button>
                </form>
                <a href="users.php" class="btn btn-back"><?= htmlspecialchars((string)__('back_to_users'), ENT_QUOTES, 'UTF-8') ?></a>
            </div>
        </div>

        <script>
        function toggleDetailsPasswordVisibility() {
            const p1 = document.getElementById('details_new_password');
            const p2 = document.getElementById('details_confirm_password');
            const btn = document.getElementById('details_toggle_pwd_btn');
            if (!p1 || !btn) return;
            if (p1.type === 'password') {
                p1.type = 'text';
                if (p2) p2.type = 'text';
                btn.textContent = <?= json_encode((string)__('hide_password')) ?>;
            } else {
                p1.type = 'password';
                if (p2) p2.type = 'password';
                btn.textContent = <?= json_encode((string)__('show_password')) ?>;
            }
        }

        function generateDetailsCustomerPassword() {
            const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
            let generated = '';
            const randomValues = new Uint32Array(10);
            window.crypto.getRandomValues(randomValues);
            for (let i = 0; i < 10; i++) {
                generated += chars[randomValues[i] % chars.length];
            }
            const p1 = document.getElementById('details_new_password');
            const p2 = document.getElementById('details_confirm_password');
            const btn = document.getElementById('details_toggle_pwd_btn');
            if (p1) {
                p1.value = generated;
                p1.type = 'text';
            }
            if (p2) {
                p2.value = generated;
                p2.type = 'text';
            }
            if (btn) {
                btn.textContent = <?= json_encode((string)__('hide_password')) ?>;
            }
        }
        </script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>