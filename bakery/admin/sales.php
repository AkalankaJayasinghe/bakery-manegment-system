<?php
// Include database connection
include 'includes/db_connect.php';
include 'includes/functions.php';
include 'includes/session.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Check if sales table exists and has necessary columns
$table_check_query = "SHOW TABLES LIKE 'sales'";
$table_check_result = mysqli_query($conn, $table_check_query);
$table_exists = mysqli_num_rows($table_check_result) > 0;

if (!$table_exists) {
    // Create sales table if it doesn't exist
    $create_table_query = "CREATE TABLE `sales` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `product_id` int(11) NOT NULL,
      `quantity` int(11) NOT NULL DEFAULT 1,
      `sale_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `customer_name` varchar(255) DEFAULT NULL,
      `total_amount` decimal(10,2) NOT NULL,
      `user_id` int(11) DEFAULT NULL,
      `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `product_id` (`product_id`),
      KEY `user_id` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    mysqli_query($conn, $create_table_query);
    
    // Log table creation
    logActivity($conn, 'Created sales table', $_SESSION['user_id']);
}

// Handle sales actions (add, edit, delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        // Add new sale
        if ($_POST['action'] === 'add') {
            $product_id = $_POST['product_id'];
            $quantity = $_POST['quantity'];
            $sale_date = $_POST['sale_date'];
            $customer_name = $_POST['customer_name'];
            $total_amount = $_POST['total_amount'];
            
            $query = "INSERT INTO sales (product_id, quantity, sale_date, customer_name, total_amount, user_id) 
                     VALUES ('$product_id', '$quantity', '$sale_date', '$customer_name', '$total_amount', '{$_SESSION['user_id']}')";
            
            if (mysqli_query($conn, $query)) {
                // Log the activity
                logActivity($conn, 'Added new sale', $_SESSION['user_id']);
                $success_message = "Sale added successfully!";
            } else {
                $error_message = "Error: " . mysqli_error($conn);
            }
        }
        
        // Delete sale
        if ($_POST['action'] === 'delete' && isset($_POST['sale_id'])) {
            $sale_id = $_POST['sale_id'];
            
            $query = "DELETE FROM sales WHERE id = '$sale_id'";
            
            if (mysqli_query($conn, $query)) {
                // Log the activity
                logActivity($conn, 'Deleted sale ID: ' . $sale_id, $_SESSION['user_id']);
                $success_message = "Sale deleted successfully!";
            } else {
                $error_message = "Error: " . mysqli_error($conn);
            }
        }
    }
}

// Get products for dropdown
$products_query = "SELECT * FROM products";
$products_result = mysqli_query($conn, $products_query);

// Safe query that checks for column existence
$column_check_query = "SHOW COLUMNS FROM sales LIKE 'product_id'";
$column_check_result = mysqli_query($conn, $column_check_query);
$product_id_exists = mysqli_num_rows($column_check_result) > 0;

if ($product_id_exists) {
    // Get all sales with product information
    $query = "SELECT s.*, p.name AS product_name, u.username 
              FROM sales s
              JOIN products p ON s.product_id = p.id
              LEFT JOIN users u ON s.user_id = u.id
              ORDER BY s.sale_date DESC";
    
    // Add search filter if provided
    if (isset($_GET['search']) && !empty($_GET['search'])) {
        $search = $_GET['search'];
        $query = "SELECT s.*, p.name AS product_name, u.username 
                  FROM sales s
                  JOIN products p ON s.product_id = p.id
                  LEFT JOIN users u ON s.user_id = u.id
                  WHERE p.name LIKE '%$search%' OR s.customer_name LIKE '%$search%'
                  ORDER BY s.sale_date DESC";
    }
    
    // Add date filter if provided
    if (isset($_GET['date_from']) && !empty($_GET['date_from']) && isset($_GET['date_to']) && !empty($_GET['date_to'])) {
        $date_from = $_GET['date_from'];
        $date_to = $_GET['date_to'];
        $query = "SELECT s.*, p.name AS product_name, u.username 
                  FROM sales s
                  JOIN products p ON s.product_id = p.id
                  LEFT JOIN users u ON s.user_id = u.id
                  WHERE s.sale_date BETWEEN '$date_from' AND '$date_to'
                  ORDER BY s.sale_date DESC";
    }
    
    $result = mysqli_query($conn, $query);
} else {
    // Use a simpler query if product_id doesn't exist
    $result = false;
    $error_message = "Sales table structure is not complete. Please run the database migration script.";
}

// Page title
$page_title = "Manage Sales";

