<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../helpers.php';
requireAdmin();

$config = getSiteConfig();
$db = getDB();
$errors = [];
$success = '';

// Check flash message from redirect
$flash = getFlash();
if ($flash && ($flash['type'] ?? '') === 'success') {
    $success = (string)$flash['message'];
}

// Handle POST actions (add/update/delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = __('invalid_csrf_token') ?: 'Invalid CSRF token.';
    } elseif (isset($_POST['delete_admin'])) {
        $delete_id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

        $stmt = $db->query("SELECT COUNT(*) FROM admins");
        if ((int)$stmt->fetchColumn() <= 1) {
            $errors[] = "You cannot delete the only administrator.";
        } elseif ($delete_id === (int)($_SESSION['admin_id'] ?? 0)) {
            $errors[] = "You cannot delete your own account.";
        } elseif ($delete_id) {
            $stmt = $db->prepare("DELETE FROM admins WHERE id = ?");
            $stmt->execute([$delete_id]);
            setFlash('success', "Administrator deleted successfully.");
            redirect('admins.php');
        }
    } else {
        $username = trim((string)($_POST['username'] ?? ''));
        $email = trim((string)filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL));
        $password = (string)($_POST['password'] ?? '');
        $admin_id = isset($_POST['id']) && (int)$_POST['id'] > 0 ? (int)$_POST['id'] : null;

        // --- Validation ---
        if ($username === '' || $email === '') {
            $errors[] = "Username and email are required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Please enter a valid email address.";
        }

        // For new admins, password is required
        if ($admin_id === null && trim($password) === '') {
            $errors[] = "Password is required for new administrators.";
        }

        // Check for duplicate username
        $stmt = $db->prepare("SELECT id FROM admins WHERE LOWER(username) = LOWER(?) AND id != ?");
        $stmt->execute([$username, $admin_id ?? 0]);
        if ($stmt->fetch()) {
            $errors[] = "That username is already taken.";
        }

        if (empty($errors)) {
            if ($admin_id !== null) {
                // --- Update existing admin ---
                if ($password !== '') {
                    $password_hash = hashPassword($password);
                    $stmt = $db->prepare("UPDATE admins SET username = ?, email = ?, password_hash = ? WHERE id = ?");
                    $stmt->execute([$username, $email, $password_hash, $admin_id]);
                    setFlash('success', sprintf("Administrator '%s' updated successfully (new password has been saved).", $username));
                } else {
                    $stmt = $db->prepare("UPDATE admins SET username = ?, email = ? WHERE id = ?");
                    $stmt->execute([$username, $email, $admin_id]);
                    setFlash('success', sprintf("Administrator '%s' updated successfully (password was left blank and kept unchanged).", $username));
                }
                redirect('admins.php');
            } else {
                // --- Add new admin ---
                $password_hash = hashPassword($password);
                $stmt = $db->prepare("INSERT INTO admins (username, email, password_hash) VALUES (?, ?, ?)");
                $stmt->execute([$username, $email, $password_hash]);
                setFlash('success', sprintf("Administrator '%s' added successfully.", $username));
                redirect('admins.php');
            }
        }
    }
}

// Fetch all admins for the list
$stmt = $db->query("SELECT id, username, email, created_at FROM admins ORDER BY id ASC");
$admins = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch data for editing if an ID is in the URL
$edit_admin = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt = $db->prepare("SELECT id, username, email FROM admins WHERE id = ?");
    $stmt->execute([$edit_id]);
    $edit_admin = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$page_title = 'Manage Administrators';
require_once __DIR__ . '/includes/header.php';
?>
        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <?php foreach ($errors as $error): ?>
                    <p><?= htmlspecialchars((string)$error) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if ($success !== ''): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <div class="grid-container" style="display:grid; grid-template-columns: 2fr 1fr; gap: 30px;">
            <div class="section">
                <h2>Administrator List</h2>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($admins as $admin): ?>
                        <tr <?= ($edit_admin && (int)$edit_admin['id'] === (int)$admin['id']) ? 'style="background:#fff8e1;"' : '' ?>>
                            <td><strong><?= htmlspecialchars((string)$admin['username']) ?></strong></td>
                            <td><?= htmlspecialchars((string)$admin['email']) ?></td>
                            <td><?= date('M j, Y', strtotime((string)$admin['created_at'])) ?></td>
                            <td class="action-links">
                                <a href="admins.php?edit=<?= (int)$admin['id'] ?>" style="color:#f39c12;">Edit</a>
                                <form method="POST" action="admins.php" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this administrator?');">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken()) ?>">
                                    <input type="hidden" name="id" value="<?= (int)$admin['id'] ?>">
                                    <button type="submit" name="delete_admin" style="background:none;border:none;color:#e74c3c;cursor:pointer;font-weight:bold;padding:0;font-size:inherit;font-family:inherit;">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="section">
                <h2><?= $edit_admin ? 'Edit Administrator: ' . htmlspecialchars((string)$edit_admin['username']) : 'Add New Administrator' ?></h2>
                <form method="POST" action="admins.php<?= $edit_admin ? '?edit=' . (int)$edit_admin['id'] : '' ?>" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getCsrfToken()) ?>">
                    <?php if ($edit_admin): ?>
                        <input type="hidden" name="id" value="<?= (int)$edit_admin['id'] ?>">
                    <?php endif; ?>

                    <label for="admin_username_input">Username</label>
                    <input type="text"
                           id="admin_username_input"
                           name="username"
                           value="<?= htmlspecialchars((string)($edit_admin['username'] ?? '')) ?>"
                           autocomplete="off"
                           required>

                    <label for="admin_email_input">Email</label>
                    <input type="email"
                           id="admin_email_input"
                           name="email"
                           value="<?= htmlspecialchars((string)($edit_admin['email'] ?? '')) ?>"
                           autocomplete="off"
                           required>

                    <label for="admin_password_input"><?= $edit_admin ? 'New Password' : 'Password' ?></label>
                    <div style="display: flex; gap: 8px; align-items: center;">
                        <input type="password"
                               id="admin_password_input"
                               name="password"
                               autocomplete="new-password"
                               placeholder="<?= $edit_admin ? 'Enter new password to change...' : 'Enter password...' ?>"
                               <?= !$edit_admin ? 'required' : '' ?>>
                        <button type="button"
                                class="btn btn-secondary"
                                style="padding: 10px 14px; font-size: 0.85em;"
                                onclick="toggleAdminPasswordVisibility(this)">
                            Show
                        </button>
                    </div>
                    <?php if ($edit_admin): ?>
                        <p class="password-note" style="font-size: 0.88em; color: #64748b; margin-top: 6px;">
                            Leave blank only if you want to keep the current password.
                        </p>
                    <?php endif; ?>

                    <div style="margin-top: 20px; display: flex; gap: 10px; flex-wrap: wrap;">
                        <?php if ($edit_admin): ?>
                            <button type="submit" class="btn btn-save">Update Admin</button>
                            <a href="admins.php" class="btn btn-secondary">Cancel Edit</a>
                        <?php else: ?>
                            <button type="submit" class="btn btn-add">Add Admin</button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

<script>
function toggleAdminPasswordVisibility(btn) {
    const input = document.getElementById('admin_password_input');
    if (!input) return;
    if (input.type === 'password') {
        input.type = 'text';
        btn.textContent = 'Hide';
    } else {
        input.type = 'password';
        btn.textContent = 'Show';
    }
}
</script>
<?php
require_once __DIR__ . '/includes/footer.php';