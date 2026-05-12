<?php
session_start();

// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'db.php';
$user_id = $_SESSION['user_id'];

// Check if viewing another seller's profile
$view_seller_id = isset($_GET['seller_id']) ? (int)$_GET['seller_id'] : $user_id;

// If viewing own profile, check if user is a seller
if ($view_seller_id == $user_id) {
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
}

// Handle profile update (only for own profile)
$success_msg = '';
$error_msg = '';
$is_own_profile = ($view_seller_id == $user_id);

// Check for success parameter in URL
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $success_msg = "Shop profile updated successfully!";
}

if ($is_own_profile) {
    // Check if shop_name and shop_description columns exist, add them if not
    $column_check = $conn->query("SHOW COLUMNS FROM users LIKE 'shop_name'");
    if ($column_check->num_rows == 0) {
        $conn->query("ALTER TABLE users ADD COLUMN shop_name VARCHAR(100) NULL AFTER is_seller");
    }
    $column_check = $conn->query("SHOW COLUMNS FROM users LIKE 'shop_description'");
    if ($column_check->num_rows == 0) {
        $conn->query("ALTER TABLE users ADD COLUMN shop_description TEXT NULL AFTER shop_name");
    }
    $column_check = $conn->query("SHOW COLUMNS FROM users LIKE 'shop_profile_pic'");
    if ($column_check->num_rows == 0) {
        $conn->query("ALTER TABLE users ADD COLUMN shop_profile_pic VARCHAR(255) NULL AFTER shop_description");
    }
    $column_check = $conn->query("SHOW COLUMNS FROM users LIKE 'cover_photo'");
    if ($column_check->num_rows == 0) {
        $conn->query("ALTER TABLE users ADD COLUMN cover_photo VARCHAR(255) NULL AFTER shop_profile_pic");
    }

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $shop_name = $_POST['shop_name'] ?? '';
        $shop_description = $_POST['shop_description'] ?? '';

        // Get current seller data for file uploads
        $current_seller_stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
        $current_seller_stmt->bind_param("i", $user_id);
        $current_seller_stmt->execute();
        $current_seller = $current_seller_stmt->get_result()->fetch_assoc();
        $current_seller_stmt->close();

        // Handle shop profile picture upload
        $shop_profile_pic = $current_seller['shop_profile_pic'] ?? '';
        if (isset($_FILES['shop_profile_pic']) && $_FILES['shop_profile_pic']['error'] == 0) {
            $upload_dir = '../uploads/shop_profiles/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $file_ext = pathinfo($_FILES['shop_profile_pic']['name'], PATHINFO_EXTENSION);
            $file_name = 'shop_profile_' . $user_id . '_' . time() . '.' . $file_ext;
            $upload_path = $upload_dir . $file_name;
            if (move_uploaded_file($_FILES['shop_profile_pic']['tmp_name'], $upload_path)) {
                $shop_profile_pic = 'uploads/shop_profiles/' . $file_name;
            }
        }

        // Handle cover photo upload
        $cover_photo = $current_seller['cover_photo'] ?? '';
        if (isset($_FILES['cover_photo']) && $_FILES['cover_photo']['error'] == 0) {
            $upload_dir = '../uploads/covers/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $file_ext = pathinfo($_FILES['cover_photo']['name'], PATHINFO_EXTENSION);
            $file_name = 'cover_' . $user_id . '_' . time() . '.' . $file_ext;
            $upload_path = $upload_dir . $file_name;
            if (move_uploaded_file($_FILES['cover_photo']['tmp_name'], $upload_path)) {
                $cover_photo = 'uploads/covers/' . $file_name;
            }
        }

        // Update seller profile
        $update_stmt = $conn->prepare("UPDATE users SET shop_name = ?, shop_description = ?, shop_profile_pic = ?, cover_photo = ? WHERE id = ?");
        if ($update_stmt === false) {
            $error_msg = "Database error: " . $conn->error;
        } else {
            $update_stmt->bind_param("ssssi", $shop_name, $shop_description, $shop_profile_pic, $cover_photo, $user_id);

            if ($update_stmt->execute()) {
                $success_msg = "Shop profile updated successfully!";
                // Redirect to refresh the page and show new images
                header("Location: seller_profile.php?success=1");
                exit();
            } else {
                $error_msg = "Failed to update shop profile.";
            }
            $update_stmt->close();
        }
    }
}

