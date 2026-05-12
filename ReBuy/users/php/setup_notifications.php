<?php
include 'db.php';

// Create notifications table
$sql = "CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('promo', 'message', 'order', 'system', 'wishlist') DEFAULT 'system',
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";

if ($conn->query($sql) === TRUE) {
    echo "Table notifications created successfully<br>";
} else {
    echo "Error creating table: " . $conn->error . "<br>";
}

// Insert sample notifications for testing
$sample_notifications = [
    [
        'user_id' => 1,
        'title' => '🎉 Summer Sale!',
        'message' => 'Get up to 50% off on selected furniture items. Limited time offer!',
        'type' => 'promo'
    ],
    [
        'user_id' => 1,
        'title' => '💬 New Message',
        'message' => 'You have a new message from Furniture Store about your recent inquiry.',
        'type' => 'message'
    ],
    [
        'user_id' => 1,
        'title' => '📦 Order Update',
        'message' => 'Your order #12345 has been shipped and will arrive in 2-3 business days.',
        'type' => 'order'
    ],
    [
        'user_id' => 1,
        'title' => '❤️ Wishlist Item Available',
        'message' => 'An item in your wishlist is now back in stock!',
        'type' => 'wishlist'
    ]
];

foreach ($sample_notifications as $notif) {
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $notif['user_id'], $notif['title'], $notif['message'], $notif['type']);
    $stmt->execute();
    $stmt->close();
}

echo "Sample notifications inserted successfully<br>";
$conn->close();
echo "Setup complete!";
?>
