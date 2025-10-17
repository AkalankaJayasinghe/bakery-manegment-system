<?php
// Include database connection
include 'includes/db_connect.php';
include 'includes/functions.php';
include 'includes/session.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ' . SITE_URL . '/auth/login.php');
    exit();
}

// Handle AJAX requests for dashboard data
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    if (isset($_GET['action']) && $_GET['action'] == 'get_dashboard_data') {
        
        $response = [
            'status' => 'success',
            'data' => [
                'salesStats' => getSalesStats(),
                'inventoryStats' => getInventoryStats(),
                'recentSales' => getRecentSales(),
                'lowStockProducts' => getLowStockProducts(),
                'monthlySales' => getMonthlySales(),
                'topSellingProducts' => getTopSellingProducts(),
                'categoryDistribution' => getCategoryDistribution(),
                'dailySalesTrend' => getDailySalesTrend()
            ]
        ];
        
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
}

// Page title
$pageTitle = "Admin Dashboard";

// Get today's date
$today = date('Y-m-d');
$currentMonth = date('Y-m');
$currentYear = date('Y');

// Function to get sales statistics
function getSalesStats() {
    global $conn, $today, $currentMonth, $currentYear;
    
    $stats = [
        'today' => 0,
        'month' => 0,
        'year' => 0,
        'total' => 0,
        'orders_today' => 0,
        'orders_month' => 0
    ];
    
    try {
        // Today's sales - Using prepared statements to prevent SQL injection
        $query = "SELECT COALESCE(SUM(total_amount), 0) AS total FROM sales WHERE DATE(sale_date) = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "s", $today);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $stats['today'] = mysqli_fetch_assoc($result)['total'];
        
        // This month's sales
        $query = "SELECT COALESCE(SUM(total_amount), 0) AS total FROM sales WHERE DATE_FORMAT(sale_date, '%Y-%m') = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "s", $currentMonth);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $stats['month'] = mysqli_fetch_assoc($result)['total'];
        
        // This year's sales
        $query = "SELECT COALESCE(SUM(total_amount), 0) AS total FROM sales WHERE YEAR(sale_date) = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "s", $currentYear);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $stats['year'] = mysqli_fetch_assoc($result)['total'];
        
        // Total sales all time
        $query = "SELECT COALESCE(SUM(total_amount), 0) AS total FROM sales";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $stats['total'] = mysqli_fetch_assoc($result)['total'];
        
        // Order counts
        $query = "SELECT COUNT(*) AS count FROM sales WHERE DATE(sale_date) = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "s", $today);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $stats['orders_today'] = mysqli_fetch_assoc($result)['count'];
        
        $query = "SELECT COUNT(*) AS count FROM sales WHERE DATE_FORMAT(sale_date, '%Y-%m') = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "s", $currentMonth);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $stats['orders_month'] = mysqli_fetch_assoc($result)['count'];
        
    } catch (Exception $e) {
        error_log("Error getting sales stats: " . $e->getMessage());
    }
    
    return $stats;
}

// Get inventory statistics
function getInventoryStats() {
    global $conn;
    
    $stats = [
        'total_products' => 0,
        'low_stock' => 0,
        'out_of_stock' => 0,
        'categories' => 0
    ];
    
    try {
        // Total active products
        $query = "SELECT COUNT(*) AS count FROM products WHERE status = 1";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($result) {
            $stats['total_products'] = mysqli_fetch_assoc($result)['count'];
        }
        
        // Low stock products
        $query = "SELECT COUNT(*) AS count FROM products WHERE quantity > 0 AND quantity <= 10";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($result) {
            $stats['low_stock'] = mysqli_fetch_assoc($result)['count'];
        }
        
        // Out of stock products
        $query = "SELECT COUNT(*) AS count FROM products WHERE quantity = 0";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($result) {
            $stats['out_of_stock'] = mysqli_fetch_assoc($result)['count'];
        }
        
        // Active categories
        $query = "SELECT COUNT(*) AS count FROM categories";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($result) {
            $stats['categories'] = mysqli_fetch_assoc($result)['count'];
        }
        
    } catch (Exception $e) {
        error_log("Error getting inventory stats: " . $e->getMessage());
    }
    
    return $stats;
}

