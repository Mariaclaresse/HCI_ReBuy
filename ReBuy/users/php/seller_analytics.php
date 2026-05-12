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

// Get analytics data
$total_earnings = 0;
$total_orders = 0;
$total_products = 0;
$delivered_orders = 0;
$pending_orders = 0;
$processing_orders = 0;
$shipped_orders = 0;

// Total earnings from delivered orders
$earnings_stmt = $conn->prepare("SELECT SUM(total_amount) as total FROM seller_orders WHERE seller_id = ? AND status = 'delivered'");
$earnings_stmt->bind_param("i", $user_id);
$earnings_stmt->execute();
$earnings_result = $earnings_stmt->get_result();
$earnings_data = $earnings_result->fetch_assoc();
$total_earnings = $earnings_data['total'] ?? 0;
$earnings_stmt->close();

// Total orders
$orders_stmt = $conn->prepare("SELECT COUNT(*) as total FROM seller_orders WHERE seller_id = ?");
$orders_stmt->bind_param("i", $user_id);
$orders_stmt->execute();
$orders_result = $orders_stmt->get_result();
$orders_data = $orders_result->fetch_assoc();
$total_orders = $orders_data['total'] ?? 0;
$orders_stmt->close();

// Total products
$products_stmt = $conn->prepare("SELECT COUNT(*) as total FROM products WHERE seller_id = ?");
$products_stmt->bind_param("i", $user_id);
$products_stmt->execute();
$products_result = $products_stmt->get_result();
$products_data = $products_result->fetch_assoc();
$total_products = $products_data['total'] ?? 0;
$products_stmt->close();

// Orders by status
$status_stmt = $conn->prepare("SELECT status, COUNT(*) as count FROM seller_orders WHERE seller_id = ? GROUP BY status");
$status_stmt->bind_param("i", $user_id);
$status_stmt->execute();
$status_result = $status_stmt->get_result();
while ($row = $status_result->fetch_assoc()) {
    if ($row['status'] == 'delivered') $delivered_orders = $row['count'];
    if ($row['status'] == 'pending') $pending_orders = $row['count'];
    if ($row['status'] == 'processing') $processing_orders = $row['count'];
    if ($row['status'] == 'shipped') $shipped_orders = $row['count'];
}
$status_stmt->close();

// Top selling products
$top_products_stmt = $conn->prepare("
    SELECT p.*, SUM(so.quantity) as total_sold 
    FROM products p 
    LEFT JOIN seller_orders so ON p.id = so.product_id AND so.status = 'delivered'
    WHERE p.seller_id = ? 
    GROUP BY p.id 
    ORDER BY total_sold DESC 
    LIMIT 5
");
$top_products_stmt->bind_param("i", $user_id);
$top_products_stmt->execute();
$top_products = $top_products_stmt->get_result();
$top_products_stmt->close();

// Recent sales (last 7 days)
$recent_sales_stmt = $conn->prepare("
    SELECT DATE(order_date) as sale_date, SUM(total_amount) as daily_total
    FROM seller_orders
    WHERE seller_id = ? AND status = 'delivered' AND order_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY DATE(order_date)
    ORDER BY sale_date ASC
");
$recent_sales_stmt->bind_param("i", $user_id);
$recent_sales_stmt->execute();
$recent_sales = $recent_sales_stmt->get_result();
$recent_sales_stmt->close();
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
        }

        .header h1 {
            color: var(--primary-color);
            margin: 0;
            font-size: 28px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 24px;
        }

        .stat-icon.earnings { background: rgba(40, 167, 69, 0.1); color: #28a745; }
        .stat-icon.orders { background: rgba(23, 162, 184, 0.1); color: #17a2b8; }
        .stat-icon.products { background: rgba(255, 193, 7, 0.1); color: #ffc107; }
        .stat-icon.delivered { background: rgba(40, 167, 69, 0.1); color: #28a745; }

        .stat-value {
            font-size: 32px;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #666;
            font-size: 14px;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        .card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .card-header {
            background: var(--primary-color);
            color: white;
            padding: 20px;
            font-size: 18px;
            font-weight: 600;
        }

        .card-content {
            padding: 20px;
        }

        .status-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #eee;
        }

        .status-item:last-child {
            border-bottom: none;
        }

        .status-label {
            font-weight: 600;
            color: #333;
        }

        .status-count {
            font-size: 20px;
            font-weight: bold;
            color: var(--primary-color);
        }

        .product-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #eee;
        }

        .product-item:last-child {
            border-bottom: none;
        }

        .product-info h4 {
            margin: 0 0 5px 0;
            color: #333;
            font-size: 14px;
        }

        .product-info p {
            margin: 0;
            color: #666;
            font-size: 12px;
        }

        .product-sold {
            font-size: 18px;
            font-weight: bold;
            color: var(--primary-color);
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
            margin-bottom: 20px;
        }

        .back-link:hover {
            text-decoration: underline;
        }

        @media (max-width: 768px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include '_header.php'; ?>
    
    <div class="container">
        <a href="seller_dashboard.php" class="back-link">
            <i class="fas fa-arrow-left"></i>
        </a>

        <div class="header">
            <h1><i class="fas fa-chart-line"></i> Seller Analytics</h1>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon earnings">
                    <i class="fas fa-peso-sign"></i>
                </div>
                <div class="stat-value">₱<?php echo number_format($total_earnings, 2); ?></div>
                <div class="stat-label">Total Earnings</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon orders">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <div class="stat-value"><?php echo $total_orders; ?></div>
                <div class="stat-label">Total Orders</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon products">
                    <i class="fas fa-box"></i>
                </div>
                <div class="stat-value"><?php echo $total_products; ?></div>
                <div class="stat-label">Total Products</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon delivered">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-value"><?php echo $delivered_orders; ?></div>
                <div class="stat-label">Delivered Orders</div>
            </div>
        </div>

        <div class="dashboard-grid">
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-tasks"></i> Order Status Breakdown
                </div>
                <div class="card-content">
                    <div class="status-item">
                        <span class="status-label">Pending</span>
                        <span class="status-count"><?php echo $pending_orders; ?></span>
                    </div>
                    <div class="status-item">
                        <span class="status-label">Processing</span>
                        <span class="status-count"><?php echo $processing_orders; ?></span>
                    </div>
                    <div class="status-item">
                        <span class="status-label">Shipped</span>
                        <span class="status-count"><?php echo $shipped_orders; ?></span>
                    </div>
                    <div class="status-item">
                        <span class="status-label">Delivered</span>
                        <span class="status-count"><?php echo $delivered_orders; ?></span>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <i class="fas fa-star"></i> Top Selling Products
                </div>
                <div class="card-content">
                    <?php if ($top_products->num_rows > 0): ?>
                        <?php while ($product = $top_products->fetch_assoc()): ?>
                            <div class="product-item">
                                <div class="product-info">
                                    <h4><?php echo htmlspecialchars($product['name']); ?></h4>
                                    <p>₱<?php echo number_format($product['price'], 2); ?></p>
                                </div>
                                <div class="product-sold">
                                    <?php echo $product['total_sold'] ?? 0; ?> sold
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p style="text-align: center; color: #666; padding: 20px;">No sales data yet</p>
                    <?php endif; ?>
                </div>
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
