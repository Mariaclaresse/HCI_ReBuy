<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Check if user is a seller
require_once 'db.php';
$user_id = $_SESSION['user_id'];

$seller_check = $conn->query("SHOW COLUMNS FROM users LIKE 'is_seller'");
if ($seller_check->num_rows > 0) {
    $stmt = $conn->prepare("SELECT is_seller FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    
    if ($user['is_seller'] != 1) {
        header("Location: dashboard.php");
        exit();
    }
} else {
    header("Location: dashboard.php");
    exit();
}

// Get product ID from URL
$product_id = $_GET['id'] ?? '';
if (empty($product_id) || !is_numeric($product_id)) {
    header("Location: manage_products.php");
    exit();
}

// Get product details
$product_stmt = $conn->prepare("SELECT * FROM products WHERE id = ? AND seller_id = ?");
$product_stmt->bind_param("ii", $product_id, $user_id);
$product_stmt->execute();
$product_result = $product_stmt->get_result();

if ($product_result->num_rows === 0) {
    header("Location: manage_products.php");
    exit();
}

$product = $product_result->fetch_assoc();
$product_stmt->close();

// Get all product images (main image + additional images)
$product_images = [];
if (!empty($product['image_url'])) {
    $product_images[] = [
        'url' => $product['image_url'],
        'is_main' => true,
        'id' => null
    ];
}

// Add order column if it doesn't exist
$conn->query("ALTER TABLE product_images ADD COLUMN IF NOT EXISTS sort_order INT DEFAULT 0");

// Create product_colors table if it doesn't exist
$conn->query("CREATE TABLE IF NOT EXISTS product_colors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    color VARCHAR(50) NOT NULL,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
)");

// Create product_sizes table if it doesn't exist
$conn->query("CREATE TABLE IF NOT EXISTS product_sizes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    size VARCHAR(10) NOT NULL,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
)");

// Update existing images to have sequential order if not set
$conn->query("SET @row_number = 0;");
$conn->query("UPDATE product_images SET sort_order = (@row_number:=@row_number+1) WHERE product_id = $product_id ORDER BY created_at");

// Get additional images from product_images table with order
$additional_images_stmt = $conn->prepare("SELECT id, image_url, sort_order FROM product_images WHERE product_id = ? ORDER BY sort_order ASC, created_at ASC");
$additional_images_stmt->bind_param("i", $product_id);
$additional_images_stmt->execute();
$additional_images_result = $additional_images_stmt->get_result();

$additional_images = [];
while ($row = $additional_images_result->fetch_assoc()) {
    $additional_images[] = [
        'url' => $row['image_url'],
        'is_main' => false,
        'id' => $row['id'],
        'sort_order' => $row['sort_order']
    ];
}
$additional_images_stmt->close();

// Merge main image with additional images
$product_images = [];
if (!empty($product['image_url'])) {
    $product_images[] = [
        'url' => $product['image_url'],
        'is_main' => true,
        'id' => null,
        'sort_order' => 0
    ];
}

// Add additional images after main image
foreach ($additional_images as $image) {
    $image['sort_order'] = $image['sort_order'] + 1; // Offset by 1 since main is 0
    $product_images[] = $image;
}

// Get existing colors for the product
$product_colors = [];
$colors_stmt = $conn->prepare("SELECT color FROM product_colors WHERE product_id = ?");
$colors_stmt->bind_param("i", $product_id);
$colors_stmt->execute();
$colors_result = $colors_stmt->get_result();

while ($row = $colors_result->fetch_assoc()) {
    $product_colors[] = $row['color'];
}
$colors_stmt->close();

// Get existing sizes for the product
$product_sizes = [];
$sizes_stmt = $conn->prepare("SELECT size FROM product_sizes WHERE product_id = ?");
$sizes_stmt->bind_param("i", $product_id);
$sizes_stmt->execute();
$sizes_result = $sizes_stmt->get_result();

while ($row = $sizes_result->fetch_assoc()) {
    $product_sizes[] = $row['size'];
}
$sizes_stmt->close();

// Handle product update
$success_msg = $_SESSION['success_msg'] ?? '';
unset($_SESSION['success_msg']);

$error_msg = '';

if (isset($_POST['action']) && $_POST['action'] == 'edit_product') {
    $name = $_POST['name'] ?? '';
    $description = $_POST['description'] ?? '';
    $price = $_POST['price'] ?? '';
    $original_price = $_POST['original_price'] ?? '';
    $category = $_POST['category'] ?? '';
    $stock_quantity = $_POST['stock_quantity'] ?? 0;
    $colors = $_POST['colors'] ?? [];
    $sizes = $_POST['sizes'] ?? [];
    
    // Handle additional image uploads first
    $additional_images_uploaded = 0;

    if (isset($_FILES['additional_images']) && !empty($_FILES['additional_images']['name'][0])) {

        $upload_dir = '../uploads/products/';

        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        foreach ($_FILES['additional_images']['name'] as $key => $fileName) {

            if ($_FILES['additional_images']['error'][$key] == 0) {

                $new_file_name = time() . '_add_' . $key . '_' . basename($fileName);

                $target_file = $upload_dir . $new_file_name;

                if (move_uploaded_file($_FILES['additional_images']['tmp_name'][$key], $target_file)) {

                    $image_path = 'uploads/products/' . $new_file_name;

                    $insert_stmt = $conn->prepare("
                        INSERT INTO product_images (product_id, image_url) 
                        VALUES (?, ?)
                    ");

                    if ($insert_stmt) {

                        $insert_stmt->bind_param("is", $product_id, $image_path);

                        if ($insert_stmt->execute()) {
                            $additional_images_uploaded++;
                        }

                        $insert_stmt->close();
                    }
                }
            }
        }
    }

    // Handle main image upload
    $image_url = $product['image_url'];

    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] == 0) {

        $upload_dir = '../uploads/products/';

        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $file_name = time() . '_main_' . basename($_FILES['product_image']['name']);

        $target_file = $upload_dir . $file_name;

        if (move_uploaded_file($_FILES['product_image']['tmp_name'], $target_file)) {

            // Move old main image to additional images
            if (!empty($product['image_url'])) {

                $insert_old = $conn->prepare("
                    INSERT INTO product_images (product_id, image_url) 
                    VALUES (?, ?)
                ");

                if ($insert_old) {

                    $insert_old->bind_param("is", $product_id, $product['image_url']);
                    $insert_old->execute();
                    $insert_old->close();
                }
            }

            $image_url = 'uploads/products/' . $file_name;

        } else {

            $error_msg = "Failed to upload main image.";
        }
    }

    if (empty($error_msg)) {

        $stmt = $conn->prepare("
            UPDATE products 
            SET 
                name = ?, 
                description = ?, 
                price = ?, 
                original_price = ?, 
                category = ?, 
                image_url = ?, 
                stock_quantity = ?
            WHERE id = ? 
            AND seller_id = ?
        ");

        if ($stmt === false) {

            $error_msg = "Prepare failed: " . $conn->error;

        } else {

            $stmt->bind_param(
                "sssdsssii",
                $name,
                $description,
                $price,
                $original_price,
                $category,
                $image_url,
                $stock_quantity,
                $product_id,
                $user_id
            );

            if ($stmt->execute()) {

                // Delete old colors
                $conn->query("DELETE FROM product_colors WHERE product_id = $product_id");

                // Insert new colors
                if (!empty($colors) && is_array($colors)) {

                    $color_stmt = $conn->prepare("
                        INSERT INTO product_colors (product_id, color) 
                        VALUES (?, ?)
                    ");

                    foreach ($colors as $color) {

                        $color = trim($color);

                        if (!empty($color)) {

                            $color_stmt->bind_param("is", $product_id, $color);
                            $color_stmt->execute();
                        }
                    }

                    $color_stmt->close();
                }

                // Delete old sizes
                $conn->query("DELETE FROM product_sizes WHERE product_id = $product_id");

                // Insert new sizes
                if (!empty($sizes) && is_array($sizes)) {

                    $size_stmt = $conn->prepare("
                        INSERT INTO product_sizes (product_id, size) 
                        VALUES (?, ?)
                    ");

                    foreach ($sizes as $size) {

                        $size = trim($size);

                        if (!empty($size)) {

                            $size_stmt->bind_param("is", $product_id, $size);
                            $size_stmt->execute();
                        }
                    }

                    $size_stmt->close();
                }

                // Success message
                $_SESSION['success_msg'] = "Product updated successfully!";

                if ($additional_images_uploaded > 0) {

                    $_SESSION['success_msg'] .= " " . $additional_images_uploaded . " additional images uploaded.";
                }

                // AUTO REFRESH PAGE
                header("Location: edit_product.php?id=" . $product_id);
                exit();

            } else {

                $error_msg = "Failed to update product: " . $stmt->error;
            }

            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ReBuy</title>
    <link rel="icon" type="image/x-icon" href="../../assets/logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../css/header-footer.css">
    <style>
        :root {
            --primary-color: #2d5016;
            --secondary-color: #f4c430;
            --success-color: #28a745;
            --danger-color: #dc3545;
            --light-gray: #f5f5f5;
            --dark-gray: #333;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--light-gray);
            margin: 0;
            padding: 0;
        }

        .upload-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }

        .upload-header {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .upload-header h1 {
            color: var(--primary-color);
            margin: 0;
            font-size: 28px;
        }

        .upload-header p {
            color: #666;
            margin: 5px 0 0 0;
        }

        .upload-form {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-row.full {
            grid-template-columns: 1fr;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-size: 14px;
            font-weight: 600;
            color: var(--dark-gray);
            margin-bottom: 8px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            font-family: inherit;
            transition: border-color 0.3s;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(45, 80, 22, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 120px;
        }

        .image-upload {
            border: 2px dashed #ddd;
            border-radius: 6px;
            padding: 30px;
            text-align: center;
            cursor: pointer;
            transition: border-color 0.3s;
        }

        .image-upload:hover {
            border-color: var(--primary-color);
        }

        .image-upload i {
            font-size: 48px;
            color: #999;
            margin-bottom: 15px;
        }

        .image-preview {
            margin-top: 15px;
            max-width: 200px;
            max-height: 200px;
            border-radius: 6px;
        }

        .current-image {
            margin-top: 10px;
            font-size: 12px;
            color: #666;
        }

        .product-images-gallery {
            margin-top: 20px;
        }

        .gallery-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .gallery-title {
            font-size: 14px;
            font-weight: 600;
            color: var(--dark-gray);
        }

        .add-more-images {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 12px;
            cursor: pointer;
            transition: background 0.3s;
        }

        .add-more-images:hover {
            background: #1a3009;
        }

        .images-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .image-item {
            position: relative;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            background: white;
            border: 1px solid #e9ecef;
        }

        .image-item.main-image {
            border: 2px solid var(--primary-color);
        }

        .image-item img {
            width: 100%;
            height: 150px;
            object-fit: cover;
            display: block;
        }

        .image-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s;
        }

        .image-item:hover .image-overlay {
            opacity: 1;
        }

        .image-actions {
            display: flex;
            gap: 10px;
        }

        .image-action-btn {
            background: white;
            border: none;
            width: 35px;
            height: 35px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.2s;
        }

        .image-action-btn:hover {
            transform: scale(1.1);
        }

        .image-action-btn.delete {
            color: var(--danger-color);
        }

        .image-action-btn.set-main {
            color: var(--primary-color);
        }

        .image-item {
            cursor: move;
        }

        .image-item.dragging {
            cursor: grabbing;
        }

        .image-number {
            position: absolute;
            top: 5px;
            left: 5px;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            font-weight: 600;
        }

        .image-item.dragging {
            opacity: 0.5;
            transform: scale(0.95);
        }

        .image-item.drag-over {
            border: 2px dashed var(--primary-color);
        }

        .sortable-ghost {
            opacity: 0.4;
            background: var(--primary-color);
        }

        .image-badge {
            position: absolute;
            top: 5px;
            left: 5px;
            background: var(--primary-color);
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 600;
        }

        .additional-images-upload {
            margin-top: 20px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            display: none;
        }

        .additional-images-upload.show {
            display: block;
        }

        .color-size-container {
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 15px;
            background: #f9f9f9;
        }

        .color-size-inputs {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 10px;
        }

        .color-size-input {
            flex: 1;
            min-width: 150px;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        .add-color-size-btn {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            transition: background 0.3s;
        }

        .add-color-size-btn:hover {
            background: #1a3009;
        }

        .size-options {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .size-category-group {
            border: 1px solid #e0e0e0;
            border-radius: 4px;
            padding: 10px;
            background: white;
        }

        .size-category-label {
            font-weight: 600;
            color: var(--dark-gray);
            margin-bottom: 8px;
            display: block;
            font-size: 14px;
        }

        .size-checkboxes {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
        }

        .size-checkboxes label {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 14px;
            cursor: pointer;
            margin: 0;
        }

        .size-checkboxes input[type="checkbox"] {
            margin: 0;
            width: auto;
        }

        .remove-color-btn {
            background: var(--danger-color);
            color: white;
            border: none;
            padding: 4px 8px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            margin-left: 5px;
        }

        .color-input-wrapper {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .image-preview-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 10px;
            margin-top: 15px;
        }

        .preview-item {
            position: relative;
            border-radius: 4px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .preview-item img {
            width: 100%;
            height: 100px;
            object-fit: cover;
        }

        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }

        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: #1a3009;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(45, 80, 22, 0.2);
        }

        .btn-secondary {
            background: #f5f5f5;
            color: var(--dark-gray);
            border: 1px solid #ddd;
        }

        .btn-secondary:hover {
            background: #e8e8e8;
        }

        .alert {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        .alert-error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }

        .required {
            color: var(--danger-color);
        }

        @media (max-width: 768px) {
            .upload-container {
                padding: 10px;
            }
            
            .upload-form {
                padding: 20px;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>

    <?php include '_header.php'; ?>

    <div class="upload-container">
        <!-- Upload Header -->
        <div class="upload-header">
            <h1><i class="fas fa-edit"></i> Edit Product</h1>
            <p>Update your product information</p>
        </div>

        <!-- Upload Form -->
        <div class="upload-form">
            <?php if ($success_msg): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $success_msg; ?>
                </div>
            <?php endif; ?>

            <?php if ($error_msg): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error_msg; ?>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="edit_product">
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Product Name <span class="required">*</span></label>
                        <input type="text" name="name" required placeholder="Enter product name" value="<?php echo htmlspecialchars($product['name']); ?>">
                    </div>
                    <div class="form-group">
                        <label>Category <span class="required">*</span></label>
                        <select name="category" required>
                            <option value="">Select category</option>
                            <option value="Appliances" <?php echo $product['category'] == 'Appliances' ? 'selected' : ''; ?>>Appliances</option>
                            <option value="Clothing" <?php echo $product['category'] == 'Clothing' ? 'selected' : ''; ?>>Clothing</option>
                            <option value="Books" <?php echo $product['category'] == 'Books' ? 'selected' : ''; ?>>Books</option>
                            <option value="Home & Garden" <?php echo $product['category'] == 'Home & Garden' ? 'selected' : ''; ?>>Home & Garden</option>
                            <option value="Sports" <?php echo $product['category'] == 'Sports' ? 'selected' : ''; ?>>Sports</option>
                            <option value="Toys" <?php echo $product['category'] == 'Toys' ? 'selected' : ''; ?>>Toys</option>
                            <option value="Shoes" <?php echo $product['category'] == 'Shoes' ? 'selected' : ''; ?>>Shoes</option>
                            <option value="Gadget" <?php echo $product['category'] == 'Gadget' ? 'selected' : ''; ?>>Gadget</option>
                            <option value="Stationery" <?php echo $product['category'] == 'Stationery' ? 'selected' : ''; ?>>Stationery</option>
                            <option value="Other" <?php echo $product['category'] == 'Other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Selling Price <span class="required">*</span></label>
                        <input type="number" name="price" step="0.01" min="0" required placeholder="₱0.00" value="<?php echo htmlspecialchars($product['price']); ?>">
                    </div>
                    <div class="form-group">
                        <label>Original Price</label>
                        <input type="number" name="original_price" step="0.01" min="0" placeholder="₱0.00" value="<?php echo htmlspecialchars($product['original_price']); ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Stock Quantity <span class="required">*</span></label>
                        <input type="number" name="stock_quantity" min="0" required placeholder="0" value="<?php echo htmlspecialchars($product['stock_quantity']); ?>">
                    </div>
                    <div class="form-group">
                        <label>Colors (Optional)</label>
                        <div class="color-size-container">
                            <div class="color-size-inputs" id="color_inputs">
                                <?php if (!empty($product_colors)): ?>
                                    <?php foreach ($product_colors as $index => $color): ?>
                                        <div class="color-input-wrapper">
                                            <input type="text" name="colors[]" placeholder="e.g., Red, Blue, Black" class="color-size-input" value="<?php echo htmlspecialchars($color); ?>">
                                            <button type="button" class="remove-color-btn" onclick="this.parentElement.remove()">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="color-input-wrapper">
                                        <input type="text" name="colors[]" placeholder="e.g., Red, Blue, Black" class="color-size-input">
                                    </div>
                                <?php endif; ?>
                            </div>
                            <button type="button" class="add-color-size-btn" onclick="addColorInput()">
                                <i class="fas fa-plus"></i> Add Color
                            </button>
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Sizes (Optional)</label>
                        <div class="color-size-container">
                            <div class="size-options" id="size_options">
                                <div class="size-category-group">
                                    <label class="size-category-label">Clothing Sizes:</label>
                                    <div class="size-checkboxes">
                                        <label><input type="checkbox" name="sizes[]" value="XS" <?php echo in_array('XS', $product_sizes) ? 'checked' : ''; ?>> XS</label>
                                        <label><input type="checkbox" name="sizes[]" value="S" <?php echo in_array('S', $product_sizes) ? 'checked' : ''; ?>> S</label>
                                        <label><input type="checkbox" name="sizes[]" value="M" <?php echo in_array('M', $product_sizes) ? 'checked' : ''; ?>> M</label>
                                        <label><input type="checkbox" name="sizes[]" value="L" <?php echo in_array('L', $product_sizes) ? 'checked' : ''; ?>> L</label>
                                        <label><input type="checkbox" name="sizes[]" value="XL" <?php echo in_array('XL', $product_sizes) ? 'checked' : ''; ?>> XL</label>
                                    </div>
                                </div>
                                <div class="size-category-group">
                                    <label class="size-category-label">Shoe Sizes:</label>
                                    <div class="size-checkboxes">
                                        <label><input type="checkbox" name="sizes[]" value="36" <?php echo in_array('36', $product_sizes) ? 'checked' : ''; ?>> 36</label>
                                        <label><input type="checkbox" name="sizes[]" value="37" <?php echo in_array('37', $product_sizes) ? 'checked' : ''; ?>> 37</label>
                                        <label><input type="checkbox" name="sizes[]" value="38" <?php echo in_array('38', $product_sizes) ? 'checked' : ''; ?>> 38</label>
                                        <label><input type="checkbox" name="sizes[]" value="39" <?php echo in_array('39', $product_sizes) ? 'checked' : ''; ?>> 39</label>
                                        <label><input type="checkbox" name="sizes[]" value="40" <?php echo in_array('40', $product_sizes) ? 'checked' : ''; ?>> 40</label>
                                        <label><input type="checkbox" name="sizes[]" value="41" <?php echo in_array('41', $product_sizes) ? 'checked' : ''; ?>> 41</label>
                                        <label><input type="checkbox" name="sizes[]" value="42" <?php echo in_array('42', $product_sizes) ? 'checked' : ''; ?>> 42</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Product Images</label>
                        
                        <!-- Product Images Gallery -->
                        <div class="product-images-gallery">
                            <div class="gallery-header">
                                <span class="gallery-title">Current Images (<?php echo count($product_images); ?>)</span>
                                <button type="button" class="add-more-images" onclick="toggleAdditionalUpload()">
                                    <i class="fas fa-plus"></i> Add More
                                </button>
                            </div>
                            
                            <?php if (!empty($product_images)): ?>
                                <div class="images-grid" id="images_grid">
                                    <?php foreach ($product_images as $index => $image): ?>
                                        <div class="image-item <?php echo $image['is_main'] ? 'main-image' : ''; ?>" 
                                             data-image-id="<?php echo $image['id'] ?? 'main'; ?>" 
                                             data-sort-order="<?php echo $index; ?>"
                                             draggable="true">
                                            <span class="image-number"><?php echo $index + 1; ?></span>
                                            <?php if ($image['is_main']): ?>
                                                <span class="image-badge">MAIN</span>
                                            <?php endif; ?>
                                            
                                            <img src="<?php echo '../' . htmlspecialchars($image['url']); ?>" alt="Product image <?php echo $index + 1; ?>">
                                            <div class="image-overlay">
                                                <div class="image-actions">
                                                    <?php if (!$image['is_main']): ?>
                                                        <button type="button" class="image-action-btn set-main" onclick="setAsMainImage(<?php echo $image['id']; ?>)" title="Set as main image">
                                                            <i class="fas fa-star"></i>
                                                        </button>
                                                        <button type="button" class="image-action-btn delete" onclick="deleteImage(<?php echo $image['id']; ?>)" title="Delete image">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p style="color: #666; font-size: 14px;">No images uploaded yet.</p>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Additional Images Upload (Hidden by default) -->
                        <div id="additional_images_upload" class="additional-images-upload">
                            <h4 style="margin-bottom: 15px; color: #333;">Add More Images</h4>
                            <div class="image-upload" onclick="document.getElementById('additional_images').click()">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <p>Click to upload multiple images</p>
                                <input type="file" id="additional_images" name="additional_images[]" accept="image/*" multiple style="display: none;" onchange="previewAdditionalImages(event)">
                            </div>
                            <div id="additional_preview_container" class="image-preview-container"></div>
                        </div>
                    </div>
                </div>

                <div class="form-row full">
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" placeholder="Describe your product (features, condition, etc.)"><?php echo htmlspecialchars($product['description']); ?></textarea>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Product
                    </button>
                    <a href="manage_products.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Products
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Footer -->
    <footer>
        <div class="footer-container">
            <div class="footer-content">
                <div class="footer-section">
                    <div class="footer-logo">
                        <i class="fas fa-shopping-bag"></i>
                        <span>ReBuy</span>
                    </div>
                    <p class="footer-text">ReBuy lets you buy quality second-hand items for less, saving money while supporting a more sustainable lifestyle.</p>
                    <div class="social-links">
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-pinterest"></i></a>
                    </div>
                </div>

                <div class="footer-section">
                    <h3>Company</h3>
                    <ul>
                        <li><a href="about_us.php">About Us</a></li>
                        <li><a href="#">Contact Us</a></li>
                    </ul>
                </div>

                <div class="footer-section">
                    <h3>Customer Services</h3>
                    <ul>
                        <li><a href="settings.php">My Account</a></li>
                        <li><a href="#">Track Your Order</a></li>
                        <li><a href="#">Returns</a></li>
                        <li><a href="#">FAQ</a></li>
                    </ul>
                </div>

                <div class="footer-section">
                    <h3>Our Information</h3>
                    <ul>
                        <li><a href="#">Privacy Policy</a></li>
                        <li><a href="#">Terms & Condition</a></li>
                        <li><a href="#">Return Policy</a></li>
                        <li><a href="#">Shipping Info</a></li>
                    </ul>
                </div>

                <div class="footer-section">
                    <h3>Contact Info</h3>
                    <p class="footer-text"><i class="fas fa-phone"></i> +639813446215</p>
                    <p class="footer-text"><i class="fa-solid fa-envelope"></i> rebuy@gmail.com</p>
                    <p class="footer-text"><i class="fa-solid fa-location-dot"></i> T. Curato St. Cabadbaran City Agusan Del Norte, Philippines, 8600</p>
                </div>
            </div>

            <div class="footer-bottom">
                <p>&copy; Copyright @ 2026 <strong>ReBuy</strong>. All Rights Reserved.</p>
            </div>
        </div>
    </footer>

    <script>
        // User dropdown menu
        document.querySelector('.icon-btn').addEventListener('click', function() {
            document.querySelector('.user-dropdown').classList.toggle('active');
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const userMenu = document.querySelector('.user-menu');
            if (!userMenu.contains(event.target)) {
                document.querySelector('.user-dropdown').classList.remove('active');
            }
        });

        // Image preview
        function previewImage(event) {
            const file = event.target.files[0];
            const preview = document.getElementById('image_preview');
            
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                }
                reader.readAsDataURL(file);
            }
        }

        // Toggle additional images upload
        function toggleAdditionalUpload() {
            const uploadSection = document.getElementById('additional_images_upload');
            uploadSection.classList.toggle('show');
        }

        // Preview additional images
        function previewAdditionalImages(event) {
            const files = event.target.files;
            const container = document.getElementById('additional_preview_container');
            
            // Clear existing previews
            container.innerHTML = '';
            
            for (let i = 0; i < files.length; i++) {
                const file = files[i];
                
                if (file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    
                    reader.onload = function(e) {
                        const previewItem = document.createElement('div');
                        previewItem.className = 'preview-item';
                        
                        const img = document.createElement('img');
                        img.src = e.target.result;
                        img.alt = `Preview ${i + 1}`;
                        
                        previewItem.appendChild(img);
                        container.appendChild(previewItem);
                    };
                    
                    reader.readAsDataURL(file);
                }
            }
        }

        // Set image as main
        function setAsMainImage(imageId) {
            if (confirm('Are you sure you want to set this image as the main image?')) {
                const xhr = new XMLHttpRequest();
                xhr.open('POST', 'ajax_image_management.php', true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                
                xhr.onreadystatechange = function() {
                    if (xhr.readyState === 4) {
                        try {
                            const response = JSON.parse(xhr.responseText);
                            if (response.success) {
                                location.reload();
                            } else {
                                alert('Error: ' + response.message);
                            }
                        } catch (e) {
                            alert('Error occurred while processing your request.');
                        }
                    }
                };
                
                xhr.send('action=set_main&image_id=' + imageId + '&product_id=<?php echo $product_id; ?>');
            }
        }

        // Delete image
        function deleteImage(imageId) {
            if (confirm('Are you sure you want to delete this image? This action cannot be undone.')) {
                const xhr = new XMLHttpRequest();
                xhr.open('POST', 'ajax_image_management.php', true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                
                xhr.onreadystatechange = function() {
                    if (xhr.readyState === 4) {
                        try {
                            const response = JSON.parse(xhr.responseText);
                            if (response.success) {
                                location.reload();
                            } else {
                                alert('Error: ' + response.message);
                            }
                        } catch (e) {
                            alert('Error occurred while processing your request.');
                        }
                    }
                };
                
                xhr.send('action=delete&image_id=' + imageId + '&product_id=<?php echo $product_id; ?>');
            }
        }

        // Update image numbers
        function updateImageNumbers() {
            const grid = document.getElementById('images_grid');
            const items = Array.from(grid.children);
            
            items.forEach((item, index) => {
                const numberBadge = item.querySelector('.image-number');
                if (numberBadge) {
                    numberBadge.textContent = index + 1;
                }
                
                // Update data attributes
                item.dataset.sortOrder = index;
            });
        }

        // Save image order to database
        function saveImageOrder() {
            const grid = document.getElementById('images_grid');
            const items = Array.from(grid.children);
            const imageOrder = [];
            let mainImagePosition = -1;
            
            items.forEach((item, index) => {
                const imageId = item.dataset.imageId;
                
                if (imageId === 'main') {
                    mainImagePosition = index;
                } else {
                    imageOrder.push({
                        id: imageId,
                        sort_order: index
                    });
                }
            });
            
            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'ajax_image_management.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (!response.success) {
                            console.error('Failed to save image order:', response.message);
                            // Optionally show an error message to user
                        }
                    } catch (e) {
                        console.error('Error saving image order:', e);
                    }
                }
            };
            
            const data = {
                action: 'reorder',
                product_id: <?php echo $product_id; ?>,
                order: imageOrder,
                main_position: mainImagePosition
            };
            
            xhr.send('action=reorder&product_id=<?php echo $product_id; ?>&order=' + JSON.stringify(imageOrder) + '&main_position=' + mainImagePosition);
        }

        // Drag and drop functionality
        let draggedElement = null;

        document.addEventListener('DOMContentLoaded', function() {
            const grid = document.getElementById('images_grid');
            if (!grid) return;
            
            grid.addEventListener('dragstart', function(e) {
                if (e.target.classList.contains('image-item')) {
                    draggedElement = e.target;
                    e.target.classList.add('dragging');
                    e.dataTransfer.effectAllowed = 'move';
                }
            });
            
            grid.addEventListener('dragend', function(e) {
                if (e.target.classList.contains('image-item')) {
                    e.target.classList.remove('dragging');
                }
            });
            
            grid.addEventListener('dragover', function(e) {
                e.preventDefault();
                const afterElement = getDragAfterElement(grid, e.clientX, e.clientY);
                if (afterElement == null) {
                    grid.appendChild(draggedElement);
                } else {
                    grid.insertBefore(draggedElement, afterElement);
                }
            });
            
            grid.addEventListener('drop', function(e) {
                e.preventDefault();
                updateImageNumbers();
                saveImageOrder();
            });
        });

        function getDragAfterElement(container, x, y) {
            const draggableElements = [...container.querySelectorAll('.image-item:not(.dragging)')];
            
            return draggableElements.reduce((closest, child) => {
                const box = child.getBoundingClientRect();
                const offset = y - box.top - box.height / 2;
                
                if (offset < 0 && offset > closest.offset) {
                    return { offset: offset, element: child };
                } else {
                    return closest;
                }
            }, { offset: Number.NEGATIVE_INFINITY }).element;
        }

        // Add color input function
        function addColorInput() {
            const colorInputs = document.getElementById('color_inputs');
            const newWrapper = document.createElement('div');
            newWrapper.className = 'color-input-wrapper';
            
            const newInput = document.createElement('input');
            newInput.type = 'text';
            newInput.name = 'colors[]';
            newInput.placeholder = 'e.g., Red, Blue, Black';
            newInput.className = 'color-size-input';
            
            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'remove-color-btn';
            removeBtn.innerHTML = '<i class="fas fa-times"></i>';
            removeBtn.onclick = function() {
                newWrapper.remove();
            };
            
            newWrapper.appendChild(newInput);
            newWrapper.appendChild(removeBtn);
            colorInputs.appendChild(newWrapper);
        }
    </script>
</body>
</html>