// Get recent sales
function getRecentSales($limit = 5) {
    global $conn;
    
    $sales = [];
    
    try {
        $query = "SELECT s.id, s.sale_date, s.customer_name, s.total_amount, s.quantity,
                  p.name AS product_name, u.username AS cashier 
                  FROM sales s 
                  LEFT JOIN products p ON s.product_id = p.id
                  LEFT JOIN users u ON s.user_id = u.id
                  ORDER BY s.sale_date DESC
                  LIMIT ?";
                  
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "i", $limit);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $sales[] = $row;
            }
        }
    } catch (Exception $e) {
        error_log("Error getting recent sales: " . $e->getMessage());
    }
    
    return $sales;
}

// Get low stock products
function getLowStockProducts($limit = 5) {
    global $conn;
    
    $products = [];
    
    try {
        $query = "SELECT p.id, p.name, p.price, 
                  COALESCE(p.quantity, 0) AS quantity,
                  c.name AS category_name
                  FROM products p 
                  LEFT JOIN categories c ON p.category_id = c.id
                  WHERE COALESCE(p.quantity, 0) > 0 AND COALESCE(p.quantity, 0) <= 10
                  ORDER BY COALESCE(p.quantity, 0) ASC
                  LIMIT ?";
                  
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "i", $limit);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $products[] = $row;
            }
        }
    } catch (Exception $e) {
        error_log("Error getting low stock products: " . $e->getMessage());
    }
    
    return $products;
}

// Get monthly sales for chart
function getMonthlySales() {
    global $conn;
    
    $monthlySales = [];
    
    try {
        $year = date('Y');
        
        for ($month = 1; $month <= 12; $month++) {
            $monthPadded = str_pad($month, 2, '0', STR_PAD_LEFT);
            $yearMonth = $year . '-' . $monthPadded;
            
            $query = "SELECT COALESCE(SUM(total_amount), 0) AS total FROM sales WHERE DATE_FORMAT(sale_date, '%Y-%m') = ?";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, "s", $yearMonth);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $total = 0;
            if ($result) {
                $total = mysqli_fetch_assoc($result)['total'];
            }
            
            $monthlySales[$month - 1] = (float)$total;
        }
    } catch (Exception $e) {
        error_log("Error getting monthly sales: " . $e->getMessage());
    }
    
    return $monthlySales;
}

// Get top selling products
function getTopSellingProducts($limit = 5) {
    global $conn;
    
    $products = [];
    
    try {
        $query = "SELECT p.id, p.name, SUM(s.quantity) AS total_sold, 
                  SUM(s.total_amount) AS revenue, c.name AS category_name
                  FROM sales s
                  JOIN products p ON s.product_id = p.id
                  LEFT JOIN categories c ON p.category_id = c.id
                  GROUP BY p.id
                  ORDER BY total_sold DESC
                  LIMIT ?";
                  
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "i", $limit);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $products[] = $row;
            }
        }
    } catch (Exception $e) {
        error_log("Error getting top selling products: " . $e->getMessage());
    }
    
    return $products;
}

// Get daily sales trend for the last 7 days
function getDailySalesTrend() {
    global $conn;
    $dailySalesTrend = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $query = "SELECT COALESCE(SUM(total_amount), 0) AS total FROM sales WHERE DATE(sale_date) = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "s", $date);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $total = $result ? (float)mysqli_fetch_assoc($result)['total'] : 0;
        $dailySalesTrend[] = ['date' => date('D', strtotime($date)), 'amount' => $total];
    }
    return $dailySalesTrend;
}

// Get category distribution for chart
function getCategoryDistribution() {
    global $conn;
    
    $categories = [];
    $counts = [];
    
    try {
        $query = "SELECT c.name, COUNT(p.id) AS product_count
                  FROM categories c
                  LEFT JOIN products p ON c.id = p.category_id
                  GROUP BY c.id
                  ORDER BY product_count DESC
                  LIMIT 5";
        
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $categories[] = $row['name'];
                $counts[] = (int)$row['product_count'];
            }
        }
    } catch (Exception $e) {
        error_log("Error getting category distribution: " . $e->getMessage());
    }
    
    return [
        'categories' => $categories,
        'counts' => $counts
    ];
}

