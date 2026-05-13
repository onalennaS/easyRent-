<?php
/**
 * Logout Handler - L&T Connect
 * Place this file in: auth/logout.php
 */

require_once '../config/database.php';

// Initialize database connection and auth
$database = new Database();
$conn = $database->getConnection();
$auth = new UserAuth($conn);

// Perform logout
$auth->logout();

// Clear any additional session data if needed
if (session_status() === PHP_SESSION_ACTIVE) {
    session_unset();
    session_destroy();
}

// Clear session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Redirect to login page
header("Location: login.php");
exit();
?>