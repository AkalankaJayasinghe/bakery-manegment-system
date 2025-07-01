<?php
session_start();
require_once '../config/database.php';
require_once '../config/constants.php';
require_once '../includes/functions.php';

// Check if user is already logged in
if (isLoggedIn()) {
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
$username = '';

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get and sanitize input
    $username = isset($_POST['username']) ? sanitizeInput($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    
    // Validate input
    if (empty($username) || empty($password)) {
        $error = "Username and password are required";
    } else {
        try {
            $conn = getDBConnection();
            
            if (!$conn) {
                throw new Exception("Database connection failed");
            }
            
            // Prepare SQL statement to prevent SQL injection
            $stmt = $conn->prepare("SELECT id, username, password, first_name, last_name, role, status FROM users WHERE username = ?");
            if (!$stmt) {
                throw new Exception("Prepare statement failed: " . $conn->error);
            }
            
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows == 1) {
                $user = $result->fetch_assoc();
                
                // Verify password - for testing purposes, check both hashed and plain passwords
                if (password_verify($password, $user['password']) || $password === $user['password']) {
                    // Check if account is active
                    if (isset($user['status']) && $user['status'] != 1) {
                        $error = "Your account has been deactivated. Please contact administrator.";
                    } else {
                        // Password is correct, create session
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['role'] = $user['role'];
                        $_SESSION['name'] = $user['first_name'] . ' ' . $user['last_name'];
                        
                        // Log the activity if function exists
                        if (function_exists('logActivity')) {
                            logActivity('login', 'User logged in successfully');
                        }
                        
                        // Redirect based on role
                        if ($user['role'] === 'admin') {
                            header("Location: ../admin/index.php");
                            exit;
                        } else if ($user['role'] === 'cashier') {
                            header("Location: ../cashier/index.php");
                            exit;
                        } else {
                            header("Location: ../index.php");
                            exit;
                        }
                    }
                } else {
                    $error = "Invalid username or password";
                }
            } else {
                $error = "Invalid username or password";
            }
            
            $stmt->close();
            
        } catch (Exception $e) {
            $error = "System error: " . $e->getMessage();
            // Log the error for admin review
            error_log("Login error: " . $e->getMessage());
        }
    }
}

// Page title
$pageTitle = "Login";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Bakery Management System</title>
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
        }
        
        .login-container {
            display: flex;
            width: 100%;
            max-width: 1000px;
            background: var(--light-color);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: var(--shadow);
        }
        
        .login-image {
            flex: 1;
            background: url('../assets/images/bakery-bg.jpg') center center;
            background-size: cover;
            padding: 40px;
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
        }
        
        .login-image::before {
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
        
        .login-form {
            flex: 1;
            padding: 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .logo-container {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .logo {
            width: 100px;
            height: 100px;
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
        
        .login-heading {
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 10px;
            color: var(--dark-color);
        }
        
        .login-subheading {
            color: #777;
            margin-bottom: 30px;
        }
        
        .form-floating {
            margin-bottom: 20px;
        }
        
        .form-floating .form-control {
            border-radius: 10px;
            height: 60px;
            border: 1px solid #e1e1e1;
        }
        
        .form-floating .form-control:focus {
            box-shadow: 0 0 0 4px rgba(255, 107, 107, 0.2);
            border-color: var(--primary-color);
        }
        
        .form-floating label {
            padding: 1rem;
        }
        
        .login-button {
            height: 55px;
            border-radius: 10px;
            background: var(--primary-color);
            border: none;
            font-weight: 600;
            font-size: 1.1rem;
            margin-top: 10px;
            transition: all 0.3s ease;
        }
        
        .login-button:hover {
            background: #ff5252;
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(255, 107, 107, 0.3);
        }
        
        .signup-link {
            text-align: center;
            margin-top: 20px;
            color: #777;
        }
        
        .signup-link a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
        }
        
        .signup-link a:hover {
            text-decoration: underline;
        }
        
        .return-link {
            text-align: center;
            margin-top: 15px;
            font-size: 0.9rem;
        }
        
        .return-link a {
            color: #777;
            text-decoration: none;
        }
        
        .return-link a:hover {
            color: var(--primary-color);
        }
        
        .alert-custom {
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
        
        .current-time {
            position: absolute;
            bottom: 20px;
            right: 20px;
            color: white;
            font-size: 0.9rem;
            opacity: 0.7;
        }
        
        /* Responsive styles */
        @media (max-width: 992px) {
            .login-container {
                flex-direction: column;
                max-width: 500px;
            }
            
            .login-image {
                display: none;
            }
            
            .login-form {
                padding: 40px 30px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-image">
            <div class="welcome-text">
                <h2>Welcome Back!</h2>
                <p>Sign in to manage your bakery operations efficiently.</p>
            </div>
        </div>
        
        <div class="login-form">
            <div class="logo-container">
                <img src="../assets/images/bakery-logo.png" alt="Bakery Logo" class="logo">
                <h2 class="logo-text">Bakery Management</h2>
                <p class="logo-subtext">System</p>
            </div>
            
            <h3 class="login-heading">Sign In</h3>
            <p class="login-subheading">Please enter your credentials to continue</p>
            
            <?php if (!empty($error)): ?>
                <div class="alert-custom">
                    <i class="fas fa-exclamation-circle alert-icon"></i>
                    <div><?php echo $error; ?></div>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <div class="form-floating">
                    <input type="text" class="form-control" id="username" name="username" placeholder="Username" value="<?php echo htmlspecialchars($username); ?>" required>
                    <label for="username"><i class="fas fa-user me-2"></i> Username</label>
                </div>
                
                <div class="form-floating">
                    <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                    <label for="password"><i class="fas fa-lock me-2"></i> Password</label>
                </div>
                
                <button type="submit" class="btn btn-primary w-100 login-button">Sign In</button>
            </form>
            
            <div class="signup-link">
                Don't have an account? <a href="signup.php">Sign up here</a>
            </div>
            
            <div class="return-link">
                <a href="../index.php"><i class="fas fa-home me-1"></i> Return to Home</a>
            </div>
            
            <div class="current-time">
                <?php echo date('F j, Y - g:i A'); ?>
            </div>
        </div>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert-custom');
            alerts.forEach(function(alert) {
                alert.style.opacity = '0';
                alert.style.transition = 'opacity 0.5s';
                setTimeout(() => {
                    alert.style.display = 'none';
                }, 500);
            });
        }, 5000);
    </script>
</body>
</html>