// Include header
include 'includes/header.php';
?>

<!-- Custom CSS for Admin Dashboard -->
<style>
:root {
    --primary-color: #4e73df;
    --success-color: #1cc88a;
    --warning-color: #f6c23e;
    --danger-color: #e74a3b;
    --info-color: #36b9cc;
    --dark-color: #5a5c69;
    --body-bg: #f8f9fc;
    --card-bg: #fff;
    --text-color: #525f7f;
    --text-muted: #858796;
    --border-color: #e3e6f0;
    --transition-speed: 0.3s;
    --card-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
    --hover-shadow: 0 0.5rem 2rem 0 rgba(58, 59, 69, 0.25);
    --navbar-bg: #fff;
    --sidebar-bg: #4e73df;
    --sidebar-text: #fff;
}

.dark-mode {
    --primary-color: #4e73df;
    --success-color: #1cc88a;
    --warning-color: #f6c23e;
    --danger-color: #e74a3b;
    --info-color: #36b9cc;
    --dark-color: #5a5c69;
    --body-bg: #1a1c24;
    --card-bg: #283046;
    --text-color: #b4b7bd;
    --text-muted: #676d7d;
    --border-color: #3b4253;
    --card-shadow: 0 0.15rem 1.75rem 0 rgba(0, 0, 0, 0.2);
    --hover-shadow: 0 0.5rem 2rem 0 rgba(0, 0, 0, 0.3);
    --navbar-bg: #283046;
    --sidebar-bg: #283046;
    --sidebar-text: #b4b7bd;
}

body {
    background-color: var(--body-bg);
    color: var(--text-color);
    transition: background-color var(--transition-speed), color var(--transition-speed);
}

.main-content {
    padding: 24px;
    min-height: calc(100vh - 56px);
    transition: all var(--transition-speed);
}

.navbar {
    background-color: var(--navbar-bg);
    box-shadow: 0 2px 4px rgba(0,0,0,0.08);
    transition: background-color var(--transition-speed);
}

.card {
    border: none;
    box-shadow: var(--card-shadow);
    margin-bottom: 24px;
    border-radius: 0.5rem;
    overflow: hidden;
    background-color: var(--card-bg);
    transition: all var(--transition-speed);
}

.card:hover {
    transform: translateY(-5px);
    box-shadow: var(--hover-shadow);
}

.card-header {
    background-color: rgba(0, 0, 0, 0.03);
    border-bottom: 1px solid var(--border-color);
    font-weight: bold;
    display: flex;
    align-items: center;
    padding: 1rem 1.25rem;
}

.stat-card {
    border-radius: 0.5rem;
    overflow: hidden;
}

.stat-card .icon-area {
    font-size: 2rem;
    opacity: 0.6;
    transition: transform 0.3s ease;
}

.stat-card:hover .icon-area {
    transform: scale(1.2);
}

.stat-card .card-body {
    position: relative;
    z-index: 1;
    overflow: hidden;
}

.stat-card .card-body::before {
    content: "";
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(
        circle,
        rgba(255, 255, 255, 0.3) 0%,
        rgba(255, 255, 255, 0) 70%
    );
    opacity: 0;
    z-index: -1;
    transform: rotate(30deg);
    transition: opacity 0.5s;
}

.stat-card:hover .card-body::before {
    opacity: 1;
}

.text-white-50 {
    font-size: 0.8rem;
    font-weight: 600;
    letter-spacing: 0.5px;
}

.quick-action-btn {
    transition: all 0.3s;
    border: none;
    overflow: hidden;
    position: relative;
    z-index: 1;
}

.quick-action-btn::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: rgba(255, 255, 255, 0.2);
    transition: left 0.4s ease;
    z-index: -1;
}

.quick-action-btn:hover::before {
    left: 0;
}

.quick-action-btn .fa-2x {
    transition: transform 0.3s;
}

.quick-action-btn:hover .fa-2x {
    transform: scale(1.2);
}

table {
    color: var(--text-color) !important;
}

.table-hover tbody tr:hover {
    background-color: rgba(0, 0, 0, 0.04);
}

.dark-mode .table-hover tbody tr:hover {
    background-color: rgba(255, 255, 255, 0.04);
}

