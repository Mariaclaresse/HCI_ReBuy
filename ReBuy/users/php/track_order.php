<?php
session_start();
require_once 'db.php';

$order_id = $_GET['order_id'] ?? '';
$email = $_GET['email'] ?? '';

if (!$order_id || !$email) {
    header("Location: settings.php");
    exit();
}

// Clean order ID (remove # prefix if present)
$order_id = str_replace('#', '', $order_id);
$order_id = str_replace('ORD', '', $order_id);
$order_id = intval($order_id);

// Add timestamp columns if they don't exist
$conn->query("ALTER TABLE orders ADD COLUMN IF NOT EXISTS accepted_at TIMESTAMP NULL AFTER status");
$conn->query("ALTER TABLE orders ADD COLUMN IF NOT EXISTS processing_at TIMESTAMP NULL AFTER accepted_at");
$conn->query("ALTER TABLE orders ADD COLUMN IF NOT EXISTS shipped_at TIMESTAMP NULL AFTER processing_at");
$conn->query("ALTER TABLE orders ADD COLUMN IF NOT EXISTS delivered_at TIMESTAMP NULL AFTER shipped_at");
$conn->query("ALTER TABLE orders ADD COLUMN IF NOT EXISTS cancelled_at TIMESTAMP NULL AFTER delivered_at");

