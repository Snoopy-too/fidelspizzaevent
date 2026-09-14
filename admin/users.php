<?php
declare(strict_types=1);

require_once '../config.php';
require_once __DIR__ . '/../helpers.php';
requireAdmin();

$config = getSiteConfig();
$db = getDB();

// Fetch all users
$stmt = $db->query("
    SELECT id, first_name, last_name, email, is_confirmed, created_at
    FROM users 
    ORDER BY created_at DESC
");
$users = $stmt->fetchAll();

$page_title = __('user_management');
require_once __DIR__ . '/includes/header.php';
?>
        <div class="section">
            <div class="section-header">
                <h2><?= __('registered_users') ?></h2>
                <a href="add_user.php" class="btn btn-add add-user-btn"><?= __('add_user_button') ?></a>
            </div>

            <?php if (!empty($users)): ?>
            <table class="table">
                <thead>
                    <tr>
                        <th><?= __('user_id') ?></th>
                        <th><?= __('full_name') ?></th>
                        <th><?= __('email_address') ?></th>
                        <th><?= __('status') ?></th>
                        <th><?= __('registration_date') ?></th>
                        <th><?= __('operations') ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?= htmlspecialchars($user['id']) ?></td>
                        <td><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></td>
                        <td><?= htmlspecialchars($user['email']) ?></td>
                        <td>
                            <?php if ($user['is_confirmed']): ?>
                                <span class="status-badge status-confirmed"><?= __('confirmed') ?></span>
                            <?php else: ?>
                                <span class="status-badge status-pending"><?= __('pending') ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?= date('Y/m/d', strtotime($user['created_at'])) ?></td>
                        <td class="action-links">
                            <a href="user_details.php?id=<?= $user['id'] ?>" style="color: #3498db;"><?= __('details') ?></a> | 
                            <a href="edit_user.php?id=<?= $user['id'] ?>" style="color: #f39c12;"><?= __('edit') ?></a> | 
                            <form method="POST" action="delete_user.php" style="display:inline;" onsubmit="return confirm('<?= __('confirm_delete_user') ?>');">
                                <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
                                <input type="hidden" name="id" value="<?= $user['id'] ?>">
                                <button type="submit" style="background:none;border:none;color:#e74c3c;cursor:pointer;font-weight:bold;padding:0;font-size:inherit;font-family:inherit;"><?= __('delete_user') ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
                <p><?= __('no_users_found') ?></p>
            <?php endif; ?>
        </div>
<?php
require_once __DIR__ . '/includes/footer.php';