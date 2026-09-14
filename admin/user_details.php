<?php
declare(strict_types=1);

require_once '../config.php';
requireAdmin();

$config = getSiteConfig();
$db = getDB();

$id = $_GET['id'] ?? null;
if (!$id) {
    header("Location: users.php");
    exit;
}

// Get user
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch();

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
$orders = $stmt->fetchAll();

$pageTitle = __('user_details_title');
$pageIcon = '👤';
require_once __DIR__ . '/includes/header.php';
?>

        <div class="section">
            <h2><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></h2>
            <div class="user-details">
                <p><strong><?= __('email_address') ?>:</strong> <?= htmlspecialchars($user['email']) ?></p>
                <p><strong><?= __('phone') ?>:</strong> <?= htmlspecialchars($user['phone'] ?? '') ?: '-' ?></p>
                <p><strong><?= __('status') ?>:</strong>
                    <?php if ($user['is_confirmed']): ?>
                        <span class="status-badge status-confirmed"><?= __('confirmed') ?></span>
                    <?php else: ?>
                        <span class="status-badge status-pending"><?= __('pending') ?></span>
                    <?php endif; ?>
                </p>
                <p><strong><?= __('registration_date') ?>:</strong> <?= date('Y/m/d H:i', strtotime($user['created_at'])) ?></p>
            </div>

            <h3 style="margin-top: 30px; font-size: 1.3em; color: #2c3e50; border-bottom: 2px solid #3498db; padding-bottom: 8px;"><?= __('order_history_title') ?></h3>
            <?php if ($orders): ?>
            <table class="table">
                <thead>
                    <tr>
                        <th><?= __('order_number') ?></th>
                        <th><?= __('amount') ?></th>
                        <th><?= __('status') ?></th>
                        <th><?= __('date') ?></th>
                        <th><?= __('action') ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($orders as $o): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($o['order_number']) ?></strong></td>
                        <td><?= formatPrice((float)$o['total_amount']) ?></td>
                        <td>
                            <span class="status-badge status-<?= htmlspecialchars($o['status']) ?>">
                                <?= translateStatus($o['status']) ?>
                            </span>
                        </td>
                        <td><?= date('Y/m/d H:i', strtotime($o['created_at'])) ?></td>
                        <td>
                            <a href="order_details.php?id=<?= (int)$o['id'] ?>" style="color:#3498db; font-weight:bold;"><?= __('view_details') ?></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
                <p style="margin-top: 15px; color: #7f8c8d;"><?= __('no_orders_for_user') ?></p>
            <?php endif; ?>

            <div class="action-links" style="margin-top: 30px; display: flex; gap: 10px; flex-wrap: wrap;">
                <a href="edit_user.php?id=<?= (int)$user['id'] ?>" class="btn btn-edit">✏️ <?= __('edit') ?></a>
                <form method="POST" action="delete_user.php" style="display:inline;" onsubmit="return confirm('<?= __('confirm_delete_user') ?>');">
                    <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
                    <input type="hidden" name="id" value="<?= (int)$user['id'] ?>">
                    <button type="submit" class="btn btn-delete" style="cursor:pointer; border:none; font-family:inherit; font-size:inherit;">🗑 <?= __('delete_user') ?></button>
                </form>
                <a href="users.php" class="btn btn-back"><?= __('back_to_users') ?></a>
            </div>
        </div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>