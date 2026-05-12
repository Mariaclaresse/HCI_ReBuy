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

// Handle product upload
$success_msg = '';
$error_msg = '';

// Create product_images table if it doesn't exist
$conn->query("CREATE TABLE IF NOT EXISTS product_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    image_url VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
)");

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

if (isset($_POST['action']) && $_POST['action'] == 'upload_product') {
    $name = $_POST['name'] ?? '';
    $description = $_POST['description'] ?? '';
    $price = $_POST['price'] ?? '';
    $original_price = $_POST['original_price'] ?? '';
    $category = $_POST['category'] ?? '';
    $stock_quantity = $_POST['stock_quantity'] ?? 0;
    $colors = $_POST['colors'] ?? [];
    $sizes = $_POST['sizes'] ?? [];
    
    // Handle main image upload (first image or single image)
    $image_url = '';
    $additional_images = [];
    
    if (isset($_FILES['product_images']) && !empty($_FILES['product_images']['name'][0])) {
        $upload_dir = '../uploads/products/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        foreach ($_FILES['product_images']['name'] as $key => $file_original_name) {
            if ($_FILES['product_images']['error'][$key] == 0) {
                $file_name = time() . '_' . $key . '_' . basename($_FILES['product_images']['name'][$key]);
                $target_file = $upload_dir . $file_name;
                
                if (move_uploaded_file($_FILES['product_images']['tmp_name'][$key], $target_file)) {
                    $image_path = 'uploads/products/' . $file_name;
                    if (empty($image_url)) {
                        $image_url = $image_path; // First image as main image
                    }
                    $additional_images[] = $image_path;
                } else {
                    $error_msg = "Failed to upload image: " . $_FILES['product_images']['name'][$key];
                    break;
                }
            }
        }
    }
    
    if (empty($error_msg)) {
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Insert product
            $stmt = $conn->prepare("INSERT INTO products (seller_id, name, description, price, original_price, category, image_url, stock_quantity) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            if ($stmt === false) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            
            $stmt->bind_param("isssdssi", $user_id, $name, $description, $price, $original_price, $category, $image_url, $stock_quantity);
            if (!$stmt->execute()) {
                throw new Exception("Failed to upload product: " . $stmt->error);
            }
            
            $product_id = $conn->insert_id;
            $stmt->close();
            
            // Insert additional images
            if (!empty($additional_images) && count($additional_images) > 1) {
                $img_stmt = $conn->prepare("INSERT INTO product_images (product_id, image_url) VALUES (?, ?)");
                if ($img_stmt === false) {
                    throw new Exception("Image prepare failed: " . $conn->error);
                }
                
                // Skip first image as it's already the main image
                for ($i = 1; $i < count($additional_images); $i++) {
                    $img_stmt->bind_param("is", $product_id, $additional_images[$i]);
                    if (!$img_stmt->execute()) {
                        throw new Exception("Failed to save additional image: " . $img_stmt->error);
                    }
                }
                $img_stmt->close();
            }
            
            // Insert colors
            if (!empty($colors) && is_array($colors)) {
                $color_stmt = $conn->prepare("INSERT INTO product_colors (product_id, color) VALUES (?, ?)");
                if ($color_stmt === false) {
                    throw new Exception("Color prepare failed: " . $conn->error);
                }
                
                foreach ($colors as $color) {
                    $color = trim($color);
                    if (!empty($color)) {
                        $color_stmt->bind_param("is", $product_id, $color);
                        if (!$color_stmt->execute()) {
                            throw new Exception("Failed to save color: " . $color_stmt->error);
                        }
                    }
                }
                $color_stmt->close();
            }
            
            // Insert sizes
            if (!empty($sizes) && is_array($sizes)) {
                $size_stmt = $conn->prepare("INSERT INTO product_sizes (product_id, size) VALUES (?, ?)");
                if ($size_stmt === false) {
                    throw new Exception("Size prepare failed: " . $conn->error);
                }
                
                foreach ($sizes as $size) {
                    $size = trim($size);
                    if (!empty($size)) {
                        $size_stmt->bind_param("is", $product_id, $size);
                        if (!$size_stmt->execute()) {
                            throw new Exception("Failed to save size: " . $size_stmt->error);
                        }
                    }
                }
                $size_stmt->close();
            }
            
            $conn->commit();
            $success_msg = "Product uploaded successfully!";
            
        } catch (Exception $e) {
            $conn->rollback();
            $error_msg = $e->getMessage();
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

        .image-preview-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }

        .image-preview-item {
            position: relative;
            border-radius: 6px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .image-preview-item img {
            width: 100%;
            height: 150px;
            object-fit: cover;
            display: block;
        }

        .image-preview-item .remove-btn {
            position: absolute;
            top: 5px;
            right: 5px;
            background: rgba(220, 53, 69, 0.9);
            color: white;
            border: none;
            border-radius: 50%;
            width: 25px;
            height: 25px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            transition: background 0.3s;
        }

        .image-preview-item .remove-btn:hover {
            background: rgba(220, 53, 69, 1);
        }

        .image-preview-item.main-image::after {
            content: 'Main';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: var(--primary-color);
            color: white;
            text-align: center;
            padding: 4px;
            font-size: 12px;
            font-weight: 600;
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

        .image-preview-item {
    position: relative;
    overflow: hidden;
    border-radius: 10px;
    cursor: grab;
    user-select: none;

    transition:
        transform 0.25s ease,
        box-shadow 0.25s ease,
        opacity 0.25s ease,
        border 0.25s ease;

    background: white;
}

.image-preview-item:hover {
    transform: scale(1.03);
    box-shadow: 0 8px 20px rgba(0,0,0,0.15);
}

.image-preview-item:active {
    cursor: grabbing;
}

/* DRAGGING EFFECT */
.image-preview-item.dragging {
    opacity: 0.5;
    transform: scale(0.95) rotate(2deg);
    z-index: 1000;
}

/* DROP TARGET */
.image-preview-item.drag-over {
    transform: scale(1.05);
    border: 2px dashed var(--primary-color);
}

/* IMAGE */
.image-preview-item img {
    width: 100%;
    height: 150px;
    object-fit: cover;
    display: block;
    pointer-events: none;
}

/* REMOVE BUTTON */
.image-preview-item .remove-btn {
    position: absolute;
    top: 8px;
    right: 8px;
    background: rgba(220, 53, 69, 0.95);
    color: white;
    border: none;
    border-radius: 50%;
    width: 28px;
    height: 28px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    transition: all 0.2s ease;
    z-index: 10;
}

.image-preview-item .remove-btn:hover {
    transform: scale(1.1);
    background: rgba(220, 53, 69, 1);
}

/* MAIN LABEL */
.image-preview-item.main-image::after {
    content: 'Main';
    position: absolute;
    bottom: 0;
    left: 0;
    width: 100%;
    background: var(--primary-color);
    color: white;
    text-align: center;
    padding: 7px 0;
    font-size: 12px;
    font-weight: bold;
    letter-spacing: 0.5px;
    z-index: 5;
}
    </style>
</head>
<body>

    <?php include '_header.php'; ?>

    <div class="upload-container">
        <!-- Upload Header -->
        <div class="upload-header">
            <h1><i class="fas fa-plus"></i> Upload New Product</h1>
            <p>Add your product to the marketplace and start selling</p>
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
                <input type="hidden" name="action" value="upload_product">
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Product Name <span class="required">*</span></label>
                        <input type="text" name="name" required placeholder="Enter product name">
                    </div>
                    <div class="form-group">
                        <label>Category <span class="required">*</span></label>
                        <select name="category" required>
                            <option value="">Select category</option>
                            <option value="Appliances">Appliances</option>
                            <option value="Clothing">Clothing</option>
                            <option value="Books">Books</option>
                            <option value="Home & Garden">Home & Garden</option>
                            <option value="Sports">Sports</option>
                            <option value="Toys">Toys</option>
                            <option value="Shoes">Shoes</option>
                            <option value="Gadget">Gadget</option>
                            <option value="Stationery">Stationery</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Selling Price <span class="required">*</span></label>
                        <input type="number" name="price" step="0.01" min="0" required placeholder="₱0.00">
                    </div>
                    <div class="form-group">
                        <label>Original Price</label>
                        <input type="number" name="original_price" step="0.01" min="0" placeholder="₱0.00">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Stock Quantity <span class="required">*</span></label>
                        <input type="number" name="stock_quantity" min="0" required placeholder="0">
                    </div>
                    <div class="form-group">
                        <label>Colors (Optional)</label>
                        <div class="color-size-container">
                            <div class="color-size-inputs" id="color_inputs">
                                <input type="text" name="colors[]" placeholder="e.g., Red, Blue, Black" class="color-size-input">
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
                                        <label><input type="checkbox" name="sizes[]" value="XS"> XS</label>
                                        <label><input type="checkbox" name="sizes[]" value="S"> S</label>
                                        <label><input type="checkbox" name="sizes[]" value="M"> M</label>
                                        <label><input type="checkbox" name="sizes[]" value="L"> L</label>
                                        <label><input type="checkbox" name="sizes[]" value="XL"> XL</label>
                                    </div>
                                </div>
                                <div class="size-category-group">
                                    <label class="size-category-label">Shoe Sizes:</label>
                                    <div class="size-checkboxes">
                                        <label><input type="checkbox" name="sizes[]" value="36"> 36</label>
                                        <label><input type="checkbox" name="sizes[]" value="37"> 37</label>
                                        <label><input type="checkbox" name="sizes[]" value="38"> 38</label>
                                        <label><input type="checkbox" name="sizes[]" value="39"> 39</label>
                                        <label><input type="checkbox" name="sizes[]" value="40"> 40</label>
                                        <label><input type="checkbox" name="sizes[]" value="41"> 41</label>
                                        <label><input type="checkbox" name="sizes[]" value="42"> 42</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Product Images</label>
                        <div class="image-upload" onclick="document.getElementById('product_images').click()">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <p>Click to upload multiple images</p>
                            <small style="color: #666; display: block; margin-top: 10px;">First image will be the main product image</small>
                            <input type="file" id="product_images" name="product_images[]" accept="image/*" multiple style="display: none;" onchange="previewMultipleImages(event)">
                        </div>
                        <div id="image_preview_container" class="image-preview-container"></div>
                    </div>
                </div>

                <div class="form-row full">
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" placeholder="Describe your product (features, condition, etc.)"></textarea>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-upload"></i> Upload Product
                    </button>
                    <a href="seller_dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
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
let uploadedImages = [];
let draggedIndex = null;

// =========================
// PREVIEW IMAGES
// =========================
function previewMultipleImages(event) {
    uploadedImages = Array.from(event.target.files);
    renderImages();
}

// =========================
// RENDER IMAGES (STABLE + SMOOTH)
// =========================
function renderImages() {
    const container = document.getElementById('image_preview_container');
    container.innerHTML = '';

    uploadedImages.forEach((file, index) => {
        const previewItem = document.createElement('div');
        previewItem.className = 'image-preview-item';
        previewItem.draggable = true;
        previewItem.dataset.index = index;

        // MAIN IMAGE ALWAYS FIRST ONLY
        if (index === 0) {
            previewItem.classList.add('main-image');
        }

        const imgURL = URL.createObjectURL(file);

        previewItem.innerHTML = `
            <img src="${imgURL}">
            <button type="button" class="remove-btn">
                <i class="fas fa-times"></i>
            </button>
        `;

        // REMOVE
        previewItem.querySelector('.remove-btn').onclick = () => {
            removeImage(index);
        };

        // DRAG START
        previewItem.addEventListener('dragstart', (e) => {
            draggedIndex = index;
            e.dataTransfer.effectAllowed = "move";
            previewItem.classList.add('dragging');
        });

        // DRAG END
        previewItem.addEventListener('dragend', () => {
            previewItem.classList.remove('dragging');
        });

        // DRAG OVER
        previewItem.addEventListener('dragover', (e) => {
            e.preventDefault();
        });

        // DROP
        previewItem.addEventListener('drop', (e) => {
            e.preventDefault();

            const targetIndex = index;

            if (draggedIndex === null || draggedIndex === targetIndex) return;

            const movedItem = uploadedImages.splice(draggedIndex, 1)[0];
            uploadedImages.splice(targetIndex, 0, movedItem);

            // ⭐ FORCE MAIN IMAGE ALWAYS FIRST
            uploadedImages = sortMainFirst(uploadedImages);

            draggedIndex = null;
            updateFileInput();
            renderImages();
        });

        container.appendChild(previewItem);
    });
}

// =========================
// REMOVE IMAGE
// =========================
function removeImage(index) {
    uploadedImages.splice(index, 1);

    // KEEP MAIN IMAGE LOGIC SAFE
    uploadedImages = sortMainFirst(uploadedImages);

    updateFileInput();
    renderImages();
}

// =========================
// ALWAYS KEEP FIRST IMAGE AS MAIN
// =========================
function sortMainFirst(arr) {
    if (arr.length === 0) return arr;

    const first = arr[0];
    const rest = arr.slice(1);

    return [first, ...rest];
}

// =========================
// UPDATE FILE INPUT
// =========================
function updateFileInput() {
    const dt = new DataTransfer();

    uploadedImages.forEach(file => {
        dt.items.add(file);
    });

    document.getElementById('product_images').files = dt.files;
}

// =========================
// ADD COLOR INPUT
// =========================
function addColorInput() {
    const colorInputs = document.getElementById('color_inputs');

    const wrapper = document.createElement('div');
    wrapper.className = 'color-input-wrapper';

    const input = document.createElement('input');
    input.type = 'text';
    input.name = 'colors[]';
    input.placeholder = 'e.g., Red, Blue';
    input.className = 'color-size-input';

    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'remove-color-btn';
    btn.innerHTML = '<i class="fas fa-times"></i>';
    btn.onclick = () => wrapper.remove();

    wrapper.appendChild(input);
    wrapper.appendChild(btn);
    colorInputs.appendChild(wrapper);
}

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
</script>
</body>
</html>
