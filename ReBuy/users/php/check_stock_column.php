<?php
session_start();
include 'db.php';

echo "<h2>Check Stock Column</h2>";

// Check if stock column exists in products table
$result = $conn->query("SHOW COLUMNS FROM products LIKE 'stock'");

if ($result) {
    if ($result->num_rows > 0) {
        echo "✓ Stock column exists in products table<br>";
        
        $column_info = $result->fetch_assoc();
        echo "Column details:<br>";
        echo "- Field: " . $column_info['Field'] . "<br>";
        echo "- Type: " . $column_info['Type'] . "<br>";
        echo "- Null: " . $column_info['Null'] . "<br>";
        echo "- Key: " . $column_info['Key'] . "<br>";
        echo "- Default: " . $column_info['Default'] . "<br>";
        echo "- Extra: " . $column_info['Extra'] . "<br>";
        
        // Try to select stock from products
        echo "<br>Testing stock query:<br>";
        $test_result = $conn->query("SELECT id, name, stock FROM products LIMIT 3");
        
        if ($test_result) {
            echo "✓ Stock query successful<br>";
            echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
            echo "<tr><th>ID</th><th>Name</th><th>Stock</th></tr>";
            
            while ($row = $test_result->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . $row['id'] . "</td>";
                echo "<td>" . htmlspecialchars($row['name'] ?? 'N/A') . "</td>";
                echo "<td>" . ($row['stock'] ?? 'NULL') . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "✗ Stock query failed: " . $conn->error . "<br>";
        }
        
    } else {
        echo "✗ Stock column does NOT exist in products table<br>";
        
        // Show all columns in products table
        echo "<br>All columns in products table:<br>";
        $all_columns = $conn->query("SHOW COLUMNS FROM products");
        
        if ($all_columns) {
            echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
            echo "<tr><th>Column Name</th><th>Type</th></tr>";
            
            while ($col = $all_columns->fetch_assoc()) {
                echo "<tr>";
                echo "<td><strong>" . $col['Field'] . "</strong></td>";
                echo "<td>" . $col['Type'] . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    }
} else {
    echo "Error checking columns: " . $conn->error . "<br>";
}

$conn->close();
?>
