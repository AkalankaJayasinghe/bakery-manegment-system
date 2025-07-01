<?php
session_start();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Session Debug</title>
</head>
<body>
    <h2>Session Debug Information</h2>
    <h3>All Session Variables:</h3>
    <pre><?php print_r($_SESSION); ?></pre>
    
    <h3>Specific Checks:</h3>
    <p><strong>User ID:</strong> <?php echo isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'NOT SET'; ?></p>
    <p><strong>Username:</strong> <?php echo isset($_SESSION['username']) ? $_SESSION['username'] : 'NOT SET'; ?></p>
    <p><strong>Role:</strong> <?php echo isset($_SESSION['role']) ? $_SESSION['role'] : 'NOT SET'; ?></p>
    <p><strong>Name:</strong> <?php echo isset($_SESSION['name']) ? $_SESSION['name'] : 'NOT SET'; ?></p>
    
    <h3>Authentication Status:</h3>
    <p><strong>Is Logged In:</strong> <?php echo isset($_SESSION['user_id']) ? 'YES' : 'NO'; ?></p>
    <p><strong>Role Check (cashier):</strong> <?php echo (isset($_SESSION['role']) && $_SESSION['role'] === 'cashier') ? 'YES' : 'NO'; ?></p>
    <p><strong>Role Check (admin):</strong> <?php echo (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') ? 'YES' : 'NO'; ?></p>
    <p><strong>Combined Role Check:</strong> <?php echo (isset($_SESSION['role']) && ($_SESSION['role'] === 'cashier' || $_SESSION['role'] === 'admin')) ? 'YES' : 'NO'; ?></p>
</body>
</html>
