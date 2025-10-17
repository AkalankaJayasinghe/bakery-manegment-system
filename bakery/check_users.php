<?php
require_once 'config/database.php';
require_once 'cashier/includes/compatibility.php';

$conn = connectDB();

// Check existing users
echo "<h2>Existing Users in Database:</h2>";
$result = $conn->query("SELECT id, username, role, email, first_name, last_name FROM users");

if ($result && $result->num_rows > 0) {
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>ID</th><th>Username</th><th>Role</th><th>Name</th><th>Email</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td><strong>" . $row['username'] . "</strong></td>";
        echo "<td>" . $row['role'] . "</td>";
        echo "<td>" . $row['first_name'] . " " . $row['last_name'] . "</td>";
        echo "<td>" . $row['email'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'>No users found in database!</p>";
    
    // Create default admin user
    echo "<h3>Creating default admin user...</h3>";
    
    $username = 'admin';
    $password = password_hash('admin123', PASSWORD_DEFAULT);
    $first_name = 'Admin';
    $last_name = 'User';
    $full_name = 'Admin User';
    $email = 'admin@bakery.com';
    $role = 'admin';
    $status = 1;
    
    $stmt = $conn->prepare("INSERT INTO users (username, password, first_name, last_name, full_name, email, role, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssssi", $username, $password, $first_name, $last_name, $full_name, $email, $role, $status);
    
    if ($stmt->execute()) {
        echo "<p style='color: green;'>✓ Admin user created successfully!</p>";
    } else {
        echo "<p style='color: red;'>✗ Error creating admin user: " . $stmt->error . "</p>";
    }
    $stmt->close();
}

echo "<hr>";
echo "<h3>Login Credentials:</h3>";
echo "<p><strong>Username:</strong> admin</p>";
echo "<p><strong>Password:</strong> admin123</p>";
echo "<p><a href='auth/login.php'>Go to Login Page</a></p>";

$conn->close();
?>
