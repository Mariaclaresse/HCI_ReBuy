<?php
// Only include these if not already included
if (!function_exists('getUnreadNotificationCount')) {
    require_once 'db.php';
    require_once 'notification_functions.php';
}

// Check if user is logged in - use existing variables if available
if (!isset($is_logged_in)) {
    $is_logged_in = isset($_SESSION['user_id']);
}
if (!isset($user_id)) {
    $user_id = $is_logged_in ? $_SESSION['user_id'] : null;
}

// Get notification count if user is logged in
$notification_count = 0;
$recent_notifications = [];
if ($is_logged_in) {
    $notification_count = getUnreadNotificationCount($user_id);
    $recent_notifications = getUserNotifications($user_id, 5);
}

// Check if user is a seller - use existing variable if available
if (!isset($is_seller)) {
    $is_seller = false;
    if ($is_logged_in) {
        $seller_check = $conn->query("SHOW COLUMNS FROM users LIKE 'is_seller'");
        if ($seller_check->num_rows > 0) {
            $stmt = $conn->prepare("SELECT is_seller FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $seller_data = $result->fetch_assoc();
            $stmt->close();
            
            $is_seller = isset($seller_data['is_seller']) && $seller_data['is_seller'] == 1;
        }
    }
}

// Get current page for navbar highlighting
$current_page = basename($_SERVER['PHP_SELF']);
function is_active($page) {
    global $current_page;
    return $current_page === $page ? 'active' : '';
}
?>

<style>
<?php include '../css/notification-dropdown.css'; ?>
</style>

<!-- Main Header -->
<header class="main-header">
    <div class="header-container">
        <a href="dashboard.php" class="logo">
            <i class="fas fa-shopping-bag"></i>
            <span>ReBuy</span>
        </a>
        
        <?php if ($is_seller): ?>
        <!-- Seller Navigation -->
        <nav class="nav-menu">
            <a href="seller_profile.php" class="<?php echo is_active('seller_profile.php'); ?>">My Shop</a>
            <a href="seller_dashboard.php" class="<?php echo is_active('seller_dashboard.php'); ?>">Dashboard</a>
            <a href="message.php" class="<?php echo is_active('message.php'); ?>">Messages</a>
        </nav>
        <?php else: ?>
        <!-- Regular User Navigation -->
        <nav class="nav-menu">
            <a href="dashboard.php" class="<?php echo is_active('dashboard.php'); ?>">Home</a>
            <a href="shop.php" class="<?php echo is_active('shop.php'); ?>">Shop</a>
            <a href="orders.php" class="<?php echo is_active('orders.php'); ?>">Orders</a>
            <a href="wishlist.php" class="<?php echo is_active('wishlist.php'); ?>">Wishlist</a>
            <a href="message.php" class="<?php echo is_active('message.php'); ?>">Messages</a>
        </nav>
        <?php endif; ?>
        
        <div class="header-icons">
            <div class="user-menu">
                <button class="icon-btn" id="menuToggle"><i class="fas fa-bars"></i></button>
                <div class="user-dropdown" id="userDropdown">
                    <?php if (!$is_seller): ?>
                        <a href="cart.php"><i class="fas fa-shopping-bag"></i> Cart</a>
                    <?php endif; ?>
                    <div class="notification-dropdown-mobile">
    <a href="<?php echo $is_seller ? 'seller_notification.php' : 'notification.php'; ?>" 
       class="notification-bell-mobile"
       style="display:flex; align-items:center; gap:8px;">

        <i class="fas fa-bell"></i> 
        Notifications

        <?php if ($notification_count > 0): ?>
            <span style="
                background:red;
                color:white;
                border-radius:50%;
                padding:2px 6px;
                font-size:11px;
                font-weight:bold;
                line-height:1;
                display:inline-block;
            ">
                <?php echo $notification_count; ?>
            </span>
        <?php endif; ?>

    </a>
</div>
                    <?php if ($is_seller): ?>
                        <a href="manage_products.php"><i class="fas fa-box"></i> Manage Products</a>
                        <a href="seller_orders.php"><i class="fas fa-shopping-cart"></i> Seller Orders</a>
                        <a href="seller_analytics.php"> <i class="fas fa-chart-line"></i> View Analytics</a>
                        <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
                    <?php else: ?>
                        <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
                        <a href="about_us.php"><i class="fas fa-info-circle"></i> About Us</a>
                    <?php endif; ?>
                    <hr>
                    <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
        </div>
    </div>
</header>
