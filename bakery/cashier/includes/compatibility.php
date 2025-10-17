<?php
/**
 * Compatibility functions for cashier interface
 * This file helps bridge different function naming conventions
 */

// Include main configuration files
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';

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

if (!defined('DATETIME_FORMAT')) {
    define('DATETIME_FORMAT', 'M j, Y g:i A');
}

// Invoice status constants
if (!defined('INVOICE_UNPAID')) {
    define('INVOICE_UNPAID', 0);
    define('INVOICE_PAID', 1);
    define('INVOICE_CANCELLED', 2);
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
            first_name VARCHAR(50) NOT NULL,
            last_name VARCHAR(50) NOT NULL,
            full_name VARCHAR(100) NOT NULL,
            role ENUM('admin', 'cashier') NOT NULL,
            email VARCHAR(100) DEFAULT '',
            phone VARCHAR(20),
            status TINYINT DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");
        
        // Create categories table
        $conn->query("CREATE TABLE IF NOT EXISTS categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            description TEXT,
            status TINYINT(1) DEFAULT 1,
            sort_order INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");
        
        // Create products table
        $conn->query("CREATE TABLE IF NOT EXISTS products (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            description TEXT,
            category_id INT,
            price DECIMAL(10,2) NOT NULL,
            cost_price DECIMAL(10,2) DEFAULT 0.00,
            quantity INT(11) DEFAULT 0,
            image VARCHAR(255),
            status TINYINT DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
        )");
        
        // Create sales table (previously invoices)
        $conn->query("CREATE TABLE IF NOT EXISTS sales (
            id INT AUTO_INCREMENT PRIMARY KEY,
            invoice_no VARCHAR(50) NOT NULL UNIQUE,
            cashier_id INT,
            customer_name VARCHAR(100) DEFAULT '',
            subtotal DECIMAL(10, 2) NOT NULL,
            tax_amount DECIMAL(10,2) DEFAULT 0.00,
            discount_amount DECIMAL(10,2) DEFAULT 0.00,
            total_amount DECIMAL(10, 2) NOT NULL,
            payment_method ENUM('cash', 'card', 'other') DEFAULT 'cash',
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (cashier_id) REFERENCES users(id) ON DELETE SET NULL
        )");
        
        // Create invoice_items table
        $conn->query("CREATE TABLE IF NOT EXISTS invoice_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            sale_id INT,
            product_id INT,
            quantity INT NOT NULL,
            unit_price DECIMAL(10, 2) NOT NULL,
            subtotal DECIMAL(10, 2) NOT NULL,
            FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
        )");
        
        // Create activity_logs table
        $conn->query("CREATE TABLE IF NOT EXISTS activity_logs (
            id INT(11) AUTO_INCREMENT PRIMARY KEY,
            user_id INT(11),
            action VARCHAR(50) NOT NULL,
            description TEXT,
            ip_address VARCHAR(50),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        )");

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
        
        // Insert sample products if none exist
        $result = $conn->query("SELECT COUNT(*) as count FROM products");
        $row = $result->fetch_assoc();
        if ($row['count'] == 0) {
            // Resolve category IDs by name to avoid hard-coded IDs causing FK failures
            $needed_categories = array('Bread', 'Pastries', 'Cakes', 'Cookies', 'Beverages');
            $escaped = array();
            foreach ($needed_categories as $cname) {
                $escaped[] = "'" . $conn->real_escape_string($cname) . "'";
            }
            $in_list = implode(',', $escaped);

            $cats = array();
            $res = $conn->query("SELECT id, name FROM categories WHERE name IN (" . $in_list . ")");
            if ($res) {
                while ($r = $res->fetch_assoc()) {
                    $cats[$r['name']] = (int)$r['id'];
                }
            }

            // Prepare product rows: name, description, category_name, price, stock_quantity
            $sample_products = array(
                array('White Bread', 'Fresh white bread loaf', 'Bread', 2.50, 25),
                array('Chocolate Croissant', 'Buttery croissant with chocolate filling', 'Pastries', 3.00, 15),
                array('Birthday Cake', 'Small celebration cake', 'Cakes', 15.00, 5),
                array('Chocolate Chip Cookies', 'Classic chocolate chip cookies (6 pack)', 'Cookies', 4.50, 30),
                array('Coffee', 'Fresh brewed coffee', 'Beverages', 2.00, 50)
            );

            foreach ($sample_products as $p) {
                $pname = $conn->real_escape_string($p[0]);
                $pdesc = $conn->real_escape_string($p[1]);
                $catName = $p[2];
                $catId = isset($cats[$catName]) ? (int)$cats[$catName] : null;
                $price = number_format((float)$p[3], 2, '.', '');
                $quantity = (int)$p[4];

                $cat_sql = is_null($catId) ? 'NULL' : $catId;
                $sql = "INSERT INTO products (name, description, category_id, price, quantity) VALUES ('" . $pname . "', '" . $pdesc . "', " . $cat_sql . ", " . $price . ", " . $quantity . ")";
                $conn->query($sql);
            }
        }
    }
}
?>