// Fetch order details
$stmt = $conn->prepare("
    SELECT o.*, u.first_name, u.last_name, u.email, u.purok_street, u.barangay, u.municipality_city, u.province, u.country, u.zip_code
    FROM orders o
    JOIN users u ON o.user_id = u.id
    WHERE o.id = ? AND u.email = ?
");
$stmt->bind_param("is", $order_id, $email);
$stmt->execute();
$result = $stmt->get_result();
$order = $result->fetch_assoc();
$stmt->close();

if (!$order) {
    $error = "Order not found. Please check your Order ID and Billing Email.";
}

// Fetch order items with seller order status
$items = [];
if ($order) {
    $items_stmt = $conn->prepare("
        SELECT oi.*, p.name as product_name, p.image_url as product_image, 
               u.first_name as seller_first_name, u.last_name as seller_last_name,
               so.status as seller_status, so.id as seller_order_id
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        JOIN users u ON p.seller_id = u.id
        LEFT JOIN seller_orders so ON so.customer_id = oi.order_id AND so.product_id = oi.product_id
        WHERE oi.order_id = ?
    ");
    $items_stmt->bind_param("i", $order_id);
    $items_stmt->execute();
    $items_result = $items_stmt->get_result();
    while ($item = $items_result->fetch_assoc()) {
        $items[] = $item;
    }
    $items_stmt->close();
}

// Use the orders.status column which is synced with seller_orders
$overall_status = $order['status'] ?? 'pending';

// Fallback: if orders.status is still pending but seller_orders show different status, use seller_orders
if ($overall_status == 'pending' && !empty($items)) {
    $has_shipped = false;
    $has_delivered = false;
    $has_processing = false;
    $has_cancelled = false;
    $all_delivered = true;
    
    foreach ($items as $item) {
        $seller_status = $item['seller_status'] ?? 'pending';
        
        if ($seller_status == 'cancelled') {
            $has_cancelled = true;
        }
        if ($seller_status == 'delivered') {
            $has_delivered = true;
        }
        if ($seller_status == 'shipped') {
            $has_shipped = true;
        }
        if ($seller_status == 'processing') {
            $has_processing = true;
        }
        
        if ($seller_status != 'delivered') {
            $all_delivered = false;
        }
    }
    
    if ($has_cancelled) {
        $overall_status = 'cancelled';
    } elseif ($all_delivered) {
        $overall_status = 'delivered';
    } elseif ($has_shipped) {
        $overall_status = 'shipped';
    } elseif ($has_processing) {
        $overall_status = 'processing';
    }
}

// Generate order ID display
$order_id_display = '#ORD' . str_pad($order_id, 6, '0', STR_PAD_LEFT);
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
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8f9fa;
            color: #333;
        }

        .track-page {
            max-width: 1000px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .track-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .track-header h1 {
            font-size: 32px;
            color: #1a1a1a;
            margin-bottom: 10px;
        }

        .track-header p {
            color: #666;
            font-size: 16px;
        }

        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            margin-bottom: 20px;
        }

        .order-info-card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }

        .order-info-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e9ecef;
        }

        .order-info-header h2 {
            font-size: 24px;
            color: #1a1a1a;
        }

        .order-status-badge {
            padding: 8px 20px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-processing {
            background: #cce5ff;
            color: #004085;
        }

        .status-shipped {
            background: #d1ecf1;
            color: #0c5460;
        }

        .status-delivered {
            background: #d4edda;
            color: #155724;
        }

        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }

        .order-details-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        .detail-item {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .detail-label {
            font-size: 12px;
            color: #666;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .detail-value {
            font-size: 16px;
            font-weight: 600;
            color: #333;
        }

        .tracking-timeline {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }

        .tracking-timeline h3 {
            font-size: 20px;
            color: #1a1a1a;
            margin-bottom: 25px;
        }

        .timeline {
            display: flex;
            justify-content: space-between;
            position: relative;
            padding: 40px 0 20px;
        }

        .timeline::before {
            content: '';
            position: absolute;
            top: 50px;
            left: 50px;
            right: 50px;
            height: 4px;
            background: #e9ecef;
            transform: translateY(-50%);
        }

        .timeline-item {
            position: relative;
            text-align: center;
            flex: 1;
            z-index: 1;
        }

        .timeline-dot {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e9ecef;
            border: 4px solid white;
            box-shadow: 0 0 0 3px #e9ecef;
            margin: 0 auto 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            color: #999;
        }

        .timeline-item.completed .timeline-dot {
            background: #2d5016;
            box-shadow: 0 0 0 3px #2d5016;
            color: white;
        }

        .timeline-item.current .timeline-dot {
            background: #f4c430;
            box-shadow: 0 0 0 3px #f4c430;
            color: #333;
        }

        .timeline-content h4 {
            font-size: 14px;
            color: #333;
            margin-bottom: 5px;
            font-weight: 600;
        }

        .timeline-content p {
            font-size: 12px;
            color: #666;
            margin-bottom: 3px;
        }

        .timeline-date {
            font-size: 11px;
            color: #999;
        }

        .timeline-item.completed .timeline-content h4,
        .timeline-item.completed .timeline-content p {
            color: #2d5016;
        }

        .timeline-item.current .timeline-content h4,
        .timeline-item.current .timeline-content p {
            color: #333;
        }

        .order-items {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
        }

        .order-items h3 {
            font-size: 20px;
            color: #1a1a1a;
            margin-bottom: 20px;
        }

        .item-card {
            display: flex;
            align-items: center;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 15px;
        }

        .item-card:last-child {
            margin-bottom: 0;
        }

        .item-image {
            width: 80px;
            height: 80px;
            border-radius: 8px;
            object-fit: cover;
            margin-right: 20px;
            border: 1px solid #e9ecef;
        }

        .item-info {
            flex: 1;
        }

        .item-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }

        .item-seller {
            font-size: 13px;
            color: #666;
        }

        .item-quantity {
            font-size: 13px;
            color: #666;
        }

        .item-status {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: white;
            color: #333;
            border: 1px solid #ddd;
            padding: 12px 24px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
            margin-bottom: 20px;
        }

        .back-btn:hover {
            background: #f8f9fa;
        }

        @media (max-width: 768px) {
            .order-details-grid {
                grid-template-columns: 1fr;
            }

            .order-info-header {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }

            .timeline {
                flex-direction: column;
                padding: 20px 0;
            }

            .timeline::before {
                left: 20px;
                top: 0;
                bottom: 0;
                right: auto;
                width: 3px;
                height: auto;
                transform: none;
            }

            .timeline-item {
                text-align: left;
                padding-left: 50px;
                margin-bottom: 25px;
            }

            .timeline-dot {
                margin: 0;
                position: absolute;
                left: 0;
                top: 0;
            }
        }
    </style>
