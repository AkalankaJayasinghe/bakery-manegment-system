<?php
session_start();
require_once 'includes/compatibility.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

// Set page title
$pageTitle = "Point of Sale";

// Database connection
$conn = connectDB();
?>

<?php include_once 'includes/header.php'; ?>

<style>
    .product-grid {
        max-height: 60vh;
        overflow-y: auto;
    }
    .product-card {
        transition: transform 0.2s;
        cursor: pointer;
        height: 200px;
    }
    .product-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
    .product-image {
        height: 100px;
        object-fit: cover;
        width: 100%;
    }
    .cart-section {
        background: #f8f9fa;
        min-height: 80vh;
        border-left: 1px solid #ddd;
    }
    .cart-item {
        padding: 10px;
        border-bottom: 1px solid #ddd;
    }
    .cart-empty {
        text-align: center;
        color: #6c757d;
        padding: 40px 20px;
    }
</style>

<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
    <div class="row">
        <!-- Products Section -->
        <div class="col-md-8">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4>Products</h4>
                <div class="d-flex gap-2">
                    <input type="text" id="searchProduct" class="form-control" placeholder="Search products..." style="width: 250px;">
                    <select id="categoryFilter" class="form-select" style="width: 200px;">
                        <option value="">All Categories</option>
                        <?php
                        $result = $conn->query("SELECT id, name FROM categories WHERE status = 1 ORDER BY name");
                        if ($result) {
                            while ($row = $result->fetch_assoc()) {
                                echo '<option value="'.$row['id'].'">'.$row['name'].'</option>';
                            }
                        }
                        ?>
                    </select>
                </div>
            </div>
            
            <div class="product-grid">
                <div class="row" id="productsContainer">
                    <?php
                    // Fetch all active products with stock
                    $sql = "SELECT p.id, p.name, p.price, p.image, p.stock_quantity as quantity, c.name as category_name, c.id as category_id
                            FROM products p 
                            LEFT JOIN categories c ON p.category_id = c.id
                            WHERE p.status = 1 AND p.stock_quantity > 0
                            ORDER BY p.name ASC";
                    $result = $conn->query($sql);
                    
                    if ($result && $result->num_rows > 0) {
                        while ($product = $result->fetch_assoc()) {
                            $imagePath = !empty($product['image']) ? '../' . $product['image'] : '../assets/images/no-image.jpg';
                            ?>
                            <div class="col-lg-3 col-md-4 col-sm-6 mb-3 product-item" 
                                 data-category="<?php echo $product['category_id']; ?>" 
                                 data-name="<?php echo strtolower($product['name']); ?>">
                                <div class="card product-card" onclick="addToCart(<?php echo $product['id']; ?>, '<?php echo addslashes($product['name']); ?>', <?php echo $product['price']; ?>)">
                                    <img src="<?php echo $imagePath; ?>" class="card-img-top product-image" alt="<?php echo htmlspecialchars($product['name']); ?>">
                                    <div class="card-body p-2">
                                        <h6 class="card-title mb-1"><?php echo htmlspecialchars($product['name']); ?></h6>
                                        <p class="card-text mb-1">
                                            <strong>$<?php echo number_format($product['price'], 2); ?></strong>
                                        </p>
                                        <small class="text-muted">Stock: <?php echo $product['quantity']; ?></small>
                                    </div>
                                </div>
                            </div>
                            <?php
                        }
                    } else {
                        echo '<div class="col-12"><div class="alert alert-info">No products available.</div></div>';
                    }
                    ?>
                </div>
            </div>
        </div>
        
        <!-- Cart Section -->
        <div class="col-md-4 cart-section">
            <div class="p-3">
                <h5 class="mb-3">Current Order</h5>
                
                <!-- Customer Info -->
                <div class="mb-3">
                    <input type="text" id="customerName" class="form-control" placeholder="Customer Name (Optional)">
                </div>
                
                <!-- Cart Items -->
                <div id="cartItems" style="max-height: 300px; overflow-y: auto;">
                    <div class="cart-empty">
                        <i class="fas fa-shopping-cart fa-3x mb-3"></i>
                        <p>Your cart is empty</p>
                        <small>Add products to start an order</small>
                    </div>
                </div>
                
                <!-- Order Summary -->
                <div class="mt-3 pt-3 border-top">
                    <div class="d-flex justify-content-between mb-2">
                        <span>Subtotal:</span>
                        <span id="subtotal">$0.00</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Tax (10%):</span>
                        <span id="tax">$0.00</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Discount:</span>
                        <div class="input-group input-group-sm" style="width: 100px;">
                            <input type="number" id="discount" class="form-control" value="0" min="0" max="100">
                            <span class="input-group-text">%</span>
                        </div>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between mb-3">
                        <strong>Total:</strong>
                        <strong id="total">$0.00</strong>
                    </div>
                    
                    <!-- Payment Method -->
                    <div class="mb-3">
                        <label class="form-label">Payment Method</label>
                        <select id="paymentMethod" class="form-select">
                            <option value="cash">Cash</option>
                            <option value="card">Card</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    
                    <!-- Cash Payment Details -->
                    <div id="cashDetails" class="mb-3">
                        <div class="row">
                            <div class="col-6">
                                <label class="form-label">Amount Tendered</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" id="amountTendered" class="form-control" step="0.01">
                                </div>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Change</label>
                                <input type="text" id="change" class="form-control" value="$0.00" readonly>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="d-grid gap-2">
                        <button id="completeSale" class="btn btn-success" disabled>
                            <i class="fas fa-check-circle"></i> Complete Sale
                        </button>
                        <button id="cancelOrder" class="btn btn-outline-danger">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
