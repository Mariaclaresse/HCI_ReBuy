<?php
session_start();
include 'db.php';
include 'notification_functions.php';

echo "<h2>Notification System Test</h2>";

if (!isset($_SESSION['user_id'])) {
    echo "<p style='color: red;'>Please log in first to test notifications.</p>";
    echo "<p><a href='login.php'>Go to Login</a></p>";
    exit();
}

$user_id = $_SESSION['user_id'];
echo "<p>Testing for User ID: $user_id</p>";

// Test 1: Create a test notification
echo "<h3>Test 1: Creating Test Notification</h3>";
$result = createNotification($user_id, '🧪 Test Notification', 'This is a test notification from the test script.', 'system');
if ($result) {
    echo "<p style='color: green;'>✓ Test notification created successfully</p>";
} else {
    echo "<p style='color: red;'>✗ Failed to create test notification</p>";
}

// Test 2: Get notification count
echo "<h3>Test 2: Getting Notification Count</h3>";
$count = getUnreadNotificationCount($user_id);
echo "<p>Unread notifications count: $count</p>";

// Test 3: Get notifications
echo "<h3>Test 3: Getting Notifications</h3>";
$notifications = getUserNotifications($user_id, 5);
if (!empty($notifications)) {
    echo "<p>Found " . count($notifications) . " notifications:</p>";
    echo "<ul>";
    foreach ($notifications as $notif) {
        $status = $notif['is_read'] ? 'Read' : 'Unread';
        echo "<li><strong>{$notif['title']}</strong> - {$notif['message']} (Status: $status, Type: {$notif['type']})</li>";
    }
    echo "</ul>";
} else {
    echo "<p>No notifications found</p>";
}

// Test 4: Mark notification as read
echo "<h3>Test 4: Marking Notification as Read</h3>";
if (!empty($notifications)) {
    $first_notif_id = $notifications[0]['id'];
    $result = markNotificationAsRead($first_notif_id, $user_id);
    if ($result) {
        echo "<p style='color: green;'>✓ Notification marked as read</p>";
    } else {
        echo "<p style='color: red;'>✗ Failed to mark notification as read</p>";
    }
} else {
    echo "<p>No notifications to mark as read</p>";
}

// Test 5: Check updated count
echo "<h3>Test 5: Updated Notification Count</h3>";
$new_count = getUnreadNotificationCount($user_id);
echo "<p>Updated unread notifications count: $new_count</p>";

echo "<hr>";
echo "<h3>Navigation Links</h3>";
echo "<p><a href='dashboard.php'>← Back to Dashboard</a></p>";
echo "<p><a href='notification.php'>View All Notifications</a></p>";

// Create multiple test notifications
echo "<h3>Creating Sample Notifications</h3>";
$sample_notifications = [
    [
        'title' => '🎉 Promo Alert!',
        'message' => 'Special offer: Get 25% off on selected items!',
        'type' => 'promo'
    ],
    [
        'title' => '💬 New Message',
        'message' => 'You have received a new message from customer support.',
        'type' => 'message',
        'sender_id' => 2,
        'redirect_url' => 'message.php?sender_id=2'
    ],
    [
        'title' => '📦 Order Update',
        'message' => 'Your order has been shipped and is on its way!',
        'type' => 'order'
    ],
    [
        'title' => '❤️ Wishlist Alert',
        'message' => 'An item in your wishlist is now available!',
        'type' => 'wishlist'
    ]
];

foreach ($sample_notifications as $notif) {
    $sender_id = $notif['sender_id'] ?? null;
    $redirect_url = $notif['redirect_url'] ?? null;
    $result = createNotification($user_id, $notif['title'], $notif['message'], $notif['type'], $sender_id, $redirect_url);
    if ($result) {
        echo "<p style='color: green;'>✓ Created: {$notif['title']}</p>";
        if ($notif['type'] == 'message') {
            echo "<p style='color: blue;'>→ This message notification is clickable and will redirect to message page</p>";
        }
    } else {
        echo "<p style='color: red;'>✗ Failed to create: {$notif['title']}</p>";
    }
}

// Test the updated notifyNewMessage function
echo "<h3>Testing Updated Message Notification Function</h3>";
$message_result = notifyNewMessage($user_id, 3, 'John Seller', 'Hi! I wanted to let you know that your order is ready for pickup.');
if ($message_result) {
    echo "<p style='color: green;'>✓ Created clickable message notification from John Seller</p>";
} else {
    echo "<p style='color: red;'>✗ Failed to create message notification</p>";
}

// Test bidirectional messaging
echo "<h3>Testing Bidirectional Message Notifications</h3>";

// Simulate user sending message to seller
$receiver_id = 2; // Assuming seller ID is 2
$receiver_name = 'Maria Store';
$message_content = 'Hi! Is this item still available?';

// Create notification for receiver (new message)
$to_receiver = notifyNewMessage($receiver_id, $user_id, 'You', $message_content);
if ($to_receiver) {
    echo "<p style='color: green;'>✓ Created notification for receiver: {$receiver_name}</p>";
}

// Create notification for sender (message sent)
$to_sender = notifyMessageSent($user_id, $receiver_name, $message_content);
if ($to_sender) {
    echo "<p style='color: blue;'>✓ Created notification for sender (you): Message sent confirmation</p>";
}

echo "<p style='color: #666;'><i>Note: Both sender and receiver now get notifications when a message is sent!</i></p>";

echo "<p><strong>Test complete!</strong> Check your notification bell on the dashboard.</p>";
?>
