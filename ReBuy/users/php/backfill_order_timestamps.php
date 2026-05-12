<?php
require_once 'db.php';

echo "<h2>Backfilling Order Timestamps</h2>";

// Get all orders with status but missing timestamps
$orders = $conn->query("
    SELECT id, status, order_date, accepted_at, processing_at, shipped_at, delivered_at, cancelled_at
    FROM orders
    WHERE status IN ('pending', 'processing', 'shipped', 'delivered', 'cancelled')
");

$count = 0;
while ($order = $orders->fetch_assoc()) {
    $order_id = $order['id'];
    $status = $order['status'];
    
    $updates = [];
    $params = [];
    $types = '';
    
    // If status is processing or higher and accepted_at is NULL, set it to order_date
    if (in_array($status, ['processing', 'shipped', 'delivered']) && $order['accepted_at'] === NULL) {
        $updates[] = "accepted_at = ?";
        $params[] = $order['order_date'];
        $types .= 's';
    }
    
    // If status is shipped or delivered and processing_at is NULL, set it to accepted_at or order_date
    if (in_array($status, ['shipped', 'delivered']) && $order['processing_at'] === NULL) {
        $processing_date = $order['accepted_at'] ?? $order['order_date'];
        $updates[] = "processing_at = ?";
        $params[] = $processing_date;
        $types .= 's';
    }
    
    // If status is delivered and shipped_at is NULL, set it to processing_at or accepted_at or order_date
    if ($status == 'delivered' && $order['shipped_at'] === NULL) {
        $shipped_date = $order['processing_at'] ?? $order['accepted_at'] ?? $order['order_date'];
        $updates[] = "shipped_at = ?";
        $params[] = $shipped_date;
        $types .= 's';
    }
    
    // If status is delivered and delivered_at is NULL, set it to shipped_at or processing_at or accepted_at or order_date
    if ($status == 'delivered' && $order['delivered_at'] === NULL) {
        $delivered_date = $order['shipped_at'] ?? $order['processing_at'] ?? $order['accepted_at'] ?? $order['order_date'];
        $updates[] = "delivered_at = ?";
        $params[] = $delivered_date;
        $types .= 's';
    }
    
    // If status is cancelled and cancelled_at is NULL, set it to order_date
    if ($status == 'cancelled' && $order['cancelled_at'] === NULL) {
        $updates[] = "cancelled_at = ?";
        $params[] = $order['order_date'];
        $types .= 's';
    }
    
    if (!empty($updates)) {
        $params[] = $order_id;
        $types .= 'i';
        
        $sql = "UPDATE orders SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        
        if ($stmt->execute()) {
            echo "Updated order ID {$order_id} (status: {$status})<br>";
            $count++;
        } else {
            echo "Error updating order ID {$order_id}: " . $conn->error . "<br>";
        }
        $stmt->close();
    }
}

echo "<h3>Total orders updated: {$count}</h3>";
echo "<p><a href='settings.php'>Back to Settings</a></p>";
