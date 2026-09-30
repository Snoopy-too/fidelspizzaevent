<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../helpers.php';

requireLogin();
requireAdmin();

$container = getServiceContainer();
$db = $container->getPdo();
$manageSlotsUseCase = $container->getManagePickupTimeSlotsUseCase();
// Ensure admin_email_2 column and cancellation templates exist before any transaction
$container->getSendOrderNotificationUseCase();

// Fetch current config
$stmt = $db->prepare("SELECT * FROM site_config WHERE id = 1");
$stmt->execute();
$config = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$error_message = null;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', __('invalid_csrf_token') ?: 'Invalid CSRF token.');
        redirect('settings.php');
    }

    try {
        $db->beginTransaction();

        $eventDateInput = trim((string)($_POST['event_date'] ?? ''));
        $eventDateValue = $eventDateInput !== '' ? $eventDateInput : null;

        $stmt = $db->prepare("
            UPDATE site_config
            SET site_title = ?, event_location = ?, event_date = ?, registration_code = ?, landing_content = ?, menu_content = ?, admin_email = ?, admin_email_2 = ?
            WHERE id = 1
        ");
        $stmt->execute([
            trim((string)($_POST['site_title'] ?? '')),
            trim((string)($_POST['event_location'] ?? '')),
            $eventDateValue,
            trim((string)($_POST['registration_code'] ?? '')),
            (string)($_POST['landing_content'] ?? ''),
            (string)($_POST['menu_content'] ?? ''),
            trim((string)($_POST['admin_email'] ?? '')),
            trim((string)($_POST['admin_email_2'] ?? '')),
        ]);

        $rawSlots = isset($_POST['slots']) && is_array($_POST['slots']) ? $_POST['slots'] : [];
        $manageSlotsUseCase->saveAdminSlots($rawSlots, 1);

        $db->commit();

        setFlash('success', __('settings_updated') ?: 'Settings updated successfully.');
        header("Location: settings.php?success=1");
        exit;
    } catch (\Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $error_message = $e->getMessage();
        // Keep submitted config values in form
        $config['site_title'] = $_POST['site_title'] ?? ($config['site_title'] ?? '');
        $config['event_location'] = $_POST['event_location'] ?? ($config['event_location'] ?? '');
        $config['event_date'] = $_POST['event_date'] ?? ($config['event_date'] ?? '');
        $config['registration_code'] = $_POST['registration_code'] ?? ($config['registration_code'] ?? '');
        $config['landing_content'] = $_POST['landing_content'] ?? ($config['landing_content'] ?? '');
        $config['menu_content'] = $_POST['menu_content'] ?? ($config['menu_content'] ?? '');
        $config['admin_email'] = $_POST['admin_email'] ?? ($config['admin_email'] ?? '');
        $config['admin_email_2'] = $_POST['admin_email_2'] ?? ($config['admin_email_2'] ?? '');
    }
}

$pickupSlots = $manageSlotsUseCase->getAllSlots(1, (string)($config['event_date'] ?? ''));

