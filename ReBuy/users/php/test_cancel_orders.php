<?php
session_start();
include 'db.php';

// Test script to verify cancel order functionality
if (!isset($_SESSION['user_id'])) {
    echo "Please login first to test this functionality.";
    exit();
}

$user_id = $_SESSION['user_id'];

echo "<h2>Testing Cancel Order Functionality</h2>";

// Test 1: Check if cancelled_at column exists
echo "<h3>1. Checking database structure...</h3>";
$column_check = $conn->query("SHOW COLUMNS FROM orders LIKE 'cancelled_at'");
if ($column_check->num_rows > 0) {
    echo "✓ cancelled_at column exists in orders table<br>";
} else {
    echo "✗ cancelled_at column missing. Please run the migration script.<br>";
    echo "<a href='add_cancelled_at_column.php'>Click here to add the column</a><br>";
}

// Test 2: Create a test order if none exists
echo "<h3>2. Creating test order...</h3>";
$test_order_check = $conn->prepare("SELECT id FROM orders WHERE user_id = ? AND status = 'pending' LIMIT 1");
$test_order_check->bind_param("i", $user_id);
$test_order_check->execute();
$test_order_result = $test_order_check->get_result();

if ($test_order_result->num_rows == 0) {
    // Create a test order
    $insert_order = $conn->prepare("
        INSERT INTO orders (user_id, total_amount, shipping_address, payment_method, status) 
        VALUES (?, 10.00, 'Test Address', 'cash_on_delivery', 'pending')
    ");
    $insert_order->bind_param("i", $user_id);
    if ($insert_order->execute()) {
        $order_id = $insert_order->insert_id;
        
        // Add a test order item
        $insert_item = $conn->prepare("
            INSERT INTO order_items (order_id, product_id, quantity, price) 
            VALUES (?, 1, 1, 10.00)
        ");
        $insert_item->bind_param("i", $order_id);
        $insert_item->execute();
        $insert_item->close();
        
        echo "✓ Test order created with ID: $order_id<br>";
    } else {
        echo "✗ Failed to create test order<br>";
    }
    $insert_order->close();
} else {
    $test_order = $test_order_result->fetch_assoc();
    echo "✓ Found existing test order with ID: " . $test_order['id'] . "<br>";
}
$test_order_check->close();

// Test 3: Show current orders
echo "<h3>3. Current orders:</h3>";
$orders_query = $conn->prepare("
    SELECT id, status, cancelled_at, order_date 
    FROM orders 
    WHERE user_id = ? 
    ORDER BY order_date DESC
");
$orders_query->bind_param("i", $user_id);
$orders_query->execute();
$orders_result = $orders_query->get_result();

echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
echo "<tr><th>Order ID</th><th>Status</th><th>Cancelled At</th><th>Order Date</th><th>Countdown</th><th>Action</th></tr>";

while ($order = $orders_result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . $order['id'] . "</td>";
    echo "<td>" . $order['status'] . "</td>";
    echo "<td>" . ($order['cancelled_at'] ?? 'NULL') . "</td>";
    echo "<td>" . $order['order_date'] . "</td>";
    
    if ($order['status'] == 'cancelled' && $order['cancelled_at']) {
        echo "<td><span class='countdown' data-cancelled-at='" . htmlspecialchars($order['cancelled_at']) . "' data-server-time='" . date('Y-m-d\TH:i:s\Z') . "'>Loading...</span></td>";
        echo "<td><em>Auto-removing...</em></td>";
    } else {
        echo "<td>-</td>";
        if ($order['status'] == 'pending') {
            echo "<td><a href='?cancel_order=" . $order['id'] . "'>Cancel Order</a></td>";
        } else {
            echo "<td>-</td>";
        }
    }
    echo "</tr>";
}
echo "</table>";
$orders_query->close();

// Test 4: Handle cancel order request
if (isset($_GET['cancel_order'])) {
    $order_id = $_GET['cancel_order'];
    
    echo "<h3>4. Cancelling order $order_id...</h3>";
    
    // Check if order belongs to user and is pending
    $check_stmt = $conn->prepare("SELECT id FROM orders WHERE id = ? AND user_id = ? AND status = 'pending'");
    $check_stmt->bind_param("ii", $order_id, $user_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        // Update order status to cancelled and set cancelled_at timestamp
        $update_stmt = $conn->prepare("UPDATE orders SET status = 'cancelled', cancelled_at = NOW() WHERE id = ? AND user_id = ?");
        $update_stmt->bind_param("ii", $order_id, $user_id);
        
        if ($update_stmt->execute()) {
            // Get order items to restore stock
            $items_stmt = $conn->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = ?");
            $items_stmt->bind_param("i", $order_id);
            $items_stmt->execute();
            $items_result = $items_stmt->get_result();
            
            // Restore stock for each item
            $stock_restored = 0;
            while ($item = $items_result->fetch_assoc()) {
                $update_stock = $conn->prepare("UPDATE products SET stock = stock + ? WHERE id = ?");
                $update_stock->bind_param("ii", $item['quantity'], $item['product_id']);
                $update_stock->execute();
                $stock_restored += $item['quantity'];
                $update_stock->close();
            }
            $items_stmt->close();
            
            echo "✓ Order cancelled successfully!<br>";
            echo "✓ Stock restored: $stock_restored items<br>";
            echo "✓ Order will be automatically removed after 30 seconds<br>";
            
            // Redirect to remove the GET parameter
            echo "<script>setTimeout(function() { window.location.href = 'test_cancel_orders.php'; }, 2000);</script>";
        } else {
            echo "✗ Failed to cancel order: " . $update_stmt->error . "<br>";
        }
        $update_stmt->close();
    } else {
        echo "✗ Order not found or cannot be cancelled<br>";
    }
    $check_stmt->close();
}

// Test 5: Check for orders that should be cleaned up
echo "<h3>5. Orders ready for cleanup (older than 30 seconds):</h3>";
$cleanup_check = $conn->prepare("
    SELECT id, cancelled_at, TIMESTAMPDIFF(SECOND, cancelled_at, NOW()) as seconds_elapsed
    FROM orders 
    WHERE status = 'cancelled' 
    AND cancelled_at IS NOT NULL 
    AND cancelled_at < DATE_SUB(NOW(), INTERVAL 30 SECOND)
");
$cleanup_check->execute();
$cleanup_result = $cleanup_check->get_result();

if ($cleanup_result->num_rows > 0) {
    echo "Found " . $cleanup_result->num_rows . " orders that should be cleaned up:<br>";
    while ($order = $cleanup_result->fetch_assoc()) {
        echo "- Order ID: " . $order['id'] . " (cancelled " . $order['seconds_elapsed'] . " seconds ago)<br>";
    }
} else {
    echo "No orders ready for cleanup yet.<br>";
}
$cleanup_check->close();

echo "<br><a href='orders.php'>← Back to Orders Page (with real-time countdown)</a>";
echo "<br><a href='dashboard.php'>← Back to Dashboard</a>";

echo "<h3>Real-time Countdown Features:</h3>";
echo "✓ Server-synchronized time for accuracy<br>";
echo "✓ Updates every 100ms for smooth display<br>";
echo "✓ Red warning when ≤ 5 seconds remaining<br>";
echo "✓ Smooth fade-out animation when removed<br>";
echo "✓ Auto-reload when all orders are gone<br>";
?>

<script>
// Enhanced real-time countdown with server synchronization
let serverTimeOffset = 0;

// Calculate server time offset
function calculateServerTimeOffset() {
    const countdowns = document.querySelectorAll('.countdown');
    if (countdowns.length > 0) {
        const serverTimeStr = countdowns[0].dataset.serverTime;
        const serverTime = new Date(serverTimeStr);
        const localTime = new Date();
        serverTimeOffset = serverTime - localTime;
    }
}

function updateCountdowns() {
    const countdowns = document.querySelectorAll('.countdown');
    countdowns.forEach(function(countdown) {
        const cancelledAtStr = countdown.dataset.cancelledAt;
        const cancelledAt = new Date(cancelledAtStr);
        
        // Use server-synchronized time
        const now = new Date(Date.now() + serverTimeOffset);
        const elapsed = Math.floor((now - cancelledAt) / 1000);
        const remaining = Math.max(0, 30 - elapsed);
        
        if (remaining > 0) {
            // Show real-time countdown with seconds
            countdown.textContent = remaining + 's';
            countdown.style.color = remaining <= 5 ? '#dc3545' : '#721c24';
            countdown.style.fontWeight = remaining <= 5 ? 'bold' : 'normal';
            countdown.style.fontSize = remaining <= 5 ? '14px' : '12px';
        } else {
            // Remove the row when countdown reaches 0
            const row = countdown.closest('tr');
            if (row && !row.dataset.removing) {
                row.dataset.removing = 'true';
                row.style.transition = 'opacity 0.5s, transform 0.5s';
                row.style.opacity = '0';
                row.style.transform = 'translateX(-20px)';
                row.style.backgroundColor = '#ffebee';
                setTimeout(function() {
                    row.remove();
                    // Show completion message
                    const completionMsg = document.createElement('div');
                    completionMsg.innerHTML = '<div style="background: #4caf50; color: white; padding: 10px; margin: 10px 0; border-radius: 4px;">✓ Order automatically removed and stock restored!</div>';
                    document.querySelector('h3').parentNode.insertBefore(completionMsg, document.querySelector('h3').nextSibling);
                }, 500);
            }
        }
    });
}

// Initialize server time offset
calculateServerTimeOffset();

// Update countdowns every 100ms for smoother real-time display
setInterval(updateCountdowns, 100);
updateCountdowns(); // Initial call

// Recalculate server time offset every 30 seconds to maintain accuracy
setInterval(calculateServerTimeOffset, 30000);

// Add visual indicator for active countdowns
document.addEventListener('DOMContentLoaded', function() {
    const countdowns = document.querySelectorAll('.countdown');
    if (countdowns.length > 0) {
        const indicator = document.createElement('div');
        indicator.innerHTML = '<div style="background: #2196f3; color: white; padding: 10px; margin: 10px 0; border-radius: 4px; animation: pulse 2s infinite;">⏱️ Real-time countdown active - Watch the timer!</div>';
        indicator.style.cssText = 'animation: pulse 2s infinite;';
        document.querySelector('h2').parentNode.insertBefore(indicator, document.querySelector('h2').nextSibling);
    }
});
</script>

<style>
@keyframes pulse {
    0% { opacity: 1; }
    50% { opacity: 0.7; }
    100% { opacity: 1; }
}
</style>

<?php
$conn->close();
?>
