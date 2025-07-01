<?php
// Include database connection
include 'includes/db_connect.php';
include 'includes/functions.php';
include 'includes/session.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit();
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
        // Today's sales
        $query = "SELECT COALESCE(SUM(total_amount), 0) AS total FROM sales WHERE DATE(sale_date) = '$today'";
        $result = mysqli_query($conn, $query);
        $stats['today'] = mysqli_fetch_assoc($result)['total'];
        
        // This month's sales
        $query = "SELECT COALESCE(SUM(total_amount), 0) AS total FROM sales WHERE DATE_FORMAT(sale_date, '%Y-%m') = '$currentMonth'";
        $result = mysqli_query($conn, $query);
        $stats['month'] = mysqli_fetch_assoc($result)['total'];
        
        // This year's sales
        $query = "SELECT COALESCE(SUM(total_amount), 0) AS total FROM sales WHERE YEAR(sale_date) = '$currentYear'";
        $result = mysqli_query($conn, $query);
        $stats['year'] = mysqli_fetch_assoc($result)['total'];
        
        // Total sales all time
        $result = mysqli_query($conn, "SELECT COALESCE(SUM(total_amount), 0) AS total FROM sales");
        $stats['total'] = mysqli_fetch_assoc($result)['total'];
        
        // Order counts
        $query = "SELECT COUNT(*) AS count FROM sales WHERE DATE(sale_date) = '$today'";
        $result = mysqli_query($conn, $query);
        $stats['orders_today'] = mysqli_fetch_assoc($result)['count'];
        
        $query = "SELECT COUNT(*) AS count FROM sales WHERE DATE_FORMAT(sale_date, '%Y-%m') = '$currentMonth'";
        $result = mysqli_query($conn, $query);
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
        $result = mysqli_query($conn, "SELECT COUNT(*) AS count FROM products WHERE status = 1");
        if ($result) {
            $stats['total_products'] = mysqli_fetch_assoc($result)['count'];
        }
        
        // Low stock products (assuming quantity field exists)
        $result = mysqli_query($conn, "SELECT COUNT(*) AS count FROM products WHERE quantity > 0 AND quantity <= 10");
        if ($result) {
            $stats['low_stock'] = mysqli_fetch_assoc($result)['count'];
        }
        
        // Out of stock products
        $result = mysqli_query($conn, "SELECT COUNT(*) AS count FROM products WHERE quantity = 0");
        if ($result) {
            $stats['out_of_stock'] = mysqli_fetch_assoc($result)['count'];
        }
        
        // Active categories
        $result = mysqli_query($conn, "SELECT COUNT(*) AS count FROM categories");
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
                  LIMIT $limit";
                  
        $result = mysqli_query($conn, $query);
        
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
                  LIMIT $limit";
                  
        $result = mysqli_query($conn, $query);
        
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
            
            $query = "SELECT COALESCE(SUM(total_amount), 0) AS total FROM sales WHERE DATE_FORMAT(sale_date, '%Y-%m') = '$yearMonth'";
            $result = mysqli_query($conn, $query);
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
                  LIMIT $limit";
                  
        $result = mysqli_query($conn, $query);
        
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

// Get the data
$salesStats = getSalesStats();
$inventoryStats = getInventoryStats();
$recentSales = getRecentSales();
$lowStockProducts = getLowStockProducts();
$monthlySales = getMonthlySales();
$topSellingProducts = getTopSellingProducts();

// Include header
include 'includes/header.php';
?>

