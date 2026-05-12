<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    echo "Please login first.";
    exit();
}

$user_id = $_SESSION['user_id'];

echo "<h2>Restore Stock for Cancelled Orders</h2>";

// Find cancelled orders and restore their stock
$restore_stmt = $conn->prepare("
    SELECT o.id, o.status, oi.product_id, oi.quantity 
    FROM orders o 
    JOIN order_items oi ON o.id = oi.order_id 
    WHERE o.user_id = ? AND o.status = 'cancelled'
");
$restore_stmt->bind_param("i", $user_id);
$restore_stmt->execute();
$restore_result = $restore_stmt->get_result();

if ($restore_result->num_rows > 0) {
    echo "<h3>Found cancelled orders to restore stock:</h3>";
    
    while ($order = $restore_result->fetch_assoc()) {
        echo "<div style='background: #f8f9fa; padding: 15px; margin: 10px 0; border-radius: 8px;'>";
        echo "<strong>Order #{$order['id']}</strong><br>";
        echo "Product ID: {$order['product_id']}<br>";
        echo "Quantity to restore: {$order['quantity']}<br>";
        
        // Get current stock
        $current_stock_stmt = $conn->prepare("SELECT stock_quantity FROM products WHERE id = ?");
        
        if ($current_stock_stmt === false) {
            echo "<span style='color: red;'>✗ Error preparing stock query: " . $conn->error . "</span><br>";
        } else {
            $current_stock_stmt->bind_param("i", $order['product_id']);
            $current_stock_stmt->execute();
            $current_stock_result = $current_stock_stmt->get_result();
            $current_stock = $current_stock_result->fetch_assoc()['stock_quantity'];
            $current_stock_stmt->close();
            
            echo "Current stock: $current_stock<br>";
            
            // Restore stock
            $update_stock = $conn->prepare("UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?");
            
            if ($update_stock === false) {
                echo "<span style='color: red;'>✗ Error preparing stock update: " . $conn->error . "</span><br>";
            } else {
                $update_stock->bind_param("ii", $order['quantity'], $order['product_id']);
                
                if ($update_stock->execute()) {
                    $new_stock = $current_stock + $order['quantity'];
                    echo "<span style='color: green;'>✓ Stock restored! New stock: $new_stock</span><br>";
                } else {
                    echo "<span style='color: red;'>✗ Failed to restore stock: " . $update_stock->error . "</span><br>";
                }
                $update_stock->close();
            }
        }
        
        echo "</div>";
    }
} else {
    echo "<p>No cancelled orders found for stock restoration.</p>";
}

$restore_stmt->close();

// Show current stock for product ID 2 (Converse Shoes)
echo "<h3>Current Stock for Product ID 2 (Converse Shoes):</h3>";
$product_stock_stmt = $conn->prepare("SELECT id, name, stock_quantity FROM products WHERE id = 2");

if ($product_stock_stmt === false) {
    echo "<p>Error preparing product stock query: " . $conn->error . "</p>";
} else {
    $product_stock_stmt->execute();
    $product_stock_result = $product_stock_stmt->get_result();

    if ($product_stock_result->num_rows > 0) {
        $product = $product_stock_result->fetch_assoc();
        echo "<div style='background: #e8f5e9; padding: 15px; border-radius: 8px;'>";
        echo "<strong>Product:</strong> {$product['name']}<br>";
        echo "<strong>Current Stock:</strong> {$product['stock_quantity']} units<br>";
        echo "</div>";
    } else {
        echo "<p>Product ID 2 not found.</p>";
    }

    $product_stock_stmt->close();
}

echo "<br><a href='product_details.php?id=2'>← Back to Product Details</a>";
echo "<br><a href='orders.php'>← View Orders</a>";

$conn->close();
?>
