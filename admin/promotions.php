<?php
declare(strict_types=1);

require_once '../config.php';
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../src/bootstrap.php';
requireAdmin();

$container = getServiceContainer();
$userRepo = $container->getUserRepository();
$campaignRepo = $container->getCampaignRepository();

// Fetch all registered users for selector
$allUsers = $userRepo->findAllUsersForSelection();

// Check pre-selected user IDs from GET parameters (e.g. from users.php)
$preselectedUserIds = [];
if (!empty($_GET['user_ids']) && is_array($_GET['user_ids'])) {
    foreach ($_GET['user_ids'] as $uid) {
        $id = (int)$uid;
        if ($id > 0) {
            $preselectedUserIds[] = $id;
        }
    }
}

// Fetch campaigns for history
$campaigns = $campaignRepo->findAllCampaigns(50, 0);

$page_title = __('promotions_title');
require_once __DIR__ . '/includes/header.php';
?>
<style>
.tab-container {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
    border-bottom: 2px solid #e0e0e0;
    padding-bottom: 0;
}
.tab-btn {
    background: none;
    border: none;
    padding: 12px 20px;
    font-size: 16px;
    font-weight: 600;
    color: #666;
    cursor: pointer;
    border-bottom: 3px solid transparent;
    transition: all 0.2s;
}
.tab-btn.active {
    color: #d32f2f;
    border-bottom-color: #d32f2f;
}
.tab-content {
    display: none;
}
.tab-content.active {
    display: block;
}
.form-card {
    background: #ffffff;
    border-radius: 8px;
    padding: 25px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    margin-bottom: 25px;
}
.tag-badge {
    display: inline-block;
    background: #eef2f7;
    color: #2c3e50;
    border: 1px solid #d5dbdb;
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 13px;
    font-family: monospace;
    cursor: pointer;
    margin: 3px;
    transition: background-color 0.2s;
}
.tag-badge:hover {
    background: #dbe4ee;
    border-color: #3498db;
    color: #2980b9;
}
.recipient-picker-box {
    max-height: 250px;
    overflow-y: auto;
    border: 1px solid #ced4da;
    border-radius: 6px;
    padding: 10px;
    background: #fafafa;
}
.recipient-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 8px 10px;
    border-bottom: 1px solid #ececec;
}
.recipient-item:last-child {
    border-bottom: none;
}
.recipient-item:hover {
    background: #f1f5f9;
}
.progress-box {
    display: none;
    background: #ffffff;
    border: 2px solid #3498db;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 25px;
}
.progress-bar-container {
    height: 22px;
    background-color: #e9ecef;
    border-radius: 11px;
    overflow: hidden;
    margin: 15px 0;
}
.progress-bar-fill {
    height: 100%;
    width: 0%;
    background: linear-gradient(90deg, #3498db, #2ecc71);
    transition: width 0.3s ease;
}

/* App Modern Modals */
.modal-overlay {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(15, 23, 42, 0.65);
    backdrop-filter: blur(4px);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10000;
    padding: 20px;
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.22s ease, visibility 0.22s ease;
}
.modal-overlay.is-open {
    opacity: 1;
    visibility: visible;
}
.modal-content {
    background: #ffffff;
    border-radius: 12px;
    width: 100%;
    max-width: 600px;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
    box-shadow: 0 20px 45px rgba(0, 0, 0, 0.25);
    transform: scale(0.95);
    transition: transform 0.22s cubic-bezier(0.16, 1, 0.3, 1);
    overflow: hidden;
}
.modal-overlay.is-open .modal-content {
    transform: scale(1);
}
.modal-header {
    padding: 16px 22px;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.modal-header h3 {
    margin: 0;
    font-size: 1.15rem;
    font-weight: 700;
    color: #1e293b;
    display: flex;
    align-items: center;
    gap: 8px;
}
.modal-close {
    background: none;
    border: none;
    font-size: 1.6rem;
    line-height: 1;
    color: #64748b;
    cursor: pointer;
    padding: 2px 8px;
    border-radius: 6px;
    transition: all 0.2s;
}
.modal-close:hover {
    color: #0f172a;
    background: #e2e8f0;
}
.modal-body {
    padding: 22px 24px;
    overflow-y: auto;
    font-size: 14.5px;
    color: #334155;
    line-height: 1.5;
}
.modal-footer {
    padding: 16px 22px;
    background: #f8fafc;
    border-top: 1px solid #e2e8f0;
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 12px;
}
.modal-btn {
    padding: 9px 18px;
    font-size: 14px;
    font-weight: 600;
    border-radius: 6px;
    cursor: pointer;
    border: none;
    transition: background-color 0.2s, opacity 0.2s;
}
.modal-btn-cancel {
    background: #e2e8f0;
    color: #475569;
}
.modal-btn-cancel:hover {
    background: #cbd5e1;
    color: #1e293b;
}
.modal-btn-primary {
    background: #d32f2f;
    color: #ffffff;
}
.modal-btn-primary:hover {
    background: #b71c1c;
}
.modal-btn-dark {
    background: #2c3e50;
    color: #ffffff;
}
.modal-btn-dark:hover {
    background: #1a252f;
}
</style>

<div class="section">
    <div class="tab-container">
        <button type="button" class="tab-btn active" onclick="switchTab('compose')">
            ✉️ <?= __('compose_promotion') ?>
        </button>
        <button type="button" class="tab-btn" onclick="switchTab('history')">
            📜 <?= __('campaign_history') ?> (<?= count($campaigns) ?>)
        </button>
    </div>

    <!-- TAB 1: COMPOSE -->
    <div id="tab-compose" class="tab-content active">
        <!-- Live Progress Card -->
        <div id="progressCard" class="progress-box">
            <h3 id="progressTitle" style="color: #2980b9; margin-bottom: 5px;">🚀 <?= __('sending_progress') ?></h3>
            <div class="progress-bar-container">
                <div id="progressBarFill" class="progress-bar-fill"></div>
            </div>
            <div style="display: flex; justify-content: space-between; font-weight: bold; font-size: 14px;">
                <span id="progressStats">0 / 0</span>
                <span id="progressPercent">0%</span>
            </div>
            <div id="progressErrors" style="margin-top: 12px; color: #c0392b; font-size: 13px; max-height: 100px; overflow-y: auto; display: none;"></div>
            <div id="progressSuccessMsg" style="margin-top: 15px; display: none;">
                <div style="background: #d4edda; color: #155724; padding: 12px 16px; border-radius: 6px; font-weight: bold;">
                    ✓ <?= __('campaign_completed') ?>
                </div>
                <button type="button" class="btn" style="margin-top: 10px; background: #27ae60; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer;" onclick="switchTab('history'); location.reload();">
                    <?= __('campaign_history') ?> →
                </button>
            </div>
        </div>

        <form id="composeForm" onsubmit="return false;">
            <input type="hidden" id="csrfToken" value="<?= htmlspecialchars((string)getCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">

            <!-- Recipient Selector Card -->
            <div class="form-card">
                <h3 style="margin-bottom: 12px; color: #2c3e50; display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                    <span>👥 <?= __('recipient_selection') ?></span>
                    <span id="recipientCountBadge" style="font-size: 13px; font-weight: 600; background: #e2e8f0; color: #475569; padding: 3px 12px; border-radius: 12px;">
                        <?= empty($preselectedUserIds) ? __('target_all_eligible') : sprintf(__('recipients_selected'), count($preselectedUserIds)) ?>
                    </span>
                </h3>
                
                <div style="margin-bottom: 15px; display: flex; gap: 20px; align-items: center; flex-wrap: wrap;">
                    <label style="font-weight: 600; cursor: pointer;">
                        <input type="radio" name="recipient_mode" value="all" <?= empty($preselectedUserIds) ? 'checked' : '' ?> onchange="toggleRecipientPicker(false)">
                        <?= __('target_all_eligible') ?>
                    </label>
                    <label style="font-weight: 600; cursor: pointer;">
                        <input type="radio" name="recipient_mode" value="selected" <?= !empty($preselectedUserIds) ? 'checked' : '' ?> onchange="toggleRecipientPicker(true)">
                        <?= __('target_selected_only') ?>
                    </label>
                </div>

                <div id="recipientPickerArea" style="<?= empty($preselectedUserIds) ? 'display: none;' : '' ?>">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; gap: 10px;">
                        <input type="text" id="userSearchInput" placeholder="Search by name or email..." style="padding: 6px 12px; border: 1px solid #ced4da; border-radius: 4px; width: 250px;" oninput="filterUsersList()">
                        <div>
                            <button type="button" class="btn" style="padding: 4px 10px; font-size: 12px; background: #95a5a6; color: white; border: none; border-radius: 4px; cursor: pointer;" onclick="toggleAllRecipients(true)">
                                <?= __('select_all_recipients') ?>
                            </button>
                            <button type="button" class="btn" style="padding: 4px 10px; font-size: 12px; background: #e0e0e0; color: #333; border: none; border-radius: 4px; cursor: pointer; margin-left: 5px;" onclick="toggleAllRecipients(false)">
                                Clear
                            </button>
                        </div>
                    </div>

                    <div class="recipient-picker-box" id="usersListContainer">
                        <?php foreach ($allUsers as $user): ?>
                            <?php 
                                $isPrechecked = in_array((int)$user['id'], $preselectedUserIds, true);
                                $isEligible = !empty($user['is_confirmed']) && !empty($user['accepts_marketing']);
                            ?>
                            <div class="recipient-item user-picker-row" data-search="<?= htmlspecialchars(strtolower($user['first_name'] . ' ' . $user['last_name'] . ' ' . $user['email']), ENT_QUOTES, 'UTF-8') ?>">
                                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; flex-grow: 1;">
                                    <input type="checkbox" name="selected_users[]" value="<?= (int)$user['id'] ?>" class="user-check-item" <?= $isPrechecked ? 'checked' : '' ?>>
                                    <span>
                                        <strong><?= htmlspecialchars((string)($user['first_name'] . ' ' . $user['last_name']), ENT_QUOTES, 'UTF-8') ?></strong>
                                        <span style="color: #666; font-size: 13px;">(<?= htmlspecialchars((string)$user['email'], ENT_QUOTES, 'UTF-8') ?>)</span>
                                    </span>
                                </label>
                                <div>
                                    <?php if (!empty($user['accepts_marketing'])): ?>
                                        <span style="background: #e8f8f5; color: #27ae60; font-size: 11px; padding: 2px 6px; border-radius: 10px;">✓ <?= __('opted_in') ?></span>
                                    <?php else: ?>
                                        <span style="background: #fbeee6; color: #e67e22; font-size: 11px; padding: 2px 6px; border-radius: 10px;">✕ <?= __('opted_out') ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Composer Card -->
            <div class="form-card">
                <div style="margin-bottom: 20px;">
                    <label for="campaignSubject" style="display: block; font-weight: bold; margin-bottom: 6px;">
                        <?= __('subject_label') ?> <span style="color: #e74c3c;">*</span>
                    </label>
                    <input type="text" id="campaignSubject" class="form-control" style="width: 100%; padding: 10px 12px; font-size: 15px; border: 1px solid #ced4da; border-radius: 6px;" placeholder="e.g. Special Pizza Night Invitation! 🍕" required>
                </div>

                <div style="margin-bottom: 12px;">
                    <label style="display: block; font-weight: bold; margin-bottom: 6px;">
                        <?= __('available_placeholders') ?>:
                    </label>
                    <div style="display: flex; flex-wrap: wrap; gap: 5px;">
                        <span class="tag-badge" onclick="insertTag('{{first_name}}')">{{first_name}}</span>
                        <span class="tag-badge" onclick="insertTag('{{last_name}}')">{{last_name}}</span>
                        <span class="tag-badge" onclick="insertTag('{{full_name}}')">{{full_name}}</span>
                        <span class="tag-badge" onclick="insertTag('{{email}}')">{{email}}</span>
                        <span class="tag-badge" onclick="insertTag('{{event_date}}')">{{event_date}}</span>
                        <span class="tag-badge" onclick="insertTag('{{event_location}}')">{{event_location}}</span>
                        <span class="tag-badge" onclick="insertTag('{{site_title}}')">{{site_title}}</span>
                        <span class="tag-badge" onclick="insertTag('{{unsubscribe_link}}')">{{unsubscribe_link}}</span>
                    </div>
                    <small style="color: #666;">Click any tag above to insert it at your current cursor position.</small>
                </div>

                <div style="margin-bottom: 20px;">
                    <label for="campaignBody" style="display: block; font-weight: bold; margin-bottom: 6px;">
                        <?= __('body_label') ?> <span style="color: #e74c3c;">*</span>
                    </label>
                    <textarea id="campaignBody" rows="10" class="form-control" style="width: 100%; padding: 12px; font-size: 15px; line-height: 1.5; border: 1px solid #ced4da; border-radius: 6px; font-family: inherit;" placeholder="<?= htmlspecialchars((string)__('body_placeholder'), ENT_QUOTES, 'UTF-8') ?>" required></textarea>
                </div>

                <div style="display: flex; gap: 15px; align-items: center; justify-content: flex-end;">
                    <button type="button" class="btn" style="background-color: #34495e; color: white; padding: 10px 20px; border-radius: 6px; font-weight: bold; border: none; cursor: pointer;" onclick="openPreviewModal()">
                        👁️ <?= __('preview_email') ?>
                    </button>
                    <button type="button" id="startSendBtn" class="btn" style="background-color: #d32f2f; color: white; padding: 10px 24px; border-radius: 6px; font-weight: bold; border: none; cursor: pointer;" onclick="promptSendConfirmation()">
                        🚀 <?= __('send_promotions_btn') ?>
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- TAB 2: HISTORY -->
    <div id="tab-history" class="tab-content">
        <div class="form-card">
            <h3 style="margin-bottom: 15px; color: #2c3e50;">📜 <?= __('campaign_history') ?></h3>

            <?php if (!empty($campaigns)): ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th><?= __('campaign_subject') ?></th>
                        <th><?= __('campaign_author') ?></th>
                        <th><?= __('status') ?></th>
                        <th><?= __('campaign_stats') ?></th>
                        <th><?= __('campaign_date') ?></th>
                        <th><?= __('operations') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($campaigns as $camp): ?>
                    <tr>
                        <td>#<?= (int)$camp['id'] ?></td>
                        <td><strong><?= htmlspecialchars((string)$camp['subject'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                        <td><?= htmlspecialchars((string)($camp['admin_username'] ?? 'Admin'), ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <?php if ($camp['status'] === 'completed'): ?>
                                <span class="status-badge status-confirmed">Completed</span>
                            <?php elseif ($camp['status'] === 'sending'): ?>
                                <span class="status-badge status-pending">Sending</span>
                            <?php else: ?>
                                <span class="status-badge status-cancelled"><?= htmlspecialchars((string)$camp['status']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span style="color: #27ae60; font-weight: bold;"><?= (int)$camp['sent_count'] ?> sent</span> / 
                            <span style="color: <?= (int)$camp['failed_count'] > 0 ? '#e74c3c' : '#7f8c8d' ?>;"><?= (int)$camp['failed_count'] ?> failed</span> 
                            (<?= (int)$camp['total_recipients'] ?> total)
                        </td>
                        <td><?= date('Y/m/d H:i', strtotime((string)$camp['created_at'])) ?></td>
                        <td>
                            <button type="button" class="btn" style="background: #3498db; color: white; border: none; padding: 4px 10px; border-radius: 4px; font-size: 12px; cursor: pointer;" onclick="openRecipientsModal(<?= (int)$camp['id'] ?>, '<?= htmlspecialchars(addslashes((string)$camp['subject']), ENT_QUOTES, 'UTF-8') ?>')">
                                <?= __('view_delivery_log') ?>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
                <p><?= __('no_campaigns_found') ?></p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- 1. STYLED CONFIRMATION MODAL -->
<div id="confirmSendModal" class="modal-overlay">
    <div class="modal-content" style="max-width: 520px;">
        <div class="modal-header">
            <h3>🍕 <?= __('confirm_send_title') ?></h3>
            <button type="button" class="modal-close" onclick="closeModal('confirmSendModal')">&times;</button>
        </div>
        <div class="modal-body">
            <div style="display: flex; align-items: flex-start; gap: 14px; margin-bottom: 18px;">
                <div style="font-size: 34px; line-height: 1;">📬</div>
                <div>
                    <h4 style="font-size: 16px; color: #1e293b; margin-bottom: 4px;">
                        <?= __('confirm_send_title') ?>
                    </h4>
                    <p id="confirmRecipientSubtext" style="color: #64748b; font-size: 14px; margin: 0;">
                        -
                    </p>
                </div>
            </div>

            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 16px; margin-bottom: 16px;">
                <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; margin-bottom: 4px;">
                    <?= __('subject_label') ?>
                </div>
                <div id="confirmSubjectPreview" style="font-weight: 600; color: #0f172a; font-size: 14.5px; word-break: break-word;">
                    -
                </div>
            </div>

            <div style="background: #fffbeb; border: 1px solid #fef3c7; border-radius: 8px; padding: 12px 14px; display: flex; gap: 10px; align-items: center;">
                <span style="font-size: 18px;">ℹ️</span>
                <span style="font-size: 13px; color: #92400e;">
                    <?= __('confirm_send_notice') ?>
                </span>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="modal-btn modal-btn-cancel" onclick="closeModal('confirmSendModal')">
                <?= __('cancel') ?>
            </button>
            <button type="button" id="confirmSendExecuteBtn" class="modal-btn modal-btn-primary" onclick="executeCampaignSending()">
                <?= __('confirm_send_action') ?>
            </button>
        </div>
    </div>
</div>

<!-- 2. STYLED APP ALERT MODAL -->
<div id="appAlertModal" class="modal-overlay">
    <div class="modal-content" style="max-width: 440px;">
        <div class="modal-header">
            <h3 id="appAlertTitle">🔔 Notice</h3>
            <button type="button" class="modal-close" onclick="closeModal('appAlertModal')">&times;</button>
        </div>
        <div class="modal-body" style="display: flex; gap: 14px; align-items: flex-start;">
            <div id="appAlertIcon" style="font-size: 30px; line-height: 1;">⚠️</div>
            <p id="appAlertMessage" style="font-size: 14px; color: #334155; margin: 0; line-height: 1.5;"></p>
        </div>
        <div class="modal-footer">
            <button type="button" class="modal-btn modal-btn-dark" onclick="closeModal('appAlertModal')">
                <?= __('close') ?>
            </button>
        </div>
    </div>
</div>

<!-- 3. PREVIEW MODAL -->
<div id="previewModal" class="modal-overlay">
    <div class="modal-content" style="max-width: 750px;">
        <div class="modal-header">
            <h3>👁️ <?= __('preview_email') ?></h3>
            <button type="button" class="modal-close" onclick="closeModal('previewModal')">&times;</button>
        </div>
        <div class="modal-body">
            <div style="background: #f8f9fa; padding: 12px; border-radius: 6px; margin-bottom: 15px; font-size: 13px;">
                <p><strong>Sample Recipient:</strong> <span id="previewSampleUser">-</span></p>
                <p><strong>Subject:</strong> <span id="previewSubjectText" style="font-weight: bold; color: #2c3e50;">-</span></p>
            </div>
            <iframe id="previewIframe" style="width: 100%; height: 380px; border: 1px solid #ddd; border-radius: 6px; background: white;"></iframe>
        </div>
        <div class="modal-footer">
            <button type="button" class="modal-btn modal-btn-cancel" onclick="closeModal('previewModal')"><?= __('close') ?></button>
        </div>
    </div>
</div>

<!-- 4. RECIPIENTS LOG MODAL -->
<div id="recipientsModal" class="modal-overlay">
    <div class="modal-content" style="max-width: 750px;">
        <div class="modal-header">
            <h3>📋 <?= __('view_delivery_log') ?>: <span id="logCampaignTitle" style="font-weight: normal; font-size: 15px;"></span></h3>
            <button type="button" class="modal-close" onclick="closeModal('recipientsModal')">&times;</button>
        </div>
        <div class="modal-body">
            <div id="logLoading" style="text-align: center; padding: 20px; color: #666;">Loading recipient records...</div>
            <table id="logTable" class="table" style="display: none; width: 100%;">
                <thead>
                    <tr>
                        <th>Recipient</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th>Sent Time</th>
                    </tr>
                </thead>
                <tbody id="logTableBody"></tbody>
            </table>
        </div>
        <div class="modal-footer">
            <button type="button" class="modal-btn modal-btn-cancel" onclick="closeModal('recipientsModal')"><?= __('close') ?></button>
        </div>
    </div>
</div>

<script>
function switchTab(tab) {
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
    
    if (tab === 'compose') {
        document.querySelectorAll('.tab-btn')[0].classList.add('active');
        document.getElementById('tab-compose').classList.add('active');
    } else {
        document.querySelectorAll('.tab-btn')[1].classList.add('active');
        document.getElementById('tab-history').classList.add('active');
    }
}

function updateRecipientBadge() {
    const badge = document.getElementById('recipientCountBadge');
    if (!badge) return;
    const modeEl = document.querySelector('input[name="recipient_mode"]:checked');
    const mode = modeEl ? modeEl.value : 'all';

    if (mode === 'all') {
        badge.textContent = <?= json_encode(__('target_all_eligible')) ?>;
        badge.style.background = '#e8f8f5';
        badge.style.color = '#27ae60';
    } else {
        const checkedCount = document.querySelectorAll('.user-check-item:checked').length;
        badge.textContent = <?= json_encode(__('recipients_selected')) ?>.replace('%d', checkedCount);
        badge.style.background = '#e2e8f0';
        badge.style.color = '#475569';
    }
}

function toggleRecipientPicker(show) {
    const area = document.getElementById('recipientPickerArea');
    area.style.display = show ? 'block' : 'none';
    updateRecipientBadge();
}

function filterUsersList() {
    const term = document.getElementById('userSearchInput').value.toLowerCase();
    document.querySelectorAll('.user-picker-row').forEach(row => {
        const searchData = row.getAttribute('data-search') || '';
        row.style.display = searchData.includes(term) ? 'flex' : 'none';
    });
}

function toggleAllRecipients(check) {
    document.querySelectorAll('.user-check-item').forEach(cb => {
        if (cb.closest('.user-picker-row').style.display !== 'none') {
            cb.checked = check;
        }
    });
    updateRecipientBadge();
}

function insertTag(tag) {
    const textarea = document.getElementById('campaignBody');
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const text = textarea.value;
    textarea.value = text.substring(0, start) + tag + text.substring(end);
    textarea.focus();
    textarea.selectionStart = textarea.selectionEnd = start + tag.length;
}

// Modal opening/closing with animated classes
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
        document.querySelectorAll('.modal-overlay.is-open').forEach(m => m.classList.remove('is-open'));
    }
});

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.modal-overlay').forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                modal.classList.remove('is-open');
            }
        });
    });

    document.querySelectorAll('.user-check-item').forEach(cb => {
        cb.addEventListener('change', updateRecipientBadge);
    });

    updateRecipientBadge();
});

