<?php
session_start();
require_once 'db.php';

// Check if user is logged in
$is_logged_in = isset($_SESSION['user_id']);
if (!$is_logged_in) {
    echo '<div style="background: #fff3cd; color: #856404; padding: 10px; margin: 10px; border-radius: 5px; text-align: center;">
            <strong>Notice:</strong> You must be <a href="login.php" style="color: #856404; font-weight: bold;">logged in</a> to add items to cart.
          </div>';
}

// Get product ID from URL
$product_id = $_GET['id'] ?? 0;
if (!$product_id) {
    header("Location: shop.php");
    exit();
}

// Get product details
$stmt = $conn->prepare("SELECT p.*, u.first_name, u.last_name, u.id as seller_id FROM products p LEFT JOIN users u ON p.seller_id = u.id WHERE p.id = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();
$product = $result->fetch_assoc();
$stmt->close();

// Check if product is in user's wishlist
$is_in_wishlist = false;
if ($is_logged_in) {
    $wishlist_stmt = $conn->prepare("SELECT id FROM wishlist WHERE user_id = ? AND product_id = ?");
    $wishlist_stmt->bind_param("ii", $_SESSION['user_id'], $product_id);
    $wishlist_stmt->execute();
    $wishlist_result = $wishlist_stmt->get_result();
    $is_in_wishlist = $wishlist_result->num_rows > 0;
    $wishlist_stmt->close();
}

if (!$product) {
    header("Location: shop.php");
    exit();
}

// Get all product images (main image + additional images)
$product_images = [];
$product_images[] = $product['image_url']; // Add main image first

// Get additional images from product_images table
$additional_images_stmt = $conn->prepare("SELECT image_url FROM product_images WHERE product_id = ? ORDER BY created_at ASC");
$additional_images_stmt->bind_param("i", $product_id);
$additional_images_stmt->execute();
$additional_images_result = $additional_images_stmt->get_result();

while ($row = $additional_images_result->fetch_assoc()) {
    $product_images[] = $row['image_url'];
}
$additional_images_stmt->close();

// Get related products (same category, excluding current product)
$related_stmt = $conn->prepare("SELECT id, name, price, original_price, image_url FROM products WHERE category = ? AND id != ? ORDER BY RAND() LIMIT 4");
$related_stmt->bind_param("si", $product['category'], $product_id);
$related_stmt->execute();
$related_result = $related_stmt->get_result();

// Get more recommended products from same category (for the shop category section)
$recommended_stmt = $conn->prepare("SELECT id, name, price, original_price, image_url FROM products WHERE category = ? AND id != ? ORDER BY RAND() LIMIT 8");
$recommended_stmt->bind_param("si", $product['category'], $product_id);
$recommended_stmt->execute();
$recommended_result = $recommended_stmt->get_result();

// Get reviews for this product
$reviews_stmt = $conn->prepare("SELECT r.*, u.first_name, u.last_name FROM reviews r JOIN users u ON r.user_id = u.id WHERE r.product_id = ? ORDER BY r.created_at DESC");
$reviews_stmt->bind_param("i", $product_id);
$reviews_stmt->execute();
$reviews_result = $reviews_stmt->get_result();
$reviews_stmt->close();

// Get media for all reviews
$review_media = [];
$media_stmt = $conn->prepare("SELECT rm.* FROM review_media rm JOIN reviews r ON rm.review_id = r.id WHERE r.product_id = ? ORDER BY rm.created_at ASC");
$media_stmt->bind_param("i", $product_id);
$media_stmt->execute();
$media_result = $media_stmt->get_result();
while ($row = $media_result->fetch_assoc()) {
    $review_media[$row['review_id']][] = $row;
}
$media_stmt->close();

// Get total number of reviews for this product
$total_reviews_stmt = $conn->prepare("SELECT COUNT(*) as total_reviews FROM reviews WHERE product_id = ?");
$total_reviews_stmt->bind_param("i", $product_id);
$total_reviews_stmt->execute();
$total_reviews_result = $total_reviews_stmt->get_result();
$total_reviews_data = $total_reviews_result->fetch_assoc();
$total_reviews = $total_reviews_data['total_reviews'];
$total_reviews_stmt->close();

// Create product_colors table if it doesn't exist
$conn->query("CREATE TABLE IF NOT EXISTS product_colors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    color VARCHAR(50) NOT NULL,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
)");

// Create product_sizes table if it doesn't exist
$conn->query("CREATE TABLE IF NOT EXISTS product_sizes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    size VARCHAR(10) NOT NULL,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
)");

// Get product colors
$product_colors = [];
$colors_stmt = $conn->prepare("SELECT color FROM product_colors WHERE product_id = ?");
$colors_stmt->bind_param("i", $product_id);
$colors_stmt->execute();
$colors_result = $colors_stmt->get_result();

while ($row = $colors_result->fetch_assoc()) {
    $product_colors[] = $row['color'];
}
$colors_stmt->close();

// Get product sizes
$product_sizes = [];
$sizes_stmt = $conn->prepare("SELECT size FROM product_sizes WHERE product_id = ?");
$sizes_stmt->bind_param("i", $product_id);
$sizes_stmt->execute();
$sizes_result = $sizes_stmt->get_result();

while ($row = $sizes_result->fetch_assoc()) {
    $product_sizes[] = $row['size'];
}
$sizes_stmt->close();

// Get count of reviews for each star rating
$rating_counts_stmt = $conn->prepare("SELECT rating, COUNT(*) as count FROM reviews WHERE product_id = ? GROUP BY rating ORDER BY rating DESC");
$rating_counts_stmt->bind_param("i", $product_id);
$rating_counts_stmt->execute();
$rating_counts_result = $rating_counts_stmt->get_result();
$rating_counts = array();
while ($row = $rating_counts_result->fetch_assoc()) {
    $rating_counts[$row['rating']] = $row['count'];
}
$rating_counts_stmt->close();

// Helper function to get hex color from color name
function getColorHex($colorName) {
    $colorMap = [
        'Red' => '#FF0000',
        'Blue' => '#0000FF',
        'Green' => '#00FF00',
        'Yellow' => '#FFFF00',
        'Black' => '#000000',
        'White' => '#FFFFFF',
        'Gray' => '#808080',
        'Grey' => '#808080',
        'Brown' => '#8B4513',
        'Pink' => '#FFC0CB',
        'Purple' => '#800080',
        'Orange' => '#FFA500',
        'Navy' => '#000080',
        'Teal' => '#008080',
        'Maroon' => '#800000',
        'Lime' => '#00FF00',
        'Aqua' => '#00FFFF',
        'Silver' => '#C0C0C0',
        'Gold' => '#FFD700',
        'Beige' => '#F5F5DC',
        'Tan' => '#D2B48C',
        'Olive' => '#808000',
        'Coral' => '#FF7F50',
        'Salmon' => '#FA8072',
        'Khaki' => '#F0E68C',
        'Indigo' => '#4B0082',
        'Violet' => '#EE82EE'
    ];
    
    return $colorMap[ucfirst($colorName)] ?? '#CCCCCC';
}

// Initialize all star counts to 0
for ($i = 1; $i <= 5; $i++) {
    if (!isset($rating_counts[$i])) {
        $rating_counts[$i] = 0;
    }
}

// Check if current user has already reviewed this product
$user_has_reviewed = false;
$user_review = null;
if ($is_logged_in) {
    $user_review_stmt = $conn->prepare("SELECT r.*, u.first_name, u.last_name FROM reviews r JOIN users u ON r.user_id = u.id WHERE r.product_id = ? AND r.user_id = ?");
    $user_review_stmt->bind_param("ii", $product_id, $_SESSION['user_id']);
    $user_review_stmt->execute();
    $user_review_result = $user_review_stmt->get_result();
    if ($user_review_result->num_rows > 0) {
        $user_has_reviewed = true;
        $user_review = $user_review_result->fetch_assoc();
    }
    $user_review_stmt->close();
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

        .product-page {
            max-width: 1350px;
            margin: 0 auto;
            padding: 25px;
        }

        .product-container {
            display: grid;
            grid-template-columns: 1.3fr 1.5fr;
            background: white;
            padding: 50px;
            border-radius: 20px;
            box-shadow: 0 2px 20px rgba(0,0,0,0.08);
            margin-bottom: 20px;
            gap: 40px;
        }

        .product-images {
            position: relative;
        }

        .main-image {
            width: 100%;
            height: 550px;
            background: #f8f9fa;
            border-radius: 8px;
            overflow: hidden;
            position: relative;
            border: 1px solid #e9ecef;
        }

        .main-image-container {
            width: 100%;
            height: 100%;
            position: relative;
        }

        .main-image-item {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            opacity: 0;
            transition: opacity 0.3s ease, transform 0.5s ease;
        }

        .main-image-item.active {
            opacity: 1;
        }

        .main-image-item:hover {
            transform: scale(1.05);
        }

        .image-nav {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(0, 0, 0, 0.5);
            color: white;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            transition: background 0.3s ease;
            z-index: 10;
        }

        .image-nav:hover {
            background: rgba(0, 0, 0, 0.7);
        }

        .image-nav.prev {
            left: 10px;
        }

        .image-nav.next {
            right: 10px;
        }

        .image-thumbnails {
            display: flex;
            gap: 20px;
            margin-top: 15px;
            overflow-x: auto;
            padding-bottom: 5px;
        }

        .image-thumbnails::-webkit-scrollbar {
            height: 4px;
        }

        .image-thumbnails::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 2px;
        }

        .image-thumbnails::-webkit-scrollbar-thumb {
            background: #2d5016;
            border-radius: 2px;
        }

        .thumbnail {
            min-width: 100px;
            width: 100px;
            height: 100px;
            border-radius: 6px;
            overflow: hidden;
            cursor: pointer;
            border: 2px solid transparent;
            transition: all 0.3s ease;
            flex-shrink: 0;
        }

        .thumbnail:hover,
        .thumbnail.active {
            border-color: #2d5016;
        }

        .thumbnail img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .product-info {
            padding: 5px 0;
        }

        .product-badge {
            display: inline-block;
            background: #e8f5e8;
            color: #2d5016;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .product-title {
            font-size: 30px;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 5px;
            line-height: 1;
        }

        .product-rating {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 10px;
        }

        .stars {
            display: flex;
            gap: 1px;
        }

        .star {
            color: #ffc107;
            font-size: 15px;
        }

        .star.empty {
            color: #e9ecef;
        }

        .rating-text {
            color: #666;
            font-size: 14px;
        }

        .product-price {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 10px;
            padding: 20px 0;
            border-top: 1px solid #e9ecef;
            border-bottom: 1px solid #e9ecef;
        }

        .current-price {
            font-size: 32px;
            font-weight: 700;
            color: #2d5016;
        }

        .original-price {
            font-size: 20px;
            color: #999;
            text-decoration: line-through;
        }

        .discount-badge {
            background: #ff4444;
            color: white;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
        }

        .product-meta {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 15px;
        }

        .meta-item {
            text-align: center;
            padding: 12px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .meta-icon {
            font-size: 20px;
            color: #2d5016;
            margin-bottom: 8px;
        }

        .meta-label {
            font-size: 12px;
            color: #666;
            margin-bottom: 5px;
        }

        .meta-value {
            font-size: 15px;
            font-weight: 600;
            color: #333;
        }

        .product-description {
            margin-bottom: 15px;
        }

        .description-title {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 5px;
            color: #1a1a1a;
        }

        .description-text {
            line-height: 1.2;
            color: #666;
        }

        .weight-options {
            margin-bottom: 20px;
        }

        .weight-label {
            font-size: 14px;
            font-weight: 600;
            color: #666;
            margin-bottom: 10px;
        }

        .weight-buttons {
            display: flex;
            gap: 10px;
        }

        .weight-btn {
            padding: 10px 20px;
            border: 2px solid #e9ecef;
            background: white;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            color: #666;
            transition: all 0.3s ease;
        }

        .weight-btn:hover {
            border-color: #2d5016;
            color: #2d5016;
        }

        .weight-btn.active {
            background: #2d5016;
            color: white;
            border-color: #2d5016;
        }

        .seller-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .seller-details {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .seller-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #2d5016;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 17px;
        }

        .seller-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 4px;
        }

        .seller-status {
            font-size: 12px;
            color: #28a745;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
        }

        .quantity-section {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .quantity-label {
            font-size: 14px;
            color: #666;
            font-weight: 600;
        }

        .quantity-selector {
            display: flex;
            align-items: center;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            overflow: hidden;
        }

        .quantity-btn {
            background: white;
            border: none;
            width: 30px;
            height: 10px;
            cursor: pointer;
            font-size: 15px;
            transition: background 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .quantity-btn:hover {
            background: #f8f9fa;
        }

        .quantity-input {
            border: none;
            width: 40px;
            height: 40px;
            text-align: center;
            font-size: 14px;
            font-weight: 600;
        }

        .btn-add-cart {
            background: #2d5016;
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            flex: 1;
            justify-content: center;
        }

        .btn-add-cart:hover {
            background: #1e3009;
            transform: translateY(-2px);
        }

        .btn-buy-now {
            background: #f4c430;
            color: #333;
            border: none;
            padding: 15px 30px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            flex: 1;
        }

        .btn-buy-now:hover {
            background: #e6b800;
            transform: translateY(-2px);
        }

        .btn-wishlist {
            background: white;
            color: #e74c3c;
            border: 2px solid #e74c3c;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            font-size: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
        }

        .btn-wishlist:hover {
            background: #e74c3c;
            color: white;
            transform: scale(1.1);
        }

        .btn-wishlist.in-wishlist {
            background: #e74c3c;
            color: white;
        }

        .btn-wishlist.in-wishlist:hover {
            background: #c0392b;
        }

        .product-features {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .feature {
            text-align: center;
            padding: 15px;
        }

        .feature-icon {
            font-size: 24px;
            color: #2d5016;
            margin-bottom: 8px;
        }

        .feature-text {
            font-size: 12px;
            color: #666;
        }

        .related-products {
            margin-bottom: 10px;
        }

        .section-header {
            text-align: center;
            margin-bottom: 50px;
        }

        .section-title {
            font-size: 28px;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 10px;
        }

        .section-subtitle {
            color: #666;
            font-size: 16px;
        }

        .related-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 25px;
        }

        .related-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 15px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .related-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .related-image {
            height: 200px;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
        }

        .related-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .related-card:hover .related-image img {
            transform: scale(1.05);
        }

        .related-discount {
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

        .related-info {
            padding: 20px;
        }

        .related-category {
            font-size: 12px;
            color: #666;
            margin-bottom: 8px;
        }

        .related-name {
            font-weight: 600;
            margin-bottom: 12px;
            color: #1a1a1a;
            font-size: 16px;
            line-height: 1.4;
        }

        .related-price {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .related-current {
            color: #2d5016;
            font-weight: 700;
            font-size: 18px;
        }

        .related-original {
            color: #999;
            text-decoration: line-through;
            font-size: 14px;
        }

        .add-to-cart-btn {
            width: 100%;
            background: #2d5016;
            color: white;
            border: none;
            padding: 10px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
            margin-top: 15px;
        }

        .add-to-cart-btn:hover {
            background: #1e3009;
        }

        .featured-categories {
            margin-bottom: 60px;
            padding: 60px 0;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 12px;
        }

        .categories-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 30px;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .category-card {
            background: white;
            padding: 30px 20px;
            border-radius: 12px;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
            box-shadow: 0 2px 15px rgba(0,0,0,0.08);
            position: relative;
            overflow: hidden;
        }

        .category-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #2d5016, #4a7c2e);
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }

        .category-card:hover::before {
            transform: scaleX(1);
        }

        .category-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 30px rgba(0,0,0,0.15);
        }

        .category-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 20px;
            background: linear-gradient(135deg, #2d5016, #4a7c2e);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            color: white;
            transition: all 0.3s ease;
        }

        .category-card:hover .category-icon {
            transform: scale(1.1);
            box-shadow: 0 8px 20px rgba(45, 80, 22, 0.3);
        }

        .category-card h3 {
            font-size: 20px;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 10px;
        }

        .category-card p {
            color: #666;
            font-size: 14px;
            margin-bottom: 20px;
            line-height: 1.5;
        }

        .category-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #2d5016;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .category-link:hover {
            color: #4a7c2e;
            gap: 12px;
        }

        .category-link i {
            font-size: 12px;
            transition: transform 0.3s ease;
        }

        .category-card:hover .category-link i {
            transform: translateX(4px);
        }

        @media (max-width: 1024px) {
            .product-container {
                grid-template-columns: 1fr;
                gap: 40px;
            }

            .related-grid {
                grid-template-columns: repeat(3, 1fr);
            }

            .product-features {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .product-page {
                padding: 15px;
            }

            .product-container {
                padding: 25px;
            }

            .main-image {
                height: 350px;
            }

            .product-title {
                font-size: 24px;
            }

            .current-price {
                font-size: 24px;
            }

            .product-meta {
                grid-template-columns: 1fr;
            }

            .action-buttons {
                flex-direction: column;
            }

            .related-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .product-features {
                grid-template-columns: 1fr;
            }
        }

        .shop-hero {
            background: linear-gradient(135deg, #2d5016 0%, #4a7c2e 100%);
            color: white;
            padding: 30px 0 30px;
            text-align: center;
        }
        .shop-hero h1 {
            font-size: 35px;
            margin-bottom: 10px;
            font-weight: 700;
        }
        .shop-hero p {
            font-size: 16px;
            opacity: 0.9;
            margin-bottom: 20px;
        }

        /* Product Tabs */
        .product-tabs-section {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.08);
        }

        .tabs-header {
            display: flex;
            gap: 30px;
            border-bottom: 2px solid #e9ecef;
            margin-bottom: 25px;
        }

        .tab-btn {
            padding: 15px 0;
            background: none;
            border: none;
            font-size: 16px;
            font-weight: 600;
            color: #666;
            cursor: pointer;
            position: relative;
            transition: color 0.3s;
        }

        .tab-btn:hover {
            color: #2d5016;
        }

        .tab-btn.active {
            color: #2d5016;
        }

        .tab-btn.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            right: 0;
            height: 2px;
            background: #2d5016;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .tab-description {
            line-height: 1.8;
            color: #666;
            font-size: 15px;
        }

        .additional-info-table table {
            width: 100%;
            border-collapse: collapse;
        }

        .additional-info-table td {
            padding: 15px 20px;
            border-bottom: 1px solid #e9ecef;
            font-size: 14px;
        }

        .additional-info-table td:first-child {
            font-weight: 600;
            color: #333;
            width: 200px;
            background: #f8f9fa;
        }

        .additional-info-table td:last-child {
            color: #666;
        }

        .review-section {
            color: #666;
            font-size: 15px;
        }

        .review-form {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 30px;
        }

        .review-form h3 {
            font-size: 18px;
            color: #333;
            margin-bottom: 20px;
        }

        .rating-input {
            display: flex;
            gap: 5px;
            margin-bottom: 15px;
        }

        .rating-input input {
            display: none;
        }

        .rating-input label {
            font-size: 24px;
            color: #ddd;
            cursor: pointer;
            transition: color 0.3s;
        }

        .rating-input label.active,
        .rating-input label:hover {
            color: #ffc107;
        }

        .review-textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            resize: vertical;
            min-height: 100px;
            margin-bottom: 15px;
        }

        .review-textarea:focus {
            outline: none;
            border-color: #2d5016;
        }

        .submit-review-btn {
            background: #2d5016;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
        }

        .submit-review-btn:hover {
            background: #1a3009;
        }

        .product-options {
            margin-bottom: 20px;
        }

        .option-title {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin-bottom: 10px;
        }

        .color-options {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }

        .color-option {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            background: white;
        }

        .color-option:hover {
            border-color: #2d5016;
            transform: translateY(-2px);
        }

        .color-option.selected {
            border-color: #2d5016;
            background: #f0f8e6;
        }

        .color-swatch {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            border: 1px solid #ddd;
        }

        .color-name {
            font-size: 14px;
            color: #333;
        }

        .size-options {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .size-option {
            padding: 10px 16px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            background: white;
            font-size: 14px;
            font-weight: 500;
            color: #333;
            min-width: 50px;
            text-align: center;
        }

        .size-option:hover {
            border-color: #2d5016;
            transform: translateY(-2px);
        }

        .size-option.selected {
            border-color: #2d5016;
            background: #2d5016;
            color: white;
        }

        .confirmation-dialog {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }

        .confirmation-content {
            background: white;
            border-radius: 12px;
            width: 90%;
            max-width: 400px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            animation: slideUp 0.3s ease;
        }

        @keyframes slideUp {
            from {
                transform: translateY(50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .confirmation-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            border-bottom: 1px solid #e9ecef;
        }

        .confirmation-header h3 {
            margin: 0;
            font-size: 18px;
            color: #333;
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 18px;
            color: #666;
            cursor: pointer;
            padding: 5px;
            border-radius: 4px;
            transition: background 0.3s;
        }

        .close-btn:hover {
            background: #f0f0f0;
        }

        .confirmation-body {
            padding: 20px;
        }

        .product-summary h4 {
            margin: 0 0 15px 0;
            font-size: 16px;
            color: #333;
            font-weight: 600;
        }

        .selected-options {
            margin-bottom: 20px;
        }

        .selected-option {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .selected-option:last-child {
            border-bottom: none;
        }

        .option-label {
            font-weight: 500;
            color: #666;
        }

        .option-value {
            font-weight: 600;
            color: #333;
        }

        .price-summary {
            border-top: 1px solid #e9ecef;
            padding-top: 15px;
        }

        .price-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 5px 0;
        }

        .price-row.total {
            font-weight: 600;
            font-size: 16px;
            color: #2d5016;
            border-top: 1px solid #e9ecef;
            padding-top: 10px;
            margin-top: 5px;
        }

        .confirmation-footer {
            display: flex;
            gap: 10px;
            padding: 20px;
            border-top: 1px solid #e9ecef;
        }

        .btn-cancel {
            flex: 1;
            padding: 12px;
            border: 2px solid #e9ecef;
            background: white;
            color: #666;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-cancel:hover {
            background: #f8f9fa;
            border-color: #dee2e6;
        }

        .btn-confirm {
            flex: 1;
            padding: 12px;
            border: none;
            background: #2d5016;
            color: white;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
        }

        .btn-confirm:hover {
            background: #1a3009;
        }

        .selection-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1001;
        }

        .selection-content {
            background: white;
            border-radius: 12px;
            width: 90%;
            max-width: 400px;
            padding: 0;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            animation: slideUp 0.3s ease;
            position: relative;
            border-left: 4px solid #2d5016;
        }

        .selection-icon {
            position: absolute;
            top: -15px;
            left: 20px;
            width: 40px;
            height: 40px;
            background: #2d5016;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 18px;
            box-shadow: 0 2px 10px rgba(45, 80, 22, 0.3);
        }

        .selection-message {
            padding: 30px 20px 20px 20px;
            text-align: center;
            max-height: 70vh;
            overflow-y: auto;
        }

        .selection-message h4 {
            margin: 0 0 10px 0;
            color: #2d5016;
            font-size: 18px;
            font-weight: 600;
        }

        .selection-message p {
            margin: 0 0 20px 0;
            color: #666;
            font-size: 14px;
            line-height: 1.5;
        }

        .selection-section {
            margin-bottom: 20px;
            text-align: left;
        }

        .selection-label {
            display: block;
            font-weight: 600;
            color: #333;
            margin-bottom: 10px;
            font-size: 14px;
        }

        .selection-color-options {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 15px;
        }

        .selection-color-option {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 6px 10px;
            border: 2px solid #e9ecef;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
            background: white;
        }

        .selection-color-option:hover {
            border-color: #2d5016;
            transform: translateY(-1px);
        }

        .selection-color-option.selected {
            border-color: #2d5016;
            background: #f0f8e6;
        }

        .selection-color-swatch {
            width: 16px;
            height: 16px;
            border-radius: 50%;
            border: 1px solid #ddd;
        }

        .selection-color-name {
            font-size: 12px;
            color: #333;
        }

        .selection-size-options {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 15px;
        }

        .selection-size-option {
            padding: 8px 12px;
            border: 2px solid #e9ecef;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
            background: white;
            font-size: 12px;
            font-weight: 500;
            color: #333;
            min-width: 40px;
            text-align: center;
        }

        .selection-size-option:hover {
            border-color: #2d5016;
            transform: translateY(-1px);
        }

        .selection-size-option.selected {
            border-color: #2d5016;
            background: #2d5016;
            color: white;
        }

        .selection-continue-btn {
            width: 100%;
            padding: 12px;
            border: none;
            background: #2d5016;
            color: white;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
            margin-top: 15px;
        }

        .selection-continue-btn:hover {
            background: #1a3009;
        }

        .selection-continue-btn:disabled {
            background: #6c757d;
            cursor: not-allowed;
        }

        .selection-close-btn {
            position: absolute;
            top: 15px;
            right: 15px;
            background: none;
            border: none;
            font-size: 16px;
            color: #999;
            cursor: pointer;
            padding: 5px;
            border-radius: 4px;
            transition: all 0.3s;
        }

        .selection-close-btn:hover {
            background: #f0f0f0;
            color: #666;
        }

        .reviews-list {
            margin-top: 30px;
        }

        .review-item {
            background: white;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #e9ecef;
            margin-bottom: 15px;
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
            font-size: 12px;
            color: #999;
        }

        .review-rating {
            color: #ffc107;
            margin-bottom: 10px;
        }

        .review-comment {
            color: #666;
            line-height: 1.6;
        }

        .review-media {
            margin-top: 15px;
            border-radius: 8px;
            overflow: hidden;
            max-width: 100%;
        }

        .review-media img {
            max-width: 100%;
            max-height: 150px;
            object-fit: cover;
            border-radius: 8px;
        }

        .review-media video {
            max-width: 100%;
            max-height: 200px;
            border-radius: 8px;
        }

        .no-reviews {
            text-align: center;
            padding: 40px;
            color: #999;
        }

        /* Footer Features */
        .footer-features {
            display: flex;
            justify-content: center;
            gap: 60px;
            padding: 40px 0;
            background: white;
            border-bottom: 1px solid #e9ecef;
            margin-bottom: 50px;
        }

        .footer-feature {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .footer-feature-icon {
            width: 60px;
            height: 60px;
            background: #2d5016;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }

        .footer-feature-text h4 {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }

        .footer-feature-text p {
            font-size: 13px;
            color: #666;
        }
    </style>
</head>
<body>
    <?php include '_header.php'; ?>

         <!-- Shop Hero Section -->
    <section class="shop-hero">
        <div class="container">
            <h1>Shop</h1>
            <p>Discover our amazing collections</p>
        </div>
    </section>

    <div class="product-page">

        <!-- Product Container -->
        <div class="product-container">
            <div class="product-images">
                <div class="main-image">
                    <div class="main-image-container">
                        <?php if (!empty($product_images)): ?>
                            <?php foreach ($product_images as $index => $image): ?>
                                <img src="<?php echo !empty($image) ? '../' . htmlspecialchars($image) : 'https://images.unsplash.com/photo-1586023492125-27b2c045efd7?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=400&q=80'; ?>" 
                                     alt="<?php echo htmlspecialchars($product['name']); ?> - Image <?php echo $index + 1; ?>"
                                     class="main-image-item <?php echo $index === 0 ? 'active' : ''; ?>"
                                     data-image-index="<?php echo $index; ?>">
                            <?php endforeach; ?>
                        <?php else: ?>
                            <img src="https://images.unsplash.com/photo-1586023492125-27b2c045efd7?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=400&q=80'" 
                                 alt="<?php echo htmlspecialchars($product['name']); ?>"
                                 class="main-image-item active">
                        <?php endif; ?>
                    </div>
                    
                    <!-- Navigation arrows -->
                    <?php if (count($product_images) > 1): ?>
                        <button class="image-nav prev" onclick="changeImage(-1)">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <button class="image-nav next" onclick="changeImage(1)">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    <?php endif; ?>
                </div>
                
                <!-- Thumbnail gallery -->
                <?php if (!empty($product_images) && count($product_images) > 1): ?>
                    <div class="image-thumbnails">
                        <?php foreach ($product_images as $index => $image): ?>
                            <div class="thumbnail <?php echo $index === 0 ? 'active' : ''; ?>" 
                                 onclick="selectImage(<?php echo $index; ?>)"
                                 data-thumb-index="<?php echo $index; ?>">
                                <img src="<?php echo !empty($image) ? '../' . htmlspecialchars($image) : 'https://images.unsplash.com/photo-1586023492125-27b2c045efd7?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=400&q=80'; ?>" 
                                     alt="Thumbnail <?php echo $index + 1; ?>">
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="product-info">
                <div class="product-badge" style="<?php echo ($product['stock_quantity'] ?? 0) <= 0 ? 'background: #dc3545;' : ''; ?>">
                    <?php echo ($product['stock_quantity'] ?? 0) <= 0 ? 'Out of Stock' : 'In Stock'; ?>
                </div>
                
                <h1 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h1>
                
                <div class="product-rating">
                    <div class="stars">
                        <?php 
                        $rating = $product['rating'] ?? 0;
                        for ($i = 1; $i <= 5; $i++) {
                            if ($i <= $rating) {
                                echo '<i class="fas fa-star star"></i>';
                            } else {
                                echo '<i class="fas fa-star star empty"></i>';
                            }
                        }
                        ?>
                    </div>
                    <span class="rating-text"><?php echo number_format($rating, 1); ?> (<?php echo $total_reviews; ?> reviews)</span>
                </div>
                
                <!-- Rating Breakdown -->
                <div class="rating-breakdown" style="margin-bottom: 15px; background: #f8f9fa; padding: 15px; border-radius: 8px;">
                    <div style="font-weight: 600; margin-bottom: 10px; color: #333;">Rating Breakdown</div>
                    <?php for ($i = 5; $i >= 1; $i--): ?>
                        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 5px;">
                            <span style="width: 60px; font-size: 14px;"><?php echo $i; ?> stars</span>
                            <div style="flex: 1; background: #e9ecef; height: 8px; border-radius: 4px; overflow: hidden;">
                                <div style="background: #ffc107; height: 100%; width: <?php echo $total_reviews > 0 ? ($rating_counts[$i] / $total_reviews) * 100 : 0; ?>%; transition: width 0.3s ease;"></div>
                            </div>
                            <span style="width: 40px; text-align: right; font-size: 14px; color: #666;"><?php echo $rating_counts[$i]; ?></span>
                        </div>
                    <?php endfor; ?>
                </div>

                <div class="product-price">
                    <span class="current-price">₱<?php echo number_format($product['price'], 2); ?></span>
                    <?php if ($product['original_price'] && $product['original_price'] > $product['price']): ?>
                        <span class="original-price">₱<?php echo number_format($product['original_price'], 2); ?></span>
                        <span class="discount-badge">-<?php echo round((($product['original_price'] - $product['price']) / $product['original_price']) * 100); ?>%</span>
                    <?php endif; ?>
                </div>

                <div class="product-meta">
                    <div class="meta-item">
                        <div class="meta-icon"><i class="fas fa-tag"></i></div>
                        <div class="meta-label">Category</div>
                        <div class="meta-value"><?php echo htmlspecialchars(ucfirst($product['category'])); ?></div>
                    </div>
                    <div class="meta-item">
                        <div class="meta-icon"><i class="fas fa-box"></i></div>
                        <div class="meta-label">Stock</div>
                        <div class="meta-value"><?php echo $product['stock_quantity'] ?? 0; ?> units</div>
                    </div>
                    <div class="meta-item">
                        <div class="meta-icon"><i class="fas fa-truck"></i></div>
                        <div class="meta-label">Shipping</div>
                        <div class="meta-value">Free delivery</div>
                    </div>
                </div>

                <div class="product-description">
                    <div class="description-title">Description</div>
                    <div class="description-text">
                        <?php echo nl2br(htmlspecialchars($product['description'] ?? 'No description available for this product.')); ?>
                    </div>
                </div>

                <!-- Color Selection -->
                <?php if (!empty($product_colors)): ?>
                    <div class="product-options">
                        <div class="option-title">Color</div>
                        <div class="color-options">
                            <?php foreach ($product_colors as $color): ?>
                                <div class="color-option" data-color="<?php echo htmlspecialchars($color); ?>" onclick="selectColor(this)">
                                    <div class="color-swatch" style="background-color: <?php echo getColorHex($color); ?>;"></div>
                                    <span class="color-name"><?php echo htmlspecialchars($color); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Size Selection -->
                <?php if (!empty($product_sizes)): ?>
                    <div class="product-options">
                        <div class="option-title">Size</div>
                        <div class="size-options">
                            <?php foreach ($product_sizes as $size): ?>
                                <div class="size-option" data-size="<?php echo htmlspecialchars($size); ?>" onclick="selectSize(this)">
                                    <?php echo htmlspecialchars($size); ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($product['first_name'] || $product['last_name']): ?>
                    <div class="seller-info">
                        <div class="seller-details">
                            <div class="seller-avatar">
                                <?php echo strtoupper(substr($product['first_name'] ?? 'S', 0, 1) . substr($product['last_name'] ?? 'S', 0, 1)); ?>
                            </div>
                            <div>
                                <div class="seller-name">Sold by: <?php echo htmlspecialchars($product['first_name'] . ' ' . $product['last_name']); ?></div>
                                <div class="seller-status">
                                    <i class="fas fa-check-circle"></i> Verified Seller
                                </div>
                            </div>
                        </div>
                        <div style="display: flex; gap: 10px;">
                            <a href="message.php?user_id=<?php echo $product['seller_id']; ?>" class="btn-add-cart" style="width: auto; padding: 5px 20px; text-decoration: none; display: inline-flex; align-items: center; gap: 10px;">
                                <i class="fas fa-envelope"></i> Contact Seller
                            </a>
                            <a href="seller_profile.php?seller_id=<?php echo $product['seller_id']; ?>" class="btn-add-cart" style="width: auto; padding: 5px 20px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px;">
                                <i class="fas fa-store"></i> Visit Store
                            </a>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="action-buttons">
                    <div class="quantity-section">
                        <span class="quantity-label">Quantity:</span>
                        <div class="quantity-selector">
                            <button class="quantity-btn" onclick="changeQuantity(-1)">-</button>
                            <input type="number" id="quantity" class="quantity-input" value="1" min="1" max="<?php echo $product['stock_quantity'] ?? 1; ?>">
                            <button class="quantity-btn" onclick="changeQuantity(1)">+</button>
                        </div>
                    </div>
                </div>

                <div class="action-buttons">
                    <?php if ($is_logged_in): ?>
                        <button class="btn-wishlist <?php echo $is_in_wishlist ? 'in-wishlist' : ''; ?>" onclick="toggleWishlist(<?php echo $product_id; ?>, this)" title="<?php echo $is_in_wishlist ? 'Remove from Wishlist' : 'Add to Wishlist'; ?>">
                            <i class="<?php echo $is_in_wishlist ? 'fas' : 'far'; ?> fa-heart"></i>
                        </button>
                    <?php else: ?>
                        <a href="login.php" class="btn-wishlist" title="Add to Wishlist">
                            <i class="far fa-heart"></i>
                        </a>
                    <?php endif; ?>
                    <?php if (($product['stock_quantity'] ?? 0) > 0): ?>
                        <button class="btn-add-cart" onclick="addToCart(<?php echo $product_id; ?>, event)">
                            <i class="fas fa-shopping-cart"></i> Add to Cart
                        </button>
                        <button class="btn-buy-now" onclick="buyNow(<?php echo $product_id; ?>)">
                            Buy Now
                        </button>
                    <?php else: ?>
                        <button class="btn-add-cart" disabled style="opacity: 0.5; cursor: not-allowed;">
                            <i class="fas fa-shopping-cart"></i> Out of Stock
                        </button>
                        <button class="btn-buy-now" disabled style="opacity: 0.5; cursor: not-allowed;">
                            Out of Stock
                        </button>
                    <?php endif; ?>
                </div>

                <!-- Selection Message Container -->
                <div id="errorContainer" class="selection-container" style="display: none;">
                    <div class="selection-content">
                        <div class="selection-icon">
                            <i class="fas fa-palette"></i>
                        </div>
                        <div class="selection-message">
                            <h4>Select Your Options</h4>
                            <p id="errorText">Please select your preferred color and size to continue</p>
                            
                            <!-- Color Selection in Selection Dialog -->
                            <?php if (!empty($product_colors)): ?>
                                <div class="selection-section">
                                    <label class="selection-label">Select Color:</label>
                                    <div class="selection-color-options">
                                        <?php foreach ($product_colors as $color): ?>
                                            <div class="selection-color-option" data-color="<?php echo htmlspecialchars($color); ?>" onclick="selectColorFromError(this)">
                                                <div class="selection-color-swatch" style="background-color: <?php echo getColorHex($color); ?>;"></div>
                                                <span class="selection-color-name"><?php echo htmlspecialchars($color); ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Size Selection in Selection Dialog -->
                            <?php if (!empty($product_sizes)): ?>
                                <div class="selection-section">
                                    <label class="selection-label">Select Size:</label>
                                    <div class="selection-size-options">
                                        <?php foreach ($product_sizes as $size): ?>
                                            <div class="selection-size-option" data-size="<?php echo htmlspecialchars($size); ?>" onclick="selectSizeFromError(this)">
                                                <?php echo htmlspecialchars($size); ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <button class="selection-continue-btn" onclick="continueAfterSelection()">
                                Continue <i class="fas fa-arrow-right"></i>
                            </button>
                        </div>
                        <button class="selection-close-btn" onclick="hideError()">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>

                <!-- Confirmation Dialog -->
                <div id="confirmationDialog" class="confirmation-dialog" style="display: none;">
                    <div class="confirmation-content">
                        <div class="confirmation-header">
                            <h3>Confirm Your Selection</h3>
                            <button class="close-btn" onclick="closeConfirmationDialog()">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <div class="confirmation-body">
                            <div class="product-summary">
                                <h4><?php echo htmlspecialchars($product['name']); ?></h4>
                                <div class="selected-options">
                                    <?php if (!empty($product_colors)): ?>
                                        <div class="selected-option">
                                            <span class="option-label">Color:</span>
                                            <span id="selectedColorDisplay" class="option-value">-</span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($product_sizes)): ?>
                                        <div class="selected-option">
                                            <span class="option-label">Size:</span>
                                            <span id="selectedSizeDisplay" class="option-value">-</span>
                                        </div>
                                    <?php endif; ?>
                                    <div class="selected-option">
                                        <span class="option-label">Quantity:</span>
                                        <span id="selectedQuantityDisplay" class="option-value">1</span>
                                    </div>
                                </div>
                                <div class="price-summary">
                                    <div class="price-row">
                                        <span>Price:</span>
                                        <span>₱<?php echo number_format($product['price'], 2); ?></span>
                                    </div>
                                    <div class="price-row total">
                                        <span>Total:</span>
                                        <span id="totalPriceDisplay">₱<?php echo number_format($product['price'], 2); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="confirmation-footer">
                            <button class="btn-cancel" onclick="closeConfirmationDialog()">Cancel</button>
                            <button id="confirmActionBtn" class="btn-confirm">Confirm</button>
                        </div>
                    </div>
                </div>

                <div class="product-features">
                    <div class="feature">
                        <div class="feature-icon"><i class="fas fa-shield-alt"></i></div>
                        <div class="feature-text">1 Year Warranty</div>
                    </div>
                    <div class="feature">
                        <div class="feature-icon"><i class="fas fa-undo"></i></div>
                        <div class="feature-text">30-Day Returns</div>
                    </div>
                    <div class="feature">
                        <div class="feature-icon"><i class="fas fa-headset"></i></div>
                        <div class="feature-text">24/7 Support</div>
                    </div>
                    <div class="feature">
                        <div class="feature-icon"><i class="fas fa-lock"></i></div>
                        <div class="feature-text">Secure Payment</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Product Tabs -->
        <div class="product-tabs-section">
            <div class="tabs-header">
                <button class="tab-btn active" data-tab="description">Description</button>
                <button class="tab-btn" data-tab="additional">Additional Information</button>
                <button class="tab-btn" data-tab="review">Review</button>
            </div>
            <div class="tabs-content">
                <div class="tab-content active" id="description">
                    <div class="tab-description">
                        <?php echo nl2br(htmlspecialchars($product['description'] ?? 'No description available for this product.')); ?>
                    </div>
                </div>
                <div class="tab-content" id="additional">
                    <div class="additional-info-table">
                        <table>
                            <tr>
                                <td>Product Type</td>
                                <td><?php echo htmlspecialchars(ucfirst($product['category'])); ?></td>
                            </tr>
                            <tr>
                                <td>Origin</td>
                                <td>Local</td>
                            </tr>
                            <tr>
                                <td>Color</td>
                                <td>As shown</td>
                            </tr>
                            <tr>
                                <td>Guarantee</td>
                                <td>30 Days</td>
                            </tr>
                            <tr>
                                <td>Barcode</td>
                                <td><?php echo 'PRD' . str_pad($product['id'], 6, '0', STR_PAD_LEFT); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
                <div class="tab-content" id="review">
                    <div class="review-section">
                        <?php if ($is_logged_in): ?>
                            <?php if ($user_has_reviewed): ?>
                                <div class="review-form">
                                    <h3>Your Review</h3>
                                    <div class="review-item" style="background: #f8f9fa; border: none;">
                                        <div class="review-header">
                                            <div class="reviewer-name">
                                                <?php echo htmlspecialchars($user_review['first_name'] . ' ' . $user_review['last_name']); ?>
                                            </div>
                                            <div class="review-date">
                                                <?php echo date('F j, Y', strtotime($user_review['created_at'])); ?>
                                            </div>
                                        </div>
                                        <div class="review-rating">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <?php if ($i <= $user_review['rating']): ?>
                                                    <i class="fas fa-star"></i>
                                                <?php else: ?>
                                                    <i class="far fa-star"></i>
                                                <?php endif; ?>
                                            <?php endfor; ?>
                                        </div>
                                        <div class="review-comment">
                                            <?php echo nl2br(htmlspecialchars($user_review['comment'])); ?>
                                        </div>
                                        <?php if (!empty($review_media[$user_review['id']])): ?>
                                            <div class="review-media">
                                                <?php foreach ($review_media[$user_review['id']] as $media): ?>
                                                    <?php if ($media['media_type'] == 'video'): ?>
                                                        <video controls style="max-width: 100%; max-height: 300px; border-radius: 8px; margin-bottom: 10px;">
                                                            <source src="<?php echo '../' . htmlspecialchars($media['media_url']); ?>" type="video/mp4">
                                                            Your browser does not support the video tag.
                                                        </video>
                                                    <?php else: ?>
                                                        <img src="<?php echo '../' . htmlspecialchars($media['media_url']); ?>" alt="Review image" style="max-width: 100%; max-height: 300px; border-radius: 8px; margin-bottom: 10px;">
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <p style="color: #666; margin-top: 15px; font-size: 14px;">You have already reviewed this product.</p>
                                </div>
                            <?php else: ?>
                                <div class="no-reviews">
                                    <p>You can write a review for this product after your order is delivered.</p>
                                    <p style="margin-top: 10px;"><a href="settings.php#orders" style="color: #2d5016; font-weight: 600;">View your orders</a> to write a review.</p>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="no-reviews">
                                <p>Please <a href="login.php" style="color: #2d5016; font-weight: 600;">login</a> to write a review.</p>
                            </div>
                        <?php endif; ?>

                        <div class="reviews-list">
                            <h3 style="font-size: 18px; color: #333; margin-bottom: 20px;">Customer Reviews</h3>
                            <?php if ($reviews_result->num_rows > 0): ?>
                                <?php while ($review = $reviews_result->fetch_assoc()): ?>
                                    <div class="review-item">
                                        <div class="review-header">
                                            <div class="reviewer-name">
                                                <?php echo htmlspecialchars($review['first_name'] . ' ' . $review['last_name']); ?>
                                            </div>
                                            <div class="review-date">
                                                <?php echo date('F j, Y', strtotime($review['created_at'])); ?>
                                            </div>
                                        </div>
                                        <div class="review-rating">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <?php if ($i <= $review['rating']): ?>
                                                    <i class="fas fa-star"></i>
                                                <?php else: ?>
                                                    <i class="far fa-star"></i>
                                                <?php endif; ?>
                                            <?php endfor; ?>
                                        </div>
                                        <div class="review-comment">
                                            <?php echo nl2br(htmlspecialchars($review['comment'])); ?>
                                        </div>
                                        <?php if (!empty($review_media[$review['id']])): ?>
                                            <div class="review-media">
                                                <?php foreach ($review_media[$review['id']] as $media): ?>
                                                    <?php if ($media['media_type'] == 'video'): ?>
                                                        <video controls style="max-width: 100%; max-height: 300px; border-radius: 8px; margin-bottom: 10px;">
                                                            <source src="<?php echo '../' . htmlspecialchars($media['media_url']); ?>" type="video/mp4">
                                                            Your browser does not support the video tag.
                                                        </video>
                                                    <?php else: ?>
                                                        <img src="<?php echo '../' . htmlspecialchars($media['media_url']); ?>" alt="Review image" style="max-width: 100%; max-height: 300px; border-radius: 8px; margin-bottom: 10px;">
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="no-reviews">
                                    <p>No reviews yet. Be the first to review this product!</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer Features -->
        <div class="footer-features">
            <div class="footer-feature">
                <div class="footer-feature-icon"><i class="fas fa-truck"></i></div>
                <div class="footer-feature-text">
                    <h4>Free Shipping</h4>
                    <p>On orders over ₱500</p>
                </div>
            </div>
            <div class="footer-feature">
                <div class="footer-feature-icon"><i class="fas fa-credit-card"></i></div>
                <div class="footer-feature-text">
                    <h4>Flexible Payment</h4>
                    <p>Pay with multiple methods</p>
                </div>
            </div>
            <div class="footer-feature">
                <div class="footer-feature-icon"><i class="fas fa-headset"></i></div>
                <div class="footer-feature-text">
                    <h4>24x7 Support</h4>
                    <p>Dedicated support team</p>
                </div>
            </div>
        </div>

        
        <?php if ($related_result->num_rows > 0): ?>
        <div class="related-products">
            <div class="section-header">
                <h2 class="section-title">Explore Related Products</h2>
                <p class="section-subtitle">Discover similar products that might interest you</p>
            </div>
            <div class="related-grid">
                <?php while ($related = $related_result->fetch_assoc()): ?>
                    <div class="related-card" onclick="window.location.href='product_details.php?id=<?php echo $related['id']; ?>'">
                        <div class="related-image">
                            <img src="<?php echo !empty($related['image_url']) ? '../' . htmlspecialchars($related['image_url']) : 'https://images.unsplash.com/photo-1555041469-a586c61ea9bc?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=400&q=80'; ?>" alt="<?php echo htmlspecialchars($related['name']); ?>">
                            <?php if ($related['original_price'] && $related['original_price'] > $related['price']): ?>
                                <span class="related-discount">-<?php echo round((($related['original_price'] - $related['price']) / $related['original_price']) * 100); ?>%</span>
                            <?php endif; ?>
                        </div>
                        <div class="related-info">
                            <div class="related-category"><?php echo htmlspecialchars(ucfirst($product['category'])); ?></div>
                            <div class="related-name"><?php echo htmlspecialchars($related['name']); ?></div>
                            <div class="related-price">
                                <span class="related-current">₱<?php echo number_format($related['price'], 2); ?></span>
                                <?php if ($related['original_price']): ?>
                                    <span class="related-original">₱<?php echo number_format($related['original_price'], 2); ?></span>
                                <?php endif; ?>
                            </div>
                            <button class="add-to-cart-btn" onclick="event.stopPropagation(); addToCart(<?php echo $related['id']; ?>, event)">
                                <i class="fas fa-shopping-cart"></i> Add to Cart
                            </button>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
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

    <script>

    // =========================
    // USER DROPDOWN
    // =========================

    document.querySelector('.icon-btn').addEventListener('click', function() {
        document.querySelector('.user-dropdown').classList.toggle('active');
    });

    document.addEventListener('click', function(event) {

        const userMenu = document.querySelector('.user-menu');

        if (!userMenu.contains(event.target)) {
            document.querySelector('.user-dropdown').classList.remove('active');
        }
    });

    // =========================
    // GLOBAL VARIABLES
    // =========================

    let selectedColor = '';
    let selectedSize = '';
    let pendingAction = null;

    // =========================
    // RATING
    // =========================

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

    // =========================
    // WISHLIST
    // =========================

    function toggleWishlist(productId, button) {

        const formData = new FormData();

        formData.append('product_id', productId);

        fetch('wishlist_toggle.php', {
            method: 'POST',
            body: formData
        })

        .then(response => response.json())

        .then(data => {

            if (data.success) {

                const icon = button.querySelector('i');

                if (data.action === 'added') {

                    button.classList.add('in-wishlist');

                    icon.classList.remove('far');

                    icon.classList.add('fas');

                    button.title = 'Remove from Wishlist';

                } else {

                    button.classList.remove('in-wishlist');

                    icon.classList.remove('fas');

                    icon.classList.add('far');

                    button.title = 'Add to Wishlist';
                }
            }
        })

        .catch(error => {
            console.error('Wishlist error:', error);
        });
    }

    // =========================
    // QUANTITY
    // =========================

    function changeQuantity(change) {

        const input = document.getElementById('quantity');

        const maxValue = parseInt(input.max);

        let newValue = parseInt(input.value) + change;

        if (newValue < 1) newValue = 1;

        if (newValue > maxValue) newValue = maxValue;

        input.value = newValue;
    }

    // =========================
    // SELECT COLOR
    // =========================

    function selectColor(element) {

        document.querySelectorAll('.color-option').forEach(option => {
            option.classList.remove('selected');
        });

        element.classList.add('selected');

        selectedColor = element.dataset.color;

        syncErrorDialogSelections();
    }

    // =========================
    // SELECT SIZE
    // =========================

    function selectSize(element) {

        document.querySelectorAll('.size-option').forEach(option => {
            option.classList.remove('selected');
        });

        element.classList.add('selected');

        selectedSize = element.dataset.size;

        syncErrorDialogSelections();
    }

    // =========================
    // VALIDATION
    // =========================

    function validateRequiredOptions() {

        const hasColors = <?php echo !empty($product_colors) ? 'true' : 'false'; ?>;

        const hasSizes = <?php echo !empty($product_sizes) ? 'true' : 'false'; ?>;

        // =====================
        // NO ATTRIBUTES
        // =====================

        if (!hasColors && !hasSizes) {

            return true;
        }

        // =====================
        // BOTH REQUIRED
        // =====================

        if (hasColors && hasSizes) {

            if (!selectedColor && !selectedSize) {

                showError('Please select your preferred color and size to continue');

                return false;
            }

            if (!selectedColor) {

                showError('Please select your preferred color to continue');

                return false;
            }

            if (!selectedSize) {

                showError('Please select your preferred size to continue');

                return false;
            }

            return true;
        }

        // =====================
        // COLOR ONLY
        // =====================

        if (hasColors && !hasSizes) {

            if (!selectedColor) {

                showError('Please select your preferred color to continue');

                return false;
            }

            return true;
        }

        // =====================
        // SIZE ONLY
        // =====================

        if (!hasColors && hasSizes) {

            if (!selectedSize) {

                showError('Please select your preferred size to continue');

                return false;
            }

            return true;
        }

        return true;
    }

    // =========================
    // ERROR DIALOG
    // =========================

    function showError(message) {

        document.getElementById('errorText').textContent = message;

        document.getElementById('errorContainer').style.display = 'flex';

        syncErrorDialogSelections();

        updateErrorContinueButton();
    }

    function hideError() {

        document.getElementById('errorContainer').style.display = 'none';
    }

    // =========================
    // ERROR DIALOG SELECTIONS
    // =========================

    function syncErrorDialogSelections() {

        document.querySelectorAll('.selection-color-option').forEach(option => {

            option.classList.remove('selected');

            if (selectedColor === option.dataset.color) {
                option.classList.add('selected');
            }
        });

        document.querySelectorAll('.selection-size-option').forEach(option => {

            option.classList.remove('selected');

            if (selectedSize === option.dataset.size) {
                option.classList.add('selected');
            }
        });
    }

    function selectColorFromError(element) {

        document.querySelectorAll('.selection-color-option').forEach(option => {
            option.classList.remove('selected');
        });

        element.classList.add('selected');

        selectedColor = element.dataset.color;

        document.querySelectorAll('.color-option').forEach(option => {

            option.classList.remove('selected');

            if (selectedColor === option.dataset.color) {
                option.classList.add('selected');
            }
        });

        updateErrorContinueButton();
    }

    function selectSizeFromError(element) {

        document.querySelectorAll('.selection-size-option').forEach(option => {
            option.classList.remove('selected');
        });

        element.classList.add('selected');

        selectedSize = element.dataset.size;

        document.querySelectorAll('.size-option').forEach(option => {

            option.classList.remove('selected');

            if (selectedSize === option.dataset.size) {
                option.classList.add('selected');
            }
        });

        updateErrorContinueButton();
    }

    function updateErrorContinueButton() {

        const hasColors = <?php echo !empty($product_colors) ? 'true' : 'false'; ?>;

        const hasSizes = <?php echo !empty($product_sizes) ? 'true' : 'false'; ?>;

        const continueBtn = document.querySelector('.selection-continue-btn');

        let canContinue = true;

        if (hasColors && !selectedColor) {
            canContinue = false;
        }

        if (hasSizes && !selectedSize) {
            canContinue = false;
        }

        if (continueBtn) {
            continueBtn.disabled = !canContinue;
        }
    }

    // =========================
    // CONTINUE AFTER SELECTION
    // =========================

    function continueAfterSelection() {

        if (!validateRequiredOptions()) {
            return;
        }

        hideError();

        if (pendingAction) {

            const { action, productId, event } = pendingAction;

            pendingAction = null;

            if (action === 'addToCart') {

                showConfirmationDialog('addToCart', productId, event);

            } else if (action === 'buyNow') {

                showConfirmationDialog('buyNow', productId, event);
            }
        }
    }

    // =========================
    // ADD TO CART
    // =========================

    function addToCart(productId, event) {

        pendingAction = {
            action: 'addToCart',
            productId,
            event
        };

        if (!validateRequiredOptions()) {
            return;
        }

        pendingAction = null;

        showConfirmationDialog('addToCart', productId, event);
    }

    // =========================
    // BUY NOW
    // =========================

    function buyNow(productId) {

        pendingAction = {
            action: 'buyNow',
            productId,
            event: null
        };

        if (!validateRequiredOptions()) {
            return;
        }

        pendingAction = null;

        showConfirmationDialog('buyNow', productId, null);
    }

    // =========================
    // CONFIRMATION DIALOG
    // =========================

    function showConfirmationDialog(action, productId, event) {

        const quantity = document.getElementById('quantity').value;

        const price = <?php echo $product['price']; ?>;

        const totalPrice = price * quantity;

        const colorDisplay = document.getElementById('selectedColorDisplay');

        const sizeDisplay = document.getElementById('selectedSizeDisplay');

        if (colorDisplay) {
            colorDisplay.textContent = selectedColor || '-';
        }

        if (sizeDisplay) {
            sizeDisplay.textContent = selectedSize || '-';
        }

        document.getElementById('selectedQuantityDisplay').textContent = quantity;

        document.getElementById('totalPriceDisplay').textContent =
            '₱' + totalPrice.toFixed(2);

        const confirmBtn = document.getElementById('confirmActionBtn');

        confirmBtn.textContent =
            action === 'addToCart'
            ? 'Add to Cart'
            : 'Buy Now';

        confirmBtn.onclick = function() {

            if (action === 'addToCart') {

                executeAddToCart(productId, event);

            } else {

                executeBuyNow(productId);
            }

            closeConfirmationDialog();
        };

        document.getElementById('confirmationDialog').style.display = 'flex';
    }

    function closeConfirmationDialog() {

        document.getElementById('confirmationDialog').style.display = 'none';
    }

    // =========================
    // EXECUTE ADD TO CART
    // =========================

    function executeAddToCart(productId, event) {

        const quantity = document.getElementById('quantity').value;

        const formData = new FormData();

        formData.append('product_id', productId);

        formData.append('quantity', quantity);

        formData.append('color', selectedColor || '');

        formData.append('size', selectedSize || '');

        fetch('cart_add.php', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
        })

        .then(response => {

            if (response.status === 401) {

                window.location.href = 'login.php';

                return;
            }

            return response.json();
        })

        .then(data => {

            if (data.success) {

                let button;

                if (event && event.target) {

                    button = event.target.closest('.btn-add-cart');

                    if (button) {

                        const originalText = button.innerHTML;

                        button.innerHTML =
                            '<i class="fas fa-check"></i> Added!';

                        button.style.background = '#28a745';

                        setTimeout(() => {

                            button.innerHTML = originalText;

                            button.style.background = '';

                        }, 2000);
                    }
                }

                const badge = document.querySelector('.cart-badge');

                if (badge && data.cart_count !== undefined) {
                    badge.textContent = data.cart_count;
                }

            } else {

                alert(data.error || 'Failed to add to cart');
            }
        })

        .catch(error => {

            console.error(error);

            alert('Error adding to cart');
        });
    }

    // =========================
    // EXECUTE BUY NOW
    // =========================

    function executeBuyNow(productId) {

        const quantity = document.getElementById('quantity').value;

        let url =
            'checkout.php?product_id=' + productId +
            '&quantity=' + quantity;

        if (selectedColor) {
            url += '&color=' + encodeURIComponent(selectedColor);
        }

        if (selectedSize) {
            url += '&size=' + encodeURIComponent(selectedSize);
        }

        window.location.href = url;
    }

</script>
</body>
</html>
