<?php
// Test script for cart functionality - REMOVE IN PRODUCTION
// This script should only be used for testing purposes

session_start();

// Security check - only allow in development
if ($_SERVER['SERVER_NAME'] !== 'localhost' && $_SERVER['SERVER_NAME'] !== '127.0.0.1') {
    die('This test script is only available in development environment.');
}

echo "<div style='background: #fff3cd; border: 1px solid #ffecb5; color: #664d03; padding: 15px; margin: 10px; border-radius: 5px;'>";
echo "<strong>⚠️ WARNING:</strong> This is a test script for development only. Remove this file in production!";
echo "</div>";

// Add test items to cart for testing billing page
$_SESSION['cart'] = [
    [
        'id' => 3,
        'name' => 'Chocolate Cake',
        'price' => 765.00,
        'quantity' => 1,
        'max_quantity' => 54
    ],
    [
        'id' => 1,
        'name' => 'Fresh Bun',
        'price' => 34.00,
        'quantity' => 2,
        'max_quantity' => 34
    ]
];

echo "<div style='background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; padding: 15px; margin: 10px; border-radius: 5px;'>";
echo "<strong>✅ Success:</strong> Test cart items have been added to your session!";
echo "</div>";

echo "<div style='margin: 20px;'>";
echo "<h3>Test Actions:</h3>";
echo "<a href='billing.php' style='display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; margin: 5px;'>Go to Billing Page</a>";
echo "<a href='index.php' style='display: inline-block; padding: 10px 20px; background: #28a745; color: white; text-decoration: none; border-radius: 5px; margin: 5px;'>Go to POS Dashboard</a>";
echo "<a href='?clear=1' style='display: inline-block; padding: 10px 20px; background: #dc3545; color: white; text-decoration: none; border-radius: 5px; margin: 5px;'>Clear Test Cart</a>";
echo "</div>";

// Handle clear cart request
if (isset($_GET['clear'])) {
    unset($_SESSION['cart']);
    echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; margin: 10px; border-radius: 5px;'>";
    echo "<strong>🗑️ Cart Cleared:</strong> Test cart has been removed from session.";
    echo "</div>";
}

echo "<div style='margin: 20px; padding: 15px; background: #f8f9fa; border-radius: 5px;'>";
echo "<h4>Current Session Cart:</h4>";
if (isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
    echo "<pre>" . htmlspecialchars(json_encode($_SESSION['cart'], JSON_PRETTY_PRINT)) . "</pre>";
} else {
    echo "<p style='color: #6c757d;'>No items in cart</p>";
}
echo "</div>";
?>
