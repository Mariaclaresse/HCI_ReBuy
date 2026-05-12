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

// Get seller statistics
$total_earnings = 0;
$total_orders = 0;
$total_products = 0;
$total_wishlist = 0;

// Get total earnings from completed orders
$earnings_stmt = $conn->prepare("SELECT SUM(total_amount) as total FROM seller_orders WHERE seller_id = ? AND status IN ('delivered')");
$earnings_stmt->bind_param("i", $user_id);
$earnings_stmt->execute();
$earnings_result = $earnings_stmt->get_result();
$earnings_data = $earnings_result->fetch_assoc();
$total_earnings = $earnings_data['total'] ?? 0;
$earnings_stmt->close();

// Get total orders
$orders_stmt = $conn->prepare("SELECT COUNT(*) as total FROM seller_orders WHERE seller_id = ?");
$orders_stmt->bind_param("i", $user_id);
$orders_stmt->execute();
$orders_result = $orders_stmt->get_result();
$orders_data = $orders_result->fetch_assoc();
$total_orders = $orders_data['total'] ?? 0;
$orders_stmt->close();

// Get total products
$products_stmt = $conn->prepare("SELECT COUNT(*) as total FROM products WHERE seller_id = ?");
$products_stmt->bind_param("i", $user_id);
$products_stmt->execute();
$products_result = $products_stmt->get_result();
$products_data = $products_result->fetch_assoc();
$total_products = $products_data['total'] ?? 0;
$products_stmt->close();

// Get average rating for seller's products
$rating_stmt = $conn->prepare("SELECT AVG(r.rating) as avg_rating FROM reviews r JOIN products p ON r.product_id = p.id WHERE p.seller_id = ?");
$rating_stmt->bind_param("i", $user_id);
$rating_stmt->execute();
$rating_result = $rating_stmt->get_result();
$rating_data = $rating_result->fetch_assoc();
$average_rating = round($rating_data['avg_rating'] ?? 0, 1);
$rating_stmt->close();