// Get seller info (moved after form processing to get updated data)
$seller_stmt = $conn->prepare("SELECT * FROM users WHERE id = ? AND is_seller = 1");
$seller_stmt->bind_param("i", $view_seller_id);
$seller_stmt->execute();
$seller = $seller_stmt->get_result()->fetch_assoc();
$seller_stmt->close();

// Check if shop_profile_pic column exists, if not use profile_pic as fallback
$column_check = $conn->query("SHOW COLUMNS FROM users LIKE 'shop_profile_pic'");
if ($column_check->num_rows == 0) {
    // If shop_profile_pic doesn't exist, use profile_pic for shop
    if (isset($seller['profile_pic'])) {
        $seller['shop_profile_pic'] = $seller['profile_pic'];
    }
}

if (!$seller) {
    header("Location: dashboard.php");
    exit();
}

// Get seller's products count
$products_count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM products WHERE seller_id = ?");
$products_count_stmt->bind_param("i", $view_seller_id);
$products_count_stmt->execute();
$products_count = $products_count_stmt->get_result()->fetch_assoc()['count'];
$products_count_stmt->close();

// Get seller's orders count
$orders_count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM seller_orders WHERE seller_id = ?");
$orders_count_stmt->bind_param("i", $view_seller_id);
$orders_count_stmt->execute();
$orders_count = $orders_count_stmt->get_result()->fetch_assoc()['count'];
$orders_count_stmt->close();

// Get seller's total earnings
$earnings_stmt = $conn->prepare("SELECT SUM(total_amount) as total FROM seller_orders WHERE seller_id = ? AND status = 'delivered'");
$earnings_stmt->bind_param("i", $view_seller_id);
$earnings_stmt->execute();
$earnings = $earnings_stmt->get_result()->fetch_assoc()['total'] ?? 0;
$earnings_stmt->close();

// Get seller's products for display
$products_stmt = $conn->prepare("SELECT * FROM products WHERE seller_id = ? ORDER BY created_at DESC LIMIT 8");
$products_stmt->bind_param("i", $view_seller_id);
$products_stmt->execute();
$products_result = $products_stmt->get_result();
$products_stmt->close();

// Get seller's reviews
$reviews_stmt = $conn->prepare("SELECT r.*, u.first_name, u.last_name, p.name as product_name FROM reviews r JOIN users u ON r.user_id = u.id JOIN products p ON r.product_id = p.id WHERE r.seller_id = ? ORDER BY r.created_at DESC LIMIT 10");
$reviews_stmt->bind_param("i", $view_seller_id);
$reviews_stmt->execute();
$reviews_result = $reviews_stmt->get_result();
$reviews_stmt->close();

