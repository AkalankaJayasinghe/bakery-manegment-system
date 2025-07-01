<?php
// Start the session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include necessary files
require_once '../config/database.php';  // Include this FIRST
require_once '../config/constants.php';
require_once '../includes/functions.php';

// Log the logout activity if user was logged in
if (isset($_SESSION['user_id'])) {
    $username = isset($_SESSION['username']) ? $_SESSION['username'] : 'Unknown user';
    logActivity('logout', "User logged out: $username");
}

// Destroy the session
session_unset();     // Remove all session variables
session_destroy();   // Destroy the session

// Redirect to the login page
header("Location: " . SITE_URL . "/auth/login.php?logout=success");
exit;
?>
