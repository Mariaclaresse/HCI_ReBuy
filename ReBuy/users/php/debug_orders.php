<?php
session_start();
require_once 'db.php';

$user_id = $_SESSION['user_id'] ?? 0;

// Fetch orders
$orders_stmt = $conn->prepare("
    SELECT o.id, o.order_date, o.total_amount, o.status, o.payment_method, o.cancelled_at,
           CASE 
               WHEN o.created_at IS NOT NULL THEN o.created_at 
               ELSE o.order_date 
           END as display_date,
           so.id as seller_order_id, so.status as seller_status, so.order_id as seller_order_ref,
           p.name as product_name, p.image_url as product_image, so.quantity,
           u.first_name as seller_first_name, u.last_name as seller_last_name
    FROM orders o
    LEFT JOIN seller_orders so ON o.id = so.customer_id
    LEFT JOIN products p ON so.product_id = p.id
    LEFT JOIN users u ON so.seller_id = u.id
    WHERE o.user_id = ?
    ORDER BY o.order_date DESC
");

$orders_stmt->bind_param("i", $user_id);
$orders_stmt->execute();
$orders_result = $orders_stmt->get_result();

$user_orders = [];
while ($row = $orders_result->fetch_assoc()) {
    $order_id = $row['id'];
    if (!isset($user_orders[$order_id])) {
        $user_orders[$order_id] = [
            'id' => $row['id'],
            'order_date' => $row['order_date'],
            'display_date' => $row['display_date'],
            'total_amount' => $row['total_amount'],
            'status' => $row['status'],
            'payment_method' => $row['payment_method'],
            'cancelled_at' => $row['cancelled_at'],
            'items' => []
        ];
    }
    if ($row['seller_order_id']) {
        $user_orders[$order_id]['items'][] = [
            'seller_order_id' => $row['seller_order_id'],
            'seller_status' => $row['seller_status'],
            'seller_order_ref' => $row['seller_order_ref'],
            'product_name' => $row['product_name'],
            'product_image' => $row['product_image'],
            'quantity' => $row['quantity'],
            'seller_name' => $row['seller_first_name'] . ' ' . $row['seller_last_name']
        ];
    }
}
$orders_stmt->close();

// Group orders by date
$orders_by_date = [];
foreach ($user_orders as $order) {
    $date_key = date('F j, Y', strtotime($order['display_date']));
    if (!isset($orders_by_date[$date_key])) {
        $orders_by_date[$date_key] = [];
    }
    $orders_by_date[$date_key][] = $order;
}

echo "<h3>Original Orders Array:</h3>";
echo "<pre>";
print_r($user_orders);
echo "</pre>";

echo "<h3>Grouped by Date:</h3>";
echo "<pre>";
print_r($orders_by_date);
echo "</pre>";

echo "<h3>Count of user_orders: " . count($user_orders) . "</h3>";
echo "<h3>Count of orders_by_date: " . count($orders_by_date) . "</h3>";
?>
