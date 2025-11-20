<?php
require_once 'config.php';

requireLogin();

$config = getSiteConfig();

// Check if order success data exists
if (!isset($_SESSION['order_success'])) {
    redirect('menu.php');
}

$order_data = $_SESSION['order_success'];
unset($_SESSION['order_success']); // Clear the session data

// Helper to safely output data
function safeHtml($value) {
    return htmlspecialchars($value ?? '');
}

// Check if email was sent
$emailSent = isset($order_data['email_sent']) ? (bool)$order_data['email_sent'] : false;
?>
<!DOCTYPE html>
<html lang="<?= $_SESSION['lang'] ?? 'ja' ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= __('order_success_title') ?> - <?= safeHtml($config['site_title'] ?? __('site_title')) ?></title>
<style>
/* Styles remain the same as previous version */
* { margin:0; padding:0; box-sizing:border-box; }
body {
    font-family:'Arial', sans-serif;
    background: linear-gradient(135deg, #4caf50, #8bc34a);
    min-height:100vh;
    display:flex;
    align-items:center;
    justify-content:center;
    padding:20px;
}
.container {
    background: rgba(255,255,255,0.95);
    padding:50px;
    border-radius:20px;
    box-shadow:0 20px 40px rgba(0,0,0,0.2);
    max-width:600px;
    width:100%;
    text-align:center;
}
.success-icon { font-size:5em; color:#4caf50; margin-bottom:20px; animation:bounce 2s infinite; }
@keyframes bounce {0%,20%,50%,80%,100%{transform:translateY(0);}40%{transform:translateY(-10px);}60%{transform:translateY(-5px);}}
h1 { color:#2e7d32; font-size:2.5em; margin-bottom:30px; }
.order-details { background:#e8f5e8; padding:30px; border-radius:15px; margin:30px 0; border:2px solid #4caf50; }
.order-number { font-size:2em; font-weight:bold; color:#2e7d32; margin-bottom:15px; }
.amount { font-size:1.5em; font-weight:bold; color:#d32f2f; margin-bottom:20px; }
.message { font-size:1.1em; line-height:1.6; color:#555; margin-bottom:30px; }
.highlight { background:#fff3e0; padding:20px; border-radius:10px; border-left:4px solid #ff9800; margin:20px 0; }
.highlight strong { color:#e65100; }
.btn { display:inline-block; padding:15px 30px; background:#d32f2f; color:white; text-decoration:none; border-radius:25px; font-weight:bold; font-size:1.1em; margin:10px; transition:all 0.3s ease; box-shadow:0 5px 15px rgba(211,47,47,0.4); }
.btn:hover { background:#b71c1c; transform:translateY(-2px); box-shadow:0 8px 25px rgba(211,47,47,0.6); }
.btn.secondary { background:#4caf50; box-shadow:0 5px 15px rgba(76,175,80,0.4); }
.btn.secondary:hover { background:#388e3c; box-shadow:0 8px 25px rgba(76,175,80,0.6); }
.pizza-animation { font-size:2em; margin:20px 0; animation:rotate 3s linear infinite; }
@keyframes rotate { from { transform:rotate(0deg); } to { transform:rotate(360deg); } }
@media(max-width:600px){ .container { padding:30px 20px; margin:10px; } h1 { font-size:2em; } .order-number { font-size:1.5em; } .btn { display:block; margin:10px 0; } }
</style>
</head>
<body>
<div class="container">
    <div class="success-icon">✅</div>

    <h1><?= __('thank_you_order') ?></h1>

    <div class="pizza-animation">🍕</div>

    <div class="order-details">
        <div class="order-number"><?= sprintf(__('order_number'), safeHtml($order_data['order_number'] ?? '')) ?></div>
        <div class="amount"><?= safeHtml(formatPrice($order_data['total_amount'] ?? 0)) ?></div>
    </div>

    <div class="message">
        <?php if ($emailSent): ?>
            <?= sprintf(__('email_sent_message'), safeHtml($order_data['user_email'] ?? 'N/A')) ?>
        <?php else: ?>
            <?= __('email_send_failed_message') ?>
        <?php endif; ?>
    </div>

    <div class="highlight">
        <strong><?= __('important_notice') ?></strong>
        <?= sprintf(__('pickup_instruction'), 
            !empty($config['event_date']) ? date('Y年n月j日', strtotime($config['event_date'])) : 'イベント当日',
            safeHtml(formatPrice($order_data['total_amount'] ?? 0))
        ) ?>
    </div>

    <?php if (!empty($config['event_location'])): ?>
    <div class="highlight">
        <strong><?= __('pickup_location_label') ?></strong><br>
        <?= nl2br(safeHtml($config['event_location'])) ?>
    </div>
    <?php endif; ?>

    <div style="margin-top:40px;">
        <a href="menu.php" class="btn secondary"><?= __('order_more_pizza') ?></a>
        <a href="my_orders.php" class="btn"><?= __('view_order_history') ?></a>
        <a href="index.php" class="btn"><?= __('back_home') ?></a>
    </div>

    <div style="margin-top:30px; color:#666; font-style:italic;">
        <?= __('waiting_message') ?>
    </div>
</div>
</body>
</html>