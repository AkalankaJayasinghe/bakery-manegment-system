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

// Initialize variables
$error = '';
$success = '';
$startDate = date('Y-m-d', strtotime('-30 days'));
$endDate = date('Y-m-d');
$reportType = isset($_GET['report']) ? $_GET['report'] : 'sales';

// Process filter parameters
if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['filter'])) {
    $startDate = isset($_GET['start_date']) ? $_GET['start_date'] : $startDate;
    $endDate = isset($_GET['end_date']) ? $_GET['end_date'] : $endDate;
    $reportType = isset($_GET['report']) ? $_GET['report'] : $reportType;
}

// Function to get daily sales data
function getDailySalesData($conn, $startDate, $endDate) {
    $data = [];
    
    $sql = "SELECT DATE(created_at) as sale_date, 
            SUM(total_amount) as total_sales, 
            COUNT(*) as num_transactions
            FROM sales
            WHERE DATE(created_at) BETWEEN ? AND ? 
            AND payment_status = 'paid'
            GROUP BY DATE(created_at)
            ORDER BY DATE(created_at) ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    
    return $data;
}

// Function to get product sales data
function getProductSalesData($conn, $startDate, $endDate) {
    $data = [];
    
    $sql = "SELECT p.name as product_name,
            SUM(si.quantity) as quantity_sold,
            SUM(si.subtotal) as total_sales
            FROM sale_items si
            JOIN products p ON si.product_id = p.id
            JOIN sales s ON si.sale_id = s.id
            WHERE DATE(s.created_at) BETWEEN ? AND ?
            AND s.payment_status = 'paid'
            GROUP BY p.id
            ORDER BY quantity_sold DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    
    return $data;
}

// Function to get category sales data
function getCategorySalesData($conn, $startDate, $endDate) {
    $data = [];
    
    $sql = "SELECT c.name as category_name,
            SUM(si.quantity) as quantity_sold,
            SUM(si.subtotal) as total_sales
            FROM sale_items si
            JOIN products p ON si.product_id = p.id
            JOIN categories c ON p.category_id = c.id
            JOIN sales s ON si.sale_id = s.id
            WHERE DATE(s.created_at) BETWEEN ? AND ?
            AND s.payment_status = 'paid'
            GROUP BY c.id
            ORDER BY total_sales DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    
    return $data;
}

// Function to get cashier performance data
function getCashierPerformanceData($conn, $startDate, $endDate) {
    $data = [];
    
    $sql = "SELECT u.full_name as cashier_name,
            COUNT(s.id) as num_transactions,
            SUM(s.total_amount) as total_sales,
            AVG(s.total_amount) as avg_transaction
            FROM sales s
            JOIN users u ON s.cashier_id = u.id
            WHERE DATE(s.created_at) BETWEEN ? AND ?
            AND s.payment_status = 'paid'
            GROUP BY s.cashier_id
            ORDER BY total_sales DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    
    return $data;
}

// Function to get inventory status
function getInventoryStatusData($conn) {
    $data = [];
    
    $sql = "SELECT p.id, p.name, p.quantity as stock_quantity, 
            FLOOR(p.quantity * 0.2) as reorder_level, 
            c.name as category_name, p.price, p.cost_price,
            (p.price - p.cost_price) as profit_margin,
            (p.price - p.cost_price) * p.quantity as potential_profit
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            ORDER BY p.quantity ASC";
    
    $result = $conn->query($sql);
    
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    
    return $data;
}

