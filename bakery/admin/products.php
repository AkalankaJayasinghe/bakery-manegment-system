<?php
session_start();
require_once '../config/database.php';
require_once '../config/constants.php';
require_once '../includes/functions.php';

// Check if user is logged in and has admin privileges
if (!isLoggedIn() || !hasAdminPrivileges()) {
    header("Location: " . SITE_URL . "/auth/login.php");
    exit;
}

// Database connection
$conn = getDBConnection();

// Set page title
$pageTitle = "Manage Products";

// Initialize variables
$error = '';
$success = '';
$formAction = 'add'; // Default form action is add
$productId = 0;
$productName = '';
$productDescription = '';
$productCategory = '';
$productPrice = '';
$productCostPrice = '';
$productQuantity = '';
$currentImage = '';

// Get categories for dropdown
$categories = [];
$categoryResult = $conn->query("SELECT id, name FROM categories WHERE status = 1 ORDER BY name");
if ($categoryResult) {
    while ($row = $categoryResult->fetch_assoc()) {
        $categories[] = $row;
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        // Validate and sanitize inputs
        $productName = sanitizeInput($_POST['name']);
        $productDescription = sanitizeInput($_POST['description']);
        $productCategory = !empty($_POST['category']) ? (int)$_POST['category'] : NULL;
        $productPrice = (float)$_POST['price'];
        $productCostPrice = !empty($_POST['cost_price']) ? (float)$_POST['cost_price'] : 0;
        $productQuantity = (int)$_POST['quantity'];
        $productStatus = isset($_POST['status']) ? 1 : 0;
        
        // Validation
        if (empty($productName)) {
            $error = "Product name is required";
        } elseif ($productPrice <= 0) {
            $error = "Price must be greater than zero";
        } else {
            // Handle image upload
            $imageToUpload = false;
            $imagePath = '';
            
            if (!empty($_FILES['image']['name'])) {
                $imageToUpload = true;
                $targetDir = "../assets/images/products/";
                
                // Create directory if it doesn't exist
                if (!file_exists($targetDir)) {
                    mkdir($targetDir, 0755, true);
                }
                
                // Generate unique filename
                $imageFileType = strtolower(pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION));
                $fileName = 'product_' . time() . '_' . rand(1000, 9999) . '.' . $imageFileType;
                $targetFile = $targetDir . $fileName;
                $imagePath = "assets/images/products/" . $fileName;
                
                // Check file size (limit to 2MB)
                if ($_FILES["image"]["size"] > 2000000) {
                    $error = "Image file is too large. Maximum size is 2MB.";
                    $imageToUpload = false;
                }
                
                // Allow certain file formats
                if ($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" && $imageFileType != "gif") {
                    $error = "Only JPG, JPEG, PNG & GIF files are allowed.";
                    $imageToUpload = false;
                }
            }
            
            // If no errors, proceed with database operations
            if (empty($error)) {
                // Begin transaction
                $conn->begin_transaction();
                try {
                    if ($action === 'add') {
                        // Add new product
                        // IMPORTANT: Changed stock_quantity to quantity in the field list
                        $stmt = $conn->prepare("INSERT INTO products (name, description, category_id, price, cost_price, quantity, image, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->bind_param("ssiddssi", $productName, $productDescription, $productCategory, $productPrice, $productCostPrice, $productQuantity, $imagePath, $productStatus);
                        $stmt->execute();
                        
                        $productId = $conn->insert_id;
                        $success = "Product added successfully!";
                        
                        // Log activity
                        logActivity('add_product', "Added new product: $productName");
                        
                    } elseif ($action === 'edit') {
                        // Update existing product
                        $productId = (int)$_POST['product_id'];
                        
                        if ($imageToUpload) {
                            // Update with new image
                            // IMPORTANT: Changed stock_quantity to quantity in the field list
                            $stmt = $conn->prepare("UPDATE products SET name = ?, description = ?, category_id = ?, price = ?, cost_price = ?, quantity = ?, image = ?, status = ? WHERE id = ?");
                            $stmt->bind_param("ssiddsisi", $productName, $productDescription, $productCategory, $productPrice, $productCostPrice, $productQuantity, $imagePath, $productStatus, $productId);
                        } else {
                            // Update without changing image
                            // IMPORTANT: Changed stock_quantity to quantity in the field list
                            $stmt = $conn->prepare("UPDATE products SET name = ?, description = ?, category_id = ?, price = ?, cost_price = ?, quantity = ?, status = ? WHERE id = ?");
                            $stmt->bind_param("ssiddsii", $productName, $productDescription, $productCategory, $productPrice, $productCostPrice, $productQuantity, $productStatus, $productId);
                        }
                        
                        $stmt->execute();
                        $success = "Product updated successfully!";
                        
                        // Log activity
                        logActivity('edit_product', "Updated product: $productName (ID: $productId)");
                    }
                    
                    // If image upload is needed
                    if ($imageToUpload && empty($error)) {
                        if (move_uploaded_file($_FILES["image"]["tmp_name"], $targetFile)) {
                            // Image uploaded successfully
                            
                            // Delete old image if exists and updating
                            if ($action === 'edit' && !empty($_POST['current_image'])) {
                                $oldImage = "../" . $_POST['current_image'];
                                if (file_exists($oldImage) && is_file($oldImage)) {
                                    unlink($oldImage);
                                }
                            }
                        } else {
                            throw new Exception("Error uploading image. Please try again.");
                        }
                    }
                    
                    $conn->commit();
                    
                    // Reset form after successful add
                    if ($action === 'add') {
                        $productName = '';
                        $productDescription = '';
                        $productCategory = '';
                        $productPrice = '';
                        $productCostPrice = '';
                        $productQuantity = '';
                    }
                    
                } catch (Exception $e) {
                    $conn->rollback();
                    $error = "Error: " . $e->getMessage();
                }
            }
        }
    } elseif (isset($_POST['delete'])) {
        // Delete product
        $deleteId = (int)$_POST['delete'];
        
        // Get product details for logging and image deletion
        $stmt = $conn->prepare("SELECT name, image FROM products WHERE id = ?");
        $stmt->bind_param("i", $deleteId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $product = $result->fetch_assoc();
            
            // Begin transaction
            $conn->begin_transaction();
            
            try {
                // Delete product
                $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
                $stmt->bind_param("i", $deleteId);
                $stmt->execute();
                
                // Delete product image
                if (!empty($product['image'])) {
                    $imagePath = "../" . $product['image'];
                    if (file_exists($imagePath) && is_file($imagePath)) {
                        unlink($imagePath);
                    }
                }
                
                $conn->commit();
                $success = "Product deleted successfully!";
                
                // Log activity
                logActivity('delete_product', "Deleted product: {$product['name']} (ID: $deleteId)");
                
            } catch (Exception $e) {
                $conn->rollback();
                $error = "Error deleting product: " . $e->getMessage();
            }
        } else {
            $error = "Product not found.";
        }
    }
}

