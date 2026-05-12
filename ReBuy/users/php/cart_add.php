<?php
session_start();
include 'db.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['user_id'])) {
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
        header('HTTP/1.1 401 Unauthorized');
        echo json_encode(['error' => 'Please login to add items to cart', 'debug' => 'User not logged in']);
        exit();
    } else {
        header("Location: login.php");
        exit();
    }
}

$product_id = $_POST['product_id'] ?? $_GET['id'] ?? 0;
$quantity = $_POST['quantity'] ?? 1;
$user_id = $_SESSION['user_id'];
$is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest';

// Debug logging
error_log("cart_add.php: product_id=$product_id, quantity=$quantity, user_id=$user_id, is_ajax=" . ($is_ajax ? 'true' : 'false'));

if ($product_id <= 0) {
    error_log("cart_add.php: Invalid product_id: $product_id");
    if ($is_ajax) {
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(['error' => 'Invalid product', 'debug' => "product_id=$product_id"]);
        exit();
    } else {
        header("Location: shop.php");
        exit();
    }
}

// Check if product exists and has sufficient stock
$stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
$stmt->close();

error_log("cart_add.php: Product check - found: " . ($product ? 'yes' : 'no') . ", stock: " . ($product['stock'] ?? 'N/A'));

$available_stock = isset($product['stock']) ? $product['stock'] : 999; // Default to high stock if column doesn't exist

// Allow adding to cart even if stock is 0 (data might be incomplete)
if ($available_stock == 0) {
    $available_stock = 999; // Allow unlimited if stock is 0
    error_log("cart_add.php: Stock is 0, allowing unlimited purchases");
}

if (!$product || $quantity > $available_stock) {
    $error_msg = !$product ? 'Product not found' : 'Insufficient stock';
    error_log("cart_add.php: Error - $error_msg");
    if ($is_ajax) {
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(['error' => $error_msg, 'debug' => "quantity=$quantity, available=$available_stock"]);
        exit();
    } else {
        $_SESSION['error'] = "Invalid quantity or product not available";
        header("Location: shop.php");
        exit();
    }
}

// Check if already in cart
$stmt = $conn->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ?");
$stmt->bind_param("ii", $user_id, $product_id);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();
$stmt->close();

error_log("cart_add.php: Existing cart item: " . ($existing ? 'yes' : 'no'));

if ($existing) {
    // Update quantity
    $new_quantity = $existing['quantity'] + $quantity;
    error_log("cart_add.php: Updating quantity from {$existing['quantity']} to $new_quantity");
    
    // Check if new quantity exceeds stock
    if ($new_quantity > $available_stock) {
        error_log("cart_add.php: New quantity $new_quantity exceeds stock $available_stock");
        if ($is_ajax) {
            header('HTTP/1.1 400 Bad Request');
            echo json_encode(['error' => 'Insufficient stock available', 'debug' => "new_quantity=$new_quantity, stock=$available_stock"]);
            exit();
        } else {
            $_SESSION['error'] = "Insufficient stock available";
            header("Location: shop.php");
            exit();
        }
    }
    
    $stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
    $stmt->bind_param("ii", $new_quantity, $existing['id']);
    $stmt->execute();
    $stmt->close();
    error_log("cart_add.php: Cart updated successfully");
} else {
    // Add to cart
    error_log("cart_add.php: Adding new item to cart");
    $stmt = $conn->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)");
    $stmt->bind_param("iii", $user_id, $product_id, $quantity);
    $stmt->execute();
    $stmt->close();
    error_log("cart_add.php: New item added to cart successfully");
}

if ($is_ajax) {
    // Get updated cart count
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM cart WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $cart_count = $row['count'];
    $stmt->close();
    
    error_log("cart_add.php: Returning success response with cart_count=$cart_count");
    
    echo json_encode([
        'success' => true,
        'message' => 'Product added to cart',
        'cart_count' => $cart_count,
        'debug' => 'Operation completed successfully'
    ]);
} else {
    // Set success message and redirect back to referring page or wishlist
    $_SESSION['success'] = "Product successfully added to cart!";
    
    // Check if there's a referring page
    $referrer = $_SERVER['HTTP_REFERER'] ?? '';
    if (strpos($referrer, 'wishlist.php') !== false) {
        header("Location: wishlist.php");
    } else {
        header("Location: cart.php");
    }
}
exit();
?>
