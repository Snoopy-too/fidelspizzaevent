<?php
session_start();

// Remove all session variables
$_SESSION = [];

// Destroy the session
session_destroy();

// Redirect to admin login page
header("Location: ../admin_login.php");
exit;
