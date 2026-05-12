<?php
// Database setup script for seller fields
require_once 'db.php';

echo "<h2>Setting up seller database fields...</h2>";

// Check if is_seller column exists
$seller_check = $conn->query("SHOW COLUMNS FROM users LIKE 'is_seller'");
if ($seller_check->num_rows == 0) {
    echo "<p>Adding seller fields to users table...</p>";
    
    // Add seller fields to users table
    $alter_sql = "
        ALTER TABLE users ADD COLUMN is_seller TINYINT(1) DEFAULT 0 COMMENT '1 if user is a seller, 0 if regular user',
        ADD COLUMN seller_requested_at TIMESTAMP NULL COMMENT 'When user requested to become a seller',
        ADD COLUMN seller_approved_at TIMESTAMP NULL COMMENT 'When user was approved as seller'
    ";
    
    if ($conn->multi_query($alter_sql)) {
        echo "<p style='color: green;'>✓ Seller fields added to users table successfully!</p>";
    } else {
        echo "<p style='color: red;'>✗ Error adding seller fields: " . $conn->error . "</p>";
    }
    
    // Clear any remaining results
    while ($conn->next_result()) {;}
} else {
    echo "<p style='color: blue;'>ℹ Seller fields already exist in users table.</p>";
}

// Create products table
echo "<p>Creating products table...</p>";
$products_sql = "
CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    seller_id INT NOT NULL,
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
    FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_seller_id (seller_id),
    INDEX idx_category (category),
    INDEX idx_status (status)
)";

if ($conn->query($products_sql)) {
    echo "<p style='color: green;'>✓ Products table created successfully!</p>";
} else {
    echo "<p style='color: red;'>✗ Error creating products table: " . $conn->error . "</p>";
}

// Create seller_orders table
echo "<p>Creating seller_orders table...</p>";
$orders_sql = "
CREATE TABLE IF NOT EXISTS seller_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id VARCHAR(50) NOT NULL UNIQUE,
    seller_id INT NOT NULL,
    customer_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    price_per_item DECIMAL(10,2) NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'processing', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending',
    order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_seller_id (seller_id),
    INDEX idx_customer_id (customer_id),
    INDEX idx_status (status),
    INDEX idx_order_date (order_date)
)";

if ($conn->query($orders_sql)) {
    echo "<p style='color: green;'>✓ Seller orders table created successfully!</p>";
} else {
    echo "<p style='color: red;'>✗ Error creating seller orders table: " . $conn->error . "</p>";
}

echo "<h3>Setup completed!</h3>";
echo "<p><a href='settings.php'>Go to Settings</a> to enable seller account.</p>";
echo "<p><a href='dashboard.php'>Go to Dashboard</a></p>";
?>
