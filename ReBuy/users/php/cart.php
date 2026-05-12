<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get cart items
$stmt = $conn->prepare("
    SELECT c.id, c.quantity, p.id as product_id, p.name, p.price, p.image_url 
    FROM cart c 
    JOIN products p ON c.product_id = p.id 
    WHERE c.user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$cart_items = [];
$total_price = 0;

while ($row = $result->fetch_assoc()) {
    $cart_items[] = $row;
    $total_price += $row['price'] * $row['quantity'];
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

    <?php include '_header.php'; ?>

    <div class="page-container">
            <section class="cart-section">
                <h1>Shopping Cart</h1>
                <?php if (count($cart_items) > 0): ?>
                    <div class="cart-container">
                        <div class="cart-items">
                            <table class="cart-table">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Price</th>
                                        <th>Quantity</th>
                                        <th>Subtotal</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($cart_items as $item): ?>
                                        <tr>
                                            <td class="product-name">
                                                <img src="<?php echo !empty($item['image_url']) ? '../' . htmlspecialchars($item['image_url']) : 'https://via.placeholder.com/50'; ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                                                <?php echo htmlspecialchars($item['name']); ?>
                                            </td>
                                            <td>$<?php echo number_format($item['price'], 2); ?></td>
                                            <td>
                                                <form method="POST" action="cart_update.php" style="display: flex; gap: 5px;">
                                                    <input type="hidden" name="cart_id" value="<?php echo $item['id']; ?>">
                                                    <button type="submit" name="action" value="minus" class="qty-btn">-</button>
                                                    <input type="number" name="quantity" value="<?php echo $item['quantity']; ?>" readonly style="width: 40px; text-align: center;">
                                                    <button type="submit" name="action" value="plus" class="qty-btn">+</button>
                                                </form>
                                            </td>
                                            <td>$<?php echo number_format($item['price'] * $item['quantity'], 2); ?></td>
                                            <td>
                                                <a href="cart_remove.php?id=<?php echo $item['id']; ?>" class="btn-remove"><i class="fas fa-trash"></i></a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="cart-summary">
                            <h3>Order Summary</h3>
                            <div class="summary-row">
                                <span>Subtotal:</span>
                                <span>$<?php echo number_format($total_price, 2); ?></span>
                            </div>
                            <div class="summary-row">
                                <span>Shipping:</span>
                                <span>$0.00</span>
                            </div>
                            <div class="summary-row">
                                <span>Tax:</span>
                                <span>$0.00</span>
                            </div>
                            <hr>
                            <div class="summary-row total">
                                <span>Total:</span>
                                <span>$<?php echo number_format($total_price, 2); ?></span>
                            </div>
                            <a href="checkout.php" class="btn btn-checkout">Proceed to Checkout</a>
                            <a href="shop.php" class="btn btn-continue-shopping">Continue Shopping</a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="empty-cart">
                        <i class="fas fa-shopping-cart"></i>
                        <h2>Your cart is empty</h2>
                        <p>Add items to your cart to get started</p>
                        <a href="shop.php" class="btn btn-primary">Continue Shopping</a>
                    </div>
                <?php endif; ?>
            </section>
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
</body>

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
</html>