// Calculate average rating
$avg_rating_stmt = $conn->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews FROM reviews WHERE seller_id = ?");
$avg_rating_stmt->bind_param("i", $view_seller_id);
$avg_rating_stmt->execute();
$rating_data = $avg_rating_stmt->get_result()->fetch_assoc();
$avg_rating_stmt->close();
$average_rating = $rating_data['avg_rating'] ?? 0;
$total_reviews = $rating_data['total_reviews'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ReBuy<?php echo $is_own_profile ? 'My Shop' : htmlspecialchars($seller['shop_name'] ?? ($seller['first_name'] . "'s Shop")); ?> - ReBuy</title>
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
            max-width: 1200px;
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

        .profile-header {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 30px;
        }

        .profile-cover {
            background: linear-gradient(135deg, var(--primary-color), #4a7c23);
            height: 150px;
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
        }

        .profile-info {
            padding: 30px;
            display: flex;
            align-items: flex-end;
            margin-top: -50px;
        }

        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: white;
            border: 4px solid white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            color: var(--primary-color);
            margin-right: 20px;
            overflow: hidden;
        }

        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-details h2 {
            margin: 0 0 5px 0;
            color: #333;
            font-size: 24px;
        }

        .profile-details p {
            margin: 0;
            color: #666;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            font-size: 20px;
        }

        .stat-icon.products { background: rgba(255, 193, 7, 0.1); color: #ffc107; }
        .stat-icon.orders { background: rgba(23, 162, 184, 0.1); color: #17a2b8; }
        .stat-icon.earnings { background: rgba(40, 167, 69, 0.1); color: #28a745; }

        .stat-value {
            font-size: 28px;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #666;
            font-size: 14px;
        }

        .card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 30px;
            margin-bottom: 30px;
        }

        .card h3 {
            color: var(--primary-color);
            margin-top: 0;
            font-size: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            font-family: inherit;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
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

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .product-card {
            background: #f8f9fa;
            border-radius: 8px;
            overflow: hidden;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .product-image {
            height: 150px;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
        }

        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .product-discount {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #ff4444;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }

        .product-info {
            padding: 15px;
        }

        .product-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            font-size: 14px;
            line-height: 1.4;
        }

        .product-price {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .current-price {
            color: var(--primary-color);
            font-weight: 700;
            font-size: 16px;
        }

        .original-price {
            color: #999;
            text-decoration: line-through;
            font-size: 12px;
        }

        .rating-summary {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .average-rating {
            text-align: center;
        }

        .rating-number {
            font-size: 48px;
            font-weight: bold;
            color: var(--primary-color);
            line-height: 1;
        }

        .rating-stars {
            color: #ffc107;
            font-size: 20px;
            margin: 10px 0;
        }

        .rating-stars .empty {
            color: #e9ecef;
        }

        .total-reviews {
            color: #666;
            font-size: 14px;
        }

        .reviews-list {
            margin-top: 20px;
        }

        .review-item {
            padding: 20px;
            border-bottom: 1px solid #e9ecef;
        }

        .review-item:last-child {
            border-bottom: none;
        }

        .review-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .reviewer-name {
            font-weight: 600;
            color: #333;
        }

        .review-date {
            color: #999;
            font-size: 12px;
        }

        .review-rating {
            color: #ffc107;
            font-size: 14px;
            margin-bottom: 8px;
        }

        .review-rating .empty {
            color: #e9ecef;
        }

        .review-product {
            font-size: 13px;
            color: #666;
            margin-bottom: 8px;
            font-style: italic;
        }

        .review-comment {
            color: #333;
            line-height: 1.6;
        }

        .preview-btn {
            position: absolute;
            top: 10px;
            left: 10px;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            display: none;
            align-items: center;
            gap: 5px;
            z-index: 10;
            transition: all 0.3s;
        }

        .preview-btn:hover {
            background: rgba(0, 0, 0, 0.9);
        }

        .product-card:hover .preview-btn {
            display: flex;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.9);
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            max-width: 90%;
            max-height: 90%;
            border-radius: 8px;
        }

        .modal-close {
            position: absolute;
            top: 20px;
            right: 30px;
            color: white;
            font-size: 40px;
            font-weight: bold;
            cursor: pointer;
            z-index: 1001;
        }

        .modal-close:hover {
            color: #f4c430;
        }

        .profile-cover {
            background: linear-gradient(135deg, var(--primary-color), #4a7c23);
            height: 150px;
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            cursor: pointer;
            position: relative;
            transition: all 0.3s;
        }

        .profile-cover:hover {
            opacity: 0.9;
        }

        .profile-cover:hover::after {
            content: '\f030';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: white;
            font-size: 24px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.5);
        }

        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: white;
            border: 4px solid white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            color: var(--primary-color);
            margin-right: 20px;
            overflow: hidden;
            cursor: pointer;
            position: relative;
            transition: all 0.3s;
        }

        .profile-avatar:hover {
            transform: scale(1.05);
        }

        .profile-avatar:hover::after {
            content: '\f030';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: rgba(0,0,0,0.7);
            font-size: 20px;
            z-index: 10;
        }

        .profile-avatar:hover img {
            opacity: 0.7;
        }

        .hidden-file-input {
            display: none;
        }
    </style>
</head>
<body>
    <?php include '_header.php'; ?>
    
    <div class="container">

        <?php if ($success_msg): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $success_msg; ?>
            </div>
        <?php endif; ?>

        <?php if ($error_msg): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error_msg; ?>
            </div>
        <?php endif; ?>

        <div class="profile-header">
            <div class="profile-cover" id="coverPhotoPreview" <?php echo $is_own_profile ? 'onclick="document.getElementById(\'cover_photo\').click()"' : ''; ?> style="<?php echo !empty($seller['cover_photo']) ? 'background-image: url(../' . htmlspecialchars($seller['cover_photo']) . ');' : ''; ?>">
            </div>
            <div class="profile-info">
                <div class="profile-avatar" <?php echo $is_own_profile ? 'onclick="document.getElementById(\'shop_profile_pic\').click()"' : ''; ?>>
                    <img id="profilePicPreview" src="<?php echo !empty($seller['shop_profile_pic']) ? '../' . htmlspecialchars($seller['shop_profile_pic']) : 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgdmlld0JveD0iMCAwIDEwMCAxMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIxMDAiIGhlaWdodD0iMTAwIiBmaWxsPSIjRjVGNUY1Ii8+CjxwYXRoIGQ9Ik01MCA2MkM1Ny4xODMgNjIgNjMgNTYuMTgzIDYzIDQ5QzYzIDQxLjgxNyA1Ny4xODMgMzYgNTAgMzZDNDIuODE3IDM2IDM3IDQxLjgxNyAzNyA0OUMzNyA1Ni4xODMgNDIuODE3IDYyIDUwIDYyWiIgZmlsbD0iIzMzNEYzQyIvPgo8cGF0aCBkPSJNMjUgNzRIMzVWMjhIMjVWNzRaIiBmaWxsPSIjMzM0RjQzIi8+CjxwYXRoIGQ9Ik02NSA3NEg3NVYyOEg2NVY3NFoiIGZpbGw9IiMzMzRGNDMiLz4KPC9zdmc+'; ?>" alt="Shop Profile Picture">
                </div>
                <div class="profile-details">
                    <h2><?php echo htmlspecialchars($seller['shop_name'] ?? ($seller['first_name'] . "'s Shop")); ?></h2>
                    <p><?php echo htmlspecialchars($seller['email']); ?></p>
                </div>
            </div>
        </div>

        <?php if ($is_own_profile): ?>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon products">
                    <i class="fas fa-box"></i>
                </div>
                <div class="stat-value"><?php echo $products_count; ?></div>
                <div class="stat-label">Products</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon orders">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <div class="stat-value"><?php echo $orders_count; ?></div>
                <div class="stat-label">Orders</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon earnings">
                    <i class="fas fa-peso-sign"></i>
                </div>
                <div class="stat-value">₱<?php echo number_format($earnings, 2); ?></div>
                <div class="stat-label">Total Earnings</div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Seller's Products -->
        <div class="card">
            <h3><i class="fas fa-box"></i> Products</h3>
            <?php if ($products_result->num_rows > 0): ?>
                <div class="products-grid">
                    <?php while ($product = $products_result->fetch_assoc()): ?>
                        <div class="product-card" onclick="window.location.href='product_details.php?id=<?php echo $product['id']; ?>'">
                            <div class="product-image">
                                <?php if ($is_own_profile): ?>
                                <button class="preview-btn" onclick="event.stopPropagation(); openPreview('<?php echo !empty($product['image_url']) ? '../' . htmlspecialchars($product['image_url']) : 'https://images.unsplash.com/photo-1555041469-a586c61ea9bc?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80'; ?>')">
                                    <i class="fas fa-eye"></i> Preview
                                </button>
                                <?php endif; ?>
                                <img src="<?php echo !empty($product['image_url']) ? '../' . htmlspecialchars($product['image_url']) : 'https://images.unsplash.com/photo-1555041469-a586c61ea9bc?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80'; ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                                <?php if ($product['original_price'] && $product['original_price'] > $product['price']): ?>
                                    <span class="product-discount">-<?php echo round((($product['original_price'] - $product['price']) / $product['original_price']) * 100); ?>%</span>
                                <?php endif; ?>
                            </div>
                            <div class="product-info">
                                <div class="product-name"><?php echo htmlspecialchars($product['name']); ?></div>
                                <div class="product-price">
                                    <span class="current-price">₱<?php echo number_format($product['price'], 2); ?></span>
                                    <?php if ($product['original_price']): ?>
                                        <span class="original-price">₱<?php echo number_format($product['original_price'], 2); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <p style="color: #666; text-align: center; padding: 20px;">No products available yet.</p>
            <?php endif; ?>
        </div>

         <!-- Seller Reviews (visible to all users) -->
        <div class="card">
            <h3><i class="fas fa-star"></i> Reviews & Ratings</h3>
            <div class="rating-summary">
                <div class="average-rating">
                    <div class="rating-number"><?php echo number_format($average_rating, 1); ?></div>
                    <div class="rating-stars">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i class="fas fa-star <?php echo $i <= round($average_rating) ? '' : 'empty'; ?>"></i>
                        <?php endfor; ?>
                    </div>
                    <div class="total-reviews"><?php echo $total_reviews; ?> reviews</div>
                </div>
            </div>
            <?php if ($reviews_result->num_rows > 0): ?>
                <div class="reviews-list">
                    <?php while ($review = $reviews_result->fetch_assoc()): ?>
                        <div class="review-item">
                            <div class="review-header">
                                <div class="reviewer-name">
                                    <?php echo htmlspecialchars($review['first_name'] . ' ' . $review['last_name']); ?>
                                </div>
                                <div class="review-date">
                                    <?php echo date('M d, Y', strtotime($review['created_at'])); ?>
                                </div>
                            </div>
                            <div class="review-rating">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star <?php echo $i <= $review['rating'] ? '' : 'empty'; ?>"></i>
                                <?php endfor; ?>
                            </div>
                            <div class="review-product">Product: <?php echo htmlspecialchars($review['product_name']); ?></div>
                            <div class="review-comment"><?php echo htmlspecialchars($review['comment']); ?></div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <p style="color: #666; text-align: center; padding: 20px;">No reviews yet.</p>
            <?php endif; ?>
        </div>

        

        <?php if ($is_own_profile): ?>
        <div class="card">
            <h3><i class="fas fa-edit"></i> Shop Information</h3>
            <form method="POST" action="" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="shop_name">Shop Name</label>
                    <input type="text" id="shop_name" name="shop_name" value="<?php echo htmlspecialchars($seller['shop_name'] ?? ''); ?>" placeholder="Enter your shop name">
                </div>
                <div class="form-group">
                    <label for="shop_description">Shop Description</label>
                    <textarea id="shop_description" name="shop_description" placeholder="Describe your shop..."><?php echo htmlspecialchars($seller['shop_description'] ?? ''); ?></textarea>
                </div>
                <!-- Hidden file inputs for profile picture and cover photo -->
                <input type="file" id="shop_profile_pic" name="shop_profile_pic" accept="image/*" class="hidden-file-input">
                <input type="file" id="cover_photo" name="cover_photo" accept="image/*" class="hidden-file-input">
                <p style="color: #666; font-size: 14px; margin-bottom: 20px;"><i class="fas fa-info-circle"></i> Click on the profile picture or cover photo above to change them</p>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </form>
        </div>
        <?php endif; ?>
    </div>

    <!-- Image Preview Modal -->
    <div id="previewModal" class="modal">
        <span class="modal-close" onclick="closePreview()">&times;</span>
        <img class="modal-content" id="previewImage" src="" alt="Product Preview">
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
        function openPreview(imageSrc) {
            document.getElementById('previewImage').src = imageSrc;
            document.getElementById('previewModal').classList.add('active');
        }

        function closePreview() {
            document.getElementById('previewModal').classList.remove('active');
        }

        // Close modal when clicking outside the image
        document.getElementById('previewModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closePreview();
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closePreview();
            }
        });

        // Handle file input changes for profile picture and cover photo
        document.getElementById('shop_profile_pic')?.addEventListener('change', function(e) {
            if (e.target.files && e.target.files[0]) {
                const file = e.target.files[0];
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    // Update the profile picture preview immediately
                    document.getElementById('profilePicPreview').src = e.target.result;
                };
                
                reader.readAsDataURL(file);
                
                // Show file selected feedback
                const successDiv = document.createElement('div');
                successDiv.className = 'alert alert-success';
                successDiv.style.position = 'fixed';
                successDiv.style.top = '20px';
                successDiv.style.right = '20px';
                successDiv.style.zIndex = '9999';
                successDiv.innerHTML = '<i class="fas fa-check-circle"></i> Profile picture selected. Click "Save Changes" to upload.';
                document.body.appendChild(successDiv);
                
                setTimeout(() => {
                    successDiv.remove();
                }, 3000);
            }
        });

        document.getElementById('cover_photo')?.addEventListener('change', function(e) {
            if (e.target.files && e.target.files[0]) {
                const file = e.target.files[0];
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    // Update the cover photo preview immediately
                    document.getElementById('coverPhotoPreview').style.backgroundImage = 'url(' + e.target.result + ')';
                };
                
                reader.readAsDataURL(file);
                
                // Show file selected feedback
                const successDiv = document.createElement('div');
                successDiv.className = 'alert alert-success';
                successDiv.style.position = 'fixed';
                successDiv.style.top = '20px';
                successDiv.style.right = '20px';
                successDiv.style.zIndex = '9999';
                successDiv.innerHTML = '<i class="fas fa-check-circle"></i> Cover photo selected. Click "Save Changes" to upload.';
                document.body.appendChild(successDiv);
                
                setTimeout(() => {
                    successDiv.remove();
                }, 3000);
            }
        });

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

        // Prevent back navigation to login page
        history.pushState(null, null, location.href);
        window.onpopstate = function() {
            history.go(1);
        };
    </script>
</body>
</html>
