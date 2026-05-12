<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$product_id = $_GET['id'] ?? 0;

// Get product details
$stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$product) {
    header("Location: shop.php");
    exit();
}

// Check if in wishlist
$stmt = $conn->prepare("SELECT id FROM wishlist WHERE user_id = ? AND product_id = ?");
$stmt->bind_param("ii", $_SESSION['user_id'], $product_id);
$stmt->execute();
$in_wishlist = $stmt->get_result()->num_rows > 0;
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($product['name']); ?> - ReBuy</title>
    <link rel="icon" type="image/x-icon" href="../../assets/logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../css/sidebar.css">
    <link rel="stylesheet" href="../css/product.css">
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="logo">
                <i class="fas fa-shopping-bag"></i>
                <span>ReBuy</span>
            </div>
            <nav class="nav-menu">
                <a href="dashboard.php" class="nav-item">
                    <i class="fas fa-home"></i>
                    <span>Home</span>
                </a>
                <a href="shop.php" class="nav-item active">
                    <i class="fas fa-store"></i>
                    <span>Shop</span>
                </a>
                <a href="wishlist.php" class="nav-item">
                    <i class="fas fa-heart"></i>
                    <span>Wishlist</span>
                </a>
                <a href="orders.php" class="nav-item">
                    <i class="fas fa-shopping-cart"></i>
                    <span>Orders</span>
                </a>
                <a href="notification.php" class="nav-item">
                    <i class="fas fa-bell"></i>
                    <span>Notifications</span>
                </a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header class="top-header">
                <a href="shop.php">Shop</a> / <span><?php echo htmlspecialchars($product['name']); ?></span>
            </header>

            <section class="content-section product-detail">
                <div class="product-container">
                    <div class="product-image">
                        <img src="<?php echo htmlspecialchars($product['image_url'] ?? 'https://via.placeholder.com/400'); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                    </div>

                    <div class="product-details">
                        <h1><?php echo htmlspecialchars($product['name']); ?></h1>

                        <div class="rating-section">
                            <i class="fas fa-star"></i>
                            <span><?php echo number_format($product['rating'], 1); ?>/5</span>
                        </div>

                        <div class="price-section">
                            <span class="current-price">$<?php echo number_format($product['price'], 2); ?></span>
                            <?php if ($product['original_price']): ?>
                                <span class="original-price">$<?php echo number_format($product['original_price'], 2); ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="description">
                            <h3>Description</h3>
                            <p><?php echo htmlspecialchars($product['description']); ?></p>
                        </div>

                        <div class="stock-info">
                            <p>Stock: <strong><?php echo $product['stock'] > 0 ? $product['stock'] . ' available' : 'Out of stock'; ?></strong></p>
                        </div>

                        <form class="add-to-cart-form" method="POST" action="cart_add.php">
                            <input type="hidden" name="product_id" value="<?php echo $product_id; ?>">
                            <div class="quantity-selector">
                                <label>Quantity:</label>
                                <input type="number" name="quantity" value="1" min="1" max="<?php echo $product['stock']; ?>" <?php echo $product['stock'] == 0 ? 'disabled' : ''; ?>>
                            </div>

                            <div class="actions">
                                <button type="submit" class="btn btn-add-cart" <?php echo $product['stock'] == 0 ? 'disabled' : ''; ?>>
                                    <i class="fas fa-shopping-cart"></i> Add to Cart
                                </button>
                                <a href="wishlist_add.php?id=<?php echo $product_id; ?>" class="btn btn-wishlist <?php echo $in_wishlist ? 'in-wishlist' : ''; ?>">
                                    <i class="fas fa-heart"></i>
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </section>
        </main>
    </div>
</body>
</html>
