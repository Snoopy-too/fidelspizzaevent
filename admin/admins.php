<?php
require_once '../config.php';
requireAdmin();

$config = getSiteConfig();
$db = getDB();
$errors = [];
$success = '';

// Handle POST actions (add/update/delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = __('invalid_csrf_token') ?: 'Invalid CSRF token.';
    } elseif (isset($_POST['delete_admin'])) {
        $delete_id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        
        // Safety check: Don't allow deleting the last admin
        $stmt = $db->query("SELECT COUNT(*) FROM admins");
        if ($stmt->fetchColumn() <= 1) {
            $errors[] = "You cannot delete the only administrator.";
        } elseif ($delete_id === (int)($_SESSION['admin_id'] ?? 0)) {
            $errors[] = "You cannot delete your own account.";
        } elseif ($delete_id) {
            $stmt = $db->prepare("DELETE FROM admins WHERE id = ?");
            $stmt->execute([$delete_id]);
            $success = "Administrator deleted successfully.";
        }
    } else {
        $username = sanitize($_POST['username'] ?? '');
        $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
        $password = $_POST['password'] ?? '';
        $admin_id = $_POST['id'] ?? null;

        // --- Validation ---
        if (empty($username) || empty($email)) {
            $errors[] = "Username and email are required.";
        }
        // For new admins, password is required
        if (!$admin_id && empty($password)) {
            $errors[] = "Password is required for new administrators.";
        }
        // Check for duplicate username
        $stmt = $db->prepare("SELECT id FROM admins WHERE username = ? AND id != ?");
        $stmt->execute([$username, $admin_id ?? 0]);
        if ($stmt->fetch()) {
            $errors[] = "That username is already taken.";
        }

        if (empty($errors)) {
            if ($admin_id) {
                // --- Update existing admin ---
                if (!empty($password)) {
                    $password_hash = hashPassword($password);
                    $stmt = $db->prepare("UPDATE admins SET username=?, email=?, password_hash=? WHERE id=?");
                    $stmt->execute([$username, $email, $password_hash, $admin_id]);
                } else {
                    $stmt = $db->prepare("UPDATE admins SET username=?, email=? WHERE id=?");
                    $stmt->execute([$username, $email, $admin_id]);
                }
                $success = "Administrator updated successfully.";
            } else {
                // --- Add new admin ---
                $password_hash = hashPassword($password);
                $stmt = $db->prepare("INSERT INTO admins (username, email, password_hash) VALUES (?, ?, ?)");
                $stmt->execute([$username, $email, $password_hash]);
                $success = "Administrator added successfully.";
            }
        }
    }
}

// Fetch all admins for the list
$stmt = $db->query("SELECT id, username, email, created_at FROM admins ORDER BY username");
$admins = $stmt->fetchAll();

// Fetch data for editing if an ID is in the URL
$edit_admin = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt = $db->prepare("SELECT id, username, email FROM admins WHERE id = ?");
    $stmt->execute([$edit_id]);
    $edit_admin = $stmt->fetch();
}

if (isset($_GET['deleted'])) {
    $success = "Administrator deleted successfully.";
}

$page_title = 'Manage Administrators';
require_once __DIR__ . '/includes/header.php';
?>
        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <?php foreach ($errors as $error) echo "<p>" . htmlspecialchars($error) . "</p>"; ?>
            </div>
        <?php endif; ?>
        <?php if ($success): ?>
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
                        <tr>
                            <td><?= htmlspecialchars($admin['username']) ?></td>
                            <td><?= htmlspecialchars($admin['email']) ?></td>
                            <td><?= date('M j, Y', strtotime($admin['created_at'])) ?></td>
                            <td class="action-links">
                                <a href="admins.php?edit=<?= $admin['id'] ?>" style="color:#f39c12;">Edit</a>
                                <form method="POST" action="admins.php" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this administrator?');">
                                    <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
                                    <input type="hidden" name="id" value="<?= $admin['id'] ?>">
                                    <button type="submit" name="delete_admin" style="background:none;border:none;color:#e74c3c;cursor:pointer;font-weight:bold;padding:0;font-size:inherit;font-family:inherit;">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="section">
                <h2><?= $edit_admin ? 'Edit Administrator' : 'Add New Administrator' ?></h2>
                <form method="POST" action="admins.php">
                    <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
                    <?php if ($edit_admin): ?>
                        <input type="hidden" name="id" value="<?= $edit_admin['id'] ?>">
                    <?php endif; ?>
                    
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" value="<?= htmlspecialchars($edit_admin['username'] ?? '') ?>" required>
                    
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="<?= htmlspecialchars($edit_admin['email'] ?? '') ?>" required>
                    
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" <?= !$edit_admin ? 'required' : '' ?>>
                    <?php if ($edit_admin): ?>
                        <p class="password-note">Leave blank to keep the current password.</p>
                    <?php endif; ?>

                    <?php if ($edit_admin): ?>
                        <button type="submit" class="btn btn-save">Update Admin</button>
                        <a href="admins.php" class="btn" style="background:#7f8c8d;">Cancel Edit</a>
                    <?php else: ?>
                        <button type="submit" class="btn btn-add">Add Admin</button>
                    <?php endif; ?>
                </form>
            </div>
        </div>
<?php
require_once __DIR__ . '/includes/footer.php';