// Function to get low stock products
function getLowStockProductsData($conn) {
    $data = [];
    
    $sql = "SELECT p.id, p.name, p.quantity as stock_quantity, 
            FLOOR(p.quantity * 0.2) as reorder_level, 
            c.name as category_name, 
            (SELECT SUM(quantity) FROM sale_items si 
             JOIN sales s ON si.sale_id = s.id 
             WHERE si.product_id = p.id 
             AND DATE(s.created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) as monthly_demand
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.quantity <= FLOOR(p.quantity * 0.2)
            ORDER BY p.quantity ASC";
    
    $result = $conn->query($sql);
    
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    
    return $data;
}

// Get summary metrics
function getSummaryMetrics($conn, $startDate, $endDate) {
    $metrics = [];
    
    // Total sales
    $stmt = $conn->prepare("SELECT SUM(total_amount) as total_sales, COUNT(*) as total_invoices FROM sales WHERE DATE(created_at) BETWEEN ? AND ? AND payment_status = 'paid'");
    $stmt->bind_param("ss", $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $metrics['total_sales'] = $row['total_sales'] ?: 0;
    $metrics['total_invoices'] = $row['total_invoices'] ?: 0;
    
    // Average sale
    $metrics['avg_sale'] = ($metrics['total_invoices'] > 0) ? ($metrics['total_sales'] / $metrics['total_invoices']) : 0;
    
    // Total items sold
    $stmt = $conn->prepare("SELECT SUM(si.quantity) as total_items 
                          FROM sale_items si 
                          JOIN sales s ON si.sale_id = s.id 
                          WHERE DATE(s.created_at) BETWEEN ? AND ? 
                          AND s.payment_status = 'paid'");
    $stmt->bind_param("ss", $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $metrics['total_items'] = $row['total_items'] ?: 0;
    
    // Profit calculation
    $stmt = $conn->prepare("SELECT SUM((p.price - p.cost_price) * si.quantity) as total_profit
                          FROM sale_items si 
                          JOIN products p ON si.product_id = p.id
                          JOIN sales s ON si.sale_id = s.id 
                          WHERE DATE(s.created_at) BETWEEN ? AND ? 
                          AND s.payment_status = 'paid'");
    $stmt->bind_param("ss", $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $metrics['total_profit'] = $row['total_profit'] ?: 0;
    
    return $metrics;
}

// Function to format currency
function formatCurrency($amount) {
    return '$' . number_format($amount, 2);
}

// Get data based on report type
$reportData = [];
$reportTitle = '';
$metrics = [];

switch ($reportType) {
    case 'sales':
        $reportData = getDailySalesData($conn, $startDate, $endDate);
        $reportTitle = 'Daily Sales Report';
        $metrics = getSummaryMetrics($conn, $startDate, $endDate);
        break;
        
    case 'products':
        $reportData = getProductSalesData($conn, $startDate, $endDate);
        $reportTitle = 'Product Sales Report';
        $metrics = getSummaryMetrics($conn, $startDate, $endDate);
        break;
        
    case 'categories':
        $reportData = getCategorySalesData($conn, $startDate, $endDate);
        $reportTitle = 'Category Sales Report';
        $metrics = getSummaryMetrics($conn, $startDate, $endDate);
        break;
        
    case 'cashiers':
        $reportData = getCashierPerformanceData($conn, $startDate, $endDate);
        $reportTitle = 'Cashier Performance Report';
        $metrics = getSummaryMetrics($conn, $startDate, $endDate);
        break;
        
    case 'inventory':
        $reportData = getInventoryStatusData($conn);
        $reportTitle = 'Inventory Status Report';
        break;
        
    case 'low_stock':
        $reportData = getLowStockProductsData($conn);
        $reportTitle = 'Low Stock Products Report';
        break;
        
    default:
        $reportData = getDailySalesData($conn, $startDate, $endDate);
        $reportTitle = 'Daily Sales Report';
        $metrics = getSummaryMetrics($conn, $startDate, $endDate);
        break;
}

// Set page title
$pageTitle = "Reports";

// Include header
include_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Include sidebar -->
        <?php include_once '../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Reports</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="printReport">
                            <i class="fas fa-print"></i> Print
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="exportCSV">
                            <i class="fas fa-file-excel"></i> Export CSV
                        </button>
                    </div>
                </div>
            </div>
            
            <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>
            
            <!-- Report Types and Filters -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title">Report Options</h5>
                </div>
                <div class="card-body">
                    <form method="get" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="row g-3">
                        <input type="hidden" name="filter" value="1">
                        
                        <div class="col-md-3">
                            <label for="report" class="form-label">Report Type</label>
                            <select class="form-select" id="report" name="report">
                                <option value="sales" <?php echo ($reportType == 'sales') ? 'selected' : ''; ?>>Daily Sales</option>
                                <option value="products" <?php echo ($reportType == 'products') ? 'selected' : ''; ?>>Product Sales</option>
                                <option value="categories" <?php echo ($reportType == 'categories') ? 'selected' : ''; ?>>Category Sales</option>
                                <option value="cashiers" <?php echo ($reportType == 'cashiers') ? 'selected' : ''; ?>>Cashier Performance</option>
                                <option value="inventory" <?php echo ($reportType == 'inventory') ? 'selected' : ''; ?>>Inventory Status</option>
                                <option value="low_stock" <?php echo ($reportType == 'low_stock') ? 'selected' : ''; ?>>Low Stock Products</option>
                            </select>
                        </div>
                        
                        <div class="col-md-3" id="startDateContainer">
                            <label for="start_date" class="form-label">Start Date</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $startDate; ?>">
                        </div>
                        
                        <div class="col-md-3" id="endDateContainer">
                            <label for="end_date" class="form-label">End Date</label>
                            <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $endDate; ?>">
                        </div>
                        
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">Generate Report</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Report Content -->
            <div class="card" id="reportContent">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title"><?php echo $reportTitle; ?></h5>
                    <span class="badge bg-primary">
                        <?php echo date('M d, Y', strtotime($startDate)); ?> - <?php echo date('M d, Y', strtotime($endDate)); ?>
                    </span>
                </div>
                <div class="card-body">
                    <?php if (!empty($metrics)): ?>
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card bg-primary text-white mb-3">
                                <div class="card-body">
                                    <h5 class="card-title">Total Sales</h5>
                                    <h3><?php echo formatCurrency($metrics['total_sales']); ?></h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-success text-white mb-3">
                                <div class="card-body">
                                    <h5 class="card-title">Profit</h5>
                                    <h3><?php echo formatCurrency($metrics['total_profit']); ?></h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-info text-white mb-3">
                                <div class="card-body">
                                    <h5 class="card-title">Transactions</h5>
                                    <h3><?php echo $metrics['total_invoices']; ?></h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-warning text-dark mb-3">
                                <div class="card-body">
                                    <h5 class="card-title">Items Sold</h5>
                                    <h3><?php echo $metrics['total_items']; ?></h3>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($reportType == 'sales'): ?>
                        <!-- Daily Sales Chart -->
                        <div class="mb-4">
                            <canvas id="salesChart" width="400" height="200"></canvas>
                        </div>
                        
                        <!-- Daily Sales Table -->
                        <div class="table-responsive">
                            <table class="table table-striped table-hover" id="reportTable">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Transactions</th>
                                        <th>Total Sales</th>
                                        <th>Average Sale</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($reportData) > 0): ?>
                                        <?php foreach ($reportData as $item): ?>
                                            <tr>
                                                <td><?php echo date('M d, Y', strtotime($item['sale_date'])); ?></td>
                                                <td><?php echo $item['num_transactions']; ?></td>
                                                <td><?php echo formatCurrency($item['total_sales']); ?></td>
                                                <td><?php echo formatCurrency($item['total_sales'] / $item['num_transactions']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" class="text-center">No data available for the selected period.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php elseif ($reportType == 'products'): ?>
                        <!-- Product Sales Chart -->
                        <div class="mb-4">
                            <canvas id="productChart" width="400" height="200"></canvas>
                        </div>
                        
                        <!-- Product Sales Table -->
                        <div class="table-responsive">
                            <table class="table table-striped table-hover" id="reportTable">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Quantity Sold</th>
                                        <th>Total Sales</th>
                                        <th>Average Price</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($reportData) > 0): ?>
                                        <?php foreach ($reportData as $item): ?>
                                            <tr>
                                                <td><?php echo $item['product_name']; ?></td>
                                                <td><?php echo $item['quantity_sold']; ?></td>
                                                <td><?php echo formatCurrency($item['total_sales']); ?></td>
                                                <td><?php echo formatCurrency($item['total_sales'] / $item['quantity_sold']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" class="text-center">No data available for the selected period.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php elseif ($reportType == 'categories'): ?>
                        <!-- Category Sales Chart -->
                        <div class="mb-4">
                            <canvas id="categoryChart" width="400" height="200"></canvas>
                        </div>
                        
                        <!-- Category Sales Table -->
                        <div class="table-responsive">
                            <table class="table table-striped table-hover" id="reportTable">
                                <thead>
                                    <tr>
                                        <th>Category</th>
                                        <th>Quantity Sold</th>
                                        <th>Total Sales</th>
                                        <th>% of Total Sales</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($reportData) > 0): ?>
                                        <?php 
                                        $totalCategorySales = array_sum(array_column($reportData, 'total_sales'));
                                        foreach ($reportData as $item): 
                                        ?>
                                            <tr>
                                                <td><?php echo $item['category_name'] ?: 'Uncategorized'; ?></td>
                                                <td><?php echo $item['quantity_sold']; ?></td>
                                                <td><?php echo formatCurrency($item['total_sales']); ?></td>
                                                <td><?php echo number_format(($item['total_sales'] / $totalCategorySales) * 100, 2); ?>%</td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" class="text-center">No data available for the selected period.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php elseif ($reportType == 'cashiers'): ?>
                        <!-- Cashier Performance Chart -->
                        <div class="mb-4">
                            <canvas id="cashierChart" width="400" height="200"></canvas>
                        </div>
                        
                        <!-- Cashier Performance Table -->
                        <div class="table-responsive">
                            <table class="table table-striped table-hover" id="reportTable">
                                <thead>
                                    <tr>
                                        <th>Cashier</th>
                                        <th>Transactions</th>
                                        <th>Total Sales</th>
                                        <th>Average Sale</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($reportData) > 0): ?>
                                        <?php foreach ($reportData as $item): ?>
                                            <tr>
                                                <td><?php echo $item['cashier_name']; ?></td>
                                                <td><?php echo $item['num_transactions']; ?></td>
                                                <td><?php echo formatCurrency($item['total_sales']); ?></td>
                                                <td><?php echo formatCurrency($item['avg_transaction']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" class="text-center">No data available for the selected period.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php elseif ($reportType == 'inventory'): ?>
                        <!-- Inventory Status Table -->
                        <div class="table-responsive">
                            <table class="table table-striped table-hover" id="reportTable">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Category</th>
                                        <th>Stock</th>
                                        <th>Reorder Level</th>
                                        <th>Status</th>
                                        <th>Unit Price</th>
                                        <th>Cost Price</th>
                                        <th>Profit Margin</th>
                                        <th>Total Value</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($reportData) > 0): ?>
                                        <?php foreach ($reportData as $item): ?>
                                            <tr>
                                                <td><?php echo $item['name']; ?></td>
                                                <td><?php echo $item['category_name'] ?: 'Uncategorized'; ?></td>
                                                <td><?php echo $item['stock_quantity']; ?></td>
                                                <td><?php echo $item['reorder_level']; ?></td>
                                                <td>
                                                    <?php if ($item['stock_quantity'] <= 0): ?>
                                                        <span class="badge bg-danger">Out of Stock</span>
                                                    <?php elseif ($item['stock_quantity'] <= $item['reorder_level']): ?>
                                                        <span class="badge bg-warning">Low Stock</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-success">In Stock</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo formatCurrency($item['price']); ?></td>
                                                <td><?php echo formatCurrency($item['cost_price']); ?></td>
                                                <td><?php echo formatCurrency($item['profit_margin']); ?> (<?php echo number_format(($item['profit_margin'] / $item['price']) * 100, 1); ?>%)</td>
                                                <td><?php echo formatCurrency($item['price'] * $item['stock_quantity']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="9" class="text-center">No inventory data available.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php elseif ($reportType == 'low_stock'): ?>
                        <!-- Low Stock Products Table -->
                        <div class="table-responsive">
                            <table class="table table-striped table-hover" id="reportTable">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Category</th>
                                        <th>Current Stock</th>
                                        <th>Reorder Level</th>
                                        <th>Monthly Demand</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($reportData) > 0): ?>
                                        <?php foreach ($reportData as $item): ?>
                                            <tr>
                                                <td><?php echo $item['name']; ?></td>
                                                <td><?php echo $item['category_name'] ?: 'Uncategorized'; ?></td>
                                                <td><?php echo $item['stock_quantity']; ?></td>
                                                <td><?php echo $item['reorder_level']; ?></td>
                                                <td><?php echo $item['monthly_demand'] ?: 0; ?></td>
                                                <td>
                                                    <?php if ($item['stock_quantity'] <= 0): ?>
                                                        <span class="badge bg-danger">Out of Stock</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning">Low Stock</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <a href="stock_adjustment.php?product=<?php echo $item['id']; ?>" class="btn btn-sm btn-primary">
                                                        Add Stock
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="text-center">No low stock products found.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle date fields based on report type
    const reportSelect = document.getElementById('report');
    const startDateContainer = document.getElementById('startDateContainer');
    const endDateContainer = document.getElementById('endDateContainer');
    
    reportSelect.addEventListener('change', function() {
        const showDateFields = !['inventory', 'low_stock'].includes(this.value);
        startDateContainer.style.display = showDateFields ? 'block' : 'none';
        endDateContainer.style.display = showDateFields ? 'block' : 'none';
    });
    
    // Trigger change event to set initial state
    reportSelect.dispatchEvent(new Event('change'));
    
    // Initialize charts based on report type
    <?php if ($reportType == 'sales' && !empty($reportData)): ?>
        // Sales Chart
        const salesData = {
            labels: [<?php echo implode(',', array_map(function($item) { return "'" . date('M d', strtotime($item['sale_date'])) . "'"; }, $reportData)); ?>],
            datasets: [{
                label: 'Daily Sales',
                data: [<?php echo implode(',', array_map(function($item) { return $item['total_sales']; }, $reportData)); ?>],
                backgroundColor: 'rgba(54, 162, 235, 0.2)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1,
                tension: 0.1
            }]
        };
        
        const salesConfig = {
            type: 'line',
            data: salesData,
            options: {
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Sales ($)'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Date'
                        }
                    }
                },
                plugins: {
                    title: {
                        display: true,
                        text: 'Daily Sales Trend'
                    }
                }
            }
        };
        
        const salesChart = new Chart(
            document.getElementById('salesChart'),
            salesConfig
        );
    <?php endif; ?>
    
    <?php if ($reportType == 'products' && !empty($reportData)): ?>
        // Products Chart (top 10 for clarity)
        const productData = {
            labels: [<?php 
                $topProducts = array_slice($reportData, 0, 10);
                echo implode(',', array_map(function($item) { return "'" . str_replace("'", "\\'", $item['product_name']) . "'"; }, $topProducts)); 
            ?>],
            datasets: [{
                label: 'Units Sold',
                data: [<?php echo implode(',', array_map(function($item) { return $item['quantity_sold']; }, $topProducts)); ?>],
                backgroundColor: [
                    'rgba(255, 99, 132, 0.2)',
                    'rgba(54, 162, 235, 0.2)',
                    'rgba(255, 206, 86, 0.2)',
                    'rgba(75, 192, 192, 0.2)',
                    'rgba(153, 102, 255, 0.2)',
                    'rgba(255, 159, 64, 0.2)',
                    'rgba(199, 199, 199, 0.2)',
                    'rgba(83, 102, 255, 0.2)',
                    'rgba(40, 159, 64, 0.2)',
                    'rgba(210, 199, 199, 0.2)'
                ],
                borderColor: [
                    'rgba(255, 99, 132, 1)',
                    'rgba(54, 162, 235, 1)',
                    'rgba(255, 206, 86, 1)',
                    'rgba(75, 192, 192, 1)',
                    'rgba(153, 102, 255, 1)',
                    'rgba(255, 159, 64, 1)',
                    'rgba(199, 199, 199, 1)',
                    'rgba(83, 102, 255, 1)',
                    'rgba(40, 159, 64, 1)',
                    'rgba(210, 199, 199, 1)'
                ],
                borderWidth: 1
            }]
        };
        
        const productConfig = {
            type: 'bar',
            data: productData,
            options: {
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Units Sold'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Product'
                        }
                    }
                },
                plugins: {
                    title: {
                        display: true,
                        text: 'Top 10 Products by Units Sold'
                    }
                }
            }
        };
        
        const productChart = new Chart(
            document.getElementById('productChart'),
            productConfig
        );
    <?php endif; ?>
    
    <?php if ($reportType == 'categories' && !empty($reportData)): ?>
        // Categories Chart
        const categoryData = {
            labels: [<?php echo implode(',', array_map(function($item) { return "'" . ($item['category_name'] ?: 'Uncategorized') . "'"; }, $reportData)); ?>],
            datasets: [{
                label: 'Sales by Category',
                data: [<?php echo implode(',', array_map(function($item) { return $item['total_sales']; }, $reportData)); ?>],
                backgroundColor: [
                    'rgba(255, 99, 132, 0.2)',
                    'rgba(54, 162, 235, 0.2)',
                    'rgba(255, 206, 86, 0.2)',
                    'rgba(75, 192, 192, 0.2)',
                    'rgba(153, 102, 255, 0.2)',
                    'rgba(255, 159, 64, 0.2)',
                    'rgba(199, 199, 199, 0.2)'
                ],
                borderColor: [
                    'rgba(255, 99, 132, 1)',
                    'rgba(54, 162, 235, 1)',
                    'rgba(255, 206, 86, 1)',
                    'rgba(75, 192, 192, 1)',
                    'rgba(153, 102, 255, 1)',
                    'rgba(255, 159, 64, 1)',
                    'rgba(199, 199, 199, 1)'
                ],
                borderWidth: 1,
                hoverOffset: 4
            }]
        };
        
        const categoryConfig = {
            type: 'pie',
            data: categoryData,
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: 'Sales Distribution by Category'
                    }
                }
            }
        };
        
        const categoryChart = new Chart(
            document.getElementById('categoryChart'),
            categoryConfig
        );
    <?php endif; ?>
    
    <?php if ($reportType == 'cashiers' && !empty($reportData)): ?>
        // Cashier Performance Chart
        const cashierData = {
            labels: [<?php echo implode(',', array_map(function($item) { return "'" . $item['cashier_name'] . "'"; }, $reportData)); ?>],
            datasets: [{
                label: 'Total Sales',
                data: [<?php echo implode(',', array_map(function($item) { return $item['total_sales']; }, $reportData)); ?>],
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                borderColor: 'rgba(75, 192, 192, 1)',
                borderWidth: 1
            }]
        };
        
        const cashierConfig = {
            type: 'bar',
            data: cashierData,
            options: {
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Sales ($)'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Cashier'
                        }
                    }
                },
                plugins: {
                    title: {
                        display: true,
                        text: 'Sales by Cashier'
                    }
                }
            }
        };
        
        const cashierChart = new Chart(
            document.getElementById('cashierChart'),
            cashierConfig
        );
    <?php endif; ?>
    
    // Print report functionality
    document.getElementById('printReport').addEventListener('click', function() {
        const reportContent = document.getElementById('reportContent').innerHTML;
        const printWindow = window.open('', '_blank');
        
        printWindow.document.write(`
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>${document.title}</title>
                <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
                <style>
                    body { padding: 20px; }
                    @media print {
                        .no-print { display: none; }
                        button { display: none; }
                    }
                </style>
            </head>
            <body>
                <div class="container">
                    <div class="row mb-4">
                        <div class="col-12 text-center">
                            <h2>Bakery Cashier Management System</h2>
                            <h3>${document.querySelector('.card-title').textContent}</h3>
                            <p>Period: ${document.querySelector('.badge').textContent}</p>
                        </div>
                    </div>
                    ${reportContent}
                    <div class="row mt-5">
                        <div class="col-12 text-center">
                            <p>Generated on: ${new Date().toLocaleString()}</p>
                        </div>
                    </div>
                </div>
                <script>
                    window.onload = function() { window.print(); };
                </script>
            </body>
            </html>
        `);
        
        printWindow.document.close();
    });
    
    // Export CSV functionality
    document.getElementById('exportCSV').addEventListener('click', function() {
        const table = document.getElementById('reportTable');
        if (!table) return;
        
        let csv = [];
        const rows = table.querySelectorAll('tr');
        
        for (let i = 0; i < rows.length; i++) {
            const row = [], cols = rows[i].querySelectorAll('td, th');
            
            for (let j = 0; j < cols.length; j++) {
                // Get text content, removing any badge element text for status columns
                let content = cols[j].innerText;
                row.push('"' + content.replace(/"/g, '""') + '"');
            }
            
            csv.push(row.join(','));
        }
        
        const csvString = csv.join('\n');
        const filename = '<?php echo $reportTitle; ?>_<?php echo date('Y-m-d'); ?>.csv';
        const link = document.createElement('a');
        
        link.style.display = 'none';
        link.setAttribute('target', '_blank');
        link.setAttribute('href', 'data:text/csv;charset=utf-8,' + encodeURIComponent(csvString));
        link.setAttribute('download', filename);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    });
});

// Auto-hide alerts after 5 seconds
setTimeout(function() {
    const alerts = document.querySelectorAll('.alert-dismissible');
    alerts.forEach(function(alert) {
        const alertInstance = new bootstrap.Alert(alert);
        alertInstance.close();
    });
}, 5000);
</script>

<?php
// Include footer
include_once '../includes/footer.php';
?>