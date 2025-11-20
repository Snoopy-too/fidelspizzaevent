<?php
require_once '../config.php';
requireAdmin();

$config = getSiteConfig();
$db = getDB();
$errors = [];
$success = '';

// Handle POST actions (add/update)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username']);
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];
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

// Handle delete action
if (isset($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];
    
    // Safety check: Don't allow deleting the last admin
    $stmt = $db->query("SELECT COUNT(*) FROM admins");
    if ($stmt->fetchColumn() <= 1) {
        $errors[] = "You cannot delete the only administrator.";
    } 
    // Safety check: Don't allow an admin to delete themselves
    elseif ($delete_id == $_SESSION['admin_id']) {
        $errors[] = "You cannot delete your own account.";
    } else {
        $stmt = $db->prepare("DELETE FROM admins WHERE id = ?");
        $stmt->execute([$delete_id]);
        header("Location: admins.php?deleted=1");
        exit;
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Administrators - <?= htmlspecialchars($config['site_title']) ?></title>
    <style>
        /* Consistent Admin Panel Styles */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Arial', sans-serif; background: #f5f5f5; color: #333; }
        .header { background: #2c3e50; color: white; padding: 20px 0; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header-content { max-width: 1200px; margin: 0 auto; padding: 0 20px; display: flex; justify-content: space-between; align-items: center; }
        .header h1 { font-size: 1.8em; }
        .nav-links a { color: white; text-decoration: none; margin-left: 20px; padding: 8px 16px; border-radius: 5px; transition: background 0.3s; }
        .nav-links a:hover { background: rgba(255,255,255,0.2); }
        .container { max-width: 1200px; margin: 30px auto; padding: 0 20px; }
        .grid-container { display: grid; grid-template-columns: 2fr 1fr; gap: 30px; }
        .section { background: white; border-radius: 10px; padding: 30px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .section h2 { color: #2c3e50; margin-bottom: 20px; font-size: 1.5em; border-bottom: 3px solid #3498db; padding-bottom: 10px; }
        
        /* Table styles */
        .table { width:100%; border-collapse:collapse; margin-top:20px; }
        .table th, .table td { padding:12px; border-bottom:1px solid #ddd; text-align:left; }
        .table th { background:#34495e; color:#fff; }
        .table tr:hover { background: #f8f9fa; }
        .action-links a { margin-right: 10px; text-decoration: none; font-weight: bold; }
        
        /* Form styles */
        label { display: block; margin-top: 15px; margin-bottom: 5px; font-weight: bold; color: #34495e; }
        input[type=text], input[type=email], input[type=password] { width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 8px; font-size: 1em; }
        .password-note { font-size: 0.8em; color: #777; margin-top: 5px; }
        .btn { display:inline-block; margin-top: 25px; padding: 12px 25px; color: #fff; border: none; border-radius: 8px; cursor: pointer; font-size: 1.1em; font-weight: bold; transition: background 0.3s; text-decoration: none; }
        .btn-save { background: #27ae60; }
        .btn-save:hover { background: #2ecc71; }
        .btn-add { background: #3498db; }
        .btn-add:hover { background: #2980b9; }

        /* Messages */
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 8px; font-weight: bold; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;}
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb;}

        @media (max-width: 992px) { .grid-container { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1>👨‍💼 Manage Administrators</h1>
            <div class="nav-links">
                <a href="dashboard.php">📊 Dashboard</a>
                <a href="orders.php">📋 Orders</a>
                <a href="users.php">👥 Users</a>
                <a href="admins.php">👨‍💼 Admins</a>
                <a href="settings.php">⚙️ Settings</a>
                <a href="../logout.php">🚪 Logout</a>
            </div>
        </div>
    </div>

    <div class="container">
        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <?php foreach ($errors as $error) echo "<p>" . htmlspecialchars($error) . "</p>"; ?>
            </div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <div class="grid-container">
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
                                <a href="admins.php?delete=<?= $admin['id'] ?>" style="color:#e74c3c;" onclick="return confirm('Are you sure you want to delete this administrator?')">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="section">
                <h2><?= $edit_admin ? 'Edit Administrator' : 'Add New Administrator' ?></h2>
                <form method="POST" action="admins.php">
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
    </div>
</body>
</html>