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
$name = '';
$description = '';
$error = '';
$success = '';
$id = 0;

// Process AJAX requests
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    $response = ['success' => false, 'message' => ''];

    // Handle AJAX form submissions
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        // Get the action type
        $action = isset($_POST['action']) ? $_POST['action'] : '';

        // Sanitize inputs
        $name = isset($_POST['name']) ? sanitizeInput($_POST['name']) : '';
        $description = isset($_POST['description']) ? sanitizeInput($_POST['description']) : '';
        
        // Process based on action type
        switch ($action) {
            case 'add':
                if (empty($name)) {
                    $response['message'] = "Category name is required";
                } else {
                    // Check if category already exists
                    $stmt = $conn->prepare("SELECT id FROM categories WHERE name = ?");
                    $stmt->bind_param("s", $name);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($result->num_rows > 0) {
                        $response['message'] = "Category '$name' already exists";
                    } else {
                        // Insert new category
                        $stmt = $conn->prepare("INSERT INTO categories (name, description) VALUES (?, ?)");
                        $stmt->bind_param("ss", $name, $description);
                        
                        if ($stmt->execute()) {
                            $category_id = $conn->insert_id;
                            $response['success'] = true;
                            $response['message'] = "Category added successfully";
                            logActivity('add_category', "Added new category: $name");
                            
                            // Return new category data for dynamic update
                            $response['category'] = [
                                'id' => $category_id,
                                'name' => $name,
                                'description' => $description,
                                'status' => 1,
                                'product_count' => 0
                            ];
                        } else {
                            $response['message'] = "Error adding category: " . $conn->error;
                        }
                    }
                }
                break;
                
            case 'edit':
                $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
                
                if (empty($name)) {
                    $response['message'] = "Category name is required";
                } else {
                    // Check if category exists
                    $stmt = $conn->prepare("SELECT id FROM categories WHERE name = ? AND id != ?");
                    $stmt->bind_param("si", $name, $id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($result->num_rows > 0) {
                        $response['message'] = "Another category with name '$name' already exists";
                    } else {
                        // Update category
                        $stmt = $conn->prepare("UPDATE categories SET name = ?, description = ? WHERE id = ?");
                        $stmt->bind_param("ssi", $name, $description, $id);
                        
                        if ($stmt->execute()) {
                            $response['success'] = true;
                            $response['message'] = "Category updated successfully";
                            logActivity('edit_category', "Updated category: $name (ID: $id)");
                            
                            // Return updated category data
                            $response['category'] = [
                                'id' => $id,
                                'name' => $name,
                                'description' => $description
                            ];
                        } else {
                            $response['message'] = "Error updating category: " . $conn->error;
                        }
                    }
                }
                break;
                
            case 'delete':
                $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
                
                // Check if category is in use
                $stmt = $conn->prepare("SELECT COUNT(*) as count FROM products WHERE category_id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $result = $stmt->get_result();
                $row = $result->fetch_assoc();
                
                if ($row['count'] > 0) {
                    $response['message'] = "Cannot delete category because it is being used by products. Please reassign products first.";
                } else {
                    // Get category name for logging
                    $stmt = $conn->prepare("SELECT name FROM categories WHERE id = ?");
                    $stmt->bind_param("i", $id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $category = $result->fetch_assoc();
                    
                    // Delete category
                    $stmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
                    $stmt->bind_param("i", $id);
                    
                    if ($stmt->execute()) {
                        $response['success'] = true;
                        $response['message'] = "Category deleted successfully";
                        $response['id'] = $id;
                        logActivity('delete_category', "Deleted category: {$category['name']} (ID: $id)");
                    } else {
                        $response['message'] = "Error deleting category: " . $conn->error;
                    }
                }
                break;
                
            case 'change_status':
                $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
                $status = isset($_POST['status']) ? (int)$_POST['status'] : 0;
                
                $stmt = $conn->prepare("UPDATE categories SET status = ? WHERE id = ?");
                $stmt->bind_param("ii", $status, $id);
                
                if ($stmt->execute()) {
                    $statusText = ($status == 1) ? "activated" : "deactivated";
                    $response['success'] = true;
                    $response['message'] = "Category $statusText successfully";
                    $response['id'] = $id;
                    $response['status'] = $status;
                    
                    // Get category name for logging
                    $stmt = $conn->prepare("SELECT name FROM categories WHERE id = ?");
                    $stmt->bind_param("i", $id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $category = $result->fetch_assoc();
                    
                    logActivity('change_category_status', "Changed category status: {$category['name']} (ID: $id) - $statusText");
                } else {
                    $response['message'] = "Error changing category status: " . $conn->error;
                }
                break;
                
            case 'get_category':
                $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
                
                $stmt = $conn->prepare("SELECT * FROM categories WHERE id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows == 1) {
                    $category = $result->fetch_assoc();
                    $response['success'] = true;
                    $response['category'] = $category;
                } else {
                    $response['message'] = "Category not found";
                }
                break;
                
            case 'search':
                $term = isset($_POST['term']) ? sanitizeInput($_POST['term']) : '';
                $searchTerm = "%$term%";
                
                $stmt = $conn->prepare("SELECT * FROM categories WHERE name LIKE ? OR description LIKE ? ORDER BY name ASC");
                $stmt->bind_param("ss", $searchTerm, $searchTerm);
                $stmt->execute();
                $result = $stmt->get_result();
                
                $categories = [];
                while ($row = $result->fetch_assoc()) {
                    // Get product count for each category
                    $countStmt = $conn->prepare("SELECT COUNT(*) as count FROM products WHERE category_id = ?");
                    $countStmt->bind_param("i", $row['id']);
                    $countStmt->execute();
                    $countResult = $countStmt->get_result();
                    $productCount = $countResult->fetch_assoc()['count'];
                    
                    $row['product_count'] = $productCount;
                    $categories[] = $row;
                }
                
                $response['success'] = true;
                $response['categories'] = $categories;
                break;
                
            case 'reorder':
                $order = isset($_POST['order']) ? $_POST['order'] : [];
                
                if (!empty($order)) {
                    $success = true;
                    
                    foreach ($order as $position => $id) {
                        $stmt = $conn->prepare("UPDATE categories SET sort_order = ? WHERE id = ?");
                        $stmt->bind_param("ii", $position, $id);
                        
                        if (!$stmt->execute()) {
                            $success = false;
                            break;
                        }
                    }
                    
                    if ($success) {
                        $response['success'] = true;
                        $response['message'] = "Categories reordered successfully";
                        logActivity('reorder_categories', "Reordered categories");
                    } else {
                        $response['message'] = "Error reordering categories";
                    }
                } else {
                    $response['message'] = "No order data provided";
                }
                break;
        }
        
        // Return JSON response
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
}

// Handle standard (non-AJAX) form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    if (isset($_POST['action'])) {
        // Sanitize inputs
        $name = sanitizeInput($_POST['name']);
        $description = sanitizeInput($_POST['description']);
        
        // Validate inputs
        if (empty($name)) {
            $error = "Category name is required";
        } else {
            // Determine action
            switch ($_POST['action']) {
                case 'add':
                    // Check if category already exists
                    $stmt = $conn->prepare("SELECT id FROM categories WHERE name = ?");
                    $stmt->bind_param("s", $name);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($result->num_rows > 0) {
                        $error = "Category '$name' already exists";
                    } else {
                        // Insert new category
                        $stmt = $conn->prepare("INSERT INTO categories (name, description) VALUES (?, ?)");
                        $stmt->bind_param("ss", $name, $description);
                        
                        if ($stmt->execute()) {
                            $success = "Category added successfully";
                            logActivity('add_category', "Added new category: $name");
                            // Clear form fields
                            $name = '';
                            $description = '';
                        } else {
                            $error = "Error adding category: " . $conn->error;
                        }
                    }
                    break;
                    
                case 'edit':
                    $id = $_POST['id'];
                    
                    // Check if category exists
                    $stmt = $conn->prepare("SELECT id FROM categories WHERE name = ? AND id != ?");
                    $stmt->bind_param("si", $name, $id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($result->num_rows > 0) {
                        $error = "Another category with name '$name' already exists";
                    } else {
                        // Update category
                        $stmt = $conn->prepare("UPDATE categories SET name = ?, description = ? WHERE id = ?");
                        $stmt->bind_param("ssi", $name, $description, $id);
                        
                        if ($stmt->execute()) {
                            $success = "Category updated successfully";
                            logActivity('edit_category', "Updated category: $name (ID: $id)");
                            $id = 0; // Reset form to Add mode
                            $name = '';
                            $description = '';
                        } else {
                            $error = "Error updating category: " . $conn->error;
                        }
                    }
                    break;
            }
        }
    }
    
    // Handle delete action
    if (isset($_POST['delete'])) {
        $id = $_POST['delete'];
        
        // Check if category is in use
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM products WHERE category_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        if ($row['count'] > 0) {
            $error = "Cannot delete category because it is being used by products. Please reassign products first.";
        } else {
            // Get category name for logging
            $stmt = $conn->prepare("SELECT name FROM categories WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $category = $result->fetch_assoc();
            
            // Delete category
            $stmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute()) {
                $success = "Category deleted successfully";
                logActivity('delete_category', "Deleted category: {$category['name']} (ID: $id)");
            } else {
                $error = "Error deleting category: " . $conn->error;
            }
        }
    }
    
    // Handle status change
    if (isset($_POST['change_status'])) {
        $id = $_POST['change_status'];
        $status = $_POST['status'];
        
        $stmt = $conn->prepare("UPDATE categories SET status = ? WHERE id = ?");
        $stmt->bind_param("ii", $status, $id);
        
        if ($stmt->execute()) {
            $statusText = ($status == 1) ? "activated" : "deactivated";
            $success = "Category $statusText successfully";
            
            // Get category name for logging
            $stmt = $conn->prepare("SELECT name FROM categories WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $category = $result->fetch_assoc();
            
            logActivity('change_category_status', "Changed category status: {$category['name']} (ID: $id) - $statusText");
        } else {
            $error = "Error changing category status: " . $conn->error;
        }
    }
}

// Handle edit request via GET
if (isset($_GET['edit'])) {
    $id = $_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $category = $result->fetch_assoc();
        $name = $category['name'];
        $description = $category['description'];
    } else {
        $error = "Category not found";
    }
}

// Set page title
$pageTitle = "Manage Categories";

// Get all categories
$categories = [];
// Check if sort_order column exists
$columns_result = $conn->query("SHOW COLUMNS FROM categories LIKE 'sort_order'");
$sort_order_exists = $columns_result && $columns_result->num_rows > 0;

if ($sort_order_exists) {
    $result = $conn->query("SELECT * FROM categories ORDER BY sort_order ASC, name ASC");
} else {
    // Fallback if the column doesn't exist to prevent fatal error
    $result = $conn->query("SELECT * FROM categories ORDER BY name ASC");
}
while ($row = $result->fetch_assoc()) {
    $categories[] = $row;
}

// Include header
include_once '../includes/header.php';
?>

<!-- Custom CSS -->
<style>
:root {
    --primary-color: #4e73df;
    --primary-dark: #2e59d9;
    --success-color: #1cc88a;
    --danger-color: #e74a3b;
    --warning-color: #f6c23e;
    --info-color: #36b9cc;
    --card-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
    --transition-speed: 0.3s;
}

.dark-mode {
    --primary-color: #375abe;
    --primary-dark: #2e59d9;
    --card-shadow: 0 0.15rem 1.75rem 0 rgba(0, 0, 0, 0.3);
    color-scheme: dark;
}

.card {
    border: none;
    border-radius: 0.5rem;
    box-shadow: var(--card-shadow);
    transition: transform var(--transition-speed), box-shadow var(--transition-speed);
    margin-bottom: 1.5rem;
}

.card:hover {
    transform: translateY(-5px);
    box-shadow: 0 0.5rem 2rem 0 rgba(58, 59, 69, 0.2);
}

.card-header {
    background-color: rgba(0, 0, 0, 0.03);
    border-bottom: 1px solid rgba(0, 0, 0, 0.125);
    padding: 1rem 1.25rem;
}

.form-control:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 0.25rem rgba(78, 115, 223, 0.25);
}

.btn-primary {
    background-color: var(--primary-color);
    border-color: var(--primary-color);
}

.btn-primary:hover {
    background-color: var(--primary-dark);
    border-color: var(--primary-dark);
}

/* Table styles */
.table {
    margin-bottom: 0;
}

.table thead th {
    border-top: none;
    border-bottom: 1px solid rgba(0, 0, 0, 0.125);
    font-weight: 600;
}

.category-row {
    cursor: pointer;
    transition: background-color var(--transition-speed);
}

.category-row:hover {
    background-color: rgba(0, 0, 0, 0.05);
}

.category-row.editing {
    background-color: rgba(78, 115, 223, 0.1);
}

/* Search bar */
.search-container {
    position: relative;
    margin-bottom: 1rem;
}

.search-container .form-control {
    padding-left: 2.5rem;
    border-radius: 2rem;
}

.search-icon {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: #aaa;
}

/* Badges */
.badge {
    font-weight: 600;
    padding: 0.35em 0.65em;
}

/* Custom toast notifications */
.toast-container {
    position: fixed;
    top: 1rem;
    right: 1rem;
    z-index: 1050;
}

.toast {
    background-color: #fff;
    border-radius: 0.25rem;
    box-shadow: 0 0.25rem 0.75rem rgba(0, 0, 0, 0.1);
    opacity: 0;
    transition: opacity 0.3s;
}

.toast.showing {
    opacity: 1;
}

/* Loading spinner */
.loading-spinner {
    display: inline-block;
    width: 1rem;
    height: 1rem;
    border-radius: 50%;
    border: 2px solid rgba(0, 0, 0, 0.1);
    border-top-color: var(--primary-color);
    animation: spinner 0.8s linear infinite;
}

@keyframes spinner {
    to { transform: rotate(360deg); }
}

/* Drag and drop styles */
.sortable-ghost {
    background-color: rgba(78, 115, 223, 0.2) !important;
}

.sortable-handle {
    cursor: grab;
    color: #aaa;
}

.sortable-handle:active {
    cursor: grabbing;
}

/* Custom animations */
.fade-in {
    animation: fadeIn 0.5s ease-in-out;
}

.bounce {
    animation: bounce 0.5s ease-in-out;
}

@keyframes fadeIn {
    0% { opacity: 0; transform: translateY(-10px); }
    100% { opacity: 1; transform: translateY(0); }
}

@keyframes bounce {
    0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
    40% { transform: translateY(-10px); }
    60% { transform: translateY(-5px); }
}

/* Loading overlay */
.loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 9999;
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

/* Custom confirmation dialog */
.custom-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 1050;
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.3s, visibility 0.3s;
}