<script>
let cart = [];
let taxRate = 0.10; // 10% tax

// Add product to cart
function addToCart(id, name, price) {
    const existingItem = cart.find(item => item.id === id);
    
    if (existingItem) {
        existingItem.quantity += 1;
    } else {
        cart.push({
            id: id,
            name: name,
            price: parseFloat(price),
            quantity: 1
        });
    }
    
    updateCartDisplay();
    updateTotals();
}

// Remove item from cart
function removeFromCart(id) {
    cart = cart.filter(item => item.id !== id);
    updateCartDisplay();
    updateTotals();
}

// Update quantity
function updateQuantity(id, quantity) {
    const item = cart.find(item => item.id === id);
    if (item) {
        item.quantity = parseInt(quantity);
        if (item.quantity <= 0) {
            removeFromCart(id);
        } else {
            updateCartDisplay();
            updateTotals();
        }
    }
}

// Update cart display
function updateCartDisplay() {
    const cartContainer = document.getElementById('cartItems');
    
    if (cart.length === 0) {
        cartContainer.innerHTML = `
            <div class="cart-empty">
                <i class="fas fa-shopping-cart fa-3x mb-3"></i>
                <p>Your cart is empty</p>
                <small>Add products to start an order</small>
            </div>
        `;
        document.getElementById('completeSale').disabled = true;
        return;
    }
    
    let html = '';
    cart.forEach(item => {
        html += `
            <div class="cart-item d-flex justify-content-between align-items-center">
                <div>
                    <strong>${item.name}</strong><br>
                    <small>$${item.price.toFixed(2)} each</small>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <div class="input-group input-group-sm" style="width: 80px;">
                        <button class="btn btn-outline-secondary" onclick="updateQuantity(${item.id}, ${item.quantity - 1})">-</button>
                        <input type="text" class="form-control text-center" value="${item.quantity}" readonly>
                        <button class="btn btn-outline-secondary" onclick="updateQuantity(${item.id}, ${item.quantity + 1})">+</button>
                    </div>
                    <button class="btn btn-danger btn-sm" onclick="removeFromCart(${item.id})">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        `;
    });
    
    cartContainer.innerHTML = html;
    document.getElementById('completeSale').disabled = false;
}