// App alert dialog replacement
function showAppAlert(message, title = 'Notice', icon = '⚠️') {
    document.getElementById('appAlertTitle').textContent = title;
    document.getElementById('appAlertMessage').textContent = message;
    document.getElementById('appAlertIcon').textContent = icon;
    openModal('appAlertModal');
}

function openPreviewModal() {
    const subject = document.getElementById('campaignSubject').value.trim();
    const bodyContent = document.getElementById('campaignBody').value.trim();
    const csrfToken = document.getElementById('csrfToken').value;

    if (!subject) {
        showAppAlert('Please enter a subject line first.', 'Subject Required', '✍️');
        document.getElementById('campaignSubject').focus();
        return;
    }
    if (!bodyContent) {
        showAppAlert('Please enter email body content first.', 'Message Required', '📝');
        document.getElementById('campaignBody').focus();
        return;
    }

    let sampleUserId = null;
    const checked = document.querySelector('.user-check-item:checked');
    if (checked) {
        sampleUserId = checked.value;
    }

    const formData = new FormData();
    formData.append('csrf_token', csrfToken);
    formData.append('subject', subject);
    formData.append('body_content', bodyContent);
    if (sampleUserId) formData.append('sample_user_id', sampleUserId);

    fetch('ajax_preview_promotion.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'success') {
            document.getElementById('previewSubjectText').textContent = data.data.subject;
            document.getElementById('previewSampleUser').textContent = data.data.sample_user;
            
            const iframe = document.getElementById('previewIframe');
            iframe.srcdoc = data.data.html;

            openModal('previewModal');
        } else {
            showAppAlert(data.message || 'Unknown error occurred while generating preview.', 'Preview Error', '❌');
        }
    })
    .catch(err => {
        showAppAlert('Communication error: ' + err.message, 'Network Error', '🌐');
    });
}