.custom-modal.show {
    opacity: 1;
    visibility: visible;
}

.custom-modal-content {
    background-color: #fff;
    border-radius: 0.5rem;
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
    width: 400px;
    max-width: 90%;
    transform: translateY(-20px);
    transition: transform 0.3s;
}

.custom-modal.show .custom-modal-content {
    transform: translateY(0);
}

.custom-modal-header {
    padding: 1rem;
    border-bottom: 1px solid rgba(0, 0, 0, 0.125);
}

.custom-modal-body {
    padding: 1rem;
}

.custom-modal-footer {
    padding: 1rem;
    border-top: 1px solid rgba(0, 0, 0, 0.125);
    display: flex;
    justify-content: flex-end;
    gap: 0.5rem;
}

/* Theme toggle button */
.theme-toggle {
    position: fixed;
    bottom: 1.5rem;
    right: 1.5rem;
    width: 3rem;
    height: 3rem;
    border-radius: 50%;
    background-color: var(--primary-color);
    color: white;
    display: flex;
    justify-content: center;
    align-items: center;
    cursor: pointer;
    box-shadow: 0 0.25rem 0.5rem rgba(0, 0, 0, 0.15);
    transition: transform var(--transition-speed), background-color var(--transition-speed);
    z-index: 1000;
}

