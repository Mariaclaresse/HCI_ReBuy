<?php
require_once 'db.php';

// Sync all orders.status with seller_orders status
echo "Syncing order statuses...\n";

// Get all orders with user_id
$orders_stmt = $conn->query("SELECT id, user_id FROM orders");
$orders = $orders_stmt->fetch_all(MYSQLI_ASSOC);
$orders_stmt->close();

$updated = 0;
foreach ($orders as $order) {
    $order_id = $order['id'];
    $user_id = $order['user_id'];
    
    // Get all seller orders for this user (customer_id in seller_orders = user_id)
    $seller_orders_stmt = $conn->prepare("SELECT status FROM seller_orders WHERE customer_id = ?");
    $seller_orders_stmt->bind_param("i", $user_id);
    $seller_orders_stmt->execute();
    $seller_result = $seller_orders_stmt->get_result();
    
    $has_cancelled = false;
    $has_delivered = false;
    $has_shipped = false;
    $has_processing = false;
    $all_delivered = true;
    $has_any_status = false;
    
    while ($so = $seller_result->fetch_assoc()) {
        $has_any_status = true;
        if ($so['status'] == 'cancelled') {
            $has_cancelled = true;
        }
        if ($so['status'] == 'delivered') {
            $has_delivered = true;
        }
        if ($so['status'] == 'shipped') {
            $has_shipped = true;
        }
        if ($so['status'] == 'processing') {
            $has_processing = true;
        }
        if ($so['status'] != 'delivered') {
            $all_delivered = false;
        }
    }
    $seller_orders_stmt->close();
    
    // Determine overall status
    $overall_status = 'pending';
    if ($has_any_status) {
        if ($has_cancelled) {
            $overall_status = 'cancelled';
        } elseif ($all_delivered) {
            $overall_status = 'delivered';
        } elseif ($has_shipped) {
            $overall_status = 'shipped';
        } elseif ($has_processing) {
            $overall_status = 'processing';
        }
    }
    
    // Update orders.status
    $status_update = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
    $status_update->bind_param("si", $overall_status, $order_id);
    $status_update->execute();
    $status_update->close();
    
    $updated++;
    echo "Order #$order_id updated to: $overall_status\n";
}

echo "\nTotal orders updated: $updated\n";

// Now backfill timestamps based on the synced status
echo "\nBackfilling timestamps...\n";

$orders_stmt = $conn->query("SELECT id, status, order_date, accepted_at, processing_at, shipped_at, delivered_at, cancelled_at FROM orders");
$orders = $orders_stmt->fetch_all(MYSQLI_ASSOC);
$orders_stmt->close();

$backfilled = 0;
foreach ($orders as $order) {
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
            echo "Order #$order_id timestamps backfilled\n";
            $backfilled++;
        } else {
            echo "Error backfilling order #$order_id: " . $conn->error . "\n";
        }
        $stmt->close();
    }
}

echo "\nTotal orders backfilled: $backfilled\n";
echo "Done!\n";
?>
