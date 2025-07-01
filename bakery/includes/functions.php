<?php
// Make sure we have access to database constants
if (!defined('DB_HOST')) {
    // If constants aren't defined, include them
    require_once __DIR__ . '/../config/database.php';
}

// Start the session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Input sanitization function
if (!function_exists('sanitizeInput')) {
    function sanitizeInput($data) {
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data);
        return $data;
    }
}

// Check if user is logged in
if (!function_exists('isLoggedIn')) {
    function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
}

// Check if user has admin privileges
if (!function_exists('hasAdminPrivileges')) {
    function hasAdminPrivileges() {
        return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    }
}

// Check if user has cashier privileges
if (!function_exists('hasCashierPrivileges')) {
    function hasCashierPrivileges() {
        return isset($_SESSION['role']) && ($_SESSION['role'] === 'cashier' || $_SESSION['role'] === 'admin');
    }
}

// Function to get database connection
if (!function_exists('getDBConnection')) {
    function getDBConnection() {
        // Check if constants are defined
        if (!defined('DB_HOST') || !defined('DB_USER') || !defined('DB_PASS') || !defined('DB_NAME')) {
            error_log("Database constants are not defined");
            return false;
        }
        
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            error_log("Connection failed: " . $conn->connect_error);
            return false;
        }
        return $conn;
    }
}

// Activity logging function
if (!function_exists('logActivity')) {
    function logActivity($action, $description = '') {
        if (!isset($_SESSION['user_id'])) {
            return false;
        }
        
        $user_id = $_SESSION['user_id'];
        $ip_address = $_SERVER['REMOTE_ADDR'];
        $action = trim($action);
        $description = trim($description);
        
        try {
            $conn = getDBConnection();
            if (!$conn) {
                return false;
            }
            
            // Create table if it doesn't exist
            $createTable = "CREATE TABLE IF NOT EXISTS activity_logs (
                id INT(11) AUTO_INCREMENT PRIMARY KEY,
                user_id INT(11) NOT NULL,
                action VARCHAR(50) NOT NULL,
                description TEXT,
                ip_address VARCHAR(50),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )";
            $conn->query($createTable);
            
            $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, description, ip_address) VALUES (?, ?, ?, ?)");
            
            if (!$stmt) {
                error_log("Prepare statement failed: " . $conn->error);
                return false;
            }
            
            $stmt->bind_param("isss", $user_id, $action, $description, $ip_address);
            $result = $stmt->execute();
            $stmt->close();
            
            return $result;
        } catch (Exception $e) {
            error_log("Log activity error: " . $e->getMessage());
            return false;
        }
    }
}

// Format currency
if (!function_exists('formatCurrency')) {
    function formatCurrency($amount) {
        return CURRENCY_SYMBOL . number_format($amount, DECIMAL_POINTS);
    }
}

// Get product by ID
if (!function_exists('getProductById')) {
    function getProductById($id) {
        $conn = getDBConnection();
        
        if (!$conn) {
            return null;
        }
        
        $stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            return $result->fetch_assoc();
        }
        
        return null;
    }
}

// Get category by ID
if (!function_exists('getCategoryById')) {
    function getCategoryById($id) {
        $conn = getDBConnection();
        
        if (!$conn) {
            return null;
        }
        
        $stmt = $conn->prepare("SELECT * FROM categories WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            return $result->fetch_assoc();
        }
        
        return null;
    }
}

// Generate unique invoice number
if (!function_exists('generateInvoiceNumber')) {
    function generateInvoiceNumber() {
        $prefix = 'INV';
        $date = date('Ymd');
        $random = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 4));
        
        return $prefix . '-' . $date . '-' . $random;
    }
}

// Get today's sales total
if (!function_exists('getTodaySales')) {
    function getTodaySales() {
        $conn = getDBConnection();
        
        if (!$conn) {
            return "0.00";
        }
        
        $today = date('Y-m-d');
        
        try {
            $query = "SELECT COALESCE(SUM(total_amount), 0) AS total FROM sales 
                      WHERE DATE(created_at) = ? AND payment_status = 'paid'";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("s", $today);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            
            return number_format($row['total'], 2);
        } catch (Exception $e) {
            error_log("Error in getTodaySales: " . $e->getMessage());
            return "0.00";
        }
    }
}

