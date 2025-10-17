<?php
/**
 * NOTE: This file appears to be a duplicate or an outdated version of the main functions file.
 * It's recommended to consolidate functions into a single `includes/functions.php` file.
 * For now, I'm adding the missing functions here to ensure functionality.
 */
/**
 * Authentication related functions
 */

/**
 * Hash a password
 * 
 * @param string $password Plain password
 * @return string Hashed password
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Sanitize user input
 * 
 * @param string $data Input data
 * @return string Sanitized data
 */
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

/**
 * Generate a random password
 * 
 * @param int $length Password length
 * @return string Random password
 */
function generateRandomPassword($length = 10) {
    $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_-=+;:,.?";
    $password = substr(str_shuffle($chars), 0, $length);
    return $password;
}

/**
 * Create a new user
 * 
 * @param string $username Username
 * @param string $password Plain password
 * @param string $firstName First name
 * @param string $lastName Last name
 * @param string $email Email address
 * @param int $role User role
 * @return int|bool User ID if successful, false otherwise
 */
function createUser($username, $password, $firstName, $lastName, $email, $role) {
    $conn = getDBConnection();
    
    // Check if username or email already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $stmt->bind_param("ss", $username, $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Username or email already exists
        return false;
    }
    
    // Hash the password
    $hashedPassword = hashPassword($password);
    
    // Insert new user
    $stmt = $conn->prepare("INSERT INTO users (username, password, first_name, last_name, email, role, status, created_at) 
                            VALUES (?, ?, ?, ?, ?, ?, 1, NOW())");
    $stmt->bind_param("sssssi", $username, $hashedPassword, $firstName, $lastName, $email, $role);
    
    if ($stmt->execute()) {
        return $conn->insert_id;
    } else {
        return false;
    }
}

/**
 * Update user profile
 * 
 * @param int $userId User ID
 * @param string $firstName First name
 * @param string $lastName Last name
 * @param string $email Email address
 * @param string $password New password (optional)
 * @return bool True if successful, false otherwise
 */
function updateUserProfile($userId, $firstName, $lastName, $email, $password = null) {
    $conn = getDBConnection();
    
    if ($password) {
        // Update with new password
        $hashedPassword = hashPassword($password);
        $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, password = ? WHERE id = ?");
        $stmt->bind_param("ssssi", $firstName, $lastName, $email, $hashedPassword, $userId);
    } else {
        // Update without changing password
        $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ? WHERE id = ?");
        $stmt->bind_param("sssi", $firstName, $lastName, $email, $userId);
    }
    
    return $stmt->execute();
}

/**
 * Get user details by ID
 * 
 * @param int $userId User ID
 * @return array|bool User details as array or false if not found
 */
function getUserById($userId) {
    $conn = getDBConnection();
    
    $stmt = $conn->prepare("SELECT id, username, first_name, last_name, email, role, status, created_at, last_login 
                            FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        return $result->fetch_assoc();
    } else {
        return false;
    }
}

/**
 * Update user's last login time
 * 
 * @param int $userId User ID
 * @return bool True if successful, false otherwise
 */
function updateLastLogin($userId) {
    $conn = getDBConnection();
    
    $stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
    $stmt->bind_param("i", $userId);
    
    return $stmt->execute();
}

/**
 * Change user status (activate/deactivate)
 * 
 * @param int $userId User ID
 * @param int $status New status (1 = active, 0 = inactive)
 * @return bool True if successful, false otherwise
 */
function changeUserStatus($userId, $status) {
    $conn = getDBConnection();
    
    $stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");
    $stmt->bind_param("ii", $status, $userId);
    
    return $stmt->execute();
}

/**
 * Check if password meets security requirements
 * 
 * @param string $password Password to check
 * @return bool True if password meets requirements, false otherwise
 */
function isPasswordSecure($password) {
    // Password should be at least 8 characters long and contain at least one uppercase letter, 
    // one lowercase letter, one number, and one special character
    return (strlen($password) >= 8 && 
            preg_match('/[A-Z]/', $password) && 
            preg_match('/[a-z]/', $password) && 
            preg_match('/[0-9]/', $password) && 
            preg_match('/[^A-Za-z0-9]/', $password));
}
?>
<?php
/**
 * Log user activity
 * 
 * @param object $conn Database connection
 * @param string $action The action performed
 * @param int $user_id The ID of the user performing the action
 */
function logActivity($conn, $action, $user_id) {
    $ip_address = $_SERVER['REMOTE_ADDR'];
    
    // Check if activity_logs table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'activity_logs'");
    if ($table_check->num_rows > 0) {
        $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, ip_address) VALUES (?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("iss", $user_id, $action, $ip_address);
            $stmt->execute();
            $stmt->close();
        }
    }
}

?>
