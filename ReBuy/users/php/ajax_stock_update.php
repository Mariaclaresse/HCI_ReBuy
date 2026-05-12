<?php
session_start();
require_once 'db.php';

// Check if user is logged in and is a seller
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];

// Check if user is a seller
$seller_check = $conn->query("SHOW COLUMNS FROM users LIKE 'is_seller'");
if ($seller_check->num_rows > 0) {
    $stmt = $conn->prepare("SELECT is_seller FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    
    if ($user['is_seller'] != 1) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Not a seller']);
        exit();
    }
}

// Handle POST request for stock update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_stock') {
        $product_id = $_POST['product_id'] ?? 0;
        $stock_add = $_POST['stock_add'] ?? 0;
        
        if (!is_numeric($product_id) || !is_numeric($stock_add) || $stock_add <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid input']);
            exit();
        }
        
        // Check if product belongs to seller
        $check_stmt = $conn->prepare("SELECT id, stock_quantity FROM products WHERE id = ? AND seller_id = ?");
        $check_stmt->bind_param("ii", $product_id, $user_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $product = $check_result->fetch_assoc();
            $old_stock = $product['stock_quantity'];
            
            // Update stock
            $update_stmt = $conn->prepare("UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ? AND seller_id = ?");
            $update_stmt->bind_param("iii", $stock_add, $product_id, $user_id);
            
            if ($update_stmt->execute()) {
                // Get new stock quantity
                $new_stock = $old_stock + $stock_add;
                
                echo json_encode([
                    'success' => true, 
                    'message' => 'Stock updated successfully!',
                    'old_stock' => $old_stock,
                    'new_stock' => $new_stock,
                    'added_stock' => $stock_add
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update stock']);
            }
            $update_stmt->close();
        } else {
            echo json_encode(['success' => false, 'message' => 'Product not found or not owned by seller']);
        }
        $check_stmt->close();
    }
}

// Handle GET request for current stock
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_stock') {
    $product_id = $_GET['product_id'] ?? 0;
    
    if (!is_numeric($product_id)) {
        echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
        exit();
    }
    
    // Get current stock
    $stmt = $conn->prepare("SELECT stock_quantity FROM products WHERE id = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $product = $result->fetch_assoc();
        echo json_encode([
            'success' => true,
            'stock_quantity' => $product['stock_quantity']
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Product not found']);
    }
    $stmt->close();
}
?>
