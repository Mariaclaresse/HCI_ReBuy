<?php
session_start();
include 'db.php';

// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Handle test notification creation
if (isset($_GET['test_notif']) && $_GET['test_notif'] == '1') {
    include 'notification_functions.php';
    createNotification(
        $user_id,
        '🎉 Test Notification',
        'This is a test notification to verify the system is working properly! You can see this in your notification panel.',
        'system'
    );
}

// Check if user is a seller
$is_seller = false;
$seller_check = $conn->query("SHOW COLUMNS FROM users LIKE 'is_seller'");
if ($seller_check->num_rows > 0) {
    $stmt = $conn->prepare("SELECT is_seller FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user_seller = $result->fetch_assoc();
    $stmt->close();
    
    $is_seller = isset($user_seller['is_seller']) && $user_seller['is_seller'] == 1;
}

// Get user info
$stmt = $conn->prepare("SELECT first_name, last_name, email FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get order stats
$stmt = $conn->prepare("
    SELECT COUNT(*) as total_orders, SUM(total_amount) as total_spent
    FROM orders WHERE user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get recent orders
$stmt = $conn->prepare("
    SELECT id, order_date, total_amount, status FROM orders
    WHERE user_id = ? ORDER BY order_date DESC LIMIT 5
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$recent_orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get hot products with ratings 3.5-5 and minimum 5 reviews
$stmt = $conn->prepare("
    SELECT p.id, p.name, p.price, p.original_price, p.image_url, p.rating, p.category,
           (SELECT COUNT(*) FROM reviews WHERE product_id = p.id) as review_count
    FROM products p 
    WHERE p.rating >= 3.5 AND p.stock > 0 
    AND (SELECT COUNT(*) FROM reviews WHERE product_id = p.id) >= 5
    ORDER BY p.rating DESC, p.created_at DESC 
    LIMIT 8
");
$stmt->execute();
$hot_products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
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
        .hero-banner {
            background: linear-gradient(135deg, #2d5016 0%, #4a7c2e 100%);
            color: white;
            padding: 80px 0;
            margin-bottom: 60px;
            position: relative;
            overflow: hidden;
        }
        .carousel-container {
            position: relative;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 40px;
            height: 500px;
        }
        .carousel-wrapper {
            position: relative;
            height: 100%;
            overflow: hidden;
            border-radius: 15px;
        }
        .carousel-slides {
            position: relative;
            height: 100%;
        }
        .carousel-slide {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            gap: 60px;
            opacity: 0;
            transform: translateX(50%);
            transition: all 0.6s ease;
            pointer-events: none;
        }
        .carousel-slide.active {
            opacity: 1;
            transform: translateX(0);
            position: relative;
            pointer-events: auto;
        }
        .carousel-slide.exiting {
            opacity: 0;
            transform: translateX(-100%);
        }
        @keyframes slideInUp {
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
        .carousel-nav {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(255,255,255,0.2);
            color: white;
            border: none;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 18px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 5;
            backdrop-filter: blur(10px);
        }
        .carousel-nav:hover {
            background: rgba(255,255,255,0.3);
            transform: translateY(-50%) scale(1.1);
        }
        .carousel-prev {
            left: 20px;
        }
        .carousel-next {
            right: 20px;
        }
        .carousel-indicators {
            position: absolute;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 12px;
            z-index: 5;
        }
        .indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: rgba(255,255,255,0.3);
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            backdrop-filter: blur(10px);
        }
        .indicator:hover {
            background: rgba(255,255,255,0.5);
            transform: scale(1.2);
        }
        .indicator.active {
            background: white;
            transform: scale(1.3);
        }
        .hero-text {
            flex: 1;
            padding: 40px;
        }
        .hero-text h1 {
            font-size: 48px;
            margin-bottom: 20px;
            font-weight: 700;
        }
        .hero-text p {
            font-size: 18px;
            margin-bottom: 30px;
        }
        .hero-image {
            flex: 1;
            text-align: center;
            padding: 40px;
        }
        .hero-image img {
            max-width: 100%;
            height: 400px;
            object-fit: cover;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
        }
        @keyframes slideInUp {
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
        .btn-shop {
            display: inline-block;
            background: white;
            color: #2d5016;
            padding: 15px 40px;
            border-radius: 30px;
            text-decoration: none;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        .btn-shop:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        .section-title {
            text-align: center;
            font-size: 32px;
            margin-bottom: 40px;
            color: #333;
            font-weight: 600;
        }
        .categories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 25px;
            margin-bottom: 60px;
            max-width: 1400px;
            margin-left: auto;
            margin-right: auto;
            padding: 0 40px;
        }
        .category-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            position: relative;
        }
        .category-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(45, 80, 22, 0.1) 0%, rgba(74, 124, 46, 0.1) 100%);
            opacity: 0;
            transition: opacity 0.4s ease;
            z-index: 1;
        }
        .category-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 15px 40px rgba(45, 80, 22, 0.15);
        }
        .category-card:hover::before {
            opacity: 1;
        }
        .category-card:hover img {
            transform: scale(1.1);
        }
        .category-card:hover h3 {
            color: #2d5016;
            font-weight: 600;
        }
        .category-card img {
            width: 100%;
            height: 180px;
            object-fit: cover;
            transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .category-card h3 {
            padding: 18px;
            margin: 0;
            text-align: center;
            color: #333;
            font-size: 16px;
            font-weight: 500;
            transition: all 0.3s ease;
            position: relative;
            z-index: 2;
            background: white;
        }
        .rooms-section {
            margin-bottom: 60px;
            padding: 0 40px;
        }
        .rooms-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            max-width: 1200px;
            margin: 0 auto;
        }
        .room-card {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .room-card img {
            width: 100%;
            height: 250px;
            object-fit: cover;
        }
        .room-content {
            padding: 20px;
            text-align: center;
        }
        .room-content h3 {
            margin: 0 0 10px 0;
            color: #333;
            font-size: 20px;
        }
        .room-content p {
            color: #666;
            margin-bottom: 20px;
        }
        .hot-products {
            background: #f8f9fa;
            padding: 60px 0;
        }
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 40px;
        }
        .product-card {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            position: relative;
        }
        .product-card img {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }
        .discount-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #ff4444;
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .product-info {
            padding: 20px;
        }
        .product-info h4 {
            margin: 0 0 10px 0;
            color: #333;
            font-size: 16px;
        }
        .product-price {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }
        .current-price {
            font-size: 18px;
            font-weight: 600;
            color: #2d5016;
        }
        .original-price {
            font-size: 14px;
            color: #999;
            text-decoration: line-through;
        }
        .btn-add-cart {
            width: 100%;
            background: #2d5016;
            color: white;
            border: none;
            padding: 12px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.3s ease;
        }
        .btn-add-cart:hover {
            background: #4a7c2e;
        }
        .rating-stars {
            color: #ffc107;
            font-size: 14px;
            margin-bottom: 10px;
        }
        .rating-stars .empty {
            color: #ddd;
        }
        .product-category {
            color: #666;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }
    </style>
</head>
<body>
    <div class="page-wrapper">
        <?php include '_header.php'; ?>

        <?php if ($is_seller): ?>
        <!-- Seller Welcome Message -->
        <div style="background: linear-gradient(135deg, #2d5016 0%, #4a7c2e 100%); color: white; padding: 30px 40px; text-align: center; margin-bottom: 0;">
            <h1 style="font-size: 36px; margin-bottom: 10px; font-weight: 700;">
                <i class="fas fa-store" style="margin-right: 15px;"></i>Welcome Seller!
            </h1>
            <p style="font-size: 18px; opacity: 0.9; margin: 0;">Manage your store and track your sales performance</p>
            <div style="margin-top: 20px;">
                <a href="seller_dashboard.php" style="background: #f4c430; color: #333; padding: 12px 30px; border-radius: 6px; text-decoration: none; font-weight: 600; display: inline-block; transition: all 0.3s;">
                    <i class="fas fa-tachometer-alt" style="margin-right: 8px;"></i>Go to Seller Dashboard
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Hero Banner Carousel -->
        <section class="hero-banner">
            <div class="carousel-container">
                <div class="carousel-wrapper">
                    <div class="carousel-slides">
                        <!-- Slide 1 -->
                        <div class="carousel-slide active">
                            <div class="hero-text">
                                <h1>Welcome to ReBuy</h1>
                                <p>Buy quality second-hand items for less while supporting a more sustainable lifestyle. Save money and reduce waste with our curated collection of pre-loved treasures.</p>
                                <a href="shop.php" class="btn-shop">Start Shopping</a>
                            </div>
                            <div class="hero-image">
                                <img src="https://images.unsplash.com/photo-1556742049-0cfed4f6a45d?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1000&q=80" alt="Shopping Cart">
                            </div>
                        </div>

                        <!-- Slide 2 -->
                        <div class="carousel-slide">
                            <div class="hero-text">
                                <h1>Special Sales & Promos</h1>
                                <p>Enjoy exclusive discounts and flash sales on premium second-hand items. Limited-time offers that you don't want to miss!</p>
                                <a href="promotion.php" class="btn-shop">View Promotions</a>
                            </div>
                            <div class="hero-image">
                                <img src="https://images.unsplash.com/photo-1607082348824-0a96f2a4b9da?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1000&q=80" alt="Sale Banner">
                            </div>
                        </div>

                        <!-- Slide 3 -->
                        <div class="carousel-slide">
                            <div class="hero-text">
                                <h1>Upcoming Events</h1>
                                <p>Join our exclusive seller meetups, community swap events, and special product launches. Stay connected with the ReBuy community!</p>
                                <a href="event.php" class="btn-shop">View Events</a>
                            </div>
                            <div class="hero-image">
                                <img src="https://images.unsplash.com/photo-1511578314322-379afb476865?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1000&q=80" alt="Community Event">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Navigation Buttons -->
                <button class="carousel-nav carousel-prev" onclick="changeSlide(-1)">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <button class="carousel-nav carousel-next" onclick="changeSlide(1)">
                    <i class="fas fa-chevron-right"></i>
                </button>

                <!-- Indicators -->
                <div class="carousel-indicators">
                    <span class="indicator active" onclick="goToSlide(0)"></span>
                    <span class="indicator" onclick="goToSlide(1)"></span>
                    <span class="indicator" onclick="goToSlide(2)"></span>
                </div>
            </div>
        </section>

        <!-- Shop by Categories -->
        <section>
            <h2 class="section-title">Shop by categories</h2>
            <div class="categories-grid">
                <div class="category-card" onclick="window.location.href='shop.php?category=Electronics'">
                    <img src="../../assets/appliances.jpg" alt="Electronics">
                    <h3>Appliances</h3>
                </div>
                <div class="category-card" onclick="window.location.href='shop.php?category=Clothing'">
                    <img src="../../assets/clothing.jpg" alt="Clothing">
                    <h3>Clothing</h3>
                </div>
                <div class="category-card" onclick="window.location.href='shop.php?category=Books'">
                    <img src="../../assets/books.jpg" alt="Books">
                    <h3>Books</h3>
                </div>
                <div class="category-card" onclick="window.location.href='shop.php?category=Home & Garden'">
                    <img src="../../assets/home.jpg" alt="Home & Garden">
                    <h3>Home & Garden</h3>
                </div>
                <div class="category-card" onclick="window.location.href='shop.php?category=Sports'">
                    <img src="../../assets/sports.jpg" alt="Sports">
                    <h3>Sports</h3>
                </div>
                <div class="category-card" onclick="window.location.href='shop.php?category=Toys'">
                    <img src="../../assets/toys.jpg" alt="Toys">
                    <h3>Toys</h3>
                </div>
                <div class="category-card" onclick="window.location.href='shop.php?category=Shoes'">
                    <img src="../../assets/shoes.jpg" alt="Shoes">
                    <h3>Shoes</h3>
                </div>
                <div class="category-card" onclick="window.location.href='shop.php?category=Gadgets'">
                    <img src="../../assets/gadgets.jpg" alt="Gadgets">
                    <h3>Gadgets</h3>
                </div>
                <div class="category-card" onclick="window.location.href='shop.php?category=Stationery'">
                    <img src="../../assets/stationery.jpg" alt="Stationery">
                    <h3>Stationery</h3>
                </div>
                <div class="category-card" onclick="window.location.href='shop.php?category=Other'">
                    <img src="../../assets/other.jpg" alt="Other">
                    <h3>Other</h3>
                </div>
            </div>
        </section>

        <!-- Hot Products -->
        <section class="hot-products">
            <h2 class="section-title">Hot Products</h2>
            <div class="products-grid">
                <?php if (!empty($hot_products)): ?>
                    <?php foreach ($hot_products as $product): ?>
                        <div class="product-card">
                            <?php if (!empty($product['image_url'])): ?>
                                <img src="<?php echo htmlspecialchars($product['image_url']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                            <?php else: ?>
                                <img src="https://images.unsplash.com/photo-1560472354-b33ff0c44a43?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=400&q=80" alt="<?php echo htmlspecialchars($product['name']); ?>">
                            <?php endif; ?>
                            
                            <?php if (!empty($product['original_price']) && $product['original_price'] > $product['price']): ?>
                                <div class="discount-badge">
                                    <?php echo round((($product['original_price'] - $product['price']) / $product['original_price']) * 100); ?>% OFF
                                </div>
                            <?php endif; ?>
                            
                            <div class="product-info">
                                <div class="product-category"><?php echo htmlspecialchars($product['category'] ?? 'General'); ?></div>
                                <h4><?php echo htmlspecialchars($product['name']); ?></h4>
                                
                                <div class="rating-stars">
                                    <?php 
                                    $rating = round($product['rating']);
                                    for ($i = 1; $i <= 5; $i++) {
                                        if ($i <= $rating) {
                                            echo '<i class="fas fa-star"></i>';
                                        } else {
                                            echo '<i class="fas fa-star empty"></i>';
                                        }
                                    }
                                    ?>
                                    <span style="color: #666; font-size: 12px; margin-left: 5px;">
                                        (<?php echo number_format($product['rating'], 1); ?>)
                                    </span>
                                </div>
                                
                                <div class="product-price">
                                    <span class="current-price">₱<?php echo number_format($product['price'], 2); ?></span>
                                    <?php if (!empty($product['original_price']) && $product['original_price'] > $product['price']): ?>
                                        <span class="original-price">₱<?php echo number_format($product['original_price'], 2); ?></span>
                                    <?php endif; ?>
                                </div>
                                
                                <button class="btn-add-cart" onclick="addToCart(<?php echo $product['id']; ?>)">
                                    <i class="fas fa-shopping-cart"></i> Add to Cart
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="grid-column: 1 / -1; text-align: center; padding: 60px 20px; color: #666;">
                        <i class="fas fa-fire" style="font-size: 48px; margin-bottom: 20px; opacity: 0.3;"></i>
                        <h3 style="margin-bottom: 10px;">No hot products available</h3>
                        <p>Check back soon for products with high ratings!</p>
                    </div>
                <?php endif; ?>
            </div>
        </section>

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
    </div>

    <script src="../js/notification.js"></script>
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

        // Add to cart functionality
        function addToCart(productId) {
            fetch('add_to_cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'product_id=' + productId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success notification
                    showNotification('Product added to cart successfully!', 'success');
                } else {
                    showNotification(data.message || 'Failed to add product to cart', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('An error occurred. Please try again.', 'error');
            });
        }

        // Notification function
        function showNotification(message, type = 'info') {
            // Create notification element
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: ${type === 'success' ? '#28a745' : type === 'error' ? '#dc3545' : '#17a2b8'};
                color: white;
                padding: 15px 20px;
                border-radius: 5px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                z-index: 9999;
                font-weight: 500;
                max-width: 300px;
                animation: slideInRight 0.3s ease;
            `;
            notification.textContent = message;
            
            // Add to page
            document.body.appendChild(notification);
            
            // Remove after 3 seconds
            setTimeout(() => {
                notification.style.animation = 'slideOutRight 0.3s ease';
                setTimeout(() => {
                    document.body.removeChild(notification);
                }, 300);
            }, 3000);
        }

        // Add animations
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideInRight {
                from {
                    transform: translateX(100%);
                    opacity: 0;
                }
                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }
            @keyframes slideOutRight {
                from {
                    transform: translateX(0);
                    opacity: 1;
                }
                to {
                    transform: translateX(100%);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(style);

        // Carousel functionality
        let currentSlide = 0;
        const slides = document.querySelectorAll('.carousel-slide');
        const indicators = document.querySelectorAll('.indicator');
        const totalSlides = slides.length;
        let isTransitioning = false;

        function showSlide(index) {
            if (isTransitioning) return;
            isTransitioning = true;

            // Add exiting class to current slide
            slides[currentSlide].classList.add('exiting');
            indicators.forEach(indicator => indicator.classList.remove('active'));

            // Update current slide
            const prevSlide = currentSlide;
            currentSlide = index;

            // Add active class to new slide
            slides[currentSlide].classList.add('active');
            indicators[currentSlide].classList.add('active');

            // Remove exiting class after transition
            setTimeout(() => {
                slides[prevSlide].classList.remove('exiting', 'active');
                isTransitioning = false;
            }, 600);
        }

        function changeSlide(direction) {
            let newSlide = currentSlide + direction;
            
            if (newSlide >= totalSlides) {
                newSlide = 0;
            } else if (newSlide < 0) {
                newSlide = totalSlides - 1;
            }
            
            showSlide(newSlide);
        }

        function goToSlide(index) {
            if (index === currentSlide) return;
            showSlide(index);
        }

        // Auto-rotate carousel
        let autoRotateInterval;
        
        function startAutoRotate() {
            autoRotateInterval = setInterval(() => {
                changeSlide(1);
            }, 5000);
        }
        
        function stopAutoRotate() {
            clearInterval(autoRotateInterval);
        }

        // Start auto-rotation
        startAutoRotate();

        // Pause auto-rotation on hover
        const carouselContainer = document.querySelector('.carousel-container');
        
        carouselContainer.addEventListener('mouseenter', () => {
            stopAutoRotate();
        });

        carouselContainer.addEventListener('mouseleave', () => {
            startAutoRotate();
        });

        // Keyboard navigation
        document.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowLeft') {
                changeSlide(-1);
                stopAutoRotate();
                startAutoRotate();
            } else if (e.key === 'ArrowRight') {
                changeSlide(1);
                stopAutoRotate();
                startAutoRotate();
            }
        });

        // Touch support for mobile
        let touchStartX = 0;
        let touchEndX = 0;
        
        carouselContainer.addEventListener('touchstart', (e) => {
            touchStartX = e.changedTouches[0].screenX;
        });
        
        carouselContainer.addEventListener('touchend', (e) => {
            touchEndX = e.changedTouches[0].screenX;
            handleSwipe();
        });
        
        function handleSwipe() {
            const swipeThreshold = 50;
            const diff = touchStartX - touchEndX;
            
            if (Math.abs(diff) > swipeThreshold) {
                if (diff > 0) {
                    changeSlide(1); // Swipe left, go to next slide
                } else {
                    changeSlide(-1); // Swipe right, go to previous slide
                }
                stopAutoRotate();
                startAutoRotate();
            }
        }

        // Prevent back navigation to login page
        history.pushState(null, null, location.href);
        window.onpopstate = function() {
            history.go(1);
        };
    </script>
</body>
</html>
