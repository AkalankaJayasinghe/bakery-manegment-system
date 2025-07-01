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

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
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

// Handle edit request
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
$result = $conn->query("SELECT * FROM categories ORDER BY name ASC");
while ($row = $result->fetch_assoc()) {
    $categories[] = $row;
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
                <h1 class="h2">Manage Categories</h1>
            </div>
            
            <?php if (!empty($error)): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo $error; ?>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
            <div class="alert alert-success" role="alert">
                <?php echo $success; ?>
            </div>
            <?php endif; ?>
            
            <div class="row">
                <!-- Category Form -->
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h5><?php echo ($id > 0) ? 'Edit Category' : 'Add New Category'; ?></h5>
                        </div>
                        <div class="card-body">
                            <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                                <input type="hidden" name="action" value="<?php echo ($id > 0) ? 'edit' : 'add'; ?>">
                                <?php if ($id > 0): ?>
                                <input type="hidden" name="id" value="<?php echo $id; ?>">
                                <?php endif; ?>
                                
                                <div class="mb-3">
                                    <label for="name" class="form-label">Category Name</label>
                                    <input type="text" class="form-control" id="name" name="name" value="<?php echo $name; ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="description" class="form-label">Description</label>
                                    <textarea class="form-control" id="description" name="description" rows="3"><?php echo $description; ?></textarea>
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary"><?php echo ($id > 0) ? 'Update Category' : 'Add Category'; ?></button>
                                    <?php if ($id > 0): ?>
                                    <a href="categories.php" class="btn btn-secondary">Cancel</a>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Categories List -->
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5>Categories List</h5>
                            <span class="badge bg-primary"><?php echo count($categories); ?> Categories</span>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Name</th>
                                            <th>Description</th>
                                            <th>Products</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
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
                                                <tr>
                                                    <td><?php echo $index + 1; ?></td>
                                                    <td><?php echo $category['name']; ?></td>
                                                    <td><?php echo $category['description']; ?></td>
                                                    <td><?php echo $productCount; ?></td>
                                                    <td>
                                                        <?php if ($category['status'] == 1): ?>
                                                            <span class="badge bg-success">Active</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-danger">Inactive</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group btn-group-sm">
                                                            <a href="categories.php?edit=<?php echo $category['id']; ?>" class="btn btn-primary" title="Edit">
                                                                <i class="fa fa-edit"></i>
                                                            </a>
                                                            
                                                            <form method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to change the status of this category?');">
                                                                <input type="hidden" name="change_status" value="<?php echo $category['id']; ?>">
                                                                <input type="hidden" name="status" value="<?php echo $category['status'] == 1 ? 0 : 1; ?>">
                                                                <button type="submit" class="btn <?php echo $category['status'] == 1 ? 'btn-warning' : 'btn-success'; ?>" title="<?php echo $category['status'] == 1 ? 'Deactivate' : 'Activate'; ?>">
                                                                    <i class="fa <?php echo $category['status'] == 1 ? 'fa-times' : 'fa-check'; ?>"></i>
                                                                </button>
                                                            </form>
                                                            
                                                            <form method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this category? This action cannot be undone.');">
                                                                <input type="hidden" name="delete" value="<?php echo $category['id']; ?>">
                                                                <button type="submit" class="btn btn-danger" title="Delete" <?php echo $productCount > 0 ? 'disabled' : ''; ?>>
                                                                    <i class="fa fa-trash"></i>
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="6" class="text-center">No categories found.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
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
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        if (alert) {
            bootstrap.Alert.getOrCreateInstance(alert).close();
        }
    });
}, 5000);
</script>

<?php
// Include footer
include_once '../includes/footer.php';
?>
