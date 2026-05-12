<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$product_id = $_GET['id'] ?? 0;
$user_id = $_SESSION['user_id'];

// Validate product ID
if (!is_numeric($product_id) || $product_id <= 0) {
    $_SESSION['error'] = "Invalid product ID";
    header("Location: wishlist.php");
    exit();
}

// Get product name for feedback message
$product_check = $conn->prepare("SELECT p.name FROM products p JOIN wishlist w ON p.id = w.product_id WHERE w.product_id = ? AND w.user_id = ?");
$product_check->bind_param("ii", $product_id, $user_id);
$product_check->execute();
$product_result = $product_check->get_result();

if ($product_result->num_rows === 0) {
    $_SESSION['error'] = "Product not found in your wishlist";
    header("Location: wishlist.php");
    exit();
}

$product_name = $product_result->fetch_assoc()['name'];
$product_check->close();

// Remove from wishlist
$stmt = $conn->prepare("DELETE FROM wishlist WHERE product_id = ? AND user_id = ?");
$stmt->bind_param("ii", $product_id, $user_id);

if ($stmt->execute()) {
    $_SESSION['success'] = "Product '{$product_name}' has been removed from your wishlist!";
} else {
    $_SESSION['error'] = "Failed to remove product from wishlist. Please try again.";
}

$stmt->close();
header("Location: wishlist.php");
exit();
?>
