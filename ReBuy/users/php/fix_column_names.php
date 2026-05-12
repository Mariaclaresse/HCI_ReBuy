<?php
require_once 'db.php';

echo "<h2>🔧 Fix Column Name Mismatch</h2>";

// Show current products table structure
echo "<h3>Current Products Table Structure:</h3>";
$result = $conn->query("DESCRIBE products");
if ($result) {
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr><td>" . $row['Field'] . "</td><td>" . $row['Type'] . "</td><td>" . $row['Null'] . "</td><td>" . $row['Key'] . "</td></tr>";
    }
    echo "</table>";
}

// Check if stock_quantity column exists
$stock_qty_check = $conn->query("SHOW COLUMNS FROM products LIKE 'stock_quantity'");
if ($stock_qty_check->num_rows > 0) {
    echo "<p>✅ stock_quantity column exists</p>";
} else {
    echo "<p>❌ stock_quantity column missing</p>";
    
    // Check if stock column exists
    $stock_check = $conn->query("SHOW COLUMNS FROM products LIKE 'stock'");
    if ($stock_check->num_rows > 0) {
        echo "<p>✅ stock column exists - renaming to stock_quantity</p>";
        
        // Rename stock column to stock_quantity
        $rename_sql = "ALTER TABLE products CHANGE COLUMN stock stock_quantity INT DEFAULT 0";
        if ($conn->query($rename_sql)) {
            echo "<p style='color: green;'>✅ Column renamed from 'stock' to 'stock_quantity'</p>";
        } else {
            echo "<p style='color: red;'>❌ Failed to rename column: " . $conn->error . "</p>";
        }
    } else {
        echo "<p>❌ Neither stock nor stock_quantity columns exist - adding stock_quantity</p>";
        
        // Add stock_quantity column
        $add_sql = "ALTER TABLE products ADD COLUMN stock_quantity INT DEFAULT 0 AFTER category";
        if ($conn->query($add_sql)) {
            echo "<p style='color: green;'>✅ stock_quantity column added</p>";
        } else {
            echo "<p style='color: red;'>❌ Failed to add stock_quantity: " . $conn->error . "</p>";
        }
    }
}

// Check for status column
$status_check = $conn->query("SHOW COLUMNS FROM products LIKE 'status'");
if ($status_check->num_rows == 0) {
    echo "<p>❌ status column missing - adding it</p>";
    
    $add_status_sql = "ALTER TABLE products ADD COLUMN status ENUM('active', 'inactive', 'out_of_stock') DEFAULT 'active' AFTER stock_quantity";
    if ($conn->query($add_status_sql)) {
        echo "<p style='color: green;'>✅ status column added</p>";
    } else {
        echo "<p style='color: red;'>❌ Failed to add status: " . $conn->error . "</p>";
    }
} else {
    echo "<p>✅ status column exists</p>";
}

// Check for updated_at column
$updated_check = $conn->query("SHOW COLUMNS FROM products LIKE 'updated_at'");
if ($updated_check->num_rows == 0) {
    echo "<p>❌ updated_at column missing - adding it</p>";
    
    $add_updated_sql = "ALTER TABLE products ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at";
    if ($conn->query($add_updated_sql)) {
        echo "<p style='color: green;'>✅ updated_at column added</p>";
    } else {
        echo "<p style='color: red;'>❌ Failed to add updated_at: " . $conn->error . "</p>";
    }
} else {
    echo "<p>✅ updated_at column exists</p>";
}

// Show final structure
echo "<h3>Final Products Table Structure:</h3>";
$result = $conn->query("DESCRIBE products");
if ($result) {
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr><td>" . $row['Field'] . "</td><td>" . $row['Type'] . "</td><td>" . $row['Null'] . "</td><td>" . $row['Key'] . "</td></tr>";
    }
    echo "</table>";
}

echo "<hr>";
echo "<h2>✅ Column Fix Complete!</h2>";
echo "<p><a href='debug_upload.php'>Test Upload Again</a></p>";
echo "<p><a href='upload_product.php'>Upload Product</a></p>";
?>
