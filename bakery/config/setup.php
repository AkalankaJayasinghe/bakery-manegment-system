<?php
// Turn on error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Database credentials from config file
require_once 'config/database.php';

// Page title
$pageTitle = "Bakery Management System - Setup";

// Process form submission
$message = '';
$success = false;
$error = false;
$dbCreated = false;
$tablesCreated = false;
$adminCreated = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['setup'])) {
    try {
        // Connect to MySQL without selecting a database
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS);
        
        if ($conn->connect_error) {
            throw new Exception("Connection failed: " . $conn->connect_error);
        }
        
        // Create the database
        $sql = "CREATE DATABASE IF NOT EXISTS " . DB_NAME;
        if ($conn->query($sql)) {
            $dbCreated = true;
        } else {
            throw new Exception("Error creating database: " . $conn->error);
        }
        
        // Select the database
        $conn->select_db(DB_NAME);
        
        // Create users table
        $sql = "CREATE TABLE IF NOT EXISTS users (
            id INT(11) AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            first_name VARCHAR(50) NOT NULL,
            last_name VARCHAR(50) NOT NULL,
            full_name VARCHAR(100) NOT NULL,
            role ENUM('admin', 'cashier') NOT NULL,
            email VARCHAR(100),
            phone VARCHAR(20),
            status TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        
        if ($conn->query($sql) === false) {
            throw new Exception("Error creating users table: " . $conn->error);
        }
        
        // Create categories table
        $sql = "CREATE TABLE IF NOT EXISTS categories (
            id INT(11) AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL UNIQUE,
            description TEXT,
            status TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        
        if ($conn->query($sql) === false) {
            throw new Exception("Error creating categories table: " . $conn->error);
        }
        
        // Create products table
        $sql = "CREATE TABLE IF NOT EXISTS products (
            id INT(11) AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            description TEXT,
            category_id INT(11),
            price DECIMAL(10,2) NOT NULL,
            cost_price DECIMAL(10,2) DEFAULT 0.00,
            quantity INT(11) DEFAULT 0,
            image VARCHAR(255),
            status TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
        )";
        
        if ($conn->query($sql) === false) {
            throw new Exception("Error creating products table: " . $conn->error);
        }
        
        // Create sales table
        $sql = "CREATE TABLE IF NOT EXISTS sales (
            id INT(11) AUTO_INCREMENT PRIMARY KEY,
            invoice_no VARCHAR(50) NOT NULL UNIQUE,
            cashier_id INT(11),
            customer_name VARCHAR(100),
            subtotal DECIMAL(10,2) NOT NULL,
            tax_amount DECIMAL(10,2) DEFAULT 0.00,
            discount_amount DECIMAL(10,2) DEFAULT 0.00,
            total_amount DECIMAL(10,2) NOT NULL,
            payment_method ENUM('cash', 'card', 'other') DEFAULT 'cash',
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (cashier_id) REFERENCES users(id) ON DELETE SET NULL
        )";
        
        if ($conn->query($sql) === false) {
            throw new Exception("Error creating sales table: " . $conn->error);
        }
        
        // Create sale_items table
        $sql = "CREATE TABLE IF NOT EXISTS sale_items (
            id INT(11) AUTO_INCREMENT PRIMARY KEY,
            sale_id INT(11) NOT NULL,
            product_id INT(11),
            quantity INT(11) NOT NULL,
            unit_price DECIMAL(10,2) NOT NULL,
            subtotal DECIMAL(10,2) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
        )";
        
        if ($conn->query($sql) === false) {
            throw new Exception("Error creating sale_items table: " . $conn->error);
        }
        
        // Create activity_logs table
        $sql = "CREATE TABLE IF NOT EXISTS activity_logs (
            id INT(11) AUTO_INCREMENT PRIMARY KEY,
            user_id INT(11),
            action VARCHAR(50) NOT NULL,
            description TEXT,
            ip_address VARCHAR(50),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        )";
        
        if ($conn->query($sql) === false) {
            throw new Exception("Error creating activity_logs table: " . $conn->error);
        }
        
        $tablesCreated = true;
        
        // Insert default admin user if no users exist
        $result = $conn->query("SELECT COUNT(*) AS count FROM users");
        $row = $result->fetch_assoc();
        
        if ($row['count'] == 0) {
            // Generate password hash
            $password = password_hash('admin123', PASSWORD_DEFAULT);
            
            $sql = "INSERT INTO users (username, password, first_name, last_name, full_name, role, email, phone, status) 
                    VALUES ('admin', ?, 'Admin', 'User', 'Admin User', 'admin', 'admin@bakery.com', '1234567890', 1)";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $password);
            
            if ($stmt->execute() === false) {
                throw new Exception("Error creating default admin user: " . $stmt->error);
            }
            
            $adminCreated = true;
        }
        
        // Insert sample categories
        $sampleCategories = [
            ['Bread', 'Various types of bread'],
            ['Pastries', 'Delicious pastries and desserts'],
            ['Cakes', 'Birthday and special occasion cakes'],
            ['Cookies', 'Fresh baked cookies']
        ];
        
        $categoryInserted = 0;
        foreach ($sampleCategories as $category) {
            $stmt = $conn->prepare("INSERT IGNORE INTO categories (name, description) VALUES (?, ?)");
            $stmt->bind_param("ss", $category[0], $category[1]);
            if ($stmt->execute()) {
                $categoryInserted++;
            }
        }
        
        $success = true;
        $message = "Setup completed successfully! Database and tables created.";
        if ($adminCreated) {
            $message .= "<br><strong>Default admin account:</strong> <br>Username: admin <br>Password: admin123";
        }
        if ($categoryInserted > 0) {
            $message .= "<br><strong>Sample categories added:</strong> " . $categoryInserted;
        }
        
    } catch (Exception $e) {
        $error = true;
        $message = "Setup failed: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Nunito', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .setup-container {
            max-width: 800px;
            margin: 50px auto;
            padding: 30px;
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
        }
        .setup-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .setup-logo {
            font-size: 3rem;
            margin-bottom: 20px;
            color: #ff6b6b;
        }
        .setup-title {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 10px;
        }
        .setup-subtitle {
            color: #6c757d;
            font-size: 16px;
        }
        .setup-steps {
            margin: 30px 0;
        }
        .step {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        .step-number {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            background-color: #ff6b6b;
            color: white;
            border-radius: 50%;
            font-weight: bold;
            margin-right: 15px;
        }
        .step-content {
            flex: 1;
        }
        .step-title {
            font-weight: 600;
            margin-bottom: 5px;
        }
        .step-description {
            color: #6c757d;
            font-size: 14px;
        }
        .success-message {
            background-color: #d1e7dd;
            color: #0f5132;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .error-message {
            background-color: #f8d7da;
            color: #842029;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .btn-setup {
            background-color: #ff6b6b;
            border: none;
            padding: 10px 20px;
            font-weight: 600;
        }
        .btn-setup:hover {
            background-color: #ff5252;
        }
        .step.completed .step-number {
            background-color: #198754;
        }
        .step.failed .step-number {
            background-color: #dc3545;
        }
    </style>
</head>
<body>
    <div class="setup-container">
        <div class="setup-header">
            <div class="setup-logo">
                <i class="fas fa-bread-slice"></i>
            </div>
            <h1 class="setup-title">Bakery Management System Setup</h1>
            <p class="setup-subtitle">Configure your system database and initial settings</p>
        </div>
        
        <?php if (!empty($message)): ?>
            <div class="<?php echo $success ? 'success-message' : 'error-message'; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <div class="setup-steps">
            <div class="step <?php echo $dbCreated ? 'completed' : ($error ? 'failed' : ''); ?>">
                <div class="step-number">1</div>
                <div class="step-content">
                    <div class="step-title">Create Database</div>
                    <div class="step-description">Create the '<?php echo DB_NAME; ?>' database for storing all application data.</div>
                </div>
                <?php if ($dbCreated): ?>
                    <i class="fas fa-check-circle text-success"></i>
                <?php endif; ?>
            </div>
            
            <div class="step <?php echo $tablesCreated ? 'completed' : ($error && $dbCreated ? 'failed' : ''); ?>">
                <div class="step-number">2</div>
                <div class="step-content">
                    <div class="step-title">Create Database Tables</div>
                    <div class="step-description">Create necessary tables including users, products, categories, sales, and more.</div>
                </div>
                <?php if ($tablesCreated): ?>
                    <i class="fas fa-check-circle text-success"></i>
                <?php endif; ?>
            </div>
            
            <div class="step <?php echo $adminCreated ? 'completed' : ''; ?>">
                <div class="step-number">3</div>
                <div class="step-content">
                    <div class="step-title">Create Admin Account</div>
                    <div class="step-description">Create default administrator account for system management.</div>
                </div>
                <?php if ($adminCreated): ?>
                    <i class="fas fa-check-circle text-success"></i>
                <?php endif; ?>
            </div>
            
            <div class="step">
                <div class="step-number">4</div>
                <div class="step-content">
                    <div class="step-title">Add Sample Data</div>
                    <div class="step-description">Add sample categories to help you get started.</div>
                </div>
                <?php if (isset($categoryInserted) && $categoryInserted > 0): ?>
                    <i class="fas fa-check-circle text-success"></i>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if (!$success): ?>
            <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <div class="d-grid">
                    <button type="submit" name="setup" class="btn btn-primary btn-setup btn-lg">
                        <i class="fas fa-cog me-2"></i>Start Setup Process
                    </button>
                </div>
            </form>
        <?php else: ?>
            <div class="d-grid gap-2">
                <a href="index.php" class="btn btn-success btn-lg">
                    <i class="fas fa-home me-2"></i>Go to Homepage
                </a>
                <a href="auth/login.php" class="btn btn-primary btn-lg">
                    <i class="fas fa-sign-in-alt me-2"></i>Login to Dashboard
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>