function openRecipientsModal(campaignId, title) {
    document.getElementById('logCampaignTitle').textContent = title;
    document.getElementById('logLoading').style.display = 'block';
    document.getElementById('logTable').style.display = 'none';
    openModal('recipientsModal');

    fetch('ajax_send_promotion.php?action=recipients&campaign_id=' + campaignId)
    .then(r => r.json())
    .then(res => {
        document.getElementById('logLoading').style.display = 'none';
        if (res.status === 'success') {
            const tbody = document.getElementById('logTableBody');
            tbody.innerHTML = '';
            res.data.forEach(r => {
                const tr = document.createElement('tr');
                let statusBadge = '<span class="status-badge status-confirmed">Sent</span>';
                if (r.status === 'failed') {
                    statusBadge = '<span class="status-badge status-cancelled">Failed</span><br><small style="color:#c0392b;">' + (r.error_message || '') + '</small>';
                } else if (r.status === 'opted_out') {
                    statusBadge = '<span style="background:#fbeee6;color:#e67e22;padding:2px 6px;border-radius:10px;font-size:11px;">Opted-Out</span>';
                } else if (r.status === 'pending') {
                    statusBadge = '<span class="status-badge status-pending">Pending</span>';
                }

                tr.innerHTML = `
                    <td>${r.first_name || ''} ${r.last_name || ''}</td>
                    <td>${r.email_sent_to}</td>
                    <td>${statusBadge}</td>
                    <td>${r.sent_at || '-'}</td>
                `;
                tbody.appendChild(tr);
            });
            document.getElementById('logTable').style.display = 'table';
        } else {
            showAppAlert(res.message || 'Could not load log.', 'Log Error', '❌');
        }
    })
    .catch(e => {
        document.getElementById('logLoading').textContent = 'Error loading logs: ' + e.message;
    });
}

