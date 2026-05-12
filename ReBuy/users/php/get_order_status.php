<?php
require_once 'db.php';

header('Content-Type: application/json');

$order_id = $_GET['order_id'] ?? '';

if (empty($order_id)) {
    echo json_encode(['success' => false]);
    exit();
}

$stmt = $conn->prepare("SELECT status FROM seller_orders WHERE id = ?");
$stmt->bind_param("i", $order_id);
$stmt->execute();

$result = $stmt->get_result();
$data = $result->fetch_assoc();

echo json_encode([
    'success' => true,
    'status' => $data['status'] ?? 'pending'
]);

$stmt->close();
$conn->close();
?>