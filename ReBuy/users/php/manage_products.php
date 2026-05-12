<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'db.php';
$user_id = $_SESSION['user_id'];

// Check if user is a seller
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

// Handle delete product
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $product_id = $_GET['delete'];
    
    // Check if product belongs to seller
    $check_stmt = $conn->prepare("SELECT id, image_url FROM products WHERE id = ? AND seller_id = ?");
    $check_stmt->bind_param("ii", $product_id, $user_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $product = $check_result->fetch_assoc();
        
        // Delete product image if exists
        if (!empty($product['image_url'])) {
            $image_path = '../' . $product['image_url'];
            if (file_exists($image_path)) {
                unlink($image_path);
            }
        }
        
        // Delete product
        $delete_stmt = $conn->prepare("DELETE FROM products WHERE id = ? AND seller_id = ?");
        $delete_stmt->bind_param("ii", $product_id, $user_id);
        $delete_stmt->execute();
        $delete_stmt->close();
        
        header("Location: manage_products.php?deleted=1");
        exit();
    }
    $check_stmt->close();
}

// Stock updates are now handled via AJAX in ajax_stock_update.php
// No traditional form handling needed to prevent page reloads

// Get seller's products
$products_stmt = $conn->prepare("SELECT * FROM products WHERE seller_id = ? ORDER BY created_at DESC");
$products_stmt->bind_param("i", $user_id);
$products_stmt->execute();
$products = $products_stmt->get_result();
$products_stmt->close();
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
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            color: var(--primary-color);
            margin: 0;
            font-size: 28px;
        }

        .btn {
            padding: 12px 25px;
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
        }

        .btn-secondary {
            background: var(--secondary-color);
            color: #333;
        }

        .btn-secondary:hover {
            background: #e6b800;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
        }

        .btn-edit {
            background: #17a2b8;
            color: white;
        }

        .btn-edit:hover {
            background: #138496;
        }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 30px;
        }

        .product-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: transform 0.3s;
            display: flex;
            flex-direction: column;
            height: 450px;
        }

        .product-card:hover {
            transform: translateY(-5px);
        }

        .product-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }

        .product-info {
            padding: 20px;
            display: flex;
            flex-direction: column;
            flex: 1;
        }

        .product-name {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin-bottom: 10px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
            min-height: 50px;
            max-height: 50px;
            line-height: 1.4;
        }

        .product-price {
            font-size: 20px;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 10px;
        }

        .product-meta {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }

        .product-meta span {
            font-size: 13px;
            color: #666;
        }

        .product-status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 15px;
        }

        .status-active { background: #d4edda; color: #155724; }
        .status-inactive { background: #f8d7da; color: #721c24; }
        .status-out_of_stock { background: #fff3cd; color: #856404; }

        .product-actions {
            display: flex;
            gap: 10px;
            margin-top: auto;
        }

        .product-actions .btn {
            flex: 1;
            justify-content: center;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }

        .no-products {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .no-products i {
            font-size: 60px;
            color: #ddd;
            margin-bottom: 20px;
        }

        .no-products h2 {
            color: #999;
            margin-bottom: 10px;
        }

        .no-products p {
            color: #ccc;
            margin-bottom: 20px;
        }
    </style>
    <script>
        function toggleStockForm(productId) {
            const form = document.getElementById('stock-form-' + productId);
            if (form.style.display === 'none') {
                form.style.display = 'block';
            } else {
                form.style.display = 'none';
            }
        }

        function updateStock(productId, event) {
            const stockAddInput = document.getElementById('stock-add-' + productId);
            const stockAdd = parseInt(stockAddInput.value);
            
            if (!stockAdd || stockAdd <= 0) {
                alert('Please enter a valid stock quantity');
                return;
            }

            // Prevent any form submission
            if (event) {
                event.preventDefault();
                event.stopPropagation();
            }

            // Show loading state
            const submitBtn = document.getElementById('submit-btn-' + productId);
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
            submitBtn.disabled = true;

            // Create AJAX request
            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'ajax_stock_update.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                    
                    try {
                        const response = JSON.parse(xhr.responseText);
                        
                        if (response.success) {
                            // Update stock display
                            updateStockDisplay(productId, response.new_stock);
                            
                            // Show success message
                            showNotification('Stock updated successfully! Added ' + response.added_stock + ' units.', 'success');
                            
                            // Reset form and hide
                            stockAddInput.value = '';
                            toggleStockForm(productId);
                        } else {
                            showNotification(response.message || 'Failed to update stock', 'error');
                        }
                    } catch (e) {
                        showNotification('An error occurred. Please try again.', 'error');
                    }
                }
            };
            
            xhr.send('action=update_stock&product_id=' + productId + '&stock_add=' + stockAdd);
        }

        // Add event listeners to prevent form submission
        document.addEventListener('DOMContentLoaded', function() {
            // Prevent any form submissions that might cause reload
            const stockForms = document.querySelectorAll('[id^="stock-form-"]');
            stockForms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    return false;
                });
            });
        });

        function updateStockDisplay(productId, newStock) {
            // Update stock display in the product card
            const stockElement = document.getElementById('stock-display-' + productId);
            if (stockElement) {
                stockElement.textContent = newStock;
            }
            
            // Update stock meta information
            const stockMeta = document.getElementById('stock-meta-' + productId);
            if (stockMeta) {
                stockMeta.textContent = 'Stock: ' + newStock;
            }
        }

        function showNotification(message, type) {
            // Create notification element
            const notification = document.createElement('div');
            notification.className = 'alert alert-' + (type === 'success' ? 'success' : 'error');
            notification.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 9999; min-width: 300px; animation: slideIn 0.3s ease;';
            notification.innerHTML = '<i class="fas fa-' + (type === 'success' ? 'check-circle' : 'exclamation-circle') + '"></i> ' + message;
            
            // Add to page
            document.body.appendChild(notification);
            
            // Remove after 3 seconds
            setTimeout(() => {
                notification.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => {
                    document.body.removeChild(notification);
                }, 300);
            }, 3000);
        }

        // Add CSS animations
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideIn {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            @keyframes slideOut {
                from { transform: translateX(0); opacity: 1; }
                to { transform: translateX(100%); opacity: 0; }
            }
            .alert-error {
                background: #f8d7da;
                color: #721c24;
                border-left: 4px solid #dc3545;
                padding: 15px 20px;
                border-radius: 8px;
                margin-bottom: 20px;
            }
        `;
        document.head.appendChild(style);
    </script>
</head>
<body>
    <?php include '_header.php'; ?>
    
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-box"></i> Manage Products</h1>
            <a href="upload_product.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Add New Product
            </a>
        </div>

        <?php if (isset($_GET['deleted'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> Product deleted successfully!
            </div>
        <?php endif; ?>

        
        <?php if ($products->num_rows > 0): ?>
            <div class="products-grid">
                <?php while ($product = $products->fetch_assoc()): ?>
                    <div class="product-card">
                        <?php if (!empty($product['image_url'])): ?>
                            <img src="<?php echo '../' . htmlspecialchars($product['image_url']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="product-image">
                        <?php else: ?>
                            <img src="https://images.unsplash.com/photo-1586023492125-27b2c045efd7?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80" alt="Product" class="product-image">
                        <?php endif; ?>
                        <div class="product-info">
                            <h3 class="product-name"><?php echo htmlspecialchars($product['name']); ?></h3>
                            <div class="product-price">₱<?php echo number_format($product['price'], 2); ?></div>
                            <div class="product-meta">
                                <span id="stock-meta-<?php echo $product['id']; ?>"><i class="fas fa-box"></i> Stock: <?php echo $product['stock_quantity']; ?></span>
                                <span><i class="fas fa-tag"></i> <?php echo htmlspecialchars($product['category']); ?></span>
                            </div>
                            <span class="product-status status-<?php echo $product['status']; ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $product['status'])); ?>
                            </span>
                            <div class="product-actions">
                                <a href="edit_product.php?id=<?php echo $product['id']; ?>" class="btn btn-edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="manage_products.php?delete=<?php echo $product['id']; ?>" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this product?');">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                            
                            <!-- Stock Management Form (Hidden by default) -->
                            <div id="stock-form-<?php echo $product['id']; ?>" style="display: none; margin-top: 15px; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                                <h4 style="margin: 0 0 10px 0; color: #333; font-size: 14px;">Add Stock</h4>
                                <div style="display: flex; gap: 10px; align-items: center;">
                                    <input type="number" id="stock-add-<?php echo $product['id']; ?>" min="1" max="9999" placeholder="Enter quantity" required
                                           style="flex: 1; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;"
                                           onkeypress="if(event.key === 'Enter') { updateStock(<?php echo $product['id']; ?>, event); return false; }">
                                    <button type="button" id="submit-btn-<?php echo $product['id']; ?>" class="btn btn-primary" onclick="updateStock(<?php echo $product['id']; ?>, event); return false;" style="padding: 8px 16px; font-size: 14px;">
                                        <i class="fas fa-check"></i>
                                    </button>
                                    <button type="button" class="btn btn-danger" onclick="toggleStockForm(<?php echo $product['id']; ?>)" 
                                            style="padding: 8px 16px; font-size: 14px;">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="no-products">
                <i class="fas fa-box-open"></i>
                <h2>No products yet</h2>
                <p>Start by adding your first product to sell</p>
                <a href="upload_product.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add Product
                </a>
            </div>
        <?php endif; ?>
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
    </script>
</body>
</html>
