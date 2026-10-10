<?php
declare(strict_types=1);

// Forward to the root admin login page to prevent 404 errors from stale links, bookmarks, or relative redirects
$queryString = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
header('Location: ../admin_login.php' . $queryString);
exit;
