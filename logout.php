<?php
// config.php will handle starting the session, so we don't need it here.
require_once 'config.php';

// The session is already active from config.php, so this call is removed.
// session_start();  <-- DELETE THIS LINE

// Determine redirect target
$redirect_url = 'index.php';
if (!empty($_SESSION['is_admin']) || !empty($_SESSION['admin_id'])) {
    // This might be 'admin/admin_login.php' depending on your file structure.
    // Adjust if necessary.
    $redirect_url = 'admin_login.php'; 
}

// Clear session data from database if exists
if (isset($_SESSION['user_id']) || isset($_SESSION['admin_id'])) {
    try {
        $db = getDB();
        $session_id = session_id();
        $stmt = $db->prepare("DELETE FROM user_sessions WHERE id = ?");
        $stmt->execute([$session_id]);
    } catch (Exception $e) {
        // Continue with logout even if database cleanup fails
    }
}

// Clear all session data
$_SESSION = [];
session_destroy();

// Start a new, clean session for the redirect message.
// This call is CORRECT and should remain.
session_start();
$_SESSION['logout_message'] = 'You have been successfully logged out.';

// Redirect to appropriate page
redirect($redirect_url);