.theme-toggle:hover {
    transform: scale(1.1);
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .row {
        flex-direction: column-reverse;
    }
    .table-responsive {
        font-size: 0.875rem;
    }
}
</style>

<!-- Loading Overlay -->
<div class="loading-overlay" id="loadingOverlay">
    <div class="spinner"></div>
</div>

<!-- Toast Container -->
<div class="toast-container" id="toastContainer"></div>

<!-- Custom Confirmation Dialog -->
<div class="custom-modal" id="confirmationModal">
    <div class="custom-modal-content">
        <div class="custom-modal-header">
            <h5 class="mb-0" id="confirmationModalTitle">Confirmation</h5>
        </div>
        <div class="custom-modal-body">
            <p id="confirmationModalMessage">Are you sure you want to perform this action?</p>
        </div>
        <div class="custom-modal-footer">
            <button type="button" class="btn btn-secondary" id="confirmationModalCancel">Cancel</button>
            <button type="button" class="btn btn-danger" id="confirmationModalConfirm">Confirm</button>
        </div>
    </div>
</div>

<div class="container-fluid">
    <div class="row">
        <!-- Include sidebar -->
        <?php include_once '../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Manage Categories</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="form-check form-switch me-3">
                        <input class="form-check-input" type="checkbox" id="reorderModeSwitch">
                        <label class="form-check-label" for="reorderModeSwitch">Reorder Mode</label>
                    </div>
                    <div class="btn-group">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="refreshBtn">
                            <i class="fa fa-sync-alt"></i> Refresh
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="exportBtn">
                            <i class="fa fa-file-export"></i> Export
                        </button>
                    </div>
                </div>
            </div>
            
            <div id="alertContainer">
                <?php if (!empty($error)): ?>
                <div class="alert alert-danger fade-in" role="alert">
                    <i class="fa fa-exclamation-circle me-2"></i> <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($success)): ?>
                <div class="alert alert-success fade-in" role="alert">
                    <i class="fa fa-check-circle me-2"></i> <?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="row">
                <!-- Categories List -->
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Categories List</h5>
                            <span class="badge bg-primary" id="categoryCount"><?php echo count($categories); ?> Categories</span>
                        </div>
                        <div class="card-body">
                            <div class="search-container mb-3">
                                <i class="fa fa-search search-icon"></i>
                                <input type="text" id="searchCategories" class="form-control" placeholder="Search categories...">
                            </div>
                            
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th width="5%"></th>
                                            <th width="25%">Name</th>
                                            <th width="35%">Description</th>
                                            <th width="10%">Products</th>
                                            <th width="10%">Status</th>
                                            <th width="15%">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="categoriesTableBody">
                                        <?php if (count($categories) > 0): ?>
                                            <?php foreach ($categories as $index => $category): ?>
                                                <?php
                                                // Count products in this category
                                                $stmt = $conn->prepare("SELECT COUNT(*) as count FROM products WHERE category_id = ?");
                                                $stmt->bind_param("i", $category['id']);
                                                $stmt->execute();
                                                $result = $stmt->get_result();
                                                $productCount = $result->fetch_assoc()['count'];
                                                ?>
                                                <tr class="category-row" data-id="<?php echo $category['id']; ?>">
                                                    <td class="text-center sortable-handle" title="Drag to reorder"><i class="fa fa-grip-vertical"></i></td>
                                                    <td><?php echo htmlspecialchars($category['name']); ?></td>
                                                    <td><?php echo htmlspecialchars($category['description']); ?></td>
                                                    <td class="text-center">
                                                        <span class="badge bg-info"><?php echo $productCount; ?></span>
                                                    </td>
                                                    <td class="text-center">
                                                        <?php if ($category['status'] == 1): ?>
                                                            <span class="badge bg-success">Active</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-danger">Inactive</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group btn-group-sm">
                                                            <button type="button" class="btn btn-primary edit-btn" data-id="<?php echo $category['id']; ?>" title="Edit">
                                                                <i class="fa fa-edit"></i>
                                                            </button>
                                                            
                                                            <button type="button" class="btn <?php echo $category['status'] == 1 ? 'btn-warning' : 'btn-success'; ?> status-btn" 
                                                                data-id="<?php echo $category['id']; ?>" 
                                                                data-status="<?php echo $category['status'] == 1 ? 0 : 1; ?>" 
                                                                title="<?php echo $category['status'] == 1 ? 'Deactivate' : 'Activate'; ?>">
                                                                <i class="fa <?php echo $category['status'] == 1 ? 'fa-times' : 'fa-check'; ?>"></i>
                                                            </button>
                                                            
                                                            <button type="button" class="btn btn-danger delete-btn" 
                                                                data-id="<?php echo $category['id']; ?>" 
                                                                title="Delete" 
                                                                <?php echo $productCount > 0 ? 'disabled' : ''; ?>>
                                                                <i class="fa fa-trash"></i>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr id="noCategoriesRow">
                                                <td colspan="6" class="text-center py-4">No categories found.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Category Form -->
                <div class="col-md-4">
                    <div class="card" id="categoryFormCard">
                        <div class="card-header">
                            <h5 class="mb-0" id="formTitle"><?php echo ($id > 0) ? 'Edit Category' : 'Add New Category'; ?></h5>
                        </div>
                        <div class="card-body">
                            <form id="categoryForm" method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                                <input type="hidden" id="formAction" name="action" value="<?php echo ($id > 0) ? 'edit' : 'add'; ?>">
                                <input type="hidden" id="categoryId" name="id" value="<?php echo $id; ?>">
                                
                                <div class="mb-3">
                                    <label for="categoryName" class="form-label">Category Name</label>
                                    <input type="text" class="form-control" id="categoryName" name="name" value="<?php echo htmlspecialchars($name); ?>" required>
                                    <div class="invalid-feedback" id="nameError"></div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="categoryDescription" class="form-label">Description</label>
                                    <textarea class="form-control" id="categoryDescription" name="description" rows="4"><?php echo htmlspecialchars($description); ?></textarea>
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary" id="submitBtn">
                                        <span id="submitBtnText"><?php echo ($id > 0) ? 'Update Category' : 'Add Category'; ?></span>
                                        <span class="loading-spinner ms-2 d-none" id="submitSpinner"></span>
                                    </button>
                                    
                                    <?php if ($id > 0): ?>
                                    <button type="button" class="btn btn-secondary" id="cancelEditBtn">Cancel</button>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Quick Help Card -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <h5 class="mb-0">Quick Help</h5>
                        </div>
                        <div class="card-body">
                            <ul class="list-unstyled">
                                <li><i class="fa fa-info-circle me-2 text-info"></i> <strong>Add</strong>: Fill in the form and click "Add Category"</li>
                                <li class="mt-2"><i class="fa fa-info-circle me-2 text-info"></i> <strong>Edit</strong>: Click the pencil icon next to a category</li>
                                <li class="mt-2"><i class="fa fa-info-circle me-2 text-info"></i> <strong>Reorder</strong>: Toggle reorder mode and drag categories to reposition</li>
                                <li class="mt-2"><i class="fa fa-keyboard me-2 text-info"></i> <strong>Keyboard shortcuts</strong>:
                                    <ul class="mt-1">
                                        <li>Alt + N: New category</li>
                                        <li>Alt + S: Save form</li>
                                        <li>Esc: Cancel edit</li>
                                    </ul>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Theme Toggle Button -->
