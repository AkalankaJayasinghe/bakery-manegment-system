<?php
session_start();
require_once '../config/database.php';
require_once '../config/constants.php';
require_once '../includes/functions.php';
require_once '../auth/functions.php';

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
$username = '';
$firstName = '';
$lastName = '';
$email = '';
$role = '';
$error = '';
$success = '';
$id = 0;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        // Sanitize inputs
        $username = sanitizeInput($_POST['username']);
        $firstName = sanitizeInput($_POST['firstName']);
        $lastName = sanitizeInput($_POST['lastName']);
        $email = sanitizeInput($_POST['email']);
        $role = sanitizeInput($_POST['role']);
        
        // Validate inputs
        if (empty($username) || empty($firstName) || empty($lastName) || empty($email) || empty($role)) {
            $error = "All fields are required";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email format";
        } else {
            // Determine action
            switch ($_POST['action']) {
                case 'add':
                    $password = sanitizeInput($_POST['password']);
                    if (empty($password)) {
                        $error = "Password is required";
                    } elseif (strlen($password) < 6) {
                        $error = "Password must be at least 6 characters long";
                    } else {
                        // Use createUser function from auth/functions.php
                        $newUserId = createUser($username, $password, $firstName, $lastName, $email, $role);
                        
                        if ($newUserId) {
                            $success = "User added successfully";
                            logActivity('add_user', "Added new user: $username ($firstName $lastName)");
                            // Clear form fields
                            $username = '';
                            $firstName = '';
                            $lastName = '';
                            $email = '';
                            $role = '';
                        } else {
                            $error = "Username or email already exists";
                        }
                    }
                    break;
                    
                case 'edit':
                    $id = $_POST['id'];
                    $password = sanitizeInput($_POST['password']);
                    
                    // Check if username or email already exists for other users
                    $stmt = $conn->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
                    $stmt->bind_param("ssi", $username, $email, $id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($result->num_rows > 0) {
                        $error = "Username or email already exists for another user";
                    } else {
                        // Update user
                        if (!empty($password)) {
                            // Update with new password
                            $hashedPassword = hashPassword($password);
                            $stmt = $conn->prepare("UPDATE users SET username = ?, first_name = ?, last_name = ?, email = ?, role = ?, password = ? WHERE id = ?");
                            $stmt->bind_param("ssssssi", $username, $firstName, $lastName, $email, $role, $hashedPassword, $id);
                        } else {
                            // Update without changing password
                            $stmt = $conn->prepare("UPDATE users SET username = ?, first_name = ?, last_name = ?, email = ?, role = ? WHERE id = ?");
                            $stmt->bind_param("sssssi", $username, $firstName, $lastName, $email, $role, $id);
                        }
                        
                        if ($stmt->execute()) {
                            $success = "User updated successfully";
                            logActivity('edit_user', "Updated user: $username ($firstName $lastName, ID: $id)");
                            $id = 0; // Reset form to Add mode
                            $username = '';
                            $firstName = '';
                            $lastName = '';
                            $email = '';
                            $role = '';
                        } else {
                            $error = "Error updating user: " . $conn->error;
                        }
                    }
                    break;
            }
        }
    }
    
    // Handle delete action
    if (isset($_POST['delete'])) {
        $id = $_POST['delete'];
        
        // Don't allow deleting the current user
        if ($id == $_SESSION['user_id']) {
            $error = "You cannot delete your own account";
        } else {
            // Get user name for logging
            $stmt = $conn->prepare("SELECT username, first_name, last_name FROM users WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            
            // Delete user
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $id);
            
            if ($stmt->execute()) {
                $success = "User deleted successfully";
                logActivity('delete_user', "Deleted user: {$user['username']} ({$user['first_name']} {$user['last_name']}, ID: $id)");
            } else {
                $error = "Error deleting user: " . $conn->error;
            }
        }
    }
    
    // Handle status change
    if (isset($_POST['change_status'])) {
        $id = $_POST['change_status'];
        $status = $_POST['status'];
        
        // Don't allow disabling the current user
        if ($id == $_SESSION['user_id'] && $status == 0) {
            $error = "You cannot disable your own account";
        } else {
            $stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");
            $stmt->bind_param("ii", $status, $id);
            
            if ($stmt->execute()) {
                $statusText = ($status == 1) ? "activated" : "deactivated";
                $success = "User $statusText successfully";
                
                // Get user name for logging
                $stmt = $conn->prepare("SELECT username, first_name, last_name FROM users WHERE id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $result = $stmt->get_result();
                $user = $result->fetch_assoc();
                
                logActivity('change_user_status', "Changed user status: {$user['username']} ({$user['first_name']} {$user['last_name']}, ID: $id) - $statusText");
            } else {
                $error = "Error changing user status: " . $conn->error;
            }
        }
    }
}

// Handle edit request
if (isset($_GET['edit'])) {
    $id = $_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        $username = $user['username'];
        $firstName = $user['first_name'];
        $lastName = $user['last_name'];
        $email = $user['email'];
        $role = $user['role'];
    } else {
        $error = "User not found";
    }
}

