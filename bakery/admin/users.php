<?php
// Include database connection
include 'includes/db_connect.php';
include 'includes/functions.php';
include 'includes/session.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit();
}

// Handle user actions (add, edit, delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        // Add new user
        if ($_POST['action'] === 'add') {
            $username = $_POST['username'];
            $email = $_POST['email'];
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $role = $_POST['role'];
            
            // Check if username already exists
            $check_query = "SELECT * FROM users WHERE username = '$username' OR email = '$email'";
            $check_result = mysqli_query($conn, $check_query);
            
            if (mysqli_num_rows($check_result) > 0) {
                $error_message = "Username or email already exists!";
            } else {
                $query = "INSERT INTO users (username, email, password, role) 
                         VALUES ('$username', '$email', '$password', '$role')";
                
                if (mysqli_query($conn, $query)) {
                    // Log the activity
                    logActivity($conn, 'Added new user: ' . $username, $_SESSION['user_id']);
                    $success_message = "User added successfully!";
                } else {
                    $error_message = "Error: " . mysqli_error($conn);
                }
            }
        }
        
        // Edit user
        if ($_POST['action'] === 'edit') {
            $user_id = $_POST['user_id'];
            $username = $_POST['username'];
            $email = $_POST['email'];
            $role = $_POST['role'];
            
            // Check if password is being updated
            if (!empty($_POST['password'])) {
                $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $query = "UPDATE users SET username = '$username', email = '$email', 
                         password = '$password', role = '$role' WHERE id = '$user_id'";
            } else {
                $query = "UPDATE users SET username = '$username', email = '$email', 
                         role = '$role' WHERE id = '$user_id'";
            }
            
            if (mysqli_query($conn, $query)) {
                // Log the activity
                logActivity($conn, 'Updated user: ' . $username, $_SESSION['user_id']);
                $success_message = "User updated successfully!";
            } else {
                $error_message = "Error: " . mysqli_error($conn);
            }
        }
        
        // Delete user
        if ($_POST['action'] === 'delete' && isset($_POST['user_id'])) {
            $user_id = $_POST['user_id'];
            
            // Get username for logging
            $username_query = "SELECT username FROM users WHERE id = '$user_id'";
            $username_result = mysqli_query($conn, $username_query);
            $username_row = mysqli_fetch_assoc($username_result);
            $username = $username_row['username'];
            
            // Don't allow deleting your own account
            if ($user_id == $_SESSION['user_id']) {
                $error_message = "You cannot delete your own account!";
            } else {
                $query = "DELETE FROM users WHERE id = '$user_id'";
                
                if (mysqli_query($conn, $query)) {
                    // Log the activity
                    logActivity($conn, 'Deleted user: ' . $username, $_SESSION['user_id']);
                    $success_message = "User deleted successfully!";
                } else {
                    $error_message = "Error: " . mysqli_error($conn);
                }
            }
        }
    }
}

// Get all users
$query = "SELECT * FROM users ORDER BY username ASC";

// Add search filter if provided
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = $_GET['search'];
    $query = "SELECT * FROM users 
              WHERE username LIKE '%$search%' OR email LIKE '%$search%' OR role LIKE '%$search%'
              ORDER BY username ASC";
}

$result = mysqli_query($conn, $query);

// Include header
include 'includes/header.php';
?>

<div class="main-content">
    <h1>Manage Users</h1>
    
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h3>Add New User</h3>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="add">
                        
                        <div class="form-group">
                            <label>Username</label>
                            <input type="text" name="username" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Password</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Role</label>
                            <select name="role" class="form-control" required>
                                <option value="admin">Admin</option>
                                <option value="cashier">Cashier</option>
                                <option value="manager">Manager</option>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-block">Add User</button>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h3>Users List</h3>
                </div>
                <div class="card-body">
                    <!-- Search Form -->
                    <form method="GET" class="mb-3">
                        <div class="input-group">
                            <input type="text" name="search" class="form-control" placeholder="Search by username, email or role" value="<?php echo isset($_GET['search']) ? $_GET['search'] : ''; ?>">
                            <div class="input-group-append">
                                <button type="submit" class="btn btn-primary">Search</button>
                                <a href="users.php" class="btn btn-secondary">Reset</a>
                            </div>
                        </div>
                    </form>
                    
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Username</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Created At</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (mysqli_num_rows($result) > 0): ?>
                                    <?php $counter = 1; while ($row = mysqli_fetch_assoc($result)): ?>
                                        <tr>
                                            <td><?php echo $counter++; ?></td>
                                            <td><?php echo $row['username']; ?></td>
                                            <td><?php echo $row['email']; ?></td>
                                            <td><span class="badge badge-<?php echo $row['role'] === 'admin' ? 'danger' : ($row['role'] === 'manager' ? 'warning' : 'info'); ?>"><?php echo ucfirst($row['role']); ?></span></td>
                                            <td><?php echo date('Y-m-d', strtotime($row['created_at'])); ?></td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-primary" data-toggle="modal" data-target="#editModal<?php echo $row['id']; ?>">Edit</button>
                                                
                                                <?php if ($row['id'] != $_SESSION['user_id']): ?>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="user_id" value="<?php echo $row['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this user?')">Delete</button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        
                                        <!-- Edit User Modal -->
                                        <div class="modal fade" id="editModal<?php echo $row['id']; ?>" tabindex="-1" role="dialog" aria-labelledby="editModalLabel" aria-hidden="true">
                                            <div class="modal-dialog" role="document">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title" id="editModalLabel">Edit User: <?php echo $row['username']; ?></h5>
                                                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                            <span aria-hidden="true">&times;</span>
                                                        </button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <form method="POST">
                                                            <input type="hidden" name="action" value="edit">
                                                            <input type="hidden" name="user_id" value="<?php echo $row['id']; ?>">
                                                            
                                                            <div class="form-group">
                                                                <label>Username</label>
                                                                <input type="text" name="username" class="form-control" value="<?php echo $row['username']; ?>" required>
                                                            </div>
                                                            
                                                            <div class="form-group">
                                                                <label>Email</label>
                                                                <input type="email" name="email" class="form-control" value="<?php echo $row['email']; ?>" required>
                                                            </div>
                                                            
                                                            <div class="form-group">
                                                                <label>Password (leave blank to keep current)</label>
                                                                <input type="password" name="password" class="form-control">
                                                            </div>
                                                            
                                                            <div class="form-group">
                                                                <label>Role</label>
                                                                <select name="role" class="form-control" required>
                                                                    <option value="admin" <?php echo $row['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                                                    <option value="cashier" <?php echo $row['role'] === 'cashier' ? 'selected' : ''; ?>>Cashier</option>
                                                                    <option value="manager" <?php echo $row['role'] === 'manager' ? 'selected' : ''; ?>>Manager</option>
                                                                </select>
                                                            </div>
                                                            
                                                            <button type="submit" class="btn btn-primary">Save Changes</button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center">No users found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>