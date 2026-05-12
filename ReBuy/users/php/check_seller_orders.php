<?php
require_once 'db.php';

// Check seller_orders table status
echo "Checking seller_orders table status...\n\n";

$seller_orders = $conn->query("SELECT id, order_id, seller_id, customer_id, product_id, status, order_date FROM seller_orders ORDER BY id DESC LIMIT 10");

if ($seller_orders->num_rows > 0) {
    echo "ID | Order ID | Seller ID | Customer ID | Product ID | Status | Order Date\n";
    echo "------------------------------------------------------------\n";
    while ($row = $seller_orders->fetch_assoc()) {
        echo $row['id'] . " | " . $row['order_id'] . " | " . $row['seller_id'] . " | " . $row['customer_id'] . " | " . $row['product_id'] . " | " . $row['status'] . " | " . $row['order_date'] . "\n";
    }
} else {
    echo "No seller orders found.\n";
}

echo "\n\nChecking orders table status...\n\n";

$orders = $conn->query("SELECT id, user_id, status, order_date FROM orders ORDER BY id DESC LIMIT 10");

if ($orders->num_rows > 0) {
    echo "ID | User ID | Status | Order Date\n";
    echo "--------------------------------\n";
    while ($row = $orders->fetch_assoc()) {
        echo $row['id'] . " | " . $row['user_id'] . " | " . $row['status'] . " | " . $row['order_date'] . "\n";
    }
} else {
    echo "No orders found.\n";
}

echo "\nDone!\n";
?>