// Update totals
function updateTotals() {
    const subtotal = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
    const discount = parseFloat(document.getElementById('discount').value) || 0;
    const discountAmount = (subtotal * discount) / 100;
    const discountedSubtotal = subtotal - discountAmount;
    const tax = discountedSubtotal * taxRate;
    const total = discountedSubtotal + tax;
    
    document.getElementById('subtotal').textContent = `$${subtotal.toFixed(2)}`;
    document.getElementById('tax').textContent = `$${tax.toFixed(2)}`;
    document.getElementById('total').textContent = `$${total.toFixed(2)}`;
    
    // Update change calculation
    const amountTendered = parseFloat(document.getElementById('amountTendered').value) || 0;
    const change = amountTendered - total;
    document.getElementById('change').value = `$${Math.max(0, change).toFixed(2)}`;
}

// Product search
function setupSearch() {
    document.getElementById('searchProduct').addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        const products = document.querySelectorAll('.product-item');
        
        products.forEach(product => {
            const productName = product.dataset.name;
            if (productName.includes(searchTerm)) {
                product.style.display = 'block';
            } else {
                product.style.display = 'none';
            }
        });
    });
}

// Category filter
function setupCategoryFilter() {
    document.getElementById('categoryFilter').addEventListener('change', function() {
        const categoryId = this.value;
        const products = document.querySelectorAll('.product-item');
        
        products.forEach(product => {
            if (categoryId === '' || product.dataset.category === categoryId) {
                product.style.display = 'block';
            } else {
                product.style.display = 'none';
            }
        });
    });
}

// Payment method change
function setupPaymentMethod() {
    document.getElementById('paymentMethod').addEventListener('change', function() {
        const cashDetails = document.getElementById('cashDetails');
        if (this.value === 'cash') {
            cashDetails.style.display = 'block';
        } else {
            cashDetails.style.display = 'none';
        }
    });
}

// Complete sale
function completeSale() {
    if (cart.length === 0) {
        alert('Cart is empty!');
        return;
    }
    
    const paymentMethod = document.getElementById('paymentMethod').value;
    const customerName = document.getElementById('customerName').value || 'Walk-in Customer';
    const amountTendered = parseFloat(document.getElementById('amountTendered').value) || 0;
    
    const subtotal = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
    const discount = parseFloat(document.getElementById('discount').value) || 0;
    const discountAmount = (subtotal * discount) / 100;
    const discountedSubtotal = subtotal - discountAmount;
    const tax = discountedSubtotal * taxRate;
    const total = discountedSubtotal + tax;
    
    if (paymentMethod === 'cash' && amountTendered < total) {
        alert('Insufficient payment amount!');
        return;
    }
    
    // Process the sale (you can add AJAX call here to save to database)
    const saleData = {
        customer_name: customerName,
        items: cart,
        subtotal: subtotal,
        discount: discount,
        tax: tax,
        total: total,
        payment_method: paymentMethod,
        amount_tendered: amountTendered,
        change: amountTendered - total
    };
    
    // For now, just show success message
    alert('Sale completed successfully!');
    
    // Clear cart
    cart = [];
    updateCartDisplay();
    updateTotals();
    
    // Reset form
    document.getElementById('customerName').value = '';
    document.getElementById('discount').value = '0';
    document.getElementById('amountTendered').value = '';
}

// Cancel order
function cancelOrder() {
    if (cart.length > 0 && confirm('Are you sure you want to cancel this order?')) {
        cart = [];
        updateCartDisplay();
        updateTotals();
        document.getElementById('customerName').value = '';
        document.getElementById('discount').value = '0';
        document.getElementById('amountTendered').value = '';
    }
}

// Initialize everything when page loads
document.addEventListener('DOMContentLoaded', function() {
    setupSearch();
    setupCategoryFilter();
    setupPaymentMethod();
    
    // Event listeners
    document.getElementById('discount').addEventListener('input', updateTotals);
    document.getElementById('amountTendered').addEventListener('input', updateTotals);
    document.getElementById('completeSale').addEventListener('click', completeSale);
    document.getElementById('cancelOrder').addEventListener('click', cancelOrder);
    
    // Initial setup
    updateTotals();
});
</script>

<?php include_once 'includes/footer.php'; ?>
