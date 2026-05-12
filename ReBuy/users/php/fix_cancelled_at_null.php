<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    echo "Please login first.";
    exit();
}

$user_id = $_SESSION['user_id'];

echo "<h2>Fix Cancelled Orders with NULL cancelled_at</h2>";

// Find orders with status 'cancelled' but NULL cancelled_at and restore their stock
$fix_stmt = $conn->prepare("
    UPDATE orders 
    SET cancelled_at = NOW() 
    WHERE user_id = ? AND status = 'cancelled' AND (cancelled_at IS NULL OR cancelled_at = '0000-00-00 00:00:00')
");

if ($fix_stmt === false) {
    echo "✗ Error preparing fix statement: " . $conn->error . "<br>";
} else {
    $fix_stmt->bind_param("i", $user_id);

    if ($fix_stmt->execute()) {
        $affected_rows = $fix_stmt->affected_rows;
        echo "✓ Fixed $affected_rows cancelled order(s) with proper timestamp<br>";
        
        // Now restore stock for these orders
        if ($affected_rows > 0) {
            // Get the cancelled orders to restore stock
            $restore_stmt = $conn->prepare("
                SELECT o.id, oi.product_id, oi.quantity 
                FROM orders o 
                JOIN order_items oi ON o.id = oi.order_id 
                WHERE o.user_id = ? AND o.status = 'cancelled'
            ");
            
            if ($restore_stmt === false) {
                echo "✗ Error preparing restore statement: " . $conn->error . "<br>";
            } else {
                $restore_stmt->bind_param("i", $user_id);
                $restore_stmt->execute();
                $restore_result = $restore_stmt->get_result();
                
                $total_restored = 0;
                while ($order = $restore_result->fetch_assoc()) {
                    // Restore stock
                    $update_stock = $conn->prepare("UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?");
                    
                    if ($update_stock === false) {
                        echo "✗ Error preparing stock update: " . $conn->error . "<br>";
                        continue;
                    }
                    
                    $update_stock->bind_param("ii", $order['quantity'], $order['product_id']);
                    
                    if ($update_stock->execute()) {
                        $total_restored += $order['quantity'];
                        echo "✓ Restored {$order['quantity']} units to product ID {$order['product_id']}<br>";
                    } else {
                        echo "✗ Failed to restore stock for product ID {$order['product_id']}: " . $update_stock->error . "<br>";
                    }
                    $update_stock->close();
                }
                $restore_stmt->close();
                
                echo "<strong>✓ Total stock restored: $total_restored units</strong><br>";
                echo "<br><a href='product_details.php?id=2'>← Back to Product Details</a>";
            }
        } else {
            echo "✗ Error fixing orders: " . $fix_stmt->error . "<br>";
        }
        $fix_stmt->close();
    }
}

// Show current orders
echo "<h3>Current Orders Status:</h3>";
$check_stmt = $conn->prepare("
    SELECT o.id, o.status, o.cancelled_at, oi.product_id 
    FROM orders o 
    JOIN order_items oi ON o.id = oi.order_id 
    WHERE o.user_id = ? AND oi.product_id = 2
    ORDER BY o.id DESC
");
$check_stmt->bind_param("i", $user_id);
$check_stmt->execute();
$result = $check_stmt->get_result();

if ($result->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr><th>Order ID</th><th>Status</th><th>Cancelled At</th></tr>";
    while ($order = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $order['id'] . "</td>";
        echo "<td>" . $order['status'] . "</td>";
        echo "<td>" . ($order['cancelled_at'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "No orders found for product ID 2.<br>";
}

$check_stmt->close();
$conn->close();
?>