<div class="theme-toggle" id="themeToggle" title="Toggle Light/Dark Mode">
    <i class="fa fa-moon"></i>
</div>

<!-- Include SortableJS for drag and drop functionality -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.14.0/Sortable.min.js"></script>
<!-- Include jQuery for AJAX requests -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
// DOM elements
const categoryForm = document.getElementById('categoryForm');
const formTitle = document.getElementById('formTitle');
const formAction = document.getElementById('formAction');
const categoryId = document.getElementById('categoryId');
const categoryName = document.getElementById('categoryName');
const categoryDescription = document.getElementById('categoryDescription');
const submitBtn = document.getElementById('submitBtn');
const submitBtnText = document.getElementById('submitBtnText');
const submitSpinner = document.getElementById('submitSpinner');
const categoriesTableBody = document.getElementById('categoriesTableBody');
const searchCategories = document.getElementById('searchCategories');
const loadingOverlay = document.getElementById('loadingOverlay');
const categoryCount = document.getElementById('categoryCount');
const alertContainer = document.getElementById('alertContainer');
const reorderModeSwitch = document.getElementById('reorderModeSwitch');
const themeToggle = document.getElementById('themeToggle');
const confirmationModal = document.getElementById('confirmationModal');
const confirmationModalTitle = document.getElementById('confirmationModalTitle');
const confirmationModalMessage = document.getElementById('confirmationModalMessage');
const confirmationModalConfirm = document.getElementById('confirmationModalConfirm');
const confirmationModalCancel = document.getElementById('confirmationModalCancel');
const refreshBtn = document.getElementById('refreshBtn');
const exportBtn = document.getElementById('exportBtn');