$page_title = __('settings_title');
require_once __DIR__ . '/includes/header.php';
?>
<style>
.pickup-slots-card {
    margin-top: 22px;
    margin-bottom: 10px;
    padding: 20px;
    background: #f8fafc;
    border: 1px solid #cbd5e1;
    border-radius: 10px;
}
.pickup-slots-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 15px;
    flex-wrap: wrap;
    margin-bottom: 14px;
}
.pickup-slots-header h3 {
    margin: 0 0 4px 0 !important;
    padding: 0 !important;
    border: none !important;
    font-size: 1.15em;
    color: #1e293b;
}
.pickup-slots-desc {
    margin: 0;
    font-size: 0.92em;
    color: #64748b;
}
.pickup-slots-table {
    width: 100%;
    border-collapse: collapse;
    background: #fff;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0,0,0,0.06);
}
.pickup-slots-table th,
.pickup-slots-table td {
    padding: 10px 12px;
    border-bottom: 1px solid #e2e8f0;
    vertical-align: middle;
    text-align: left;
}
.pickup-slots-table th {
    background: #334155;
    color: #fff;
    font-size: 0.88em;
    font-weight: 600;
    white-space: nowrap;
}
.pickup-slots-table input[type="text"],
.pickup-slots-table input[type="time"],
.pickup-slots-table input[type="number"] {
    width: 100%;
    padding: 8px 10px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    font-size: 0.95em;
    margin: 0;
}
.pickup-slots-table input[type="checkbox"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
}
.slot-booking-pill {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 999px;
    background: #e0f2fe;
    color: #0369a1;
    font-size: 0.84em;
    font-weight: 600;
    white-space: nowrap;
}
.btn-remove-slot {
    background: #fee2e2;
    color: #b91c1c;
    border: 1px solid #fecaca;
    padding: 6px 12px;
    border-radius: 6px;
    font-weight: 600;
    font-size: 0.85em;
    cursor: pointer;
    transition: all 0.15s ease;
}
.btn-remove-slot:hover {
    background: #ef4444;
    color: #fff;
    border-color: #ef4444;
}
.empty-slots-row td {
    text-align: center !important;
    color: #64748b;
    padding: 20px !important;
    font-style: italic;
}
@media (max-width: 768px) {
    .pickup-slots-table {
        display: block;
        overflow-x: auto;
    }
}
</style>

        <div class="section">
            <h2><?= htmlspecialchars((string)__('settings_title')) ?></h2>

            <?php if ($error_message !== null): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error_message) ?></div>
            <?php elseif (isset($_GET['success'])): ?>
                <div class="success"><?= htmlspecialchars((string)__('settings_updated')) ?></div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken()) ?>">

                <label for="site_title"><?= htmlspecialchars((string)__('site_title_label')) ?></label>
                <input type="text" name="site_title" id="site_title" value="<?= htmlspecialchars((string)($config['site_title'] ?? '')) ?>" required>

                <label for="event_location"><?= htmlspecialchars((string)__('event_location_label')) ?></label>
                <textarea name="event_location" id="event_location"><?= htmlspecialchars((string)($config['event_location'] ?? '')) ?></textarea>

                <label for="event_date"><?= htmlspecialchars((string)__('event_date')) ?></label>
                <input type="date" name="event_date" id="event_date" value="<?= htmlspecialchars((string)($config['event_date'] ?? '')) ?>">

                <!-- PICKUP TIME SLOTS CONFIGURATION -->
                <div class="pickup-slots-card">
                    <div class="pickup-slots-header">
                        <div>
                            <h3>⏰ <?= htmlspecialchars((string)__('pickup_time_slots_title')) ?></h3>
                            <p class="pickup-slots-desc"><?= htmlspecialchars((string)__('pickup_time_slots_desc')) ?></p>
                        </div>
                        <button type="button" class="btn btn-save" onclick="addPickupSlotRow()">
                            <?= htmlspecialchars((string)__('add_pickup_time_slot')) ?>
                        </button>
                    </div>

                    <table class="pickup-slots-table" id="pickupSlotsTable">
                        <thead>
                            <tr>
                                <th style="min-width: 165px;"><?= htmlspecialchars((string)__('slot_label_column')) ?></th>
                                <th style="min-width: 125px;"><?= htmlspecialchars((string)__('slot_start_time_column')) ?></th>
                                <th style="min-width: 125px;"><?= htmlspecialchars((string)__('slot_end_time_column')) ?></th>
                                <th style="min-width: 110px;"><?= htmlspecialchars((string)__('slot_max_pizzas_column')) ?></th>
                                <th style="min-width: 110px;"><?= htmlspecialchars((string)__('slot_max_orders_column')) ?></th>
                                <th style="width: 65px; text-align: center;"><?= htmlspecialchars((string)__('slot_active_column')) ?></th>
                                <th style="min-width: 125px;"><?= htmlspecialchars((string)__('slot_current_bookings_column')) ?></th>
                                <th style="width: 85px; text-align: center;"><?= htmlspecialchars((string)__('actions')) ?></th>
                            </tr>
                        </thead>
                        <tbody id="pickupSlotsBody">
                            <?php if (empty($pickupSlots)): ?>
                                <tr class="empty-slots-row" id="emptySlotsRow">
                                    <td colspan="8"><?= htmlspecialchars((string)__('no_pickup_slots_configured')) ?></td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($pickupSlots as $idx => $slot): ?>
                                    <tr class="pickup-slot-row">
                                        <td>
                                            <input type="hidden" name="slots[<?= $idx ?>][id]" value="<?= (int)$slot->getId() ?>">
                                            <input type="text"
                                                   name="slots[<?= $idx ?>][label]"
                                                   value="<?= htmlspecialchars($slot->getLabel()) ?>"
                                                   placeholder="<?= htmlspecialchars((string)__('slot_label_placeholder')) ?>"
                                                   maxlength="100">
                                        </td>
                                        <td>
                                            <input type="time"
                                                   class="slot-start-input"
                                                   name="slots[<?= $idx ?>][slot_time]"
                                                   value="<?= htmlspecialchars($slot->getSlotTime()) ?>"
                                                   required>
                                        </td>
                                        <td>
                                            <input type="time"
                                                   class="slot-end-input"
                                                   name="slots[<?= $idx ?>][end_time]"
                                                   value="<?= htmlspecialchars($slot->getEndTime()) ?>"
                                                   required>
                                        </td>
                                        <td>
                                            <input type="number"
                                                   name="slots[<?= $idx ?>][max_pizzas]"
                                                   value="<?= $slot->getMaxPizzas() !== null ? (int)$slot->getMaxPizzas() : '' ?>"
                                                   min="1"
                                                   placeholder="<?= htmlspecialchars((string)__('slot_unlimited_placeholder')) ?>">
                                        </td>
                                        <td>
                                            <input type="number"
                                                   name="slots[<?= $idx ?>][max_orders]"
                                                   value="<?= $slot->getMaxOrders() !== null ? (int)$slot->getMaxOrders() : '' ?>"
                                                   min="1"
                                                   placeholder="<?= htmlspecialchars((string)__('slot_unlimited_placeholder')) ?>">
                                        </td>
                                        <td style="text-align: center;">
                                            <input type="checkbox"
                                                   name="slots[<?= $idx ?>][is_active]"
                                                   value="1"
                                                   <?= $slot->isActive() ? 'checked' : '' ?>>
                                        </td>
                                        <td>
                                            <span class="slot-booking-pill">
                                                <?= htmlspecialchars(sprintf((string)__('slot_bookings_badge'), $slot->getBookedOrders(), $slot->getBookedPizzas())) ?>
                                            </span>
                                        </td>
                                        <td style="text-align: center;">
                                            <button type="button" class="btn-remove-slot" onclick="removePickupSlotRow(this)">
                                                <?= htmlspecialchars((string)__('remove_slot')) ?>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <label for="registration_code"><?= htmlspecialchars((string)__('registration_code')) ?></label>
                <input type="text" name="registration_code" id="registration_code" value="<?= htmlspecialchars((string)($config['registration_code'] ?? '')) ?>">

                <label for="landing_content"><?= htmlspecialchars((string)__('landing_page_content')) ?></label>
                <textarea name="landing_content" id="landing_content"><?= htmlspecialchars((string)($config['landing_content'] ?? '')) ?></textarea>

                <label for="menu_content"><?= htmlspecialchars((string)__('menu_page_content')) ?></label>
                <textarea name="menu_content" id="menu_content"><?= htmlspecialchars((string)($config['menu_content'] ?? '')) ?></textarea>

                <label for="admin_email"><?= htmlspecialchars((string)__('admin_email_notification')) ?></label>
                <input type="email" name="admin_email" id="admin_email" value="<?= htmlspecialchars((string)($config['admin_email'] ?? '')) ?>" placeholder="admin1@example.com">

                <label for="admin_email_2"><?= htmlspecialchars((string)__('admin_email_notification_2')) ?></label>
                <input type="email" name="admin_email_2" id="admin_email_2" value="<?= htmlspecialchars((string)($config['admin_email_2'] ?? '')) ?>" placeholder="admin2@example.com">

                <div style="margin-top: 20px;">
                    <button type="submit" class="btn btn-save"><?= htmlspecialchars((string)__('save_settings')) ?></button>
                </div>
            </form>
        </div>

