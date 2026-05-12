<?php
session_start();
require_once 'db.php';

// Get product ID from URL
$product_id = $_GET['id'] ?? 0;
if (!$product_id) {
    header("Location: shop.php");
    exit();
}

// Get product details
$stmt = $conn->prepare("SELECT p.*, u.first_name, u.last_name FROM products p LEFT JOIN users u ON p.seller_id = u.id WHERE p.id = ? AND p.status = 'active'");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();
$product = $result->fetch_assoc();
$stmt->close();

if (!$product) {
    header("Location: shop.php");
    exit();
}

// Get related products (same category, excluding current product)
$related_stmt = $conn->prepare("SELECT id, name, price, original_price, image_url FROM products WHERE category = ? AND id != ? AND status = 'active' ORDER BY RAND() LIMIT 4");
$related_stmt->bind_param("si", $product['category'], $product_id);
$related_stmt->execute();
$related_result = $related_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($product['name']); ?> - ReBuy</title>
    <link rel="icon" type="image/x-icon" href="../../assets/logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../css/header-footer.css">
    <style>
        .product-container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 60px;
        }
        
        .product-images {
            position: relative;
        }
        
        .main-image {
            width: 100%;
            height: 500px;
            background: #f8f9fa;
            border-radius: 12px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .main-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .product-info {
            padding: 20px 0;
        }
        
        .product-title {
            font-size: 32px;
            font-weight: 700;
            color: #2d5016;
            margin-bottom: 15px;
        }
        
        .product-price {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .current-price {
            font-size: 28px;
            font-weight: 700;
            color: #2d5016;
        }
        
        .original-price {
            font-size: 20px;
            color: #999;
            text-decoration: line-through;
        }
        
        .discount-badge {
            background: #ff4444;
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 14px;
        }
        
        .product-meta {
            display: flex;
            gap: 30px;
            margin-bottom: 25px;
            padding: 20px 0;
            border-top: 1px solid #eee;
            border-bottom: 1px solid #eee;
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #666;
        }
        
        .meta-item i {
            color: #2d5016;
        }
        
        .product-description {
            margin-bottom: 30px;
            line-height: 1.6;
            color: #555;
        }
        
        .seller-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        
        .seller-name {
            font-weight: 600;
            color: #2d5016;
        }
        
        .add-to-cart-section {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .quantity-selector {
            display: flex;
            align-items: center;
            border: 2px solid #ddd;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .quantity-btn {
            background: #f8f9fa;
            border: none;
            padding: 12px 16px;
            cursor: pointer;
            font-size: 18px;
            transition: background 0.3s;
        }
        
        .quantity-btn:hover {
            background: #e9ecef;
        }
        
        .quantity-input {
            border: none;
            width: 60px;
            text-align: center;
            font-size: 16px;
            font-weight: 600;
        }
        
        .btn-add-cart {
            background: #2d5016;
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-add-cart:hover {
            background: #1e3009;
        }
        
        .btn-buy-now {
            background: #ff6b35;
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .btn-buy-now:hover {
            background: #e55a2b;
        }
        
        .related-products {
            max-width: 1200px;
            margin: 60px auto;
            padding: 0 20px;
        }
        
        .related-products h2 {
            font-size: 28px;
            color: #2d5016;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .related-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
        }
        
        .related-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
            cursor: pointer;
        }
        
        .related-card:hover {
            transform: translateY(-5px);
        }
        
        .related-image {
            height: 200px;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        
        .related-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .related-info {
            padding: 15px;
        }
        
        .related-name {
            font-weight: 600;
            margin-bottom: 8px;
            color: #333;
        }
        
        .related-price {
            color: #2d5016;
            font-weight: 700;
            font-size: 18px;
        }
        
        @media (max-width: 768px) {
            .product-container {
                grid-template-columns: 1fr;
                gap: 30px;
            }
            
            .main-image {
                height: 300px;
            }
            
            .product-title {
                font-size: 24px;
            }
            
            .add-to-cart-section {
                flex-direction: column;
            }
            
            .btn-add-cart, .btn-buy-now {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <?php include '_header.php'; ?>

    <div class="product-container">
        <div class="product-images">
            <div class="main-image">
                <img src="<?php echo !empty($product['image_url']) ? '../' . htmlspecialchars($product['image_url']) : 'https://images.unsplash.com/photo-1586023492125-27b2c045efd7?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=400&q=80'; ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
            </div>
        </div>

        <div class="product-info">
            <h1 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h1>
            
            <div class="product-price">
                <span class="current-price">₱<?php echo number_format($product['price'], 2); ?></span>
                <?php if ($product['original_price'] && $product['original_price'] > $product['price']): ?>
                    <span class="original-price">₱<?php echo number_format($product['original_price'], 2); ?></span>
                    <span class="discount-badge">-<?php echo round((($product['original_price'] - $product['price']) / $product['original_price']) * 100); ?>%</span>
                <?php endif; ?>
            </div>

            <div class="product-meta">
                <div class="meta-item">
                    <i class="fas fa-tag"></i>
                    <span><?php echo htmlspecialchars(ucfirst($product['category'])); ?></span>
                </div>
                <div class="meta-item">
                    <i class="fas fa-box"></i>
                    <span><?php echo $product['stock_quantity']; ?> in stock</span>
                </div>
                <div class="meta-item">
                    <i class="fas fa-star"></i>
                    <span><?php echo number_format($product['rating'] ?? 0, 1); ?> rating</span>
                </div>
            </div>

            <div class="product-description">
                <?php echo nl2br(htmlspecialchars($product['description'] ?? 'No description available for this product.')); ?>
            </div>

            <?php if ($product['first_name'] || $product['last_name']): ?>
                <div class="seller-info">
                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
                        <i class="fas fa-store"></i>
                        <span class="seller-name">Sold by: <?php echo htmlspecialchars($product['first_name'] . ' ' . $product['last_name']); ?></span>
                    </div>
                    <div style="color: #666; font-size: 14px;">
                        <i class="fas fa-check-circle" style="color: #28a745;"></i> Verified Seller
                    </div>
                </div>
            <?php endif; ?>

            <div class="add-to-cart-section">
                <div class="quantity-selector">
                    <button class="quantity-btn" onclick="changeQuantity(-1)">-</button>
                    <input type="number" id="quantity" class="quantity-input" value="1" min="1" max="<?php echo $product['stock_quantity']; ?>">
                    <button class="quantity-btn" onclick="changeQuantity(1)">+</button>
                </div>
                
                <button class="btn-add-cart" onclick="addToCart(<?php echo $product_id; ?>)">
                    <i class="fas fa-shopping-cart"></i> Add to Cart
                </button>
                
                <button class="btn-buy-now" onclick="buyNow(<?php echo $product_id; ?>)">
                    Buy Now
                </button>
            </div>
        </div>
    </div>

    <?php if ($related_result->num_rows > 0): ?>
    <div class="related-products">
        <h2>You May Also Like</h2>
        <div class="related-grid">
            <?php while ($related = $related_result->fetch_assoc()): ?>
                <div class="related-card" onclick="window.location.href='product_details.php?id=<?php echo $related['id']; ?>'">
                    <div class="related-image">
                        <img src="<?php echo !empty($related['image_url']) ? '../' . htmlspecialchars($related['image_url']) : 'https://images.unsplash.com/photo-1555041469-a586c61ea9bc?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=400&q=80'; ?>" alt="<?php echo htmlspecialchars($related['name']); ?>">
                    </div>
                    <div class="related-info">
                        <div class="related-name"><?php echo htmlspecialchars($related['name']); ?></div>
                        <div class="related-price">₱<?php echo number_format($related['price'], 2); ?></div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    </div>
    <?php endif; ?>

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
        function changeQuantity(change) {
            const input = document.getElementById('quantity');
            const newValue = parseInt(input.value) + change;
            const maxValue = parseInt(input.max);
            
            if (newValue >= 1 && newValue <= maxValue) {
                input.value = newValue;
            }
        }

        function addToCart(productId) {
            const quantity = document.getElementById('quantity').value;
            
            // Show success feedback
            const button = event.target;
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="fas fa-check"></i> Added!';
            button.style.background = '#28a745';
            
            setTimeout(() => {
                button.innerHTML = originalText;
                button.style.background = '';
            }, 2000);
            
            // Here you would implement actual cart functionality
            console.log('Adding to cart:', productId, 'Quantity:', quantity);
        }

        function buyNow(productId) {
            const quantity = document.getElementById('quantity').value;
            
            // Here you would implement buy now functionality
            // For now, redirect to checkout
            window.location.href = 'checkout.php?product_id=' + productId + '&quantity=' + quantity;
        }
    </script>
</body>
</html>
