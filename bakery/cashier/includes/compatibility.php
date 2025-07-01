<?php
/**
 * Compatibility functions for cashier interface
 * This file helps bridge different function naming conventions
 */

// Function compatibility for different systems
if (!function_exists('formatCurrency')) {
    function formatCurrency($amount) {
        return '$' . number_format($amount, 2);
    }
}

// Safe session handling
if (!function_exists('ensureSession')) {
    function ensureSession() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
}

if (!function_exists('isLoggedIn')) {
    function isLoggedIn() {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
}

if (!function_exists('hasCashierPrivileges')) {
    function hasCashierPrivileges() {
        return isLoggedIn() && isset($_SESSION['role']) && 
               ($_SESSION['role'] === 'cashier' || $_SESSION['role'] === 'admin');
    }
}

if (!function_exists('hasAdminPrivileges')) {
    function hasAdminPrivileges() {
        return isLoggedIn() && isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    }
}

if (!function_exists('getDBConnection')) {
    function getDBConnection() {
        static $conn = null;
        
        if ($conn === null) {
            // First, connect without database to create it if needed
            $temp_conn = new mysqli(DB_HOST, DB_USER, DB_PASS);
            
            if ($temp_conn->connect_error) {
                die("Connection failed: " . $temp_conn->connect_error);
            }
            
            // Create database if it doesn't exist
            $temp_conn->query("CREATE DATABASE IF NOT EXISTS " . DB_NAME);
            $temp_conn->close();
            
            // Now connect to the specific database
            $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            
            if ($conn->connect_error) {
                die("Connection failed: " . $conn->connect_error);
            }
            
            $conn->set_charset("utf8");
            
            // Create basic tables if they don't exist
            createBasicTables($conn);
        }
        
        return $conn;
    }
}

if (!function_exists('connectDB')) {
    function connectDB() {
        return getDBConnection();
    }
}

// Define constants if not already defined
if (!defined('SITE_URL')) {
    define('SITE_URL', 'http://localhost/bakery');
}

if (!defined('SITE_NAME')) {
    define('SITE_NAME', 'Bakery Management System');
}

if (!defined('CASHIER_URL')) {
    define('CASHIER_URL', SITE_URL . '/cashier');
}

if (!defined('ASSETS_URL')) {
    define('ASSETS_URL', SITE_URL . '/assets');
}

if (!defined('DATETIME_FORMAT')) {
    define('DATETIME_FORMAT', 'M j, Y g:i A');
}

// Invoice status constants
if (!defined('INVOICE_UNPAID')) {
    define('INVOICE_UNPAID', 0);
    define('INVOICE_PAID', 1);
    define('INVOICE_CANCELLED', 2);
}

// Database configuration if not set
if (!defined('DB_HOST')) {
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_NAME', 'bakery');
}

// Common utility functions for cashier system

// Function to get username
if (!function_exists('getUserName')) {
    function getUserName($conn, $user_id) {
        $query = "SELECT CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) as full_name FROM users WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            return trim($row['full_name']) ?: 'Unknown User';
        }
        return 'Unknown User';
    }
}

// Function to generate invoice number
if (!function_exists('generateInvoiceNumber')) {
    function generateInvoiceNumber() {
        $prefix = 'INV';
        $date = date('Ymd');
        $random = mt_rand(1000, 9999);
        return $prefix . $date . $random;
    }
}

// Function to log activity (placeholder for future use)
if (!function_exists('logActivity')) {
    function logActivity($user_id, $action, $details = '') {
        // This can be implemented later for activity logging
        return true;
    }
}

