<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$product_id = $_POST['product_id'] ?? 0;
$seller_id = $_POST['seller_id'] ?? 0;
$rating = $_POST['rating'] ?? 5;
$comment = $_POST['comment'] ?? '';

if (!$product_id) {
    header("Location: shop.php");
    exit();
}

// If seller_id is 0, set it to NULL
if (!$seller_id) {
    $seller_id = NULL;
}

// Check if user already reviewed this product
$check_stmt = $conn->prepare("SELECT id FROM reviews WHERE user_id = ? AND product_id = ?");
$check_stmt->bind_param("ii", $user_id, $product_id);
$check_stmt->execute();
$existing = $check_stmt->get_result()->fetch_assoc();
$check_stmt->close();

if ($existing) {
    // User already reviewed, redirect back
    header("Location: product_details.php?id=" . $product_id);
    exit();
}

// Handle multiple file uploads
$media_files = [];
$upload_dir = '../uploads/reviews/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

if (isset($_FILES['review_media']) && !empty($_FILES['review_media']['name'][0])) {
    $files = $_FILES['review_media'];
    $file_count = count($files['name']);
    
    // Limit to 5 files
    if ($file_count > 5) {
        die("Maximum 5 files allowed.");
    }
    
    // Allowed file types
    $allowed_image_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $allowed_video_ext = ['mp4', 'webm', 'mov'];
    $allowed_ext = array_merge($allowed_image_ext, $allowed_video_ext);
    $max_size = 10 * 1024 * 1024; // 10MB
    
    for ($i = 0; $i < $file_count; $i++) {
        if ($files['error'][$i] == 0) {
            $file_name = $files['name'][$i];
            $file_tmp = $files['tmp_name'][$i];
            $file_size = $files['size'][$i];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            
            // Validate file type
            if (!in_array($file_ext, $allowed_ext)) {
                die("Invalid file type: " . $file_name . ". Only images (JPG, PNG, GIF, WebP) and videos (MP4, WebM, MOV) are allowed.");
            }
            
            // Validate file size
            if ($file_size > $max_size) {
                die("File size too large: " . $file_name . ". Maximum size is 10MB.");
            }
            
            // Determine media type
            $media_type = in_array($file_ext, $allowed_video_ext) ? 'video' : 'image';
            
            // Generate unique filename
            $new_filename = 'review_' . $user_id . '_' . $product_id . '_' . time() . '_' . $i . '.' . $file_ext;
            $file_path = $upload_dir . $new_filename;
            
            // Move uploaded file
            if (!move_uploaded_file($file_tmp, $file_path)) {
                die("Failed to upload file: " . $file_name);
            }
            
            $media_files[] = [
                'url' => 'uploads/reviews/' . $new_filename,
                'type' => $media_type
            ];
        }
    }
}

// Create review_media table if it doesn't exist
$conn->query("CREATE TABLE IF NOT EXISTS review_media (
    id INT AUTO_INCREMENT PRIMARY KEY,
    review_id INT NOT NULL,
    media_url VARCHAR(255) NOT NULL,
    media_type ENUM('image', 'video') NOT NULL DEFAULT 'image',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (review_id) REFERENCES reviews(id) ON DELETE CASCADE
)");

// Insert new review
if ($seller_id) {
    $stmt = $conn->prepare("INSERT INTO reviews (product_id, user_id, seller_id, rating, comment) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("iiiis", $product_id, $user_id, $seller_id, $rating, $comment);
} else {
    $stmt = $conn->prepare("INSERT INTO reviews (product_id, user_id, rating, comment) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiis", $product_id, $user_id, $rating, $comment);
}

if (!$stmt->execute()) {
    die("Error inserting review: " . $stmt->error);
}
$review_id = $conn->insert_id;
$stmt->close();

// Insert media files into review_media table
if (!empty($media_files)) {
    $media_stmt = $conn->prepare("INSERT INTO review_media (review_id, media_url, media_type) VALUES (?, ?, ?)");
    foreach ($media_files as $media) {
        $media_stmt->bind_param("iss", $review_id, $media['url'], $media['type']);
        if (!$media_stmt->execute()) {
            die("Error inserting media: " . $media_stmt->error);
        }
    }
    $media_stmt->close();
}

header("Location: product_details.php?id=" . $product_id);
exit();
?>
