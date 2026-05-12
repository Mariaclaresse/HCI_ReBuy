<?php
require_once 'db.php';

echo "<h2>🔧 Quick Fix: Add seller_id Column</h2>";

// Check if products table exists
echo "<h3>Checking Products Table:</h3>";
$table_check = $conn->query("SHOW TABLES LIKE 'products'");
if ($table_check->num_rows == 0) {
    echo "❌ Products table doesn't exist. Creating it first...<br>";
    
    // Create products table with seller_id
    $create_sql = "
    CREATE TABLE products (
        id INT AUTO_INCREMENT PRIMARY KEY,
        seller_id INT NOT NULL DEFAULT 1,
        name VARCHAR(255) NOT NULL,
        description TEXT,
        price DECIMAL(10,2) NOT NULL,
        original_price DECIMAL(10,2),
        category VARCHAR(100),
        image_url VARCHAR(500),
        stock_quantity INT DEFAULT 0,
        status ENUM('active', 'inactive', 'out_of_stock') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_seller_id (seller_id),
        INDEX idx_category (category),
        INDEX idx_status (status)
    )";
    
    if ($conn->query($create_sql)) {
        echo "✅ Products table created with seller_id column!<br>";
    } else {
        echo "❌ Failed to create products table: " . $conn->error . "<br>";
        exit();
    }
} else {
    echo "✅ Products table exists<br>";
    
    // Check if seller_id column exists
    $column_check = $conn->query("SHOW COLUMNS FROM products LIKE 'seller_id'");
    if ($column_check->num_rows == 0) {
        echo "❌ seller_id column missing. Adding it...<br>";
        
        // Add seller_id column
        $alter_sql = "ALTER TABLE products ADD COLUMN seller_id INT NOT NULL DEFAULT 1 AFTER id";
        
        if ($conn->query($alter_sql)) {
            echo "✅ seller_id column added successfully!<br>";
            
            // Add index
            $index_sql = "ALTER TABLE products ADD INDEX idx_seller_id (seller_id)";
            if ($conn->query($index_sql)) {
                echo "✅ Index added for seller_id<br>";
            }
        } else {
            echo "❌ Failed to add seller_id: " . $conn->error . "<br>";
        }
    } else {
        echo "✅ seller_id column already exists<br>";
    }
}

// Show final table structure
echo "<h3>Final Products Table Structure:</h3>";
$result = $conn->query("DESCRIBE products");
if ($result) {
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr><td>" . $row['Field'] . "</td><td>" . $row['Type'] . "</td><td>" . $row['Null'] . "</td><td>" . $row['Key'] . "</td></tr>";
    }
    echo "</table>";
}

echo "<hr>";
echo "<h2>✅ Fix Complete!</h2>";
echo "<p><a href='debug_upload.php'>Test Upload Again</a></p>";
echo "<p><a href='upload_product.php'>Upload Product</a></p>";
?>
