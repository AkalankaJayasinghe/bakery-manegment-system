<?php
/**
 * Logout Script
 * 
 * Handles user logout process with security features, animation, and redirect
 * 
 * @package BakeryManagementSystem
 */
session_start();
require_once '../config/constants.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

// Set timeout for script execution
set_time_limit(30);

// CSRF protection - verify token if available
$csrf_valid = true;
if (isset($_SESSION['csrf_token']) && isset($_POST['csrf_token'])) {
    if ($_SESSION['csrf_token'] !== $_POST['csrf_token']) {
        $csrf_valid = false;
        // Log CSRF attempt
        error_log('CSRF attempt detected during logout: ' . $_SERVER['REMOTE_ADDR']);
    }
}

// Process logout only if CSRF check passes
if ($csrf_valid) {
    // Check if user is logged in
    if (isLoggedIn()) {
        // Connect to database
        $conn = getDBConnection();
        
        // Record logout time and update user's last_activity
        if ($conn) {
            try {
                // Update user's last_activity timestamp
                $user_id = $_SESSION['user_id'];
                $stmt = $conn->prepare("UPDATE users SET last_activity = NOW(), is_online = 0 WHERE id = ?");
                if ($stmt) {
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $stmt->close();
                }
                
                // Log logout activity with additional details
                if (function_exists('logActivity')) {
                    $browser = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'Unknown';
                    $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'Unknown';
                    $details = "User logged out: {$_SESSION['username']} | IP: $ip | Browser: $browser";
                    
                    logActivity($conn, 'logout', $user_id, $details);
                }
            } catch (Exception $e) {
                error_log('Error during logout process: ' . $e->getMessage());
            }
            
            // Close database connection
            $conn->close();
        }
        
        // Store username temporarily for goodbye message
        $username = isset($_SESSION['username']) ? $_SESSION['username'] : '';
        
        // Clear all session variables
        $_SESSION = array();
        
        // If it's desired to kill the session, also delete the session cookie
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(), 
                '', 
                time() - 86400,  // Set expiration to past (1 day ago)
                $params["path"], 
                $params["domain"],
                $params["secure"], 
                $params["httponly"]
            );
        }
        
        // Finally, destroy the session
        session_destroy();
        
        // Set logout success flag in URL
        $redirect_params = "logged_out=true";
        
        // Add username for personalized message if available
        if (!empty($username)) {
            $redirect_params .= "&user=" . urlencode($username);
        }
    } else {
        // No active session found
        $redirect_params = "logged_out=inactive";
    }
} else {
    // CSRF validation failed
    $redirect_params = "error=security";
}

// Create a visually appealing logout page before redirecting
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logging Out - Bakery Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #fff0f0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .logout-container {
            background-color: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 500px;
            padding: 40px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .logout-icon {
            width: 100px;
            height: 100px;
            background-color: #ff6b6b;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            color: white;
            font-size: 40px;
        }
        
        .logout-title {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 10px;
            color: #333;
        }
        
        .logout-message {
            color: #666;
            margin-bottom: 30px;
        }
        
        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid rgba(255, 107, 107, 0.3);
            border-radius: 50%;
            border-top-color: #ff6b6b;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .redirect-text {
            font-size: 14px;
            color: #888;
        }
        
        .countdown {
            display: inline-block;
            width: 25px;
            height: 25px;
            line-height: 25px;
            background-color: #ff6b6b;
            color: white;
            border-radius: 50%;
            font-size: 12px;
            margin-left: 5px;
        }
        
        .progress-bar {
            height: 5px;
            background-color: #f0f0f0;
            border-radius: 5px;
            overflow: hidden;
            margin: 20px 0;
        }
        
        .progress-fill {
            height: 100%;
            background-color: #ff6b6b;
            width: 0;
            transition: width 3s linear;
        }
    </style>
</head>
<body>
    <div class="logout-container">
        <div class="logout-icon">
            <i class="fas fa-sign-out-alt"></i>
        </div>
        <h2 class="logout-title">Logging Out</h2>
        <p class="logout-message">Please wait while we securely log you out...</p>
        <div class="spinner"></div>
        <div class="progress-bar">
            <div class="progress-fill"></div>
        </div>
        <p class="redirect-text">You will be redirected to the login page in <span class="countdown">3</span></p>
    </div>

    <script>
        // Animate progress bar
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelector('.progress-fill').style.width = '100%';
            
            // Countdown timer
            let countdown = 3;
            const countdownEl = document.querySelector('.countdown');
            
            const countdownInterval = setInterval(function() {
                countdown--;
                if (countdown <= 0) {
                    clearInterval(countdownInterval);
                    // Redirect to login page
                    window.location.href = "<?php echo SITE_URL . '/auth/login.php?' . $redirect_params; ?>";
                } else {
                    countdownEl.textContent = countdown;
                }
            }, 1000);
        });
    </script>
</body>
</html>