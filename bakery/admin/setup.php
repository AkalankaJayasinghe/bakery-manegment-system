<?php
/**
 * This setup script is deprecated.
 * Redirecting to the main setup script.
 * 
 * The main setup script provides a better user interface and a single
 * source of truth for the database schema.
 */

// Get the base URL of the site
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$path = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
$baseUrl = "$protocol://$host" . str_replace('/admin', '', $path);

header("Location: " . $baseUrl . "/config/setup.php");
exit();