// Include header
include 'includes/header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bakery Management System - <?php echo $page_title; ?></title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: Arial, sans-serif;
        }
        .sidebar {
            background-color: #ffffff;
            height: 100vh;
            position: fixed;
            padding: 0;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .sidebar .nav-link {
            color: #333;
            padding: 10px 15px;
            transition: all 0.3s;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background-color: #f1f8ff;
            color: #007bff;
        }
        .sidebar .nav-link i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        .top-nav {
            background-color: #007bff;
            color: white;
            padding: 10px 15px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .top-nav h4 {
            margin: 0;
        }
        .section-title {
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .card {
            margin-bottom: 20px;
            box-shadow: 0 0 10px rgba(0,0,0,0.05);
            border: none;
        }
        .card-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
            font-weight: bold;
        }
        .action-buttons .btn {
            margin-right: 5px;
        }
        .quick-actions {
            margin-top: 20px;
            border-top: 1px solid #dee2e6;
            padding-top: 15px;
        }
    </style>
</head>
<body>
    <!-- Top Navigation -->
    <div class="top-nav">
        <h4>Bakery Admin</h4>
        <div class="user-info">
            <i class="fas fa-user-circle"></i> <?php echo isset($_SESSION['username']) ? $_SESSION['username'] : 'User'; ?>
        </div>
    </div>

    <!-- Sidebar Navigation -->
    <div class="col-md-2 sidebar">
        <div class="p-3">
            <h5>Menu</h5>
        </div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link" href="index.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="products.php"><i class="fas fa-box"></i> Products</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="categories.php"><i class="fas fa-tags"></i> Categories</a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="sales.php"><i class="fas fa-shopping-cart"></i> Sales</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="users.php"><i class="fas fa-users"></i> Users</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="activity_logs.php"><i class="fas fa-history"></i> Activity Logs</a>
            </li>
            
            <div class="quick-actions">
                <h6 class="pl-3 mb-2">Quick Actions</h6>
                <li class="nav-item">
                    <a class="nav-link" href="cashier.php"><i class="fas fa-cash-register"></i> Cashier System</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="backup.php"><i class="fas fa-database"></i> Backup Data</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </li>
            </div>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <h1 class="section-title"><?php echo $page_title; ?></h1>
        
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger"><?php echo $error_message; ?></div>
        <?php endif; ?>
        
        <?php if (!$table_exists || !$product_id_exists): ?>
            <div class="alert alert-warning">
                <h4>Database Update Required</h4>
                <p>Your sales table needs to be updated. Please copy and run the following SQL in your database:</p>
                <pre>
CREATE TABLE IF NOT EXISTS `sales` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `sale_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `customer_name` varchar(255) DEFAULT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
                </pre>
                <p>After running the SQL, refresh this page.</p>
            </div>
        <?php else: ?>
            <div class="row">
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            Add New Sale
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="add">
                                
                                <div class="form-group">
                                    <label>Product</label>
                                    <select name="product_id" class="form-control" required>
                                        <option value="">Select Product</option>
                                        <?php if ($products_result && mysqli_num_rows($products_result) > 0): ?>
                                            <?php while ($product = mysqli_fetch_assoc($products_result)): ?>
                                                <option value="<?php echo $product['id']; ?>"><?php echo $product['name']; ?> - $<?php echo isset($product['price']) ? $product['price'] : '0.00'; ?></option>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <option value="" disabled>No products available</option>
                                        <?php endif; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label>Quantity</label>
                                    <input type="number" name="quantity" class="form-control" required min="1">
                                </div>
                                
                                <div class="form-group">
                                    <label>Sale Date</label>
                                    <input type="date" name="sale_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label>Customer Name</label>
                                    <input type="text" name="customer_name" class="form-control">
                                </div>
                                
                                <div class="form-group">
                                    <label>Total Amount</label>
                                    <input type="number" name="total_amount" class="form-control" required step="0.01">
                                </div>
                                
                                <button type="submit" class="btn btn-primary btn-block">Add Sale</button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span>Sales List</span>
                            <div>
                                <button class="btn btn-sm btn-outline-primary" data-toggle="collapse" data-target="#filterPanel">
                                    <i class="fas fa-filter"></i> Filter
                                </button>
                                <a href="reports.php" class="btn btn-sm btn-outline-info">
                                    <i class="fas fa-chart-line"></i> Reports
                                </a>
                            </div>
                        </div>
                        
                        <!-- Filter Panel -->
                        <div class="collapse" id="filterPanel">
                            <div class="card-body border-bottom">
                                <form method="GET" class="row">
                                    <div class="col-md-4">
                                        <input type="text" name="search" class="form-control form-control-sm" placeholder="Search by product or customer" value="<?php echo isset($_GET['search']) ? $_GET['search'] : ''; ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <input type="date" name="date_from" class="form-control form-control-sm" placeholder="Date From" value="<?php echo isset($_GET['date_from']) ? $_GET['date_from'] : ''; ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <input type="date" name="date_to" class="form-control form-control-sm" placeholder="Date To" value="<?php echo isset($_GET['date_to']) ? $_GET['date_to'] : ''; ?>">
                                    </div>
                                    <div class="col-md-2">
                                        <button type="submit" class="btn btn-sm btn-primary">Filter</button>
                                        <a href="sales.php" class="btn btn-sm btn-secondary">Reset</a>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Product</th>
                                            <th>Quantity</th>
                                            <th>Sale Date</th>
                                            <th>Customer</th>
                                            <th>Amount</th>
                                            <th>Sold By</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($result && mysqli_num_rows($result) > 0): ?>
                                            <?php $counter = 1; while ($row = mysqli_fetch_assoc($result)): ?>
                                                <tr>
                                                    <td><?php echo $counter++; ?></td>
                                                    <td><?php echo $row['product_name']; ?></td>
                                                    <td><?php echo $row['quantity']; ?></td>
                                                    <td><?php echo date('Y-m-d', strtotime($row['sale_date'])); ?></td>
                                                    <td><?php echo $row['customer_name']; ?></td>
                                                    <td>$<?php echo number_format($row['total_amount'], 2); ?></td>
                                                    <td><?php echo $row['username']; ?></td>
                                                    <td class="action-buttons">
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="action" value="delete">
                                                            <input type="hidden" name="sale_id" value="<?php echo $row['id']; ?>">
                                                            <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this sale?')">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="8" class="text-center py-3">No sales found.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>