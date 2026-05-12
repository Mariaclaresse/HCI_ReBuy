<?php
include 'db.php';

// Check if cancelled_at column already exists
$column_check = $conn->query("SHOW COLUMNS FROM orders LIKE 'cancelled_at'");
if ($column_check->num_rows > 0) {
    echo "✓ cancelled_at column already exists\n";
} else {
    // Add cancelled_at timestamp column to orders table
    $sql = "ALTER TABLE `orders` ADD COLUMN `cancelled_at` TIMESTAMP NULL DEFAULT NULL AFTER `status`";
    
    if ($conn->query($sql) === TRUE) {
        echo "✓ Column cancelled_at added successfully\n";
    } else {
        echo "✗ Error adding cancelled_at column: " . $conn->error . "\n";
    }
}

// Check if index already exists
$index_check = $conn->query("SHOW INDEX FROM orders WHERE Key_name = 'idx_cancelled_at'");
if ($index_check->num_rows > 0) {
    echo "✓ idx_cancelled_at index already exists\n";
} else {
    // Add index for better performance on cancelled_at queries
    $sql = "ALTER TABLE `orders` ADD INDEX `idx_cancelled_at` (`cancelled_at`)";
    
    if ($conn->query($sql) === TRUE) {
        echo "✓ Index idx_cancelled_at added successfully\n";
    } else {
        echo "✗ Error adding index: " . $conn->error . "\n";
    }
}

echo "\nDatabase setup complete! The cancel order functionality is now ready.\n";
echo "You can test it at: test_cancel_orders.php\n";

$conn->close();
?>
