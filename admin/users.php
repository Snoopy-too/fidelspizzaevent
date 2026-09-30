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
                    <button type="button" class="btn btn-add add-user-btn" onclick="openAddUserModal()">
                        <?= __('add_user_button') ?>
                    </button>
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
                                <?php
                                $userPayload = [
                                    'id'                => (int)$user['id'],
                                    'first_name'        => (string)$user['first_name'],
                                    'last_name'         => (string)$user['last_name'],
                                    'email'             => (string)$user['email'],
                                    'phone'             => (string)($user['phone'] ?? ''),
                                    'is_confirmed'      => (int)$user['is_confirmed'] === 1,
                                    'accepts_marketing' => (int)($user['accepts_marketing'] ?? 1) === 1,
                                ];
                                $userJsonAttr = htmlspecialchars(json_encode($userPayload, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
                                $userFullNameAttr = htmlspecialchars((string)($user['first_name'] . ' ' . $user['last_name']), ENT_QUOTES, 'UTF-8');
                                $userEmailAttr = htmlspecialchars((string)$user['email'], ENT_QUOTES, 'UTF-8');
                                ?>
                                <button type="button" class="btn-action-link" onclick="openUserDetailsModal(<?= (int)$user['id'] ?>)" style="background:none;border:none;color:#3498db;cursor:pointer;font-weight:bold;padding:0;font-size:inherit;font-family:inherit;"><?= __('details') ?></button> | 
                                <button type="button" class="btn-action-link" onclick="openEditUserModal(<?= $userJsonAttr ?>)" style="background:none;border:none;color:#f39c12;cursor:pointer;font-weight:bold;padding:0;font-size:inherit;font-family:inherit;"><?= __('edit') ?></button> | 
                                <button type="button" class="btn-action-link" onclick="openPasswordUserModal(<?= $userJsonAttr ?>)" style="background:none;border:none;color:#16a085;cursor:pointer;font-weight:bold;padding:0;font-size:inherit;font-family:inherit;"><?= htmlspecialchars((string)__('change_password_action'), ENT_QUOTES, 'UTF-8') ?></button> | 
                                <a href="promotions.php?user_ids[]=<?= (int)$user['id'] ?>" style="color: #9b59b6; text-decoration:none;" title="Send promotional email">📧</a> |
                                <button type="button" class="btn-action-link" onclick="openDeleteUserModal(<?= (int)$user['id'] ?>, '<?= $userFullNameAttr ?>', '<?= $userEmailAttr ?>')" style="background:none;border:none;color:#e74c3c;cursor:pointer;font-weight:bold;padding:0;font-size:inherit;font-family:inherit;"><?= __('delete_user') ?></button>
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

    <!-- ==========================================================================
         USER DETAILS MODAL
         ========================================================================== -->
    <div class="modal-backdrop" id="userDetailsModal">
        <div class="modal-dialog modal-dialog-lg">
            <div class="modal-header">
                <h3>👤 <span id="detailModalTitle"><?= __('user_details_title') ?></span></h3>
                <button type="button" class="modal-close" onclick="closeModal('userDetailsModal')">&times;</button>
            </div>
            <div class="modal-body">
                <div id="detailLoading" class="modal-loading-spinner">
                    <div class="spinner-icon"></div>
                    <span>Loading user details...</span>
                </div>

                <div id="detailContent" style="display: none; flex-direction: column; gap: 20px;">
                    <!-- User Profile Grid -->
                    <div class="modal-form-grid">
                        <div class="modal-detail-item">
                            <span class="modal-detail-label"><?= __('full_name') ?></span>
                            <span class="modal-detail-value" id="detailFullName">-</span>
                        </div>
                        <div class="modal-detail-item">
                            <span class="modal-detail-label"><?= __('email_address') ?></span>
                            <span class="modal-detail-value" id="detailEmail">-</span>
                        </div>
                        <div class="modal-detail-item">
                            <span class="modal-detail-label"><?= __('phone') ?></span>
                            <span class="modal-detail-value" id="detailPhone">-</span>
                        </div>
                        <div class="modal-detail-item">
                            <span class="modal-detail-label"><?= __('registration_date') ?></span>
                            <span class="modal-detail-value" id="detailCreatedDate">-</span>
                        </div>
                        <div class="modal-detail-item">
                            <span class="modal-detail-label"><?= __('account_status') ?></span>
                            <div id="detailStatusContainer" style="margin-top: 4px;">-</div>
                        </div>
                        <div class="modal-detail-item">
                            <span class="modal-detail-label"><?= __('marketing_consent') ?></span>
                            <div id="detailMarketingContainer" style="margin-top: 4px;">-</div>
                        </div>
                    </div>

                    <!-- Order History Section -->
                    <div>
                        <h4 style="color: #2c3e50; font-size: 1.1rem; margin-bottom: 12px; border-bottom: 2px solid #3498db; padding-bottom: 6px;">
                            📋 <?= __('order_history_title') ?>
                        </h4>
                        <div style="overflow-x: auto;">
                            <table class="table" id="detailOrdersTable" style="margin-top: 0; display: none;">
                                <thead>
                                    <tr>
                                        <th><?= __('order_number') ?></th>
                                        <th><?= __('amount') ?></th>
                                        <th><?= __('status') ?></th>
                                        <th><?= __('date') ?></th>
                                        <th><?= __('action') ?></th>
                                    </tr>
                                </thead>
                                <tbody id="detailOrdersTbody"></tbody>
                            </table>
                            <p id="detailNoOrdersMsg" style="color: #7f8c8d; font-style: italic; margin: 10px 0; display: none;">
                                <?= __('no_orders_for_user') ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="modal-btn modal-btn-primary" id="detailEditBtn" onclick="transitionToEditFromDetails()">
                    ✏️ <?= __('edit') ?>
                </button>
                <button type="button" class="modal-btn modal-btn-success" id="detailPasswordBtn" onclick="transitionToPasswordFromDetails()">
                    <?= htmlspecialchars((string)__('change_password_action'), ENT_QUOTES, 'UTF-8') ?>
                </button>
                <button type="button" class="modal-btn modal-btn-danger" id="detailDeleteBtn" onclick="transitionToDeleteFromDetails()">
                    🗑️ <?= __('delete_user') ?>
                </button>
                <button type="button" class="modal-btn modal-btn-cancel" onclick="closeModal('userDetailsModal')">
                    <?= __('close') ?>
                </button>
            </div>
        </div>
    </div>

    <!-- ==========================================================================
         CHANGE CUSTOMER PASSWORD MODAL
         ========================================================================== -->
    <div class="modal-backdrop" id="userPasswordModal">
        <div class="modal-dialog modal-dialog-sm">
            <form method="POST" action="edit_user.php" id="changePasswordForm" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)getCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="return_to" value="users.php">
                <input type="hidden" name="admin_change_password" value="1">
                <input type="hidden" name="id" id="pwdUserId" value="">

                <div class="modal-header">
                    <h3>🔑 <?= htmlspecialchars((string)__('change_customer_password_title'), ENT_QUOTES, 'UTF-8') ?></h3>
                    <button type="button" class="modal-close" onclick="closeModal('userPasswordModal')">&times;</button>
                </div>
                <div class="modal-body">
                    <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 12px 14px; color: #166534;">
                        <div style="font-weight: bold; font-size: 1rem;">👤 <span id="pwdUserName">-</span></div>
                        <div style="font-size: 0.88rem; color: #15803d; margin-top: 2px;" id="pwdUserEmail">-</div>
                    </div>

                    <p style="font-size: 0.9rem; color: #475569; line-height: 1.5; margin: 0;">
                        <?= htmlspecialchars((string)__('change_customer_password_desc'), ENT_QUOTES, 'UTF-8') ?>
                    </p>

                    <div class="form-group">
                        <label for="pwdNewPassword"><?= htmlspecialchars((string)__('new_password_label'), ENT_QUOTES, 'UTF-8') ?> *</label>
                        <div style="display: flex; gap: 8px; align-items: center;">
                            <input type="password"
                                   id="pwdNewPassword"
                                   name="new_password"
                                   minlength="6"
                                   autocomplete="new-password"
                                   placeholder="<?= htmlspecialchars((string)__('password_min_length_hint'), ENT_QUOTES, 'UTF-8') ?>"
                                   required>
                            <button type="button"
                                    id="pwdToggleBtn"
                                    class="modal-btn modal-btn-cancel"
                                    style="padding: 8px 12px; font-size: 0.85em; white-space: nowrap;"
                                    onclick="toggleModalPasswordVisibility('pwdNewPassword', this, 'pwdConfirmPassword')">
                                <?= htmlspecialchars((string)__('show_password'), ENT_QUOTES, 'UTF-8') ?>
                            </button>
                            <button type="button"
                                    class="modal-btn modal-btn-cancel"
                                    style="padding: 8px 12px; font-size: 0.85em; white-space: nowrap;"
                                    onclick="generateModalCustomerPassword()">
                                <?= htmlspecialchars((string)__('generate_password_btn'), ENT_QUOTES, 'UTF-8') ?>
                            </button>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="pwdConfirmPassword"><?= htmlspecialchars((string)__('confirm_new_password_label'), ENT_QUOTES, 'UTF-8') ?> *</label>
                        <input type="password"
                               id="pwdConfirmPassword"
                               name="confirm_password"
                               minlength="6"
                               autocomplete="new-password"
                               placeholder="<?= htmlspecialchars((string)__('password_min_length_hint'), ENT_QUOTES, 'UTF-8') ?>"
                               required>
                    </div>

                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px 14px;">
                        <label style="margin: 0; font-weight: 500; display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 0.9rem;">
                            <input type="checkbox" id="pwdConfirmEmail" name="confirm_email" value="1" checked style="width: 17px; height: 17px; cursor: pointer;">
                            <?= htmlspecialchars((string)__('also_confirm_email_label'), ENT_QUOTES, 'UTF-8') ?>
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="modal-btn modal-btn-cancel" onclick="closeModal('userPasswordModal')"><?= __('cancel') ?></button>
                    <button type="submit" class="modal-btn modal-btn-primary"><?= htmlspecialchars((string)__('update_password_btn'), ENT_QUOTES, 'UTF-8') ?></button>
                </div>
            </form>
        </div>
    </div>

    <!-- ==========================================================================
         EDIT USER MODAL
         ========================================================================== -->
    <div class="modal-backdrop" id="userEditModal">
        <div class="modal-dialog">
            <form method="POST" action="edit_user.php" id="editUserForm">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)getCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="return_to" value="users.php">
                <input type="hidden" name="id" id="editUserId" value="">

                <div class="modal-header">
                    <h3>✏️ <?= __('edit_user_title') ?></h3>
                    <button type="button" class="modal-close" onclick="closeModal('userEditModal')">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="modal-form-grid">
                        <div class="form-group">
                            <label for="editFirstName"><?= __('first_name') ?> *</label>
                            <input type="text" id="editFirstName" name="first_name" required>
                        </div>
                        <div class="form-group">
                            <label for="editLastName"><?= __('last_name') ?> *</label>
                            <input type="text" id="editLastName" name="last_name" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="editEmail"><?= __('email') ?> *</label>
                        <input type="email" id="editEmail" name="email" required>
                    </div>

                    <div class="form-group">
                        <label for="editPhone"><?= __('phone') ?></label>
                        <input type="text" id="editPhone" name="phone">
                    </div>

                    <div class="form-group">
                        <label for="editNewPassword"><?= htmlspecialchars((string)__('new_password_optional'), ENT_QUOTES, 'UTF-8') ?></label>
                        <div style="display: flex; gap: 8px; align-items: center;">
                            <input type="password"
                                   id="editNewPassword"
                                   name="new_password"
                                   minlength="6"
                                   autocomplete="new-password"
                                   placeholder="<?= htmlspecialchars((string)__('leave_blank_keep_password'), ENT_QUOTES, 'UTF-8') ?>">
                            <button type="button"
                                    class="modal-btn modal-btn-cancel"
                                    style="padding: 8px 12px; font-size: 0.85em; white-space: nowrap;"
                                    onclick="toggleModalPasswordVisibility('editNewPassword', this)">
                                <?= htmlspecialchars((string)__('show_password'), ENT_QUOTES, 'UTF-8') ?>
                            </button>
                        </div>
                        <small style="color: #64748b; display: block; margin-top: 4px;"><?= htmlspecialchars((string)__('leave_blank_keep_password'), ENT_QUOTES, 'UTF-8') ?></small>
                    </div>

                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 16px; display: flex; flex-direction: column; gap: 10px; margin-top: 5px;">
                        <label style="margin: 0; font-weight: 500; display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" id="editIsConfirmed" name="is_confirmed" style="width: 18px; height: 18px; cursor: pointer;">
                            <?= __('email_confirmed') ?>
                        </label>
                        <label style="margin: 0; font-weight: 500; display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" id="editAcceptsMarketing" name="accepts_marketing" style="width: 18px; height: 18px; cursor: pointer;">
                            <?= __('opted_in') ?> (<?= __('marketing_consent') ?>)
                        </label>
                    </div>
                </div>
                <div class="modal-footer" style="flex-wrap: wrap; gap: 8px;">
                    <button type="submit" name="send_reset_email" value="1" class="modal-btn modal-btn-cancel" style="margin-right: auto;" formnovalidate>
                        <?= htmlspecialchars((string)__('send_password_reset_email_btn'), ENT_QUOTES, 'UTF-8') ?>
                    </button>
                    <button type="button" class="modal-btn modal-btn-cancel" onclick="closeModal('userEditModal')"><?= __('cancel') ?></button>
                    <button type="submit" class="modal-btn modal-btn-primary">💾 <?= __('save_user') ?></button>
                </div>
            </form>
        </div>
    </div>

    <!-- ==========================================================================
         DELETE USER CONFIRMATION MODAL
         ========================================================================== -->
    <div class="modal-backdrop" id="userDeleteModal">
        <div class="modal-dialog modal-dialog-sm">
            <form method="POST" action="delete_user.php" id="deleteUserForm">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)getCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="id" id="deleteUserId" value="">

                <div class="modal-header" style="border-bottom-color: #fca5a5; background: #fff5f5;">
                    <h3 style="color: #b91c1c;">⚠️ <?= __('confirm_delete_user') ?></h3>
                    <button type="button" class="modal-close" onclick="closeModal('userDeleteModal')">&times;</button>
                </div>
                <div class="modal-body">
                    <div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 14px 16px; color: #991b1b; display: flex; flex-direction: column; gap: 8px;">
                        <p style="font-size: 1rem; margin: 0;">
                            <?= __('confirm_delete_user') ?>
                        </p>
                        <p style="margin: 0; font-weight: bold; font-size: 1.05rem; color: #7f1d1d;">
                            👤 <span id="deleteUserName"></span>
                        </p>
                        <p style="margin: 0; font-size: 0.9em; color: #b91c1c;" id="deleteUserEmail"></p>
                    </div>
                    <p style="color: #64748b; font-size: 0.88rem; margin: 0;">
                        ⚠️ This action will permanently remove the user and cannot be undone.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="modal-btn modal-btn-cancel" onclick="closeModal('userDeleteModal')"><?= __('cancel') ?></button>
                    <button type="submit" class="modal-btn modal-btn-danger">🗑️ <?= __('delete_user') ?></button>
                </div>
            </form>
        </div>
    </div>

    <!-- ==========================================================================
         ADD NEW USER MODAL
         ========================================================================== -->
    <div class="modal-backdrop" id="userAddModal">
        <div class="modal-dialog">
            <form method="POST" action="add_user.php" id="addUserForm">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)getCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="return_to" value="users.php">

                <div class="modal-header">
                    <h3>➕ <?= __('add_user_title') ?></h3>
                    <button type="button" class="modal-close" onclick="closeModal('userAddModal')">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="modal-form-grid">
                        <div class="form-group">
                            <label for="addFirstName"><?= __('first_name') ?> *</label>
                            <input type="text" id="addFirstName" name="first_name" required>
                        </div>
                        <div class="form-group">
                            <label for="addLastName"><?= __('last_name') ?> *</label>
                            <input type="text" id="addLastName" name="last_name" required>
                        </div>
                    </div>

                    <div class="modal-form-grid">
                        <div class="form-group">
                            <label for="addEmail"><?= __('email') ?> *</label>
                            <input type="email" id="addEmail" name="email" required>
                        </div>
                        <div class="form-group">
                            <label for="addPhone"><?= __('phone') ?></label>
                            <input type="text" id="addPhone" name="phone">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="addPassword"><?= __('password') ?> *</label>
                        <input type="password" id="addPassword" name="password" minlength="6" required>
                    </div>

                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 16px; display: flex; flex-direction: column; gap: 10px; margin-top: 5px;">
                        <label style="margin: 0; font-weight: 500; display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" id="addIsConfirmed" name="is_confirmed" checked style="width: 18px; height: 18px; cursor: pointer;">
                            <?= __('email_confirmed') ?>
                        </label>
                        <label style="margin: 0; font-weight: 500; display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" id="addAcceptsMarketing" name="accepts_marketing" checked style="width: 18px; height: 18px; cursor: pointer;">
                            <?= __('opted_in') ?> (<?= __('marketing_consent') ?>)
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="modal-btn modal-btn-cancel" onclick="closeModal('userAddModal')"><?= __('cancel') ?></button>
                    <button type="submit" class="modal-btn modal-btn-success">➕ <?= __('save_user') ?></button>
                </div>
            </form>
        </div>
    </div>

    <!-- ==========================================================================
         MODAL CONTROLLERS SCRIPT
         ========================================================================== -->
    <script>
    let activeUserDetails = null;

    function openModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('is-open');
        }
    }

    function closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('is-open');
        }
    }

    // Close on Escape or click outside
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-backdrop.is-open').forEach(m => m.classList.remove('is-open'));
        }
    });

    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.modal-backdrop').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    modal.classList.remove('is-open');
                }
            });
        });
    });

    // Open User Details Modal
    function openUserDetailsModal(userId) {
        openModal('userDetailsModal');
        const loading = document.getElementById('detailLoading');
        const content = document.getElementById('detailContent');
        loading.style.display = 'flex';
        content.style.display = 'none';

        fetch('ajax_user_details.php?id=' + encodeURIComponent(userId))
            .then(res => {
                if (!res.ok) throw new Error('Network response failed (' + res.status + ')');
                return res.json();
            })
            .then(data => {
                if (!data.success || !data.user) {
                    throw new Error(data.message || 'Failed to load user details.');
                }
                activeUserDetails = data.user;

                document.getElementById('detailModalTitle').textContent = data.user.full_name;
                document.getElementById('detailFullName').textContent = data.user.full_name;
                document.getElementById('detailEmail').textContent = data.user.email;
                document.getElementById('detailPhone').textContent = data.user.phone || '-';
                document.getElementById('detailCreatedDate').textContent = data.user.created_at;

                // Status Badge
                const statusContainer = document.getElementById('detailStatusContainer');
                if (data.user.is_confirmed) {
                    statusContainer.innerHTML = '<span class="status-badge status-confirmed"><?= htmlspecialchars(__('confirmed')) ?></span>';
                } else {
                    statusContainer.innerHTML = '<span class="status-badge status-pending"><?= htmlspecialchars(__('pending')) ?></span>';
                }

                // Marketing Badge
                const marketingContainer = document.getElementById('detailMarketingContainer');
                if (data.user.accepts_marketing) {
                    marketingContainer.innerHTML = '<span style="display: inline-block; background: #e8f8f5; color: #27ae60; padding: 3px 8px; border-radius: 12px; font-size: 0.85em; font-weight: bold;">✓ <?= htmlspecialchars(__('opted_in')) ?></span>';
                } else {
                    marketingContainer.innerHTML = '<span style="display: inline-block; background: #fbeee6; color: #e67e22; padding: 3px 8px; border-radius: 12px; font-size: 0.85em; font-weight: bold;">✕ <?= htmlspecialchars(__('opted_out')) ?></span>';
                }

                // Orders Table
                const ordersTable = document.getElementById('detailOrdersTable');
                const ordersTbody = document.getElementById('detailOrdersTbody');
                const noOrdersMsg = document.getElementById('detailNoOrdersMsg');
                ordersTbody.innerHTML = '';

                if (data.orders && data.orders.length > 0) {
                    data.orders.forEach(order => {
                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                            <td><strong>${escapeHtml(order.order_number)}</strong></td>
                            <td>${escapeHtml(order.total_formatted)}</td>
                            <td><span class="status-badge status-${escapeHtml(order.status)}">${escapeHtml(order.status_label)}</span></td>
                            <td>${escapeHtml(order.created_at)}</td>
                            <td>
                                <a href="order_details.php?id=${encodeURIComponent(order.id)}" style="color: #3498db; font-weight: bold; text-decoration: none;">
                                    <?= htmlspecialchars(__('view_details')) ?>
                                </a>
                            </td>
                        `;
                        ordersTbody.appendChild(tr);
                    });
                    ordersTable.style.display = 'table';
                    noOrdersMsg.style.display = 'none';
                } else {
                    ordersTable.style.display = 'none';
                    noOrdersMsg.style.display = 'block';
                }

                loading.style.display = 'none';
                content.style.display = 'flex';
            })
            .catch(err => {
                loading.innerHTML = '<p style="color: #e74c3c; font-weight: bold;">❌ ' + escapeHtml(err.message) + '</p>';
            });
    }

    // Open Edit User Modal
    function openEditUserModal(user) {
        if (!user) return;
        document.getElementById('editUserId').value = user.id || '';
        document.getElementById('editFirstName').value = user.first_name || '';
        document.getElementById('editLastName').value = user.last_name || '';
        document.getElementById('editEmail').value = user.email || '';
        document.getElementById('editPhone').value = user.phone || '';
        const pwdField = document.getElementById('editNewPassword');
        if (pwdField) {
            pwdField.value = '';
            pwdField.type = 'password';
        }
        document.getElementById('editIsConfirmed').checked = !!user.is_confirmed;
        document.getElementById('editAcceptsMarketing').checked = !!user.accepts_marketing;
        openModal('userEditModal');
    }

    function toggleModalPasswordVisibility(inputId, btn, secondInputId) {
        const input = document.getElementById(inputId);
        const second = secondInputId ? document.getElementById(secondInputId) : null;
        if (!input) return;
        if (input.type === 'password') {
            input.type = 'text';
            if (second) second.type = 'text';
            btn.textContent = <?= json_encode((string)__('hide_password')) ?>;
        } else {
            input.type = 'password';
            if (second) second.type = 'password';
            btn.textContent = <?= json_encode((string)__('show_password')) ?>;
        }
    }

    // Open Change Customer Password Modal
    function openPasswordUserModal(user) {
        if (!user) return;
        document.getElementById('pwdUserId').value = user.id || '';
        const fullName = user.full_name || ((user.first_name || '') + ' ' + (user.last_name || '')).trim();
        document.getElementById('pwdUserName').textContent = fullName || '-';
        document.getElementById('pwdUserEmail').textContent = user.email || '-';

        const pwdInput = document.getElementById('pwdNewPassword');
        const confirmInput = document.getElementById('pwdConfirmPassword');
        const toggleBtn = document.getElementById('pwdToggleBtn');
        if (pwdInput) {
            pwdInput.value = '';
            pwdInput.type = 'password';
        }
        if (confirmInput) {
            confirmInput.value = '';
            confirmInput.type = 'password';
            confirmInput.setCustomValidity('');
        }
        if (toggleBtn) {
            toggleBtn.textContent = <?= json_encode((string)__('show_password')) ?>;
        }
        document.getElementById('pwdConfirmEmail').checked = true;
        openModal('userPasswordModal');
    }

    function generateModalCustomerPassword() {
        const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
        let generated = '';
        const randomValues = new Uint32Array(10);
        window.crypto.getRandomValues(randomValues);
        for (let i = 0; i < 10; i++) {
            generated += chars[randomValues[i] % chars.length];
        }
        const pwdInput = document.getElementById('pwdNewPassword');
        const confirmInput = document.getElementById('pwdConfirmPassword');
        const toggleBtn = document.getElementById('pwdToggleBtn');
        if (pwdInput) {
            pwdInput.value = generated;
            pwdInput.type = 'text';
        }
        if (confirmInput) {
            confirmInput.value = generated;
            confirmInput.type = 'text';
            confirmInput.setCustomValidity('');
        }
        if (toggleBtn) {
            toggleBtn.textContent = <?= json_encode((string)__('hide_password')) ?>;
        }
    }

    // Transition from details to password modal
    function transitionToPasswordFromDetails() {
        if (activeUserDetails) {
            closeModal('userDetailsModal');
            openPasswordUserModal(activeUserDetails);
        }
    }

    // Transition from details to edit
    function transitionToEditFromDetails() {
        if (activeUserDetails) {
            closeModal('userDetailsModal');
            openEditUserModal(activeUserDetails);
        }
    }

    // Open Delete User Modal
    function openDeleteUserModal(id, name, email) {
        document.getElementById('deleteUserId').value = id;
        document.getElementById('deleteUserName').textContent = name;
        document.getElementById('deleteUserEmail').textContent = email ? '(' + email + ')' : '';
        openModal('userDeleteModal');
    }

    // Transition from details to delete
    function transitionToDeleteFromDetails() {
        if (activeUserDetails) {
            closeModal('userDetailsModal');
            openDeleteUserModal(activeUserDetails.id, activeUserDetails.full_name, activeUserDetails.email);
        }
    }

    // Open Add User Modal
    function openAddUserModal() {
        document.getElementById('addUserForm').reset();
        document.getElementById('addIsConfirmed').checked = true;
        document.getElementById('addAcceptsMarketing').checked = true;
        openModal('userAddModal');
    }

    function escapeHtml(text) {
        if (text === null || text === undefined) return '';
        const div = document.createElement('div');
        div.textContent = String(text);
        return div.innerHTML;
    }
    </script>
<?php
require_once __DIR__ . '/includes/footer.php';