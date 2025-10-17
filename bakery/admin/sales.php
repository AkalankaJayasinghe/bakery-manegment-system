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

// Function to safely get POST/GET values
function getInputValue($type, $key, $default = '') {
    if ($type === 'POST' && isset($_POST[$key])) {
        return htmlspecialchars(trim($_POST[$key]));
    } elseif ($type === 'GET' && isset($_GET[$key])) {
        return htmlspecialchars(trim($_GET[$key]));
    }
    return $default;
}

// AJAX response handling
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    $response = array('status' => 'error', 'message' => 'Invalid action');
    
    // Handle AJAX requests
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
        
        switch ($_POST['ajax_action']) {
            case 'add_sale':
                $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
                $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 0;
                $sale_date = isset($_POST['sale_date']) ? $_POST['sale_date'] : date('Y-m-d');
                $customer_name = isset($_POST['customer_name']) ? $_POST['customer_name'] : null;
                $total_amount = isset($_POST['total_amount']) ? floatval($_POST['total_amount']) : 0;
                
                // Validation
                if ($product_id <= 0 || $quantity <= 0 || $total_amount <= 0) {
                    $response['message'] = 'Invalid product, quantity or amount';
                    break;
                }
                
                // Prepared statement
                $stmt = mysqli_prepare($conn, "INSERT INTO sales (product_id, quantity, sale_date, customer_name, total_amount, user_id) 
                                              VALUES (?, ?, ?, ?, ?, ?)");
                
                mysqli_stmt_bind_param($stmt, "iissdi", $product_id, $quantity, $sale_date, $customer_name, $total_amount, $_SESSION['user_id']);
                
                if (mysqli_stmt_execute($stmt)) {
                    // Get the newly inserted sale ID
                    $sale_id = mysqli_insert_id($conn);
                    
                    // Get product and user details for the response
                    $sale_query = "SELECT s.*, p.name AS product_name, u.username 
                                   FROM sales s
                                   JOIN products p ON s.product_id = p.id
                                   LEFT JOIN users u ON s.user_id = u.id
                                   WHERE s.id = ?";
                    $sale_stmt = mysqli_prepare($conn, $sale_query);
                    mysqli_stmt_bind_param($sale_stmt, "i", $sale_id);
                    mysqli_stmt_execute($sale_stmt);
                    $sale_result = mysqli_stmt_get_result($sale_stmt);
                    $sale_data = mysqli_fetch_assoc($sale_result);
                    
                    // Log the activity
                    logActivity($conn, 'Added new sale', $_SESSION['user_id']);
                    
                    $response = array(
                        'status' => 'success',
                        'message' => 'Sale added successfully!',
                        'sale' => $sale_data
                    );
                } else {
                    $response['message'] = 'Database error: ' . mysqli_error($conn);
                }
                break;
                
            case 'delete_sale':
                $sale_id = isset($_POST['sale_id']) ? intval($_POST['sale_id']) : 0;
                
                if ($sale_id <= 0) {
                    $response['message'] = 'Invalid sale ID';
                    break;
                }
                
                // Prepared statement
                $stmt = mysqli_prepare($conn, "DELETE FROM sales WHERE id = ?");
                mysqli_stmt_bind_param($stmt, "i", $sale_id);
                
                if (mysqli_stmt_execute($stmt)) {
                    // Log the activity
                    logActivity($conn, 'Deleted sale ID: ' . $sale_id, $_SESSION['user_id']);
                    
                    $response = array(
                        'status' => 'success',
                        'message' => 'Sale deleted successfully!',
                        'sale_id' => $sale_id
                    );
                } else {
                    $response['message'] = 'Database error: ' . mysqli_error($conn);
                }
                break;
                
            case 'get_product_price':
                $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
                
                if ($product_id <= 0) {
                    $response['message'] = 'Invalid product ID';
                    break;
                }
                
                // Prepared statement
                $stmt = mysqli_prepare($conn, "SELECT price FROM products WHERE id = ?");
                mysqli_stmt_bind_param($stmt, "i", $product_id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_bind_result($stmt, $price);
                
                if (mysqli_stmt_fetch($stmt)) {
                    $response = array(
                        'status' => 'success',
                        'price' => $price
                    );
                } else {
                    $response['message'] = 'Product not found';
                }
                break;
                
            case 'get_sales_stats':
                // Get today's sales
                $today = date('Y-m-d');
                $stmt = mysqli_prepare($conn, "SELECT COUNT(*) as count, SUM(total_amount) as total FROM sales WHERE DATE(sale_date) = ?");
                mysqli_stmt_bind_param($stmt, "s", $today);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $today_stats = mysqli_fetch_assoc($result);
                
                // Get this week's sales
                $week_start = date('Y-m-d', strtotime('monday this week'));
                $week_end = date('Y-m-d', strtotime('sunday this week'));
                $stmt = mysqli_prepare($conn, "SELECT COUNT(*) as count, SUM(total_amount) as total FROM sales WHERE DATE(sale_date) BETWEEN ? AND ?");
                mysqli_stmt_bind_param($stmt, "ss", $week_start, $week_end);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $week_stats = mysqli_fetch_assoc($result);
                
                // Get this month's sales
                $month_start = date('Y-m-01');
                $month_end = date('Y-m-t');
                $stmt = mysqli_prepare($conn, "SELECT COUNT(*) as count, SUM(total_amount) as total FROM sales WHERE DATE(sale_date) BETWEEN ? AND ?");
                mysqli_stmt_bind_param($stmt, "ss", $month_start, $month_end);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $month_stats = mysqli_fetch_assoc($result);
                
                // Get top 5 products by sales
                $top_products_query = "SELECT p.name, SUM(s.quantity) as total_quantity, SUM(s.total_amount) as total_amount
                                      FROM sales s
                                      JOIN products p ON s.product_id = p.id
                                      GROUP BY p.id
                                      ORDER BY total_amount DESC
                                      LIMIT 5";
                $top_products_result = mysqli_query($conn, $top_products_query);
                $top_products = array();
                while ($row = mysqli_fetch_assoc($top_products_result)) {
                    $top_products[] = $row;
                }
                
                // Get daily sales for the past 7 days for chart
                $daily_sales = array();
                for ($i = 6; $i >= 0; $i--) {
                    $date = date('Y-m-d', strtotime("-$i days"));
                    $stmt = mysqli_prepare($conn, "SELECT COALESCE(SUM(total_amount), 0) as total FROM sales WHERE DATE(sale_date) = ?");
                    mysqli_stmt_bind_param($stmt, "s", $date);
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_bind_result($stmt, $total);
                    mysqli_stmt_fetch($stmt);
                    
                    $daily_sales[] = array(
                        'date' => date('D', strtotime($date)),
                        'full_date' => $date,
                        'total' => (float)$total
                    );
                }
                
                $response = array(
                    'status' => 'success',
                    'today' => array(
                        'count' => (int)$today_stats['count'],
                        'total' => (float)$today_stats['total']
                    ),
                    'week' => array(
                        'count' => (int)$week_stats['count'],
                        'total' => (float)$week_stats['total']
                    ),
                    'month' => array(
                        'count' => (int)$month_stats['count'],
                        'total' => (float)$month_stats['total']
                    ),
                    'top_products' => $top_products,
                    'daily_sales' => $daily_sales
                );
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

// Check if sales table exists and has necessary columns
$table_check_query = "SHOW TABLES LIKE 'sales'";
$table_check_result = mysqli_query($conn, $table_check_query);
$table_exists = mysqli_num_rows($table_check_result) > 0;

if (!$table_exists) {
    // Create sales table if it doesn't exist
    $create_table_query = "CREATE TABLE `sales` ( `id` int(11) NOT NULL AUTO_INCREMENT, `product_id` int(11) NOT NULL, `quantity` int(11) NOT NULL DEFAULT 1, `sale_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP, `customer_name` varchar(255) DEFAULT NULL, `total_amount` decimal(10,2) NOT NULL, `user_id` int(11) DEFAULT NULL, `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (`id`), KEY `product_id` (`product_id`), KEY `user_id` (`user_id`) ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    mysqli_query($conn, $create_table_query);
    
    // Log table creation
    logActivity($conn, 'Created sales table', $_SESSION['user_id']);
}

// Handle non-AJAX form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['ajax_action'])) {
    if (isset($_POST['action'])) {
        // Add new sale
        if ($_POST['action'] === 'add') {
            // Sanitize and validate inputs
            $product_id = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
            $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);
            $sale_date = filter_input(INPUT_POST, 'sale_date', );
            $customer_name = filter_input(INPUT_POST, 'customer_name', );
            $total_amount = filter_input(INPUT_POST, 'total_amount', FILTER_VALIDATE_FLOAT);
            
            // Validate inputs
            if (!$product_id || !$quantity || !$sale_date || !$total_amount) {
                $error_message = "Please provide valid inputs for all required fields.";
            } else {
                // Prepared statement
                $stmt = mysqli_prepare($conn, "INSERT INTO sales (product_id, quantity, sale_date, customer_name, total_amount, user_id) 
                                             VALUES (?, ?, ?, ?, ?, ?)");
                
                mysqli_stmt_bind_param($stmt, "iissdi", $product_id, $quantity, $sale_date, $customer_name, $total_amount, $_SESSION['user_id']);
                
                if (mysqli_stmt_execute($stmt)) {
                    // Log the activity
                    logActivity($conn, 'Added new sale', $_SESSION['user_id']);
                    $success_message = "Sale added successfully!";
                } else {
                    $error_message = "Error: " . mysqli_error($conn);
                }
            }
        }
        
        // Delete sale
        if ($_POST['action'] === 'delete' && isset($_POST['sale_id'])) {
            $sale_id = filter_input(INPUT_POST, 'sale_id', FILTER_VALIDATE_INT);
            
            if (!$sale_id) {
                $error_message = "Invalid sale ID.";
            } else {
                // Prepared statement
                $stmt = mysqli_prepare($conn, "DELETE FROM sales WHERE id = ?");
                mysqli_stmt_bind_param($stmt, "i", $sale_id);
                
                if (mysqli_stmt_execute($stmt)) {
                    // Log the activity
                    logActivity($conn, 'Deleted sale ID: ' . $sale_id, $_SESSION['user_id']);
                    $success_message = "Sale deleted successfully!";
                } else {
                    $error_message = "Error: " . mysqli_error($conn);
                }
            }
        }
    }
}

// Get products for dropdown
$products_query = "SELECT * FROM products ORDER BY name ASC";
$products_result = mysqli_query($conn, $products_query);

// Safe query that checks for column existence
$column_check_query = "SHOW COLUMNS FROM sales LIKE 'product_id'";
$column_check_result = mysqli_query($conn, $column_check_query);
$product_id_exists = mysqli_num_rows($column_check_result) > 0;

// Handle search and filtering
$search = getInputValue('GET', 'search');
$date_from = getInputValue('GET', 'date_from');
$date_to = getInputValue('GET', 'date_to');
$sort_by = getInputValue('GET', 'sort_by', 's.sale_date');
$sort_dir = getInputValue('GET', 'sort_dir', 'DESC');

// Validate sort parameters to prevent SQL injection
$allowed_sort_fields = ['s.id', 'p.name', 's.quantity', 's.sale_date', 's.customer_name', 's.total_amount', 'u.username'];
if (!in_array($sort_by, $allowed_sort_fields)) {
    $sort_by = 's.sale_date';
}
$sort_dir = strtoupper($sort_dir) === 'ASC' ? 'ASC' : 'DESC';

if ($product_id_exists) {
    // Base query
    $query = "SELECT s.*, p.name AS product_name, u.username 
              FROM sales s
              JOIN products p ON s.product_id = p.id
              LEFT JOIN users u ON s.user_id = u.id";
    
    // Build WHERE clause
    $where_clauses = [];
    $params = [];
    $types = "";
    
    if (!empty($search)) {
        $where_clauses[] = "(p.name LIKE ? OR s.customer_name LIKE ?)";
        $search_param = "%$search%";
        array_push($params, $search_param, $search_param);
        $types .= "ss";
    }
    
    if (!empty($date_from) && !empty($date_to)) {
        $where_clauses[] = "DATE(s.sale_date) BETWEEN ? AND ?";
        array_push($params, $date_from, $date_to);
        $types .= "ss";
    }
    
    // Add WHERE clause if needed
    if (!empty($where_clauses)) {
        $query .= " WHERE " . implode(" AND ", $where_clauses);
    }
    
    // Add ORDER BY clause
    $query .= " ORDER BY $sort_by $sort_dir";
    
    // Prepare and execute the statement
    $stmt = mysqli_prepare($conn, $query);
    
    if (!empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    // Get total records for pagination
    $count_query = "SELECT COUNT(*) as total FROM sales s
                   JOIN products p ON s.product_id = p.id
                   LEFT JOIN users u ON s.user_id = u.id";
                   
    if (!empty($where_clauses)) {
        $count_query .= " WHERE " . implode(" AND ", $where_clauses);
    }
    
    $count_stmt = mysqli_prepare($conn, $count_query);
    
    if (!empty($params)) {
        mysqli_stmt_bind_param($count_stmt, $types, ...$params);
    }
    
    mysqli_stmt_execute($count_stmt);
    $count_result = mysqli_stmt_get_result($count_stmt);
    $count_row = mysqli_fetch_assoc($count_result);
    $total_records = $count_row['total'];
    
    // Pagination
    $current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $records_per_page = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
    $total_pages = ceil($total_records / $records_per_page);
    
    // Adjust query for pagination
    $offset = ($current_page - 1) * $records_per_page;
    $query .= " LIMIT ?, ?";
    
    $stmt = mysqli_prepare($conn, $query);
    
    if (!empty($params)) {
        array_push($params, $offset, $records_per_page);
        $types .= "ii";
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    } else {
        mysqli_stmt_bind_param($stmt, "ii", $offset, $records_per_page);
    }
    
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
} else {
    // Use a simpler query if product_id doesn't exist
    $result = false;
    $error_message = "Sales table structure is not complete. Please run the database migration script.";
    $total_records = 0;
    $total_pages = 0;
    $current_page = 1;
    $records_per_page = 10;
}

// Page title
$page_title = "Manage Sales";

// Include header
include 'includes/header.php';
?>

<!-- Custom CSS -->
<style>
:root {
    --primary-color: #4e73df;
    --primary-dark: #2e59d9;
    --success-color: #1cc88a;
    --info-color: #36b9cc;
    --warning-color: #f6c23e;
    --danger-color: #e74a3b;
    --secondary-color: #858796;
    --light-color: #f8f9fa;
    --dark-color: #5a5c69;
    --card-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
    --border-radius: 0.35rem;
    --transition-speed: 0.3s;
    --bg-color: #f8f9fc;
    --text-color: #333;
    --card-bg: #fff;
    --input-bg: #fff;
    --input-border: #d1d3e2;
    --input-focus-border: #bac8f3;
    --card-header-bg: #f8f9fc;
}

.dark-mode {
    --primary-color: #4e73df;
    --primary-dark: #2e59d9;
    --success-color: #1cc88a;
    --info-color: #36b9cc;
    --warning-color: #f6c23e;
    --danger-color: #e74a3b;
    --secondary-color: #858796;
    --light-color: #393e46;
    --dark-color: #e0e0e0;
    --card-shadow: 0 0.15rem 1.75rem 0 rgba(0, 0, 0, 0.25);
    --border-radius: 0.35rem;
    --transition-speed: 0.3s;
    --bg-color: #222831;
    --text-color: #e0e0e0;
    --card-bg: #393e46;
    --input-bg: #2c3440;
    --input-border: #4a5568;
    --input-focus-border: #4e73df;
    --card-header-bg: #2c3440;
}

body {
    background-color: var(--bg-color);
    color: var(--text-color);
    transition: background-color var(--transition-speed), color var(--transition-speed);
    font-family: 'Nunito', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
}

.card {
    border: none;
    border-radius: var(--border-radius);
    box-shadow: var(--card-shadow);
    transition: transform var(--transition-speed), box-shadow var(--transition-speed);
    margin-bottom: 1.5rem;
    background-color: var(--card-bg);
}

.card:hover {
    transform: translateY(-3px);
    box-shadow: 0 0.5rem 2rem rgba(58, 59, 69, 0.2);
}

.card-header {
    background-color: var(--card-header-bg);
    border-bottom: 1px solid rgba(0, 0, 0, 0.125);
    padding: 1rem 1.25rem;
    font-weight: 700;
    display: flex;
    align-items: center;
}

.card-header .fas {
    margin-right: 0.5rem;
    color: var(--primary-color);
}

.form-control, .form-select {
    border-radius: 0.25rem;
    border-color: var(--input-border);
    background-color: var(--input-bg);
    color: var(--text-color);
    transition: border-color var(--transition-speed), box-shadow var(--transition-speed);
}

.form-control:focus, .form-select:focus {
    border-color: var(--input-focus-border);
    box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
}

.btn {
    border-radius: 0.25rem;
    padding: 0.375rem 1rem;
    font-weight: 600;
    transition: all var(--transition-speed);
}

.btn-primary {
    background-color: var(--primary-color);
    border-color: var(--primary-color);
}

.btn-primary:hover {
    background-color: var(--primary-dark);
    border-color: var(--primary-dark);
    transform: translateY(-2px);
}

.table {
    color: var(--text-color);
}

.table th {
    font-weight: 700;
    border-top: none;
}

.table td, .table th {
    vertical-align: middle;
    padding: 0.75rem;
    transition: background-color var(--transition-speed);
}

.table-hover tbody tr:hover {
    background-color: rgba(78, 115, 223, 0.05);
}

.stats-card {
    text-align: center;
    padding: 1.25rem;
    border-radius: var(--border-radius);
    transition: all var(--transition-speed);
    margin-bottom: 1.5rem;
    color: white;
}

.stats-card .icon {
    font-size: 2rem;
    margin-bottom: 1rem;
    opacity: 0.7;
}

.stats-card .stat-value {
    font-size: 1.75rem;
    font-weight: 700;
    margin-bottom: 0.25rem;
}

.stats-card .stat-label {
    font-size: 0.875rem;
    text-transform: uppercase;
    opacity: 0.8;
}

.bg-primary {
    background-color: var(--primary-color) !important;
}

.bg-success {
    background-color: var(--success-color) !important;
}

.bg-info {
    background-color: var(--info-color) !important;
}

.bg-warning {
    background-color: var(--warning-color) !important;
}

.badge {
    font-weight: 600;
    padding: 0.35em 0.65em;
    border-radius: 0.2rem;
}

.alert {
    border-radius: var(--border-radius);
    border-left: 4px solid transparent;
}

.alert-success {
    border-left-color: var(--success-color);
}

.alert-danger {
    border-left-color: var(--danger-color);
}

.alert-warning {
    border-left-color: var(--warning-color);
}

/* Fade-in animation */
.fade-in {
    animation: fadeIn 0.5s ease forwards;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Spinner animation */
.spinner {
    display: inline-block;
    width: 1rem;
    height: 1rem;
    border-radius: 50%;
    border: 2px solid rgba(255, 255, 255, 0.3);
    border-top-color: #fff;
    animation: spin 0.8s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* Pagination style */
.pagination {
    margin-bottom: 0;
}

.pagination .page-link {
    color: var(--primary-color);
    background-color: var(--card-bg);
    border-color: var(--input-border);
}

.pagination .page-link:hover {
    z-index: 2;
    color: var(--primary-dark);
    background-color: rgba(78, 115, 223, 0.1);
    border-color: var(--input-border);
}

.pagination .page-item.active .page-link {
    z-index: 3;
    color: #fff;
    background-color: var(--primary-color);
    border-color: var(--primary-color);
}

/* Toast notifications */
.toast-container {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 1050;
}

.toast {
    min-width: 250px;
    background-color: var(--card-bg);
    color: var(--text-color);
    box-shadow: 0 0.25rem 0.75rem rgba(0, 0, 0, 0.1);
    border-left: 4px solid var(--primary-color);
    margin-bottom: 10px;
    border-radius: var(--border-radius);
    opacity: 0;
    transform: translateX(100px);
    transition: all 0.3s ease;
}

.toast.show {
    opacity: 1;
    transform: translateX(0);
}

.toast.success { border-left-color: var(--success-color); }
.toast.error { border-left-color: var(--danger-color); }
.toast.warning { border-left-color: var(--warning-color); }
.toast.info { border-left-color: var(--info-color); }

/* Loading overlay */
.loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(0, 0, 0, 0.5);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 1060;
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.3s, visibility 0.3s;
}

.loading-overlay.show {
    opacity: 1;
    visibility: visible;
}

.loading-spinner {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    border: 4px solid rgba(255, 255, 255, 0.3);
    border-top-color: white;
    animation: spin 1s linear infinite;
}

/* Action buttons */
.action-buttons .btn {
    width: 32px;
    height: 32px;
    padding: 0;
    line-height: 32px;
    text-align: center;
    margin-right: 5px;
}

.action-buttons .btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 0.25rem 0.5rem rgba(0, 0, 0, 0.15);
}

/* Tooltip */
.tooltip {
    position: relative;
    display: inline-block;
}

/* Chart container */
.chart-container {
    position: relative;
    height: 250px;
    margin-bottom: 1rem;
}

/* Theme toggle button */
.theme-toggle {
    position: fixed;
    bottom: 20px;
    right: 20px;
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background-color: var(--primary-color);
    color: white;
    display: flex;
    justify-content: center;
    align-items: center;
    cursor: pointer;
    box-shadow: 0 0.25rem 1rem rgba(0, 0, 0, 0.2);
    z-index: 1030;
    transition: all var(--transition-speed);
}

.theme-toggle:hover {
    transform: scale(1.1);
}

/* Filter panel */
.filter-panel {
    margin-bottom: 1rem;
    transition: all var(--transition-speed);
    overflow: hidden;
}

/* Sorting indicators */
.sort-icon {
    display: inline-block;
    width: 16px;
    text-align: center;
    margin-left: 5px;
}

/* For hover effect on table rows */
.clickable-row {
    cursor: pointer;
    transition: background-color var(--transition-speed);
}

.clickable-row:hover {
    background-color: rgba(78, 115, 223, 0.05);
}

/* Responsive adjustments */
@media (max-width: 992px) {
    .stats-card {
        margin-bottom: 1rem;
    }
    
    .chart-container {
        height: 200px;
    }
}

@media (max-width: 768px) {
    .action-buttons .btn {
        width: 28px;
        height: 28px;
        line-height: 28px;
    }
    
    .stats-card .stat-value {
        font-size: 1.5rem;
    }
}

/* Price calculator animation */
.price-calculator {
    transition: all 0.3s ease;
}

.price-calculator.updated {
    background-color: rgba(78, 115, 223, 0.1);
}

/* Dropdown styles */
.dropdown-menu {
    border: none;
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
    border-radius: var(--border-radius);
}

.dropdown-item {
    transition: all 0.2s;
}

.dropdown-item:hover {
    background-color: rgba(78, 115, 223, 0.1);
}

/* Sale detail modal */
.sale-detail-modal .modal-content {
    border: none;
    border-radius: var(--border-radius);
    overflow: hidden;
    box-shadow: 0 0.5rem 2rem rgba(0, 0, 0, 0.2);
}

.sale-detail-modal .modal-header {
    background-color: var(--card-header-bg);
    border-bottom: 1px solid rgba(0, 0, 0, 0.125);
}

.sale-detail-modal .modal-body {
    background-color: var(--card-bg);
}

.sale-detail-modal .modal-footer {
    background-color: var(--card-header-bg);
    border-top: 1px solid rgba(0, 0, 0, 0.125);
}
</style>

<!-- Toast container for notifications -->
<div class="toast-container" id="toastContainer"></div>

<!-- Loading overlay -->
<div class="loading-overlay" id="loadingOverlay">
    <div class="loading-spinner"></div>
</div>

<!-- Main Content -->
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 text-gray-800 mb-0"><?php echo $page_title; ?></h1>
        <div>
            <button class="btn btn-primary" id="refreshBtn">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
            <button class="btn btn-outline-primary ms-2" id="toggleFilterBtn">
                <i class="fas fa-filter"></i> Filter
            </button>
        </div>
    </div>
    
    <!-- Alerts -->
    <div id="alertsContainer">
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i> <?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
    </div>
    
    <?php if (!$table_exists || !$product_id_exists): ?>
        <div class="card mb-4">
            <div class="card-header bg-warning text-dark">
                <i class="fas fa-exclamation-triangle"></i> Database Update Required
            </div>
            <div class="card-body">
                <h5>Your sales table needs to be updated.</h5>
                <p>Please copy and run the following SQL in your database:</p>
                <div class="bg-light p-3 rounded">
                    <code>
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
                    </code>
                </div>
                <div class="mt-3">
                    <button class="btn btn-primary" onclick="location.reload()">
                        <i class="fas fa-sync-alt"></i> Refresh Page After Update
                    </button>
                </div>
            </div>
        </div>
    <?php else: ?>
        <!-- Filter Panel -->
        <div class="card mb-4 filter-panel" id="filterPanel" style="display: none;">
            <div class="card-header">
                <i class="fas fa-filter"></i> Filter Sales
            </div>
            <div class="card-body">
                <form method="GET" action="" id="filterForm">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="search" class="form-label">Search</label>
                            <input type="text" class="form-control" id="search" name="search" placeholder="Product or customer name" value="<?php echo $search; ?>">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="date_from" class="form-label">Date From</label>
                            <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo $date_from; ?>">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="date_to" class="form-label">Date To</label>
                            <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo $date_to; ?>">
                        </div>
                        <div class="col-md-2 mb-3">
                            <label for="limit" class="form-label">Show</label>
                            <select class="form-select" id="limit" name="limit">
                                <option value="10" <?php echo ($records_per_page == 10) ? 'selected' : ''; ?>>10</option>
                                <option value="25" <?php echo ($records_per_page == 25) ? 'selected' : ''; ?>>25</option>
                                <option value="50" <?php echo ($records_per_page == 50) ? 'selected' : ''; ?>>50</option>
                                <option value="100" <?php echo ($records_per_page == 100) ? 'selected' : ''; ?>>100</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> Apply Filter
                            </button>
                            <a href="sales.php" class="btn btn-secondary ms-2">
                                <i class="fas fa-times"></i> Reset
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Sales Statistics -->
        <div class="row mb-4" id="salesStats">
            <!-- Stats will be loaded via AJAX -->
            <div class="col-xl-3 col-md-6">
                <div class="stats-card bg-primary">
                    <div class="icon"><i class="fas fa-shopping-cart"></i></div>
                    <div class="stat-value" id="todaySalesAmount">$0.00</div>
                    <div class="stat-label">Today's Sales</div>
                    <div class="mt-2 small" id="todaySalesCount">0 transactions</div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="stats-card bg-success">
                    <div class="icon"><i class="fas fa-calendar-week"></i></div>
                    <div class="stat-value" id="weekSalesAmount">$0.00</div>
                    <div class="stat-label">This Week</div>
                    <div class="mt-2 small" id="weekSalesCount">0 transactions</div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="stats-card bg-info">
                    <div class="icon"><i class="fas fa-calendar-alt"></i></div>
                    <div class="stat-value" id="monthSalesAmount">$0.00</div>
                    <div class="stat-label">This Month</div>
                    <div class="mt-2 small" id="monthSalesCount">0 transactions</div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="stats-card bg-warning">
                    <div class="icon"><i class="fas fa-chart-line"></i></div>
                    <div class="stat-value" id="avgSaleValue">$0.00</div>
                    <div class="stat-label">Avg. Sale Value</div>
                    <div class="mt-2 small" id="avgSalesGrowth">0% growth</div>
                </div>
            </div>
        </div>
        
        <div class="row mb-4">
            <!-- Sales Chart -->
            <div class="col-xl-8">
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div><i class="fas fa-chart-bar"></i> Sales Overview</div>
                        <div class="btn-group btn-group-sm" role="group">
                            <button type="button" class="btn btn-outline-primary active" id="weeklyChartBtn">Weekly</button>
                            <button type="button" class="btn btn-outline-primary" id="monthlyChartBtn">Monthly</button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="salesChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Top Products -->
            <div class="col-xl-4">
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-trophy"></i> Top Products
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table mb-0" id="topProductsTable">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th class="text-center">Qty</th>
                                        <th class="text-end">Revenue</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td colspan="3" class="text-center py-3">Loading...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <!-- Add New Sale -->
            <div class="col-lg-4">
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-plus-circle"></i> Add New Sale
                    </div>
                    <div class="card-body">
                        <form id="addSaleForm">
                            <input type="hidden" name="ajax_action" value="add_sale">
                            
                            <div class="mb-3">
                                <label for="product_id" class="form-label">Product <span class="text-danger">*</span></label>
                                <select name="product_id" id="product_id" class="form-select" required>
                                    <option value="">Select Product</option>
                                    <?php if ($products_result && mysqli_num_rows($products_result) > 0): ?>
                                        <?php while ($product = mysqli_fetch_assoc($products_result)): ?>
                                            <option value="<?php echo $product['id']; ?>" data-price="<?php echo isset($product['price']) ? $product['price'] : '0.00'; ?>">
                                                <?php echo $product['name']; ?> - $<?php echo isset($product['price']) ? number_format($product['price'], 2) : '0.00'; ?>
                                            </option>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <option value="" disabled>No products available</option>
                                    <?php endif; ?>
                                </select>
                                <div class="invalid-feedback">Please select a product</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="quantity" class="form-label">Quantity <span class="text-danger">*</span></label>
                                <input type="number" name="quantity" id="quantity" class="form-control" min="1" value="1" required>
                                <div class="invalid-feedback">Please enter a valid quantity</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="sale_date" class="form-label">Sale Date <span class="text-danger">*</span></label>
                                <input type="date" name="sale_date" id="sale_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                                <div class="invalid-feedback">Please enter a valid date</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="customer_name" class="form-label">Customer Name</label>
                                <input type="text" name="customer_name" id="customer_name" class="form-control">
                            </div>
                            
                            <div class="mb-3 price-calculator">
                                <label for="total_amount" class="form-label">Total Amount <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" name="total_amount" id="total_amount" class="form-control" step="0.01" min="0" required>
                                    <button type="button" class="btn btn-outline-secondary" id="calculateTotal">
                                        <i class="fas fa-calculator"></i>
                                    </button>
                                </div>
                                <div class="invalid-feedback">Please enter a valid amount</div>
                                <small class="form-text text-muted">Click the calculator to auto-compute total from quantity and price.</small>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary" id="submitSaleBtn">
                                    <i class="fas fa-save"></i> Add Sale
                                    <span class="spinner d-none ms-2" id="submitSpinner"></span>
                                </button>
                                <button type="reset" class="btn btn-outline-secondary">
                                    <i class="fas fa-times"></i> Reset
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Sales List -->
            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <i class="fas fa-shopping-cart"></i> Sales List 
                            <span class="badge bg-primary ms-2"><?php echo $total_records; ?> records</span>
                        </div>
                        <div class="btn-group">
                            <button type="button" class="btn btn-sm btn-outline-primary" id="exportBtn">
                                <i class="fas fa-file-export"></i> Export
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="printBtn">
                                <i class="fas fa-print"></i> Print
                            </button>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="salesTable">
                                <thead>
                                    <tr>
                                        <th>
                                            <a href="?sort_by=s.id&sort_dir=<?php echo ($sort_by === 's.id' && $sort_dir === 'ASC') ? 'DESC' : 'ASC'; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($date_from) ? '&date_from=' . urlencode($date_from) : ''; ?><?php echo !empty($date_to) ? '&date_to=' . urlencode($date_to) : ''; ?>&limit=<?php echo $records_per_page; ?>" class="text-decoration-none text-dark">
                                                #
                                                <span class="sort-icon">
                                                    <?php if ($sort_by === 's.id'): ?>
                                                        <i class="fas fa-sort-<?php echo $sort_dir === 'ASC' ? 'up' : 'down'; ?>"></i>
                                                    <?php else: ?>
                                                        <i class="fas fa-sort"></i>
                                                    <?php endif; ?>
                                                </span>
                                            </a>
                                        </th>
                                        <th>
                                            <a href="?sort_by=p.name&sort_dir=<?php echo ($sort_by === 'p.name' && $sort_dir === 'ASC') ? 'DESC' : 'ASC'; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($date_from) ? '&date_from=' . urlencode($date_from) : ''; ?><?php echo !empty($date_to) ? '&date_to=' . urlencode($date_to) : ''; ?>&limit=<?php echo $records_per_page; ?>" class="text-decoration-none text-dark">
                                                Product
                                                <span class="sort-icon">
                                                    <?php if ($sort_by === 'p.name'): ?>
                                                        <i class="fas fa-sort-<?php echo $sort_dir === 'ASC' ? 'up' : 'down'; ?>"></i>
                                                    <?php else: ?>
                                                        <i class="fas fa-sort"></i>
                                                    <?php endif; ?>
                                                </span>
                                            </a>
                                        </th>
                                        <th>
                                            <a href="?sort_by=s.quantity&sort_dir=<?php echo ($sort_by === 's.quantity' && $sort_dir === 'ASC') ? 'DESC' : 'ASC'; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($date_from) ? '&date_from=' . urlencode($date_from) : ''; ?><?php echo !empty($date_to) ? '&date_to=' . urlencode($date_to) : ''; ?>&limit=<?php echo $records_per_page; ?>" class="text-decoration-none text-dark">
                                                Qty
                                                <span class="sort-icon">
                                                    <?php if ($sort_by === 's.quantity'): ?>
                                                        <i class="fas fa-sort-<?php echo $sort_dir === 'ASC' ? 'up' : 'down'; ?>"></i>
                                                    <?php else: ?>
                                                        <i class="fas fa-sort"></i>
                                                    <?php endif; ?>
                                                </span>
                                            </a>
                                        </th>
                                        <th>
                                            <a href="?sort_by=s.sale_date&sort_dir=<?php echo ($sort_by === 's.sale_date' && $sort_dir === 'ASC') ? 'DESC' : 'ASC'; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($date_from) ? '&date_from=' . urlencode($date_from) : ''; ?><?php echo !empty($date_to) ? '&date_to=' . urlencode($date_to) : ''; ?>&limit=<?php echo $records_per_page; ?>" class="text-decoration-none text-dark">
                                                Date
                                                <span class="sort-icon">
                                                    <?php if ($sort_by === 's.sale_date'): ?>
                                                        <i class="fas fa-sort-<?php echo $sort_dir === 'ASC' ? 'up' : 'down'; ?>"></i>
                                                    <?php else: ?>
                                                        <i class="fas fa-sort"></i>
                                                    <?php endif; ?>
                                                </span>
                                            </a>
                                        </th>
                                        <th>
                                            <a href="?sort_by=s.customer_name&sort_dir=<?php echo ($sort_by === 's.customer_name' && $sort_dir === 'ASC') ? 'DESC' : 'ASC'; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($date_from) ? '&date_from=' . urlencode($date_from) : ''; ?><?php echo !empty($date_to) ? '&date_to=' . urlencode($date_to) : ''; ?>&limit=<?php echo $records_per_page; ?>" class="text-decoration-none text-dark">
                                                Customer
                                                <span class="sort-icon">
                                                    <?php if ($sort_by === 's.customer_name'): ?>
                                                        <i class="fas fa-sort-<?php echo $sort_dir === 'ASC' ? 'up' : 'down'; ?>"></i>
                                                    <?php else: ?>
                                                        <i class="fas fa-sort"></i>
                                                    <?php endif; ?>
                                                </span>
                                            </a>
                                        </th>
                                        <th>
                                            <a href="?sort_by=s.total_amount&sort_dir=<?php echo ($sort_by === 's.total_amount' && $sort_dir === 'ASC') ? 'DESC' : 'ASC'; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($date_from) ? '&date_from=' . urlencode($date_from) : ''; ?><?php echo !empty($date_to) ? '&date_to=' . urlencode($date_to) : ''; ?>&limit=<?php echo $records_per_page; ?>" class="text-decoration-none text-dark">
                                                Amount
                                                <span class="sort-icon">
                                                    <?php if ($sort_by === 's.total_amount'): ?>
                                                        <i class="fas fa-sort-<?php echo $sort_dir === 'ASC' ? 'up' : 'down'; ?>"></i>
                                                    <?php else: ?>
                                                        <i class="fas fa-sort"></i>
                                                    <?php endif; ?>
                                                </span>
                                            </a>
                                        </th>
                                        <th>
                                            <a href="?sort_by=u.username&sort_dir=<?php echo ($sort_by === 'u.username' && $sort_dir === 'ASC') ? 'DESC' : 'ASC'; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($date_from) ? '&date_from=' . urlencode($date_from) : ''; ?><?php echo !empty($date_to) ? '&date_to=' . urlencode($date_to) : ''; ?>&limit=<?php echo $records_per_page; ?>" class="text-decoration-none text-dark">
                                                Sold By
                                                <span class="sort-icon">
                                                    <?php if ($sort_by === 'u.username'): ?>
                                                        <i class="fas fa-sort-<?php echo $sort_dir === 'ASC' ? 'up' : 'down'; ?>"></i>
                                                    <?php else: ?>
                                                        <i class="fas fa-sort"></i>
                                                    <?php endif; ?>
                                                </span>
                                            </a>
                                        </th>
                                        <th class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($result && mysqli_num_rows($result) > 0): ?>
                                        <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                            <tr class="clickable-row" data-id="<?php echo $row['id']; ?>">
                                                <td><?php echo $row['id']; ?></td>
                                                <td><?php echo htmlspecialchars($row['product_name']); ?></td>
                                                <td><?php echo $row['quantity']; ?></td>
                                                <td><?php echo date('Y-m-d', strtotime($row['sale_date'])); ?></td>
                                                <td><?php echo htmlspecialchars($row['customer_name'] ?: 'Walk-in'); ?></td>
                                                <td>$<?php echo number_format($row['total_amount'], 2); ?></td>
                                                <td><?php echo htmlspecialchars($row['username'] ?: 'System'); ?></td>
                                                <td class="action-buttons text-center">
                                                    <button type="button" class="btn btn-sm btn-info view-sale" data-id="<?php echo $row['id']; ?>" title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-danger delete-sale" data-id="<?php echo $row['id']; ?>" title="Delete Sale">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
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
                    <div class="card-footer">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                Showing <?php echo min(($current_page - 1) * $records_per_page + 1, $total_records); ?>-<?php echo min($current_page * $records_per_page, $total_records); ?> of <?php echo $total_records; ?> records
                            </div>
                            <?php if ($total_pages > 1): ?>
                                <nav aria-label="Page navigation">
                                    <ul class="pagination mb-0">
                                        <li class="page-item <?php echo ($current_page <= 1) ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $current_page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($date_from) ? '&date_from=' . urlencode($date_from) : ''; ?><?php echo !empty($date_to) ? '&date_to=' . urlencode($date_to) : ''; ?>&sort_by=<?php echo $sort_by; ?>&sort_dir=<?php echo $sort_dir; ?>&limit=<?php echo $records_per_page; ?>" aria-label="Previous">
                                                <span aria-hidden="true">&laquo;</span>
                                            </a>
                                        </li>
                                        <?php for ($i = max(1, $current_page - 2); $i <= min($total_pages, $current_page + 2); $i++): ?>
                                            <li class="page-item <?php echo ($i == $current_page) ? 'active' : ''; ?>">
                                                <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($date_from) ? '&date_from=' . urlencode($date_from) : ''; ?><?php echo !empty($date_to) ? '&date_to=' . urlencode($date_to) : ''; ?>&sort_by=<?php echo $sort_by; ?>&sort_dir=<?php echo $sort_dir; ?>&limit=<?php echo $records_per_page; ?>">
                                                    <?php echo $i; ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>
                                        <li class="page-item <?php echo ($current_page >= $total_pages) ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $current_page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($date_from) ? '&date_from=' . urlencode($date_from) : ''; ?><?php echo !empty($date_to) ? '&date_to=' . urlencode($date_to) : ''; ?>&sort_by=<?php echo $sort_by; ?>&sort_dir=<?php echo $sort_dir; ?>&limit=<?php echo $records_per_page; ?>" aria-label="Next">
                                                <span aria-hidden="true">&raquo;</span>
                                            </a>
                                        </li>
                                    </ul>
                                                            </nav>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Theme toggle button -->
                            <div class="theme-toggle" id="themeToggle">
                                <i class="fas fa-moon"></i>
                            </div>
                            
                            <!-- Sale Details Modal -->
                            <div class="modal fade sale-detail-modal" id="saleDetailModal" tabindex="-1" aria-labelledby="saleDetailModalLabel" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="saleDetailModalLabel">Sale Details</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body" id="saleDetailContent">
                                            Loading...
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Delete Confirmation Modal -->
                            <div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-labelledby="deleteConfirmModalLabel" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="deleteConfirmModalLabel">Confirm Delete</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <p>Are you sure you want to delete this sale? This action cannot be undone.</p>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Delete</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
                            <script>
                            document.addEventListener('DOMContentLoaded', function() {
                                // Initialize variables
                                let salesChart = null;
                                let currentSaleId = null;
                                
                                // Load sales statistics
                                loadSalesStats();
                                
                                // Toggle filter panel
                                document.getElementById('toggleFilterBtn').addEventListener('click', function() {
                                    const filterPanel = document.getElementById('filterPanel');
                                    if (filterPanel.style.display === 'none') {
                                        filterPanel.style.display = 'block';
                                    } else {
                                        filterPanel.style.display = 'none';
                                    }
                                });
                                
                                // Auto-calculate total amount
                                document.getElementById('calculateTotal').addEventListener('click', function() {
                                    const productSelect = document.getElementById('product_id');
                                    const quantity = document.getElementById('quantity').value;
                                    
                                    if (productSelect.selectedIndex > 0 && quantity > 0) {
                                        const price = productSelect.options[productSelect.selectedIndex].dataset.price;
                                        const total = (price * quantity).toFixed(2);
                                        
                                        const totalField = document.getElementById('total_amount');
                                        totalField.value = total;
                                        
                                        // Flash effect
                                        const calculator = document.querySelector('.price-calculator');
                                        calculator.classList.add('updated');
                                        setTimeout(() => calculator.classList.remove('updated'), 1000);
                                    }
                                });
                                
                                // Product price update
                                document.getElementById('product_id').addEventListener('change', function() {
                                    const quantity = document.getElementById('quantity').value;
                                    if (this.selectedIndex > 0 && quantity > 0) {
                                        const price = this.options[this.selectedIndex].dataset.price;
                                        const total = (price * quantity).toFixed(2);
                                        document.getElementById('total_amount').value = total;
                                    }
                                });
                                
                                document.getElementById('quantity').addEventListener('input', function() {
                                    const productSelect = document.getElementById('product_id');
                                    if (productSelect.selectedIndex > 0 && this.value > 0) {
                                        const price = productSelect.options[productSelect.selectedIndex].dataset.price;
                                        const total = (price * this.value).toFixed(2);
                                        document.getElementById('total_amount').value = total;
                                    }
                                });
                                
                                // Form submission
                                document.getElementById('addSaleForm').addEventListener('submit', function(e) {
                                    e.preventDefault();
                                    
                                    // Form validation
                                    if (!this.checkValidity()) {
                                        e.stopPropagation();
                                        this.classList.add('was-validated');
                                        return;
                                    }
                                    
                                    // Show loading state
                                    document.getElementById('submitSaleBtn').disabled = true;
                                    document.getElementById('submitSpinner').classList.remove('d-none');
                                    
                                    // Collect form data
                                    const formData = new FormData(this);
                                    
                                    // AJAX request
                                    fetch('sales.php', {
                                        method: 'POST',
                                        body: formData,
                                        headers: {
                                            'X-Requested-With': 'XMLHttpRequest'
                                        }
                                    })
                                    .then(response => response.json())
                                    .then(data => {
                                        if (data.status === 'success') {
                                            // Show success toast
                                            showToast('Success', data.message, 'success');
                                            
                                            // Reset form
                                            this.reset();
                                            this.classList.remove('was-validated');
                                            
                                            // Update stats
                                            loadSalesStats();
                                            
                                            // Add new row to table
                                            addSaleToTable(data.sale);
                                        } else {
                                            // Show error toast
                                            showToast('Error', data.message, 'error');
                                        }
                                    })
                                    .catch(error => {
                                        showToast('Error', 'An unexpected error occurred', 'error');
                                        console.error('Error:', error);
                                    })
                                    .finally(() => {
                                        // Reset loading state
                                        document.getElementById('submitSaleBtn').disabled = false;
                                        document.getElementById('submitSpinner').classList.add('d-none');
                                    });
                                });
                                
                                // Delete sale
                                document.querySelectorAll('.delete-sale').forEach(button => {
                                    button.addEventListener('click', function(e) {
                                        e.preventDefault();
                                        e.stopPropagation();
                                        
                                        const saleId = this.dataset.id;
                                        currentSaleId = saleId;
                                        
                                        // Show confirmation modal
                                        const deleteModal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
                                        deleteModal.show();
                                    });
                                });
                                
                                // Confirm delete
                                document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
                                    if (!currentSaleId) return;
                                    
                                    const formData = new FormData();
                                    formData.append('ajax_action', 'delete_sale');
                                    formData.append('sale_id', currentSaleId);
                                    
                                    // Show loading overlay
                                    document.getElementById('loadingOverlay').classList.add('show');
                                    
                                    // AJAX request
                                    fetch('sales.php', {
                                        method: 'POST',
                                        body: formData,
                                        headers: {
                                            'X-Requested-With': 'XMLHttpRequest'
                                        }
                                    })
                                    .then(response => response.json())
                                    .then(data => {
                                        // Hide modal
                                        bootstrap.Modal.getInstance(document.getElementById('deleteConfirmModal')).hide();
                                        
                                        if (data.status === 'success') {
                                            // Show success toast
                                            showToast('Success', data.message, 'success');
                                            
                                            // Remove row from table
                                            const row = document.querySelector(`.clickable-row[data-id="${data.sale_id}"]`);
                                            if (row) {
                                                row.classList.add('fade-out');
                                                setTimeout(() => {
                                                    row.remove();
                                                }, 500);
                                            }
                                            
                                            // Update stats
                                            loadSalesStats();
                                        } else {
                                            // Show error toast
                                            showToast('Error', data.message, 'error');
                                        }
                                    })
                                    .catch(error => {
                                        showToast('Error', 'An unexpected error occurred', 'error');
                                        console.error('Error:', error);
                                    })
                                    .finally(() => {
                                        // Hide loading overlay
                                        document.getElementById('loadingOverlay').classList.remove('show');
                                        currentSaleId = null;
                                    });
                                });
                                
                                // View sale details
                                document.querySelectorAll('.view-sale, .clickable-row').forEach(el => {
                                    el.addEventListener('click', function(e) {
                                        if (e.target.closest('.delete-sale')) {
                                            return; // Don't trigger view for delete button click
                                        }
                                        
                                        let saleId;
                                        if (this.classList.contains('view-sale')) {
                                            e.stopPropagation();
                                            saleId = this.dataset.id;
                                        } else {
                                            saleId = this.dataset.id;
                                        }
                                        
                                        // Show modal with loading state
                                        const modal = new bootstrap.Modal(document.getElementById('saleDetailModal'));
                                        document.getElementById('saleDetailContent').innerHTML = 'Loading...';
                                        modal.show();
                                        
                                        // TODO: Add AJAX to load sale details
                                        // This would be implemented in a future enhancement
                                    });
                                });
                                
                                // Refresh button
                                document.getElementById('refreshBtn').addEventListener('click', function() {
                                    loadSalesStats();
                                    location.reload();
                                });
                                
                                // Chart type toggle
                                document.getElementById('weeklyChartBtn').addEventListener('click', function() {
                                    this.classList.add('active');
                                    document.getElementById('monthlyChartBtn').classList.remove('active');
                                    // Update chart to weekly data
                                    // This would be implemented in a future enhancement
                                });
                                
                                document.getElementById('monthlyChartBtn').addEventListener('click', function() {
                                    this.classList.add('active');
                                    document.getElementById('weeklyChartBtn').classList.remove('active');
                                    // Update chart to monthly data
                                    // This would be implemented in a future enhancement
                                });
                                
                                // Theme toggle
                                document.getElementById('themeToggle').addEventListener('click', function() {
                                    if (document.body.classList.contains('dark-mode')) {
                                        document.body.classList.remove('dark-mode');
                                        this.innerHTML = '<i class="fas fa-moon"></i>';
                                        localStorage.setItem('theme', 'light');
                                    } else {
                                        document.body.classList.add('dark-mode');
                                        this.innerHTML = '<i class="fas fa-sun"></i>';
                                        localStorage.setItem('theme', 'dark');
                                    }
                                    
                                    // Update chart colors
                                    if (salesChart) {
                                        updateChartTheme(salesChart);
                                    }
                                });
                                
                                // Check saved theme preference
                                if (localStorage.getItem('theme') === 'dark') {
                                    document.body.classList.add('dark-mode');
                                    document.getElementById('themeToggle').innerHTML = '<i class="fas fa-sun"></i>';
                                }
                                
                                // Load sales statistics via AJAX
                                function loadSalesStats() {
                                    const formData = new FormData();
                                    formData.append('ajax_action', 'get_sales_stats');
                                    
                                    fetch('sales.php', {
                                        method: 'POST',
                                        body: formData,
                                        headers: {
                                            'X-Requested-With': 'XMLHttpRequest'
                                        }
                                    })
                                    .then(response => response.json())
                                    .then(data => {
                                        if (data.status === 'success') {
                                            // Update stats cards
                                            document.getElementById('todaySalesAmount').textContent = `$${parseFloat(data.today.total || 0).toFixed(2)}`;
                                            document.getElementById('todaySalesCount').textContent = `${data.today.count || 0} transactions`;
                                            
                                            document.getElementById('weekSalesAmount').textContent = `$${parseFloat(data.week.total || 0).toFixed(2)}`;
                                            document.getElementById('weekSalesCount').textContent = `${data.week.count || 0} transactions`;
                                            
                                            document.getElementById('monthSalesAmount').textContent = `$${parseFloat(data.month.total || 0).toFixed(2)}`;
                                            document.getElementById('monthSalesCount').textContent = `${data.month.count || 0} transactions`;
                                            
                                            // Calculate average sale value
                                            let avgSale = 0;
                                            if (data.month.count > 0) {
                                                avgSale = data.month.total / data.month.count;
                                            }
                                            document.getElementById('avgSaleValue').textContent = `$${avgSale.toFixed(2)}`;
                                            
                                            // For now, just a placeholder for growth
                                            document.getElementById('avgSalesGrowth').textContent = `Growing business`;
                                            
                                            // Update top products table
                                            updateTopProductsTable(data.top_products);
                                            
                                            // Update chart
                                            updateSalesChart(data.daily_sales);
                                        }
                                    })
                                    .catch(error => {
                                        console.error('Error loading sales stats:', error);
                                    });
                                }
                                
                                // Update top products table
                                function updateTopProductsTable(products) {
                                    const table = document.getElementById('topProductsTable');
                                    const tbody = table.querySelector('tbody');
                                    tbody.innerHTML = '';
                                    
                                    if (products && products.length > 0) {
                                        products.forEach(product => {
                                            const row = document.createElement('tr');
                                            row.innerHTML = `
                                                <td>${escapeHtml(product.name)}</td>
                                                <td class="text-center">${product.total_quantity}</td>
                                                <td class="text-end">$${parseFloat(product.total_amount).toFixed(2)}</td>
                                            `;
                                            tbody.appendChild(row);
                                        });
                                    } else {
                                        const row = document.createElement('tr');
                                        row.innerHTML = `<td colspan="3" class="text-center py-3">No data available</td>`;
                                        tbody.appendChild(row);
                                    }
                                }
                                
                                // Update sales chart
                                function updateSalesChart(dailySales) {
                                    const ctx = document.getElementById('salesChart');
                                    
                                    const isDarkMode = document.body.classList.contains('dark-mode');
                                    const textColor = isDarkMode ? '#e0e0e0' : '#666';
                                    const gridColor = isDarkMode ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.1)';
                                    
                                    const labels = dailySales.map(sale => sale.date);
                                    const data = dailySales.map(sale => sale.total);
                                    
                                    if (salesChart) {
                                        salesChart.destroy();
                                    }
                                    
                                    salesChart = new Chart(ctx, {
                                        type: 'bar',
                                        data: {
                                            labels: labels,
                                            datasets: [{
                                                label: 'Sales Amount',
                                                data: data,
                                                backgroundColor: 'rgba(78, 115, 223, 0.6)',
                                                borderColor: 'rgba(78, 115, 223, 1)',
                                                borderWidth: 1
                                            }]
                                        },
                                        options: {
                                            responsive: true,
                                            maintainAspectRatio: false,
                                            scales: {
                                                y: {
                                                    beginAtZero: true,
                                                    ticks: {
                                                        color: textColor,
                                                        callback: function(value) {
                                                            return '$' + value;
                                                        }
                                                    },
                                                    grid: {
                                                        color: gridColor
                                                    }
                                                },
                                                x: {
                                                    ticks: {
                                                        color: textColor
                                                    },
                                                    grid: {
                                                        color: gridColor
                                                    }
                                                }
                                            },
                                            plugins: {
                                                legend: {
                                                    labels: {
                                                        color: textColor
                                                    }
                                                }
                                            }
                                        }
                                    });
                                }
                                
                                // Update chart theme
                                function updateChartTheme(chart) {
                                    const isDarkMode = document.body.classList.contains('dark-mode');
                                    const textColor = isDarkMode ? '#e0e0e0' : '#666';
                                    const gridColor = isDarkMode ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.1)';
                                    
                                    chart.options.scales.x.ticks.color = textColor;
                                    chart.options.scales.y.ticks.color = textColor;
                                    chart.options.scales.x.grid.color = gridColor;
                                    chart.options.scales.y.grid.color = gridColor;
                                    chart.options.plugins.legend.labels.color = textColor;
                                    
                                    chart.update();
                                }
                                
                                // Add new sale to the table
                                function addSaleToTable(sale) {
                                    const table = document.getElementById('salesTable');
                                    const tbody = table.querySelector('tbody');
                                    
                                    // Create a new row
                                    const row = document.createElement('tr');
                                    row.className = 'clickable-row fade-in';
                                    row.dataset.id = sale.id;
                                    
                                    // Format the date
                                    const saleDate = new Date(sale.sale_date);
                                    const formattedDate = saleDate.getFullYear() + '-' + 
                                                         String(saleDate.getMonth() + 1).padStart(2, '0') + '-' + 
                                                         String(saleDate.getDate()).padStart(2, '0');
                                    
                                    // Set the row content
                                    row.innerHTML = `
                                        <td>${sale.id}</td>
                                        <td>${escapeHtml(sale.product_name)}</td>
                                        <td>${sale.quantity}</td>
                                        <td>${formattedDate}</td>
                                        <td>${escapeHtml(sale.customer_name || 'Walk-in')}</td>
                                        <td>$${parseFloat(sale.total_amount).toFixed(2)}</td>
                                        <td>${escapeHtml(sale.username || 'System')}</td>
                                        <td class="action-buttons text-center">
                                            <button type="button" class="btn btn-sm btn-info view-sale" data-id="${sale.id}" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-danger delete-sale" data-id="${sale.id}" title="Delete Sale">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    `;
                                    
                                    // Add event listeners to the new buttons
                                    const viewBtn = row.querySelector('.view-sale');
                                    viewBtn.addEventListener('click', function(e) {
                                        e.stopPropagation();
                                        const saleId = this.dataset.id;
                                        // Show sale details modal
                                        const modal = new bootstrap.Modal(document.getElementById('saleDetailModal'));
                                        document.getElementById('saleDetailContent').innerHTML = 'Loading...';
                                        modal.show();
                                        // TODO: Implement loading sale details
                                    });
                                    
                                    const deleteBtn = row.querySelector('.delete-sale');
                                    deleteBtn.addEventListener('click', function(e) {
                                        e.preventDefault();
                                        e.stopPropagation();
                                        
                                        const saleId = this.dataset.id;
                                        currentSaleId = saleId;
                                        
                                        // Show confirmation modal
                                        const deleteModal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
                                        deleteModal.show();
                                    });
                                    
                                    // Add click event for the entire row
                                    row.addEventListener('click', function(e) {
                                        if (!e.target.closest('.delete-sale')) {
                                            const saleId = this.dataset.id;
                                            // Show sale details modal
                                            const modal = new bootstrap.Modal(document.getElementById('saleDetailModal'));
                                            document.getElementById('saleDetailContent').innerHTML = 'Loading...';
                                            modal.show();
                                            // TODO: Implement loading sale details
                                        }
                                    });
                                    
                                    // Check if the table has "No sales found" row
                                    const noSalesRow = tbody.querySelector('tr td[colspan="8"]');
                                    if (noSalesRow) {
                                        tbody.innerHTML = '';
                                    }
                                    
                                    // Add the new row at the beginning of the table
                                    tbody.insertBefore(row, tbody.firstChild);
                                }
                                
                                // Show toast notification
                                function showToast(title, message, type = 'info') {
                                    const toastContainer = document.getElementById('toastContainer');
                                    
                                    const toast = document.createElement('div');
                                    toast.className = `toast ${type}`;
                                    toast.innerHTML = `
                                        <div class="toast-header">
                                            <strong>${title}</strong>
                                            <button type="button" class="btn-close ms-auto" aria-label="Close"></button>
                                        </div>
                                        <div class="toast-body">
                                            ${message}
                                        </div>
                                    `;
                                    
                                    toastContainer.appendChild(toast);
                                    
                                    // Show toast with animation
                                    setTimeout(() => {
                                        toast.classList.add('show');
                                    }, 100);
                                    
                                    // Add click event to close button
                                    toast.querySelector('.btn-close').addEventListener('click', () => {
                                        toast.classList.remove('show');
                                        setTimeout(() => {
                                            toast.remove();
                                        }, 300);
                                    });
                                    
                                    // Auto-hide after 5 seconds
                                    setTimeout(() => {
                                        toast.classList.remove('show');
                                        setTimeout(() => {
                                            toast.remove();
                                        }, 300);
                                    }, 5000);
                                }
                                
                                // Helper function to escape HTML
                                function escapeHtml(text) {
                                    if (!text) return '';
                                    const map = {
                                        '&': '&amp;',
                                        '<': '&lt;',
                                        '>': '&gt;',
                                        '"': '&quot;',
                                        "'": '&#039;'
                                    };
                                    return text.replace(/[&<>"']/g, m => map[m]);
                                }
                            });
                            </script>
                            
                            <?php include 'includes/footer.php'; ?>
                