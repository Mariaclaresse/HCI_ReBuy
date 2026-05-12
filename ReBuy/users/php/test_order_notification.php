<?php
session_start();
include 'db.php';
include 'notification_functions.php';

echo "<h1>Test Order Notification</h1>";

// Check if logged in
if (!isset($_SESSION['user_id'])) {
    echo "<p>⚠ Not logged in. Please log in first.</p>";
    echo "<p><a href='login.php'>Login</a></p>";
    exit();
}

$user_id = $_SESSION['user_id'];
echo "<p>Current user ID: $user_id</p>";

// Get a recent order to test with
echo "<h2>Getting recent orders...</h2>";
$order_query = $conn->query("SELECT id, user_id, status FROM orders ORDER BY id DESC LIMIT 5");

if ($order_query->num_rows > 0) {
    echo "<table border='1'><tr><th>Order ID</th><th>User ID</th><th>Status</th><th>Action</th></tr>";
    while ($order = $order_query->fetch_assoc()) {
        echo "<tr><td>{$order['id']}</td><td>{$order['user_id']}</td><td>{$order['status']}</td>";
        echo "<td><a href='?test_notify={$order['id']}&user={$order['user_id']}'>Test Notification</a></td></tr>";
    }
    echo "</table>";
} else {
    echo "<p>No orders found in database.</p>";
}

// Test notification if requested
if (isset($_GET['test_notify']) && isset($_GET['user'])) {
    $order_id = $_GET['test_notify'];
    $target_user_id = $_GET['user'];
    $status = 'delivered';

    echo "<h2>Testing notification for Order #$order_id to User #$target_user_id</h2>";

    // Enable error reporting
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);

    echo "<p>Attempting to create notification...</p>";

    $result = notifyUserOrderStatus($target_user_id, $order_id, $status);

    if ($result) {
        echo "<p>✓ Notification created successfully!</p>";

        // Verify it was created - search by user_id and type, order_id is in the message
        $check = $conn->query("SELECT * FROM notifications WHERE user_id = $target_user_id AND type = 'order' ORDER BY id DESC LIMIT 1");
        if ($check->num_rows > 0) {
            $notif = $check->fetch_assoc();
            echo "<p>✓ Notification found in database:</p>";
            echo "<ul>";
            echo "<li>ID: {$notif['id']}</li>";
            echo "<li>Title: {$notif['title']}</li>";
            echo "<li>Message: {$notif['message']}</li>";
            echo "<li>Type: {$notif['type']}</li>";
            echo "<li>Is Read: " . ($notif['is_read'] ? 'Yes' : 'No') . "</li>";
            echo "<li>Created At: {$notif['created_at']}</li>";
            echo "</ul>";
        } else {
            echo "<p>✗ Notification not found in database (this is an error!)</p>";
        }
    } else {
        echo "<p>✗ Failed to create notification</p>";
        echo "<p>Checking database connection error: " . $conn->error . "</p>";
    }
}

// Check recent notifications
echo "<h2>Recent notifications in database:</h2>";
$notif_query = $conn->query("SELECT * FROM notifications ORDER BY id DESC LIMIT 10");
if ($notif_query->num_rows > 0) {
    echo "<table border='1'><tr><th>ID</th><th>User ID</th><th>Title</th><th>Type</th><th>Is Read</th><th>Created At</th></tr>";
    while ($notif = $notif_query->fetch_assoc()) {
        echo "<tr><td>{$notif['id']}</td><td>{$notif['user_id']}</td><td>{$notif['title']}</td><td>{$notif['type']}</td><td>" . ($notif['is_read'] ? 'Yes' : 'No') . "</td><td>{$notif['created_at']}</td></tr>";
    }
    echo "</table>";
} else {
    echo "<p>No notifications found.</p>";
}

echo "<p><a href='notification.php'>Go to Notification Page</a></p>";
?>
