<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Check if user is a seller
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
</head>
<body>
    <div class="page-wrapper">

        <?php include '_header.php'; ?>

        <!-- Page Content -->
        <div class="page-content" style="max-width: 1200px; margin: 0 auto; padding: 40px;">
            <h1 style="font-size: 28px; color: #333; margin-bottom: 30px;"><i class="fas fa-bell"></i> Seller Notifications</h1>

            <div style="background: white; padding: 40px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); text-align: center;">
                <i class="fas fa-bell-slash" style="font-size: 60px; color: #ddd; display: block; margin-bottom: 20px;"></i>
                <h2 style="color: #999; margin-bottom: 10px;">No notifications</h2>
                <p style="color: #ccc; margin-bottom: 20px;">You don't have any seller notifications at this time. Stay tuned for updates!</p>
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
                    </select>
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
    </script>
</body>
</html>
