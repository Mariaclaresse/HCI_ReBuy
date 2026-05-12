<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    echo "Please login first.";
    exit();
}

$user_id = $_SESSION['user_id'];

echo "<h2>Creating Test Canceled Order for Countdown Demo</h2>";

// Create a test order that was canceled 10 seconds ago
$insert_order = $conn->prepare("
    INSERT INTO orders (user_id, total_amount, shipping_address, payment_method, status, cancelled_at) 
    VALUES (?, 25.00, 'Test Address for Countdown', 'cash_on_delivery', 'cancelled', DATE_SUB(NOW(), INTERVAL 10 SECOND))
");
$insert_order->bind_param("i", $user_id);

if ($insert_order->execute()) {
    $order_id = $insert_order->insert_id;
    
    // Add a test order item
    $insert_item = $conn->prepare("
        INSERT INTO order_items (order_id, product_id, quantity, price) 
        VALUES (?, 1, 2, 12.50)
    ");
    $insert_item->bind_param("i", $order_id);
    $insert_item->execute();
    $insert_item->close();
    
    echo "✓ Test canceled order created with ID: $order_id<br>";
    echo "✓ Order was canceled 10 seconds ago, so you should see '20s' countdown<br>";
    echo "✓ Stock will be restored when countdown reaches 0<br>";
    
    echo "<br><strong>Now visit the orders page to see the countdown:</strong><br>";
    echo "<a href='orders.php' style='display: inline-block; background: #2d5016; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; margin: 10px 0;'>📊 View Orders with Countdown</a><br>";
    echo "<a href='test_cancel_orders.php' style='display: inline-block; background: #2196f3; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; margin: 10px 0;'>🧪 Test Page with Countdown</a>";
    
} else {
    echo "✗ Failed to create test order: " . $insert_order->error . "<br>";
}

$insert_order->close();
$conn->close();
?>