// Get recent orders
$recent_orders_stmt = $conn->prepare("
    SELECT so.*, p.name as product_name, p.image_url as product_image, u.first_name, u.last_name, u.email 
    FROM seller_orders so 
    JOIN products p ON so.product_id = p.id 
    JOIN users u ON so.customer_id = u.id 
    WHERE so.seller_id = ? 
    ORDER BY so.order_date DESC 
    LIMIT 10
");
$recent_orders_stmt->bind_param("i", $user_id);
$recent_orders_stmt->execute();
$recent_orders = $recent_orders_stmt->get_result();
$recent_orders_stmt->close();

// Get top products
$top_products_stmt = $conn->prepare("
    SELECT p.*, SUM(so.quantity) as total_sold 
    FROM products p 
    LEFT JOIN seller_orders so ON p.id = so.product_id AND so.status IN ('delivered')
    WHERE p.seller_id = ? 
    GROUP BY p.id 
    ORDER BY total_sold DESC 
    LIMIT 5
");
$top_products_stmt->bind_param("i", $user_id);
$top_products_stmt->execute();
$top_products = $top_products_stmt->get_result();
$top_products_stmt->close();
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
            --warning-color: #ffc107;
            --info-color: #17a2b8;
            --light-gray: #f5f5f5;
            --dark-gray: #333;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--light-gray);
            margin: 0;
            padding: 0;
        }

        .dashboard-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .dashboard-header {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .dashboard-title h1 {
            color: var(--primary-color);
            margin: 0;
            font-size: 28px;
        }

        .dashboard-title p {
            color: #666;
            margin: 5px 0 0 0;
        }

        .dashboard-actions {
            display: flex;
            gap: 15px;
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
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(45, 80, 22, 0.2);
        }

        .btn-secondary {
            background: var(--secondary-color);
            color: var(--dark-gray);
        }

        .btn-secondary:hover {
            background: #e6b800;
            transform: translateY(-2px);
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
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
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

        .stat-icon.earnings { background: rgba(40, 167, 69, 0.1); color: var(--success-color); }
        .stat-icon.orders { background: rgba(23, 162, 184, 0.1); color: var(--info-color); }
        .stat-icon.products { background: rgba(255, 193, 7, 0.1); color: var(--warning-color); }
        .stat-icon.wishlist { background: rgba(220, 53, 69, 0.1); color: var(--danger-color); }

        .stat-value {
            font-size: 32px;
            font-weight: bold;
            color: var(--dark-gray);
            margin-bottom: 5px;
        }

        .stat-label {
            color: #666;
            font-size: 14px;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: 3fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        .dashboard-card {
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

        .order-item, .product-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #eee;
        }

        .order-item:last-child, .product-item:last-child {
            border-bottom: none;
        }

        .order-info h4, .product-info h4 {
            margin: 0 0 5px 0;
            color: var(--dark-gray);
            font-size: 14px;
        }

        .order-info p, .product-info p {
            margin: 0;
            color: #666;
            font-size: 12px;
        }

        .order-status, .product-status {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-pending { background: #fff3cd; color: #856404; }
        .status-processing { background: #cce5ff; color: #004085; }
        .status-shipped { background: #d1ecf1; color: #0c5460; }
        .status-delivered { background: #d4edda; color: #155724; }
        .status-cancelled { background: #f8d7da; color: #721c24; }

        /* Custom Modal Styles */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.3);
            max-width: 400px;
            width: 90%;
            text-align: center;
        }

        .modal-icon {
            font-size: 48px;
            color: var(--primary-color);
            margin-bottom: 20px;
        }

        .modal-title {
            font-size: 20px;
            font-weight: 600;
            color: var(--dark-gray);
            margin-bottom: 10px;
        }

        .modal-message {
            color: #666;
            margin-bottom: 25px;
            font-size: 14px;
        }

        .modal-actions {
            display: flex;
            gap: 10px;
            justify-content: center;
        }

        .modal-btn {
            padding: 10px 25px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .modal-btn-confirm {
            background: var(--primary-color);
            color: white;
        }

        .modal-btn-confirm:hover {
            background: #1a3009;
        }

        .modal-btn-cancel {
            background: #e0e0e0;
            color: #333;
        }

        .modal-btn-cancel:hover {
            background: #d0d0d0;
        }

        .modal-icon.success {
            color: var(--success-color);
        }

        .modal-icon.error {
            color: var(--danger-color);
        }

        .orders-table {
            overflow-x: auto;
        }

        .orders-table table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .orders-table thead {
            background: var(--primary-color);
            color: white;
        }

        .orders-table th {
            padding: 12px 10px;
            text-align: left;
            font-weight: 600;
        }

        .orders-table td {
            padding: 12px 10px;
            border-bottom: 1px solid #eee;
        }

        .orders-table tr:hover {
            background: #f9f9f9;
        }

        .product-cell {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .product-thumb {
            width: 40px;
            height: 40px;
            object-fit: cover;
            border-radius: 4px;
            border: 1px solid #ddd;
        }

        .customer-info strong {
            display: block;
            color: var(--dark-gray);
        }

        .customer-info small {
            color: #666;
        }

        .order-actions {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }

        .btn-action {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s;
        }

        .btn-process {
            background: #17a2b8;
            color: white;
        }

        .btn-process:hover {
            background: #138496;
        }

        .btn-ship {
            background: #ffc107;
            color: #333;
        }

        .btn-ship:hover {
            background: #e0a800;
        }

        .btn-deliver {
            background: #28a745;
            color: white;
        }

        .btn-deliver:hover {
            background: #218838;
        }

        .btn-cancel {
            background: #dc3545;
            color: white;
        }

        .btn-cancel:hover {
            background: #c82333;
        }

        .quick-actions {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .quick-actions h3 {
            color: var(--primary-color);
            margin-bottom: 20px;
        }

        .quick-act {
            padding: 0px;
        }

        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        @media (max-width: 768px) {
            .dashboard-container {
                padding: 10px;
            }
            
            .dashboard-header {
                flex-direction: column;
                text-align: center;
                gap: 20px;
            }
            
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include '_header.php'; ?>

    <div class="dashboard-container">
        <!-- Dashboard Header -->
        <div class="dashboard-header">
            <div class="dashboard-title">
                <h1><i class="fas fa-store"></i> Seller Dashboard</h1>
                <p>Manage your products and track your sales performance</p>
            </div>
            <div class="dashboard-actions">
                <a href="upload_product.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add Product
                </a>
                <a href="sellers.php" class="btn btn-secondary">
                    <i class="fas fa-users"></i> View Sellers
                </a>
            </div>
        </div>

        <!-- Statistics Cards -->
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
                <div class="stat-icon rating">
                    <i class="fas fa-star"></i>
                </div>
                <div class="stat-value"><?php echo $average_rating; ?></div>
                <div class="stat-label">Average Rating</div>
            </div>
        </div>

        <!-- Recent Orders and Top Products -->
        <div class="dashboard-grid">
            <div class="dashboard-card">
                <div class="card-header">
                    <i class="fas fa-shopping-cart"></i> Recent Orders
                </div>
                <div class="card-content">
                    <?php if ($recent_orders->num_rows > 0): ?>
                        <div class="orders-table">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Order ID</th>
                                        <th>Product</th>
                                        <th>Customer</th>
                                        <th>Qty</th>
                                        <th>Total</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($order = $recent_orders->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($order['order_id']); ?></td>
                                            <td>
                                                <div class="product-cell">
                                                    <?php if (!empty($order['product_image'])): ?>
                                                        <img src="<?php echo '../' . htmlspecialchars($order['product_image']); ?>" alt="<?php echo htmlspecialchars($order['product_name']); ?>" class="product-thumb">
                                                    <?php endif; ?>
                                                    <span><?php echo htmlspecialchars($order['product_name']); ?></span>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="customer-info">
                                                    <strong><?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></strong><br>
                                                    <small><?php echo htmlspecialchars($order['email']); ?></small>
                                                </div>
                                            </td>
                                            <td><?php echo $order['quantity']; ?></td>
                                            <td>₱<?php echo number_format($order['total_amount'], 2); ?></td>
                                            <td>
                                                <span class="order-status status-<?php echo $order['status']; ?>">
                                                    <?php echo ucfirst($order['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="order-actions">
                                                    <?php if ($order['status'] == 'pending'): ?>
                                                        <button onclick="updateOrderStatus(<?php echo $order['id']; ?>, 'processing')" class="btn-action btn-process">
                                                            <i class="fas fa-clock"></i> Process
                                                        </button>
                                                    <?php endif; ?>
                                                    <?php if ($order['status'] == 'processing'): ?>
                                                        <button onclick="updateOrderStatus(<?php echo $order['id']; ?>, 'shipped')" class="btn-action btn-ship">
                                                            <i class="fas fa-shipping-fast"></i> Ship
                                                        </button>
                                                    <?php endif; ?>
                                                    <?php if ($order['status'] == 'shipped'): ?>
                                                        <button onclick="updateOrderStatus(<?php echo $order['id']; ?>, 'delivered')" class="btn-action btn-deliver">
                                                            <i class="fas fa-check-circle"></i> Deliver
                                                        </button>
                                                    <?php endif; ?>
                                                    <?php if (in_array($order['status'], ['pending', 'processing'])): ?>
                                                        <button onclick="updateOrderStatus(<?php echo $order['id']; ?>, 'cancelled')" class="btn-action btn-cancel">
                                                            <i class="fas fa-times"></i> Cancel
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p style="text-align: center; color: #666; padding: 20px;">No orders yet</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="dashboard-card">
                <div class="card-header">
                    <i class="fas fa-star"></i> Top Products
                </div>
                <div class="card-content">
                    <?php if ($top_products->num_rows > 0): ?>
                        <?php while ($product = $top_products->fetch_assoc()): ?>
                            <div class="product-item">
                                <div class="product-info">
                                    <h4><?php echo htmlspecialchars($product['name']); ?></h4>
                                    <p>Sold: <?php echo $product['total_sold'] ?? 0; ?> • ₱<?php echo number_format($product['price'], 2); ?></p>
                                </div>
                                <div class="product-status status-active">
                                    Active
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p style="text-align: center; color: #666; padding: 20px;">No products yet</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="quick-act">
        </div>
    </div>

    <!-- Custom Confirmation Modal -->
    <div class="modal-overlay" id="confirmModal">
        <div class="modal-content">
            <div class="modal-icon">
                <i class="fas fa-question-circle"></i>
            </div>
            <div class="modal-title">Confirm Order Status Change</div>
            <div class="modal-message" id="modalMessage">Are you sure you want to change the order status?</div>
            <div class="modal-actions">
                <button class="modal-btn modal-btn-cancel" id="modalCancel">Cancel</button>
                <button class="modal-btn modal-btn-confirm" id="modalConfirm">Confirm</button>
            </div>
        </div>
    </div>

    <!-- Custom Alert Modal -->
    <div class="modal-overlay" id="alertModal">
        <div class="modal-content">
            <div class="modal-icon" id="alertIcon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="modal-title" id="alertTitle">Success</div>
            <div class="modal-message" id="alertMessage">Operation completed successfully!</div>
            <div class="modal-actions">
                <button class="modal-btn modal-btn-confirm" id="alertOk">OK</button>
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

        // Custom modal functionality
        let pendingOrderId = null;
        let pendingStatus = null;

        const modal = document.getElementById('confirmModal');
        const modalMessage = document.getElementById('modalMessage');
        const modalCancel = document.getElementById('modalCancel');
        const modalConfirm = document.getElementById('modalConfirm');

        const alertModal = document.getElementById('alertModal');
        const alertIcon = document.getElementById('alertIcon');
        const alertTitle = document.getElementById('alertTitle');
        const alertMessage = document.getElementById('alertMessage');
        const alertOk = document.getElementById('alertOk');

        // Custom alert function
        function showAlert(type, title, message, callback) {
            alertIcon.className = 'modal-icon ' + (type === 'success' ? 'success' : 'error');
            alertIcon.innerHTML = type === 'success' ? '<i class="fas fa-check-circle"></i>' : '<i class="fas fa-exclamation-circle"></i>';
            alertTitle.textContent = title;
            alertMessage.textContent = message;
            alertModal.classList.add('active');

            alertOk.onclick = function() {
                alertModal.classList.remove('active');
                if (callback) callback();
            };
        }

        modalCancel.addEventListener('click', function() {
            modal.classList.remove('active');
            pendingOrderId = null;
            pendingStatus = null;
        });

        modalConfirm.addEventListener('click', function() {
            modal.classList.remove('active');
            if (pendingOrderId && pendingStatus) {
                executeOrderStatusUpdate(pendingOrderId, pendingStatus);
            }
            pendingOrderId = null;
            pendingStatus = null;
        });

        // Close modal when clicking outside
        modal.addEventListener('click', function(event) {
            if (event.target === modal) {
                modal.classList.remove('active');
                pendingOrderId = null;
                pendingStatus = null;
            }
        });

        alertModal.addEventListener('click', function(event) {
            if (event.target === alertModal) {
                alertModal.classList.remove('active');
            }
        });

        // Update order status
        function updateOrderStatus(orderId, newStatus) {
            pendingOrderId = orderId;
            pendingStatus = newStatus;
            modalMessage.textContent = 'Are you sure you want to change the order status to ' + newStatus + '?';
            modal.classList.add('active');
        }

        function executeOrderStatusUpdate(orderId, newStatus) {
            fetch('update_order_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'order_id=' + orderId + '&status=' + newStatus
            })
            .then(response => {
                console.log('Response status:', response.status);
                console.log('Response ok:', response.ok);
                return response.text().then(text => {
                    console.log('Response text:', text);
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('JSON parse error:', e);
                        throw new Error('Invalid JSON response: ' + text);
                    }
                });
            })
            .then(data => {
                if (data.success) {
                    showAlert('success', 'Success', 'Order status updated successfully!', function() {
                        location.reload();
                    });
                } else {
                    showAlert('error', 'Error', 'Error updating order status: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('error', 'Error', 'Error updating order status: ' + error.message);
            });
        }
    </script>
</body>
</html>
