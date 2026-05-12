<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get wishlist items
$stmt = $conn->prepare("
    SELECT p.id, p.name, p.price, p.original_price, p.image_url, p.rating, p.stock_quantity as stock, w.added_at
    FROM wishlist w 
    JOIN products p ON w.product_id = p.id 
    WHERE w.user_id = ? 
    ORDER BY w.id DESC
");

if ($stmt === false) {
    die("Error preparing statement: " . $conn->error);
}

$stmt->bind_param("i", $user_id);

if (!$stmt->execute()) {
    die("Error executing statement: " . $stmt->error);
}

$result = $stmt->get_result();
if ($result === false) {
    die("Error getting result: " . $stmt->error);
}

$wishlist_items = $result->fetch_all(MYSQLI_ASSOC);
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
        .page-wrapper {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        .page-content {
            flex: 1;
            background: #f5f5f5;
        }

        .wishlist-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .wishlist-header {
            background: white;
            padding: 30px;
            border-radius: 8px;
            margin-bottom: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .wishlist-header h1 {
            font-size: 28px;
            color: #333;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .wishlist-header p {
            color: #999;
            margin: 10px 0 0 0;
            font-size: 14px;
        }

        .wishlist-counter {
            display: inline-block;
            background: #f4c430;
            color: #333;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 10px;
        }

        .empty-wishlist {
            text-align: center;
            padding: 60px 40px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .empty-wishlist i {
            font-size: 60px;
            color: #ddd;
            margin-bottom: 20px;
        }

        .empty-wishlist h2 {
            color: #999;
            font-size: 20px;
            margin: 0 0 10px 0;
        }

        .empty-wishlist p {
            color: #bbb;
            margin-bottom: 30px;
        }

        .empty-wishlist a {
            display: inline-block;
            background: #2d5016;
            color: white;
            padding: 12px 30px;
            border-radius: 4px;
            text-decoration: none;
            font-weight: 600;
            transition: background 0.3s;
        }

        .empty-wishlist a:hover {
            background: #1a3009;
        }

        /* Wishlist Table Design */
        .wishlist-table-wrapper {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 30px;
        }

        .wishlist-table {
            width: 100%;
            border-collapse: collapse;
        }

        .wishlist-table thead {
            background: #f4c430;
        }

        .wishlist-table th {
            padding: 15px 20px;
            text-align: left;
            font-weight: 600;
            color: #333;
            font-size: 14px;
            text-transform: uppercase;
        }

        .wishlist-table th:first-child {
            width: 60px;
            text-align: center;
        }

        .wishlist-table th:nth-child(2) {
            width: 100px;
        }

        .wishlist-table th:nth-child(3) {
            width: 300px;
        }

        .wishlist-table th:nth-child(4) {
            width: 100px;
        }

        .wishlist-table th:nth-child(5) {
            width: 150px;
        }

        .wishlist-table th:nth-child(6) {
            width: 100px;
        }

        .wishlist-table th:last-child {
            width: 120px;
        }

        .wishlist-table tbody tr {
            border-bottom: 1px solid #eee;
            transition: background 0.3s;
        }

        .wishlist-table tbody tr:hover {
            background: #f9f9f9;
        }

        .wishlist-table tbody tr:last-child {
            border-bottom: none;
        }

        .wishlist-table td {
            padding: 20px;
            vertical-align: middle;
        }

        .wishlist-table td:first-child {
            text-align: center;
        }

        .remove-btn {
            background: none;
            border: none;
            color: #999;
            font-size: 18px;
            cursor: pointer;
            transition: color 0.3s;
            width: 30px;
            height: 30px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .remove-btn:hover {
            color: #e74c3c;
        }

        .product-img-cell img {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 4px;
        }

        .product-name-cell {
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }

        .product-quantity {
            color: #999;
            font-size: 12px;
            margin-top: 4px;
        }

        .price-cell {
            font-weight: 600;
            color: #333;
            font-size: 16px;
        }

        .date-cell {
            color: #666;
            font-size: 13px;
        }

        .stock-cell {
            font-size: 13px;
            font-weight: 600;
        }

        .stock-cell.instock {
            color: #27ae60;
        }

        .stock-cell.outstock {
            color: #e74c3c;
        }

        .add-to-cart-btn {
            background: #2d5016;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        .add-to-cart-btn:hover {
            background: #1a3009;
        }

        /* Add by Link Section */
        .add-by-link-section {
            background: rgba(255, 255, 255, 0.95);
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
            border: 1px solid #e0e0e0;
        }

        .add-by-link-section h3 {
            color: var(--primary-color);
            margin: 0 0 15px 0;
            font-size: 16px;
            font-weight: 600;
        }

        .link-form {
            margin: 0;
        }

        .link-input-group {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .link-input-group input[type="url"] {
            flex: 1;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        .link-input-group input[type="url"]:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(45, 80, 22, 0.1);
        }

        .add-link-btn {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
            white-space: nowrap;
        }

        .add-link-btn:hover {
            background: #1a3009;
        }

        /* Alert Messages */
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
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

        /* Wishlist Actions Section */
        .wishlist-actions {
            background: white;
            padding: 25px 30px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
        }

        .wishlist-link-section {
            display: flex;
            align-items: center;
            gap: 10px;
            flex: 1;
        }

        .wishlist-link-section label {
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }

        .wishlist-link-input {
            flex: 1;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 13px;
            color: #666;
            background: #f9f9f9;
        }

        .copy-link-btn {
            background: #f4c430;
            color: #333;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
        }

        .copy-link-btn:hover {
            background: #e0b000;
        }

        .wishlist-buttons {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .clear-wishlist-link {
            color: #e74c3c;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: color 0.3s;
        }

        .clear-wishlist-link:hover {
            color: #c0392b;
        }

        .add-all-btn {
            background: #2d5016;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
        }

        .add-all-btn:hover {
            background: #1a3009;
        }

        @media (max-width: 768px) {
            .wishlist-container {
                padding: 20px 10px;
            }

            .wishlist-table-wrapper {
                overflow-x: auto;
            }

            .wishlist-table {
                min-width: 800px;
            }

            .wishlist-actions {
                flex-direction: column;
                align-items: stretch;
            }

            .wishlist-link-section {
                flex-direction: column;
                align-items: stretch;
            }

            .wishlist-buttons {
                flex-direction: column;
                align-items: stretch;
            }
        }
    </style>
</head>
<body>
    <div class="page-wrapper">
        <?php include '_header.php'; ?>
        <!-- Page Content -->
        <div class="page-content">
            <div class="wishlist-container">
                <!-- Wishlist Header -->
                <div class="wishlist-header">
                    <h1>
                        <i class="fas fa-heart"></i>
                        My Wishlist
                        <span class="wishlist-counter"><?php echo count($wishlist_items); ?> Items</span>
                    </h1>
                    <p>Your saved items for later purchase</p>
                    
                </div>

                <!-- Success/Error Messages -->
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                    </div>
                <?php endif; ?>

                <!-- Wishlist Items or Empty State -->
                <?php if (empty($wishlist_items)): ?>
                    <div class="empty-wishlist">
                        <i class="fas fa-heart-broken"></i>
                        <h2>Your Wishlist is Empty</h2>
                        <p>Add your favorite items to your wishlist and revisit them whenever you're ready!</p>
                        <a href="shop.php">Continue Shopping</a>
                    </div>
                    
                    <!-- Add Product by Link Section (shown even when wishlist is empty) -->
                    <div class="add-by-link-section" style="margin-top: 20px;">
                        <h3><i class="fas fa-link"></i> Add Product by Link</h3>
                        <form method="POST" action="wishlist_add_by_link.php" class="link-form">
                            <div class="link-input-group">
                                <input type="url" name="product_link" placeholder="Enter product URL (e.g., https://example.com/product/123)" required>
                                <button type="submit" class="add-link-btn">
                                    <i class="fas fa-plus"></i> Add to Wishlist
                                </button>
                            </div>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="wishlist-table-wrapper">
                        <table class="wishlist-table">
                            <thead>
                                <tr>
                                    <th></th>
                                    <th>Image</th>
                                    <th>Product Name</th>
                                    <th>Price</th>
                                    <th>Date Added</th>
                                    <th>Stock Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($wishlist_items as $item): ?>
                                    <tr>
                                        <td>
                                        </td>
                                        <td class="product-img-cell">
                                            <img src="<?php echo !empty($item['image_url']) ? '../' . htmlspecialchars($item['image_url']) : 'https://via.placeholder.com/80'; ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                                        </td>
                                        <td>
                                            <div class="product-name-cell"><?php echo htmlspecialchars($item['name']); ?></div>
                                            <div class="product-quantity">Quantity: 1</div>
                                        </td>
                                        <td class="price-cell">$<?php echo number_format($item['price'], 2); ?></td>
                                        <td class="date-cell"><?php echo date('d F Y', strtotime($item['added_at'])); ?></td>
                                        <td class="stock-cell <?php echo $item['stock'] > 0 ? 'instock' : 'outstock'; ?>">
                                            <?php echo $item['stock'] > 0 ? 'Instock' : 'Out of Stock'; ?>
                                        </td>
                                        <td>
                                            <form method="POST" action="cart_add.php" style="display:inline;">
                                                <input type="hidden" name="product_id" value="<?php echo $item['id']; ?>">
                                                <button type="submit" class="add-to-cart-btn"><i class="fas fa-cart-plus"></i></button>
                                            </form>
                                            <a href="wishlist_remove.php?id=<?php echo $item['id']; ?>" class="remove-btn" title="Remove from Wishlist">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Wishlist Actions -->
                        <!-- Add Product by Link Section -->
                        <div class="add-by-link-section" style="margin-top: 20px;">
                            <h3><i class="fas fa-link"></i> Add Product by Link</h3>
                            <form method="POST" action="wishlist_add_by_link.php" class="link-form">
                                <div class="link-input-group">
                                <input class="wishlist-link-input" type="url" name="product_link" placeholder="Enter product URL (e.g., https://example.com/product/123)" required>
                                <button type="submit" class="add-link-btn">
                                    <i class="fas fa-plus"></i> Add to Wishlist
                                </button>
                                </div>
                            </form>
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

        // Copy wishlist link
        function copyWishlistLink() {
            const linkInput = document.querySelector('.wishlist-link-input');
            linkInput.select();
            document.execCommand('copy');
            alert('Link copied to clipboard!');
        }

        // Add all to cart
        function addAllToCart() {
            const forms = document.querySelectorAll('.wishlist-table form');
            forms.forEach(form => {
                form.submit();
            });
        }
    </script>
</body>
</html>