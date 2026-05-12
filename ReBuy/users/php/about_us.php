<?php
session_start();
include 'db.php';

// Get statistics
$total_users = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];
$total_sellers = $conn->query("SELECT COUNT(*) as count FROM users WHERE is_seller = 1")->fetch_assoc()['count'];
$total_products = $conn->query("SELECT COUNT(*) as count FROM products")->fetch_assoc()['count'];
$total_orders = $conn->query("SELECT COUNT(*) as count FROM orders")->fetch_assoc()['count'];
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
        .hero-section {
            background: linear-gradient(135deg, #2d5016 0%, #4a7c2e 100%);
            color: white;
            padding: 100px 0;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="75" cy="75" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="50" cy="10" r="0.5" fill="rgba(255,255,255,0.05)"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            opacity: 0.3;
        }
        
        .hero-content {
            position: relative;
            z-index: 1;
            max-width: 800px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        .hero-content h1 {
            font-size: 3.5rem;
            margin-bottom: 20px;
            font-weight: 700;
        }
        
        .hero-content p {
            font-size: 1.3rem;
            opacity: 0.9;
            line-height: 1.6;
        }
        
        .content-section {
            padding: 80px 0;
            max-width: 1200px;
            margin: 0 auto;
            padding-left: 20px;
            padding-right: 20px;
        }
        
        .section-title {
            text-align: center;
            font-size: 2.5rem;
            color: #2d5016;
            margin-bottom: 50px;
            position: relative;
        }
        
        .section-title::after {
            content: '';
            position: absolute;
            bottom: -15px;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 4px;
            background: #4a7c2e;
            border-radius: 2px;
        }
        
        .story-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 40px;
            margin-bottom: 80px;
        }
        
        .story-card {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .story-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }
        
        .story-card i {
            font-size: 3rem;
            color: #4a7c2e;
            margin-bottom: 20px;
        }
        
        .story-card h3 {
            font-size: 1.5rem;
            color: #2d5016;
            margin-bottom: 15px;
        }
        
        .story-card p {
            color: #666;
            line-height: 1.6;
        }
        
        .mission-section {
            background: #f8f9fa;
            padding: 80px 0;
        }
        
        .mission-content {
            max-width: 1000px;
            margin: 0 auto;
            text-align: center;
            padding: 0 20px;
        }
        
        .mission-content h2 {
            font-size: 2.2rem;
            color: #2d5016;
            margin-bottom: 30px;
        }
        
        .mission-content p {
            font-size: 1.2rem;
            color: #555;
            line-height: 1.8;
            margin-bottom: 40px;
        }
        
        .values-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
            margin-top: 50px;
        }
        
        .value-item {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.08);
        }
        
        .value-item i {
            font-size: 2.5rem;
            color: #4a7c2e;
            margin-bottom: 15px;
        }
        
        .value-item h4 {
            font-size: 1.3rem;
            color: #2d5016;
            margin-bottom: 10px;
        }
        
        .value-item p {
            color: #666;
            line-height: 1.6;
        }
        
        .team-section {
            padding: 80px 0;
            background: white;
        }
        
        .team-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 40px;
            margin-top: 50px;
        }
        
        .team-member {
            text-align: center;
            padding: 30px;
            border-radius: 15px;
            background: #f8f9fa;
            transition: transform 0.3s ease;
        }
        
        .team-member:hover {
            transform: translateY(-5px);
        }
        
        .team-member img {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            margin-bottom: 20px;
            object-fit: cover;
            border: 5px solid #4a7c2e;
        }
        
        .team-member h4 {
            font-size: 1.3rem;
            color: #2d5016;
            margin-bottom: 5px;
        }
        
        .team-member p {
            color: #666;
            margin-bottom: 15px;
        }
        
        .team-member .social-links {
            display: flex;
            justify-content: center;
            gap: 15px;
        }
        
        .team-member .social-links a {
            color: #4a7c2e;
            font-size: 1.2rem;
            transition: color 0.3s ease;
        }
        
        .team-member .social-links a:hover {
            color: #2d5016;
        }
        
        .cta-section {
            background: linear-gradient(135deg, #f4c430 0%, #2d5016dc 100%);
            color: white;
            padding: 80px 0;
            text-align: center;
        }

        .stats-section {
            background: white;
            padding: 60px 0;
            margin-top: -60px;
            position: relative;
            z-index: 2;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 30px;
            max-width: 1000px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .stat-item {
            text-align: center;
            padding: 30px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }

        .stat-item:hover {
            transform: translateY(-5px);
        }

        .stat-number {
            font-size: 3rem;
            font-weight: 700;
            color: #2d5016;
            margin-bottom: 10px;
        }

        .stat-label {
            font-size: 1.1rem;
            color: #666;
            font-weight: 500;
        }

        .stat-icon {
            font-size: 2rem;
            color: #4a7c2e;
            margin-bottom: 15px;
        }
        
        .cta-content h2 {
            font-size: 2.5rem;
            margin-bottom: 20px;
        }
        
        .cta-content p {
            font-size: 1.2rem;
            margin-bottom: 30px;
            opacity: 0.9;
        }
        
        .btn-primary {
            background: white;
            color: #2d5016;
            padding: 15px 40px;
            border: none;
            border-radius: 25px;
            font-size: 1.1rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        }
        
        @media (max-width: 768px) {
            .hero-content h1 {
                font-size: 2.5rem;
            }
            
            .hero-content p {
                font-size: 1.1rem;
            }
            
            .section-title {
                font-size: 2rem;
            }
            
            .story-grid, .values-grid, .team-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

    <?php include '_header.php'; ?>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="hero-content">
            <h1>About ReBuy</h1>
            <p>Transforming the way we shop second-hand while building a more sustainable future</p>
        </div>
    </section>

    <!-- Statistics Section -->
    <section class="stats-section">
        <div class="stats-grid">
            <div class="stat-item">
                <div class="stat-icon"><i class="fas fa-users"></i></div>
                <div class="stat-number"><?php echo number_format($total_users); ?></div>
                <div class="stat-label">Total Users</div>
            </div>
            <div class="stat-item">
                <div class="stat-icon"><i class="fas fa-store"></i></div>
                <div class="stat-number"><?php echo number_format($total_sellers); ?></div>
                <div class="stat-label">Active Sellers</div>
            </div>
            <div class="stat-item">
                <div class="stat-icon"><i class="fas fa-shopping-bag"></i></div>
                <div class="stat-number"><?php echo number_format($total_products); ?></div>
                <div class="stat-label">Products Listed</div>
            </div>
            <div class="stat-item">
                <div class="stat-icon"><i class="fas fa-shopping-cart"></i></div>
                <div class="stat-number"><?php echo number_format($total_orders); ?></div>
                <div class="stat-label">Orders Completed</div>
            </div>
        </div>
    </section>

    <!-- Our Story Section -->
    <section class="content-section">
        <h2 class="section-title">Our Story</h2>
        <div class="story-grid">
            <div class="story-card">
                <i class="fas fa-lightbulb"></i>
                <h3>The Beginning</h3>
                <p>ReBuy started with a simple idea: make quality second-hand items accessible to everyone while reducing waste and promoting sustainability.</p>
            </div>
            <div class="story-card">
                <i class="fas fa-users"></i>
                <h3>Growing Community</h3>
                <p>From a small startup to a thriving marketplace, we've built a community of thousands of buyers and sellers who believe in sustainable shopping.</p>
            </div>
            <div class="story-card">
                <i class="fas fa-leaf"></i>
                <h3>Environmental Impact</h3>
                <p>Every item purchased through ReBuy helps reduce carbon footprint and keeps quality products out of landfills.</p>
            </div>
        </div>
    </section>

    <!-- Mission Section -->
    <section class="mission-section">
        <div class="mission-content">
            <h2>Our Mission</h2>
            <p>To revolutionize second-hand commerce by providing a trusted, user-friendly platform that connects buyers and sellers while promoting environmental sustainability and circular economy principles.</p>
            
            <div class="values-grid">
                <div class="value-item">
                    <i class="fas fa-shield-alt"></i>
                    <h4>Trust & Safety</h4>
                    <p>We ensure every transaction is secure and every item meets quality standards.</p>
                </div>
                <div class="value-item">
                    <i class="fas fa-recycle"></i>
                    <h4>Sustainability</h4>
                    <p>Promoting circular economy and reducing environmental impact through reuse.</p>
                </div>
                <div class="value-item">
                    <i class="fas fa-handshake"></i>
                    <h4>Community</h4>
                    <p>Building a supportive community of conscious consumers and sellers.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Team Section -->
    <section class="team-section">
        <div class="content-section">
            <h2 class="section-title">Meet Our Team</h2>
            <div class="team-grid">
                <div class="team-member">
                    <div style="width: 150px; height: 150px; border-radius: 50%; margin: 0 auto 20px; background: url('../../assets/alexa.jpg') center/cover; display: flex; align-items: center; justify-content: center; border: solid 4px #4a7c2e">
                    </div>
                    <h4>Alexamarie</h4>
                    <p>Project Manager</p>
                    <div class="social-links">
                        <a href="https://github.com/alexamarieantoquia034-cpu" target="_blank"><i class="fab fa-github"></i></a>
                    </div>
                </div>
                <div class="team-member">
                    <div style="width: 150px; height: 150px; border-radius: 50%; margin: 0 auto 20px; background: url('../../assets/cyrel.jpg') center/cover; display: flex; align-items: center; justify-content: center; border: solid 4px #4a7c2e">
                    </div>
                    <h4>Cyrel</h4>
                    <p>System Analyst</p>
                    <div class="social-links">
                        <a href="https://github.com/sairil12" target="_blank"><i class="fab fa-github"></i></a>
                    </div>
                </div>
                <div class="team-member">
                    <div style="width: 150px; height: 150px; border-radius: 50%; margin: 0 auto 20px; background: url('../../assets/claresse.jpg') center/cover; display: flex; align-items: center; justify-content: center; border: solid 4px #4a7c2e">
                    </div>
                    <h4>Claresse</h4>
                    <p>Lead Developer</p>
                    <div class="social-links">
                        <a href="https://github.com/Mariaclaresse" target="_blank"><i class="fab fa-github"></i></a>
                    </div>
                </div>
            </div>
        </div>
    </section>

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

        // Smooth scroll animation for elements
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver(function(entries) {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);

        // Observe story cards and team members
        document.querySelectorAll('.story-card, .team-member, .value-item').forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(30px)';
            el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            observer.observe(el);
        });
    </script>
</body>
</html>
