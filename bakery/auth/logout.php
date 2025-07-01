<?php
session_start();
require_once '../config/constants.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (isLoggedIn()) {
    // Log logout activity
    if (function_exists('logActivity')) {
        require_once '../config/database.php';
        logActivity('logout', "User logged out: {$_SESSION['username']}");
    }
    
    // Clear all session variables
    $_SESSION = array();
    
    // If it's desired to kill the session, also delete the session cookie.
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Finally, destroy the session
    session_destroy();
}

// Redirect to login page
header("Location: " . SITE_URL . "/auth/login.php");
exit;
?>