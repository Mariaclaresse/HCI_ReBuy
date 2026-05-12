<?php
include 'db.php';

// Get product ID 2 to test
$product_id = 2;

echo "<h2>Checking Product Table Structure</h2>";

// Show table structure
echo "<h3>Table Structure:</h3>";
$result = $conn->query("DESCRIBE products");
if ($result) {
    echo "<table border='1'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['Field'] . "</td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . $row['Key'] . "</td>";
        echo "<td>" . $row['Default'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Show actual product data
echo "<h3>Product ID 2 Data:</h3>";
$stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();

if ($product = $result->fetch_assoc()) {
    echo "<table border='1'>";
    echo "<tr><th>Column</th><th>Value</th></tr>";
    foreach ($product as $key => $value) {
        echo "<tr>";
        echo "<td>" . $key . "</td>";
        echo "<td>" . $value . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "Product not found";
}

$stmt->close();
$conn->close();
?>