/* Loading Animation */
.loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 9999;
    transition: opacity 0.5s;
}

.spinner {
    width: 40px;
    height: 40px;
    border: 4px solid rgba(255, 255, 255, 0.3);
    border-radius: 50%;
    border-top-color: #fff;
    animation: spin 1s ease-in-out infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

.fade-in {
    opacity: 0;
    animation: fadeIn 0.5s forwards;
}

@keyframes fadeIn {
    to { opacity: 1; }
}

/* Floating action button for theme toggle */
.theme-toggle {
    position: fixed;
    bottom: 30px;
    right: 30px;
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: var(--primary-color);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
    z-index: 100;
    transition: all 0.3s;
}

.theme-toggle:hover {
    transform: scale(1.1);
    box-shadow: 0 6px 12px rgba(0, 0, 0, 0.3);
}

/* Badge styling */
.badge {
    padding: 0.4rem 0.6rem;
    font-size: 0.75rem;
    font-weight: 600;
    border-radius: 0.5rem;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .card {
        margin-bottom: 15px;
    }
    
    .main-content {
        padding: 15px;
    }
    
    .theme-toggle {
        bottom: 15px;
        right: 15px;
        width: 40px;
        height: 40px;
    }
}

.notification-counter {
    position: absolute;
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: var(--danger-color);
    color: white;
    font-size: 0.7rem;
    font-weight: bold;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    top: -8px;
    right: -8px;
}

/* Animated pulse for notification */
@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.1); }
    100% { transform: scale(1); }
}

.pulse {
    animation: pulse 2s infinite;
}
</style>

<!-- Loading Overlay -->
<div class="loading-overlay" id="loadingOverlay">
    <div class="spinner"></div>
</div>

