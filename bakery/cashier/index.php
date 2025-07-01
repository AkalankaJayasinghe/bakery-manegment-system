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

// Check if user has cashier privileges
if (!hasCashierPrivileges()) {
    header("Location: " . SITE_URL . "/index.php?error=unauthorized");
    exit;
}

// Set page title
$pageTitle = "Cashier Dashboard";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/cashier.css">
    <script src="<?php echo ASSETS_URL; ?>/js/jquery.min.js"></script>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Include sidebar -->
            <?php include_once '../includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Point of Sale</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="btnNewOrder">New Order</button>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Products Section -->
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <div class="row">
                                    <div class="col-md-8">
                                        <input type="text" class="form-control" id="searchProduct" placeholder="Search products...">
                                    </div>
                                    <div class="col-md-4">
                                        <select class="form-select" id="categoryFilter">
                                            <option value="0">All Categories</option>
                                            <?php
                                            $conn = getDBConnection();
                                            $result = $conn->query("SELECT id, name FROM categories WHERE status = 1 ORDER BY name");
                                            while ($row = $result->fetch_assoc()) {
                                                echo '<option value="' . $row['id'] . '">' . $row['name'] . '</option>';
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row" id="productsContainer">
                                    <?php
                                    // Fetch all active products
                                    $sql = "SELECT p.id, p.name, p.price, p.image, c.name as category 
                                            FROM products p 
                                            LEFT JOIN categories c ON p.category_id = c.id
                                            WHERE p.status = 1 AND p.stock_quantity > 0
                                            ORDER BY p.name ASC";
                                    $result = $conn->query($sql);
                                    
                                    while ($product = $result->fetch_assoc()) {
                                        $imagePath = !empty($product['image']) ? ASSETS_URL . '/images/' . $product['image'] : ASSETS_URL . '/images/no-image.jpg';
                                        ?>
                                        <div class="col-lg-3 col-md-4 col-sm-6 mb-3 product-item" data-id="<?php echo $product['id']; ?>" data-category="<?php echo $product['category']; ?>">
                                            <div class="card h-100">
                                                <img src="<?php echo $imagePath; ?>" class="card-img-top product-image" alt="<?php echo $product['name']; ?>">
                                                <div class="card-body">
                                                    <h5 class="card-title"><?php echo $product['name']; ?></h5>
                                                    <p class="card-text"><?php echo formatCurrency($product['price']); ?></p>
                                                    <button class="btn btn-primary btn-sm add-to-cart" data-id="<?php echo $product['id']; ?>" data-name="<?php echo $product['name']; ?>" data-price="<?php echo $product['price']; ?>">Add</button>
                                                </div>
                                            </div>
                                        </div>
                                        <?php
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Cart Section -->
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5>Current Order</h5>
                            </div>
                            <div class="card-body">
                                <div class="customer-info mb-3">
                                    <select class="form-select mb-2" id="customerSelect">
                                        <option value="0">Walk-in Customer</option>
                                        <?php
                                        $customers = $conn->query("SELECT id, CONCAT(first_name, ' ', last_name) as name FROM customers ORDER BY name");
                                        while ($customer = $customers->fetch_assoc()) {
                                            echo '<option value="' . $customer['id'] . '">' . $customer['name'] . '</option>';
                                        }
                                        ?>
                                    </select>
                                    <button class="btn btn-sm btn-outline-secondary" id="btnAddCustomer">+ Add New Customer</button>
                                </div>
                                
                                <div class="cart-items-container">
                                    <table class="table table-sm" id="cartTable">
                                        <thead>
                                            <tr>
                                                <th>Item</th>
                                                <th>Price</th>
                                                <th>Qty</th>
                                                <th>Total</th>
                                                <th></th>
                                            </tr>
                                        </thead>
                                        <tbody id="cartItems">
                                            <!-- Cart items will be added dynamically -->
                                        </tbody>
                                    </table>
                                </div>
                                
                                <div class="cart-summary">
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Subtotal:</span>
                                        <span id="subtotal">0.00</span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Tax (<?php echo TAX_RATE ?? '0'; ?>%):</span>
                                        <span id="tax">0.00</span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Discount:</span>
                                        <div class="input-group input-group-sm">
                                            <input type="number" class="form-control" id="discountValue" value="0" min="0">
                                            <select class="form-select" id="discountType">
                                                <option value="percentage">%</option>
                                                <option value="fixed"><?php echo CURRENCY_SYMBOL; ?></option>
                                            </select>
                                        </div>
                                    </div>
                                    <hr>
                                    <div class="d-flex justify-content-between mb-2">
                                        <h5>Total:</h5>
                                        <h5 id="total"><?php echo CURRENCY_SYMBOL; ?>0.00</h5>
                                    </div>
                                </div>
                                
                                <div class="payment-section mt-3">
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Payment Method:</span>
                                        <select class="form-select form-select-sm" id="paymentMethod">
                                            <option value="cash">Cash</option>
                                            <option value="card">Card</option>
                                        </select>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2" id="cashPaymentFields">
                                        <span>Amount Tendered:</span>
                                        <input type="number" class="form-control form-control-sm" id="amountTendered" min="0">
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Change:</span>
                                        <span id="change"><?php echo CURRENCY_SYMBOL; ?>0.00</span>
                                    </div>
                                </div>
                                
                                <div class="action-buttons mt-3">
                                    <button class="btn btn-success w-100 mb-2" id="btnCompleteOrder">Complete Order</button>
                                    <button class="btn btn-outline-secondary w-100" id="btnCancelOrder">Cancel Order</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Add Customer Modal -->
    <div class="modal fade" id="addCustomerModal" tabindex="-1" aria-labelledby="addCustomerModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addCustomerModalLabel">Add New Customer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="addCustomerForm">
                        <div class="mb-3">
                            <label for="firstName" class="form-label">First Name</label>
                            <input type="text" class="form-control" id="firstName" required>
                        </div>
                        <div class="mb-3">
                            <label for="lastName" class="form-label">Last Name</label>
                            <input type="text" class="form-control" id="lastName" required>
                        </div>
                        <div class="mb-3">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="text" class="form-control" id="phone" required>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email">
                        </div>
                        <div class="mb-3">
                            <label for="address" class="form-label">Address</label>
                            <textarea class="form-control" id="address" rows="3"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="btnSaveCustomer">Save Customer</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Invoice Modal -->
    <div class="modal fade" id="invoiceModal" tabindex="-1" aria-labelledby="invoiceModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="invoiceModalLabel">Invoice</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="invoiceContent">
                    <!-- Invoice content will be dynamically generated -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="btnPrintInvoice">Print Invoice</button>
                </div>
            </div>
        </div>
    </div>

    <script src="<?php echo ASSETS_URL; ?>/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/cashier.js"></script>
</body>
</html>
