<?php
session_start();
require_once '../config/database.php';
require_once '../config/constants.php';
require_once '../includes/functions.php';

// Define the logActivity function if not already defined
if (!function_exists('logActivity')) {
    function logActivity($action, $description) {
        // Example implementation: Log activity to a file or database
        $logFile = '../logs/activity.log';
        $logEntry = date('Y-m-d H:i:s') . " - $action: $description" . PHP_EOL;
        file_put_contents($logFile, $logEntry, FILE_APPEND);
    }
}

// Define the hasAdminPrivileges function if not already defined
if (!function_exists('hasAdminPrivileges')) {
    function hasAdminPrivileges() {
        // Example logic: Check if the user role is 'admin'
        return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
    }
}

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
$id = 0;
$name = '';
$description = '';
$category_id = '';
$price = '';
$cost_price = '';
$stock_quantity = '';
$reorder_level = 10;
$status = 1;
$error = '';
$success = '';
$image = '';

// Handle form submissions for product operations
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        // Sanitize inputs
        $name = htmlspecialchars($_POST['name'], ENT_QUOTES, 'UTF-8');
        $description = htmlspecialchars($_POST['description'], ENT_QUOTES, 'UTF-8');
        $category_id = $_POST['category_id'];
        $price = floatval($_POST['price']);
        $cost_price = floatval($_POST['cost_price']);
        $stock_quantity = intval($_POST['stock_quantity']);
        $reorder_level = intval($_POST['reorder_level']);
        $status = isset($_POST['status']) ? 1 : 0;
        
        // Validate inputs
        if (empty($name)) {
            $error = "Product name is required";
        } elseif ($price <= 0) {
            $error = "Price must be greater than zero";
        } elseif ($cost_price < 0) {
            $error = "Cost price cannot be negative";
        } elseif ($stock_quantity < 0) {
            $error = "Stock quantity cannot be negative";
        } elseif ($reorder_level < 0) {
            $error = "Reorder level cannot be negative";
        } else {
            $image_name = '';
            
            // Handle image upload if a file was provided
            if (isset($_FILES['image']) && $_FILES['image']['size'] > 0) {
                $target_dir = "../assets/images/products/";
                $file_extension = strtolower(pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION));
                $image_name = "product_" . time() . "." . $file_extension;
                $target_file = $target_dir . $image_name;
                
                // Create directory if it doesn't exist
                if (!file_exists($target_dir)) {
                    mkdir($target_dir, 0777, true);
                }
                
                // Check file size
                if ($_FILES["image"]["size"] > MAX_FILE_SIZE) {
                    $error = "File is too large. Maximum file size is " . (MAX_FILE_SIZE / (1024 * 1024)) . "MB";
                }
                // Check file type
                elseif (!in_array($file_extension, ALLOWED_IMAGE_TYPES)) {
                    $error = "Only JPG, JPEG, PNG and GIF files are allowed";
                }
                // Upload file
                elseif (!move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
                    $error = "Error uploading file";
                }
            }
            
            if (empty($error)) {
                // Process based on action (add or edit)
                switch ($_POST['action']) {
                    case 'add':
                        // Check if product already exists
                        $stmt = $conn->prepare("SELECT id FROM products WHERE name = ?");
                        $stmt->bind_param("s", $name);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        
                        if ($result->num_rows > 0) {
                            $error = "Product '$name' already exists";
                        } else {
                            // Insert new product
                            $stmt = $conn->prepare("INSERT INTO products (name, description, category_id, price, cost_price, stock_quantity, reorder_level, image, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                            $stmt->bind_param("sssddiiis", $name, $description, $category_id, $price, $cost_price, $stock_quantity, $reorder_level, $image_name, $status);
                            
                            if ($stmt->execute()) {
                                $product_id = $conn->insert_id;
                                
                                // Record stock movement
                                if ($stock_quantity > 0) {
                                    $stmt = $conn->prepare("INSERT INTO stock_movements (product_id, quantity, movement_type, notes, created_by) VALUES (?, ?, 'adjustment', 'Initial stock', ?)");
                                    $stmt->bind_param("iis", $product_id, $stock_quantity, $_SESSION['user_id']);
                                    $stmt->execute();
                                }
                                
                                $success = "Product added successfully";
                                logActivity('add_product', "Added new product: $name");
                                
                                // Clear form fields
                                $name = '';
                                $description = '';
                                $category_id = '';
                                $price = '';
                                $cost_price = '';
                                $stock_quantity = '';
                                $reorder_level = 10;
                                $status = 1;
                            } else {
                                $error = "Error adding product: " . $conn->error;
                            }
                        }
                        break;
                        
                    case 'edit':
                        $id = $_POST['id'];
                        
                        // Check if another product with the same name exists
                        $stmt = $conn->prepare("SELECT id FROM products WHERE name = ? AND id != ?");
                        $stmt->bind_param("si", $name, $id);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        
                        if ($result->num_rows > 0) {
                            $error = "Another product with name '$name' already exists";
                        } else {
                            // Get current stock quantity
                            $stmt = $conn->prepare("SELECT stock_quantity, image FROM products WHERE id = ?");
                            $stmt->bind_param("i", $id);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            $current_product = $result->fetch_assoc();
                            $old_stock = $current_product['stock_quantity'];
                            $old_image = $current_product['image'];
                            
                            // Prepare SQL based on whether a new image was uploaded
                            if (!empty($image_name)) {
                                $sql = "UPDATE products SET name = ?, description = ?, category_id = ?, price = ?, cost_price = ?, stock_quantity = ?, reorder_level = ?, image = ?, status = ?, updated_at = NOW() WHERE id = ?";
                                $stmt = $conn->prepare($sql);
                                $stmt->bind_param("sssddiisii", $name, $description, $category_id, $price, $cost_price, $stock_quantity, $reorder_level, $image_name, $status, $id);
                                
                                // Delete old image if it exists
                                if (!empty($old_image)) {
                                    $old_image_path = "../assets/images/products/" . $old_image;
                                    if (file_exists($old_image_path)) {
                                        unlink($old_image_path);
                                    }
                                }
                            } else {
                                $sql = "UPDATE products SET name = ?, description = ?, category_id = ?, price = ?, cost_price = ?, stock_quantity = ?, reorder_level = ?, status = ?, updated_at = NOW() WHERE id = ?";
                                $stmt = $conn->prepare($sql);
                                $stmt->bind_param("sssddiiis", $name, $description, $category_id, $price, $cost_price, $stock_quantity, $reorder_level, $status, $id);
                            }
                            
                            if ($stmt->execute()) {
                                // Record stock movement if quantity changed
                                if ($stock_quantity != $old_stock) {
                                    $quantity_change = $stock_quantity - $old_stock;
                                    $stmt = $conn->prepare("INSERT INTO stock_movements (product_id, quantity, movement_type, notes, created_by) VALUES (?, ?, 'adjustment', 'Stock adjusted during product edit', ?)");
                                    $stmt->bind_param("iis", $id, $quantity_change, $_SESSION['user_id']);
                                    $stmt->execute();
                                }
                                
                                $success = "Product updated successfully";
                                logActivity('edit_product', "Updated product: $name (ID: $id)");
                                
                                // Reset form
                                $id = 0;
                                $name = '';
                                $description = '';
                                $category_id = '';
                                $price = '';
                                $cost_price = '';
                                $stock_quantity = '';
                                $reorder_level = 10;
                                $status = 1;
                                $image = '';
                            } else {
                                $error = "Error updating product: " . $conn->error;
                            }
                        }
                        break;
                }
            }
        }
    }
    
    // Handle delete action
    if (isset($_POST['delete'])) {
        $id = $_POST['delete'];
        
        // Check if product has sales history
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM invoice_items WHERE product_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        if ($row['count'] > 0) {
            $error = "Cannot delete product because it has sales history. You can deactivate it instead.";
        } else {
            // Get product name and image for logging
            $stmt = $conn->prepare("SELECT name, image FROM products WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $product = $result->fetch_assoc();
            
            // Delete product
            $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute()) {
                // Delete product image if it exists
                if (!empty($product['image'])) {
                    $image_path = "../assets/images/products/" . $product['image'];
                    if (file_exists($image_path)) {
                        unlink($image_path);
                    }
                }
                
                // Delete related stock movements
                $stmt = $conn->prepare("DELETE FROM stock_movements WHERE product_id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                
                $success = "Product deleted successfully";
                logActivity('delete_product', "Deleted product: {$product['name']} (ID: $id)");
            } else {
                $error = "Error deleting product: " . $conn->error;
            }
        }
    }
    
    // Handle status change
    if (isset($_POST['change_status'])) {
        $id = $_POST['change_status'];
        $status = $_POST['status'];
        
        $stmt = $conn->prepare("UPDATE products SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("ii", $status, $id);
        
        if ($stmt->execute()) {
            $statusText = ($status == 1) ? "activated" : "deactivated";
            $success = "Product $statusText successfully";
            
            // Get product name for logging
            $stmt = $conn->prepare("SELECT name FROM products WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $product = $result->fetch_assoc();
            
            logActivity('change_product_status', "Changed product status: {$product['name']} (ID: $id) - $statusText");
        } else {
            $error = "Error changing product status: " . $conn->error;
        }
    }
}

// Handle edit request
if (isset($_GET['edit'])) {
    $id = $_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $product = $result->fetch_assoc();
        $name = $product['name'];
        $description = $product['description'];
        $category_id = $product['category_id'];
        $price = $product['price'];
        $cost_price = $product['cost_price'];
        $stock_quantity = $product['stock_quantity'];
        $reorder_level = $product['reorder_level'];
        $status = $product['status'];
        $image = $product['image'];
    } else {
        $error = "Product not found";
    }
}

// Get all categories for dropdown
$categories = [];
$result = $conn->query("SELECT id, name FROM categories WHERE status = 1 ORDER BY name ASC");
while ($row = $result->fetch_assoc()) {
    $categories[] = $row;
}

// Get all products with pagination
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = ITEMS_PER_PAGE;
$offset = ($page - 1) * $limit;

// Count total products for pagination
$countResult = $conn->query("SELECT COUNT(*) as total FROM products");
$totalProducts = $countResult->fetch_assoc()['total'];
$totalPages = ceil($totalProducts / $limit);

// Get products for current page
$products = [];
$sql = "SELECT p.*, c.name as category_name 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        ORDER BY p.name ASC 
        LIMIT $offset, $limit";
$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $products[] = $row;
}