let isProcessing = false;
let currentAction = null;
let currentActionData = null;
let sortableTable = null;

// Initialize when DOM content is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Initialize form validation
    initFormValidation();
    
    // Initialize event listeners
    initEventListeners();
    
    // Initialize sortable table
    initSortable();
    
    // Initialize theme from localStorage
    initTheme();
    
    // Hide loading overlay when page is fully loaded
    window.addEventListener('load', function() {
        setTimeout(() => {
            hideLoadingOverlay();
        }, 200);
    });
    
    // Initialize keyboard shortcuts
    initKeyboardShortcuts();
});

// Initialize form validation
function initFormValidation() {
    categoryForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        if (categoryName.value.trim() === '') {
            categoryName.classList.add('is-invalid');
            document.getElementById('nameError').textContent = 'Category name is required';
            return;
        }
        
        submitForm();
    });
    
    // Real-time validation
    categoryName.addEventListener('input', function() {
        if (this.value.trim() !== '') {
            this.classList.remove('is-invalid');
        }
    });
}

// Initialize event listeners
function initEventListeners() {
    // Category row actions
    document.addEventListener('click', function(e) {
        // Edit button
        if (e.target.closest('.edit-btn')) {
            const btn = e.target.closest('.edit-btn');
            const id = btn.dataset.id;
            editCategory(id);
        }
        
        // Status button
        if (e.target.closest('.status-btn')) {
            const btn = e.target.closest('.status-btn');
            const id = btn.dataset.id;
            const status = btn.dataset.status;
            changeStatus(id, status);
        }
        
        // Delete button
        if (e.target.closest('.delete-btn')) {
            const btn = e.target.closest('.delete-btn');
            const id = btn.dataset.id;
            showConfirmationModal('Delete Category', 'Are you sure you want to delete this category? This action cannot be undone.', function() {
                deleteCategory(id);
            });
        }
    });
    
    // Cancel edit button
    if (document.getElementById('cancelEditBtn')) {
        document.getElementById('cancelEditBtn').addEventListener('click', resetForm);
    }
    
    // Search functionality
    searchCategories.addEventListener('input', function() {
        const searchTerm = this.value.trim();
        
        if (searchTerm.length >= 2) {
            // Use AJAX to search
            showLoadingOverlay();
            $.ajax({
                url: window.location.href,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'search',
                    term: searchTerm
                },
                success: function(response) {
                    if (response.success) {
                        updateCategoriesTable(response.categories);
                    }
                    hideLoadingOverlay();
                },
                error: function() {
                    showToast('Error searching categories', 'error');
                    hideLoadingOverlay();
                }
            });
        } else if (searchTerm === '') {
            // Reset to show all categories
            refreshData();
        }
    });
    
    // Reorder mode switch
    reorderModeSwitch.addEventListener('change', function() {
        const isReorderMode = this.checked;
        toggleReorderMode(isReorderMode);
    });
    
    // Theme toggle
    themeToggle.addEventListener('click', toggleTheme);
    
    // Confirmation modal buttons
    confirmationModalCancel.addEventListener('click', function() {
        hideConfirmationModal();
    });
    
    confirmationModalConfirm.addEventListener('click', function() {
        hideConfirmationModal();
        if (currentAction && typeof currentAction === 'function') {
            currentAction(currentActionData);
        }
    });
    
    // Refresh button
    refreshBtn.addEventListener('click', function() {
        refreshData();
    });
    
    // Export button
    exportBtn.addEventListener('click', function() {
        exportCategories();
    });
}

// Initialize sortable table
function initSortable() {
    sortableTable = new Sortable(categoriesTableBody, {
        handle: '.sortable-handle',
        animation: 150,
        ghostClass: 'sortable-ghost',
        disabled: true,
        onEnd: function() {
            if (reorderModeSwitch.checked) {
                saveOrder();
            }
        }
    });
}

// Toggle reorder mode
function toggleReorderMode(enabled) {
    sortableTable.option('disabled', !enabled);
    
    const handles = document.querySelectorAll('.sortable-handle');
    handles.forEach(handle => {
        if (enabled) {
            handle.style.opacity = '1';
            handle.style.cursor = 'grab';
        } else {
            handle.style.opacity = '0.4';
            handle.style.cursor = 'default';
        }
    });
    
    if (enabled) {
        showToast('Drag categories to reorder them', 'info');
    }
}

