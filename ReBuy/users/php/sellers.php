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

// Get all active sellers
$sellers_stmt = $conn->prepare("
    SELECT 
        u.id,
        u.username,
        u.first_name,
        u.last_name,
        u.shop_name,
        u.shop_description,
        u.shop_profile_pic,
        u.seller_approved_at,
        COUNT(DISTINCT p.id) as total_products,
        COUNT(DISTINCT CASE WHEN so.status = 'delivered' THEN so.id END) as total_sales
    FROM users u
    LEFT JOIN products p ON u.id = p.seller_id AND p.status = 'active'
    LEFT JOIN seller_orders so ON u.id = so.seller_id
    WHERE u.is_seller = 1
    AND u.seller_approved_at IS NOT NULL
    GROUP BY u.id
    ORDER BY total_sales DESC, total_products DESC
");
$sellers_stmt->execute();
$sellers = $sellers_stmt->get_result();
$sellers_stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ReBuy - Sellers</title>
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

        .page-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .page-header {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .page-header h1 {
            color: var(--primary-color);
            margin: 0;
            font-size: 28px;
        }

        .page-header p {
            color: #666;
            margin: 5px 0 0 0;
        }

        .sellers-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 25px;
        }

        .seller-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .seller-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }

        .seller-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, #3d6b1f 100%);
            padding: 20px;
            text-align: center;
            color: white;
        }

        .seller-profile-pic {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            border: 4px solid white;
            object-fit: cover;
            margin-bottom: 10px;
        }

        .seller-name {
            font-size: 18px;
            font-weight: 600;
            margin: 0;
        }

        .seller-shop-name {
            font-size: 14px;
            opacity: 0.9;
            margin: 5px 0 0 0;
        }

        .seller-body {
            padding: 20px;
        }

        .seller-description {
            color: #666;
            font-size: 13px;
            margin-bottom: 15px;
            min-height: 40px;
        }

        .seller-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 15px;
        }

        .stat-box {
            background: var(--light-gray);
            padding: 10px;
            border-radius: 6px;
            text-align: center;
        }

        .stat-value {
            font-size: 18px;
            font-weight: bold;
            color: var(--primary-color);
        }

        .stat-label {
            font-size: 11px;
            color: #666;
            margin-top: 3px;
        }

        .seller-actions {
            display: flex;
            gap: 10px;
        }

        .btn {
            flex: 1;
            padding: 10px;
            border: none;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
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
            color: var(--dark-gray);
        }

        .btn-secondary:hover {
            background: #e6b800;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }

        .empty-state i {
            font-size: 60px;
            margin-bottom: 20px;
        }

        .empty-state h3 {
            margin: 0 0 10px 0;
            color: #666;
        }

        @media (max-width: 768px) {
            .page-container {
                padding: 10px;
            }

            .sellers-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include '_header.php'; ?>

    <div class="page-container">
        <div class="page-header">
            <h1><i class="fas fa-store"></i> Active Sellers</h1>
            <p>Connect with other sellers on ReBuy</p>
        </div>

        <?php if ($sellers->num_rows > 0): ?>
            <div class="sellers-grid">
                <?php while ($seller = $sellers->fetch_assoc()): ?>
                    <div class="seller-card">
                        <div class="seller-header">
                            <?php if (!empty($seller['shop_profile_pic'])): ?>
                                <img src="<?php echo '../' . htmlspecialchars($seller['shop_profile_pic']); ?>" alt="<?php echo htmlspecialchars($seller['shop_name']); ?>" class="seller-profile-pic">
                            <?php else: ?>
                                <div class="seller-profile-pic" style="background: white; display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-store" style="font-size: 30px; color: var(--primary-color);"></i>
                                </div>
                            <?php endif; ?>
                            <h3 class="seller-name"><?php echo htmlspecialchars($seller['first_name'] . ' ' . $seller['last_name']); ?></h3>
                            <?php if (!empty($seller['shop_name'])): ?>
                                <p class="seller-shop-name"><?php echo htmlspecialchars($seller['shop_name']); ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="seller-body">
                            <?php if (!empty($seller['shop_description'])): ?>
                                <p class="seller-description"><?php echo htmlspecialchars($seller['shop_description']); ?></p>
                            <?php else: ?>
                                <p class="seller-description">No shop description available.</p>
                            <?php endif; ?>
                            
                            <div class="seller-stats">
                                <div class="stat-box">
                                    <div class="stat-value"><?php echo $seller['total_products']; ?></div>
                                    <div class="stat-label">Products</div>
                                </div>
                                <div class="stat-box">
                                    <div class="stat-value"><?php echo $seller['total_sales']; ?></div>
                                    <div class="stat-label">Sales</div>
                                </div>
                            </div>

                            <div class="seller-actions">
                                <a href="seller_profile.php?seller_id=<?php echo $seller['id']; ?>" class="btn btn-primary">
                                    <i class="fas fa-user"></i> View Profile
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-store-slash"></i>
                <h3>No Active Sellers</h3>
                <p>There are no active sellers on ReBuy yet.</p>
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
