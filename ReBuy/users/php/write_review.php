<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$order_id = $_GET['order_id'] ?? 0;

if (!$order_id) {
    header("Location: settings.php");
    exit();
}

// Fetch order details to get product info
$stmt = $conn->prepare("
    SELECT o.*, oi.product_id, p.name as product_name, p.image_url as product_image, p.seller_id
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    JOIN products p ON oi.product_id = p.id
    WHERE o.id = ? AND o.user_id = ?
    LIMIT 1
");
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$order = $result->fetch_assoc();
$stmt->close();

if (!$order) {
    header("Location: settings.php");
    exit();
}

$product_id = $order['product_id'];
$seller_id = $order['seller_id'];

// Check if user already reviewed this product
$check_stmt = $conn->prepare("SELECT id FROM reviews WHERE user_id = ? AND product_id = ?");
$check_stmt->bind_param("ii", $user_id, $product_id);
$check_stmt->execute();
$existing = $check_stmt->get_result()->fetch_assoc();
$check_stmt->close();

if ($existing) {
    header("Location: product_details.php?id=" . $product_id);
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

        .page-wrapper {
            min-height: 100vh;
        }

        .review-page {
            max-width: 800px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .review-container {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 2px 20px rgba(0,0,0,0.08);
        }

        .page-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .page-header h1 {
            font-size: 28px;
            color: #1a1a1a;
            margin-bottom: 10px;
        }

        .page-header p {
            color: #666;
            font-size: 14px;
        }

        .product-preview {
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 30px;
        }

        .product-preview img {
            width: 80px;
            height: 80px;
            border-radius: 8px;
            object-fit: cover;
            border: 1px solid #e9ecef;
        }

        .product-preview-info h3 {
            font-size: 16px;
            color: #333;
            margin-bottom: 5px;
        }

        .product-preview-info p {
            font-size: 13px;
            color: #666;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #333;
            margin-bottom: 10px;
        }

        .rating-input {
            display: flex;
            gap: 10px;
        }

        .rating-input label {
            font-size: 30px;
            color: #ddd;
            cursor: pointer;
            transition: color 0.3s;
        }

        .rating-input label:hover,
        .rating-input label.active {
            color: #ffc107;
        }

        .rating-input input {
            display: none;
        }

        textarea {
            width: 100%;
            padding: 15px;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            resize: vertical;
            min-height: 120px;
        }

        textarea:focus {
            outline: none;
            border-color: #2d5016;
        }

        .file-upload {
            border: 2px dashed #e9ecef;
            border-radius: 8px;
            padding: 30px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }

        .file-upload:hover {
            border-color: #2d5016;
            background: #f8f9fa;
        }

        .file-upload input {
            display: none;
        }

        .file-upload-icon {
            font-size: 40px;
            color: #2d5016;
            margin-bottom: 10px;
        }

        .file-upload-text {
            font-size: 14px;
            color: #666;
            margin-bottom: 5px;
        }

        .file-upload-hint {
            font-size: 12px;
            color: #999;
        }

        .preview-container {
            margin-top: 15px;
            display: none;
        }

        .preview-container.show {
            display: block;
        }

        .preview-item {
            position: relative;
            display: inline-block;
            margin-right: 10px;
            margin-bottom: 10px;
        }

        .preview-item img,
        .preview-item video {
            max-width: 150px;
            max-height: 150px;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }

        .remove-preview {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #dc3545;
            color: white;
            border: none;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .btn-submit {
            background: #2d5016;
            color: white;
            border: none;
            padding: 15px 40px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
            width: 100%;
        }

        .btn-submit:hover {
            background: #1e3009;
        }

        .btn-cancel {
            background: white;
            color: #333;
            border: 1px solid #ddd;
            padding: 15px 40px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            width: 100%;
            margin-top: 10px;
        }

        .btn-cancel:hover {
            background: #f8f9fa;
        }

        @media (max-width: 768px) {
            .review-page {
                padding: 20px 15px;
            }

            .review-container {
                padding: 25px;
            }

            .product-preview {
                flex-direction: column;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <?php include '_header.php'; ?>

    <div class="page-wrapper">
        <div class="review-page">
            <div class="review-container">
                <div class="page-header">
                    <h1>Write a Review</h1>
                    <p>Share your experience with this product</p>
                </div>

                <div class="product-preview">
                    <img src="<?php echo !empty($order['product_image']) ? '../' . htmlspecialchars($order['product_image']) : 'https://images.unsplash.com/photo-1586023492125-27b2c045efd7?ixlib=rb-4.0.3&auto=format&fit=crop&w=100&q=80'; ?>" alt="<?php echo htmlspecialchars($order['product_name']); ?>">
                    <div class="product-preview-info">
                        <h3><?php echo htmlspecialchars($order['product_name']); ?></h3>
                        <p>Order #<?php echo str_pad($order_id, 6, '0', STR_PAD_LEFT); ?></p>
                    </div>
                </div>

                <form method="POST" action="add_review.php" enctype="multipart/form-data" id="reviewForm">
                    <input type="hidden" name="product_id" value="<?php echo $product_id; ?>">
                    <input type="hidden" name="seller_id" value="<?php echo $seller_id; ?>">
                    <input type="hidden" name="redirect_to" value="product_details.php?id=<?php echo $product_id; ?>">

                    <div class="form-group">
                        <label>Rating</label>
                        <div class="rating-input">
                            <input type="hidden" name="rating" id="rating" value="5">
                            <label data-rating="1" onclick="setRating(1)"><i class="fas fa-star"></i></label>
                            <label data-rating="2" onclick="setRating(2)"><i class="fas fa-star"></i></label>
                            <label data-rating="3" onclick="setRating(3)"><i class="fas fa-star"></i></label>
                            <label data-rating="4" onclick="setRating(4)"><i class="fas fa-star"></i></label>
                            <label data-rating="5" onclick="setRating(5)"><i class="fas fa-star"></i></label>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="comment">Your Review</label>
                        <textarea id="comment" name="comment" placeholder="Share your experience with this product. What did you like or dislike?" required></textarea>
                    </div>

                    <div class="form-group">
                        <label>Add Photos or Videos (Optional)</label>
                        <div class="file-upload" onclick="document.getElementById('review_media').click();">
                            <div class="file-upload-icon">
                                <i class="fas fa-cloud-upload-alt"></i>
                            </div>
                            <div class="file-upload-text">Click to upload or drag and drop</div>
                            <div class="file-upload-hint">JPG, PNG, GIF, WebP, MP4, WebM (Max 10MB each, up to 5 files)</div>
                            <input type="file" id="review_media" name="review_media[]" accept="image/*,video/*" multiple onchange="previewFiles(this)">
                        </div>
                        <div class="preview-container" id="previewContainer">
                        </div>
                    </div>

                    <button type="submit" class="btn-submit">
                        <i class="fas fa-paper-plane"></i> Submit Review
                    </button>
                    <button type="button" class="btn-cancel" onclick="window.location.href='settings.php#orders'">
                        Cancel
                    </button>
                </form>
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

    <script>
        function setRating(rating) {
            document.getElementById('rating').value = rating;
            const labels = document.querySelectorAll('.rating-input label');
            labels.forEach(label => {
                const labelRating = parseInt(label.getAttribute('data-rating'));
                if (labelRating <= rating) {
                    label.classList.add('active');
                } else {
                    label.classList.remove('active');
                }
            });
        }

        function previewFiles(input) {
            const previewContainer = document.getElementById('previewContainer');
            previewContainer.innerHTML = '';
            
            if (input.files && input.files.length > 0) {
                const files = Array.from(input.files);
                
                files.forEach((file, index) => {
                    const reader = new FileReader();
                    
                    reader.onload = function(e) {
                        const previewItem = document.createElement('div');
                        previewItem.className = 'preview-item';
                        previewItem.dataset.index = index;
                        
                        const removeBtn = document.createElement('button');
                        removeBtn.type = 'button';
                        removeBtn.className = 'remove-preview';
                        removeBtn.innerHTML = '×';
                        removeBtn.onclick = function() { removeFile(index); };
                        previewItem.appendChild(removeBtn);
                        
                        if (file.type.startsWith('video/')) {
                            const video = document.createElement('video');
                            video.src = e.target.result;
                            video.controls = true;
                            previewItem.appendChild(video);
                        } else {
                            const img = document.createElement('img');
                            img.src = e.target.result;
                            previewItem.appendChild(img);
                        }
                        
                        previewContainer.appendChild(previewItem);
                    }
                    
                    reader.readAsDataURL(file);
                });
                
                previewContainer.classList.add('show');
            }
        }

        function removeFile(index) {
            const input = document.getElementById('review_media');
            const previewContainer = document.getElementById('previewContainer');
            
            // Create a new FileList without the removed file
            const files = Array.from(input.files);
            files.splice(index, 1);
            
            // Create a new DataTransfer object to set the files
            const dataTransfer = new DataTransfer();
            files.forEach(file => dataTransfer.items.add(file));
            input.files = dataTransfer.files;
            
            // Re-render previews
            previewFiles(input);
            
            if (files.length === 0) {
                previewContainer.classList.remove('show');
            }
        }

        // Initialize rating
        setRating(5);
    </script>
</body>
</html>