// Save order after drag and drop
function saveOrder() {
    const categoryRows = document.querySelectorAll('.category-row');
    const order = [];
    
    categoryRows.forEach(row => {
        order.push(row.dataset.id);
    });
    
    showLoadingOverlay();
    
    $.ajax({
        url: window.location.href,
        type: 'POST',
        dataType: 'json',
        data: {
            action: 'reorder',
            order: order
        },
        success: function(response) {
            hideLoadingOverlay();
            if (response.success) {
                showToast('Categories reordered successfully', 'success');
            } else {
                showToast(response.message || 'Error reordering categories', 'error');
            }
        },
        error: function() {
            hideLoadingOverlay();
            showToast('Error reordering categories', 'error');
        }
    });
}

// Submit form
function submitForm() {
    if (isProcessing) return;
    
    isProcessing = true;
    submitBtn.disabled = true;
    submitSpinner.classList.remove('d-none');
    
    const action = formAction.value;
    const id = categoryId.value;
    const name = categoryName.value;
    const description = categoryDescription.value;
    
    // Use AJAX to submit form
    $.ajax({
        url: window.location.href,
        type: 'POST',
        dataType: 'json',
        data: {
            action: action,
            id: id,
            name: name,
            description: description
        },
        success: function(response) {
            isProcessing = false;
            submitBtn.disabled = false;
            submitSpinner.classList.add('d-none');
            
            if (response.success) {
                showToast(response.message, 'success');
                
                if (action === 'add') {
                    // Add new category to table
                    addCategoryToTable(response.category);
                    
                    // Clear form
                    resetForm();
                } else if (action === 'edit') {
                    // Update category in table
                    updateCategoryInTable(response.category);
                    
                    // Reset form to add mode
                    resetForm();
                }
            } else {
                showToast(response.message, 'error');
            }
        },
        error: function() {
            isProcessing = false;
            submitBtn.disabled = false;
            submitSpinner.classList.add('d-none');
            showToast('An error occurred while processing your request', 'error');
        }
    });
}

