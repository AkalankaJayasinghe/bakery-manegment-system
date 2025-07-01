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
        $firstName = sanitizeInput($_POST['first_name']);
        $lastName = sanitizeInput($_POST['last_name']);
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
                    $confirmPassword = sanitizeInput($_POST['confirm_password']);
                    
                    if (empty($password)) {
                        $error = "Password is required";
                    } elseif (strlen($password) < PASSWORD_MIN_LENGTH) {
                        $error = "Password must be at least " . PASSWORD_MIN_LENGTH . " characters long";
                    } elseif ($password !== $confirmPassword) {
                        $error = "Passwords do not match";
                    } else {
                        // Check if username or email already exists
                        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
                        $stmt->bind_param("ss", $username, $email);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        
                        if ($result->num_rows > 0) {
                            $error = "Username or email already exists";
                        } else {
                            // Hash password and insert user
                            $hashedPassword = hashPassword($password);
                            $stmt = $conn->prepare("INSERT INTO users (username, password, first_name, last_name, email, role, status, created_at) VALUES (?, ?, ?, ?, ?, ?, 1, NOW())");
                            $stmt->bind_param("ssssss", $username, $hashedPassword, $firstName, $lastName, $email, $role);
                            
                            if ($stmt->execute()) {
                                $success = "User created successfully";
                                logActivity('create_user', "Created new user: $username ($firstName $lastName)");
                                // Clear form data
                                $username = $firstName = $lastName = $email = $role = '';
                            } else {
                                $error = "Error creating user: " . $conn->error;
                            }
                        }
                    }
                    break;
                    
                case 'edit':
                    $id = intval($_POST['id']);
                    
                    // Check if username or email already exists for other users
                    $stmt = $conn->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
                    $stmt->bind_param("ssi", $username, $email, $id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($result->num_rows > 0) {
                        $error = "Username or email already exists";
                    } else {
                        $stmt = $conn->prepare("UPDATE users SET username = ?, first_name = ?, last_name = ?, email = ?, role = ? WHERE id = ?");
                        $stmt->bind_param("sssssi", $username, $firstName, $lastName, $email, $role, $id);
                        
                        if ($stmt->execute()) {
                            $success = "User updated successfully";
                            logActivity('update_user', "Updated user: $username ($firstName $lastName)");
                            // Clear form data
                            $username = $firstName = $lastName = $email = $role = '';
                            $id = 0;
                        } else {
                            $error = "Error updating user: " . $conn->error;
                        }
                    }
                    break;
            }
        }
    }
}

// Handle user status change
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_status'])) {
    $id = intval($_POST['user_id']);
    $status = intval($_POST['status']);
    
    // Don't allow deactivating current admin user
    if ($id == $_SESSION['user_id'] && $status == 0) {
        $error = "You cannot deactivate your own account";
    } else {
        $stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");
        $stmt->bind_param("ii", $status, $id);
        
        if ($stmt->execute()) {
            $statusText = $status == 1 ? 'activated' : 'deactivated';
            $success = "User $statusText successfully";
            
            // Get user info for logging
            $stmt = $conn->prepare("SELECT username, first_name, last_name FROM users WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            
            logActivity('change_user_status', "Changed user status: {$user['username']} ({$user['first_name']} {$user['last_name']}) - $statusText");
        } else {
            $error = "Error changing user status: " . $conn->error;
        }
    }
}

// Handle password reset
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reset_password'])) {
    $id = intval($_POST['user_id']);
    $newPassword = generateRandomPassword(8);
    $hashedPassword = hashPassword($newPassword);
    
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmt->bind_param("si", $hashedPassword, $id);
    
    if ($stmt->execute()) {
        // Get user info for logging
        $stmt = $conn->prepare("SELECT username, email, first_name, last_name FROM users WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        
        $success = "Password reset successfully. New password: <strong>$newPassword</strong><br>Please provide this to the user and ask them to change it upon first login.";
        logActivity('reset_password', "Reset password for user: {$user['username']} ({$user['first_name']} {$user['last_name']})");
    } else {
        $error = "Error resetting password: " . $conn->error;
    }
}

// Handle edit request
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
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

// Get all users
$users = [];
$result = $conn->query("SELECT * FROM users ORDER BY first_name ASC, last_name ASC");
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}

