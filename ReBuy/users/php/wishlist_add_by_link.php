<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['product_link'])) {
    $product_link = trim($_POST['product_link']);
    
    // Validate URL
    if (!filter_var($product_link, FILTER_VALIDATE_URL)) {
        $_SESSION['error'] = "Please enter a valid URL";
        header("Location: wishlist.php");
        exit();
    }
    
    // Extract product ID from URL (assuming format like .../product.php?id=123 or .../product/123)
    $product_id = null;
    
    // Try to extract ID from different URL patterns
    if (preg_match('/product\.php\?id=(\d+)/', $product_link, $matches)) {
        $product_id = $matches[1];
    } elseif (preg_match('/\/product\/(\d+)/', $product_link, $matches)) {
        $product_id = $matches[1];
    } elseif (preg_match('/id=(\d+)/', $product_link, $matches)) {
        $product_id = $matches[1];
    }
    
    if (!$product_id) {
        $_SESSION['error'] = "Could not extract product ID from the URL. Please check the link format.";
        header("Location: wishlist.php");
        exit();
    }
    
    // Check if product exists
    $product_check = $conn->prepare("SELECT id, name FROM products WHERE id = ?");
    $product_check->bind_param("i", $product_id);
    $product_check->execute();
    $product_result = $product_check->get_result();
    
    if ($product_result->num_rows === 0) {
        $_SESSION['error'] = "Product not found. Please verify the product link.";
        header("Location: wishlist.php");
        exit();
    }
    
    $product = $product_result->fetch_assoc();
    $product_check->close();
    
    // Check if product is already in wishlist
    $wishlist_check = $conn->prepare("SELECT id FROM wishlist WHERE user_id = ? AND product_id = ?");
    $wishlist_check->bind_param("ii", $user_id, $product_id);
    $wishlist_check->execute();
    $wishlist_result = $wishlist_check->get_result();
    
    if ($wishlist_result->num_rows > 0) {
        $_SESSION['error'] = "Product '{$product['name']}' is already in your wishlist!";
        header("Location: wishlist.php");
        exit();
    }
    
    $wishlist_check->close();
    
    // Add to wishlist
    $add_stmt = $conn->prepare("INSERT INTO wishlist (user_id, product_id) VALUES (?, ?)");
    $add_stmt->bind_param("ii", $user_id, $product_id);
    
    if ($add_stmt->execute()) {
        $_SESSION['success'] = "Product '{$product['name']}' has been added to your wishlist!";
    } else {
        $_SESSION['error'] = "Failed to add product to wishlist. Please try again.";
    }
    
    $add_stmt->close();
    header("Location: wishlist.php");
    exit();
} else {
    // If not POST request, redirect to wishlist
    header("Location: wishlist.php");
    exit();
}
?>