</head>
<body>
    <?php include '_header.php'; ?>

    <div class="track-page">
        <a href="settings.php#orders" class="back-btn">
            <i class="fas fa-arrow-left"></i> Back to Orders
        </a>

        <div class="track-header">
            <h1>Track Your Order</h1>
            <p>Enter your Order ID and Billing Email to track your order</p>
        </div>

        <?php if (isset($error)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php else: ?>
            <div class="order-info-card">
                <div class="order-info-header">
                    <h2><?php echo $order_id_display; ?></h2>
                    <span class="order-status-badge status-<?php echo $overall_status; ?>">
                        <?php echo ucfirst($overall_status); ?>
                    </span>
                </div>

                <div class="order-details-grid">
                    <div class="detail-item">
                        <div class="detail-label">Order Date</div>
                        <div class="detail-value"><?php echo date('F d, Y', strtotime($order['display_date'] ?? $order['order_date'])); ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Total Amount</div>
                        <div class="detail-value">₱<?php echo number_format($order['total_amount'], 2); ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Payment Method</div>
                        <div class="detail-value"><?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($order['payment_method']))); ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Shipping Address</div>
                        <div class="detail-value" style="font-size: 14px;">
                            <?php echo htmlspecialchars($order['purok_street'] ?? ''); ?>, 
                            <?php echo htmlspecialchars($order['barangay'] ?? ''); ?><br>
                            <?php echo htmlspecialchars($order['municipality_city'] ?? ''); ?>, 
                            <?php echo htmlspecialchars($order['province'] ?? ''); ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="tracking-timeline">
                <h3>Order Status</h3>
                <div class="timeline">
                    <?php
                    $order_date = strtotime($order['display_date'] ?? $order['order_date']);
                    $accepted_at = !empty($order['accepted_at']) ? strtotime($order['accepted_at']) : null;
                    $processing_at = !empty($order['processing_at']) ? strtotime($order['processing_at']) : null;
                    $shipped_at = !empty($order['shipped_at']) ? strtotime($order['shipped_at']) : null;
                    $delivered_at = !empty($order['delivered_at']) ? strtotime($order['delivered_at']) : null;
                    $cancelled_at = !empty($order['cancelled_at']) ? strtotime($order['cancelled_at']) : null;
                    
                    // Calculate expected dates if actual timestamps not available
                    $expected_accepted = date('d M Y', strtotime('+1 day', $order_date));
                    $expected_processing = date('d M Y', strtotime('+3 days', $order_date));
                    $expected_shipped = date('d M Y', strtotime('+5 days', $order_date));
                    $expected_delivered = date('d M Y', strtotime('+7 days', $order_date));
                    ?>
                    <div class="timeline-item completed">
                        <div class="timeline-dot"><i class="fas fa-check"></i></div>
                        <div class="timeline-content">
                            <h4>Order Placed</h4>
                            <p><?php echo date('d M Y', $order_date); ?></p>
                            <p><?php echo date('h:i A', $order_date); ?></p>
                        </div>
                    </div>
                    <?php if ($overall_status != 'cancelled'): ?>
                        <div class="timeline-item <?php echo $accepted_at ? 'completed' : ''; ?>">
                            <div class="timeline-dot">
                                <?php if ($accepted_at): ?>
                                    <i class="fas fa-check"></i>
                                <?php else: ?>
                                    <i class="fas fa-hourglass-half"></i>
                                <?php endif; ?>
                            </div>
                            <div class="timeline-content">
                                <h4>Accepted at</h4>
                                <p><?php echo $accepted_at ? date('d M Y', $accepted_at) : 'Expected: ' . $expected_accepted; ?></p>
                                <p><?php echo $accepted_at ? date('h:i A', $accepted_at) : ''; ?></p>
                            </div>
                        </div>
                        <div class="timeline-item <?php echo $processing_at ? 'completed' : ($accepted_at && !$processing_at && !$shipped_at && !$delivered_at ? 'current' : ''); ?>">
                            <div class="timeline-dot">
                                <?php if ($processing_at): ?>
                                    <i class="fas fa-check"></i>
                                <?php elseif ($accepted_at && !$processing_at && !$shipped_at && !$delivered_at): ?>
                                    <i class="fas fa-spinner fa-spin"></i>
                                <?php else: ?>
                                    <i class="fas fa-hourglass-half"></i>
                                <?php endif; ?>
                            </div>
                            <div class="timeline-content">
                                <h4>Processing at</h4>
                                <p><?php echo $processing_at ? date('d M Y', $processing_at) : 'Expected: ' . $expected_processing; ?></p>
                                <p><?php echo $processing_at ? date('h:i A', $processing_at) : ''; ?></p>
                            </div>
                        </div>
                        <div class="timeline-item <?php echo $shipped_at ? 'completed' : ($processing_at && !$shipped_at && !$delivered_at ? 'current' : ''); ?>">
                            <div class="timeline-dot">
                                <?php if ($shipped_at): ?>
                                    <i class="fas fa-check"></i>
                                <?php elseif ($processing_at && !$shipped_at && !$delivered_at): ?>
                                    <i class="fas fa-truck fa-spin"></i>
                                <?php else: ?>
                                    <i class="fas fa-hourglass-half"></i>
                                <?php endif; ?>
                            </div>
                            <div class="timeline-content">
                                <h4>Shipped at</h4>
                                <p><?php echo $shipped_at ? date('d M Y', $shipped_at) : 'Expected: ' . $expected_shipped; ?></p>
                                <p><?php echo $shipped_at ? date('h:i A', $shipped_at) : ''; ?></p>
                            </div>
                        </div>
                        <div class="timeline-item <?php echo $delivered_at ? 'completed' : ($shipped_at && !$delivered_at ? 'current' : ''); ?>">
                            <div class="timeline-dot">
                                <?php if ($delivered_at): ?>
                                    <i class="fas fa-check"></i>
                                <?php elseif ($shipped_at && !$delivered_at): ?>
                                    <i class="fas fa-truck fa-spin"></i>
                                <?php else: ?>
                                    <i class="fas fa-hourglass-half"></i>
                                <?php endif; ?>
                            </div>
                            <div class="timeline-content">
                                <h4>Delivered at</h4>
                                <p><?php echo $delivered_at ? date('d M Y', $delivered_at) : 'Expected: ' . $expected_delivered; ?></p>
                                <p><?php echo $delivered_at ? date('h:i A', $delivered_at) : ''; ?></p>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="timeline-item current">
                            <div class="timeline-dot"><i class="fas fa-times"></i></div>
                            <div class="timeline-content">
                                <h4>Cancelled</h4>
                                <p><?php echo $cancelled_at ? date('d M Y h:i A', $cancelled_at) : 'Order has been cancelled'; ?></p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="order-items">
                <h3>Order Items</h3>
                <?php foreach ($items as $item): ?>
                    <div class="item-card">
                        <img src="<?php echo !empty($item['product_image']) ? '../' . htmlspecialchars($item['product_image']) : 'https://images.unsplash.com/photo-1586023492125-27b2c045efd7?ixlib=rb-4.0.3&auto=format&fit=crop&w=100&q=80'; ?>" alt="<?php echo htmlspecialchars($item['product_name']); ?>" class="item-image">
                        <div class="item-info">
                            <div class="item-name"><?php echo htmlspecialchars($item['product_name']); ?></div>
                            <div class="item-seller">Seller: <?php echo htmlspecialchars($item['seller_first_name'] . ' ' . $item['seller_last_name']); ?></div>
                            <div class="item-quantity">Quantity: <?php echo $item['quantity']; ?></div>
                        </div>
                       
                    </div>
                <?php endforeach; ?>
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
                        <li><a href="track_order.php">Track Your Order</a></li>
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
</body>
</html>
