<?php
session_start();
require_once '../config/database.php';
require_once '../config/constants.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header("Location: " . SITE_URL . "/auth/login.php");
    exit;
}

// Check if user has admin privileges
if (!hasAdminPrivileges()) {
    header("Location: " . SITE_URL . "/index.php?error=unauthorized");
    exit;
}

// Database connection
$conn = getDBConnection();

// Function to get today's sales
function getTodaySales() {
    global $conn;
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

// Function to get total products
function getTotalProducts() {
    global $conn;
    
    try {
        $query = "SELECT COUNT(*) AS total FROM products WHERE status = 1";
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

// Function to get low stock count
function getLowStockCount() {
    global $conn;
    
    try {
        // Products where quantity is less than 10 (you can adjust this threshold)
        $query = "SELECT COUNT(*) AS total FROM products WHERE quantity < 10 AND status = 1";
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

// Function to get today's orders
function getTodayOrders() {
    global $conn;
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

// Function to get recent sales
function getRecentSales($limit = 5) {
    global $conn;
    
    try {
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

// Function to get status badge
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

// Page title
$pageTitle = "Admin Dashboard";

// Include header if you have one, if not, keep the HTML header here
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?php echo SITE_NAME; ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/style.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/admin.css">
    
    <style>
        /* Fallback styles in case admin.css is not loaded */
        body {
            font-family: 'Nunito', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f7f9fb;
        }
        
        .sidebar {
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 100;
            padding: 48px 0 0;
            box-shadow: inset -1px 0 0 rgba(0, 0, 0, .1);
            background-color: #fff;
        }
        
        .sidebar-sticky {
            position: relative;
            top: 0;
            height: calc(100vh - 48px);
            padding-top: .5rem;
            overflow-x: hidden;
            overflow-y: auto;
        }
        
        .dashboard-counter {
            position: relative;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            color: white;
            overflow: hidden;
            z-index: 1;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .dashboard-counter.sales { background: linear-gradient(45deg, #ff6b6b, #ff9a9e); }
        .dashboard-counter.products { background: linear-gradient(45deg, #4ecdc4, #a6e3e0); }
        .dashboard-counter.low-stock { background: linear-gradient(45deg, #ffbe0b, #ffd166); }
        .dashboard-counter.orders { background: linear-gradient(45deg, #66bb6a, #81c784); }
        
        .counter-number {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .counter-label {
            font-size: 1rem;
            font-weight: 600;
            opacity: 0.9;
        }
        
        .dashboard-icon {
            position: absolute;
            bottom: -10px;
            right: 10px;
            font-size: 3.5rem;
            opacity: 0.2;
        }
        
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
        }
        
        .card-header {
            background-color: white;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            font-weight: 600;
        }
        
        .table {
            margin-bottom: 0;
        }
        
        .table-responsive {
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Include sidebar -->
            <?php include_once '../includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Admin Dashboard</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-file-export"></i> Export
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-print"></i> Print
                            </button>
                        </div>
                        <div class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-calendar"></i> <?php echo date('F j, Y'); ?>
                        </div>
                    </div>
                </div>

                <!-- Dashboard Content -->
                <div class="row">
                    <div class="col-md-3">
                        <div class="dashboard-counter sales">
                            <div class="counter-number"><?php echo CURRENCY_SYMBOL; ?><?php echo getTodaySales(); ?></div>
                            <div class="counter-label">Total Sales Today</div>
                            <i class="fas fa-dollar-sign dashboard-icon"></i>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="dashboard-counter products">
                            <div class="counter-number"><?php echo getTotalProducts(); ?></div>
                            <div class="counter-label">Total Products</div>
                            <i class="fas fa-box dashboard-icon"></i>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="dashboard-counter low-stock">
                            <div class="counter-number"><?php echo getLowStockCount(); ?></div>
                            <div class="counter-label">Low Stock Items</div>
                            <i class="fas fa-exclamation-triangle dashboard-icon"></i>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="dashboard-counter orders">
                            <div class="counter-number"><?php echo getTodayOrders(); ?></div>
                            <div class="counter-label">Today's Orders</div>
                            <i class="fas fa-shopping-cart dashboard-icon"></i>
                        </div>
                    </div>
                </div>

                <!-- Recent Sales -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-shopping-cart me-2"></i> Recent Sales</h5>
                        <a href="sales.php" class="btn btn-sm btn-primary">View All</a>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Invoice #</th>
                                        <th>Date</th>
                                        <th>Customer</th>
                                        <th>Items</th>
                                        <th>Total</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $recentSales = getRecentSales(5);
                                    if (!empty($recentSales)) {
                                        foreach ($recentSales as $sale) {
                                            echo "<tr>";
                                            echo "<td>" . htmlspecialchars($sale['invoice_number']) . "</td>";
                                            echo "<td>" . htmlspecialchars($sale['date']) . "</td>";
                                            echo "<td>" . htmlspecialchars($sale['customer']) . "</td>";
                                            echo "<td>" . htmlspecialchars($sale['items']) . "</td>";
                                            echo "<td>" . CURRENCY_SYMBOL . number_format($sale['total'], 2) . "</td>";
                                            echo "<td>" . getStatusBadge($sale['status']) . "</td>";
                                            echo "<td>
                                                    <a href='view_sale.php?id=" . $sale['id'] . "' class='btn btn-sm btn-info me-1' title='View'>
                                                        <i class='fas fa-eye'></i>
                                                    </a>
                                                    <a href='print_invoice.php?id=" . $sale['id'] . "' class='btn btn-sm btn-secondary' title='Print'>
                                                        <i class='fas fa-print'></i>
                                                    </a>
                                                </td>";
                                            echo "</tr>";
                                        }
                                    } else {
                                        echo "<tr><td colspan='7' class='text-center'>No recent sales found</td></tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Low Stock Products -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-exclamation-triangle me-2"></i> Low Stock Products</h5>
                        <a href="products.php?filter=low_stock" class="btn btn-sm btn-warning">View All</a>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Category</th>
                                        <th>Current Stock</th>
                                        <th>Price</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    try {
                                        // Get low stock products
                                        $query = "SELECT p.id, p.name, p.quantity, p.price, c.name as category
                                                FROM products p 
                                                LEFT JOIN categories c ON p.category_id = c.id
                                                WHERE p.quantity < 10 AND p.status = 1
                                                ORDER BY p.quantity ASC
                                                LIMIT 5";
                                        $result = $conn->query($query);
                                        
                                        if ($result && $result->num_rows > 0) {
                                            while ($product = $result->fetch_assoc()) {
                                                echo "<tr>";
                                                echo "<td>" . htmlspecialchars($product['name']) . "</td>";
                                                echo "<td>" . htmlspecialchars($product['category'] ?? 'Uncategorized') . "</td>";
                                                echo "<td><span class='badge bg-danger'>" . htmlspecialchars($product['quantity']) . "</span></td>";
                                                echo "<td>" . CURRENCY_SYMBOL . number_format($product['price'], 2) . "</td>";
                                                echo "<td>
                                                        <a href='edit_product.php?id=" . $product['id'] . "' class='btn btn-sm btn-primary me-1' title='Edit'>
                                                            <i class='fas fa-edit'></i>
                                                        </a>
                                                        <a href='stock_adjustment.php?id=" . $product['id'] . "' class='btn btn-sm btn-success' title='Add Stock'>
                                                            <i class='fas fa-plus'></i>
                                                        </a>
                                                    </td>";
                                                echo "</tr>";
                                            }
                                        } else {
                                            echo "<tr><td colspan='5' class='text-center'>No low stock products found</td></tr>";
                                        }
                                    } catch (Exception $e) {
                                        echo "<tr><td colspan='5' class='text-center'>Error retrieving low stock products</td></tr>";
                                        error_log("Error retrieving low stock products: " . $e->getMessage());
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Font Awesome -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>

    <script>
        // Document ready
        document.addEventListener('DOMContentLoaded', function() {
            // Handle sidebar toggle on mobile
            const sidebarToggle = document.querySelector('.sidebar-toggle');
            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', function() {
                    document.querySelector('.sidebar').classList.toggle('show');
                });
            }
        });
    </script>
</body>
</html>