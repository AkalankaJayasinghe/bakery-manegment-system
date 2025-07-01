<?php
session_start();
require_once 'includes/compatibility.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Check if cart data is provided
if (!isset($_POST['cart']) || empty($_POST['cart'])) {
    http_response_code(400);
    echo json_encode(['error' => 'No cart data provided']);
    exit;
}

try {
    // Decode cart data
    $cart = json_decode($_POST['cart'], true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid cart data format');
    }
    
    // Validate cart data
    if (!is_array($cart)) {
        throw new Exception('Cart data must be an array');
    }
    
    foreach ($cart as $item) {
        if (!isset($item['id']) || !isset($item['name']) || !isset($item['price']) || !isset($item['quantity'])) {
            throw new Exception('Invalid cart item format');
        }
        
        if (!is_numeric($item['id']) || !is_numeric($item['price']) || !is_numeric($item['quantity'])) {
            throw new Exception('Cart item values must be numeric');
        }
        
        if ($item['quantity'] <= 0) {
            throw new Exception('Cart item quantity must be greater than 0');
        }
    }
    
    // Store cart in session
    $_SESSION['cart'] = $cart;
    
    // Calculate totals for confirmation
    $subtotal = 0;
    foreach ($cart as $item) {
        $subtotal += $item['price'] * $item['quantity'];
    }
    
    $tax = $subtotal * 0.1;
    $total = $subtotal + $tax;
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Cart stored successfully',
        'cart_items' => count($cart),
        'subtotal' => round($subtotal, 2),
        'tax' => round($tax, 2),
        'total' => round($total, 2)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to store cart: ' . $e->getMessage()]);
}
?>
