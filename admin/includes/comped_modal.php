<?php
declare(strict_types=1);

/**
 * Reusable Comp'd Pizzas Modal Partial
 * Included on admin/dashboard.php and admin/orders.php
 *
 * @var \FidelsPizza\Infrastructure\Container\ServiceContainer $container
 */

$getCompedUseCase = $container->getGetCompedOrdersUseCase();
$compedMenuItems = $getCompedUseCase->getActiveMenuItems();
$compedOrdersList = $getCompedUseCase->getOrders();
$compedSlots = $container->getManagePickupTimeSlotsUseCase()->getAllSlots(1);
$compedSummary = $getCompedUseCase->getSummary();
?>

<!-- Comp'd Pizzas Modal -->
<div class="modal-backdrop" id="compedModalBackdrop" onclick="handleCompedBackdropClick(event)">
    <div class="modal-dialog modal-dialog-lg" role="dialog" aria-modal="true" aria-labelledby="compedModalTitle">
        <div class="modal-header">
            <h3 id="compedModalTitle">🎁 <?= htmlspecialchars((string)__('comped_modal_title')) ?></h3>
            <button type="button" class="modal-close" onclick="closeCompedModal()" aria-label="Close">&times;</button>
        </div>
        
        <div class="modal-body">
            <p style="margin: 0; color: #64748b; font-size: 0.92em;">
                <?= htmlspecialchars((string)__('comped_modal_subtitle')) ?>
            </p>

            <div class="comped-modal-tabs">
                <button type="button" class="comped-tab-btn active" id="compedTabBtnAdd" onclick="switchCompedTab('add')">
                    ➕ <?= htmlspecialchars((string)__('add_comped_pizzas')) ?>
                </button>
                <button type="button" class="comped-tab-btn" id="compedTabBtnHistory" onclick="switchCompedTab('history')">
                    📋 <?= htmlspecialchars((string)__('comped_orders_history')) ?> (<?= count($compedOrdersList) ?>)
                </button>
            </div>

            <!-- Tab 1: Add Comp'd Pizzas -->
            <div class="comped-tab-content active" id="compedTabContentAdd">
                <form method="POST" id="compedOrderForm" onsubmit="return validateCompedForm(this)">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken()) ?>">
                    <input type="hidden" name="create_comped_order" value="1">

                    <div style="margin-bottom: 16px;">
                        <label style="display: block; font-weight: bold; margin-bottom: 8px; color: #2c3e50;">
                            🍕 <?= htmlspecialchars((string)__('menu_title')) ?>:
                        </label>
                        <?php if (empty($compedMenuItems)): ?>
                            <p style="color: #999;"><?= htmlspecialchars((string)__('item_not_found')) ?></p>
                        <?php else: ?>
                            <div style="display: flex; flex-direction: column; gap: 6px;">
                                <?php foreach ($compedMenuItems as $menuItem): ?>
                                    <div class="comped-pizza-item">
                                        <div class="comped-pizza-info">
                                            <?php if (!empty($menuItem['image_path']) && file_exists(__DIR__ . '/../../' . $menuItem['image_path'])): ?>
                                                <img src="../<?= htmlspecialchars((string)$menuItem['image_path']) ?>" alt="<?= htmlspecialchars((string)$menuItem['name']) ?>" class="comped-pizza-img">
                                            <?php else: ?>
                                                <div class="comped-pizza-placeholder">🍕</div>
                                            <?php endif; ?>
                                            <div>
                                                <strong style="color: #2c3e50; font-size: 1.02em;"><?= htmlspecialchars((string)$menuItem['name']) ?></strong>
                                                <div style="color: #64748b; font-size: 0.85em;"><?= formatPrice((float)$menuItem['price']) ?> → <span style="color: #8e44ad; font-weight: bold;">¥0 (Comp)</span></div>
                                            </div>
                                        </div>
                                        <div class="comped-qty-control">
                                            <button type="button" class="comped-qty-btn" onclick="stepCompedQty(<?= (int)$menuItem['id'] ?>, -1)">−</button>
                                            <input type="number" 
                                                   name="item_quantities[<?= (int)$menuItem['id'] ?>]" 
                                                   id="comped_qty_<?= (int)$menuItem['id'] ?>" 
                                                   value="0" 
                                                   min="0" 
                                                   max="50" 
                                                   class="comped-qty-input" 
                                                   oninput="recalculateCompedTotal()">
                                            <button type="button" class="comped-qty-btn" onclick="stepCompedQty(<?= (int)$menuItem['id'] ?>, 1)">+</button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div style="margin-bottom: 16px;">
                        <label for="comped_notes" style="display: block; font-weight: bold; margin-bottom: 6px; color: #2c3e50;">
                            🏷️ <?= htmlspecialchars((string)__('comped_recipient_label')) ?>
                        </label>
                        <input type="text" 
                               name="notes" 
                               id="comped_notes" 
                               style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.95em;" 
                               placeholder="<?= htmlspecialchars((string)__('comped_recipient_placeholder')) ?>">
                    </div>

                    <div style="margin-bottom: 20px;">
                        <label for="comped_pickup_slot_id" style="display: block; font-weight: bold; margin-bottom: 6px; color: #2c3e50;">
                            ⏰ <?= htmlspecialchars((string)__('comped_pickup_slot_label')) ?>
                        </label>
                        <select name="pickup_slot_id" id="comped_pickup_slot_id" style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.95em;">
                            <option value=""><?= htmlspecialchars((string)__('comped_pickup_slot_none')) ?></option>
                            <?php foreach ($compedSlots as $slot): ?>
                                <option value="<?= (int)$slot->getId() ?>">
                                    <?= htmlspecialchars($slot->getDisplayName()) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div style="background: #f1f5f9; padding: 12px 16px; border-radius: 8px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <div>
                            <span style="font-weight: 600; color: #334155;"><?= htmlspecialchars((string)__('comped_pizzas')) ?>:</span>
                            <span id="compedTotalSelectedBadge" style="background: #8e44ad; color: white; padding: 2px 8px; border-radius: 12px; font-weight: bold; margin-left: 6px;">0</span>
                        </div>
                        <div>
                            <span style="font-weight: 600; color: #334155;"><?= htmlspecialchars((string)__('total_amount')) ?>:</span>
                            <span style="font-weight: bold; color: #27ae60; margin-left: 6px;">¥0</span>
                        </div>
                    </div>

                    <div style="display: flex; justify-content: flex-end; gap: 10px;">
                        <button type="button" class="btn" style="background: #94a3b8;" onclick="closeCompedModal()"><?= htmlspecialchars((string)__('back_home')) ?></button>
                        <button type="submit" class="btn" style="background: #8e44ad;"><?= htmlspecialchars((string)__('create_comped_order_btn')) ?></button>
                    </div>
                </form>
            </div>

            <!-- Tab 2: Existing Comp'd Orders History -->
            <div class="comped-tab-content" id="compedTabContentHistory">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 8px;">
                    <div>
                        <strong><?= htmlspecialchars((string)__('total_pizzas_to_prep')) ?>:</strong>
                        <span style="background: #8e44ad; color: white; padding: 2px 10px; border-radius: 12px; font-weight: bold;">
                            <?= (int)$compedSummary['total_comped_pizzas'] ?> <?= htmlspecialchars((string)__('quantity')) ?>
                        </span>
                    </div>
                    <?php if (!empty($compedSummary['items_breakdown'])): ?>
                        <div style="font-size: 0.9em; color: #475569;">
                            <?php 
                            $breakdownParts = [];
                            foreach ($compedSummary['items_breakdown'] as $itemName => $qty) {
                                $breakdownParts[] = htmlspecialchars($itemName) . ': ' . (int)$qty;
                            }
                            echo implode(' | ', $breakdownParts);
                            ?>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if (empty($compedOrdersList)): ?>
                    <p style="text-align: center; padding: 30px; color: #94a3b8;">
                        <?= htmlspecialchars((string)__('no_comped_pizzas')) ?>
                    </p>
                <?php else: ?>
                    <div style="max-height: 400px; overflow-y: auto;">
                        <table class="table" style="font-size: 0.92em;">
                            <thead>
                                <tr>
                                    <th><?= htmlspecialchars((string)__('order_number_label')) ?></th>
                                    <th><?= htmlspecialchars((string)__('items')) ?></th>
                                    <th><?= htmlspecialchars((string)__('notes')) ?></th>
                                    <th><?= htmlspecialchars((string)__('pickup_time')) ?></th>
                                    <th><?= htmlspecialchars((string)__('action')) ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($compedOrdersList as $comped): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($comped->getOrderNumber()) ?></strong></td>
                                        <td><?= htmlspecialchars($comped->getItemsSummary()) ?></td>
                                        <td><?= htmlspecialchars($comped->getNotes() ?? '-') ?></td>
                                        <td><?= htmlspecialchars($comped->getPickupTime() ? date('Y/m/d H:i', strtotime($comped->getPickupTime())) : __('comped_pickup_slot_none')) ?></td>
                                        <td>
                                            <a href="order_details.php?id=<?= (int)$comped->getId() ?>" style="color: #3498db; text-decoration: none; font-weight: 600;">
                                                <?= htmlspecialchars((string)__('view_details')) ?>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function openCompedModal(tab = 'add') {
    const modal = document.getElementById('compedModalBackdrop');
    if (modal) {
        modal.classList.add('is-open');
        switchCompedTab(tab);
    }
}

