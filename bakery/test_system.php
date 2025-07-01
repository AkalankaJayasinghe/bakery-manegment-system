<?php
include 'config/database.php';
include 'cashier/includes/compatibility.php';

try {
    $conn = connectDB();
    echo 'Database connection: SUCCESS' . PHP_EOL;
    
    // Check if sales table exists
    $result = $conn->query('SHOW TABLES LIKE \'sales\'');
    echo 'Sales table exists: ' . ($result->num_rows > 0 ? 'YES' : 'NO') . PHP_EOL;
    
    // Check if sale_items table exists
    $result = $conn->query('SHOW TABLES LIKE \'sale_items\'');
    echo 'Sale_items table exists: ' . ($result->num_rows > 0 ? 'YES' : 'NO') . PHP_EOL;
    
    // Check products table structure
    $result = $conn->query('DESCRIBE products');
    $columns = [];
    while ($row = $result->fetch_assoc()) {
        $columns[] = $row['Field'];
    }
    echo 'Products columns: ' . implode(', ', $columns) . PHP_EOL;
    
    // Check if there are any products
    $result = $conn->query('SELECT COUNT(*) as count FROM products');
    $count = $result->fetch_assoc()['count'];
    echo 'Number of products: ' . $count . PHP_EOL;
    
    // Check if there are any users
    $result = $conn->query('SELECT COUNT(*) as count FROM users');
    $count = $result->fetch_assoc()['count'];
    echo 'Number of users: ' . $count . PHP_EOL;
    
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
}
?>
