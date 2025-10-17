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
$reportType = isset($_GET['report']) ? filter_input(INPUT_GET, 'report', ) : 'sales';
$isAjaxRequest = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

// Handle AJAX requests
if ($isAjaxRequest) {
    $response = ['status' => 'error', 'message' => 'Invalid request'];
    
    // Process based on AJAX action
    if (isset($_GET['action'])) {
        switch ($_GET['action']) {
            case 'get_report_data':
                $reportType = filter_input(INPUT_GET, 'report', ) ?: 'sales';
                $startDate = filter_input(INPUT_GET, 'start_date', ) ?: date('Y-m-d', strtotime('-30 days'));
                $endDate = filter_input(INPUT_GET, 'end_date', ) ?: date('Y-m-d');
                
                try {
                    switch ($reportType) {
                        case 'sales':
                            $data = getDailySalesData($conn, $startDate, $endDate);
                            $metrics = getSummaryMetrics($conn, $startDate, $endDate, $reportType);
                            $response = [
                                'status' => 'success',
                                'data' => $data,
                                'metrics' => $metrics
                            ];
                            break;
                            
                        case 'products':
                            $data = getProductSalesData($conn, $startDate, $endDate);
                            $metrics = getSummaryMetrics($conn, $startDate, $endDate, $reportType);
                            $response = [
                                'status' => 'success',
                                'data' => $data,
                                'metrics' => $metrics
                            ];
                            break;
                            
                        case 'categories':
                            $data = getCategorySalesData($conn, $startDate, $endDate);
                            $metrics = getSummaryMetrics($conn, $startDate, $endDate, $reportType);
                            $response = [
                                'status' => 'success',
                                'data' => $data,
                                'metrics' => $metrics
                            ];
                            break;
                            
                        case 'cashiers':
                            $data = getCashierPerformanceData($conn, $startDate, $endDate);
                            $metrics = getSummaryMetrics($conn, $startDate, $endDate, $reportType);
                            $response = [
                                'status' => 'success',
                                'data' => $data,
                                'metrics' => $metrics
                            ];
                            break;
                            
                        case 'inventory':
                            $data = getInventoryStatusData($conn);
                            $response = [
                                'status' => 'success',
                                'data' => $data
                            ];
                            break;
                            
                        case 'low_stock':
                            $data = getLowStockProductsData($conn);
                            $response = [
                                'status' => 'success',
                                'data' => $data
                            ];
                            break;
                            
                        default:
                            $response['message'] = 'Invalid report type';
                    }
                } catch (Exception $e) {
                    $response = [
                        'status' => 'error',
                        'message' => 'Failed to retrieve data: ' . $e->getMessage()
                    ];
                }
                break;
                
            default:
                $response['message'] = 'Unknown action';
        }
    }
    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Process filter parameters
if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['filter'])) {
    $endDate = filter_input(INPUT_GET, 'end_date', ) ?: $endDate;
    $reportType = filter_input(INPUT_GET, 'report', ) ?: $reportType;
    $timeframe = filter_input(INPUT_GET, 'timeframe', ) ?: $timeframe;
    
    // Validate date inputs
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
        $error = "Invalid start date format.";
        $startDate = date('Y-m-d', strtotime('-30 days'));
    }
    
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
        $error = "Invalid end date format.";
        $endDate = date('Y-m-d');
    }
    
    // Ensure end date is not before start date
    if (strtotime($endDate) < strtotime($startDate)) {
        $error = "End date cannot be before start date.";
        $endDate = date('Y-m-d');
    }
}

// Function to get daily sales data
function getDailySalesData($conn, $startDate, $endDate) {
    $data = [];
    
    $sql = "SELECT DATE(created_at) as sale_date, 
            COALESCE(SUM(total_amount), 0) as total_sales, 
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
            COALESCE(SUM(si.quantity), 0) as quantity_sold,
            COALESCE(SUM(si.subtotal), 0) as total_sales
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
            COALESCE(SUM(si.quantity), 0) as quantity_sold,
            COALESCE(SUM(si.subtotal), 0) as total_sales
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
            COALESCE(SUM(s.total_amount), 0) as total_sales,
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
            GREATEST(5, FLOOR(p.quantity * 0.2)) as reorder_level, 
            c.name as category_name, p.price, p.cost_price,
            (p.price - p.cost_price) as profit_margin,
            (p.price - p.cost_price) * p.quantity as potential_profit
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            ORDER BY p.quantity ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    
    return $data;
}