<div class="main-content">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom fade-in">
        <h1 class="h2">Admin Dashboard</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <div class="btn-group me-2">
                <button type="button" class="btn btn-sm btn-outline-secondary" id="refreshDataBtn">
                    <i class="fas fa-sync-alt"></i> Refresh Data
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="exportBtn">
                    <i class="fas fa-file-export"></i> Export
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="printBtn">
                    <i class="fas fa-print"></i> Print
                </button>
            </div>
            <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                <i class="fas fa-calendar"></i> <?php echo date('F Y'); ?>
            </button>
            <div class="dropdown-menu dropdown-menu-end">
                <a class="dropdown-item" href="#">This Month</a>
                <a class="dropdown-item" href="#">Last Month</a>
                <a class="dropdown-item" href="#">This Quarter</a>
                <a class="dropdown-item" href="#">This Year</a>
            </div>
        </div>
    </div>
    
    <!-- Sales Overview -->
    <div class="row fade-in" style="animation-delay: 0.1s;">
        <div class="col-md-3">
            <div class="card text-white bg-primary mb-4 stat-card">
                <div class="card-body d-flex align-items-center">
                    <div>
                        <div class="text-uppercase text-white-50" id="todaySalesLabel">Today's Sales</div>
                        <div class="h4 mb-0 counter" id="todaySalesValue">$0.00</div>
                        <div class="small" id="todaySalesOrders">0 orders</div>
                    </div>
                    <div class="ms-auto icon-area">
                        <i class="fas fa-shopping-cart fa-3x"></i>
                    </div>
                </div>
                <div class="progress" style="height: 4px;">
                    <div class="progress-bar bg-light" style="width: 75%"></div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card text-white bg-success mb-4 stat-card">
                <div class="card-body d-flex align-items-center">
                    <div>
                        <div class="text-uppercase text-white-50" id="monthlySalesLabel">Monthly Sales</div>
                        <div class="h4 mb-0 counter" id="monthlySalesValue">$0.00</div>
                        <div class="small" id="monthlySalesOrders">0 orders</div>
                    </div>
                    <div class="ms-auto icon-area">
                        <i class="fas fa-calendar-check fa-3x"></i>
                    </div>
                </div>
                <div class="progress" style="height: 4px;">
                    <div class="progress-bar bg-light" style="width: 65%"></div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card text-white bg-warning mb-4 stat-card">
                <div class="card-body d-flex align-items-center">
                    <div>
                        <div class="text-uppercase text-white-50" id="inventoryStatusLabel">Inventory Status</div>
                        <div class="h4 mb-0 counter" id="inventoryStatusValue">0 Products</div>
                        <div class="small" id="inventoryStatusLow">0 Low Stock</div>
                    </div>
                    <div class="ms-auto icon-area">
                        <i class="fas fa-boxes fa-3x"></i>
                    </div>
                </div>
                <div class="progress" style="height: 4px;">
                    <div class="progress-bar bg-light" style="width: 50%"></div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card text-white bg-danger mb-4 stat-card">
                <div class="card-body d-flex align-items-center">
                    <div>
                        <div class="text-uppercase text-white-50" id="outOfStockLabel">Out of Stock</div>
                        <div class="h4 mb-0 counter pulse" id="outOfStockValue">0 Products</div>
                        <div class="small" id="outOfStockSubtext">Needs attention</div>
                    </div>
                    <div class="ms-auto icon-area">
                        <i class="fas fa-exclamation-triangle fa-3x"></i>
                    </div>
                </div>
                <div class="progress" style="height: 4px;">
                    <div class="progress-bar bg-light" style="width: 35%"></div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Charts Row -->
    <div class="row fade-in" style="animation-delay: 0.2s;">
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-chart-line me-1"></i>
                    Sales Overview - <?php echo date('Y'); ?>
                    <div class="float-end">
                        <div class="btn-group btn-group-sm" role="group">
                            <button type="button" class="btn btn-outline-secondary active" id="viewYearly">Yearly</button>
                            <button type="button" class="btn btn-outline-secondary" id="viewWeekly">Weekly</button>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="chart-container" style="position: relative; height:300px;">
                        <canvas id="salesChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-chart-pie me-1"></i>
                    Product Categories
                </div>
                <div class="card-body">
                    <div class="chart-container" style="position: relative; height:300px;">
                        <canvas id="categoryChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Tables Row -->
    <div class="row fade-in" style="animation-delay: 0.3s;">
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <i class="fas fa-table me-1"></i>
                        Recent Sales
                    </div>
                    <a href="sales.php" class="btn btn-sm btn-primary">View All</a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover table-sm">
                            <thead>
                                <tr>
                                    <th>Sale ID</th>
                                    <th>Date</th>
                                    <th>Customer</th>
                                    <th>Product</th>
                                    <th>Amount</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Recent sales will be loaded via AJAX -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <i class="fas fa-exclamation-circle me-1"></i>
                        Low Stock Products 
                        <span class="badge bg-danger ms-2" id="lowStockCount">0</span>
                    </div>
                    <a href="products.php?filter=low_stock" class="btn btn-sm btn-warning">View All</a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover table-sm">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Category</th>
                                    <th>Price</th>
                                    <th>Stock</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Low stock products will be loaded via AJAX -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Third Row -->
    <div class="row fade-in" style="animation-delay: 0.4s;">
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-trophy me-1"></i>
                    Top Selling Products
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover table-sm">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Category</th>
                                    <th>Units Sold</th>
                                    <th>Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Top selling products will be loaded via AJAX -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-tasks me-1"></i>
                    Quick Actions
                </div>
                <div class="card-body">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <a href="product_add.php" class="btn btn-primary w-100 py-3 quick-action-btn">
                                <i class="fas fa-plus-circle mb-2 fa-2x"></i>
                                <br>Add New Product
                                <span class="notification-counter">New</span>
                            </a>
                        </div>
                        <div class="col-md-6">
                            <a href="categories.php" class="btn btn-success w-100 py-3 quick-action-btn">
                                <i class="fas fa-tags mb-2 fa-2x"></i>
                                <br>Manage Categories
                            </a>
                        </div>
                        <div class="col-md-6">
                            <a href="reports.php" class="btn btn-info w-100 py-3 quick-action-btn text-white">
                                <i class="fas fa-chart-bar mb-2 fa-2x"></i>
                                <br>Generate Reports
                            </a>
                        </div>
                        <div class="col-md-6">
                            <a href="users.php" class="btn btn-warning w-100 py-3 quick-action-btn text-dark">
                                <i class="fas fa-users mb-2 fa-2x"></i>
                                <br>Manage Users
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Theme Toggle Button -->
<div class="theme-toggle" id="themeToggle">
    <i class="fas fa-moon"></i>
