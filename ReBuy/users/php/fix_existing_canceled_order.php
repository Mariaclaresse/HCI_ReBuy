<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    echo "Please login first.";
    exit();
}

$user_id = $_SESSION['user_id'];

echo "<h2>Fixing Existing Canceled Order</h2>";

// Update the existing canceled order to have a cancelled_at timestamp
$update_stmt = $conn->prepare("
    UPDATE orders 
    SET cancelled_at = DATE_SUB(NOW(), INTERVAL 15 SECOND) 
    WHERE user_id = ? AND status = 'cancelled' AND cancelled_at IS NULL
");
$update_stmt->bind_param("i", $user_id);

if ($update_stmt->execute()) {
    $affected_rows = $update_stmt->affected_rows;
    if ($affected_rows > 0) {
        echo "✓ Fixed $affected_rows canceled order(s) with timestamp<br>";
        echo "✓ Order was cancelled 15 seconds ago, so you should see '15s' countdown<br>";
        echo "✓ <a href='orders.php'>Go to Orders Page to see the countdown!</a><br>";
    } else {
        echo "ℹ️ No canceled orders needed fixing (they already have timestamps)<br>";
    }
} else {
    echo "✗ Error updating orders: " . $update_stmt->error . "<br>";
}

$update_stmt->close();

// Show current orders after fix
echo "<br><strong>Current Orders Status:</strong><br>";
$check_stmt = $conn->prepare("SELECT id, status, cancelled_at FROM orders WHERE user_id = ? ORDER BY id DESC");
$check_stmt->bind_param("i", $user_id);
$check_stmt->execute();
$result = $check_stmt->get_result();

while ($order = $result->fetch_assoc()) {
    echo "Order #" . $order['id'] . " - Status: " . $order['status'];
    if ($order['cancelled_at']) {
        echo " - Cancelled at: " . $order['cancelled_at'];
        $seconds = strtotime('now') - strtotime($order['cancelled_at']);
        echo " (" . abs($seconds) . " seconds ago)";
    }
    echo "<br>";
}
$check_stmt->close();

echo "<br><a href='orders.php' style='display: inline-block; background: #2d5016; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px;'>📊 View Orders with Countdown</a>";

$conn->close();
?>