// Function to create basic tables if they don't exist
if (!function_exists('createBasicTables')) {
    function createBasicTables($conn) {
        // Create users table
        $conn->query("CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            first_name VARCHAR(50) DEFAULT '',
            last_name VARCHAR(50) DEFAULT '',
            email VARCHAR(100) DEFAULT '',
            role ENUM('admin', 'cashier', 'manager') DEFAULT 'cashier',
            status TINYINT DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        
        // Create categories table
        $conn->query("CREATE TABLE IF NOT EXISTS categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            description TEXT,
            status TINYINT DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        
        // Create products table
        $conn->query("CREATE TABLE IF NOT EXISTS products (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            description TEXT,
            category_id INT,
            price DECIMAL(10, 2) NOT NULL,
            stock_quantity INT DEFAULT 0,
            image VARCHAR(255),
            status TINYINT DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
        )");
        
        // Create invoices table
        $conn->query("CREATE TABLE IF NOT EXISTS invoices (
            id INT AUTO_INCREMENT PRIMARY KEY,
            invoice_number VARCHAR(20) NOT NULL UNIQUE,
            customer_name VARCHAR(100) DEFAULT '',
            user_id INT NOT NULL,
            subtotal DECIMAL(10, 2) NOT NULL,
            tax_amount DECIMAL(10, 2) NOT NULL,
            discount_type ENUM('percentage', 'fixed') DEFAULT 'percentage',
            discount_value DECIMAL(10, 2) DEFAULT 0,
            total_amount DECIMAL(10, 2) NOT NULL,
            payment_method ENUM('cash', 'card', 'other') DEFAULT 'cash',
            amount_tendered DECIMAL(10, 2) DEFAULT 0,
            change_amount DECIMAL(10, 2) DEFAULT 0,
            status TINYINT DEFAULT 1,
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )");
        
        // Create invoice_items table
        $conn->query("CREATE TABLE IF NOT EXISTS invoice_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            invoice_id INT NOT NULL,
            product_id INT NOT NULL,
            quantity INT NOT NULL,
            unit_price DECIMAL(10, 2) NOT NULL,
            subtotal DECIMAL(10, 2) NOT NULL,
            FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
        )");
        
        // Insert default admin user if no users exist
        $result = $conn->query("SELECT COUNT(*) as count FROM users");
        $row = $result->fetch_assoc();
        if ($row['count'] == 0) {
            $conn->query("INSERT INTO users (username, password, first_name, last_name, email, role) 
                         VALUES ('admin', '$2y$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin', 'User', 'admin@bakery.com', 'admin')");
        }
        
        // Insert default categories if none exist
        $result = $conn->query("SELECT COUNT(*) as count FROM categories");
        $row = $result->fetch_assoc();
        if ($row['count'] == 0) {
            $conn->query("INSERT INTO categories (name, description) VALUES 
                         ('Bread', 'Fresh baked bread and loaves'),
                         ('Pastries', 'Croissants, danishes, and sweet pastries'),
                         ('Cakes', 'Birthday and celebration cakes'),
                         ('Cookies', 'Fresh baked cookies and biscuits'),
                         ('Beverages', 'Hot and cold beverages')");
        }
        
        // Check if stock_quantity column exists, if not add it or use quantity
        $columnExists = false;
        $result = $conn->query("SHOW COLUMNS FROM products LIKE 'stock_quantity'");
        if ($result && $result->num_rows > 0) {
            $columnExists = true;
        } else {
            // Check if quantity column exists and add stock_quantity
            $quantityExists = $conn->query("SHOW COLUMNS FROM products LIKE 'quantity'");
            if ($quantityExists && $quantityExists->num_rows > 0) {
                // Add stock_quantity column and copy data from quantity
                $conn->query("ALTER TABLE products ADD COLUMN stock_quantity INT DEFAULT 0");
                $conn->query("UPDATE products SET stock_quantity = quantity WHERE stock_quantity = 0");
            }
        }
        
        // Insert sample products if none exist
        $result = $conn->query("SELECT COUNT(*) as count FROM products");
        $row = $result->fetch_assoc();
        if ($row['count'] == 0) {
            $conn->query("INSERT INTO products (name, description, category_id, price, stock_quantity) VALUES 
                         ('White Bread', 'Fresh white bread loaf', 1, 2.50, 25),
                         ('Chocolate Croissant', 'Buttery croissant with chocolate filling', 2, 3.00, 15),
                         ('Birthday Cake', 'Small celebration cake', 3, 15.00, 5),
                         ('Chocolate Chip Cookies', 'Classic chocolate chip cookies (6 pack)', 4, 4.50, 30),
                         ('Coffee', 'Fresh brewed coffee', 5, 2.00, 50)");
        }
    }
}
?>
