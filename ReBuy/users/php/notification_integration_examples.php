<?php
// Example integrations for automatic notifications
// Include this file in relevant pages to trigger notifications

include 'notification_functions.php';

// Example 1: When order status changes
function updateOrderStatus($order_id, $user_id, $new_status) {
    // Update order in database
    // ... your existing order update code ...
    
    // Send notification
    notifyOrderStatusChange($user_id, $order_id, $new_status);
}

// Example 2: General bidirectional messaging
function sendMessageBetweenUsers($from_user_id, $to_user_id, $message) {
    // Get sender and receiver names
    global $conn;
    
    // Get sender name
    $stmt = $conn->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
    $stmt->bind_param("i", $from_user_id);
    $stmt->execute();
    $sender = $stmt->get_result()->fetch_assoc();
    $sender_name = $sender['first_name'] . ' ' . $sender['last_name'];
    
    // Get receiver name
    $stmt = $conn->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
    $stmt->bind_param("i", $to_user_id);
    $stmt->execute();
    $receiver = $stmt->get_result()->fetch_assoc();
    $receiver_name = $receiver['first_name'] . ' ' . $receiver['last_name'];
    
    // Insert message
    // ... your existing message insertion code ...
    
    // Send notification to receiver (new message received)
    notifyNewMessage($to_user_id, $from_user_id, $sender_name, $message);
    
    // Send notification to sender (message sent confirmation)
    notifyMessageSent($from_user_id, $receiver_name, $message);
}

// Example 2b: When seller sends message (specific version)
function sendMessageFromSeller($from_user_id, $to_user_id, $message) {
    sendMessageBetweenUsers($from_user_id, $to_user_id, $message);
}

// Example 3: When creating a promo/sale
function createPromoNotification($promo_title, $promo_description, $target_users = null) {
    global $conn;
    
    if ($target_users === null) {
        // Send to all users
        $stmt = $conn->prepare("SELECT id FROM users WHERE is_seller = 0 OR is_seller IS NULL");
        $stmt->execute();
        $users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        foreach ($users as $user) {
            notifyPromo($user['id'], $promo_title, $promo_description);
        }
    } else {
        // Send to specific users
        foreach ($target_users as $user_id) {
            notifyPromo($user_id, $promo_title, $promo_description);
        }
    }
}

// Example 4: When wishlist item becomes available
function notifyWishlistUsers($product_id) {
    global $conn;
    
    // Get product info
    $stmt = $conn->prepare("SELECT name FROM products WHERE id = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $product = $stmt->get_result()->fetch_assoc();
    
    // Get users who have this product in wishlist
    $stmt = $conn->prepare("SELECT DISTINCT user_id FROM wishlist WHERE product_id = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    foreach ($users as $user) {
        notifyWishlistItemAvailable($user['user_id'], $product['name']);
    }
}

// Example 5: System notifications
function sendSystemNotification($title, $message, $target_users = null) {
    global $conn;
    
    if ($target_users === null) {
        // Send to all users
        $stmt = $conn->prepare("SELECT id FROM users");
        $stmt->execute();
        $users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        foreach ($users as $user) {
            createNotification($user['id'], $title, $message, 'system');
        }
    } else {
        // Send to specific users
        foreach ($target_users as $user_id) {
            createNotification($user_id, $title, $message, 'system');
        }
    }
}

// Example usage in your existing code:

/*
// In your order processing file:
updateOrderStatus(12345, $_SESSION['user_id'], 'Shipped');

// In your messaging system:
sendMessageFromSeller($seller_id, $customer_id, 'Your order is ready for pickup!');

// When creating a new promo:
createPromoNotification('Weekend Special!', 'Get 25% off all living room furniture this weekend only!');

// When updating product stock:
if ($new_stock > 0 && $old_stock == 0) {
    notifyWishlistUsers($product_id);
}

// For system announcements:
sendSystemNotification('Maintenance Notice', 'Our system will be under maintenance tonight from 2AM to 4AM.');
*/

?>