// Set page title
$pageTitle = "Manage Users";

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
                <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <div class="row">
                <!-- User Form -->
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h5><?php echo ($id > 0) ? 'Edit User' : 'Add New User'; ?></h5>
                        </div>
                        <div class="card-body">
                            <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                                <input type="hidden" name="action" value="<?php echo ($id > 0) ? 'edit' : 'add'; ?>">
                                <?php if ($id > 0): ?>
                                <input type="hidden" name="id" value="<?php echo $id; ?>">
                                <?php endif; ?>
                                
                                <div class="mb-3">
                                    <label for="username" class="form-label">Username</label>
                                    <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="first_name" class="form-label">First Name</label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo htmlspecialchars($firstName); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="last_name" class="form-label">Last Name</label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo htmlspecialchars($lastName); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="role" class="form-label">Role</label>
                                    <select class="form-select" id="role" name="role" required>
                                        <option value="">Select Role</option>
                                        <option value="admin" <?php echo $role == 'admin' ? 'selected' : ''; ?>>Administrator</option>
                                        <option value="cashier" <?php echo $role == 'cashier' ? 'selected' : ''; ?>>Cashier</option>
                                    </select>
                                </div>
                                
                                <?php if ($id == 0): // Only show password fields when adding new user ?>
                                <div class="mb-3">
                                    <label for="password" class="form-label">Password</label>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                    <div class="form-text">Minimum <?php echo PASSWORD_MIN_LENGTH; ?> characters</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Confirm Password</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                </div>
                                <?php endif; ?>
                                
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary"><?php echo ($id > 0) ? 'Update User' : 'Add User'; ?></button>
                                    <?php if ($id > 0): ?>
                                    <a href="users.php" class="btn btn-secondary">Cancel</a>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Users List -->
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5>Users List</h5>
                            <span class="badge bg-primary"><?php echo count($users); ?> Users</span>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Name</th>
                                            <th>Username</th>
                                            <th>Email</th>
                                            <th>Role</th>
                                            <th>Status</th>
                                            <th>Last Login</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (count($users) > 0): ?>
                                            <?php foreach ($users as $index => $user): ?>
                                                <tr>
                                                    <td><?php echo $index + 1; ?></td>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></strong>
                                                        <?php if ($user['id'] == $_SESSION['user_id']): ?>
                                                            <span class="badge bg-info ms-1">You</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                                    <td>
                                                        <?php
                                                        $roleClass = '';
                                                        switch ($user['role']) {
                                                            case 'admin':
                                                                $roleClass = 'bg-danger';
                                                                break;
                                                            case 'cashier':
                                                                $roleClass = 'bg-primary';
                                                                break;
                                                            default:
                                                                $roleClass = 'bg-secondary';
                                                        }
                                                        ?>
                                                        <span class="badge <?php echo $roleClass; ?>">
                                                            <?php echo ucfirst($user['role']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php if ($user['status'] == 1): ?>
                                                            <span class="badge bg-success">Active</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-danger">Inactive</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($user['last_login']): ?>
                                                            <?php echo date('d/m/Y H:i', strtotime($user['last_login'])); ?>
                                                        <?php else: ?>
                                                            <span class="text-muted">Never</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group btn-group-sm">
                                                            <a href="users.php?edit=<?php echo $user['id']; ?>" class="btn btn-primary" title="Edit">
                                                                <i class="fa fa-edit"></i>
                                                            </a>
                                                            
                                                            <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                            <div class="btn-group btn-group-sm">
                                                                <button type="button" class="btn btn-warning dropdown-toggle" data-bs-toggle="dropdown" title="More Actions">
                                                                    <i class="fa fa-cog"></i>
                                                                </button>
                                                                <ul class="dropdown-menu">
                                                                    <li>
                                                                        <form method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to reset this user\'s password?');">
                                                                            <input type="hidden" name="reset_password" value="1">
                                                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                                            <button type="submit" class="dropdown-item">
                                                                                <i class="fa fa-key"></i> Reset Password
                                                                            </button>
                                                                        </form>
                                                                    </li>
                                                                    <li><hr class="dropdown-divider"></li>
                                                                    <li>
                                                                        <form method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to change this user\'s status?');">
                                                                            <input type="hidden" name="change_status" value="1">
                                                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                                            <input type="hidden" name="status" value="<?php echo $user['status'] == 1 ? 0 : 1; ?>">
                                                                            <button type="submit" class="dropdown-item <?php echo $user['status'] == 1 ? 'text-warning' : 'text-success'; ?>">
                                                                                <i class="fa <?php echo $user['status'] == 1 ? 'fa-ban' : 'fa-check'; ?>"></i>
                                                                                <?php echo $user['status'] == 1 ? 'Deactivate' : 'Activate'; ?>
                                                                            </button>
                                                                        </form>
                                                                    </li>
                                                                </ul>
                                                            </div>
                                                            <?php else: ?>
                                                            <button type="button" class="btn btn-secondary" disabled title="Cannot modify your own account">
                                                                <i class="fa fa-lock"></i>
                                                            </button>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="8" class="text-center">
                                                    <div class="py-4">
                                                        <i class="fa fa-users fa-3x text-muted mb-3"></i>
                                                        <p class="text-muted">No users found</p>
                                                    </div>
                                                </td>
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
// Password confirmation validation
document.getElementById('confirm_password')?.addEventListener('input', function() {
    const password = document.getElementById('password').value;
    const confirmPassword = this.value;
    
    if (password !== confirmPassword) {
        this.setCustomValidity('Passwords do not match');
    } else {
        this.setCustomValidity('');
    }
});

// Auto-generate username from first and last name
document.getElementById('first_name').addEventListener('input', generateUsername);
document.getElementById('last_name').addEventListener('input', generateUsername);

function generateUsername() {
    const firstName = document.getElementById('first_name').value.toLowerCase();
    const lastName = document.getElementById('last_name').value.toLowerCase();
    const usernameField = document.getElementById('username');
    
    // Only auto-generate if username field is empty
    if (!usernameField.value && firstName && lastName) {
        usernameField.value = firstName + '.' + lastName;
    }
}
</script>

<?php include_once '../includes/footer.php'; ?>