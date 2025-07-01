<?php
session_start();
require_once '../config/database.php';
require_once '../config/constants.php';
require_once '../includes/functions.php';

// Check if user is already logged in
if (function_exists('isLoggedIn') && isLoggedIn()) {
    // Redirect to appropriate dashboard
    if (hasAdminPrivileges()) {
        header("Location: " . ADMIN_URL);
        exit;
    } else if (hasCashierPrivileges()) {
        header("Location: " . CASHIER_URL);
        exit;
    } else {
        header("Location: " . SITE_URL);
        exit;
    }
}

$error = '';
$success = '';
$username = '';
$first_name = '';
$last_name = '';
$email = '';
$phone = '';
$role = '';

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get and sanitize input
    $username = isset($_POST['username']) ? sanitizeInput($_POST['username']) : '';
    $first_name = isset($_POST['first_name']) ? sanitizeInput($_POST['first_name']) : '';
    $last_name = isset($_POST['last_name']) ? sanitizeInput($_POST['last_name']) : '';
    $email = isset($_POST['email']) ? sanitizeInput($_POST['email']) : '';
    $phone = isset($_POST['phone']) ? sanitizeInput($_POST['phone']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
    $role = isset($_POST['role']) ? sanitizeInput($_POST['role']) : '';
    
    // Validate input
    if (empty($username) || empty($first_name) || empty($last_name) || empty($password) || empty($confirm_password) || empty($role)) {
        $error = "All required fields must be filled";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long";
    } elseif (!in_array($role, ['admin', 'cashier'])) {
        $error = "Invalid role selected";
    } else {
        try {
            $conn = getDBConnection();
            
            if (!$conn) {
                throw new Exception("Database connection failed");
            }
            
            // Check if username already exists
            $check_stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
            if (!$check_stmt) {
                throw new Exception("Prepare statement failed: " . $conn->error);
            }
            
            $check_stmt->bind_param("s", $username);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $error = "Username already exists. Please choose a different username.";
                $check_stmt->close();
            } else {
                $check_stmt->close();
                
                // Hash password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Create full name
                $full_name = $first_name . ' ' . $last_name;
                
                // Set status to active (1)
                $status = 1;
                
                // Prepare SQL statement to insert new user
                $stmt = $conn->prepare("INSERT INTO users (username, first_name, last_name, password, full_name, role, email, phone, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
                if (!$stmt) {
                    throw new Exception("Prepare statement failed: " . $conn->error);
                }
                
                $stmt->bind_param("sssssssis", $username, $first_name, $last_name, $hashed_password, $full_name, $role, $email, $phone, $status);
                
                if ($stmt->execute()) {
                    $success = "Account created successfully! You can now <a href='login.php'>login</a> with your credentials.";
                    
                    // Clear form data on success
                    $username = '';
                    $first_name = '';
                    $last_name = '';
                    $email = '';
                    $phone = '';
                    $role = '';
                } else {
                    $error = "Error creating account: " . $stmt->error;
                }
                
                $stmt->close();
            }
            
        } catch (Exception $e) {
            $error = "System error: " . $e->getMessage();
            // Log the error for admin review
            error_log("Signup error: " . $e->getMessage());
        }
    }
}

