<?php
require_once 'db.php';

echo "<h2>Fixing Products Table...</h2>";

// Check current products table structure
echo "<h3>Current Products Table Structure:</h3>";
$result = $conn->query("DESCRIBE products");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo "- " . $row['Field'] . " (" . $row['Type'] . ")<br>";
    }
}

// Check if seller_id column exists
$seller_id_check = $conn->query("SHOW COLUMNS FROM products LIKE 'seller_id'");
if ($seller_id_check->num_rows == 0) {
    echo "<h3>Adding seller_id column...</h3>";
    
    // Add seller_id column
    $alter_sql = "ALTER TABLE products ADD COLUMN seller_id INT NOT NULL DEFAULT 1 AFTER id";
    
    if ($conn->query($alter_sql)) {
        echo "<p style='color: green;'>✅ seller_id column added successfully!</p>";
        
        // Add foreign key constraint
        echo "<h3>Adding foreign key constraint...</h3>";
        $fk_sql = "ALTER TABLE products ADD CONSTRAINT fk_products_seller FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE";
        
        if ($conn->query($fk_sql)) {
            echo "<p style='color: green;'>✅ Foreign key constraint added!</p>";
        } else {
            echo "<p style='color: orange;'>⚠ Foreign key constraint failed (may already exist): " . $conn->error . "</p>";
        }
        
        // Add index for seller_id
        echo "<h3>Adding index for seller_id...</h3>";
        $index_sql = "ALTER TABLE products ADD INDEX idx_seller_id (seller_id)";
        
        if ($conn->query($index_sql)) {
            echo "<p style='color: green;'>✅ Index added for seller_id!</p>";
        } else {
            echo "<p style='color: orange;'>⚠ Index creation failed (may already exist): " . $conn->error . "</p>";
        }
        
    } else {
        echo "<p style='color: red;'>❌ Failed to add seller_id column: " . $conn->error . "</p>";
    }
} else {
    echo "<p style='color: blue;'>ℹ seller_id column already exists.</p>";
}

// Show final structure
echo "<h3>Final Products Table Structure:</h3>";
$result = $conn->query("DESCRIBE products");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo "- " . $row['Field'] . " (" . $row['Type'] . ")<br>";
    }
}

echo "<h3>✅ Fix Complete!</h3>";
echo "<p><a href='debug_upload.php'>Try Upload Again</a></p>";
echo "<p><a href='upload_product.php'>Go to Upload Product Page</a></p>";
echo "<p><a href='seller_dashboard.php'>Go to Seller Dashboard</a></p>";
?>