// Set page title
$pageTitle = "Manage Products";

// Include header
include_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Include sidebar -->
        <?php include_once '../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Manage Products</h1>
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
            
            <div class="row">
                <!-- Product Form -->
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h5><?php echo ($id > 0) ? 'Edit Product' : 'Add New Product'; ?></h5>
                        </div>
                        <div class="card-body">
                            <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="<?php echo ($id > 0) ? 'edit' : 'add'; ?>">
                                <?php if ($id > 0): ?>
                                <input type="hidden" name="id" value="<?php echo $id; ?>">
                                <?php endif; ?>
                                
                                <div class="mb-3">
                                    <label for="name" class="form-label">Product Name</label>
                                    <input type="text" class="form-control" id="name" name="name" value="<?php echo $name; ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="description" class="form-label">Description</label>
                                    <textarea class="form-control" id="description" name="description" rows="3"><?php echo $description; ?></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="category_id" class="form-label">Category</label>
                                    <select class="form-select" id="category_id" name="category_id">
                                        <option value="">Select Category</option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?php echo $category['id']; ?>" <?php echo ($category_id == $category['id']) ? 'selected' : ''; ?>>
                                                <?php echo $category['name']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col">
                                        <label for="price" class="form-label">Selling Price</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><?php echo CURRENCY_SYMBOL; ?></span>
                                            <input type="number" class="form-control" id="price" name="price" step="0.01" min="0" value="<?php echo $price; ?>" required>
                                        </div>
                                    </div>
                                    <div class="col">
                                        <label for="cost_price" class="form-label">Cost Price</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><?php echo CURRENCY_SYMBOL; ?></span>
                                            <input type="number" class="form-control" id="cost_price" name="cost_price" step="0.01" min="0" value="<?php echo $cost_price; ?>" required>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col">
                                        <label for="stock_quantity" class="form-label">Stock Quantity</label>
                                        <input type="number" class="form-control" id="stock_quantity" name="stock_quantity" min="0" value="<?php echo $stock_quantity; ?>" required>
                                    </div>
                                    <div class="col">
                                        <label for="reorder_level" class="form-label">Reorder Level</label>
                                        <input type="number" class="form-control" id="reorder_level" name="reorder_level" min="0" value="<?php echo $reorder_level; ?>" required>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="image" class="form-label">Product Image</label>
                                    <input type="file" class="form-control" id="image" name="image" accept="image/*">
                                    <?php if (!empty($image)): ?>
                                    <div class="mt-2">
                                        <small>Current Image:</small>
                                        <img src="<?php echo ASSETS_URL; ?>/images/products/<?php echo $image; ?>" alt="<?php echo $name; ?>" class="img-thumbnail" style="max-height: 100px;">
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="mb-3 form-check">
                                    <input type="checkbox" class="form-check-input" id="status" name="status" <?php echo ($status == 1) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="status">Active</label>
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary"><?php echo ($id > 0) ? 'Update Product' : 'Add Product'; ?></button>
                                    <?php if ($id > 0): ?>
                                    <a href="products.php" class="btn btn-secondary">Cancel</a>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Products List -->
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5>Products List</h5>
                            <span class="badge bg-primary"><?php echo $totalProducts; ?> Products</span>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Image</th>
                                            <th>Name</th>
                                            <th>Category</th>
                                            <th>Price</th>
                                            <th>Stock</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (count($products) > 0): ?>
                                            <?php foreach ($products as $product): ?>
                                                <tr>
                                                    <td>
                                                        <?php if (!empty($product['image'])): ?>
                                                            <img src="<?php echo ASSETS_URL; ?>/images/products/<?php echo $product['image']; ?>" alt="<?php echo $product['name']; ?>" class="img-thumbnail" style="max-height: 50px;">
                                                        <?php else: ?>
                                                            <img src="<?php echo ASSETS_URL; ?>/images/no-image.jpg" alt="No Image" class="img-thumbnail" style="max-height: 50px;">
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo $product['name']; ?></td>
                                                    <td><?php echo $product['category_name']; ?></td>
                                                    <td><?php echo formatCurrency($product['price']); ?></td>
                                                    <td>
                                                        <?php if ($product['stock_quantity'] <= $product['reorder_level']): ?>
                                                            <span class="text-danger fw-bold"><?php echo $product['stock_quantity']; ?></span>
                                                        <?php else: ?>
                                                            <?php echo $product['stock_quantity']; ?>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($product['status'] == 1): ?>
                                                            <span class="badge bg-success">Active</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-danger">Inactive</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group btn-group-sm">
                                                            <a href="products.php?edit=<?php echo $product['id']; ?>" class="btn btn-primary" title="Edit">
                                                                <i class="fa fa-edit"></i>
                                                            </a>
                                                            
                                                            <form method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to change the status of this product?');">
                                                                <input type="hidden" name="change_status" value="<?php echo $product['id']; ?>">
                                                                <input type="hidden" name="status" value="<?php echo $product['status'] == 1 ? 0 : 1; ?>">
                                                                <button type="submit" class="btn <?php echo $product['status'] == 1 ? 'btn-warning' : 'btn-success'; ?>" title="<?php echo $product['status'] == 1 ? 'Deactivate' : 'Activate'; ?>">
                                                                    <i class="fa <?php echo $product['status'] == 1 ? 'fa-times' : 'fa-check'; ?>"></i>
                                                                </button>
                                                            </form>
                                                            
                                                            <form method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this product? This action cannot be undone.');">
                                                                <input type="hidden" name="delete" value="<?php echo $product['id']; ?>">
                                                                <button type="submit" class="btn btn-danger" title="Delete">
                                                                    <i class="fa fa-trash"></i>
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="7" class="text-center">No products found.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Pagination -->
                            <?php if ($totalPages > 1): ?>
                            <nav aria-label="Page navigation">
                                <ul class="pagination justify-content-center">
                                    <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?>" aria-label="Previous">
                                            <span aria-hidden="true">&laquo;</span>
                                        </a>
                                    </li>
                                    
                                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                        <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?>" aria-label="Next">
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
        </main>
    </div>
