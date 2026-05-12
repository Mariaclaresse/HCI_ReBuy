<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Auto-remove canceled orders after 30 seconds and restore stock
$cleanup_stmt = $conn->prepare("
    SELECT o.id, o.cancelled_at 
    FROM orders o 
    WHERE o.status = 'cancelled' 
    AND o.cancelled_at IS NOT NULL 
    AND o.cancelled_at < DATE_SUB(NOW(), INTERVAL 30 SECOND)
");
if ($cleanup_stmt === false) {
    die("Prepare failed: " . $conn->error);
}
$cleanup_stmt->execute();
$canceled_orders = $cleanup_stmt->get_result();

while ($canceled_order = $canceled_orders->fetch_assoc()) {
    $order_id = $canceled_order['id'];
    
    // Get order items to restore stock
    $items_stmt = $conn->prepare("
        SELECT product_id, quantity 
        FROM order_items 
        WHERE order_id = ?
    ");
    if ($items_stmt === false) {
        die("Prepare failed: " . $conn->error);
    }
    $items_stmt->bind_param("i", $order_id);
    $items_stmt->execute();
    $items_result = $items_stmt->get_result();
    
    // Restore stock for each item
    while ($item = $items_result->fetch_assoc()) {
        $update_stock = $conn->prepare("
            UPDATE products 
            SET stock = stock + ? 
            WHERE id = ?
        ");
        if ($update_stock === false) {
            error_log("MySQL Error: " . $conn->error);
            error_log("SQL Query: UPDATE products SET stock = stock + ? WHERE id = ?");
            die("Prepare failed: " . htmlspecialchars($conn->error));
        }
        $update_stock->bind_param("ii", $item['quantity'], $item['product_id']);
        $update_stock->execute();
        $update_stock->close();
    }
    $items_stmt->close();
    
    // Delete order items
    $delete_items = $conn->prepare("DELETE FROM order_items WHERE order_id = ?");
    if ($delete_items === false) {
        die("Prepare failed: " . $conn->error);
    }
    $delete_items->bind_param("i", $order_id);
    $delete_items->execute();
    $delete_items->close();
    
    // Delete the order
    $delete_order = $conn->prepare("DELETE FROM orders WHERE id = ?");
    if ($delete_order === false) {
        die("Prepare failed: " . $conn->error);
    }
    $delete_order->bind_param("i", $order_id);
    $delete_order->execute();
    $delete_order->close();
}
$cleanup_stmt->close();

// Get user orders with seller order details
$stmt = $conn->prepare("
    SELECT o.id, o.order_date, o.total_amount, o.status, o.payment_method, o.cancelled_at,
           so.id as seller_order_id, so.status as seller_status, so.order_id as seller_order_ref,
           p.name as product_name, p.image_url as product_image, so.quantity,
           u.first_name as seller_first_name, u.last_name as seller_last_name
    FROM orders o
    LEFT JOIN seller_orders so ON o.user_id = so.customer_id AND o.order_date = so.order_date
    LEFT JOIN products p ON so.product_id = p.id
    LEFT JOIN users u ON so.seller_id = u.id
    WHERE o.user_id = ?
    ORDER BY o.order_date DESC
");
if ($stmt === false) {
    die("Prepare failed: " . $conn->error);
}
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result === false) {
    die("Query failed: " . $conn->error);
}
$orders = [];

while ($row = $result->fetch_assoc()) {
    $order_id = $row['id'];
    if (!isset($orders[$order_id])) {
        $orders[$order_id] = [
            'id' => $row['id'],
            'order_date' => $row['order_date'],
            'total_amount' => $row['total_amount'],
            'status' => $row['status'],
            'payment_method' => $row['payment_method'],
            'cancelled_at' => $row['cancelled_at'],
            'items' => []
        ];
    }
    if ($row['seller_order_id']) {
        $orders[$order_id]['items'][] = [
            'seller_order_id' => $row['seller_order_id'],
            'seller_status' => $row['seller_status'],
            'seller_order_ref' => $row['seller_order_ref'],
            'product_name' => $row['product_name'],
            'product_image' => $row['product_image'],
            'quantity' => $row['quantity'],
            'seller_name' => $row['seller_first_name'] . ' ' . $row['seller_last_name']
        ];
    }
}
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
    <link rel="stylesheet" href="../css/cart.css">
</head>
<body>
    <div class="page-wrapper">
        <?php include '_header.php'; ?>
        
        <!-- Page Content -->
        <div class="page-content" style="max-width: 1200px; margin: 0 auto; padding: 40px;">
            <h1 style="font-size: 28px; color: #333; margin-bottom: 30px;"><i class="fas fa-shopping-cart"></i> My Orders</h1>

            <?php if (count($orders) > 0): ?>
                <?php foreach ($orders as $order): ?>
                    <div style="background: white; border-radius: 8px; overflow: hidden; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                        <div style="background: #2d5016; color: white; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <span style="font-weight: 600; font-size: 16px;">Order #<?php echo htmlspecialchars($order['id']); ?></span>
                                <span style="margin-left: 15px; opacity: 0.9; font-size: 14px;"><?php echo date('M d, Y g:i A', strtotime($order['order_date'])); ?></span>
                            </div>
                            <div>
                                <?php if ($order['status'] == 'cancelled'): ?>
                                    <span style="display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; background: #f8d7da; color: #721c24;">
                                        Cancelled <span class="countdown" data-cancelled-at="<?php echo htmlspecialchars($order['cancelled_at']); ?>" data-server-time="<?php echo date('Y-m-d\TH:i:s\Z'); ?>">(30s)</span>
                                    </span>
                                <?php else: ?>
                                    <span style="display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; background: #e8f5e9; color: #2d5016;">
                                        <?php echo ucfirst(htmlspecialchars($order['status'])); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div style="padding: 20px;">
                            <?php if (!empty($order['items'])): ?>
                                <?php foreach ($order['items'] as $item): ?>
                                    <div style="display: flex; align-items: center; padding: 15px 0; border-bottom: 1px solid #f0f0f0;">
                                        <div style="width: 60px; height: 60px; border-radius: 4px; overflow: hidden; margin-right: 15px; flex-shrink: 0;">
                                            <img src="<?php echo !empty($item['product_image']) ? '../' . htmlspecialchars($item['product_image']) : 'https://images.unsplash.com/photo-1586023492125-27b2c045efd7?ixlib=rb-4.0.3&auto=format&fit=crop&w=100&q=80'; ?>" alt="<?php echo htmlspecialchars($item['product_name']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                        </div>
                                        <div style="flex: 1;">
                                            <div style="font-weight: 600; color: #333; margin-bottom: 5px;"><?php echo htmlspecialchars($item['product_name']); ?></div>
                                            <div style="font-size: 13px; color: #666;">Seller: <?php echo htmlspecialchars($item['seller_name']); ?> • Qty: <?php echo $item['quantity']; ?></div>
                                        </div>
                                        <div style="text-align: right;">
                                            <div style="font-size: 12px; color: #666; margin-bottom: 5px;">Item Status:</div>
                                            <span style="display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; 
                                                <?php 
                                                $status_colors = [
                                                    'pending' => 'background: #fff3cd; color: #856404;',
                                                    'processing' => 'background: #cce5ff; color: #004085;',
                                                    'shipped' => 'background: #d1ecf1; color: #0c5460;',
                                                    'delivered' => 'background: #d4edda; color: #155724;',
                                                    'cancelled' => 'background: #f8d7da; color: #721c24;'
                                                ];
                                                echo $status_colors[$item['seller_status']] ?? 'background: #e9ecef; color: #495057;';
                                                ?>">
                                                <?php echo ucfirst(htmlspecialchars($item['seller_status'])); ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div style="color: #666; padding: 10px 0;">No items in this order</div>
                            <?php endif; ?>
                            <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 15px; margin-top: 15px; border-top: 2px solid #f0f0f0;">
                                <div>
                                    <span style="color: #666; font-size: 14px;">Payment: </span>
                                    <span style="font-weight: 600; color: #333;"><?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($order['payment_method']))); ?></span>
                                </div>
                                <div style="display: flex; align-items: center; gap: 15px;">
                                    <div>
                                        <span style="color: #666; font-size: 14px;">Total: </span>
                                        <span style="font-weight: 700; color: #2d5016; font-size: 18px;">₱<?php echo number_format($order['total_amount'], 2); ?></span>
                                    </div>
                                    <?php 
                                    // Check if order can be cancelled (only if pending and no items are delivered/shipped)
                                    $can_cancel = ($order['status'] == 'pending');
                                    if ($can_cancel && !empty($order['items'])) {
                                        foreach ($order['items'] as $item) {
                                            if (in_array($item['seller_status'], ['processing', 'shipped', 'delivered'])) {
                                                $can_cancel = false;
                                                break;
                                            }
                                        }
                                    }
                                    ?>
                                    <?php if ($can_cancel): ?>
                                        <form method="POST" action="" style="display: inline;">
                                            <input type="hidden" name="action" value="cancel_order">
                                            <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                            <button type="submit" onclick="return confirm('Are you sure you want to cancel this order?');" style="background: #dc3545; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-weight: 600; font-size: 13px;">Cancel</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="text-align: center; padding: 60px 20px;">
                    <i class="fas fa-inbox" style="font-size: 60px; color: #ddd; display: block; margin-bottom: 20px;"></i>
                    <h2 style="color: #999; margin-bottom: 10px;">No orders yet</h2>
                    <p style="color: #ccc; margin-bottom: 20px;">Start shopping to place your first order</p>
                    <a href="shop.php" style="display: inline-block; background: #2d5016; color: white; padding: 12px 30px; border-radius: 4px; text-decoration: none; font-weight: 600;">Shop Now</a>
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
    </div>

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

        // Enhanced real-time countdown with server synchronization
        let serverTimeOffset = 0;
        
        // Calculate server time offset
        function calculateServerTimeOffset() {
            const countdowns = document.querySelectorAll('.countdown');
            if (countdowns.length > 0) {
                const serverTimeStr = countdowns[0].dataset.serverTime;
                const serverTime = new Date(serverTimeStr);
                const localTime = new Date();
                serverTimeOffset = serverTime - localTime;
            }
        }
        
        function updateCountdowns() {
            const countdowns = document.querySelectorAll('.countdown');
            countdowns.forEach(function(countdown) {
                const cancelledAtStr = countdown.dataset.cancelledAt;
                const cancelledAt = new Date(cancelledAtStr);
                
                // Use server-synchronized time
                const now = new Date(Date.now() + serverTimeOffset);
                const elapsed = Math.floor((now - cancelledAt) / 1000);
                const remaining = Math.max(0, 30 - elapsed);
                
                if (remaining > 0) {
                    // Show real-time countdown with seconds
                    countdown.textContent = '(' + remaining + 's)';
                    countdown.style.color = remaining <= 5 ? '#dc3545' : '#721c24';
                    countdown.style.fontWeight = remaining <= 5 ? 'bold' : 'normal';
                } else {
                    // Remove the row when countdown reaches 0
                    const row = countdown.closest('tr');
                    if (row && !row.dataset.removing) {
                        row.dataset.removing = 'true';
                        row.style.transition = 'opacity 0.5s, transform 0.5s';
                        row.style.opacity = '0';
                        row.style.transform = 'translateX(-20px)';
                        setTimeout(function() {
                            row.remove();
                            // Check if no orders left and reload page
                            const tbody = document.querySelector('tbody');
                            if (tbody && tbody.children.length === 0) {
                                setTimeout(function() {
                                    location.reload();
                                }, 100);
                            }
                        }, 500);
                    }
                }
            });
        }

        // Initialize server time offset
        calculateServerTimeOffset();
        
        // Update countdowns every 100ms for smoother real-time display
        setInterval(updateCountdowns, 100);
        updateCountdowns(); // Initial call
        
        // Recalculate server time offset every 30 seconds to maintain accuracy
        setInterval(calculateServerTimeOffset, 30000);
    </script>
</body>
</html>