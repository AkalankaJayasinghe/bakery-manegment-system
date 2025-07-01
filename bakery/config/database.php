<?php
/**
 * Database Configuration
 * 
 * This file contains the database connection settings
 * for the Bakery Management System
 */

// Database credentials
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'bakery');

// Create a database connection
function connectDB() {
    try {
        // Debug connection information
        error_log("Connecting to database: " . DB_NAME);
        
        // Create new connection with the constants defined above
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        // Check connection
        if ($conn->connect_error) {
            throw new Exception("Connection failed: " . $conn->connect_error);
        }
        
        return $conn;
    } catch (Exception $e) {
        // Log error
        error_log("Database connection error: " . $e->getMessage());
        
        // For setup process - try to create the database if it doesn't exist
        if (strpos($e->getMessage(), "Unknown database") !== false) {
            try {
                // Connect without specifying database
                $tempConn = new mysqli(DB_HOST, DB_USER, DB_PASS);
                
                // Create database
                if ($tempConn->query("CREATE DATABASE IF NOT EXISTS " . DB_NAME)) {
                    error_log("Database created: " . DB_NAME);
                    
                    // Connect to newly created database
                    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
                    if (!$conn->connect_error) {
                        return $conn;
                    }
                }
            } catch (Exception $setupError) {
                error_log("Failed to create database: " . $setupError->getMessage());
            }
        }
        
        // Show user-friendly error and redirect to setup
        echo '<div style="text-align:center; margin-top:50px; font-family:Arial, sans-serif;">';
        echo '<h2>Database Connection Error</h2>';
        echo '<p>Could not connect to the database. Please run the setup script.</p>';
        echo '<p><a href="' . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . 
             '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/../setup.php" 
             style="background:#4CAF50; color:white; padding:10px 20px; text-decoration:none; border-radius:4px;">
             Run Setup Script</a></p>';
        echo '</div>';
        exit;
    }
}

// IMPORTANT: Comment out or remove this function since it's already defined in functions.php
// function getDBConnection() {
//     static $conn = null;
//     
//     if ($conn === null) {
//         $conn = connectDB();
//     }
//     
//     return $conn;
// }
?>