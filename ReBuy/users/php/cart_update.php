<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$cart_id = $_POST['cart_id'] ?? 0;
$action = $_POST['action'] ?? '';
$user_id = $_SESSION['user_id'];

// Get current cart item
$stmt = $conn->prepare("SELECT quantity FROM cart WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $cart_id, $user_id);
$stmt->execute();
$item = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($item) {
    $new_quantity = $item['quantity'];
    
    if ($action == 'plus') {
        $new_quantity++;
    } elseif ($action == 'minus' && $new_quantity > 1) {
        $new_quantity--;
    }

    $stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
    $stmt->bind_param("ii", $new_quantity, $cart_id);
    $stmt->execute();
    $stmt->close();
}

header("Location: cart.php");
exit();
?>