// Page title
$pageTitle = "Sign Up";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - Bakery Management System</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #ff6b6b;
            --secondary-color: #fff0f0;
            --dark-color: #333333;
            --light-color: #ffffff;
            --shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--secondary-color);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .signup-container {
            width: 100%;
            max-width: 900px;
            background: var(--light-color);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: var(--shadow);
            display: flex;
        }
        
        .signup-image {
            flex: 1;
            background: url('../assets/images/bakery-bg.jpg') center center;
            background-size: cover;
            padding: 40px;
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
        }
        
        .signup-image::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(to bottom, rgba(0,0,0,0.2), rgba(0,0,0,0.7));
        }
        
        .welcome-text {
            color: var(--light-color);
            position: relative;
            z-index: 1;
        }
        
        .welcome-text h2 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .welcome-text p {
            font-size: 1rem;
            opacity: 0.9;
        }
        
        .signup-form {
            flex: 1;
            padding: 40px;
            overflow-y: auto;
            max-height: 700px;
        }
        
        .logo-container {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo {
            width: 80px;
            height: 80px;
            object-fit: contain;
            margin-bottom: 10px;
        }
        
        .logo-text {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
            margin: 0;
        }
        
        .logo-subtext {
            font-size: 0.9rem;
            color: #888;
            margin: 0;
        }
        
        .signup-heading {
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 10px;
            color: var(--dark-color);
        }
        
        .signup-subheading {
            color: #777;
            margin-bottom: 25px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            font-weight: 500;
            margin-bottom: 5px;
            color: var(--dark-color);
        }
        
        .form-control {
            border-radius: 10px;
            padding: 12px 15px;
            border: 1px solid #e1e1e1;
        }
        
        .form-control:focus {
            box-shadow: 0 0 0 4px rgba(255, 107, 107, 0.2);
            border-color: var(--primary-color);
        }
        
        .form-select {
            border-radius: 10px;
            padding: 12px 15px;
            border: 1px solid #e1e1e1;
            height: 48px;
        }
        
        .form-select:focus {
            box-shadow: 0 0 0 4px rgba(255, 107, 107, 0.2);
            border-color: var(--primary-color);
        }
        
        .signup-button {
            height: 50px;
            border-radius: 10px;
            background: var(--primary-color);
            border: none;
            font-weight: 600;
            font-size: 1.1rem;
            margin-top: 10px;
            transition: all 0.3s ease;
        }
        
        .signup-button:hover {
            background: #ff5252;
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(255, 107, 107, 0.3);
        }
        
        .login-link {
            text-align: center;
            margin-top: 20px;
            color: #777;
        }
        
        .login-link a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
        }
        
        .login-link a:hover {
            text-decoration: underline;
        }
        
        .alert-success-custom {
            background-color: rgba(76, 175, 80, 0.1);
            color: #4caf50;
            border: none;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
        }
        
        .alert-danger-custom {
            background-color: rgba(255, 82, 82, 0.1);
            color: #ff5252;
            border: none;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
        }
        
        .alert-icon {
            margin-right: 10px;
            font-size: 1.5rem;
        }
        
        /* Responsive styles */
        @media (max-width: 992px) {
            .signup-container {
                flex-direction: column;
                max-width: 500px;
            }
            
            .signup-image {
                display: none;
            }
            
            .signup-form {
                max-height: none;
                padding: 30px;
            }
        }
    </style>
</head>
<body>
    <div class="signup-container">
        <div class="signup-image">
            <div class="welcome-text">
                <h2>Join Our Team</h2>
                <p>Create an account to start managing your bakery operations efficiently.</p>
            </div>
        </div>
        
        <div class="signup-form">
            <div class="logo-container">
                <img src="../assets/images/bakery-logo.png" alt="Bakery Logo" class="logo">
                <h2 class="logo-text">Bakery Management</h2>
                <p class="logo-subtext">System</p>
            </div>
            
            <h3 class="signup-heading">Sign Up</h3>
            <p class="signup-subheading">Please fill in the form to create an account</p>
            
            <?php if (!empty($error)): ?>
                <div class="alert-danger-custom">
                    <i class="fas fa-exclamation-circle alert-icon"></i>
                    <div><?php echo $error; ?></div>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="alert-success-custom">
                    <i class="fas fa-check-circle alert-icon"></i>
                    <div><?php echo $success; ?></div>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="role" class="form-label">Role <span class="text-danger">*</span></label>
                            <select class="form-select" id="role" name="role" required>
                                <option value="">Select Role</option>
                                <option value="admin" <?php echo ($role === 'admin') ? 'selected' : ''; ?>>Admin</option>
                                <option value="cashier" <?php echo ($role === 'cashier') ? 'selected' : ''; ?>>Cashier</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo htmlspecialchars($first_name); ?>" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo htmlspecialchars($last_name); ?>" required>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="phone" class="form-label">Phone</label>
                            <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($phone); ?>">
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="password" name="password" required>
                            <small class="text-muted">Password must be at least 6 characters long</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="confirm_password" class="form-label">Confirm Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary w-100 signup-button">Create Account</button>
                </div>
            </form>
            
            <div class="login-link">
                Already have an account? <a href="login.php">Login here</a>
            </div>
        </div>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert-success-custom, .alert-danger-custom');
            alerts.forEach(function(alert) {
                alert.style.opacity = '0';
                alert.style.transition = 'opacity 0.5s';
                setTimeout(() => {
                    if (alert.classList.contains('alert-success-custom')) {
                        alert.style.display = 'none';
                    }
                }, 500);
            });
        }, 5000);
        
        // Simple password matching validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (password !== confirmPassword) {
                this.setCustomValidity("Passwords don't match");
            } else {
                this.setCustomValidity('');
            }
        });
    </script>
</body>
</html>