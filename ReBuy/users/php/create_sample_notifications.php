<?php
session_start();
include 'db.php';
include 'notification_functions.php';

// This script creates sample notifications for testing
// Only run this when logged in as a user

if (!isset($_SESSION['user_id'])) {
    echo "Please log in first";
    exit();
}

$user_id = $_SESSION['user_id'];

// Create different types of notifications
$notifications = [
    [
        'title' => '🎉 Flash Sale Alert!',
        'message' => 'Limited time offer: Get 30% off on all dining furniture. Sale ends in 24 hours!',
        'type' => 'promo'
    ],
    [
        'title' => '💬 New Message from Seller',
        'message' => 'Furniture Store: "Your order has been confirmed and will be shipped tomorrow. Thank you for your purchase!"',
        'type' => 'message'
    ],
    [
        'title' => '📦 Order Shipped',
        'message' => 'Your order #12345 has been shipped! Expected delivery: 3-5 business days. Track your package for real-time updates.',
        'type' => 'order'
    ],
    [
        'title' => '❤️ Wishlist Item Back in Stock',
        'message' => 'Great news! The "Modern Office Chair" from your wishlist is now available. Limited stock - order soon!',
        'type' => 'wishlist'
    ],
    [
        'title' => '🏪 New Store Feature',
        'message' => 'Check out our new virtual furniture placement tool! See how items look in your space before buying.',
        'type' => 'system'
    ]
];

echo "<h2>Creating Sample Notifications...</h2>";

foreach ($notifications as $notif) {
    $success = createNotification($user_id, $notif['title'], $notif['message'], $notif['type']);
    if ($success) {
        echo "<p style='color: green;'>✓ Created: {$notif['title']}</p>";
    } else {
        echo "<p style='color: red;'>✗ Failed: {$notif['title']}</p>";
    }
}

echo "<h3>Done! <a href='notification.php'>View Notifications</a></h3>";
?>