// Handle search
$searchTerm = '';
$whereClause = '';
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $searchTerm = sanitizeInput($_GET['search']);
    $whereClause = "WHERE username LIKE '%$searchTerm%' OR first_name LIKE '%$searchTerm%' OR last_name LIKE '%$searchTerm%' OR email LIKE '%$searchTerm%'";
}

// Handle role filter
$roleFilter = '';
if (isset($_GET['role_filter']) && !empty($_GET['role_filter'])) {
    $roleFilter = sanitizeInput($_GET['role_filter']);
    if (!empty($whereClause)) {
        $whereClause .= " AND role = '$roleFilter'";
    } else {
        $whereClause = "WHERE role = '$roleFilter'";
    }
}

// Set page title
$pageTitle = "Manage Users";

// Get all users
$users = [];
$sql = "SELECT * FROM users $whereClause ORDER BY first_name, last_name ASC";
$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
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
                <h1 class="h2">Manage Users</h1>
            </div>
            
            <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fa fa-exclamation-triangle"></i> <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fa fa-check-circle"></i> <?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>
            
            <div class="row">
                <!-- Add/Edit User Form -->
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h5><?php echo ($id > 0) ? 'Edit User' : 'Add New User'; ?></h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="id" value="<?php echo $id; ?>">
                                <input type="hidden" name="action" value="<?php echo ($id > 0) ? 'edit' : 'add'; ?>">
                                
                                <div class="mb-3">
                                    <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="firstName" class="form-label">First Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="firstName" name="firstName" value="<?php echo htmlspecialchars($firstName); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="lastName" class="form-label">Last Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="lastName" name="lastName" value="<?php echo htmlspecialchars($lastName); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="password" class="form-label">Password <?php echo ($id > 0) ? '(leave empty to keep current)' : '<span class="text-danger">*</span>'; ?></label>
                                    <input type="password" class="form-control" id="password" name="password" <?php echo ($id == 0) ? 'required' : ''; ?> minlength="6">
                                    <?php if ($id == 0): ?>
                                    <div class="form-text">Password must be at least 6 characters long.</div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="role" class="form-label">Role <span class="text-danger">*</span></label>
                                    <select class="form-select" id="role" name="role" required>
                                        <option value="">Select Role</option>
                                        <option value="admin" <?php echo ($role == 'admin') ? 'selected' : ''; ?>>Administrator</option>
                                        <option value="cashier" <?php echo ($role == 'cashier') ? 'selected' : ''; ?>>Cashier</option>
                                    </select>
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fa fa-save"></i> <?php echo ($id > 0) ? 'Update User' : 'Add User'; ?>
                                    </button>
                                    <?php if ($id > 0): ?>
                                    <a href="users.php" class="btn btn-secondary">
                                        <i class="fa fa-times"></i> Cancel
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Users List -->
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <div class="row">
                                <div class="col-md-8">
                                    <h5>Users List</h5>
                                </div>
                                <div class="col-md-4">
                                    <!-- Search and filter form -->
                                    <form method="GET" class="d-flex">
                                        <input type="text" class="form-control me-2" name="search" placeholder="Search users..." value="<?php echo htmlspecialchars($searchTerm); ?>">
                                        <select class="form-select me-2" name="role_filter">
                                            <option value="">All Roles</option>
                                            <option value="admin" <?php echo ($roleFilter == 'admin') ? 'selected' : ''; ?>>Admin</option>
                                            <option value="cashier" <?php echo ($roleFilter == 'cashier') ? 'selected' : ''; ?>>Cashier</option>
                                        </select>
                                        <button type="submit" class="btn btn-outline-secondary">
                                            <i class="fa fa-search"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if (count($users) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Username</th>
                                            <th>Email</th>
                                            <th>Role</th>
                                            <th>Status</th>
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td>
                                                <span class="badge <?php echo ($user['role'] == 'admin') ? 'bg-primary' : 'bg-info'; ?>">
                                                    <?php echo ucfirst($user['role']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="change_status" value="<?php echo $user['id']; ?>">
                                                    <input type="hidden" name="status" value="<?php echo $user['status'] == 1 ? 0 : 1; ?>">
                                                    <button type="submit" class="btn btn-sm <?php echo $user['status'] == 1 ? 'btn-success' : 'btn-danger'; ?>" 
                                                            onclick="return confirm('Are you sure you want to change this user\'s status?');">
                                                        <?php echo $user['status'] == 1 ? 'Active' : 'Inactive'; ?>
                                                    </button>
                                                </form>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <a href="users.php?edit=<?php echo $user['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                        <i class="fa fa-edit"></i>
                                                    </a>
                                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="delete" value="<?php echo $user['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger" 
                                                                onclick="return confirm('Are you sure you want to delete this user? This action cannot be undone.');">
                                                            <i class="fa fa-trash"></i>
                                                        </button>
                                                    </form>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php else: ?>
                            <div class="text-center py-4">
                                <p class="text-muted">No users found.</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include_once '../includes/footer.php'; ?>