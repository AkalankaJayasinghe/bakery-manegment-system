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
define('DB_NAME', 'bakery_system');

// Create a database connection
function connectDB() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    return $conn;
}

// Get database connection
function getDBConnection() {
    static $conn = null;
    
    if ($conn === null) {
        $conn = connectDB();
    }
    
    return $conn;
}

// You can also include a function to test the connection
function testDatabaseConnection() {
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if ($conn->connect_error) {
            return "Connection failed: " . $conn->connect_error;
        }
        
        $conn->close();
        return "Connection successful!";
    } catch (Exception $e) {
        return "Connection error: " . $e->getMessage();
    }
}
?>