// Edit category
function editCategory(id) {
    showLoadingOverlay();
    
    $.ajax({
        url: window.location.href,
        type: 'POST',
        dataType: 'json',
        data: {
            action: 'get_category',
            id: id
        },
        success: function(response) {
            hideLoadingOverlay();
            
            if (response.success) {
                const category = response.category;
                
                // Set form values
                categoryId.value = category.id;
                categoryName.value = category.name;
                categoryDescription.value = category.description;
                
                // Update form state
                formAction.value = 'edit';
                formTitle.textContent = 'Edit Category';
                submitBtnText.textContent = 'Update Category';
                
                // Add cancel button if not exists
                if (!document.getElementById('cancelEditBtn')) {
                    const cancelBtn = document.createElement('button');
                    cancelBtn.type = 'button';
                    cancelBtn.className = 'btn btn-secondary';
                    cancelBtn.id = 'cancelEditBtn';
                    cancelBtn.textContent = 'Cancel';
                    cancelBtn.addEventListener('click', resetForm);
                    
                    document.querySelector('#categoryForm .d-grid').appendChild(cancelBtn);
                }
                
                // Highlight row being edited
                document.querySelectorAll('.category-row').forEach(row => {
                    row.classList.remove('editing');
                });
                
                const editingRow = document.querySelector(`.category-row[data-id="${id}"]`);
                if (editingRow) {
                    editingRow.classList.add('editing');
                    // Scroll to the row
                    editingRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
                
                // Animate form card
                const formCard = document.getElementById('categoryFormCard');
                formCard.classList.add('bounce');
                setTimeout(() => {
                    formCard.classList.remove('bounce');
                }, 500);
                
                // Focus on name field
                categoryName.focus();
            } else {
                showToast(response.message || 'Error loading category', 'error');
            }
        },
        error: function() {
            hideLoadingOverlay();
            showToast('Error loading category', 'error');
        }
    });
}

// Change category status
function changeStatus(id, status) {
    showLoadingOverlay();
    
    $.ajax({
        url: window.location.href,
        type: 'POST',
        dataType: 'json',
        data: {
            action: 'change_status',
            id: id,
            status: status
        },
        success: function(response) {
            hideLoadingOverlay();
            
            if (response.success) {
                showToast(response.message, 'success');
                
                // Update UI
                const row = document.querySelector(`.category-row[data-id="${id}"]`);
                if (row) {
                    const statusCell = row.querySelector('td:nth-child(5)');
                    const statusBtn = row.querySelector('.status-btn');
                    
                    if (statusCell) {
                        statusCell.innerHTML = response.status == 1 ? 
                            '<span class="badge bg-success">Active</span>' : 
                            '<span class="badge bg-danger">Inactive</span>';
                    }
                    
                    if (statusBtn) {
                        statusBtn.classList.remove('btn-success', 'btn-warning');
                        statusBtn.classList.add(response.status == 1 ? 'btn-warning' : 'btn-success');
                        statusBtn.dataset.status = response.status == 1 ? 0 : 1;
                        statusBtn.title = response.status == 1 ? 'Deactivate' : 'Activate';
                        
                        const icon = statusBtn.querySelector('i');
                        if (icon) {
                            icon.className = response.status == 1 ? 'fa fa-times' : 'fa fa-check';
                        }
                    }
                }
            } else {
                showToast(response.message || 'Error changing status', 'error');
            }
        },
        error: function() {
            hideLoadingOverlay();
            showToast('Error changing status', 'error');
        }
    });
}

// Delete category
function deleteCategory(id) {
    showLoadingOverlay();
    
    $.ajax({
        url: window.location.href,
        type: 'POST',
        dataType: 'json',
        data: {
            action: 'delete',
            id: id
        },
        success: function(response) {
            hideLoadingOverlay();
            
            if (response.success) {
                showToast(response.message, 'success');
                
                // Remove row from table
                const row = document.querySelector(`.category-row[data-id="${id}"]`);
                if (row) {
                    row.style.backgroundColor = 'rgba(231, 74, 59, 0.2)';
                    row.style.transition = 'all 0.3s';
                    
                    setTimeout(() => {
                        row.style.opacity = '0';
                        row.style.transform = 'translateX(20px)';
                        
                        setTimeout(() => {
                            row.remove();
                            updateCategoryCount();
                            
                            // Show "no categories" message if table is empty
                            if (document.querySelectorAll('.category-row').length === 0) {
                                const noDataRow = document.createElement('tr');
                                noDataRow.id = 'noCategoriesRow';
                                noDataRow.innerHTML = '<td colspan="6" class="text-center py-4">No categories found.</td>';
                                categoriesTableBody.appendChild(noDataRow);
                            }
                        }, 300);
                    }, 100);
                }
            } else {
                showToast(response.message || 'Error deleting category', 'error');
            }
        },
        error: function() {
            hideLoadingOverlay();
            showToast('Error deleting category', 'error');
        }
    });
}

// Reset form to add mode
function resetForm() {
    // Reset form values
    categoryForm.reset();
    categoryId.value = '';
    
    // Update form state
    formAction.value = 'add';
    formTitle.textContent = 'Add New Category';
    submitBtnText.textContent = 'Add Category';
    
    // Remove validation classes
    categoryName.classList.remove('is-invalid');
    
    // Remove cancel button
    const cancelBtn = document.getElementById('cancelEditBtn');
    if (cancelBtn) {
        cancelBtn.remove();
    }
    
    // Remove row highlight
    document.querySelectorAll('.category-row').forEach(row => {
        row.classList.remove('editing');
    });
    
    // Animate form card
    const formCard = document.getElementById('categoryFormCard');
    formCard.classList.add('bounce');
    setTimeout(() => {
        formCard.classList.remove('bounce');
    }, 500);
    
    // Focus on name field
    categoryName.focus();
}

// Add new category to table
function addCategoryToTable(category) {
    // Remove "no categories" row if it exists
    const noDataRow = document.getElementById('noCategoriesRow');
    if (noDataRow) {
        noDataRow.remove();
    }
    
    // Create new row
    const newRow = document.createElement('tr');
    newRow.className = 'category-row fade-in';
    newRow.dataset.id = category.id;
    
    // Set row HTML
    newRow.innerHTML = `
        <td class="text-center sortable-handle" title="Drag to reorder"><i class="fa fa-grip-vertical"></i></td>
        <td>${escapeHtml(category.name)}</td>
        <td>${escapeHtml(category.description)}</td>
        <td class="text-center">
            <span class="badge bg-info">${category.product_count}</span>
        </td>
        <td class="text-center">
            <span class="badge bg-success">Active</span>
        </td>
        <td>
            <div class="btn-group btn-group-sm">
                <button type="button" class="btn btn-primary edit-btn" data-id="${category.id}" title="Edit">
                    <i class="fa fa-edit"></i>
                </button>
                
                <button type="button" class="btn btn-warning status-btn" 
                    data-id="${category.id}" 
                    data-status="0" 
                    title="Deactivate">
                    <i class="fa fa-times"></i>
                </button>
                
                <button type="button" class="btn btn-danger delete-btn" 
                    data-id="${category.id}" 
                    title="Delete">
                    <i class="fa fa-trash"></i>
                </button>
            </div>
        </td>
    `;
    
    // Add to table
    categoriesTableBody.prepend(newRow);
    
    // Update sortable
    sortableTable.option('disabled', !reorderModeSwitch.checked);
    
    // Update category count
    updateCategoryCount();
}

// Update category in table
function updateCategoryInTable(category) {
    const row = document.querySelector(`.category-row[data-id="${category.id}"]`);
    
    if (row) {
        // Update row cells
        row.querySelector('td:nth-child(2)').textContent = category.name;
        row.querySelector('td:nth-child(3)').textContent = category.description;
        
        // Add animation
        row.style.backgroundColor = 'rgba(78, 115, 223, 0.1)';
        row.style.transition = 'background-color 1s';
        
        setTimeout(() => {
            row.style.backgroundColor = '';
        }, 1000);
    }
}

// Update categories table with search results
function updateCategoriesTable(categories) {
    // Clear table
    categoriesTableBody.innerHTML = '';
    
    // Update category count
    categoryCount.textContent = categories.length + ' Categories';
    
    if (categories.length === 0) {
        // Show no results message
        const noDataRow = document.createElement('tr');
        noDataRow.id = 'noCategoriesRow';
        noDataRow.innerHTML = '<td colspan="6" class="text-center py-4">No categories found.</td>';
        categoriesTableBody.appendChild(noDataRow);
        return;
    }
    
    // Add categories to table
    categories.forEach(category => {
        const newRow = document.createElement('tr');
        newRow.className = 'category-row';
        newRow.dataset.id = category.id;
        
        // Set row HTML
        newRow.innerHTML = `
            <td class="text-center sortable-handle" title="Drag to reorder"><i class="fa fa-grip-vertical"></i></td>
            <td>${escapeHtml(category.name)}</td>
            <td>${escapeHtml(category.description)}</td>
            <td class="text-center">
                <span class="badge bg-info">${category.product_count}</span>
            </td>
            <td class="text-center">
                ${category.status == 1 ? 
                    '<span class="badge bg-success">Active</span>' : 
                    '<span class="badge bg-danger">Inactive</span>'}
            </td>
            <td>
                <div class="btn-group btn-group-sm">
                    <button type="button" class="btn btn-primary edit-btn" data-id="${category.id}" title="Edit">
                        <i class="fa fa-edit"></i>
                    </button>
                    
                    <button type="button" class="btn ${category.status == 1 ? 'btn-warning' : 'btn-success'} status-btn" 
                        data-id="${category.id}" 
                        data-status="${category.status == 1 ? 0 : 1}" 
                        title="${category.status == 1 ? 'Deactivate' : 'Activate'}">
                        <i class="fa ${category.status == 1 ? 'fa-times' : 'fa-check'}"></i>
                    </button>
                    
                    <button type="button" class="btn btn-danger delete-btn" 
                        data-id="${category.id}" 
                        title="Delete" 
                        ${category.product_count > 0 ? 'disabled' : ''}>
                        <i class="fa fa-trash"></i>
                    </button>
                </div>
            </td>
        `;
        
        // Add to table
        categoriesTableBody.appendChild(newRow);
    });
    
    // Update sortable
    sortableTable.option('disabled', !reorderModeSwitch.checked);
}

// Update category count
function updateCategoryCount() {
    const count = document.querySelectorAll('.category-row').length;
    categoryCount.textContent = count + ' Categories';
}

// Show custom confirmation modal
function showConfirmationModal(title, message, callback, data) {
    confirmationModalTitle.textContent = title;
    confirmationModalMessage.textContent = message;
    
    currentAction = callback;
    currentActionData = data;
    
    confirmationModal.classList.add('show');
}

// Hide custom confirmation modal
function hideConfirmationModal() {
    confirmationModal.classList.remove('show');
}

// Show toast notification
function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = 'toast';
    toast.style.marginBottom = '10px';
    
    // Set toast type
    let bgClass = 'bg-light';
    let icon = 'fa-info-circle';
    let textColor = 'text-dark';
    
    switch (type) {
        case 'success':
            icon = 'fa-check-circle';
            textColor = 'text-success';
            break;
        case 'error':
            icon = 'fa-exclamation-circle';
            textColor = 'text-danger';
            break;
        case 'warning':
            icon = 'fa-exclamation-triangle';
            textColor = 'text-warning';
            break;
        case 'info':
            icon = 'fa-info-circle';
            textColor = 'text-info';
            break;
    }
    
    toast.innerHTML = `
        <div class="toast-header">
            <i class="fa ${icon} me-2 ${textColor}"></i>
            <strong class="me-auto">${type.charAt(0).toUpperCase() + type.slice(1)}</strong>
            <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
        <div class="toast-body">
            ${message}
        </div>
    `;
    
    document.getElementById('toastContainer').appendChild(toast);
    
    // Trigger animation
    setTimeout(() => {
        toast.classList.add('showing');
    }, 10);
    
    // Auto-hide after 5 seconds
    setTimeout(() => {
        toast.classList.remove('showing');
        setTimeout(() => {
            toast.remove();
        }, 300);
    }, 5000);
    
    // Close button functionality
    toast.querySelector('.btn-close').addEventListener('click', function() {
        toast.classList.remove('showing');
        setTimeout(() => {
            toast.remove();
        }, 300);
    });
}

