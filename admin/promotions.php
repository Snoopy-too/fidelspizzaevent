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
/* Modal Styles */
.modal-overlay {
    display: none;
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0, 0, 0, 0.6);
    z-index: 9999;
    align-items: center;
    justify-content: center;
    padding: 20px;
}
.modal-content {
    background: #ffffff;
    border-radius: 8px;
    max-width: 750px;
    width: 100%;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
    box-shadow: 0 10px 30px rgba(0,0,0,0.25);
}
.modal-header {
    padding: 16px 20px;
    border-bottom: 1px solid #e9ecef;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.modal-body {
    padding: 20px;
    overflow-y: auto;
}
.modal-footer {
    padding: 15px 20px;
    border-top: 1px solid #e9ecef;
    text-align: right;
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
                <h3 style="margin-bottom: 12px; color: #2c3e50;">👥 <?= __('recipients_selected') ?></h3>
                
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
                    <button type="button" id="startSendBtn" class="btn" style="background-color: #d32f2f; color: white; padding: 10px 24px; border-radius: 6px; font-weight: bold; border: none; cursor: pointer;" onclick="confirmAndStartCampaign()">
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

<!-- PREVIEW MODAL -->
<div id="previewModal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h3 style="margin: 0;">👁️ <?= __('preview_email') ?></h3>
            <button type="button" onclick="closeModal('previewModal')" style="background: none; border: none; font-size: 24px; cursor: pointer;">&times;</button>
        </div>
        <div class="modal-body">
            <div style="background: #f8f9fa; padding: 12px; border-radius: 6px; margin-bottom: 15px; font-size: 13px;">
                <p><strong>Sample Recipient:</strong> <span id="previewSampleUser">-</span></p>
                <p><strong>Subject:</strong> <span id="previewSubjectText" style="font-weight: bold; color: #2c3e50;">-</span></p>
            </div>
            <iframe id="previewIframe" style="width: 100%; height: 380px; border: 1px solid #ddd; border-radius: 6px; background: white;"></iframe>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('previewModal')"><?= __('close') ?></button>
        </div>
    </div>
</div>

<!-- RECIPIENTS LOG MODAL -->
<div id="recipientsModal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h3 style="margin: 0;">📋 Delivery Log: <span id="logCampaignTitle" style="font-weight: normal; font-size: 15px;"></span></h3>
            <button type="button" onclick="closeModal('recipientsModal')" style="background: none; border: none; font-size: 24px; cursor: pointer;">&times;</button>
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
            <button type="button" class="btn btn-secondary" onclick="closeModal('recipientsModal')"><?= __('close') ?></button>
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

function toggleRecipientPicker(show) {
    const area = document.getElementById('recipientPickerArea');
    area.style.display = show ? 'block' : 'none';
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

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

function openPreviewModal() {
    const subject = document.getElementById('campaignSubject').value.trim();
    const bodyContent = document.getElementById('campaignBody').value.trim();
    const csrfToken = document.getElementById('csrfToken').value;

    if (!subject) {
        alert('Please enter a subject line first.');
        document.getElementById('campaignSubject').focus();
        return;
    }
    if (!bodyContent) {
        alert('Please enter email body content first.');
        document.getElementById('campaignBody').focus();
        return;
    }

    // Find first selected user ID if available
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

            document.getElementById('previewModal').style.display = 'flex';
        } else {
            alert('Preview error: ' + (data.message || 'Unknown error'));
        }
    })
    .catch(err => {
        alert('Communication error: ' + err.message);
    });
}

function openRecipientsModal(campaignId, title) {
    document.getElementById('logCampaignTitle').textContent = title;
    document.getElementById('logLoading').style.display = 'block';
    document.getElementById('logTable').style.display = 'none';
    document.getElementById('recipientsModal').style.display = 'flex';

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
            alert('Could not load log: ' + res.message);
        }
    })
    .catch(e => {
        document.getElementById('logLoading').textContent = 'Error loading logs: ' + e.message;
    });
}

async function confirmAndStartCampaign() {
    const subject = document.getElementById('campaignSubject').value.trim();
    const bodyContent = document.getElementById('campaignBody').value.trim();
    const csrfToken = document.getElementById('csrfToken').value;
    const mode = document.querySelector('input[name="recipient_mode"]:checked').value;

    if (!subject) {
        alert('Please enter a subject line.');
        document.getElementById('campaignSubject').focus();
        return;
    }
    if (!bodyContent) {
        alert('Please enter email body content.');
        document.getElementById('campaignBody').focus();
        return;
    }

    let selectedIds = [];
    if (mode === 'selected') {
        document.querySelectorAll('.user-check-item:checked').forEach(cb => {
            selectedIds.push(cb.value);
        });
        if (selectedIds.length === 0) {
            alert(<?= json_encode(__('no_recipients_selected')) ?>);
            return;
        }
    }

    const confirmMsg = mode === 'selected' 
        ? `Are you sure you want to send this promotion to ${selectedIds.length} selected recipient(s)?`
        : `Are you sure you want to send this promotion to ALL eligible confirmed users?`;

    if (!confirm(confirmMsg)) {
        return;
    }

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
                // Short throttle of 200ms to allow UI render
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
        alert('Campaign error: ' + err.message);
        startBtn.disabled = false;
        startBtn.style.opacity = '1';
        progressStats.textContent = 'Error: ' + err.message;
    }
}
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