// Handle edit request via GET
if (isset($_GET['edit']) && !empty($_GET['edit'])) {
    $formAction = 'edit';
    $productId = (int)$_GET['edit'];
    
    // Fetch product details
    $stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->bind_param("i", $productId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $product = $result->fetch_assoc();
        $productName = $product['name'];
        $productDescription = $product['description'];
        $productCategory = $product['category_id'];
        $productPrice = $product['price'];
        $productCostPrice = $product['cost_price'];
        $productQuantity = $product['quantity']; // Changed from stock_quantity to quantity
        $currentImage = $product['image'];
        $productStatus = $product['status'];
    } else {
        $error = "Product not found.";
        $formAction = 'add';
    }
}

// Get all products for listing
$products = [];
$productSql = "SELECT p.*, c.name AS category_name 
              FROM products p 
              LEFT JOIN categories c ON p.category_id = c.id 
              ORDER BY p.id DESC";
$productResult = $conn->query($productSql);

if ($productResult) {
    while ($row = $productResult->fetch_assoc()) {
        $products[] = $row;
    }
}

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
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#productModal">
                    <i class="fas fa-plus"></i> Add New Product
                </button>
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
            
            <!-- Products Table -->
            <div class="card">
                <div class="card-header">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <h5 class="mb-0">Products List</h5>
                        </div>
                        <div class="col-md-6">
                            <div class="input-group">
                                <input type="text" id="searchInput" class="form-control" placeholder="Search products...">
                                <button class="btn btn-outline-secondary" type="button" id="searchBtn">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Image</th>
                                    <th>Name</th>
                                    <th>Category</th>
                                    <th>Price</th>
                                    <th>Cost</th>
                                    <th>Quantity</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($products) > 0): ?>
                                    <?php foreach ($products as $product): ?>
                                        <tr>
                                            <td><?php echo $product['id']; ?></td>
                                            <td>
                                                <?php if (!empty($product['image']) && file_exists('../' . $product['image'])): ?>
                                                    <img src="<?php echo '../' . $product['image']; ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" style="width: 50px; height: 50px; object-fit: cover;" class="img-thumbnail">
                                                <?php else: ?>
                                                    <img src="../assets/images/no-image.jpg" alt="No Image" style="width: 50px; height: 50px; object-fit: cover;" class="img-thumbnail">
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($product['name']); ?></td>
                                            <td><?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?></td>
                                            <td><?php echo CURRENCY_SYMBOL . number_format($product['price'], 2); ?></td>
                                            <td><?php echo CURRENCY_SYMBOL . number_format($product['cost_price'], 2); ?></td>
                                            <td>
                                                <?php if ($product['quantity'] <= 10): ?>
                                                    <span class="badge bg-danger"><?php echo $product['quantity']; ?></span>
                                                <?php elseif ($product['quantity'] <= 20): ?>
                                                    <span class="badge bg-warning"><?php echo $product['quantity']; ?></span>
                                                <?php else: ?>
                                                    <span class="badge bg-success"><?php echo $product['quantity']; ?></span>
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
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <a href="?edit=<?php echo $product['id']; ?>" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#productModal" data-id="<?php echo $product['id']; ?>">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-danger delete-product" data-id="<?php echo $product['id']; ?>" data-name="<?php echo htmlspecialchars($product['name']); ?>">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="9" class="text-center">No products found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Product Modal -->
<div class="modal fade" id="productModal" tabindex="-1" aria-labelledby="productModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="productModalLabel"><?php echo $formAction === 'add' ? 'Add New Product' : 'Edit Product'; ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="productForm" method="post" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="action" value="<?php echo $formAction; ?>">
                    <?php if ($formAction === 'edit'): ?>
                        <input type="hidden" name="product_id" value="<?php echo $productId; ?>">
                        <input type="hidden" name="current_image" value="<?php echo $currentImage; ?>">
                    <?php endif; ?>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="name" class="form-label">Product Name *</label>
                            <input type="text" class="form-control" id="name" name="name" value="<?php echo $productName; ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="category" class="form-label">Category</label>
                            <select class="form-select" id="category" name="category">
                                <option value="">-- Select Category --</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>" <?php echo $productCategory == $category['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="price" class="form-label">Price *</label>
                            <div class="input-group">
                                <span class="input-group-text"><?php echo CURRENCY_SYMBOL; ?></span>
                                <input type="number" class="form-control" id="price" name="price" step="0.01" min="0" value="<?php echo $productPrice; ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="cost_price" class="form-label">Cost Price</label>
                            <div class="input-group">
                                <span class="input-group-text"><?php echo CURRENCY_SYMBOL; ?></span>
                                <input type="number" class="form-control" id="cost_price" name="cost_price" step="0.01" min="0" value="<?php echo $productCostPrice; ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="quantity" class="form-label">Quantity</label>
                            <input type="number" class="form-control" id="quantity" name="quantity" min="0" value="<?php echo $productQuantity; ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="status" class="form-label">Status</label>
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" id="status" name="status" <?php echo $formAction === 'add' || $productStatus == 1 ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="status">Active</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="image" class="form-label">Product Image</label>
                        <input type="file" class="form-control" id="image" name="image" accept="image/*">
                        <small class="text-muted">Recommended size: 500x500 pixels. Max file size: 2MB</small>
                        
                        <?php if ($formAction === 'edit' && !empty($currentImage) && file_exists('../' . $currentImage)): ?>
                            <div class="mt-2">
                                <p>Current Image:</p>
                                <img src="<?php echo '../' . $currentImage; ?>" alt="Current Image" style="max-width: 100px; max-height: 100px;" class="img-thumbnail">
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="4"><?php echo $productDescription; ?></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <?php echo $formAction === 'add' ? 'Add Product' : 'Update Product'; ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Product Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Delete Product</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this product? This action cannot be undone.</p>
                <p><strong>Product: </strong><span id="delete-product-name"></span></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="post">
                    <input type="hidden" name="delete" id="delete-product-id">
                    <button type="submit" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Show product modal if in edit mode
    <?php if ($formAction === 'edit'): ?>
        const productModal = new bootstrap.Modal(document.getElementById('productModal'));
        productModal.show();
    <?php endif; ?>
    
    // Delete product confirmation
    const deleteButtons = document.querySelectorAll('.delete-product');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function() {
            const productId = this.getAttribute('data-id');
            const productName = this.getAttribute('data-name');
            
            document.getElementById('delete-product-id').value = productId;
            document.getElementById('delete-product-name').textContent = productName;
            
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
            deleteModal.show();
        });
    });
    
    // Search functionality
    document.getElementById('searchInput').addEventListener('keyup', function() {
        const searchTerm = this.value.toLowerCase();
        const tableRows = document.querySelectorAll('tbody tr');
        
        tableRows.forEach(row => {
            const name = row.querySelector('td:nth-child(3)').textContent.toLowerCase();
            const category = row.querySelector('td:nth-child(4)').textContent.toLowerCase();
            
            if (name.includes(searchTerm) || category.includes(searchTerm)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    });
    
    // Image preview
    document.getElementById('image').addEventListener('change', function() {
        if (this.files && this.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                // Create image preview element if it doesn't exist
                let previewContainer = document.querySelector('.image-preview-container');
                if (!previewContainer) {
                    previewContainer = document.createElement('div');
                    previewContainer.className = 'image-preview-container mt-2';
                    previewContainer.innerHTML = `
                        <p>New Image Preview:</p>
                        <img src="" class="img-thumbnail" style="max-width: 100px; max-height: 100px;">
                    `;
                    document.getElementById('image').parentNode.appendChild(previewContainer);
                }
                
                // Update preview image
                previewContainer.querySelector('img').src = e.target.result;
            };
            
            reader.readAsDataURL(this.files[0]);
        }
    });
});
</script>