</div>

<!-- Restock Modal -->
<div class="modal fade" id="restockModal" tabindex="-1" aria-labelledby="restockModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="restockModalLabel">Restock Product</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="restockForm">
                    <input type="hidden" id="restockProductId" name="product_id">
                    <div class="mb-3">
                        <label for="restockQuantity" class="form-label">Add Quantity</label>
                        <input type="number" class="form-control" id="restockQuantity" name="quantity" min="1" required>
                    </div>
                    <div class="mb-3">
                        <label for="restockNote" class="form-label">Note (Optional)</label>
                        <textarea class="form-control" id="restockNote" name="note" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveRestock">Save Changes</button>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<!-- CountUp.js for animated numbers -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/countup.js/2.0.8/countUp.min.js"></script>

<script>
// Wait for page load
document.addEventListener('DOMContentLoaded', function() {
    // Show loading overlay initially
    const loadingOverlay = document.getElementById('loadingOverlay');
    loadingOverlay.style.display = 'flex';
    setTimeout(() => loadingOverlay.style.opacity = '1', 10);

    // Initialize charts
    let salesChart, categoryChart;
    
    // Theme toggle functionality
    const themeToggle = document.getElementById('themeToggle');
    const htmlElement = document.documentElement;
    const themeIcon = themeToggle.querySelector('i');
    
    // Check for saved theme preference
    if(localStorage.getItem('darkMode') === 'true') {
        htmlElement.classList.add('dark-mode');
        themeIcon.classList.remove('fa-moon');
        themeIcon.classList.add('fa-sun');
    }
    
    themeToggle.addEventListener('click', function() {
        htmlElement.classList.toggle('dark-mode');
        const isDarkMode = htmlElement.classList.contains('dark-mode');
        
        // Update icon
        if(isDarkMode) {
            themeIcon.classList.remove('fa-moon');
            themeIcon.classList.add('fa-sun');
        } else {
            themeIcon.classList.remove('fa-sun');
            themeIcon.classList.add('fa-moon');
        }
        
        // Save preference
        localStorage.setItem('darkMode', isDarkMode);
        
        // Update charts
        updateChartsTheme();
    });
    
    // Toggle between yearly and weekly view
    document.getElementById('viewYearly').addEventListener('click', function() {
        this.classList.add('active');
        document.getElementById('viewWeekly').classList.remove('active');
        fetchDashboardData(true); // Force refresh with new chart view
    });
    
    document.getElementById('viewWeekly').addEventListener('click', function() {
        this.classList.add('active');
        document.getElementById('viewYearly').classList.remove('active');
        
        salesChart.data.labels = dailySalesTrendLabels;
        salesChart.data.datasets[0].data = dailySalesTrendData;
        salesChart.update();
        fetchDashboardData(true);
    });
    
    // Update charts based on theme
    function updateChartsTheme() {
        const isDark = isDarkMode();
        
        // Update sales chart
        if (salesChart) {
            salesChart.options.scales.y.ticks.color = isDark ? '#b4b7bd' : '#525f7f';
            salesChart.options.scales.x.ticks.color = isDark ? '#b4b7bd' : '#525f7f';
            salesChart.options.scales.y.grid.color = isDark ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.1)';
            salesChart.options.scales.x.grid.color = isDark ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.1)';
            salesChart.options.plugins.tooltip.backgroundColor = isDark ? '#283046' : 'rgba(0, 0, 0, 0.7)';
            salesChart.update();
        }
        
        // Update category chart
        if (categoryChart) {
            categoryChart.options.plugins.legend.labels.color = isDark ? '#b4b7bd' : '#525f7f';
            categoryChart.options.plugins.tooltip.backgroundColor = isDark ? '#283046' : 'rgba(0, 0, 0, 0.7)';
            categoryChart.update();
        }
    }
    
    function isDarkMode() {
        return document.documentElement.classList.contains('dark-mode');
    }
    
    // Handle restock button clicks
    document.querySelectorAll('.restock-btn').forEach(button => {
        button.addEventListener('click', function() {
            const productId = this.getAttribute('data-id');
            document.getElementById('restockProductId').value = productId;
            
            // Show the modal using Bootstrap's modal API
            const restockModal = new bootstrap.Modal(document.getElementById('restockModal'));
            restockModal.show();
        });
    });
    
    // Handle form submission for restocking
    document.getElementById('saveRestock').addEventListener('click', function() {
        const form = document.getElementById('restockForm');
        const productId = document.getElementById('restockProductId').value;
        const quantity = document.getElementById('restockQuantity').value;
        
        if (!quantity || quantity < 1) {
            alert('Please enter a valid quantity');
            return;
        }
        
        // Show loading overlay
        document.getElementById('loadingOverlay').style.display = 'flex';
        setTimeout(() => {
            document.getElementById('loadingOverlay').style.opacity = '1';
        }, 10);
        
        // AJAX request to update inventory (simulated)
        setTimeout(function() {
            // Hide modal
            const restockModal = bootstrap.Modal.getInstance(document.getElementById('restockModal'));
            restockModal.hide();
            
            // Hide loading overlay
            document.getElementById('loadingOverlay').style.opacity = '0';
            setTimeout(() => {
                document.getElementById('loadingOverlay').style.display = 'none';
                
                // Show success message (you can improve this with a better notification system)
                alert('Product inventory updated successfully!');
                
                // Reset form
                form.reset();
            }, 500);
        }, 1500);
    });
    
    // Print functionality
    document.getElementById('printBtn').addEventListener('click', function() {
        window.print();
    });
    
    // Export functionality (example - can be extended for actual data export)
    document.getElementById('exportBtn').addEventListener('click', function() {
        alert('Export functionality would be implemented here. This would typically generate a CSV or Excel file with dashboard data.');
    });
    
    // Refresh Data functionality
    document.getElementById('refreshDataBtn').addEventListener('click', function() {
        fetchDashboardData(true);
    });
    
    // Add row hover effect for better UX
    document.querySelectorAll('.sale-row, .product-row').forEach(row => {
        row.addEventListener('mouseenter', function() {
            this.style.transition = 'background-color 0.3s';
        });
    });

    // Function to fetch and update dashboard data
    function fetchDashboardData(forceRefresh = false) {
        if (!forceRefresh) {
            loadingOverlay.style.display = 'flex';
            setTimeout(() => loadingOverlay.style.opacity = '1', 10);
        }

        fetch('index.php?action=get_dashboard_data', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(response => response.json())
        .then(response => {
            if (response.status === 'success') {
                updateDashboardUI(response.data);
                if (forceRefresh) alert('Data refreshed successfully!');
            } else {
                alert('Failed to load dashboard data.');
            }
        })
        .catch(error => {
            console.error('Error fetching dashboard data:', error);
            alert('An error occurred while fetching data.');
        })
        .finally(() => {
            loadingOverlay.style.opacity = '0';
            setTimeout(() => loadingOverlay.style.display = 'none', 500);
        });
    }

    // Function to update the UI with new data
    function updateDashboardUI(data) {
        // Update stat cards
        animateCounter('todaySalesValue', data.salesStats.today, { prefix: '$', decimalPlaces: 2 });
        document.getElementById('todaySalesOrders').textContent = `${data.salesStats.orders_today} orders`;
        
        animateCounter('monthlySalesValue', data.salesStats.month, { prefix: '$', decimalPlaces: 2 });
        document.getElementById('monthlySalesOrders').textContent = `${data.salesStats.orders_month} orders`;

        animateCounter('inventoryStatusValue', data.inventoryStats.total_products, { suffix: ' Products' });
        document.getElementById('inventoryStatusLow').textContent = `${data.inventoryStats.low_stock} Low Stock`;

        animateCounter('outOfStockValue', data.inventoryStats.out_of_stock, { suffix: ' Products' });
        document.getElementById('lowStockCount').textContent = data.inventoryStats.low_stock;

        // Update tables
        updateTable('recentSales', data.recentSales, (sale) => `
            <tr class="sale-row" data-sale-id="${sale.id}">
                <td>${sale.id}</td>
                <td>${new Date(sale.sale_date).toLocaleString('en-US', { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' })}</td>
                <td>${escapeHtml(sale.customer_name || 'Walk-in')}</td>
                <td>${escapeHtml(sale.product_name || 'N/A')}</td>
                <td class="fw-bold">$${parseFloat(sale.total_amount).toFixed(2)}</td>
                <td><a href="sale_details.php?id=${sale.id}" class="btn btn-sm btn-info text-white view-sale-btn"><i class="fas fa-eye"></i></a></td>
            </tr>
        `, 6);

        updateTable('lowStockProducts', data.lowStockProducts, (product) => `
            <tr class="product-row" data-product-id="${product.id}">
                <td>${escapeHtml(product.name)}</td>
                <td>${escapeHtml(product.category_name || 'Uncategorized')}</td>
                <td>$${parseFloat(product.price).toFixed(2)}</td>
                <td><span class="badge bg-danger">${product.quantity}</span></td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <a href="product_edit.php?id=${product.id}" class="btn btn-primary"><i class="fas fa-edit"></i></a>
                        <button type="button" class="btn btn-success restock-btn" data-id="${product.id}"><i class="fas fa-plus"></i></button>
                    </div>
                </td>
            </tr>
        `, 5);

        updateTable('topSellingProducts', data.topSellingProducts, (product) => `
            <tr>
                <td>${escapeHtml(product.name)}</td>
                <td>${escapeHtml(product.category_name || 'Uncategorized')}</td>
                <td><span class="badge bg-info text-white">${parseInt(product.total_sold)}</span></td>
                <td class="fw-bold">$${parseFloat(product.revenue).toFixed(2)}</td>
            </tr>
        `, 4);

        // Update charts
        renderSalesChart(data.monthlySales, data.dailySalesTrend);
        renderCategoryChart(data.categoryDistribution);
    }

    function animateCounter(elementId, value, options = {}) {
        const el = document.getElementById(elementId);
        if (!el) return;
        const countUp = new CountUp(el, value, {
            prefix: options.prefix || '',
            suffix: options.suffix || '',
            duration: 2.5,
            decimalPlaces: options.decimalPlaces || 0
        });
        if (!countUp.error) {
            countUp.start();
        } else {
            console.error(countUp.error);
            el.textContent = `${options.prefix || ''}${value.toFixed(options.decimalPlaces || 0)}${options.suffix || ''}`;
        }
    }

    function updateTable(tableId, data, rowTemplate, colSpan) {
        const tbody = document.querySelector(`#${tableId} tbody`);
        if (!tbody) return;
        tbody.innerHTML = '';
        if (data.length > 0) {
            data.forEach(item => tbody.innerHTML += rowTemplate(item));
        } else {
            tbody.innerHTML = `<tr><td colspan="${colSpan}" class="text-center">No data available</td></tr>`;
        }
    }

    function renderSalesChart(monthlyData, weeklyData) {
        const ctx = document.getElementById('salesChart').getContext('2d');
        const isWeekly = document.getElementById('viewWeekly').classList.contains('active');
        const chartData = isWeekly ? weeklyData.map(d => d.amount) : monthlyData;
        const chartLabels = isWeekly ? weeklyData.map(d => d.date) : ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

        if (salesChart) salesChart.destroy();
        salesChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: chartLabels,
                datasets: [{
                    label: 'Sales ($)',
                    data: chartData,
                    backgroundColor: 'rgba(78, 115, 223, 0.1)',
                    borderColor: '#4e73df',
                    borderWidth: 2,
                    tension: 0.3,
                    fill: true
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                scales: {
                    y: { beginAtZero: true, ticks: { callback: (v) => '$' + v.toLocaleString() } },
                },
                plugins: { legend: { display: false } }
            }
        });
        updateChartsTheme();
    }

    function renderCategoryChart(data) {
        const ctx = document.getElementById('categoryChart').getContext('2d');
        if (categoryChart) categoryChart.destroy();
        categoryChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: data.categories,
                datasets: [{
                    data: data.counts,
                    backgroundColor: ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b'],
                    borderWidth: 2,
                    borderColor: isDarkMode() ? '#283046' : '#fff'
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { padding: 20 } }
                },
                cutout: '65%'
            }
        });
        updateChartsTheme();
    }

    function escapeHtml(unsafe) {
        return unsafe
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    // Initial data load
    fetchDashboardData();
});
</script>