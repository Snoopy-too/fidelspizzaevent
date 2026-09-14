<?php
declare(strict_types=1);

require_once '../config.php';

requireLogin();
requireAdmin();

$db = getDB();

// Fetch current config
$stmt = $db->prepare("SELECT * FROM site_config WHERE id = 1");
$stmt->execute();
$config = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', __('invalid_csrf_token') ?: 'Invalid CSRF token.');
        redirect('settings.php');
    }

    $stmt = $db->prepare("UPDATE site_config SET site_title=?, event_location=?, event_date=?, registration_code=?, landing_content=?, menu_content=?, admin_email=? WHERE id=1");
    $stmt->execute([
        $_POST['site_title'] ?? '',
        $_POST['event_location'] ?? '',
        $_POST['event_date'] ?? '',
        $_POST['registration_code'] ?? '',
        $_POST['landing_content'] ?? '',
        $_POST['menu_content'] ?? '',
        $_POST['admin_email'] ?? ''
    ]);

    setFlash('success', __('settings_updated') ?: 'Settings updated successfully.');
    // Refresh config
    header("Location: settings.php?success=1");
    exit;
}

$page_title = __('settings_title');
require_once __DIR__ . '/includes/header.php';
?>
        <div class="section">
            <h2><?= __('settings_title') ?></h2>
            
            <?php if (isset($_GET['success'])): ?>
                <div class="success"><?= __('settings_updated') ?></div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
                <label for="site_title"><?= __('site_title_label') ?></label>
                <input type="text" name="site_title" id="site_title" value="<?= htmlspecialchars($config['site_title'] ?? '') ?>" required>

                <label for="event_location"><?= __('event_location_label') ?></label>
                <textarea name="event_location" id="event_location"><?= htmlspecialchars($config['event_location'] ?? '') ?></textarea>

                <label for="event_date"><?= __('event_date') ?></label>
                <input type="date" name="event_date" id="event_date" value="<?= htmlspecialchars($config['event_date'] ?? '') ?>">

                <label for="registration_code"><?= __('registration_code') ?></label>
                <input type="text" name="registration_code" id="registration_code" value="<?= htmlspecialchars($config['registration_code'] ?? '') ?>">

                <label for="landing_content"><?= __('landing_page_content') ?></label>
                <textarea name="landing_content" id="landing_content"><?= htmlspecialchars($config['landing_content'] ?? '') ?></textarea>

                <label for="menu_content"><?= __('menu_page_content') ?></label>
                <textarea name="menu_content" id="menu_content"><?= htmlspecialchars($config['menu_content'] ?? '') ?></textarea>
                
                <label for="admin_email"><?= __('admin_email_notification') ?></label>
                <input type="email" name="admin_email" id="admin_email" value="<?= htmlspecialchars($config['admin_email'] ?? '') ?>">

                <button type="submit"><?= __('save_settings') ?></button>
            </form>
        </div>
<?php
require_once __DIR__ . '/includes/footer.php';