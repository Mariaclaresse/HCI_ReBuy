<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get user info
$stmt = $conn->prepare("SELECT first_name, last_name, email FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Promotions - ReBuy</title>
    <link rel="icon" type="image/x-icon" href="../../assets/logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../css/header-footer.css">
    <style>
        .hero-banner {
            background: linear-gradient(135deg, #2d5016 0%, #4a7c2e 100%);
            color: white;
            padding: 80px 40px;
            text-align: center;
            margin-bottom: 60px;
        }
        .hero-banner h1 {
            font-size: 48px;
            margin-bottom: 20px;
            font-weight: 700;
        }
        .hero-banner p {
            font-size: 18px;
            max-width: 800px;
            margin: 0 auto;
            opacity: 0.9;
        }
        .promotions-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 40px 60px;
        }
        .promo-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
            margin-bottom: 30px;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .promo-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 40px rgba(45, 80, 22, 0.15);
        }
        .promo-image {
            width: 100%;
            height: 250px;
            object-fit: cover;
        }
        .promo-content {
            padding: 30px;
        }
        .promo-content h3 {
            font-size: 24px;
            color: #2d5016;
            margin-bottom: 15px;
            font-weight: 600;
        }
        .promo-content p {
            color: #666;
            line-height: 1.6;
            margin-bottom: 20px;
        }
        .promo-badge {
            display: inline-block;
            background: #ff4444;
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 15px;
        }
        .promo-date {
            color: #999;
            font-size: 14px;
            margin-bottom: 15px;
        }
        .btn-shop {
            display: inline-block;
            background: #2d5016;
            color: white;
            padding: 12px 30px;
            border-radius: 30px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-shop:hover {
            background: #4a7c2e;
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <div class="page-wrapper">
        <?php include '_header.php'; ?>

        <!-- Hero Banner -->
        <section class="hero-banner">
            <h1>Special Sales & Promos</h1>
            <p>Enjoy exclusive discounts and flash sales on premium second-hand items. Limited-time offers that you don't want to miss!</p>
        </section>

        <!-- Promotions Container -->
        <div class="promotions-container">
            <!-- Promo 1 -->
            <div class="promo-card">
                <img src="https://images.unsplash.com/photo-1607082348824-0a96f2a4b9da?ixlib=rb-4.0.3&auto=format&fit=crop&w=1200&q=80" alt="Flash Sale" class="promo-image">
                <div class="promo-content">
                    <span class="promo-badge">FLASH SALE</span>
                    <div class="promo-date"><i class="fas fa-calendar-alt"></i> Valid until: December 31, 2026</div>
                    <h3>Up to 50% Off on Electronics</h3>
                    <p>Get amazing deals on smartphones, laptops, tablets, and more. Don't miss out on these limited-time offers on premium second-hand electronics in excellent condition.</p>
                    <a href="shop.php?category=Electronics" class="btn-shop">Shop Now</a>
                </div>
            </div>

            <!-- Promo 2 -->
            <div class="promo-card">
                <img src="https://images.unsplash.com/photo-1445205170230-053b83016050?ixlib=rb-4.0.3&auto=format&fit=crop&w=1200&q=80" alt="Clothing Sale" class="promo-image">
                <div class="promo-content">
                    <span class="promo-badge">SEASONAL SALE</span>
                    <div class="promo-date"><i class="fas fa-calendar-alt"></i> Valid until: January 15, 2027</div>
                    <h3>Buy 1 Get 1 Free on Clothing</h3>
                    <p>Refresh your wardrobe with our special BOGO offer. Choose from a wide selection of pre-loved clothing items in great condition. Mix and match styles!</p>
                    <a href="shop.php?category=Clothing" class="btn-shop">Shop Now</a>
                </div>
            </div>

            <!-- Promo 3 -->
            <div class="promo-card">
                <img src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?ixlib=rb-4.0.3&auto=format&fit=crop&w=1200&q=80" alt="Book Sale" class="promo-image">
                <div class="promo-content">
                    <span class="promo-badge">WEEKEND SPECIAL</span>
                    <div class="promo-date"><i class="fas fa-calendar-alt"></i> Every Weekend</div>
                    <h3>₱99 Books Collection</h3>
                    <p>Discover your next favorite read from our curated collection of books at unbeatable prices. Fiction, non-fiction, educational, and more - all for just ₱99!</p>
                    <a href="shop.php?category=Books" class="btn-shop">Shop Now</a>
                </div>
            </div>

            <!-- Promo 4 -->
            <div class="promo-card">
                <img src="https://images.unsplash.com/photo-1571019613454-1cb2f99b2d8b?ixlib=rb-4.0.3&auto=format&fit=crop&w=1200&q=80" alt="Sports Sale" class="promo-image">
                <div class="promo-content">
                    <span class="promo-badge">NEW YEAR SALE</span>
                    <div class="promo-date"><i class="fas fa-calendar-alt"></i> Valid until: February 28, 2027</div>
                    <h3>30% Off on Sports Equipment</h3>
                    <p>Kickstart your fitness journey with discounted sports gear. From gym equipment to outdoor gear, we have everything you need to stay active and healthy.</p>
                    <a href="shop.php?category=Sports" class="btn-shop">Shop Now</a>
                </div>
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
    </script>
</body>
</html>
