<?php
session_start();

require_once 'includes/compatibility.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

// Database connection
$conn = connectDB();

// Get user info
$user_id = $_SESSION['user_id'];
$username = getUserName($conn, $user_id);

// Handle AJAX requests for dashboard data
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    if (isset($_GET['action']) && $_GET['action'] == 'get_dashboard_data') {
        
        $response = [
            'status' => 'success',
            'data' => [
                'sales_today' => getTodaySales($conn, $user_id),
                'orders_today' => getTodayOrders($conn, $user_id),
                'total_products' => getTotalProducts($conn),
                'low_stock_count' => getLowStockCount($conn),
                'recent_sales' => getRecentSales($conn, $user_id, 5),
                'top_products' => getTopSellingProducts($conn, $user_id, 5)
            ]
        ];
        
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
}

// Function to get today's sales for the current cashier
function getTodaySales($conn, $user_id) {
    $today = date('Y-m-d');
    $query = "SELECT COALESCE(SUM(total_amount), 0) as total FROM sales WHERE cashier_id = ? AND DATE(created_at) = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("is", $user_id, $today);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc()['total'];
}

// Function to get today's order count for the current cashier
function getTodayOrders($conn, $user_id) {
    $today = date('Y-m-d');
    $query = "SELECT COUNT(*) as total FROM sales WHERE cashier_id = ? AND DATE(created_at) = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("is", $user_id, $today);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc()['total'];
}

// Function to get total active products
function getTotalProducts($conn) {
    $query = "SELECT COUNT(*) as total FROM products WHERE status = 1";
    $result = $conn->query($query);
    return $result->fetch_assoc()['total'];
}

// Function to get low stock product count
function getLowStockCount($conn) {
    $query = "SELECT COUNT(*) as total FROM products WHERE quantity <= 10 AND status = 1";
    $result = $conn->query($query);
    return $result->fetch_assoc()['total'];
}