// Global cached campaign dispatch state
let pendingDispatchState = null;

// Prompt styled confirmation modal
function promptSendConfirmation() {
    const subject = document.getElementById('campaignSubject').value.trim();
    const bodyContent = document.getElementById('campaignBody').value.trim();
    const csrfToken = document.getElementById('csrfToken').value;
    const mode = document.querySelector('input[name="recipient_mode"]:checked').value;

    if (!subject) {
        showAppAlert('Please enter a subject line for your promotion.', 'Subject Line Required', '✍️');
        document.getElementById('campaignSubject').focus();
        return;
    }
    if (!bodyContent) {
        showAppAlert('Please enter the email body content.', 'Message Content Required', '📝');
        document.getElementById('campaignBody').focus();
        return;
    }

    let selectedIds = [];
    if (mode === 'selected') {
        document.querySelectorAll('.user-check-item:checked').forEach(cb => {
            selectedIds.push(cb.value);
        });
        if (selectedIds.length === 0) {
            showAppAlert(<?= json_encode(__('no_recipients_selected')) ?>, 'No Recipients Selected', '👥');
            return;
        }
    }

    // Populate styled confirmation modal
    const subtextEl = document.getElementById('confirmRecipientSubtext');
    if (mode === 'selected') {
        subtextEl.innerHTML = <?= json_encode(__('confirm_send_desc_selected')) ?>.replace('%d', selectedIds.length);
    } else {
        subtextEl.innerHTML = <?= json_encode(__('confirm_send_desc_all')) ?>;
    }

    document.getElementById('confirmSubjectPreview').textContent = subject;

    // Cache state for execution
    pendingDispatchState = {
        subject: subject,
        bodyContent: bodyContent,
        csrfToken: csrfToken,
        selectedIds: selectedIds
    };

    openModal('confirmSendModal');
}

