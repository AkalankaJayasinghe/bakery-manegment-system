<?php
session_start();
require_once 'includes/compatibility.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Database connection
$conn = connectDB();

try {
    // Get products with category information
    $query = "SELECT p.id, p.name, p.description, p.price, p.stock_quantity, p.image, 
                     c.name as category_name, c.id as category_id 
              FROM products p 
              LEFT JOIN categories c ON p.category_id = c.id 
              WHERE p.status = 1 
              ORDER BY p.name";
    
    $result = $conn->query($query);
    
    if (!$result) {
        throw new Exception("Database query failed: " . $conn->error);
    }
    
    $products = [];
    while ($row = $result->fetch_assoc()) {
        // Ensure numeric values are properly formatted
        $row['price'] = floatval($row['price']);
        $row['stock_quantity'] = intval($row['stock_quantity']);
        $row['id'] = intval($row['id']);
        $row['category_id'] = intval($row['category_id']);
        
        $products[] = $row;
    }
    
    header('Content-Type: application/json');
    echo json_encode($products);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to load products: ' . $e->getMessage()]);
}
?>
