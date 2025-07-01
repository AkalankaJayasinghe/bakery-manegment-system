<?php
/**
 * Admin Dashboard Setup Script
 * 
 * This script checks and sets up the necessary database tables
 * for the admin dashboard to work properly.
 */

// Include database connection
include 'includes/db_connect.php';

$setup_messages = [];
$has_errors = false;

// Check if sales table exists
$check_sales = mysqli_query($conn, "SHOW TABLES LIKE 'sales'");
if (mysqli_num_rows($check_sales) == 0) {
    // Create sales table
    $create_sales = "CREATE TABLE sales (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        quantity INT NOT NULL,
        sale_date DATE NOT NULL,
        customer_name VARCHAR(255),
        total_amount DECIMAL(10, 2) NOT NULL,
        user_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    if (mysqli_query($conn, $create_sales)) {
        $setup_messages[] = "✓ Sales table created successfully";
    } else {
        $setup_messages[] = "✗ Error creating sales table: " . mysqli_error($conn);
        $has_errors = true;
    }
} else {
    $setup_messages[] = "✓ Sales table already exists";
}

// Check if users table exists
$check_users = mysqli_query($conn, "SHOW TABLES LIKE 'users'");
if (mysqli_num_rows($check_users) == 0) {
    // Create users table
    $create_users = "CREATE TABLE users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        role VARCHAR(20) NOT NULL DEFAULT 'cashier',
        email VARCHAR(100),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    if (mysqli_query($conn, $create_users)) {
        $setup_messages[] = "✓ Users table created successfully";
        
        // Create default admin user
        $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
        $create_admin = "INSERT INTO users (username, password, role, email) VALUES ('admin', '$admin_password', 'admin', 'admin@bakery.com')";
        
        if (mysqli_query($conn, $create_admin)) {
            $setup_messages[] = "✓ Default admin user created (username: admin, password: admin123)";
        } else {
            $setup_messages[] = "✗ Error creating admin user: " . mysqli_error($conn);
        }
    } else {
        $setup_messages[] = "✗ Error creating users table: " . mysqli_error($conn);
        $has_errors = true;
    }
} else {
    $setup_messages[] = "✓ Users table already exists";
}

// Check if products table exists
$check_products = mysqli_query($conn, "SHOW TABLES LIKE 'products'");
if (mysqli_num_rows($check_products) == 0) {
    // Create products table
    $create_products = "CREATE TABLE products (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        description TEXT,
        category_id INT,
        price DECIMAL(10, 2) NOT NULL,
        quantity INT DEFAULT 0,
        image VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    if (mysqli_query($conn, $create_products)) {
        $setup_messages[] = "✓ Products table created successfully";
    } else {
        $setup_messages[] = "✗ Error creating products table: " . mysqli_error($conn);
        $has_errors = true;
    }
} else {
    $setup_messages[] = "✓ Products table already exists";
    
    // Check if quantity column exists
    $check_quantity = mysqli_query($conn, "SHOW COLUMNS FROM products LIKE 'quantity'");
    if (mysqli_num_rows($check_quantity) == 0) {
        if (mysqli_query($conn, "ALTER TABLE products ADD COLUMN quantity INT DEFAULT 0")) {
            $setup_messages[] = "✓ Added quantity column to products table";
        } else {
            $setup_messages[] = "✗ Error adding quantity column: " . mysqli_error($conn);
        }
    }
}

// Check if categories table exists
$check_categories = mysqli_query($conn, "SHOW TABLES LIKE 'categories'");
if (mysqli_num_rows($check_categories) == 0) {
    // Create categories table
    $create_categories = "CREATE TABLE categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(50) NOT NULL,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    if (mysqli_query($conn, $create_categories)) {
        $setup_messages[] = "✓ Categories table created successfully";
        
        // Insert default categories
        $default_categories = [
            "('Bread', 'Various types of bread and loaves')",
            "('Pastries', 'Croissants, danishes, and pastries')",
            "('Cakes', 'Birthday and celebration cakes')",
            "('Cookies', 'Fresh baked cookies and biscuits')",
            "('Beverages', 'Coffee, tea, and drinks')"
        ];
        
        $insert_categories = "INSERT INTO categories (name, description) VALUES " . implode(', ', $default_categories);
        
        if (mysqli_query($conn, $insert_categories)) {
            $setup_messages[] = "✓ Default categories added";
        }
    } else {
        $setup_messages[] = "✗ Error creating categories table: " . mysqli_error($conn);
        $has_errors = true;
    }
} else {
    $setup_messages[] = "✓ Categories table already exists";
}