// Function to get low stock products
function getLowStockProductsData($conn) {
    $data = [];
    
    $sql = "SELECT p.id, p.name, p.quantity as stock_quantity, 
            GREATEST(5, FLOOR(p.quantity * 0.2)) as reorder_level, 
            c.name as category_name, 
            (SELECT COALESCE(SUM(quantity), 0) FROM sale_items si 
             JOIN sales s ON si.sale_id = s.id 
             WHERE si.product_id = p.id 
             AND DATE(s.created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) as monthly_demand
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.quantity <= GREATEST(5, FLOOR(p.quantity * 0.2))
            ORDER BY p.quantity ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    
    return $data;
}

// Get summary metrics
function getSummaryMetrics($conn, $startDate, $endDate, $reportType = 'sales') {
    $metrics = [
        'total_sales' => 0,
        'total_invoices' => 0,
        'avg_sale' => 0,
        'total_items' => 0,
        'total_profit' => 0
    ];
    
    try {
        // Total sales
        $stmt = $conn->prepare("SELECT COALESCE(SUM(total_amount), 0) as total_sales, COUNT(*) as total_invoices 
                              FROM sales 
                              WHERE DATE(created_at) BETWEEN ? AND ? 
                              AND payment_status = 'paid'");
        $stmt->bind_param("ss", $startDate, $endDate);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        $metrics['total_sales'] = (float)($row['total_sales'] ?: 0);
        $metrics['total_invoices'] = (int)($row['total_invoices'] ?: 0);
        
        // Average sale
        $metrics['avg_sale'] = ($metrics['total_invoices'] > 0) ? ($metrics['total_sales'] / $metrics['total_invoices']) : 0;
        
        // Total items sold
        $stmt = $conn->prepare("SELECT COALESCE(SUM(si.quantity), 0) as total_items 
                              FROM sale_items si 
                              JOIN sales s ON si.sale_id = s.id 
                              WHERE DATE(s.created_at) BETWEEN ? AND ? 
                              AND s.payment_status = 'paid'");
        $stmt->bind_param("ss", $startDate, $endDate);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        $metrics['total_items'] = (int)($row['total_items'] ?: 0);
        
        // Profit calculation
        $stmt = $conn->prepare("SELECT COALESCE(SUM((p.price - p.cost_price) * si.quantity), 0) as total_profit
                              FROM sale_items si 
                              JOIN products p ON si.product_id = p.id
                              JOIN sales s ON si.sale_id = s.id 
                              WHERE DATE(s.created_at) BETWEEN ? AND ? 
                              AND s.payment_status = 'paid'");
        $stmt->bind_param("ss", $startDate, $endDate);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        $metrics['total_profit'] = (float)($row['total_profit'] ?: 0);
        
        // Get previous period metrics for comparison
        $dayDiff = (strtotime($endDate) - strtotime($startDate)) / (60 * 60 * 24);
        $prevStartDate = date('Y-m-d', strtotime("-" . ($dayDiff * 2) . " days", strtotime($startDate)));
        $prevEndDate = date('Y-m-d', strtotime("-" . ($dayDiff + 1) . " days", strtotime($endDate)));
        
        $stmt = $conn->prepare("SELECT COALESCE(SUM(total_amount), 0) as prev_total_sales 
                              FROM sales 
                              WHERE DATE(created_at) BETWEEN ? AND ? 
                              AND payment_status = 'paid'");
        $stmt->bind_param("ss", $prevStartDate, $prevEndDate);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        $prevTotalSales = (float)($row['prev_total_sales'] ?: 0);
        
        // Calculate growth percentages
        $metrics['sales_growth'] = ($prevTotalSales > 0) ? 
            (($metrics['total_sales'] - $prevTotalSales) / $prevTotalSales) * 100 : 0;
            
        // Get day-over-day comparison
        if ($reportType == 'sales') {
            $stmt = $conn->prepare("SELECT DATE(created_at) as sale_date, 
                                  COALESCE(SUM(total_amount), 0) as day_sales
                                  FROM sales
                                  WHERE DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
                                  AND payment_status = 'paid'
                                  GROUP BY DATE(created_at)");
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            
            $yesterdaySales = (float)($row['day_sales'] ?? 0);
            
            $stmt = $conn->prepare("SELECT DATE(created_at) as sale_date, 
                                  COALESCE(SUM(total_amount), 0) as day_sales
                                  FROM sales
                                  WHERE DATE(created_at) = CURDATE()
                                  AND payment_status = 'paid'
                                  GROUP BY DATE(created_at)");
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            
            $todaySales = (float)($row['day_sales'] ?? 0);
            
            $metrics['today_sales'] = $todaySales;
            $metrics['yesterday_sales'] = $yesterdaySales;
            $metrics['daily_growth'] = ($yesterdaySales > 0) ? 
                (($todaySales - $yesterdaySales) / $yesterdaySales) * 100 : 0;
        }
        
    } catch (Exception $e) {
        error_log('Error in getSummaryMetrics: ' . $e->getMessage());
    }
    
    return $metrics;
}

// Function to format currency
function formatCurrency($amount) {
    return '$' . number_format($amount, 2);
}

// Get data based on report type for initial page load
$reportData = [];
$reportTitle = '';
$metrics = [];

try {
    switch ($reportType) {
        case 'sales':
            $reportData = getDailySalesData($conn, $startDate, $endDate);
            $reportTitle = 'Daily Sales Report';
            $metrics = getSummaryMetrics($conn, $startDate, $endDate, $reportType);
            break;
            
        case 'products':
            $reportData = getProductSalesData($conn, $startDate, $endDate);
            $reportTitle = 'Product Sales Report';
            $metrics = getSummaryMetrics($conn, $startDate, $endDate, $reportType);
            break;
            
        case 'categories':
            $reportData = getCategorySalesData($conn, $startDate, $endDate);
            $reportTitle = 'Category Sales Report';
            $metrics = getSummaryMetrics($conn, $startDate, $endDate, $reportType);
            break;
            
        case 'cashiers':
            $reportData = getCashierPerformanceData($conn, $startDate, $endDate);
            $reportTitle = 'Cashier Performance Report';
            $metrics = getSummaryMetrics($conn, $startDate, $endDate, $reportType);
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
} catch (Exception $e) {
    $error = "An error occurred while generating the report: " . $e->getMessage();
}

// Set page title
$pageTitle = "Reports";

// Include header
include_once '../includes/header.php';
?>

<!-- Custom CSS for Reports Page -->
<style>
:root {
    --primary-color: #4e73df;
    --primary-hover: #2e59d9;
    --success-color: #1cc88a;
    --info-color: #36b9cc;
    --warning-color: #f6c23e;
    --danger-color: #e74a3b;
    --light-color: #f8f9fc;
    --dark-color: #5a5c69;
    --card-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
    --border-color: #e3e6f0;
    --bg-color: #f8f9fc;
    --text-color: #5a5c69;
    --card-bg: #fff;
    --header-bg: #f8f9fc;
    --border-radius: 0.35rem;
    --transition-speed: 0.3s;
}

.dark-mode {
    --primary-color: #4e73df;
    --primary-hover: #2e59d9;
    --success-color: #1cc88a;
    --info-color: #36b9cc;
    --warning-color: #f6c23e;
    --danger-color: #e74a3b;
    --light-color: #3a3b45;
    --dark-color: #f8f9fc;
    --card-shadow: 0 0.15rem 1.75rem 0 rgba(0, 0, 0, 0.25);
    --border-color: #4e4e57;
    --bg-color: #2d2d39;
    --text-color: #e3e6f0;
    --card-bg: #3a3b45;
    --header-bg: #2d2d39;
}

body {
    background-color: var(--bg-color);
    color: var(--text-color);
    transition: background-color var(--transition-speed), color var(--transition-speed);
}

.card {
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
    box-shadow: var(--card-shadow);
    background-color: var(--card-bg);
    margin-bottom: 1.5rem;
    transition: transform var(--transition-speed), box-shadow var(--transition-speed);
}

.card-header {
    background-color: var(--header-bg);
    border-bottom: 1px solid var(--border-color);
    padding: 1rem 1.25rem;
}

.card:hover {
    transform: translateY(-3px);
    box-shadow: 0 0.25rem 2rem 0 rgba(58, 59, 69, 0.2);
}

.metric-card {
    padding: 1.5rem;
    border-radius: var(--border-radius);
    text-align: center;
    height: 100%;
    overflow: hidden;
    position: relative;
}

.metric-card.bg-primary {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-hover) 100%);
}

.metric-card.bg-success {
    background: linear-gradient(135deg, var(--success-color) 0%, #0ea56c 100%);
}

.metric-card.bg-info {
    background: linear-gradient(135deg, var(--info-color) 0%, #2a96a5 100%);
}

.metric-card.bg-warning {
    background: linear-gradient(135deg, var(--warning-color) 0%, #dda20a 100%);
}

.metric-icon {
    font-size: 2.5rem;
    margin-bottom: 1rem;
    opacity: 0.8;
}

.metric-value {
    font-size: 2rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
}

.metric-label {
    text-transform: uppercase;
    font-size: 0.875rem;
    opacity: 0.8;
}

.trend-indicator {
    position: absolute;
    top: 1rem;
    right: 1rem;
    font-size: 1rem;
}

.trend-indicator.up {
    color: #4ceb95;
}

.trend-indicator.down {
    color: #ff8282;
}

.table {
    color: var(--text-color);
    margin-bottom: 0;
}

.table-striped tbody tr:nth-of-type(odd) {
    background-color: rgba(0, 0, 0, 0.05);
}

.dark-mode .table-striped tbody tr:nth-of-type(odd) {
    background-color: rgba(255, 255, 255, 0.05);
}

.table th {
    border-top: none;
    font-weight: 600;
}

.form-control, .form-select {
    border-color: var(--border-color);
    background-color: var(--card-bg);
    color: var(--text-color);
    transition: border-color var(--transition-speed), box-shadow var(--transition-speed);
}

.form-control:focus, .form-select:focus {
    border-color: var(--primary-color);
    background-color: var(--card-bg);
    color: var(--text-color);
    box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
}

.btn-primary {
    background-color: var(--primary-color);
    border-color: var(--primary-color);
}

.btn-primary:hover {
    background-color: var(--primary-hover);
    border-color: var(--primary-hover);
}

.chart-container {
    position: relative;
    margin: auto;
    height: 300px;
}

.badge {
    font-weight: 600;
    padding: 0.35em 0.65em;
}

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
    justify-content: center;
    align-items: center;
    cursor: pointer;
    box-shadow: 0 0.25rem 1rem rgba(0, 0, 0, 0.25);
    z-index: 1000;
    transition: transform var(--transition-speed), background-color var(--transition-speed);
}

.theme-toggle:hover {
    transform: scale(1.1);
}

.report-options-dropdown {
    position: relative;
}

.report-options-dropdown .dropdown-menu {
    min-width: 240px;
}

.report-filter-toggle {
    display: flex;
    justify-content: space-between;
    align-items: center;
    cursor: pointer;
    padding: 0.5rem 1rem;
    transition: all var(--transition-speed);
    border-radius: var(--border-radius);
}

.report-filter-toggle:hover {
    background-color: rgba(78, 115, 223, 0.1);
}

.daterangepicker {
    z-index: 1100;
    border-radius: var(--border-radius);
    border: none;
    box-shadow: var(--card-shadow);
}

/* Loading overlay */
.loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(0, 0, 0, 0.5);
    z-index: 2000;
    display: flex;
    justify-content: center;
    align-items: center;
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.3s, visibility 0.3s;
}

.loading-overlay.show {
    opacity: 1;
    visibility: visible;
}

.spinner {
    width: 50px;
    height: 50px;
    border: 5px solid rgba(255, 255, 255, 0.3);
    border-radius: 50%;
    border-top-color: #fff;
    animation: spin 1s ease-in-out infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* Animations */
.fade-in {
    opacity: 0;
    animation: fadeIn 0.5s forwards;
}

@keyframes fadeIn {
    to { opacity: 1; }
}

.scale-in {
    opacity: 0;
    transform: scale(0.9);
    animation: scaleIn 0.3s forwards;
}

@keyframes scaleIn {
    to { opacity: 1; transform: scale(1); }
}

.slide-in {
    opacity: 0;
    transform: translateY(20px);
    animation: slideIn 0.3s forwards;
}

@keyframes slideIn {
    to { opacity: 1; transform: translateY(0); }
}

/* Report filter panel */
#reportFilterPanel {
    overflow: hidden;
    max-height: 0;
    opacity: 0;
    transition: max-height 0.5s ease, opacity 0.3s ease, padding 0.3s ease;
    padding: 0 1.25rem;
}

#reportFilterPanel.show {
    max-height: 300px;
    opacity: 1;
    padding: 1.25rem;
}

/* Additional styles for datepicker */
.daterangepicker td.active, .daterangepicker td.active:hover {
    background-color: var(--primary-color);
}

.daterangepicker .ranges li.active {
    background-color: var(--primary-color);
}

/* Status badges */
.status-badge {
    padding: 0.4rem 0.6rem;
    border-radius: 30px;
    font-weight: 600;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Print styles */
@media print {
    .no-print {
        display: none !important;
    }
    
    .container-fluid {
        width: 100%;
        max-width: 100%;
    }
    
    .card {
        break-inside: avoid;
        box-shadow: none !important;
        border: 1px solid #ddd;
    }
    
    .metric-card {
        color: #000 !important;
        background: #f9f9f9 !important;
        border: 1px solid #ddd;
    }
    
    .metric-card * {
        color: #000 !important;
    }
    
    .table {
        width: 100% !important;
        color: #000 !important;
    }
    
    .table th, .table td {
        background-color: #fff !important;
        color: #000 !important;
    }
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .metric-card {
        margin-bottom: 1rem;
    }
    
    .metric-value {
        font-size: 1.5rem;
    }
    
    .chart-container {
        height: 250px;
    }
    
    .theme-toggle {
        bottom: 20px;
        right: 20px;
        width: 40px;
        height: 40px;
    }
    
    .card-header {
        padding: 0.75rem 1rem;
    }
}
</style>

<!-- Loading Overlay -->
<div class="loading-overlay" id="loadingOverlay">
    <div class="spinner"></div>
</div>

<div class="container-fluid">
    <div class="row">
        <!-- Include sidebar -->
        <?php include_once '../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Reports Dashboard</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="toggleFilterBtn">
                            <i class="fas fa-filter"></i> Filter
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="refreshBtn">
                            <i class="fas fa-sync-alt"></i> Refresh
                        </button>
                    </div>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="exportDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-download"></i> Export
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="exportDropdown">
                            <li><button class="dropdown-item" id="exportCSV"><i class="fas fa-file-csv me-2"></i> CSV</button></li>
                            <li><button class="dropdown-item" id="exportExcel"><i class="fas fa-file-excel me-2"></i> Excel</button></li>
                            <li><button class="dropdown-item" id="exportPDF"><i class="fas fa-file-pdf me-2"></i> PDF</button></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><button class="dropdown-item" id="printReport"><i class="fas fa-print me-2"></i> Print</button></li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <!-- Alerts Container -->
            <div id="alertsContainer">
                <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($success)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i> <?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Report Filter Panel -->
            <div class="card mb-4 no-print">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-chart-bar me-2"></i> Report Options
                    </h5>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="darkModeSwitch">
                        <label class="form-check-label" for="darkModeSwitch">Dark Mode</label>
                    </div>
                </div>
                <div id="reportFilterPanel" class="<?php echo isset($_GET['filter']) ? 'show' : ''; ?>">
                    <form id="reportFilterForm" method="get" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="row g-3">
                        <input type="hidden" name="filter" value="1">
                        
                        <div class="col-md-4 col-sm-6">
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
                        
                        <div class="col-md-4 col-sm-6">
                            <label for="timeframe" class="form-label">Timeframe</label>
                            <select class="form-select" id="timeframe" name="timeframe">
                                <option value="today" <?php echo ($timeframe == 'today') ? 'selected' : ''; ?>>Today</option>
                                <option value="yesterday" <?php echo ($timeframe == 'yesterday') ? 'selected' : ''; ?>>Yesterday</option>
                                <option value="last7days" <?php echo ($timeframe == 'last7days') ? 'selected' : ''; ?>>Last 7 Days</option>
                                <option value="last30days" <?php echo ($timeframe == 'last30days') ? 'selected' : ''; ?>>Last 30 Days</option>
                                <option value="thismonth" <?php echo ($timeframe == 'thismonth') ? 'selected' : ''; ?>>This Month</option>
                                <option value="lastmonth" <?php echo ($timeframe == 'lastmonth') ? 'selected' : ''; ?>>Last Month</option>
                                <option value="custom" <?php echo ($timeframe == 'custom') ? 'selected' : ''; ?>>Custom Range</option>
                            </select>
                        </div>
                        
                        <div class="col-md-4 col-sm-12">
                            <label for="daterange" class="form-label">Date Range</label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="daterange" name="daterange" value="<?php echo date('m/d/Y', strtotime($startDate)); ?> - <?php echo date('m/d/Y', strtotime($endDate)); ?>">
                                <input type="hidden" id="start_date" name="start_date" value="<?php echo $startDate; ?>">
                                <input type="hidden" id="end_date" name="end_date" value="<?php echo $endDate; ?>">
                                <button class="btn btn-outline-secondary" type="button" id="clearDateRange">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search me-1"></i> Generate Report
                            </button>
                            <button type="button" class="btn btn-secondary ms-2" id="resetFilters">
                                <i class="fas fa-undo me-1"></i> Reset Filters
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Report Content -->
            <div id="reportContainer">
                <!-- Report Header -->
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 id="reportTitle"><?php echo $reportTitle; ?></h4>
                    <span class="badge bg-primary" id="reportDateRange">
                        <?php echo date('M d, Y', strtotime($startDate)); ?> - <?php echo date('M d, Y', strtotime($endDate)); ?>
                    </span>
                </div>
                
                <!-- Metrics Cards -->
                <?php if (!empty($metrics) && ($reportType == 'sales' || $reportType == 'products' || $reportType == 'categories' || $reportType == 'cashiers')): ?>
                <div class="row mb-4" id="metricsCards">
                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="card metric-card bg-primary text-white">
                            <div class="trend-indicator <?php echo $metrics['sales_growth'] >= 0 ? 'up' : 'down'; ?>">
                                <i class="fas fa-<?php echo $metrics['sales_growth'] >= 0 ? 'arrow-up' : 'arrow-down'; ?>"></i>
                                <?php echo abs(round($metrics['sales_growth'], 1)); ?>%
                            </div>
                            <div class="metric-icon">
                                <i class="fas fa-dollar-sign"></i>
                            </div>
                            <div class="metric-value" id="totalSales">
                                <?php echo formatCurrency($metrics['total_sales']); ?>
                            </div>
                            <div class="metric-label">Total Sales</div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="card metric-card bg-success text-white">
                            <div class="metric-icon">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <div class="metric-value" id="totalProfit">
                                <?php echo formatCurrency($metrics['total_profit']); ?>
                            </div>
                            <div class="metric-label">Total Profit</div>
                            <div class="mt-2 small">
                                <?php 
                                $profitMargin = ($metrics['total_sales'] > 0) ? 
                                    ($metrics['total_profit'] / $metrics['total_sales']) * 100 : 0;
                                echo number_format($profitMargin, 1) . '% Margin';
                                ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="card metric-card bg-info text-white">
                            <div class="metric-icon">
                                <i class="fas fa-receipt"></i>
                            </div>
                            <div class="metric-value" id="totalInvoices">
                                <?php echo number_format($metrics['total_invoices']); ?>
                            </div>
                            <div class="metric-label">Transactions</div>
                            <div class="mt-2 small">
                                <?php echo formatCurrency($metrics['avg_sale']); ?> Average Sale
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="card metric-card bg-warning text-dark">
                            <div class="metric-icon">
                                <i class="fas fa-shopping-basket"></i>
                            </div>
                            <div class="metric-value" id="totalItems">
                                <?php echo number_format($metrics['total_items']); ?>
                            </div>
                            <div class="metric-label">Items Sold</div>
                            <div class="mt-2 small">
                                <?php 
                                $itemsPerTransaction = ($metrics['total_invoices'] > 0) ? 
                                    $metrics['total_items'] / $metrics['total_invoices'] : 0;
                                echo number_format($itemsPerTransaction, 1) . ' Items/Transaction';
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Report Content -->
                <div class="card" id="reportContent">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0" id="reportContentTitle">
                            <?php echo $reportTitle; ?> - Details
                        </h5>
                        <span class="badge bg-primary">
                            <?php echo count($reportData); ?> Records
                        </span>
                    </div>
                    <div class="card-body">
                        <?php if ($reportType == 'sales' || $reportType == 'products' || $reportType == 'categories' || $reportType == 'cashiers'): ?>
                        <!-- Chart Container -->
                        <div class="chart-container mb-4">
                            <canvas id="reportChart"></canvas>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Report Tables -->
                        <div class="table-responsive">
                            <?php if ($reportType == 'sales'): ?>
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
                                                    <td><?php echo formatCurrency($item['num_transactions'] > 0 ? $item['total_sales'] / $item['num_transactions'] : 0); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="4" class="text-center">No data available for the selected period.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            <?php elseif ($reportType == 'products'): ?>
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
                                                    <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                                                    <td><?php echo $item['quantity_sold']; ?></td>
                                                    <td><?php echo formatCurrency($item['total_sales']); ?></td>
                                                    <td><?php echo formatCurrency($item['quantity_sold'] > 0 ? $item['total_sales'] / $item['quantity_sold'] : 0); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="4" class="text-center">No data available for the selected period.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            <?php elseif ($reportType == 'categories'): ?>
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
                                                    <td><?php echo htmlspecialchars($item['category_name'] ?: 'Uncategorized'); ?></td>
                                                    <td><?php echo $item['quantity_sold']; ?></td>
                                                    <td><?php echo formatCurrency($item['total_sales']); ?></td>
                                                    <td><?php echo number_format(($totalCategorySales > 0 ? ($item['total_sales'] / $totalCategorySales) * 100 : 0), 2); ?>%</td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="4" class="text-center">No data available for the selected period.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            <?php elseif ($reportType == 'cashiers'): ?>
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
                                                    <td><?php echo htmlspecialchars($item['cashier_name']); ?></td>
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
                            <?php elseif ($reportType == 'inventory'): ?>
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
                                                    <td><?php echo htmlspecialchars($item['name']); ?></td>
                                                    <td><?php echo htmlspecialchars($item['category_name'] ?: 'Uncategorized'); ?></td>
                                                    <td><?php echo $item['stock_quantity']; ?></td>
                                                    <td><?php echo $item['reorder_level']; ?></td>
                                                    <td>
                                                        <?php if ($item['stock_quantity'] <= 0): ?>
                                                            <span class="status-badge bg-danger text-white">Out of Stock</span>
                                                        <?php elseif ($item['stock_quantity'] <= $item['reorder_level']): ?>
                                                            <span class="status-badge bg-warning text-dark">Low Stock</span>
                                                        <?php else: ?>
                                                            <span class="status-badge bg-success text-white">In Stock</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo formatCurrency($item['price']); ?></td>
                                                    <td><?php echo formatCurrency($item['cost_price']); ?></td>
                                                    <td><?php echo formatCurrency($item['profit_margin']); ?> (<?php echo number_format(($item['price'] > 0 ? ($item['profit_margin'] / $item['price']) * 100 : 0), 1); ?>%)</td>
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
                            <?php elseif ($reportType == 'low_stock'): ?>
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
                                                    <td><?php echo htmlspecialchars($item['name']); ?></td>
                                                    <td><?php echo htmlspecialchars($item['category_name'] ?: 'Uncategorized'); ?></td>
                                                    <td><?php echo $item['stock_quantity']; ?></td>
                                                    <td><?php echo $item['reorder_level']; ?></td>
                                                    <td><?php echo $item['monthly_demand'] ?: 0; ?></td>
                                                    <td>
                                                        <?php if ($item['stock_quantity'] <= 0): ?>
                                                            <span class="status-badge bg-danger text-white">Out of Stock</span>
                                                        <?php else: ?>
                                                            <span class="status-badge bg-warning text-dark">Low Stock</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <a href="stock_adjustment.php?product=<?php echo $item['id']; ?>" class="btn btn-sm btn-primary">
                                                            <i class="fas fa-plus"></i> Add Stock
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
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Theme Toggle Button -->
<div class="theme-toggle no-print" id="themeToggle" title="Toggle Theme">
    <i class="fas fa-moon"></i>
</div>

<!-- Include Required JavaScript Libraries -->
<script src="https://cdn.jsdelivr.net/npm/moment@2.29.1/min/moment.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-moment@1.0.0"></script>
<script src="https://cdn.jsdelivr.net/npm/daterangepicker@3.1.0/daterangepicker.min.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/daterangepicker@3.1.0/daterangepicker.css">

<!-- SheetJS (Excel Export) -->
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<!-- jsPDF (PDF Export) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize variables
    let reportChart = null;
    let darkMode = localStorage.getItem('darkMode') === 'true';
    const reportType = '<?php echo $reportType; ?>';
    let startDate = '<?php echo $startDate; ?>';
    let endDate = '<?php echo $endDate; ?>';
    
    // Initialize dark mode if enabled
    if (darkMode) {
        document.body.classList.add('dark-mode');
        document.getElementById('darkModeSwitch').checked = true;
        
        // Update toggle icon
        const themeToggle = document.getElementById('themeToggle');
        if (themeToggle) {
            themeToggle.innerHTML = '<i class="fas fa-sun"></i>';
        }
    }
    
    // Initialize loading spinner
    const loadingOverlay = document.getElementById('loadingOverlay');
    function showLoading() {
        loadingOverlay.classList.add('show');
    }
    
    function hideLoading() {
        loadingOverlay.classList.remove('show');
    }
    
    // Filter panel toggle
    const toggleFilterBtn = document.getElementById('toggleFilterBtn');
    const reportFilterPanel = document.getElementById('reportFilterPanel');
    
    if (toggleFilterBtn && reportFilterPanel) {
        toggleFilterBtn.addEventListener('click', function() {
            reportFilterPanel.classList.toggle('show');
        });
    }
    
    // Initialize Date Range Picker
    $('#daterange').daterangepicker({
        startDate: moment(startDate),
        endDate: moment(endDate),
        ranges: {
           'Today': [moment(), moment()],
           'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
           'Last 7 Days': [moment().subtract(6, 'days'), moment()],
           'Last 30 Days': [moment().subtract(29, 'days'), moment()],
           'This Month': [moment().startOf('month'), moment().endOf('month')],
           'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
        }
    }, function(start, end) {
        document.getElementById('start_date').value = start.format('YYYY-MM-DD');
        document.getElementById('end_date').value = end.format('YYYY-MM-DD');
        
        // Set timeframe to custom since user selected custom dates
        document.getElementById('timeframe').value = 'custom';
    });
    
    // Timeframe selector handler
    document.getElementById('timeframe').addEventListener('change', function() {
        const value = this.value;
        let start, end;
        
        switch (value) {
            case 'today':
                start = moment();
                end = moment();
                break;
                
            case 'yesterday':
                start = moment().subtract(1, 'days');
                end = moment().subtract(1, 'days');
                break;
                
            case 'last7days':
                start = moment().subtract(6, 'days');
                end = moment();
                break;
                
            case 'last30days':
                start = moment().subtract(29, 'days');
                end = moment();
                break;
                
            case 'thismonth':
                start = moment().startOf('month');
                end = moment().endOf('month');
                break;
                
            case 'lastmonth':
                start = moment().subtract(1, 'month').startOf('month');
                end = moment().subtract(1, 'month').endOf('month');
                break;
                
            case 'custom':
                // Don't change the current daterangepicker values
                return;
        }
        
        // Update daterangepicker
        $('#daterange').data('daterangepicker').setStartDate(start);
        $('#daterange').data('daterangepicker').setEndDate(end);
        
        // Update hidden inputs
        document.getElementById('start_date').value = start.format('YYYY-MM-DD');
        document.getElementById('end_date').value = end.format('YYYY-MM-DD');
    });
    
    // Clear date range button
    document.getElementById('clearDateRange').addEventListener('click', function() {
        // Reset to default (last 30 days)
        const defaultStart = moment().subtract(29, 'days');
        const defaultEnd = moment();
        
        $('#daterange').data('daterangepicker').setStartDate(defaultStart);
        $('#daterange').data('daterangepicker').setEndDate(defaultEnd);
        
        document.getElementById('start_date').value = defaultStart.format('YYYY-MM-DD');
        document.getElementById('end_date').value = defaultEnd.format('YYYY-MM-DD');
    });
    
    // Reset filters button
    document.getElementById('resetFilters').addEventListener('click', function() {
        window.location.href = 'reports.php';
    });
});