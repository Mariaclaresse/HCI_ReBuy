<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    echo "Not logged in. Please <a href='login.php'>login</a> first.";
    exit();
}

require_once 'db.php';
$user_id = $_SESSION['user_id'];

echo "<h2>🔍 Complete Upload Diagnosis</h2>";

// 1. Check seller status in detail
echo "<h3>1. Seller Status Check</h3>";
$seller_check = $conn->query("SHOW COLUMNS FROM users LIKE 'is_seller'");
if ($seller_check->num_rows > 0) {
    $stmt = $conn->prepare("SELECT is_seller, seller_requested_at, seller_approved_at FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    
    echo "User ID: " . $user_id . "<br>";
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

// 2. Check products table structure
echo "<h3>2. Products Table Structure</h3>";
$table_check = $conn->query("SHOW TABLES LIKE 'products'");
if ($table_check->num_rows > 0) {
    echo "✅ Products table exists<br>";
    
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

// 3. Test upload directory
echo "<h3>3. Upload Directory Check</h3>";
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

// 4. Test actual upload with dummy data
echo "<h3>4. Test Upload Process</h3>";
$test_name = "TEST PRODUCT " . date("Y-m-d H:i:s");
$test_price = 99.99;
$test_category = "Electronics";
$test_stock = 1;

$stmt = $conn->prepare("INSERT INTO products (seller_id, name, description, price, original_price, category, image_url, stock_quantity) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
if ($stmt === false) {
    echo "❌ Prepare failed: " . $conn->error . "<br>";
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
    }
    $stmt->close();
}

echo "<hr>";
echo "<h2>🎉 DIAGNOSIS COMPLETE</h2>";
echo "<p style='color: green; font-size: 18px;'>If all checks above show ✅, you should be able to upload products.</p>";

// Check if products table exists
echo "<h3>Checking Products Table:</h3>";
$table_check = $conn->query("SHOW TABLES LIKE 'products'");
if ($table_check->num_rows > 0) {
    echo "<p style='color: green;'>✅ Products table exists!</p>";
} else {
    echo "<p style='color: red;'>❌ Products table not found!</p>";
    exit();
}

// Handle upload
if (isset($_POST['action']) && $_POST['action'] == 'upload_product') {
    echo "<h3>Processing Upload:</h3>";
    
    $name = $_POST['name'] ?? '';
    $description = $_POST['description'] ?? '';
    $price = $_POST['price'] ?? '';
    $original_price = $_POST['original_price'] ?? '';
    $category = $_POST['category'] ?? '';
    $stock_quantity = $_POST['stock_quantity'] ?? 0;
    
    echo "Name: " . htmlspecialchars($name) . "<br>";
    echo "Price: " . htmlspecialchars($price) . "<br>";
    echo "Category: " . htmlspecialchars($category) . "<br>";
    echo "Stock: " . htmlspecialchars($stock_quantity) . "<br>";
    
    // Handle image upload
    $image_url = '';
    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] == 0) {
        echo "<p>Image upload detected...</p>";
        $upload_dir = '../uploads/products/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
            echo "<p>Created upload directory...</p>";
        }
        
        $file_name = time() . '_' . basename($_FILES['product_image']['name']);
        $target_file = $upload_dir . $file_name;
        
        if (move_uploaded_file($_FILES['product_image']['tmp_name'], $target_file)) {
            $image_url = 'uploads/products/' . $file_name;
            echo "<p style='color: green;'>✅ Image uploaded: " . $image_url . "</p>";
        } else {
            echo "<p style='color: red;'>❌ Failed to upload image.</p>";
        }
    } else {
        echo "<p>No image uploaded or image error.</p>";
    }
    
    // Insert into database
    $stmt = $conn->prepare("INSERT INTO products (seller_id, name, description, price, original_price, category, image_url, stock_quantity) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    if ($stmt === false) {
        echo "<p style='color: red;'>❌ Prepare failed: " . $conn->error . "</p>";
    } else {
        $stmt->bind_param("isssdssi", $user_id, $name, $description, $price, $original_price, $category, $image_url, $stock_quantity);
        if ($stmt->execute()) {
            echo "<p style='color: green;'>✅ Product uploaded successfully!</p>";
            echo "<p><a href='seller_dashboard.php'>Go to Seller Dashboard</a></p>";
        } else {
            echo "<p style='color: red;'>❌ Failed to upload product: " . $stmt->error . "</p>";
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Upload Debug</title>
    <style>
        body { font-family: Arial; padding: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; }
        input, select, textarea { width: 300px; padding: 8px; }
        button { padding: 10px 20px; background: #2d5016; color: white; border: none; cursor: pointer; }
    </style>
</head>
<body>
    <hr>
    <h3>Test Upload Form:</h3>
    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="upload_product">
        
        <div class="form-group">
            <label>Product Name:</label>
            <input type="text" name="name" required>
        </div>
        
        <div class="form-group">
            <label>Price:</label>
            <input type="number" name="price" step="0.01" required>
        </div>
        
        <div class="form-group">
            <label>Category:</label>
            <select name="category" required>
                <option value="Electronics">Electronics</option>
                <option value="Clothing">Clothing</option>
                <option value="Books">Books</option>
                <option value="Other">Other</option>
            </select>
        </div>
        
        <div class="form-group">
            <label>Stock Quantity:</label>
            <input type="number" name="stock_quantity" value="1" required>
        </div>
        
        <div class="form-group">
            <label>Description:</label>
            <textarea name="description"></textarea>
        </div>
        
        <div class="form-group">
            <label>Product Image:</label>
            <input type="file" name="product_image" accept="image/*">
        </div>
        
        <button type="submit">Upload Product</button>
    </form>
    
    <p><a href="seller_dashboard.php">← Back to Seller Dashboard</a></p>
</body>
</html>