// Check if activity_logs table exists
$check_logs = mysqli_query($conn, "SHOW TABLES LIKE 'activity_logs'");
if (mysqli_num_rows($check_logs) == 0) {
    // Create activity_logs table
    $create_logs = "CREATE TABLE activity_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        action VARCHAR(255) NOT NULL,
        ip_address VARCHAR(50),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    if (mysqli_query($conn, $create_logs)) {
        $setup_messages[] = "✓ Activity logs table created successfully";
    } else {
        $setup_messages[] = "✗ Error creating activity logs table: " . mysqli_error($conn);
        $has_errors = true;
    }
} else {
    $setup_messages[] = "✓ Activity logs table already exists";
}

// Add some sample data if tables are empty
$sales_count = mysqli_query($conn, "SELECT COUNT(*) as count FROM sales");
if ($sales_count && mysqli_fetch_assoc($sales_count)['count'] == 0) {
    // Add sample products first
    $products_count = mysqli_query($conn, "SELECT COUNT(*) as count FROM products");
    if ($products_count && mysqli_fetch_assoc($products_count)['count'] == 0) {
        $sample_products = "INSERT INTO products (name, description, category_id, price, quantity) VALUES 
            ('White Bread', 'Fresh white bread loaf', 1, 2.50, 25),
            ('Chocolate Croissant', 'Buttery croissant with chocolate', 2, 3.00, 15),
            ('Birthday Cake (Small)', 'Small celebration cake', 3, 15.00, 5),
            ('Chocolate Chip Cookies', 'Classic chocolate chip cookies', 4, 1.50, 30),
            ('Coffee', 'Fresh brewed coffee', 5, 2.00, 50)";
        
        if (mysqli_query($conn, $sample_products)) {
            $setup_messages[] = "✓ Sample products added";
        }
    }
    
    // Add sample sales
    $sample_sales = "INSERT INTO sales (product_id, quantity, sale_date, customer_name, total_amount, user_id) VALUES 
        (1, 2, CURDATE(), 'John Doe', 5.00, 1),
        (2, 1, CURDATE(), 'Jane Smith', 3.00, 1),
        (4, 6, CURDATE(), '', 9.00, 1),
        (5, 3, DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'Bob Johnson', 6.00, 1),
        (3, 1, DATE_SUB(CURDATE(), INTERVAL 2 DAY), 'Alice Brown', 15.00, 1)";
    
    if (mysqli_query($conn, $sample_sales)) {
        $setup_messages[] = "✓ Sample sales data added";
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard Setup</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-cogs"></i> Admin Dashboard Setup</h3>
                    </div>
                    <div class="card-body">
                        <?php if (!$has_errors): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle"></i> Setup completed successfully!
                            </div>
                        <?php else: ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle"></i> Setup completed with some errors. Please check the details below.
                            </div>
                        <?php endif; ?>
                        
                        <h5>Setup Results:</h5>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($setup_messages as $message): ?>
                                <li class="list-group-item">
                                    <?php if (strpos($message, '✓') !== false): ?>
                                        <span class="text-success"><?php echo $message; ?></span>
                                    <?php else: ?>
                                        <span class="text-danger"><?php echo $message; ?></span>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        
                        <div class="mt-4">
                            <a href="index.php" class="btn btn-primary">
                                <i class="fas fa-tachometer-alt"></i> Go to Admin Dashboard
                            </a>
                            
                            <?php if (!$has_errors): ?>
                                <div class="alert alert-info mt-3">
                                    <strong>Default Login Credentials:</strong><br>
                                    Username: <code>admin</code><br>
                                    Password: <code>admin123</code>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
