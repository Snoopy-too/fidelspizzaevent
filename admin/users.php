<?php
declare(strict_types=1);

require_once '../config.php';
require_once __DIR__ . '/../helpers.php';
requireAdmin();

$config = getSiteConfig();
$db = getDB();

// Fetch all users with marketing consent status
$stmt = $db->query("
    SELECT id, first_name, last_name, email, is_confirmed, accepts_marketing, created_at
    FROM users 
    ORDER BY created_at DESC
");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = __('user_management');
require_once __DIR__ . '/includes/header.php';
?>
        <div class="section">
            <div class="section-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                <h2><?= __('registered_users') ?></h2>
                <div style="display: flex; gap: 10px;">
                    <a href="promotions.php" class="btn" style="background-color: #9b59b6; color: white; text-decoration: none; padding: 10px 16px; border-radius: 5px; font-weight: bold;">
                        📧 <?= __('promotions_title') ?>
                    </a>
                    <a href="add_user.php" class="btn btn-add add-user-btn"><?= __('add_user_button') ?></a>
                </div>
            </div>

            <?php if (!empty($users)): ?>
            <form id="bulkUsersForm" method="GET" action="promotions.php">
                <!-- Bulk actions toolbar -->
                <div style="background: #eef2f7; padding: 12px 18px; border-radius: 8px; margin-bottom: 15px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <input type="checkbox" id="selectAllCheckbox" style="cursor: pointer; width: 18px; height: 18px;">
                        <label for="selectAllCheckbox" style="font-weight: bold; cursor: pointer; user-select: none;">
                            <?= __('select_all_recipients') ?>
                        </label>
                        <span id="selectedCounter" style="color: #666; font-size: 0.9em; margin-left: 10px;">(0 <?= __('recipients_selected') ?>)</span>
                    </div>
                    <div>
                        <button type="submit" id="emailSelectedBtn" class="btn" style="background-color: #e74c3c; color: white; border: none; padding: 8px 16px; border-radius: 5px; font-weight: bold; cursor: pointer; display: none;">
                            <?= sprintf(__('send_to_selected_users'), 0) ?>
                        </button>
                    </div>
                </div>

                <table class="table">
                    <thead>
                        <tr>
                            <th style="width: 40px; text-align: center;">#</th>
                            <th><?= __('user_id') ?></th>
                            <th><?= __('full_name') ?></th>
                            <th><?= __('email_address') ?></th>
                            <th><?= __('status') ?></th>
                            <th><?= __('marketing_consent') ?></th>
                            <th><?= __('registration_date') ?></th>
                            <th><?= __('operations') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td style="text-align: center;">
                                <input type="checkbox" name="user_ids[]" value="<?= (int)$user['id'] ?>" class="user-select-box" style="cursor: pointer; width: 16px; height: 16px;">
                            </td>
                            <td><?= htmlspecialchars((string)$user['id'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string)($user['first_name'] . ' ' . $user['last_name']), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string)$user['email'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <?php if (!empty($user['is_confirmed'])): ?>
                                    <span class="status-badge status-confirmed"><?= __('confirmed') ?></span>
                                <?php else: ?>
                                    <span class="status-badge status-pending"><?= __('pending') ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($user['accepts_marketing'])): ?>
                                    <span style="display: inline-block; background: #e8f8f5; color: #27ae60; padding: 3px 8px; border-radius: 12px; font-size: 0.85em; font-weight: bold;">
                                        ✓ <?= __('opted_in') ?>
                                    </span>
                                <?php else: ?>
                                    <span style="display: inline-block; background: #fbeee6; color: #e67e22; padding: 3px 8px; border-radius: 12px; font-size: 0.85em; font-weight: bold;">
                                        ✕ <?= __('opted_out') ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td><?= !empty($user['created_at']) ? date('Y/m/d', strtotime((string)$user['created_at'])) : '-' ?></td>
                            <td class="action-links">
                                <a href="user_details.php?id=<?= (int)$user['id'] ?>" style="color: #3498db;"><?= __('details') ?></a> | 
                                <a href="edit_user.php?id=<?= (int)$user['id'] ?>" style="color: #f39c12;"><?= __('edit') ?></a> | 
                                <a href="promotions.php?user_ids[]=<?= (int)$user['id'] ?>" style="color: #9b59b6;" title="Send promotional email">📧</a> |
                                <form method="POST" action="delete_user.php" style="display:inline;" onsubmit="return confirm('<?= htmlspecialchars((string)__('confirm_delete_user'), ENT_QUOTES, 'UTF-8') ?>');">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)getCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="id" value="<?= (int)$user['id'] ?>">
                                    <button type="submit" style="background:none;border:none;color:#e74c3c;cursor:pointer;font-weight:bold;padding:0;font-size:inherit;font-family:inherit;"><?= __('delete_user') ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </form>

            <script>
            document.addEventListener('DOMContentLoaded', function() {
                const selectAll = document.getElementById('selectAllCheckbox');
                const checkboxes = document.querySelectorAll('.user-select-box');
                const actionBtn = document.getElementById('emailSelectedBtn');
                const counter = document.getElementById('selectedCounter');

                function updateSelectionUI() {
                    let checkedCount = 0;
                    checkboxes.forEach(cb => {
                        if (cb.checked) checkedCount++;
                    });

                    if (checkedCount > 0) {
                        actionBtn.style.display = 'inline-block';
                        actionBtn.textContent = '📧 ' + <?= json_encode(__('send_to_selected_btn')) ?> + ' (' + checkedCount + ')';
                        counter.textContent = '(' + checkedCount + ' ' + <?= json_encode(__('recipients_selected')) ?>.replace('%d', checkedCount) + ')';
                    } else {
                        actionBtn.style.display = 'none';
                        counter.textContent = '(0 ' + <?= json_encode(__('recipients_selected')) ?>.replace('%d', 0) + ')';
                    }

                    selectAll.checked = (checkedCount === checkboxes.length && checkboxes.length > 0);
                    selectAll.indeterminate = (checkedCount > 0 && checkedCount < checkboxes.length);
                }

                if (selectAll) {
                    selectAll.addEventListener('change', function() {
                        checkboxes.forEach(cb => cb.checked = selectAll.checked);
                        updateSelectionUI();
                    });
                }

                checkboxes.forEach(cb => {
                    cb.addEventListener('change', updateSelectionUI);
                });

                updateSelectionUI();
            });
            </script>
            <?php else: ?>
                <p><?= __('no_users_found') ?></p>
            <?php endif; ?>
        </div>
<?php
require_once __DIR__ . '/includes/footer.php';