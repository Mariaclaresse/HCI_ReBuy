<?php
session_start();
include 'db.php';

header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Simulate adding to cart
$product_id = 2; // Using product ID 2 from the URL
$quantity = 1;
$user_id = $_SESSION['user_id'] ?? null;

$response = [
    'session_active' => session_status() === PHP_SESSION_ACTIVE,
    'user_id' => $user_id,
    'logged_in' => !empty($user_id),
    'product_id' => $product_id,
    'quantity' => $quantity,
    'db_connected' => $conn ? true : false,
    'cart_table_exists' => false,
    'test_result' => 'not_run'
];

if ($conn) {
    // Check if cart table exists
    $result = $conn->query("SHOW TABLES LIKE 'cart'");
    $response['cart_table_exists'] = $result->num_rows > 0;
    
    if ($response['cart_table_exists'] && $user_id) {
        try {
            // Test adding to cart
            $stmt = $conn->prepare("SELECT stock_quantity FROM products WHERE id = ? AND status = 'active'");
            $stmt->bind_param("i", $product_id);
            $stmt->execute();
            $product = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if ($product) {
                $response['product_found'] = true;
                $response['stock'] = $product['stock_quantity'];
                
                // Check if already in cart
                $stmt = $conn->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ?");
                $stmt->bind_param("ii", $user_id, $product_id);
                $stmt->execute();
                $existing = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                
                $response['existing_cart_item'] = $existing ? true : false;
                
                if ($existing) {
                    $response['test_result'] = 'would_update_existing';
                } else {
                    $response['test_result'] = 'would_insert_new';
                }
            } else {
                $response['product_found'] = false;
                $response['test_result'] = 'product_not_found';
            }
        } catch (Exception $e) {
            $response['test_result'] = 'error: ' . $e->getMessage();
        }
    } elseif (!$user_id) {
        $response['test_result'] = 'user_not_logged_in';
    } else {
        $response['test_result'] = 'cart_table_missing';
    }
}

echo json_encode($response, JSON_PRETTY_PRINT);
?>