// Execute sending after modal confirmation
async function executeCampaignSending() {
    closeModal('confirmSendModal');

    if (!pendingDispatchState) {
        return;
    }

    const { subject, bodyContent, csrfToken, selectedIds } = pendingDispatchState;
    pendingDispatchState = null;

    // Disable start button
    const startBtn = document.getElementById('startSendBtn');
    startBtn.disabled = true;
    startBtn.style.opacity = '0.6';

    // Show progress box
    const progressBox = document.getElementById('progressCard');
    const progressBar = document.getElementById('progressBarFill');
    const progressStats = document.getElementById('progressStats');
    const progressPercent = document.getElementById('progressPercent');
    const errorsDiv = document.getElementById('progressErrors');
    const successMsg = document.getElementById('progressSuccessMsg');

    progressBox.style.display = 'block';
    progressBar.style.width = '0%';
    progressStats.textContent = 'Initializing campaign...';
    progressPercent.textContent = '0%';
    errorsDiv.style.display = 'none';
    errorsDiv.innerHTML = '';
    successMsg.style.display = 'none';

    // Scroll progress card into view smoothly
    progressBox.scrollIntoView({ behavior: 'smooth', block: 'start' });

    // 1. Create Campaign
    const formData = new FormData();
    formData.append('csrf_token', csrfToken);
    formData.append('subject', subject);
    formData.append('body_content', bodyContent);
    selectedIds.forEach(id => formData.append('user_ids[]', id));

    try {
        const createRes = await fetch('ajax_send_promotion.php?action=create', {
            method: 'POST',
            body: formData
        });
        const createData = await createRes.json();

        if (createData.status !== 'success') {
            throw new Error(createData.message || 'Failed to initialize campaign.');
        }

        const campaignId = createData.campaign_id;
        const totalRecipients = createData.total_recipients;

        progressStats.textContent = `0 / ${totalRecipients}`;

        // 2. Loop batches
        let isFinished = false;
        let totalSent = 0;
        let totalFailed = 0;

        while (!isFinished) {
            const batchForm = new FormData();
            batchForm.append('csrf_token', csrfToken);
            batchForm.append('campaign_id', campaignId);
            batchForm.append('batch_size', '5');

            const batchRes = await fetch('ajax_send_promotion.php?action=batch', {
                method: 'POST',
                body: batchForm
            });
            const batchData = await batchRes.json();

            if (batchData.status !== 'success') {
                throw new Error(batchData.message || 'Batch sending failed.');
            }

            const info = batchData.data;
            totalSent = info.total_sent;
            totalFailed = info.total_failed;
            isFinished = info.is_finished;

            const percentage = info.percentage;
            progressBar.style.width = percentage + '%';
            progressPercent.textContent = percentage + '%';
            progressStats.textContent = `${totalSent + totalFailed} / ${totalRecipients} (${totalSent} sent, ${totalFailed} failed)`;

            if (info.errors && info.errors.length > 0) {
                errorsDiv.style.display = 'block';
                info.errors.forEach(err => {
                    const p = document.createElement('p');
                    p.textContent = '• ' + err;
                    errorsDiv.appendChild(p);
                });
            }

            if (!isFinished) {
                await new Promise(r => setTimeout(r, 200));
            }
        }

        // Finished!
        progressBar.style.width = '100%';
        progressPercent.textContent = '100%';
        successMsg.style.display = 'block';
        startBtn.disabled = false;
        startBtn.style.opacity = '1';

    } catch (err) {
        showAppAlert('Campaign delivery error: ' + err.message, 'Execution Error', '❌');
        startBtn.disabled = false;
        startBtn.style.opacity = '1';
        progressStats.textContent = 'Error: ' + err.message;
    }
}
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
