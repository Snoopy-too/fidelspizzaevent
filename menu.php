<?php
declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

// Ensure user is logged in
requireLogin();

if (empty($_SESSION['user_id'])) {
    redirect('login.php');
    exit;
}

$user_name = !empty($_SESSION['user_name']) ? (string)$_SESSION['user_name'] : 'お客様';

$container = getServiceContainer();
$config = getSiteConfig();
$db = $container->getPdo();
$manageSlotsUseCase = $container->getManagePickupTimeSlotsUseCase();

$eventDate = !empty($config['event_date']) ? (string)$config['event_date'] : date('Y-m-d');
$availableSlots = $manageSlotsUseCase->getAvailableSlotsForOrder($eventDate);

// Get menu items
$stmt = $db->query("SELECT * FROM menu_items WHERE is_active = 1 ORDER BY sort_order, name");
$menu_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$selectedSlotId = isset($_POST['pickup_slot_id']) ? (int)$_POST['pickup_slot_id'] : 0;
if ($selectedSlotId === 0 && !empty($availableSlots)) {
    foreach ($availableSlots as $slot) {
        if (!$slot->isFull()) {
            $selectedSlotId = (int)$slot->getId();
            break;
        }
    }
}

// Handle order submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_order'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = (string)__('invalid_csrf_token');
    } else {
        $order_items = [];
        $total_amount = 0.0;
        $total_pizzas = 0;

        foreach ($menu_items as $item) {
            $quantity = (int)($_POST['quantity_' . $item['id']] ?? 0);
            if ($quantity > 0 && $quantity <= 15) {
                $subtotal = $quantity * (float)$item['price'];
                $order_items[] = [
                    'menu_item_id' => (int)$item['id'],
                    'name' => (string)$item['name'],
                    'quantity' => $quantity,
                    'unit_price' => (float)$item['price'],
                    'subtotal' => $subtotal,
                ];
                $total_amount += $subtotal;
                $total_pizzas += $quantity;
            }
        }

        if (empty($order_items)) {
            $error = '注文するにはピザを1つ以上選択してください。';
        } elseif (!empty($availableSlots) && $selectedSlotId <= 0) {
            $error = (string)__('pickup_time_required');
        } else {
            try {
                $db->beginTransaction();

                $pickup_time = null;
                $pickup_slot_id = null;
                $pickup_display = null;

                if (!empty($availableSlots)) {
                    $validatedSlot = $manageSlotsUseCase->validateSlotForOrder(
                        $selectedSlotId,
                        $total_pizzas,
                        $eventDate
                    );
                    $pickup_time = $validatedSlot->toDatetimeString($eventDate);
                    $pickup_slot_id = $validatedSlot->getId();
                    $pickup_display = $validatedSlot->getDisplayName();
                } elseif (!empty($config['event_date'])) {
                    $pickup_time = date('Y-m-d H:i:s', strtotime((string)$config['event_date'] . ' 12:30'));
                    $pickup_display = '12:30';
                }

                $order_number = generateOrderNumber();

                $colCheck = $db->query("SHOW COLUMNS FROM `orders` LIKE 'pickup_slot_id'");
                if ($colCheck && $colCheck->rowCount() > 0) {
                    $stmt = $db->prepare("
                        INSERT INTO orders (user_id, order_number, total_amount, pickup_time, pickup_slot_id)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$_SESSION['user_id'], $order_number, $total_amount, $pickup_time, $pickup_slot_id]);
                } else {
                    $stmt = $db->prepare("
                        INSERT INTO orders (user_id, order_number, total_amount, pickup_time)
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->execute([$_SESSION['user_id'], $order_number, $total_amount, $pickup_time]);
                }
                $order_id = (int)$db->lastInsertId();

                $stmt = $db->prepare("
                    INSERT INTO order_items (order_id, menu_item_id, quantity, unit_price, subtotal)
                    VALUES (?, ?, ?, ?, ?)
                ");
                foreach ($order_items as $item) {
                    $stmt->execute([
                        $order_id,
                        $item['menu_item_id'],
                        $item['quantity'],
                        $item['unit_price'],
                        $item['subtotal'],
                    ]);
                }

                $db->commit();

                // Send email notifications
                $emailSent = sendOrderEmailNotifications($order_id);

                // Get user's email to display on success page
                $userStmt = $db->prepare("SELECT email FROM users WHERE id = ?");
                $userStmt->execute([$_SESSION['user_id']]);
                $userEmail = (string)$userStmt->fetchColumn();

                // Redirect to success page
                $_SESSION['order_success'] = [
                    'order_number' => $order_number,
                    'total_amount' => $total_amount,
                    'pickup_time' => $pickup_time,
                    'pickup_display' => $pickup_display,
                    'email_sent' => $emailSent,
                    'user_email' => $userEmail,
                ];
                redirect('order_success.php');
            } catch (\InvalidArgumentException $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                $error = $e->getMessage();
                $availableSlots = $manageSlotsUseCase->getAvailableSlotsForOrder($eventDate);
            } catch (\Throwable $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                $error = '注文の送信に失敗しました。もう一度お試しください。';
            }
        }
    }
}

