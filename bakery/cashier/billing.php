<?php
// Start the session
session_start();

// Include the compatibility layer
require_once 'includes/compatibility.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

// Get database connection
$conn = connectDB();

// Get current date and time
$currentDateTime = date('Y-m-d H:i:s');
$currentDate = date('Y-m-d');

// Initialize variables
$error = '';
$success = '';
$invoice_no = generateInvoiceNumber();
$cashier_id = $_SESSION['user_id'];
$cashier_name = getUserName($conn, $cashier_id);

// Get cart data from session
$cart_items = [];
$cart_total = 0;
$cart_subtotal = 0;
$cart_tax = 0;

if (isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
    $cart_items = $_SESSION['cart'];
    
    // Calculate totals
    foreach ($cart_items as $item) {
        $cart_subtotal += $item['price'] * $item['quantity'];
    }
    $cart_tax = $cart_subtotal * 0.1; // 10% tax
    $cart_total = $cart_subtotal + $cart_tax;
}

// Fetch all active products with their categories
$query = "SELECT p.*, c.name as category_name 
          FROM products p 
          LEFT JOIN categories c ON p.category_id = c.id 
          WHERE p.status = 1 AND p.stock_quantity > 0 
          ORDER BY c.name, p.name";
$products = [];
$result = $conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
}

// Group products by category
$productsByCategory = [];
foreach ($products as $product) {
    $category = $product['category_name'] ? $product['category_name'] : 'Uncategorized';
    if (!isset($productsByCategory[$category])) {
        $productsByCategory[$category] = [];
    }
    $productsByCategory[$category][] = $product;
}

// Get all categories for filter
$categoriesQuery = "SELECT * FROM categories WHERE status = 1 ORDER BY name";
$categories = [];
$categoriesResult = $conn->query($categoriesQuery);
if ($categoriesResult) {
    while ($row = $categoriesResult->fetch_assoc()) {
        $categories[] = $row;
    }
}