// Function to get recent sales for the current cashier
function getRecentSales($conn, $user_id, $limit = 5) {
    $query = "SELECT id, invoice_no, total_amount, created_at FROM sales WHERE cashier_id = ? ORDER BY created_at DESC LIMIT ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $user_id, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Function to get top selling products for the current cashier
function getTopSellingProducts($conn, $user_id, $limit = 5) {
    $query = "SELECT p.name, SUM(si.quantity) as total_sold
              FROM sale_items si
              JOIN sales s ON si.sale_id = s.id
              JOIN products p ON si.product_id = p.id
              WHERE s.cashier_id = ?
              GROUP BY p.id
              ORDER BY total_sold DESC
              LIMIT ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $user_id, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Get categories for tabs
$categories_result = $conn->query("SELECT * FROM categories WHERE status = 1 ORDER BY name");
$categories = [];
while ($row = $categories_result->fetch_assoc()) {
    $categories[] = $row;
}

// Set page title
$pageTitle = "POS Dashboard";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Bakery Management System</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Poppins', sans-serif;
        }
        
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.8);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }
        
        .main-header {
            background: linear-gradient(135deg, #ff6b6b, #ff5252);
            color: white;
            padding: 1rem 0;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .logo-section {
            display: flex;
            align-items: center;
        }
        
        .logo-icon {
            font-size: 2.5rem;
            margin-right: 15px;
        }
        
        .dashboard-nav {
            display: flex;
            gap: 15px;
            align-items: center;
        }
        
        .nav-btn {
            background: rgba(255,255,255,0.2);
            border: 1px solid rgba(255,255,255,0.3);
            color: white;
            padding: 8px 16px;
            border-radius: 25px;
            text-decoration: none;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }
        
        .nav-btn:hover {
            background: rgba(255,255,255,0.3);
            color: white;
            transform: translateY(-2px);
        }
        
        .nav-btn.active {
            background: white;
            color: #ff6b6b;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .main-container {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            margin: 0 2rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        :root {
            --primary-color: #ff6b6b;
            --secondary-color: #4ecdc4;
            --accent-color: #feca57;
            --success-color: #48c9b0;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
            --dark-color: #2c3e50;
            --light-color: #f8f9fa;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* Animated Background */
        .bg-animation {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .bg-animation::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 1000"><polygon fill="%23ffffff05" points="0,0 1000,300 1000,1000 0,700"/></svg>');
            animation: float 20s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }

        /* Header Styles */
        .top-bar {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            padding: 1rem 0;
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .top-bar h2 {
            color: var(--primary-color);
            font-weight: 700;
            margin: 0;
        }

        .user-info {
            color: var(--dark-color);
            font-weight: 500;
        }

        /* Main Container */
        .pos-container {
            padding: 2rem 0;
            min-height: calc(100vh - 100px);
        }

        /* Search Bar */
        .search-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .search-input {
            border: none;
            border-radius: 50px;
            padding: 1rem 1.5rem;
            font-size: 1.1rem;
            background: rgba(108, 117, 125, 0.1);
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        }

        .search-input:focus {
            outline: none;
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.3);
            transform: translateY(-2px);
        }

        /* Category Tabs */
        .category-tabs {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            overflow-x: auto;
            padding-bottom: 0.5rem;
        }

        .category-tab {
            background: rgba(255, 255, 255, 0.9);
            border: none;
            border-radius: 50px;
            padding: 0.8rem 1.5rem;
            font-weight: 600;
            color: var(--dark-color);
            transition: all 0.3s ease;
            white-space: nowrap;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .category-tab:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .category-tab.active {
            background: var(--primary-color);
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(255, 107, 107, 0.4);
        }

        /* Product Grid */
        .products-section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-top: 1rem;
        }

        .product-card {
            background: linear-gradient(145deg, #ffffff, #f0f0f0);
            border-radius: 20px;
            padding: 1.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            position: relative;
            overflow: hidden;
        }

        .product-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
            transition: left 0.5s;
        }

        .product-card:hover::before {
            left: 100%;
        }

        .product-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.2);
        }

        .product-image {
            width: 80px;
            height: 80px;
            background: var(--accent-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 2rem;
            color: white;
            box-shadow: 0 4px 15px rgba(254, 202, 87, 0.3);
        }

        .product-name {
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
            font-size: 1.1rem;
        }

        .product-price {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .product-stock {
            font-size: 0.9rem;
            color: #6c757d;
            margin-bottom: 1rem;
        }

        .add-to-cart-btn {
            background: var(--success-color);
            color: white;
            border: none;
            border-radius: 50px;
            padding: 0.8rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
            width: 100%;
            cursor: pointer;
        }

        .add-to-cart-btn:hover {
            background: #16a085;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(72, 201, 176, 0.4);
        }

        /* Cart Section */
        .cart-section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            position: sticky;
            top: 120px;
            max-height: calc(100vh - 140px);
            overflow-y: auto;
        }

        .cart-header {
            display: flex;
            align-items: center;
            justify-content: between;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #e9ecef;
        }

        .cart-title {
            font-weight: 700;
            color: var(--dark-color);
            margin: 0;
        }

        .cart-count {
            background: var(--primary-color);
            color: white;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .cart-items {
            max-height: 300px;
            overflow-y: auto;
            margin-bottom: 1.5rem;
        }

        .cart-item {
            display: flex;
            align-items: center;
            padding: 1rem;
            border-radius: 15px;
            margin-bottom: 1rem;
            background: linear-gradient(145deg, #f8f9fa, #e9ecef);
            transition: all 0.3s ease;
        }

        .cart-item:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .cart-item-info {
            flex: 1;
            margin-right: 1rem;
        }

        .cart-item-name {
            font-weight: 600;
            color: var(--dark-color);
        }

        .cart-item-price {
            color: var(--primary-color);
            font-weight: 500;
        }

        .quantity-controls {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .qty-btn {
            background: var(--secondary-color);
            color: white;
            border: none;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .qty-btn:hover {
            background: #45b7aa;
            transform: scale(1.1);
        }

        .qty-input {
            width: 50px;
            text-align: center;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 0.3rem;
        }

        .cart-summary {
            border-top: 2px solid #e9ecef;
            padding-top: 1.5rem;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.8rem;
            font-weight: 500;
        }

        .total-row {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--primary-color);
            border-top: 1px solid #e9ecef;
            padding-top: 1rem;
        }

        .checkout-btn {
            background: linear-gradient(135deg, var(--primary-color), #ff5252);
            color: white;
            border: none;
            border-radius: 50px;
            padding: 1rem 2rem;
            font-size: 1.1rem;
            font-weight: 600;
            width: 100%;
            margin-top: 1.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 6px 20px rgba(255, 107, 107, 0.3);
        }

        .checkout-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(255, 107, 107, 0.5);
        }

        .clear-cart-btn {
            background: var(--warning-color);
            color: white;
            border: none;
            border-radius: 50px;
            padding: 0.8rem 1.5rem;
            font-weight: 600;
            width: 100%;
            margin-top: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .clear-cart-btn:hover {
            background: #e67e22;
            transform: translateY(-2px);
        }

        /* Loading Animation */
        .loading {
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 2rem;
        }

        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .pos-container {
                padding: 1rem;
            }
            
            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
                gap: 1rem;
            }
            
            .cart-section {
                position: relative;
                top: 0;
                margin-top: 2rem;
            }
        }

        /* Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .fade-in-up {
            animation: fadeInUp 0.6s ease;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        .pulse {
            animation: pulse 2s infinite;
        }

        /* Notification Styles */
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            background: var(--success-color);
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
            z-index: 2000;
            transform: translateX(400px);
            transition: transform 0.3s ease;
        }

        .notification.show {
            transform: translateX(0);
        }

        .notification.error {
            background: var(--danger-color);
        }

        .notification.warning {
            background: var(--warning-color);
        }

        /* Product card animations for adding to cart */
        .product-card.adding {
            animation: addToCartPulse 0.3s ease;
        }

        @keyframes addToCartPulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        /* Cart badge animation */
        .cart-count.updated {
            animation: bounce 0.6s ease;
        }

        .stat-card .stat-value {
            font-size: 2rem;
            font-weight: 700;
        }

        @keyframes bounce {
            0%, 20%, 60%, 100% { transform: translateY(0); }
            40% { transform: translateY(-10px); }
            80% { transform: translateY(-5px); }
        }
    </style>
</head>
<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner-border text-primary" role="status"></div>
    </div>

    <!-- Main Header -->
    <header class="main-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-md-4">
                    <div class="logo-section">
                        <i class="fas fa-birthday-cake logo-icon"></i>
                        <div>
                            <h3 class="mb-0">Bakery POS</h3>
                            <small>Point of Sale System</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 text-center">
                    <div class="dashboard-nav">
                        <a href="index.php" class="nav-btn active">
                            <i class="fas fa-home"></i> Dashboard
                        </a>
                        <a href="billing.php" class="nav-btn">
                            <i class="fas fa-cash-register"></i> Billing
                        </a>
                        <a href="invoices.php" class="nav-btn">
                            <i class="fas fa-receipt"></i> Invoices
                        </a>
                    </div>
                </div>
                <div class="col-md-4 text-end">
                    <div class="user-info">
                        <div class="text-end">
                            <div><strong><?php echo htmlspecialchars($username); ?></strong></div>
                            <small>Cashier</small>
                        </div>
                        <div class="dropdown">
                            <button class="nav-btn dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user"></i>
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="../auth/logout.php">
                                    <i class="fas fa-sign-out-alt"></i> Logout
                                </a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <div class="main-container">
        <div class="row align-items-center mb-4">
            <div class="col">
                <h2 class="mb-0">
                    <i class="fas fa-cash-register text-primary"></i> 
                    POS Dashboard
                </h2>
                <p class="text-muted mb-0">Select products and manage your cart</p>
            </div>
            <div class="col-auto">
                <div class="d-flex gap-2">
                    <a href="billing.php" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus"></i> New Sale
                    </button>
                    <button class="btn btn-outline-success btn-sm" id="refreshBtn">
                        <i class="fas fa-refresh"></i> Refresh
                    </button>
                </div>
            </div>
        </div>
        
        <div class="row">
            <!-- Products Section -->
            <div class="col-lg-8">
                    <!-- Search Bar -->
                    <div class="search-container fade-in-up">
                        <div class="row">
                            <div class="col-md-8">
                                <input type="text" id="searchInput" class="form-control search-input" placeholder="🔍 Search products..." autocomplete="off">
                            </div>
                            <div class="col-md-4">
                                <select id="categoryFilter" class="form-control search-input">
                                    <option value="">All Categories</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Category Tabs -->
                    <div class="category-tabs fade-in-up">
                        <button class="category-tab active" data-category="">
                            <i class="fas fa-th-large"></i> All
                        </button>
                        <?php foreach ($categories as $category): ?>
                            <button class="category-tab" data-category="<?php echo $category['id']; ?>">
                                <i class="fas fa-tag"></i> <?php echo htmlspecialchars($category['name']); ?>
                            </button>
                        <?php endforeach; ?>
                    </div>

                    <!-- Products Grid -->
                    <div class="products-section fade-in-up">
                        <h4><i class="fas fa-cubes"></i> Products</h4>
                        <div id="productsGrid" class="products-grid">
                            <div class="loading">
                                <div class="spinner"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Cart Section -->
                <div class="col-lg-4">
                    <div class="cart-section fade-in-up">
                        <div class="cart-header">
                            <h4 class="cart-title"><i class="fas fa-shopping-cart"></i> Shopping Cart</h4>
                            <div class="cart-count" id="cartCount">0</div>
                        </div>
                        
                        <div class="cart-items" id="cartItems">
                            <div class="text-center text-muted">
                                <i class="fas fa-shopping-bag fa-3x mb-3"></i>
                                <p>Your cart is empty<br>Start adding products!</p>
                            </div>
                        </div>

                        <div class="cart-summary" id="cartSummary" style="display: none;">
                            <div class="summary-row">
                                <span>Subtotal:</span>
                                <span id="subtotal">$0.00</span>
                            </div>
                            <div class="summary-row">
                                <span>Tax (10%):</span>
                                <span id="tax">$0.00</span>
                            </div>
                            <div class="summary-row total-row">
                                <span>Total:</span>
                                <span id="total">$0.00</span>
                            </div>
                            
                            <button class="checkout-btn pulse" onclick="proceedToCheckout()">
                                <i class="fas fa-credit-card"></i> Proceed to Checkout
                            </button>
                            
                            <button class="clear-cart-btn" onclick="clearCart()">
                                <i class="fas fa-trash"></i> Clear Cart
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Cart functionality
        let cart = [];
        let products = [];
        
        // Initialize
        $(document).ready(function() {
            loadProducts();
            
            // Search functionality
            $('#searchInput').on('input', function() {
                filterProducts();
            });
            
            // Category filter
            $('#categoryFilter').on('change', function() {
                filterProducts();
            });
            
            // Category tabs
            $('.category-tab').on('click', function() {
                $('.category-tab').removeClass('active');
                $(this).addClass('active');
                
                const category = $(this).data('category');
                $('#categoryFilter').val(category);
                filterProducts();
            });
        });
        
        // Load products from server
        function loadProducts() {
            $.ajax({
                url: 'get_products.php',
                method: 'GET',
                dataType: 'json',
                success: function(data) {
                    products = data;
                    displayProducts(products);
                },
                error: function() {
                    $('#productsGrid').html('<div class="alert alert-danger">Error loading products</div>');
                }
            });
        }
        
        // Display products
        function displayProducts(productsToShow) {
            const grid = $('#productsGrid');
            grid.empty();
            
            if (productsToShow.length === 0) {
                grid.html('<div class="col-12 text-center"><p class="text-muted">No products found</p></div>');
                return;
            }
            
            productsToShow.forEach(function(product) {
                // Choose icon based on category or product name
                let productIcon = 'fas fa-cookie-bite'; // default
                if (product.category_name) {
                    switch(product.category_name.toLowerCase()) {
                        case 'bread':
                            productIcon = 'fas fa-bread-slice';
                            break;
                        case 'pastries':
                            productIcon = 'fas fa-croissant';
                            break;
                        case 'cakes':
                            productIcon = 'fas fa-birthday-cake';
                            break;
                        case 'cookies':
                            productIcon = 'fas fa-cookie';
                            break;
                        case 'beverages':
                            productIcon = 'fas fa-coffee';
                            break;
                        default:
                            productIcon = 'fas fa-cookie-bite';
                    }
                }
                
                const productCard = `
                    <div class="product-card" data-product-id="${product.id}">
                        <div class="product-image">
                            <i class="${productIcon}"></i>
                        </div>
                        <div class="product-name">${product.name}</div>
                        <div class="product-price">$${parseFloat(product.price).toFixed(2)}</div>
                        <div class="product-stock">Stock: ${product.stock_quantity}</div>
                        <button class="add-to-cart-btn" onclick="addToCart(${product.id})" ${product.stock_quantity <= 0 ? 'disabled' : ''}>
                            <i class="fas fa-plus"></i> Add to Cart
                        </button>
                    </div>
                `;
                grid.append(productCard);
            });
        }
        
        // Filter products
        function filterProducts() {
            const searchTerm = $('#searchInput').val().toLowerCase();
            const categoryId = $('#categoryFilter').val();
            
            let filtered = products.filter(function(product) {
                const matchesSearch = product.name.toLowerCase().includes(searchTerm) ||
                                    product.description.toLowerCase().includes(searchTerm);
                const matchesCategory = !categoryId || product.category_id == categoryId;
                
                return matchesSearch && matchesCategory;
            });
            
            displayProducts(filtered);
        }
        
        // Add product to cart
        function addToCart(productId) {
            const product = products.find(p => p.id == productId);
            if (!product || product.stock_quantity <= 0) {
                showNotification('Product is out of stock!', 'error');
                return;
            }
            
            const existingItem = cart.find(item => item.id == productId);
            
            if (existingItem) {
                if (existingItem.quantity < product.stock_quantity) {
                    existingItem.quantity++;
                    showNotification(`Added another ${product.name} to cart!`, 'success');
                } else {
                    showNotification('Maximum stock quantity reached!', 'warning');
                    return;
                }
            } else {
                cart.push({
                    id: product.id,
                    name: product.name,
                    price: parseFloat(product.price),
                    quantity: 1,
                    max_quantity: product.stock_quantity
                });
                showNotification(`${product.name} added to cart!`, 'success');
            }
            
            // Add visual feedback
            $(`.product-card[data-product-id="${productId}"]`).addClass('adding');
            setTimeout(() => {
                $(`.product-card[data-product-id="${productId}"]`).removeClass('adding');
            }, 300);
            
            updateCartDisplay();
        }
        
        // Update cart display
        function updateCartDisplay() {
            const cartItems = $('#cartItems');
            const cartCount = $('#cartCount');
            const cartSummary = $('#cartSummary');
            
            const totalItems = cart.reduce((sum, item) => sum + item.quantity, 0);
            cartCount.text(totalItems);
            
            // Add animation to cart count
            cartCount.addClass('updated');
            setTimeout(() => cartCount.removeClass('updated'), 600);
            
            if (cart.length === 0) {
                cartItems.html(`
                    <div class="text-center text-muted">
                        <i class="fas fa-shopping-bag fa-3x mb-3"></i>
                        <p>Your cart is empty<br>Start adding products!</p>
                    </div>
                `);
                cartSummary.hide();
                return;
            }
            
            let cartHtml = '';
            cart.forEach(function(item) {
                cartHtml += `
                    <div class="cart-item">
                        <div class="cart-item-info">
                            <div class="cart-item-name">${item.name}</div>
                            <div class="cart-item-price">$${item.price.toFixed(2)} each</div>
                        </div>
                        <div class="quantity-controls">
                            <button class="qty-btn" onclick="updateQuantity(${item.id}, ${item.quantity - 1})">
                                <i class="fas fa-minus"></i>
                            </button>
                            <input type="number" class="qty-input" value="${item.quantity}" 
                                   min="1" max="${item.max_quantity}" 
                                   onchange="updateQuantity(${item.id}, this.value)">
                            <button class="qty-btn" onclick="updateQuantity(${item.id}, ${item.quantity + 1})">
                                <i class="fas fa-plus"></i>
                            </button>
                            <button class="qty-btn" style="background: var(--danger-color); margin-left: 0.5rem;" 
                                    onclick="removeFromCart(${item.id})">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                `;
            });
            
            cartItems.html(cartHtml);
            
            // Calculate totals
            const subtotal = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
            const tax = subtotal * 0.1;
            const total = subtotal + tax;
            
            $('#subtotal').text('$' + subtotal.toFixed(2));
            $('#tax').text('$' + tax.toFixed(2));
            $('#total').text('$' + total.toFixed(2));
            
            cartSummary.show();
        }
        
        // Show notification
        function showNotification(message, type = 'success') {
            // Remove existing notifications
            $('.notification').remove();
            
            const notification = $(`
                <div class="notification ${type}">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'exclamation-triangle'}"></i>
                    ${message}
                </div>
            `);
            
            $('body').append(notification);
            
            // Show notification
            setTimeout(() => notification.addClass('show'), 100);
            
            // Hide notification after 3 seconds
            setTimeout(() => {
                notification.removeClass('show');
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        }
        
        // Update quantity
        function updateQuantity(productId, newQuantity) {
            const item = cart.find(item => item.id == productId);
            if (!item) return;
            
            newQuantity = parseInt(newQuantity);
            
            if (newQuantity <= 0) {
                removeFromCart(productId);
                return;
            }
            
            if (newQuantity > item.max_quantity) {
                newQuantity = item.max_quantity;
            }
            
            item.quantity = newQuantity;
            updateCartDisplay();
        }
        
        // Remove from cart
        function removeFromCart(productId) {
            const item = cart.find(item => item.id == productId);
            if (item) {
                showNotification(`${item.name} removed from cart!`, 'warning');
            }
            cart = cart.filter(item => item.id != productId);
            updateCartDisplay();
        }
        
        // Clear cart
        function clearCart() {
            if (cart.length === 0) {
                showNotification('Cart is already empty!', 'warning');
                return;
            }
            
            if (confirm('Are you sure you want to clear the cart?')) {
                cart = [];
                updateCartDisplay();
                showNotification('Cart cleared successfully!', 'success');
            }
        }
        
        // Proceed to checkout
        function proceedToCheckout() {
            if (cart.length === 0) {
                showNotification('Your cart is empty!', 'error');
                return;
            }
            
            // Show loading state
            $('.checkout-btn').html('<i class="fas fa-spinner fa-spin"></i> Processing...');
            
            // Store cart in session and redirect to billing
            $.ajax({
                url: 'store_cart.php',
                method: 'POST',
                data: { cart: JSON.stringify(cart) },
                success: function() {
                    showNotification('Redirecting to checkout...', 'success');
                    setTimeout(() => {
                        window.location.href = 'billing.php';
                    }, 1000);
                },
                error: function() {
                    showNotification('Error processing cart. Please try again.', 'error');
                    $('.checkout-btn').html('<i class="fas fa-credit-card"></i> Proceed to Checkout');
                }
            });
        }
    </script>
</body>
</html>
