<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get user info
$stmt = $conn->prepare("SELECT first_name, last_name, email FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Events - ReBuy</title>
    <link rel="icon" type="image/x-icon" href="../../assets/logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../css/header-footer.css">
    <style>
        .hero-banner {
            background: linear-gradient(135deg, #2d5016 0%, #4a7c2e 100%);
            color: white;
            padding: 80px 40px;
            text-align: center;
            margin-bottom: 60px;
        }
        .hero-banner h1 {
            font-size: 48px;
            margin-bottom: 20px;
            font-weight: 700;
        }
        .hero-banner p {
            font-size: 18px;
            max-width: 800px;
            margin: 0 auto;
            opacity: 0.9;
        }
        .events-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 40px 60px;
        }
        .event-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
            margin-bottom: 30px;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .event-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 40px rgba(45, 80, 22, 0.15);
        }
        .event-image {
            width: 100%;
            height: 250px;
            object-fit: cover;
        }
        .event-content {
            padding: 30px;
        }
        .event-content h3 {
            font-size: 24px;
            color: #2d5016;
            margin-bottom: 15px;
            font-weight: 600;
        }
        .event-content p {
            color: #666;
            line-height: 1.6;
            margin-bottom: 20px;
        }
        .event-badge {
            display: inline-block;
            background: #2d5016;
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 15px;
        }
        .event-badge.upcoming {
            background: #ff9800;
        }
        .event-badge.past {
            background: #999;
        }
        .event-date {
            color: #999;
            font-size: 14px;
            margin-bottom: 15px;
        }
        .event-date i {
            margin-right: 5px;
        }
        .event-location {
            color: #666;
            font-size: 14px;
            margin-bottom: 20px;
        }
        .event-location i {
            margin-right: 5px;
            color: #2d5016;
        }
        .btn-register {
            display: inline-block;
            background: #2d5016;
            color: white;
            padding: 12px 30px;
            border-radius: 30px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-register:hover {
            background: #4a7c2e;
            transform: translateY(-2px);
        }
        .btn-register.disabled {
            background: #999;
            cursor: not-allowed;
            pointer-events: none;
        }
    </style>
</head>
<body>
    <div class="page-wrapper">
        <?php include '_header.php'; ?>

        <!-- Hero Banner -->
        <section class="hero-banner">
            <h1>Upcoming Events</h1>
            <p>Join our exclusive seller meetups, community swap events, and special product launches. Stay connected with the ReBuy community!</p>
        </section>

        <!-- Events Container -->
        <div class="events-container">
            <!-- Event 1 -->
            <div class="event-card">
                <img src="https://images.unsplash.com/photo-1511578314322-379afb476865?ixlib=rb-4.0.3&auto=format&fit=crop&w=1200&q=80" alt="Seller Meetup" class="event-image">
                <div class="event-content">
                    <span class="event-badge upcoming">UPCOMING</span>
                    <div class="event-date"><i class="fas fa-calendar-alt"></i> January 20, 2027 - 10:00 AM</div>
                    <div class="event-location"><i class="fas fa-map-marker-alt"></i> ReBuy Community Center, Cabadbaran City</div>
                    <h3>Seller Meetup & Networking Event</h3>
                    <p>Connect with fellow sellers, share best practices, and learn tips for growing your ReBuy business. Free refreshments and networking opportunities. Open to all registered sellers.</p>
                    <a href="#" class="btn-register">Register Now</a>
                </div>
            </div>

            <!-- Event 2 -->
            <div class="event-card">
                <img src="https://images.unsplash.com/photo-1556742049-0cfed4f6a45d?ixlib=rb-4.0.3&auto=format&fit=crop&w=1200&q=80" alt="Community Swap" class="event-image">
                <div class="event-content">
                    <span class="event-badge upcoming">UPCOMING</span>
                    <div class="event-date"><i class="fas fa-calendar-alt"></i> February 14, 2027 - 9:00 AM</div>
                    <div class="event-location"><i class="fas fa-map-marker-alt"></i> City Plaza, Cabadbaran City</div>
                    <h3>Community Swap Meet</h3>
                    <p>Bring your pre-loved items and swap with others in the community! A great way to refresh your belongings while reducing waste. No money needed - just bring items to trade. Free entry for everyone!</p>
                    <a href="#" class="btn-register">Register Now</a>
                </div>
            </div>

            <!-- Event 3 -->
            <div class="event-card">
                <img src="https://images.unsplash.com/photo-1542291026-7eec264c27ff?ixlib=rb-4.0.3&auto=format&fit=crop&w=1200&q=80" alt="Product Launch" class="event-image">
                <div class="event-content">
                    <span class="event-badge upcoming">UPCOMING</span>
                    <div class="event-date"><i class="fas fa-calendar-alt"></i> March 5, 2027 - 2:00 PM</div>
                    <div class="event-location"><i class="fas fa-map-marker-alt"></i> Online Event (Zoom)</div>
                    <h3>New Features Launch Event</h3>
                    <p>Be the first to see the new features coming to ReBuy! Join our virtual launch event for exclusive demos, Q&A sessions, and special early-access perks. All registered users are welcome to attend.</p>
                    <a href="#" class="btn-register">Register Now</a>
                </div>
            </div>

            <!-- Event 4 -->
            <div class="event-card">
                <img src="https://images.unsplash.com/photo-1596462502278-27bfdc403348?ixlib=rb-4.0.3&auto=format&fit=crop&w=1200&q=80" alt="Charity Drive" class="event-image">
                <div class="event-content">
                    <span class="event-badge upcoming">UPCOMING</span>
                    <div class="event-date"><i class="fas fa-calendar-alt"></i> April 22, 2027 - 8:00 AM</div>
                    <div class="event-location"><i class="fas fa-map-marker-alt"></i> Multiple Locations</div>
                    <h3>Earth Day Charity Drive</h3>
                    <p>Donate your unused items to support local communities. We're collecting gently used clothing, books, and household items for those in need. A great way to give back while decluttering your home.</p>
                    <a href="#" class="btn-register">Register Now</a>
                </div>
            </div>

            <!-- Past Event -->
            <div class="event-card">
                <img src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?ixlib=rb-4.0.3&auto=format&fit=crop&w=1200&q=80" alt="Past Event" class="event-image">
                <div class="event-content">
                    <span class="event-badge past">PAST EVENT</span>
                    <div class="event-date"><i class="fas fa-calendar-alt"></i> December 15, 2026</div>
                    <div class="event-location"><i class="fas fa-map-marker-alt"></i> ReBuy Office</div>
                    <h3>Holiday Seller Workshop</h3>
                    <p>A successful workshop where sellers learned about holiday marketing strategies and customer engagement tips. Thank you to all who attended!</p>
                    <a href="#" class="btn-register disabled">Event Ended</a>
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
    </div>

    <script src="../js/notification.js"></script>
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