// Get total products
if (!function_exists('getTotalProducts')) {
    function getTotalProducts() {
        $conn = getDBConnection();
        
        if (!$conn) {
            return 0;
        }
        
        try {
            // Check if status column exists in products table
            $result = $conn->query("SHOW COLUMNS FROM products LIKE 'status'");
            
            if ($result->num_rows > 0) {
                $query = "SELECT COUNT(*) AS total FROM products WHERE status = 1";
            } else {
                $query = "SELECT COUNT(*) AS total FROM products";
            }
            
            $result = $conn->query($query);
            
            if ($result) {
                $row = $result->fetch_assoc();
                return $row['total'];
            }
            return 0;
        } catch (Exception $e) {
            error_log("Error in getTotalProducts: " . $e->getMessage());
            return 0;
        }
    }
}

// Get low stock count
if (!function_exists('getLowStockCount')) {
    function getLowStockCount() {
        $conn = getDBConnection();
        
        if (!$conn) {
            return 0;
        }
        
        try {
            // Check if status column exists in products table
            $result = $conn->query("SHOW COLUMNS FROM products LIKE 'status'");
            
            if ($result->num_rows > 0) {
                $query = "SELECT COUNT(*) AS total FROM products WHERE quantity < 10 AND status = 1";
            } else {
                $query = "SELECT COUNT(*) AS total FROM products WHERE quantity < 10";
            }
            
            $result = $conn->query($query);
            
            if ($result) {
                $row = $result->fetch_assoc();
                return $row['total'];
            }
            return 0;
        } catch (Exception $e) {
            error_log("Error in getLowStockCount: " . $e->getMessage());
            return 0;
        }
    }
}

// Get today's orders
if (!function_exists('getTodayOrders')) {
    function getTodayOrders() {
        $conn = getDBConnection();
        
        if (!$conn) {
            return 0;
        }
        
        $today = date('Y-m-d');
        
        try {
            $query = "SELECT COUNT(*) AS total FROM sales WHERE DATE(created_at) = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("s", $today);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            
            return $row['total'];
        } catch (Exception $e) {
            error_log("Error in getTodayOrders: " . $e->getMessage());
            return 0;
        }
    }
}

// Get recent sales
if (!function_exists('getRecentSales')) {
    function getRecentSales($limit = 5) {
        $conn = getDBConnection();
        
        if (!$conn) {
            return [];
        }
        
        try {
            // Check if sale_items table exists
            $result = $conn->query("SHOW TABLES LIKE 'sale_items'");
            
            if ($result->num_rows > 0) {
                $query = "SELECT s.id, s.invoice_no AS invoice_number, 
                          DATE_FORMAT(s.created_at, '%d-%m-%Y %H:%i') AS date, 
                          s.customer_name AS customer, 
                          COUNT(si.id) AS items, 
                          s.total_amount AS total, 
                          s.payment_status AS status 
                          FROM sales s 
                          LEFT JOIN sale_items si ON s.id = si.sale_id 
                          GROUP BY s.id 
                          ORDER BY s.created_at DESC LIMIT ?";
            } else {
                // Simplified query if sale_items table doesn't exist
                $query = "SELECT s.id, s.invoice_no AS invoice_number, 
                          DATE_FORMAT(s.created_at, '%d-%m-%Y %H:%i') AS date, 
                          s.customer_name AS customer, 
                          0 AS items, 
                          s.total_amount AS total, 
                          s.payment_status AS status 
                          FROM sales s 
                          ORDER BY s.created_at DESC LIMIT ?";
            }
            
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $limit);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $sales = [];
            while ($row = $result->fetch_assoc()) {
                $sales[] = $row;
            }
            
            return $sales;
        } catch (Exception $e) {
            error_log("Error in getRecentSales: " . $e->getMessage());
            return [];
        }
    }
}

// Get status badge
if (!function_exists('getStatusBadge')) {
    function getStatusBadge($status) {
        switch ($status) {
            case 'paid':
                return '<span class="badge bg-success">Paid</span>';
            case 'unpaid':
                return '<span class="badge bg-warning">Unpaid</span>';
            case 'cancelled':
                return '<span class="badge bg-danger">Cancelled</span>';
            default:
                return '<span class="badge bg-secondary">' . ucfirst($status) . '</span>';
        }
    }
}