// Handle save order request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_order'])) {
    // Get data from the form
    $customer_name = isset($_POST['customer_name']) ? mysqli_real_escape_string($conn, $_POST['customer_name']) : '';
    $subtotal = isset($_POST['subtotal']) ? (float)$_POST['subtotal'] : 0;
    $tax_rate = isset($_POST['tax_rate']) ? (float)$_POST['tax_rate'] : 0;
    $tax_amount = isset($_POST['tax_amount']) ? (float)$_POST['tax_amount'] : 0;
    $discount_amount = isset($_POST['discount_amount']) ? (float)$_POST['discount_amount'] : 0;
    $total_amount = isset($_POST['total_amount']) ? (float)$_POST['total_amount'] : 0;
    $payment_method = isset($_POST['payment_method']) ? mysqli_real_escape_string($conn, $_POST['payment_method']) : 'cash';
    $notes = isset($_POST['notes']) ? mysqli_real_escape_string($conn, $_POST['notes']) : '';
    
    // Get cart items
    $cart_items = isset($_POST['cart_items']) ? json_decode($_POST['cart_items'], true) : [];
    
    if (empty($cart_items)) {
        $error = "Cart is empty. Please add products to the cart.";
    } else if ($subtotal <= 0) {
        $error = "Invalid order total. Please check your cart.";
    } else {
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Insert into sales table
            $salesQuery = "INSERT INTO sales (invoice_no, cashier_id, customer_name, subtotal, tax_amount, discount_amount, 
                          total_amount, payment_method, notes, created_at) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $salesStmt = $conn->prepare($salesQuery);
            $salesStmt->bind_param("sisddddss", $invoice_no, $cashier_id, $customer_name, $subtotal, $tax_amount, 
                                 $discount_amount, $total_amount, $payment_method, $notes, $currentDateTime);
            $salesStmt->execute();
            
            // Get the sale ID
            $sale_id = $conn->insert_id;
            
            // Insert sale items and update product quantities
            foreach ($cart_items as $item) {
                $product_id = $item['id'];
                $quantity = $item['quantity'];
                $unit_price = $item['price'];
                $item_subtotal = $unit_price * $quantity;
                
                // Insert into sale_items table
                $itemQuery = "INSERT INTO sale_items (sale_id, product_id, quantity, unit_price, subtotal) 
                              VALUES (?, ?, ?, ?, ?)";
                $itemStmt = $conn->prepare($itemQuery);
                $itemStmt->bind_param("iiddd", $sale_id, $product_id, $quantity, $unit_price, $item_subtotal);
                $itemStmt->execute();
                
                // Update product stock quantity
                $updateQuery = "UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?";
                $updateStmt = $conn->prepare($updateQuery);
                $updateStmt->bind_param("ii", $quantity, $product_id);
                $updateStmt->execute();
            }
            
            // Commit the transaction
            $conn->commit();
            
            // Set success message
            $success = "Order #$invoice_no has been successfully processed!";
            
            // Redirect to print and PDF page
            header("Location: print_and_pdf.php?id=$sale_id");
            exit;
            
        } catch (Exception $e) {
            // Rollback the transaction in case of error
            $conn->rollback();
            $error = "Error processing order: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Billing - Bakery POS System</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
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
        .product-card {
            cursor: pointer;
            transition: all 0.3s ease;
            height: 100%;
            border: 2px solid transparent;
        }
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            border-color: #ff6b6b;
        }
        .product-card:active {
            transform: translateY(-2px);
            transition: transform 0.1s ease;
        }
        .cart-item {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
            transition: all 0.3s ease;
            border-radius: 8px;
            margin-bottom: 10px;
            background: #f8f9fa;
        }
        .cart-item:hover {
            background: #e9ecef;
            transform: translateX(5px);
        }
        .item-quantity {
            width: 60px;
            text-align: center;
            border-radius: 5px;
        }
        .bill-details {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-radius: 10px;
            padding: 20px;
            border: 1px solid #dee2e6;
        }
        .search-container {
            position: relative;
        }
        .search-results {
            position: absolute;
            top: 100%;
            left: 0;
            width: 100%;
            max-height: 300px;
            overflow-y: auto;
            background-color: white;
            border: 1px solid #ddd;
            border-radius: 0 0 8px 8px;
            z-index: 1000;
            display: none;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .search-result-item {
            padding: 12px 15px;
            cursor: pointer;
            border-bottom: 1px solid #f0f0f0;
            transition: background-color 0.2s ease;
        }
        .search-result-item:hover {
            background-color: #f5f5f5;
        }
        
        /* Toast notifications */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1060;
        }
        
        .custom-toast {
            min-width: 300px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        /* Loading animation */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255,255,255,0.9);
            display: none;
            z-index: 2000;
            justify-content: center;
            align-items: center;
        }
        
        .loading-spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #ff6b6b;
            border-radius: 50%;
            width: 60px;
            height: 60px;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Button enhancements */
        .btn {
            transition: all 0.3s ease;
            border-radius: 8px;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        
        .discount-btn.active {
            background: linear-gradient(135deg, #ff6b6b, #ff5252) !important;
            color: white !important;
            border-color: #ff6b6b !important;
        }
        
        /* Cart animations */
        .cart-add-animation {
            animation: cartAdd 0.6s ease;
        }
        
        @keyframes cartAdd {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); background-color: #d4edda; }
            100% { transform: scale(1); }
        }
        
        
        /* Responsive improvements */
        @media (max-width: 768px) {
            .product-card {
                margin-bottom: 15px;
            }
            
            .bill-details {
                margin-top: 20px;
            }
        }
    </style>
</head>
<body>

<!-- Toast container for notifications -->
<div class="toast-container" id="toastContainer"></div>

<!-- Loading overlay -->
<div class="loading-overlay" id="loadingOverlay">
    <div class="text-center">
        <div class="loading-spinner"></div>
        <p class="mt-3">Processing order...</p>
    </div>
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
                    <a href="index.php" class="nav-btn">
                        <i class="fas fa-home"></i> Dashboard
                    </a>
                    <a href="billing.php" class="nav-btn active">
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
                        <div><strong><?php echo htmlspecialchars($cashier_name); ?></strong></div>
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
                Billing & Checkout
            </h2>
            <p class="text-muted mb-0">Process orders and generate invoices</p>
        </div>
        <div class="col-auto">
            <div class="d-flex gap-2">
                <button class="btn btn-outline-primary btn-sm" onclick="clearCart()">
                    <i class="fas fa-trash"></i> Clear Cart
                </button>
                <button class="btn btn-outline-success btn-sm" onclick="location.reload()">
                    <i class="fas fa-refresh"></i> Refresh
                </button>
            </div>
        </div>
    </div>
    
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($success)): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
            
            <div class="row">
                <!-- Left side: Product selection -->
                <div class="col-md-8">
                    <div class="card mb-4">
                        <div class="card-header">
                            <div class="row align-items-center">
                                <div class="col-md-4">
                                    <h5 class="mb-0">Select Products</h5>
                                </div>
                                <div class="col-md-4">
                                    <div class="search-container">
                                        <input type="text" id="productSearch" class="form-control" placeholder="Search products...">
                                        <div id="searchResults" class="search-results"></div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <select id="categoryFilter" class="form-select">
                                        <option value="">All Categories</option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?php echo htmlspecialchars($category['name']); ?>">
                                                <?php echo htmlspecialchars($category['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                        <option value="Uncategorized">Uncategorized</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div id="productContainer">
                                <?php foreach ($productsByCategory as $category => $categoryProducts): ?>
                                    <div class="category-section mb-4" data-category="<?php echo htmlspecialchars($category); ?>">
                                        <h5><?php echo htmlspecialchars($category); ?></h5>
                                        <div class="row row-cols-1 row-cols-md-3 g-4">
                                            <?php foreach ($categoryProducts as $product): ?>
                                                <div class="col">
                                                    <div class="card product-card" onclick="addToCart(<?php echo json_encode($product); ?>)">
                                                        <?php if (!empty($product['image']) && file_exists('../' . $product['image'])): ?>
                                                            <img src="<?php echo '../' . $product['image']; ?>" class="card-img-top" alt="<?php echo htmlspecialchars($product['name']); ?>" style="height: 120px; object-fit: cover;">
                                                        <?php else: ?>
                                                            <div class="card-img-top d-flex align-items-center justify-content-center bg-light" style="height: 120px;">
                                                                <i class="fas fa-cookie fa-3x text-muted"></i>
                                                            </div>
                                                        <?php endif; ?>
                                                        <div class="card-body">
                                                            <h6 class="card-title"><?php echo htmlspecialchars($product['name']); ?></h6>
                                                            <p class="card-text text-primary fw-bold">
                                                                $<?php echo number_format($product['price'], 2); ?>
                                                            </p>
                                                            <p class="card-text small">
                                                                <small class="text-muted">
                                                                    Stock: <?php echo $product['stock_quantity']; ?>
                                                                </small>
                                                            </p>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Right side: Cart and billing -->
                <div class="col-md-4">
                    <form id="billingForm" method="POST" action="billing.php">
                        <input type="hidden" name="cart_items" id="cart_items_input" value='<?php echo htmlspecialchars(json_encode($cart_items)); ?>'>
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-shopping-cart"></i> Shopping Cart
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <label for="invoice_no" class="form-label">Invoice #</label>
                                            <input type="text" class="form-control" id="invoice_no" value="<?php echo $invoice_no; ?>" readonly>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="current_date" class="form-label">Date</label>
                                            <input type="text" class="form-control" id="current_date" value="<?php echo $currentDate; ?>" readonly>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="customer_name" class="form-label">Customer Name (Optional)</label>
                                    <input type="text" class="form-control" id="customer_name" name="customer_name">
                                </div>
                                
                                <div id="cart-items" class="mb-3" style="max-height: 300px; overflow-y: auto;">
                                    <div class="text-center py-4 text-muted" id="emptyCartMessage">
                                        <i class="fas fa-shopping-cart fa-2x mb-2"></i>
                                        <p>Cart is empty. Add products to continue.</p>
                                    </div>
                                </div>
                                
                                <hr>
                                
                                <div class="bill-details">
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Subtotal:</span>
                                        <span id="subtotal">$<?php echo number_format($cart_subtotal, 2); ?></span>
                                        <input type="hidden" name="subtotal" id="subtotal_input" value="<?php echo $cart_subtotal; ?>">
                                    </div>
                                    
                                    <div class="d-flex justify-content-between mb-2">
                                        <div class="d-flex align-items-center">
                                            <span>Tax:</span>
                                            <div class="input-group input-group-sm ms-2" style="width: 80px;">
                                                <input type="number" class="form-control" id="tax_rate" name="tax_rate" value="10" min="0" max="100" step="0.01">
                                                <span class="input-group-text">%</span>
                                            </div>
                                        </div>
                                        <span id="tax_amount">$<?php echo number_format($cart_tax, 2); ?></span>
                                        <input type="hidden" name="tax_amount" id="tax_amount_input" value="<?php echo $cart_tax; ?>">
                                    </div>
                                    
                                    <div class="d-flex justify-content-between mb-2">
                                        <div>
                                            <span>Discount:</span>
                                            <div class="btn-group btn-group-sm ms-2">
                                                <button type="button" class="btn btn-outline-secondary discount-btn" data-value="0">0%</button>
                                                <button type="button" class="btn btn-outline-secondary discount-btn" data-value="5">5%</button>
                                                <button type="button" class="btn btn-outline-secondary discount-btn" data-value="10">10%</button>
                                            </div>
                                        </div>
                                        <span id="discount_amount">$0.00</span>
                                        <input type="hidden" name="discount_amount" id="discount_amount_input" value="0">
                                        <input type="hidden" id="discount_percent" value="0">
                                    </div>
                                    
                                    <div class="d-flex justify-content-between mb-2 fw-bold">
                                        <span>Total:</span>
                                        <span id="total_amount">$<?php echo number_format($cart_total, 2); ?></span>
                                        <input type="hidden" name="total_amount" id="total_amount_input" value="<?php echo $cart_total; ?>">
                                    </div>
                                </div>
                                
                                <hr>
                                
                                <div class="mb-3">
                                    <label class="form-label">Payment Method</label>
                                    <div class="d-flex">
                                        <div class="form-check me-3">
                                            <input class="form-check-input" type="radio" name="payment_method" id="payment_cash" value="cash" checked>
                                            <label class="form-check-label" for="payment_cash">
                                                Cash
                                            </label>
                                        </div>
                                        <div class="form-check me-3">
                                            <input class="form-check-input" type="radio" name="payment_method" id="payment_card" value="card">
                                            <label class="form-check-label" for="payment_card">
                                                Card
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="payment_method" id="payment_other" value="other">
                                            <label class="form-check-label" for="payment_other">
                                                Other
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="notes" class="form-label">Notes</label>
                                    <textarea class="form-control" id="notes" name="notes" rows="2"></textarea>
                                </div>
                                
                                <input type="hidden" name="cart_items" id="cart_items_input" value="">
                                
                                <div class="d-grid gap-2">
                                    <button type="button" class="btn btn-danger" onclick="clearCart()">
                                        <i class="fas fa-trash"></i> Clear Cart
                                    </button>
                                    <button type="submit" class="btn btn-primary" name="save_order" id="save_order_btn" <?php echo empty($cart_items) ? 'disabled' : ''; ?>>
                                        <i class="fas fa-print"></i> Complete Order & Print PDF
                                    </button>
                                </div>
                            </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Initialize cart with session data
    let cart = <?php echo json_encode($cart_items); ?>;
    const taxRate = parseFloat(document.getElementById('tax_rate').value) || 10;
    let discountPercent = 0;
    
    // Initialize the display when page loads
    document.addEventListener('DOMContentLoaded', function() {
        updateCartDisplay();
        updateCartSummary();
    });
    
    // Add product to cart
    function addToCart(product) {
        // Check if product is already in cart
        const existingItemIndex = cart.findIndex(item => item.id === product.id);
        
        if (existingItemIndex !== -1) {
            // Product exists in cart, increment quantity
            if (cart[existingItemIndex].quantity < product.stock_quantity) {
                cart[existingItemIndex].quantity += 1;
                updateCartDisplay();
                showToast(`${product.name} quantity updated`, 'success');
            } else {
                showToast('Cannot add more of this product. Maximum stock reached.', 'warning');
            }
        } else {
            // Add new product to cart
            cart.push({
                id: product.id,
                name: product.name,
                price: parseFloat(product.price),
                quantity: 1,
                max_quantity: parseInt(product.stock_quantity)
            });
            updateCartDisplay();
            showToast(`${product.name} added to cart`, 'success');
        }
        
        // Add visual feedback to the cart area
        const cartContainer = document.getElementById('cart-items');
        cartContainer.classList.add('cart-add-animation');
        setTimeout(() => {
            cartContainer.classList.remove('cart-add-animation');
        }, 600);
    }
    
    // Remove item from cart
    function removeFromCart(index) {
        const itemName = cart[index].name;
        cart.splice(index, 1);
        updateCartDisplay();
        showToast(`${itemName} removed from cart`, 'info');
    }
    
    // Update quantity of an item in cart
    function updateQuantity(index, newQuantity) {
        if (newQuantity < 1) {
            newQuantity = 1;
        } else if (newQuantity > cart[index].max_quantity) {
            newQuantity = cart[index].max_quantity;
            showToast(`Maximum available quantity for ${cart[index].name} is ${cart[index].max_quantity}`, 'warning');
        }
        
        cart[index].quantity = newQuantity;
        updateCartDisplay();
    }
    
    // Update the cart display
    function updateCartDisplay() {
        const cartContainer = document.getElementById('cart-items');
        const emptyCartMessage = document.getElementById('emptyCartMessage');
        const saveOrderBtn = document.getElementById('save_order_btn');
        
        if (cart.length === 0) {
            cartContainer.innerHTML = `
                <div class="text-center py-4 text-muted" id="emptyCartMessage">
                    <i class="fas fa-shopping-cart fa-2x mb-2"></i>
                    <p>Cart is empty. Add products to continue.</p>
                </div>
            `;
            saveOrderBtn.disabled = true;
        } else {
            let cartHTML = '';
            
            cart.forEach((item, index) => {
                const itemTotal = item.price * item.quantity;
                
                cartHTML += `
                    <div class="cart-item">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <strong>${item.name}</strong>
                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeFromCart(${index})">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <small>$${item.price.toFixed(2)} x</small>
                                <input type="number" min="1" max="${item.max_quantity}" value="${item.quantity}" 
                                       class="item-quantity ms-1" onchange="updateQuantity(${index}, parseInt(this.value))">
                            </div>
                            <strong>$${itemTotal.toFixed(2)}</strong>
                        </div>
                    </div>
                `;
            });
            
            cartContainer.innerHTML = cartHTML;
            saveOrderBtn.disabled = false;
        }
        
        // Update the cart summary
        updateCartSummary();
        
        // Update PDF button state
        updatePDFButtonState();
        
        // Update hidden input for form submission
        document.getElementById('cart_items_input').value = JSON.stringify(cart);
    }
    
    // Update cart summary (subtotal, tax, discount, total)
    function updateCartSummary() {
        const subtotalElement = document.getElementById('subtotal');
        const subtotalInput = document.getElementById('subtotal_input');
        const taxAmountElement = document.getElementById('tax_amount');
        const taxAmountInput = document.getElementById('tax_amount_input');
        const discountAmountElement = document.getElementById('discount_amount');
        const discountAmountInput = document.getElementById('discount_amount_input');
        const totalAmountElement = document.getElementById('total_amount');
        const totalAmountInput = document.getElementById('total_amount_input');
        
        // Calculate subtotal
        const subtotal = cart.reduce((total, item) => total + (item.price * item.quantity), 0);
        
        // Calculate tax amount
        const taxRate = parseFloat(document.getElementById('tax_rate').value) || 0;
        const taxAmount = subtotal * (taxRate / 100);
        
        // Calculate discount
        const discountAmount = subtotal * (discountPercent / 100);
        
        // Calculate total
        const totalAmount = subtotal + taxAmount - discountAmount;
        
        // Update display elements
        subtotalElement.textContent = '$' + subtotal.toFixed(2);
        taxAmountElement.textContent = '$' + taxAmount.toFixed(2);
        discountAmountElement.textContent = '$' + discountAmount.toFixed(2);
        totalAmountElement.textContent = '$' + totalAmount.toFixed(2);
        
        // Update hidden inputs for form submission
        subtotalInput.value = subtotal.toFixed(2);
        taxAmountInput.value = taxAmount.toFixed(2);
        discountAmountInput.value = discountAmount.toFixed(2);
        totalAmountInput.value = totalAmount.toFixed(2);
    }
    
    // Clear the cart
    function clearCart() {
        if (confirm('Are you sure you want to clear the cart?')) {
            cart = [];
            updateCartDisplay();
            showToast('Cart cleared', 'info');
        }
    }
    
    // Show toast notification
    function showToast(message, type = 'info') {
        const toastContainer = document.getElementById('toastContainer');
        const toastId = 'toast-' + Date.now();
        
        const bgClass = {
            'success': 'bg-success',
            'error': 'bg-danger',
            'warning': 'bg-warning',
            'info': 'bg-info'
        }[type] || 'bg-info';
        
        const iconClass = {
            'success': 'fas fa-check-circle',
            'error': 'fas fa-exclamation-circle',
            'warning': 'fas fa-exclamation-triangle',
            'info': 'fas fa-info-circle'
        }[type] || 'fas fa-info-circle';
        
        const toastHTML = `
            <div class="toast custom-toast ${bgClass} text-white" id="${toastId}" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="toast-header ${bgClass} text-white border-0">
                    <i class="${iconClass} me-2"></i>
                    <strong class="me-auto">Notification</strong>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
                </div>
                <div class="toast-body">
                    ${message}
                </div>
            </div>
        `;
        
        toastContainer.insertAdjacentHTML('beforeend', toastHTML);
        
        const toastElement = document.getElementById(toastId);
        const toast = new bootstrap.Toast(toastElement, {
            autohide: true,
            delay: 3000
        });
        
        toast.show();
        
        // Remove toast element after it's hidden
        toastElement.addEventListener('hidden.bs.toast', function() {
            toastElement.remove();
        });
    }
    
    // Show loading overlay
    function showLoading() {
        document.getElementById('loadingOverlay').style.display = 'flex';
    }
    
    // Hide loading overlay
    function hideLoading() {
        document.getElementById('loadingOverlay').style.display = 'none';
    }
    
    // Update PDF button state
    function updatePDFButtonState() {
        const saveOrderBtn = document.getElementById('save_order_btn');
        if (cart.length === 0) {
            saveOrderBtn.disabled = true;
            saveOrderBtn.classList.add('disabled');
        } else {
            saveOrderBtn.disabled = false;
            saveOrderBtn.classList.remove('disabled');
        }
    }
    
    // Event listeners
    document.addEventListener('DOMContentLoaded', function() {
        // Category filter
        document.getElementById('categoryFilter').addEventListener('change', function() {
            const selectedCategory = this.value;
            const categorySections = document.querySelectorAll('.category-section');
            
            if (selectedCategory === '') {
                // Show all categories
                categorySections.forEach(section => {
                    section.style.display = 'block';
                });
            } else {
                // Show only the selected category
                categorySections.forEach(section => {
                    if (section.dataset.category === selectedCategory) {
                        section.style.display = 'block';
                    } else {
                        section.style.display = 'none';
                    }
                });
            }
        });
        
        // Tax rate change
        document.getElementById('tax_rate').addEventListener('change', function() {
            updateCartSummary();
        });
        
        // Discount buttons
        document.querySelectorAll('.discount-btn').forEach(button => {
            button.addEventListener('click', function() {
                // Update active button state
                document.querySelectorAll('.discount-btn').forEach(btn => {
                    btn.classList.remove('active', 'btn-secondary');
                    btn.classList.add('btn-outline-secondary');
                });
                this.classList.remove('btn-outline-secondary');
                this.classList.add('active', 'btn-secondary');
                
                // Update discount percentage
                discountPercent = parseFloat(this.dataset.value);
                document.getElementById('discount_percent').value = discountPercent;
                
                // Update cart summary
                updateCartSummary();
            });
        });
        
        // Product search
        const productSearch = document.getElementById('productSearch');
        const searchResults = document.getElementById('searchResults');
        
        productSearch.addEventListener('input', function() {
            const searchTerm = this.value.trim().toLowerCase();
            
            if (searchTerm.length < 2) {
                searchResults.style.display = 'none';
                return;
            }
            
            // Filter products based on search term
            const matchingProducts = <?php echo json_encode($products); ?>.filter(product => {
                return product.name.toLowerCase().includes(searchTerm) || 
                       (product.barcode && product.barcode.includes(searchTerm));
            });
            
            // Display search results
            if (matchingProducts.length > 0) {
                let resultsHTML = '';
                
                matchingProducts.forEach(product => {
                    resultsHTML += `
                        <div class="search-result-item" onclick="addToCart(${JSON.stringify(product).replace(/"/g, '&quot;')})">
                            <div class="d-flex justify-content-between align-items-center">
                                <span>${product.name}</span>
                                <span class="badge bg-primary">$${parseFloat(product.price).toFixed(2)}</span>
                            </div>
                            <small class="text-muted">${product.category_name || 'Uncategorized'} - Stock: ${product.stock_quantity}</small>
                        </div>
                    `;
                });
                
                searchResults.innerHTML = resultsHTML;
                searchResults.style.display = 'block';
            } else {
                searchResults.innerHTML = '<div class="p-3 text-center">No products found</div>';
                searchResults.style.display = 'block';
            }
        });
        
        // Hide search results when clicking outside
        document.addEventListener('click', function(event) {
            if (!productSearch.contains(event.target) && !searchResults.contains(event.target)) {
                searchResults.style.display = 'none';
            }
        });
        
        // Form submission validation
        document.getElementById('billingForm').addEventListener('submit', function(e) {
            if (cart.length === 0) {
                e.preventDefault();
                showToast('Cart is empty. Please add products to the cart.', 'error');
                return false;
            }
            
            // Show loading overlay
            showLoading();
            
            // Optional: Add small delay to show loading animation
            setTimeout(() => {
                // Form will submit naturally
            }, 500);
            
            return true;
        });
        
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl + Enter to submit order
            if (e.ctrlKey && e.key === 'Enter') {
                e.preventDefault();
                if (cart.length > 0) {
                    document.getElementById('billingForm').submit();
                } else {
                    showToast('Cart is empty. Add products first.', 'warning');
                }
            }
            
            // Ctrl + D to clear cart
            if (e.ctrlKey && e.key === 'd') {
                e.preventDefault();
                clearCart();
            }
            
            // F1 to focus search
            if (e.key === 'F1') {
                e.preventDefault();
                document.getElementById('productSearch').focus();
            }
            
            // Escape to close search results
            if (e.key === 'Escape') {
                document.getElementById('searchResults').style.display = 'none';
            }
        });
        
        // Show keyboard shortcuts info
        showToast('Keyboard shortcuts: F1 (Search), Ctrl+Enter (Submit), Ctrl+D (Clear Cart)', 'info');
    });
</script>

</body>
</html>