$slotsForJs = array_map(static fn($s) => $s->toArray(), $availableSlots);
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars((string)($_SESSION['lang'] ?? 'ja')) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars((string)__('menu_title')) ?> - <?= htmlspecialchars((string)($config['site_title'] ?? __('site_title'))) ?></title>
<style>
* {margin:0; padding:0; box-sizing:border-box;}
body {font-family:'Arial', sans-serif; background:linear-gradient(135deg,#ff6b6b,#feca57); min-height:100vh; padding:20px 0;}
.container {max-width:1200px; margin:0 auto; padding:0 20px;}
header {background:rgba(255,255,255,0.95); padding:30px; border-radius:15px; box-shadow:0 10px 30px rgba(0,0,0,0.2); margin-bottom:30px; text-align:center;}
h1 {color:#d32f2f; font-size:2.5em; margin-bottom:10px;}
.user-info {background:#e3f2fd; padding:15px; border-radius:8px; margin:20px 0; border-left:4px solid #2196f3;}
.menu-content {background:rgba(255,255,255,0.95); padding:30px; border-radius:15px; box-shadow:0 10px 30px rgba(0,0,0,0.2); margin-bottom:30px;}
.menu-grid {display:grid; grid-template-columns:repeat(auto-fit,minmax(350px,1fr)); gap:30px; margin-bottom:30px;}
.menu-item {background:#fff; border-radius:15px; padding:20px; box-shadow:0 5px 20px rgba(0,0,0,0.1); transition:transform 0.3s ease; display: flex; flex-direction: column;}
.menu-item:hover {transform:translateY(-5px);}
.menu-item .img-container { width: 100%; height: 250px; background-color: #f5f5f5; border-radius: 10px; margin-bottom: 15px; display: flex; align-items: center; justify-content: center; overflow: hidden; }
.menu-item img { width: 100%; height: 100%; object-fit: cover; border-radius: 10px; }
.menu-item-content { display: flex; flex-direction: column; flex-grow: 1; }
.menu-item h3 {color:#d32f2f; font-size:1.4em; margin-bottom:10px;}
.menu-item .description {color:#666; margin-bottom:15px; line-height:1.5; flex-grow: 1;}
.menu-item .price {font-size:1.5em; font-weight:bold; color:#2e7d32; margin-bottom:20px;}
.quantity-section {display:flex; align-items:center; gap:15px; margin-top: auto;}
.quantity-section label {font-weight:bold; color:#555;}
.quantity-controls {display:flex; align-items:center; gap:10px;}
.quantity-btn {background:#d32f2f; color:white; border:none; width:35px; height:35px; border-radius:50%; font-size:1.2em; cursor:pointer; transition:all 0.3s ease;}
.quantity-btn:hover {background:#b71c1c; transform:scale(1.1);}
.quantity-input {width:60px; padding:8px; text-align:center; border:2px solid #ddd; border-radius:5px; font-size:1.1em; font-weight:bold;}

/* Pickup Time Selection Section */
.pickup-time-section {
    background: #fff8e1;
    padding: 25px;
    border-radius: 15px;
    margin: 25px 0;
    border: 2px solid #ffb300;
}
.pickup-time-section h3 {
    color: #e65100;
    font-size: 1.45em;
    margin-bottom: 8px;
    text-align: center;
}
.pickup-time-section .pickup-desc {
    text-align: center;
    color: #666;
    margin-bottom: 18px;
    font-size: 0.98em;
}
.pickup-slots-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
    gap: 15px;
}
.pickup-slot-card {
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 18px 16px;
    background: #fff;
    border: 2px solid #e0e0e0;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.2s ease;
    text-align: center;
}
.pickup-slot-card:hover:not(.disabled) {
    border-color: #ff9800;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(255, 152, 0, 0.2);
}
.pickup-slot-card input[type="radio"] {
    position: absolute;
    opacity: 0;
    pointer-events: none;
}
.pickup-slot-card.selected {
    border-color: #d32f2f;
    background: #ffebee;
    box-shadow: 0 5px 15px rgba(211, 47, 47, 0.2);
}
.pickup-slot-card.disabled {
    opacity: 0.55;
    background: #f5f5f5;
    cursor: not-allowed;
}
.slot-card-label {
    font-size: 0.95em;
    font-weight: bold;
    color: #555;
    margin-bottom: 4px;
}
.slot-card-time {
    font-size: 1.65em;
    font-weight: 800;
    color: #d32f2f;
    margin-bottom: 6px;
}
.slot-card-capacity {
    font-size: 0.82em;
    font-weight: bold;
    padding: 3px 10px;
    border-radius: 999px;
    background: #e8f5e9;
    color: #2e7d32;
}
.pickup-slot-card.disabled .slot-card-capacity {
    background: #ffcdd2;
    color: #c62828;
}

.pizza-order-summary {background:#e8f5e8; padding:25px; border-radius:15px; margin:30px 0; border:2px solid #4caf50;}
.pizza-order-summary h3 {color:#2e7d32; font-size:1.5em; margin-bottom:20px; text-align:center;}
.order-item {display:flex; justify-content:space-between; padding:10px 0; border-bottom:1px solid #c8e6c9;}
.order-item:last-child {border-bottom:none; font-weight:bold; font-size:1.2em; color:#2e7d32;}
.submit-section {text-align:center; margin:30px 0;}
.btn {padding:15px 40px; background:#d32f2f; color:white; border:none; border-radius:25px; font-size:1.2em; font-weight:bold; cursor:pointer; transition:all 0.3s ease; text-decoration:none; display:inline-block; margin:10px;}
.btn:hover {background:#b71c1c; transform:translateY(-2px); box-shadow:0 8px 25px rgba(211,47,47,0.4);}
.btn.secondary {background:#ff9800;}
.btn.secondary:hover {background:#f57c00;}
.alert {padding:15px; margin-bottom:20px; border-radius:8px; font-weight:bold;}
.alert.error {background:#ffebee; color:#c62828; border:1px solid #e57373;}
.nav-links {text-align:center; margin:20px 0;}
.nav-links a {color:rgba(255,255,255,0.9); text-decoration:none; margin:0 15px; font-weight:bold;}
.nav-links a:hover {color:white; text-decoration:underline;}
@media(max-width:768px){.menu-grid{grid-template-columns:1fr;}.quantity-section{flex-direction:column; align-items:flex-start; gap:10px;}h1{font-size:2em;}}
</style>
</head>
<body>
<div class="container">
<header>
    <h1>🍕 <?= htmlspecialchars((string)__('menu_title')) ?></h1>
    <div class="user-info">
        <?= sprintf(htmlspecialchars((string)__('welcome_user')), htmlspecialchars($user_name)) ?>
        <?php if (!empty($config['event_date'])): ?>
            <?= htmlspecialchars((string)__('event_date_label')) ?> <strong><?= date('Y年n月j日', strtotime((string)$config['event_date'])) ?></strong>
        <?php endif; ?>
        <?php if (!empty($config['event_location'])): ?>
            | <?= htmlspecialchars((string)__('location_label')) ?> <strong><?= htmlspecialchars((string)$config['event_location']) ?></strong>
        <?php endif; ?>
    </div>

    <div class="nav-links">
        <a href="index.php"><?= htmlspecialchars((string)__('home')) ?></a>
        <a href="my_orders.php" class="btn secondary"><?= htmlspecialchars((string)__('order_history')) ?></a>
        <a href="logout.php"><?= htmlspecialchars((string)__('logout')) ?></a>
    </div>
</header>

<?php if (isset($error)): ?>
    <div class="alert error"><?= htmlspecialchars((string)$error) ?></div>
<?php endif; ?>

<div class="menu-content">
    <?php if (!empty($config['menu_content'])): ?>
        <div style="text-align: center; margin-bottom: 30px; font-size: 1.1em; color: #555;">
            <?= nl2br(htmlspecialchars((string)$config['menu_content'])) ?>
        </div>
    <?php endif; ?>

    <form method="POST" id="orderForm">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken()) ?>">

        <div class="menu-grid">
            <?php foreach ($menu_items as $item): ?>
            <?php $submittedQty = isset($_POST['quantity_' . $item['id']]) ? max(0, min(15, (int)$_POST['quantity_' . $item['id']])) : 0; ?>
            <div class="menu-item">
                <div class="img-container">
                    <?php
                    $image_path = (string)($item['image_path'] ?? '');
                    if ($image_path !== '' && file_exists(ROOT_PATH . DIRECTORY_SEPARATOR . $image_path)):
                    ?>
                        <img src="<?= htmlspecialchars($image_path) ?>" alt="<?= htmlspecialchars((string)$item['name']) ?>">
                    <?php else: ?>
                        <span style="font-size: 3em;">🍕</span>
                    <?php endif; ?>
                </div>

                <div class="menu-item-content">
                    <h3><?= htmlspecialchars((string)$item['name']) ?></h3>

                    <?php if (!empty($item['description'])): ?>
                        <div class="description"><?= htmlspecialchars((string)$item['description']) ?></div>
                    <?php endif; ?>

                    <div class="price"><?= htmlspecialchars(formatPrice($item['price'] ?? 0)) ?></div>
                </div>

                <div class="quantity-section">
                    <label><?= htmlspecialchars((string)__('quantity')) ?></label>
                    <div class="quantity-controls">
                        <button type="button" class="quantity-btn" onclick="changeQuantity(<?= (int)$item['id'] ?>, -1)">-</button>
                        <input type="number" class="quantity-input" id="quantity_<?= (int)$item['id'] ?>" name="quantity_<?= (int)$item['id'] ?>" value="<?= $submittedQty ?>" min="0" max="15" onchange="updateOrder()">
                        <button type="button" class="quantity-btn" onclick="changeQuantity(<?= (int)$item['id'] ?>, 1)">+</button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if (!empty($availableSlots)): ?>
        <div class="pickup-time-section" id="pickupTimeSection">
            <h3><?= htmlspecialchars((string)__('select_pickup_time_title')) ?></h3>
            <p class="pickup-desc"><?= htmlspecialchars((string)__('select_pickup_time_desc')) ?></p>
            <div class="pickup-slots-grid">
                <?php foreach ($availableSlots as $slot): ?>
                    <?php
                    $slotId = (int)$slot->getId();
                    $isFull = $slot->isFull();
                    $isSelected = !$isFull && ($selectedSlotId === $slotId);
                    $remPizzas = $slot->getRemainingPizzas();
                    $remOrders = $slot->getRemainingOrders();
                    ?>
                    <label class="pickup-slot-card <?= $isSelected ? 'selected' : '' ?> <?= $isFull ? 'disabled' : '' ?>"
                           id="slot_card_<?= $slotId ?>"
                           onclick="selectSlotCard(<?= $slotId ?>)">
                        <input type="radio"
                               name="pickup_slot_id"
                               id="pickup_slot_<?= $slotId ?>"
                               value="<?= $slotId ?>"
                               <?= $isSelected ? 'checked' : '' ?>
                               <?= $isFull ? 'disabled' : '' ?>
                               onchange="updateOrder()">
                        <?php if ($slot->getLabel() !== ''): ?>
                            <span class="slot-card-label"><?= htmlspecialchars($slot->getLabel()) ?></span>
                        <?php endif; ?>
                        <span class="slot-card-time"><?= htmlspecialchars($slot->getSlotTime()) ?></span>
                        <?php if ($isFull): ?>
                            <span class="slot-card-capacity"><?= htmlspecialchars((string)__('slot_full_badge')) ?></span>
                        <?php elseif ($remPizzas !== null): ?>
                            <span class="slot-card-capacity"><?= htmlspecialchars(sprintf((string)__('slot_remaining_pizzas'), $remPizzas)) ?></span>
                        <?php elseif ($remOrders !== null): ?>
                            <span class="slot-card-capacity"><?= htmlspecialchars(sprintf((string)__('slot_remaining_orders'), $remOrders)) ?></span>
                        <?php endif; ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="pizza-order-summary" id="orderSummary" style="display: none;">
            <h3>🛒 <?= htmlspecialchars((string)__('cart_contents')) ?></h3>
            <div id="orderItems"></div>
        </div>

        <div class="submit-section">
            <button type="submit" name="submit_order" class="btn" id="submitBtn" style="display: none;">
                🍕 <?= htmlspecialchars((string)__('submit_order')) ?>
            </button>
            <a href="index.php" class="btn secondary">← <?= htmlspecialchars((string)__('back_home')) ?></a>
            <a href="my_orders.php" class="btn secondary">📄 <?= htmlspecialchars((string)__('order_history')) ?></a>
        </div>
    </form>
</div>
</div>

<script>
const menuItems = <?= json_encode(array_column($menu_items, null, 'id')) ?>;
const pickupSlots = <?= json_encode(array_column($slotsForJs, null, 'id')) ?>;

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str ?? '';
    return div.innerHTML;
}

function selectSlotCard(slotId) {
    const radio = document.getElementById('pickup_slot_' + slotId);
    if (!radio || radio.disabled) {
        return;
    }
    radio.checked = true;
    document.querySelectorAll('.pickup-slot-card').forEach(card => {
        card.classList.remove('selected');
    });
    const card = document.getElementById('slot_card_' + slotId);
    if (card) {
        card.classList.add('selected');
    }
    updateOrder();
}

function changeQuantity(itemId, change) {
    const input = document.getElementById('quantity_' + itemId);
    let newValue = parseInt(input.value, 10) + change;
    newValue = Math.max(0, Math.min(15, newValue));
    input.value = newValue;
    updateOrder();
}

function getSelectedSlot() {
    const checked = document.querySelector('input[name="pickup_slot_id"]:checked');
    if (!checked) {
        return null;
    }
    return pickupSlots[checked.value] || null;
}

function updateOrder() {
    const orderItems = [];
    let totalAmount = 0;
    let totalPizzas = 0;

    Object.keys(menuItems).forEach(itemId => {
        const quantity = parseInt(document.getElementById('quantity_' + itemId).value, 10) || 0;
        if (quantity > 0) {
            const item = menuItems[itemId];
            const subtotal = quantity * parseFloat(item.price);
            orderItems.push({
                name: item.name,
                quantity: quantity,
                price: parseFloat(item.price),
                subtotal: subtotal
            });
            totalAmount += subtotal;
            totalPizzas += quantity;
        }
    });

    const orderSummary = document.getElementById('orderSummary');
    const orderItemsDiv = document.getElementById('orderItems');
    const submitBtn = document.getElementById('submitBtn');

    if (orderItems.length > 0) {
        let html = '';
        orderItems.forEach(item => {
            html += `<div class="order-item">
                <span>${escapeHtml(item.name)} x ${item.quantity}</span>
                <span>${new Intl.NumberFormat('ja-JP', { style: 'currency', currency: 'JPY' }).format(item.subtotal)}</span>
            </div>`;
        });

        const selectedSlot = getSelectedSlot();
        if (selectedSlot) {
            html += `<div class="order-item" style="color: #e65100; font-weight: bold;">
                <span>⏰ <?= htmlspecialchars((string)__('pickup_time_label'), ENT_QUOTES) ?></span>
                <span>${escapeHtml(selectedSlot.display_name)}</span>
            </div>`;
        }

        html += `<div class="order-item">
                <span><?= htmlspecialchars((string)__('total_amount'), ENT_QUOTES) ?></span>
                <span>${new Intl.NumberFormat('ja-JP', { style: 'currency', currency: 'JPY' }).format(totalAmount)}</span>
        </div>`;

        orderItemsDiv.innerHTML = html;
        orderSummary.style.display = 'block';
        submitBtn.style.display = 'inline-block';
    } else {
        orderSummary.style.display = 'none';
        submitBtn.style.display = 'none';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    Object.keys(menuItems).forEach(itemId => {
        const input = document.getElementById('quantity_' + itemId);
        input.addEventListener('input', updateOrder);
    });
    updateOrder();
});

document.getElementById('orderForm').addEventListener('submit', function(e) {
    let totalPizzas = 0;
    Object.keys(menuItems).forEach(itemId => {
        const quantity = parseInt(document.getElementById('quantity_' + itemId).value, 10) || 0;
        if (quantity > 0) {
            totalPizzas += quantity;
        }
    });

    if (totalPizzas === 0) {
        e.preventDefault();
        alert('注文するにはピザを1つ以上選択してください。');
        return;
    }

    const slotRadios = document.querySelectorAll('input[name="pickup_slot_id"]');
    if (slotRadios.length > 0) {
        const selectedSlot = getSelectedSlot();
        if (!selectedSlot) {
            e.preventDefault();
            alert('<?= htmlspecialchars((string)__('pickup_time_required'), ENT_QUOTES) ?>');
            return;
        }
        if (selectedSlot.remaining_pizzas !== null && totalPizzas > selectedSlot.remaining_pizzas) {
            e.preventDefault();
            alert(
                '<?= htmlspecialchars((string)__('slot_exceeds_remaining_pizzas'), ENT_QUOTES) ?>'
                    .replace('%d', totalPizzas)
                    .replace('%d', selectedSlot.remaining_pizzas)
            );
            return;
        }
    }
});
</script>
</body>
</html>