</div>

<script>
// Auto-hide alerts after 5 seconds
setTimeout(function() {
    const alerts = document.querySelectorAll('.alert-dismissible');
    alerts.forEach(function(alert) {
        const alertInstance = new bootstrap.Alert(alert);
        alertInstance.close();
    });
}, 5000);

// Calculate profit margin
document.addEventListener('DOMContentLoaded', function() {
    const priceInput = document.getElementById('price');
    const costPriceInput = document.getElementById('cost_price');
    
    function calculateProfit() {
        const price = parseFloat(priceInput.value) || 0;
        const costPrice = parseFloat(costPriceInput.value) || 0;
        
        if (price > 0 && costPrice > 0) {
            const profit = price - costPrice;
            const margin = (profit / price) * 100;
            
            // Display profit information below the price inputs
            const profitInfo = document.getElementById('profit-info');
            if (!profitInfo) {
                const infoElement = document.createElement('div');
                infoElement.id = 'profit-info';
                infoElement.className = 'alert alert-info mt-2';
                infoElement.innerHTML = `
                    Profit: ${CURRENCY_SYMBOL}${profit.toFixed(2)}<br>
                    Margin: ${margin.toFixed(2)}%
                `;
                priceInput.parentNode.parentNode.parentNode.appendChild(infoElement);
            } else {
                profitInfo.innerHTML = `
                    Profit: ${CURRENCY_SYMBOL}${profit.toFixed(2)}<br>
                    Margin: ${margin.toFixed(2)}%
                `;
            }
        }
    }
    
    if (priceInput && costPriceInput) {
        priceInput.addEventListener('input', calculateProfit);
        costPriceInput.addEventListener('input', calculateProfit);
        
        // Calculate initial values if they exist
        if (priceInput.value && costPriceInput.value) {
            calculateProfit();
        }
    }
});
</script>

<?php
// Include footer
include_once '../includes/footer.php';
?>