<script>
let nextSlotIndex = <?= count($pickupSlots) + 100 ?>;

function getNextSuggestedLabel() {
    const rows = document.querySelectorAll('#pickupSlotsBody .pickup-slot-row');
    const letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    const letter = letters[rows.length] || (rows.length + 1);
    return 'Pickup Time ' + letter;
}

function addOneHour(timeStr) {
    if (!timeStr || !timeStr.includes(':')) {
        return '12:30';
    }
    const parts = timeStr.split(':');
    let hours = parseInt(parts[0], 10);
    let minutes = parseInt(parts[1], 10);
    if (isNaN(hours) || isNaN(minutes)) {
        return '12:30';
    }
    if (hours >= 23) {
        return '23:59';
    }
    return String(hours + 1).padStart(2, '0') + ':' + String(minutes).padStart(2, '0');
}

function getNextSuggestedTimeRange() {
    const endInputs = document.querySelectorAll('#pickupSlotsBody .slot-end-input');
    if (endInputs.length === 0) {
        return { start: '11:30', end: '12:30' };
    }
    const lastEnd = endInputs[endInputs.length - 1].value || '12:30';
    return {
        start: lastEnd,
        end: addOneHour(lastEnd)
    };
}

function addPickupSlotRow() {
    const emptyRow = document.getElementById('emptySlotsRow');
    if (emptyRow) {
        emptyRow.remove();
    }

    const tbody = document.getElementById('pickupSlotsBody');
    const idx = nextSlotIndex++;
    const suggestedLabel = getNextSuggestedLabel();
    const suggestedRange = getNextSuggestedTimeRange();

    const tr = document.createElement('tr');
    tr.className = 'pickup-slot-row';
    tr.innerHTML = `
        <td>
            <input type="hidden" name="slots[${idx}][id]" value="">
            <input type="text"
                   name="slots[${idx}][label]"
                   value="${suggestedLabel}"
                   placeholder="<?= htmlspecialchars((string)__('slot_label_placeholder'), ENT_QUOTES) ?>"
                   maxlength="100">
        </td>
        <td>
            <input type="time"
                   class="slot-start-input"
                   name="slots[${idx}][slot_time]"
                   value="${suggestedRange.start}"
                   required>
        </td>
        <td>
            <input type="time"
                   class="slot-end-input"
                   name="slots[${idx}][end_time]"
                   value="${suggestedRange.end}"
                   required>
        </td>
        <td>
            <input type="number"
                   name="slots[${idx}][max_pizzas]"
                   value=""
                   min="1"
                   placeholder="<?= htmlspecialchars((string)__('slot_unlimited_placeholder'), ENT_QUOTES) ?>">
        </td>
        <td>
            <input type="number"
                   name="slots[${idx}][max_orders]"
                   value=""
                   min="1"
                   placeholder="<?= htmlspecialchars((string)__('slot_unlimited_placeholder'), ENT_QUOTES) ?>">
        </td>
        <td style="text-align: center;">
            <input type="checkbox"
                   name="slots[${idx}][is_active]"
                   value="1"
                   checked>
        </td>
        <td>
            <span class="slot-booking-pill">
                <?= htmlspecialchars(sprintf((string)__('slot_bookings_badge'), 0, 0), ENT_QUOTES) ?>
            </span>
        </td>
        <td style="text-align: center;">
            <button type="button" class="btn-remove-slot" onclick="removePickupSlotRow(this)">
                <?= htmlspecialchars((string)__('remove_slot'), ENT_QUOTES) ?>
            </button>
        </td>
    `;
    tbody.appendChild(tr);
}

function removePickupSlotRow(btn) {
    const tr = btn.closest('tr');
    if (tr) {
        tr.remove();
    }
    const tbody = document.getElementById('pickupSlotsBody');
    if (tbody.querySelectorAll('.pickup-slot-row').length === 0) {
        const emptyTr = document.createElement('tr');
        emptyTr.className = 'empty-slots-row';
        emptyTr.id = 'emptySlotsRow';
        emptyTr.innerHTML = `<td colspan="8"><?= htmlspecialchars((string)__('no_pickup_slots_configured'), ENT_QUOTES) ?></td>`;
        tbody.appendChild(emptyTr);
    }
}
</script>
<?php
require_once __DIR__ . '/includes/footer.php';