// Show loading overlay
function showLoadingOverlay() {
    loadingOverlay.classList.add('show');
}

// Hide loading overlay
function hideLoadingOverlay() {
    loadingOverlay.classList.remove('show');
}

// Refresh data
function refreshData() {
    showLoadingOverlay();
    
    // Use window.location to reload the page
    // For a full AJAX implementation, we would make an AJAX call to get fresh data
    window.location.reload();
}

// Export categories
function exportCategories() {
    showToast('Preparing categories export...', 'info');
    
    setTimeout(() => {
        // Create CSV content
        let csv = 'ID,Name,Description,Status\n';
        
        document.querySelectorAll('.category-row').forEach(row => {
            const id = row.dataset.id;
            const name = row.querySelector('td:nth-child(2)').textContent;
            const description = row.querySelector('td:nth-child(3)').textContent;
            const status = row.querySelector('td:nth-child(5) .badge').textContent;

            csv += `${id},"${name.replace(/"/g, '""')}","${description.replace(/"/g, '""')}","${status}"\n`;
        });

        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);

        link.setAttribute('href', url);
        link.setAttribute('download', 'categories-export.csv');
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }, 500);
}

// Escape HTML to prevent XSS
function escapeHtml(unsafe) {
    return unsafe
         .replace(/&/g, "&amp;")
         .replace(/</g, "&lt;")
         .replace(/>/g, "&gt;")
         .replace(/"/g, "&quot;")
         .replace(/'/g, "&#039;");
}

// Initialize theme
function initTheme() {
    if (localStorage.getItem('darkMode') === 'true') {
        document.documentElement.classList.add('dark-mode');
        themeToggle.querySelector('i').className = 'fa fa-sun';
    } else {
        document.documentElement.classList.remove('dark-mode');
        themeToggle.querySelector('i').className = 'fa fa-moon';
    }
}

// Toggle theme
function toggleTheme() {
    const isDarkMode = document.documentElement.classList.toggle('dark-mode');
    localStorage.setItem('darkMode', isDarkMode);
    
    if (isDarkMode) {
        themeToggle.querySelector('i').className = 'fa fa-sun';
        showToast('Dark mode enabled', 'info');
    } else {
        themeToggle.querySelector('i').className = 'fa fa-moon';
        showToast('Light mode enabled', 'info');
    }
}

// Initialize keyboard shortcuts
function initKeyboardShortcuts() {
    document.addEventListener('keydown', function(e) {
        if (e.altKey) {
            switch (e.key) {
                case 'n': // Alt + N for New category
                    e.preventDefault();
                    resetForm();
                    break;
                case 's': // Alt + S to Save form
                    e.preventDefault();
                    if (!submitBtn.disabled) {
                        categoryForm.dispatchEvent(new Event('submit'));
                    }
                    break;
            }
        }
        
        if (e.key === 'Escape') { // Esc to cancel edit
            if (formAction.value === 'edit') {
                e.preventDefault();
                resetForm();
            }
        }
    });
}

</script>

<?php
// Include footer
include_once '../includes/footer.php';
?>