function closeCompedModal() {
    const modal = document.getElementById('compedModalBackdrop');
    if (modal) {
        modal.classList.remove('is-open');
    }
}

function handleCompedBackdropClick(e) {
    if (e.target && e.target.id === 'compedModalBackdrop') {
        closeCompedModal();
    }
}

function switchCompedTab(tabName) {
    const btnAdd = document.getElementById('compedTabBtnAdd');
    const btnHistory = document.getElementById('compedTabBtnHistory');
    const contentAdd = document.getElementById('compedTabContentAdd');
    const contentHistory = document.getElementById('compedTabContentHistory');

    if (!btnAdd || !btnHistory || !contentAdd || !contentHistory) return;

    if (tabName === 'history') {
        btnAdd.classList.remove('active');
        btnHistory.classList.add('active');
        contentAdd.classList.remove('active');
        contentHistory.classList.add('active');
    } else {
        btnHistory.classList.remove('active');
        btnAdd.classList.add('active');
        contentHistory.classList.remove('active');
        contentAdd.classList.add('active');
    }
}

function stepCompedQty(itemId, delta) {
    const input = document.getElementById('comped_qty_' + itemId);
    if (!input) return;
    let val = parseInt(input.value, 10) || 0;
    val = Math.max(0, Math.min(50, val + delta));
    input.value = val;
    recalculateCompedTotal();
}

function recalculateCompedTotal() {
    const inputs = document.querySelectorAll('.comped-qty-input');
    let total = 0;
    inputs.forEach(input => {
        total += (parseInt(input.value, 10) || 0);
    });
    const badge = document.getElementById('compedTotalSelectedBadge');
    if (badge) {
        badge.textContent = total;
    }
}

function validateCompedForm(form) {
    const inputs = form.querySelectorAll('.comped-qty-input');
    let total = 0;
    inputs.forEach(input => {
        total += (parseInt(input.value, 10) || 0);
    });
    if (total <= 0) {
        alert('<?= htmlspecialchars((string)__('error_all_fields_required'), ENT_QUOTES) ?> (Please select at least 1 pizza)');
        return false;
    }
    return true;
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeCompedModal();
    }
});
</script>
