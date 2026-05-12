<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once 'db.php';
$user_id = $_SESSION['user_id'];

// Check if user is a seller
$seller_check = $conn->query("SHOW COLUMNS FROM users LIKE 'is_seller'");
if ($seller_check->num_rows > 0) {
    $stmt = $conn->prepare("SELECT is_seller FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    
    if ($user['is_seller'] != 1) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit();
    }
}

$action = $_POST['action'] ?? '';
$product_id = $_POST['product_id'] ?? 0;
$image_id = $_POST['image_id'] ?? 0;

if ($action === 'set_main') {
    // Verify product belongs to seller
    $product_check = $conn->prepare("SELECT id, image_url FROM products WHERE id = ? AND seller_id = ?");
    $product_check->bind_param("ii", $product_id, $user_id);
    $product_check->execute();
    $product_result = $product_check->get_result();
    
    if ($product_result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Product not found']);
        exit();
    }
    
    $product = $product_result->fetch_assoc();
    $product_check->close();
    
    // Get the image to set as main
    $image_check = $conn->prepare("SELECT image_url FROM product_images WHERE id = ? AND product_id = ?");
    $image_check->bind_param("ii", $image_id, $product_id);
    $image_check->execute();
    $image_result = $image_check->get_result();
    
    if ($image_result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Image not found']);
        exit();
    }
    
    $new_image = $image_result->fetch_assoc();
    $image_check->close();
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Move old main image to additional images
        if (!empty($product['image_url'])) {
            $insert_old = $conn->prepare("INSERT INTO product_images (product_id, image_url) VALUES (?, ?)");
            $insert_old->bind_param("is", $product_id, $product['image_url']);
            $insert_old->execute();
            $insert_old->close();
        }
        
        // Set new main image
        $update_product = $conn->prepare("UPDATE products SET image_url = ? WHERE id = ?");
        $update_product->bind_param("si", $new_image['image_url'], $product_id);
        $update_product->execute();
        $update_product->close();
        
        // Delete the image from additional images table
        $delete_image = $conn->prepare("DELETE FROM product_images WHERE id = ?");
        $delete_image->bind_param("i", $image_id);
        $delete_image->execute();
        $delete_image->close();
        
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Main image updated successfully']);
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Failed to update main image: ' . $e->getMessage()]);
    }
    
} elseif ($action === 'delete') {
    // Verify product belongs to seller
    $product_check = $conn->prepare("SELECT id FROM products WHERE id = ? AND seller_id = ?");
    $product_check->bind_param("ii", $product_id, $user_id);
    $product_check->execute();
    $product_result = $product_check->get_result();
    
    if ($product_result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Product not found']);
        exit();
    }
    $product_check->close();
    
    // Get image details
    $image_check = $conn->prepare("SELECT image_url FROM product_images WHERE id = ? AND product_id = ?");
    $image_check->bind_param("ii", $image_id, $product_id);
    $image_check->execute();
    $image_result = $image_check->get_result();
    
    if ($image_result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Image not found']);
        exit();
    }
    
    $image = $image_result->fetch_assoc();
    $image_check->close();
    
    // Delete image file from server
    if (!empty($image['image_url'])) {
        $image_path = '../' . $image['image_url'];
        if (file_exists($image_path)) {
            unlink($image_path);
        }
    }
    
    // Delete from database
    $delete_image = $conn->prepare("DELETE FROM product_images WHERE id = ?");
    $delete_image->bind_param("i", $image_id);
    
    if ($delete_image->execute()) {
        echo json_encode(['success' => true, 'message' => 'Image deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete image']);
    }
    $delete_image->close();
    
} elseif ($action === 'upload_additional') {
    // Handle additional image uploads
    if (isset($_FILES['additional_images']) && !empty($_FILES['additional_images']['name'][0])) {
        $product_check = $conn->prepare("SELECT id FROM products WHERE id = ? AND seller_id = ?");
        $product_check->bind_param("ii", $product_id, $user_id);
        $product_check->execute();
        $product_result = $product_check->get_result();
        
        if ($product_result->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Product not found']);
            exit();
        }
        $product_check->close();
        
        $upload_dir = '../uploads/products/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $uploaded_images = [];
        
        foreach ($_FILES['additional_images']['name'] as $key => $name) {
            if ($_FILES['additional_images']['error'][$key] == 0) {
                $file_name = time() . '_' . $key . '_' . basename($_FILES['additional_images']['name'][$key]);
                $target_file = $upload_dir . $file_name;
                
                if (move_uploaded_file($_FILES['additional_images']['tmp_name'][$key], $target_file)) {
                    $image_path = 'uploads/products/' . $file_name;
                    
                    // Insert into database
                    $insert_stmt = $conn->prepare("INSERT INTO product_images (product_id, image_url) VALUES (?, ?)");
                    $insert_stmt->bind_param("is", $product_id, $image_path);
                    
                    if ($insert_stmt->execute()) {
                        $uploaded_images[] = $image_path;
                    }
                    $insert_stmt->close();
                }
            }
        }
        
        if (!empty($uploaded_images)) {
            echo json_encode(['success' => true, 'message' => count($uploaded_images) . ' images uploaded successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'No images were uploaded']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'No images selected']);
    }
    
} elseif ($action === 'reorder') {
    // Verify product belongs to seller
    $product_check = $conn->prepare("SELECT id, image_url FROM products WHERE id = ? AND seller_id = ?");
    $product_check->bind_param("ii", $product_id, $user_id);
    $product_check->execute();
    $product_result = $product_check->get_result();
    
    if ($product_result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Product not found']);
        exit();
    }
    
    $product = $product_result->fetch_assoc();
    $product_check->close();
    
    // Get the order data
    $order_data = json_decode($_POST['order'], true);
    $main_position = isset($_POST['main_position']) ? intval($_POST['main_position']) : 0;
    
    if (empty($order_data) || !is_array($order_data)) {
        echo json_encode(['success' => false, 'message' => 'Invalid order data']);
        exit();
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // If main image is not at position 0, we need to swap it with the image at position 0
        if ($main_position > 0) {
            // Find the image that should become the new main image
            $new_main_image = null;
            foreach ($order_data as $item) {
                if ($item['sort_order'] === 0) {
                    // Get this image's URL
                    $image_url_stmt = $conn->prepare("SELECT image_url FROM product_images WHERE id = ? AND product_id = ?");
                    $image_url_stmt->bind_param("ii", $item['id'], $product_id);
                    $image_url_stmt->execute();
                    $image_url_result = $image_url_stmt->get_result();
                    if ($image_url_result->num_rows > 0) {
                        $image_data = $image_url_result->fetch_assoc();
                        $new_main_image = [
                            'id' => $item['id'],
                            'url' => $image_data['image_url']
                        ];
                    }
                    $image_url_stmt->close();
                    break;
                }
            }
            
            if ($new_main_image) {
                // Move old main image to product_images table
                if (!empty($product['image_url'])) {
                    $insert_old_main = $conn->prepare("INSERT INTO product_images (product_id, image_url, sort_order) VALUES (?, ?, ?)");
                    $insert_old_main->bind_param("isi", $product_id, $product['image_url'], $main_position);
                    $insert_old_main->execute();
                    $insert_old_main->close();
                }
                
                // Set new main image
                $update_main = $conn->prepare("UPDATE products SET image_url = ? WHERE id = ?");
                $update_main->bind_param("si", $new_main_image['url'], $product_id);
                $update_main->execute();
                $update_main->close();
                
                // Remove the new main image from product_images table
                $delete_from_additional = $conn->prepare("DELETE FROM product_images WHERE id = ?");
                $delete_from_additional->bind_param("i", $new_main_image['id']);
                $delete_from_additional->execute();
                $delete_from_additional->close();
                
                // Remove the old main image ID from the order data
                $order_data = array_filter($order_data, function($item) use ($new_main_image) {
                    return $item['id'] !== $new_main_image['id'];
                });
            }
        }
        
        // Update sort order for remaining images
        $update_stmt = $conn->prepare("UPDATE product_images SET sort_order = ? WHERE id = ? AND product_id = ?");
        
        foreach ($order_data as $item) {
            $image_id = $item['id'];
            $sort_order = $item['sort_order'];
            
            // Adjust sort_order to account for main image
            $adjusted_sort_order = $sort_order;
            if ($adjusted_sort_order > $main_position) {
                $adjusted_sort_order--;
            }
            
            $update_stmt->bind_param("iii", $adjusted_sort_order, $image_id, $product_id);
            $update_stmt->execute();
        }
        
        $update_stmt->close();
        $conn->commit();
        
        echo json_encode(['success' => true, 'message' => 'Image order updated successfully']);
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Failed to update image order: ' . $e->getMessage()]);
    }
    
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
?>
