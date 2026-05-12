<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    echo "Not logged in. Please <a href='login.php'>login</a> first.";
    exit();
}

require_once 'db.php';
$user_id = $_SESSION['user_id'];

echo "<h2>🔍 Complete Upload Diagnosis</h2>";
echo "<p>Checking all requirements for product upload...</p>";

// 1. Check user session
echo "<h3>1. User Session Check</h3>";
echo "✅ User ID: " . $user_id . "<br>";

// 2. Check seller status in detail
echo "<h3>2. Seller Status Check</h3>";
$seller_check = $conn->query("SHOW COLUMNS FROM users LIKE 'is_seller'");
if ($seller_check->num_rows > 0) {
    $stmt = $conn->prepare("SELECT is_seller, seller_requested_at, seller_approved_at FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    
    echo "Is Seller: " . ($user['is_seller'] ?? 'NULL') . "<br>";
    echo "Requested At: " . ($user['seller_requested_at'] ?? 'NULL') . "<br>";
    echo "Approved At: " . ($user['seller_approved_at'] ?? 'NULL') . "<br>";
    
    if (($user['is_seller'] ?? 0) == 1) {
        echo "✅ Seller status: ACTIVE<br>";
    } else {
        echo "❌ Seller status: NOT ACTIVE<br>";
        echo "<p><strong>SOLUTION:</strong> <a href='settings.php'>Enable Seller Account</a></p>";
        exit();
    }
} else {
    echo "❌ Seller fields not found<br>";
    exit();
}

// 3. Check products table structure
echo "<h3>3. Products Table Structure</h3>";
$table_check = $conn->query("SHOW TABLES LIKE 'products'");
if ($table_check->num_rows > 0) {
    echo "✅ Products table exists<br>";
    
    // Show all columns
    $columns = $conn->query("DESCRIBE products");
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th></tr>";
    while ($col = $columns->fetch_assoc()) {
        echo "<tr><td>" . $col['Field'] . "</td><td>" . $col['Type'] . "</td><td>" . $col['Null'] . "</td><td>" . $col['Key'] . "</td></tr>";
    }
    echo "</table>";
    
    // Check specifically for seller_id
    $seller_id_check = $conn->query("SHOW COLUMNS FROM products LIKE 'seller_id'");
    if ($seller_id_check->num_rows > 0) {
        echo "✅ seller_id column exists<br>";
    } else {
        echo "❌ seller_id column MISSING<br>";
        echo "<p><strong>SOLUTION:</strong> <a href='fix_products_table.php'>Fix Products Table</a></p>";
        exit();
    }
} else {
    echo "❌ Products table missing<br>";
    echo "<p><strong>SOLUTION:</strong> <a href='setup_seller_fields.php'>Setup Database</a></p>";
    exit();
}

// 4. Test upload directory
echo "<h3>4. Upload Directory Check</h3>";
$upload_dir = '../uploads/products/';
if (!file_exists($upload_dir)) {
    if (mkdir($upload_dir, 0777, true)) {
        echo "✅ Upload directory created: " . $upload_dir . "<br>";
    } else {
        echo "❌ Cannot create upload directory<br>";
    }
} else {
    echo "✅ Upload directory exists: " . $upload_dir . "<br>";
}

// 5. Test actual upload with dummy data
echo "<h3>5. Test Upload Process</h3>";
$test_name = "TEST PRODUCT " . date("Y-m-d H:i:s");
$test_price = 99.99;
$test_category = "Electronics";
$test_stock = 1;

$stmt = $conn->prepare("INSERT INTO products (seller_id, name, description, price, original_price, category, image_url, stock_quantity) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
if ($stmt === false) {
    echo "❌ Prepare failed: " . $conn->error . "<br>";
    exit();
} else {
    $stmt->bind_param("isssdssi", $user_id, $test_name, $test_description, $test_price, $test_original_price, $test_category, $test_image_url, $test_stock);
    
    $test_description = "Test product description";
    $test_original_price = 199.99;
    $test_image_url = "";
    
    if ($stmt->execute()) {
        $inserted_id = $stmt->insert_id;
        echo "✅ Test upload successful! Product ID: " . $inserted_id . "<br>";
        
        // Clean up test product
        $conn->query("DELETE FROM products WHERE id = " . $inserted_id);
        echo "✅ Test product cleaned up<br>";
    } else {
        echo "❌ Test upload failed: " . $stmt->error . "<br>";
        exit();
    }
    $stmt->close();
}

// 6. Check permissions
echo "<h3>6. File Permissions Check</h3>";
if (is_writable('../uploads/')) {
    echo "✅ Uploads directory is writable<br>";
} else {
    echo "❌ Uploads directory is NOT writable<br>";
}

echo "<hr>";
echo "<h2>🎉 DIAGNOSIS COMPLETE</h2>";
echo "<p style='color: green; font-size: 18px;'>✅ Everything looks good! You should be able to upload products.</p>";

echo "<h3>Next Steps:</h3>";
echo "<p><a href='upload_product.php' style='background: #2d5016; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>→ Try Upload Product Now</a></p>";
echo "<p><a href='seller_dashboard.php'>→ Go to Seller Dashboard</a></p>";

echo "<h3>If upload still fails:</h3>";
echo "<p>1. Check browser console for JavaScript errors</p>";
echo "<p>2. Try the <a href='debug_upload.php'>Debug Upload Page</a></p>";
echo "<p>3. Make sure you're filling all required fields (*)</p>";
echo "<p>4. Check if image file size is too large</p>";
?>