<div class="main-content">
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
            <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle">
                <i class="fas fa-calendar"></i> <?php echo date('F Y'); ?>
            </button>
        </div>
    </div>
            
            <!-- Sales Overview -->
            <div class="row">
                <div class="col-md-3">
                    <div class="card text-white bg-primary mb-4">
                        <div class="card-body d-flex align-items-center">
                            <div>
                                <div class="text-uppercase text-white-50 small">Today's Sales</div>
                                <div class="h4 mb-0">$<?php echo number_format($salesStats['today'], 2); ?></div>
                                <div class="small"><?php echo $salesStats['orders_today']; ?> orders</div>
                            </div>
                            <div class="ms-auto">
                                <i class="fas fa-shopping-cart fa-3x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="card text-white bg-success mb-4">
                        <div class="card-body d-flex align-items-center">
                            <div>
                                <div class="text-uppercase text-white-50 small">Monthly Sales</div>
                                <div class="h4 mb-0">$<?php echo number_format($salesStats['month'], 2); ?></div>
                                <div class="small"><?php echo $salesStats['orders_month']; ?> orders</div>
                            </div>
                            <div class="ms-auto">
                                <i class="fas fa-calendar-check fa-3x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="card text-white bg-warning mb-4">
                        <div class="card-body d-flex align-items-center">
                            <div>
                                <div class="text-uppercase text-white-50 small">Inventory Status</div>
                                <div class="h4 mb-0"><?php echo $inventoryStats['total_products']; ?> Products</div>
                                <div class="small"><?php echo $inventoryStats['low_stock']; ?> Low Stock</div>
                            </div>
                            <div class="ms-auto">
                                <i class="fas fa-boxes fa-3x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="card text-white bg-danger mb-4">
                        <div class="card-body d-flex align-items-center">
                            <div>
                                <div class="text-uppercase text-white-50 small">Out of Stock</div>
                                <div class="h4 mb-0"><?php echo $inventoryStats['out_of_stock']; ?> Products</div>
                                <div class="small">Needs attention</div>
                            </div>
                            <div class="ms-auto">
                                <i class="fas fa-exclamation-triangle fa-3x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Charts Row -->
            <div class="row mb-4">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-chart-line me-1"></i>
                            Sales Overview - <?php echo date('Y'); ?>
                        </div>
                        <div class="card-body">
                            <canvas id="salesChart" height="300"></canvas>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-chart-pie me-1"></i>
                            Product Categories
                        </div>
                        <div class="card-body">
                            <canvas id="categoryChart" height="300"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Tables Row -->
            <div class="row">
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
                                        <?php if (empty($recentSales)): ?>
                                            <tr>
                                                <td colspan="6" class="text-center">No recent sales found</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($recentSales as $sale): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($sale['id']); ?></td>
                                                    <td><?php echo date('M d, h:i A', strtotime($sale['sale_date'])); ?></td>
                                                    <td><?php echo htmlspecialchars($sale['customer_name'] ?: 'Walk-in'); ?></td>
                                                    <td><?php echo htmlspecialchars($sale['product_name'] ?: 'N/A'); ?></td>
                                                    <td>$<?php echo number_format($sale['total_amount'], 2); ?></td>
                                                    <td>
                                                        <a href="sales.php" class="btn btn-sm btn-info">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
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
                                        <?php if (empty($lowStockProducts)): ?>
                                            <tr>
                                                <td colspan="5" class="text-center">No low stock products found</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($lowStockProducts as $product): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($product['name']); ?></td>
                                                    <td><?php echo htmlspecialchars($product['category_name'] ?: 'Uncategorized'); ?></td>
                                                    <td>$<?php echo number_format($product['price'], 2); ?></td>
                                                    <td><span class="badge bg-danger"><?php echo $product['quantity']; ?></span></td>
                                                    <td>
                                                        <a href="products.php" class="btn btn-sm btn-primary">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Third Row -->
            <div class="row">
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
                                        <?php if (empty($topSellingProducts)): ?>
                                            <tr>
                                                <td colspan="4" class="text-center">No sales data available</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($topSellingProducts as $product): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($product['name']); ?></td>
                                                    <td><?php echo htmlspecialchars($product['category_name'] ?: 'Uncategorized'); ?></td>
                                                    <td><?php echo (int)$product['total_sold']; ?></td>
                                                    <td>$<?php echo number_format($product['revenue'], 2); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
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
                                    <a href="products.php" class="btn btn-primary w-100 py-3">
                                        <i class="fas fa-plus-circle mb-2 fa-2x"></i>
                                        <br>Add New Product
                                    </a>
                                </div>
                                <div class="col-md-6">
                                    <a href="categories.php" class="btn btn-success w-100 py-3">
                                        <i class="fas fa-tags mb-2 fa-2x"></i>
                                        <br>Manage Categories
                                    </a>
                                </div>
                                <div class="col-md-6">
                                    <a href="reports.php" class="btn btn-info w-100 py-3 text-white">
                                        <i class="fas fa-chart-bar mb-2 fa-2x"></i>
                                        <br>Generate Reports
                                    </a>
                                </div>
                                <div class="col-md-6">
                                    <a href="users.php" class="btn btn-warning w-100 py-3 text-dark">
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
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// Sales Chart
const salesChartElement = document.getElementById('salesChart');
const salesChart = new Chart(salesChartElement, {
    type: 'line',
    data: {
        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
        datasets: [{
            label: 'Sales ($)',
            data: <?php echo json_encode($monthlySales); ?>,
            backgroundColor: 'rgba(54, 162, 235, 0.2)',
            borderColor: 'rgba(54, 162, 235, 1)',
            borderWidth: 2,
            tension: 0.3,
            pointBackgroundColor: 'rgba(54, 162, 235, 1)',
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return '$' + value;
                    }
                }
            }
        },
        plugins: {
            legend: {
                display: false
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return '$' + context.raw.toFixed(2);
                    }
                }
            }
        }
    }
});

// Category Chart - Sample data, replace with actual data from database
const categoryChartElement = document.getElementById('categoryChart');
const categoryChart = new Chart(categoryChartElement, {
    type: 'pie',
    data: {
        labels: ['Bread', 'Pastries', 'Cakes', 'Cookies', 'Other'],
        datasets: [{
            data: [30, 25, 20, 15, 10],
            backgroundColor: [
                'rgba(255, 99, 132, 0.7)',
                'rgba(54, 162, 235, 0.7)',
                'rgba(255, 206, 86, 0.7)',
                'rgba(75, 192, 192, 0.7)',
                'rgba(153, 102, 255, 0.7)'
            ],
            borderColor: [
                'rgba(255, 99, 132, 1)',
                'rgba(54, 162, 235, 1)',
                'rgba(255, 206, 86, 1)',
                'rgba(75, 192, 192, 1)',
                'rgba(153, 102, 255, 1)'
            ],
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'right'
            }
        }
    }
});
</script>