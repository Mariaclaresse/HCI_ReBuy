<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    echo "Not logged in. Please <a href='login.php'>login</a> first.";
    exit();
}

require_once 'db.php';
$user_id = $_SESSION['user_id'];

echo "<h2>Seller Approval Check</h2>";

// Check current seller status
echo "<h3>Current Seller Status:</h3>";
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

// If seller but not approved, approve them
if (($user['is_seller'] ?? 0) == 1 && empty($user['seller_approved_at'])) {
    echo "<h3>Approving Seller Account...</h3>";
    
    $approve_stmt = $conn->prepare("UPDATE users SET seller_approved_at = NOW() WHERE id = ?");
    $approve_stmt->bind_param("i", $user_id);
    
    if ($approve_stmt->execute()) {
        echo "<p style='color: green;'>✅ Seller account approved successfully!</p>";
    } else {
        echo "<p style='color: red;'>❌ Failed to approve: " . $approve_stmt->error . "</p>";
    }
    $approve_stmt->close();
} elseif (($user['is_seller'] ?? 0) == 1 && !empty($user['seller_approved_at'])) {
    echo "<p style='color: green;'>✅ Seller account is already approved!</p>";
} else {
    echo "<p style='color: red;'>❌ User is not a seller yet.</p>";
    echo "<p><a href='settings.php'>Go to Settings → Enable Seller Account</a></p>";
    exit();
}

// Test upload access
echo "<h3>Testing Upload Access:</h3>";

// Check if products table exists
$table_check = $conn->query("SHOW TABLES LIKE 'products'");
if ($table_check->num_rows > 0) {
    echo "<p style='color: green;'>✅ Products table exists</p>";
    
    // Check if seller_id column exists
    $column_check = $conn->query("SHOW COLUMNS FROM products LIKE 'seller_id'");
    if ($column_check->num_rows > 0) {
        echo "<p style='color: green;'>✅ seller_id column exists</p>";
        echo "<p style='color: green;'>✅ You should be able to upload products now!</p>";
    } else {
        echo "<p style='color: red;'>❌ seller_id column missing</p>";
        echo "<p><a href='fix_products_table.php'>Fix Products Table</a></p>";
    }
} else {
    echo "<p style='color: red;'>❌ Products table missing</p>";
    echo "<p><a href='setup_seller_fields.php'>Setup Database</a></p>";
}

echo "<hr>";
echo "<h3>Ready to Upload!</h3>";
echo "<p><a href='upload_product.php'>→ Upload Product</a></p>";
echo "<p><a href='seller_dashboard.php'>→ Seller Dashboard</a></p>";
echo "<p><a href='debug_upload.php'>→ Debug Upload</a></p>";
?>
