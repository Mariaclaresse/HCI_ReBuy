<?php

session_start();

include 'db.php';



if (!isset($_SESSION['user_id'])) {

    header("Location: login.php");

    exit();

}



$user_id = $_SESSION['user_id'];

$success_msg = '';

$error_msg = '';



// Check if user is a seller

$is_seller = false;

$seller_check = $conn->query("SHOW COLUMNS FROM users LIKE 'is_seller'");

if ($seller_check->num_rows > 0) {

    $stmt = $conn->prepare("SELECT is_seller FROM users WHERE id = ?");

    $stmt->bind_param("i", $user_id);

    $stmt->execute();

    $result = $stmt->get_result();

    $user_seller = $result->fetch_assoc();

    $stmt->close();

    

    $is_seller = isset($user_seller['is_seller']) && $user_seller['is_seller'] == 1;

}



// Create uploads directory if it doesn't exist

$upload_dir = '../uploads/profile_pics/';

if (!is_dir($upload_dir)) {

    mkdir($upload_dir, 0755, true);

}



// Handle form submissions

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    if (isset($_POST['action']) && $_POST['action'] == 'remove_seller_status') {

        // Remove seller status

        $stmt = $conn->prepare("UPDATE users SET is_seller = 0 WHERE id = ?");

        if ($stmt === false) {

            $error_msg = "Database error: " . $conn->error;

        } else {

            $stmt->bind_param("i", $user_id);

            if ($stmt->execute()) {

                $stmt->close();

                // Destroy session and redirect to login

                session_destroy();

                header("Location: login.php");

                exit();

            } else {

                $error_msg = "Failed to remove seller status. Please try again.";

            }

            $stmt->close();

        }

    }

    elseif (isset($_POST['action']) && $_POST['action'] == 'upload_profile_pic') {

        if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == 0) {

            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

            $max_size = 5 * 1024 * 1024; // 5MB

            

            $file_type = $_FILES['profile_pic']['type'];

            $file_size = $_FILES['profile_pic']['size'];

            $file_tmp = $_FILES['profile_pic']['tmp_name'];

            $file_name = $_FILES['profile_pic']['name'];

            

            if (!in_array($file_type, $allowed_types)) {

                $error_msg = "Invalid file type. Only JPG, PNG, GIF, and WebP are allowed.";

            } elseif ($file_size > $max_size) {

                $error_msg = "File size too large. Maximum size is 5MB.";

            } else {

                // Generate unique filename

                $file_ext = pathinfo($file_name, PATHINFO_EXTENSION);

                $new_filename = 'user_' . $user_id . '_' . time() . '.' . $file_ext;

                $file_path = $upload_dir . $new_filename;

                

                if (move_uploaded_file($file_tmp, $file_path)) {

                    // Update database

                    $stmt = $conn->prepare("UPDATE users SET profile_pic = ? WHERE id = ?");

                    if ($stmt === false) {

                        $error_msg = "Database error: " . $conn->error;

                    } else {

                        $stmt->bind_param("si", $new_filename, $user_id);

                        if ($stmt->execute()) {

                            $success_msg = "Profile picture uploaded successfully!";

                        } else {

                            $error_msg = "Failed to save profile picture.";

                        }

                        $stmt->close();

                    }

                } else {

                    $error_msg = "Failed to upload file.";

                }

            }

        } else {

            $error_msg = "Please select a file to upload.";

        }

    }

    elseif (isset($_POST['action']) && $_POST['action'] == 'update_profile') {

        $firstname = $_POST['firstname'] ?? '';

        $lastname = $_POST['lastname'] ?? '';

        $email = $_POST['email'] ?? '';

        $middle_name = $_POST['middle_name'] ?? '';

        $name_extension = $_POST['name_extension'] ?? '';

        $gender = $_POST['gender'] ?? '';

        $birthdate = $_POST['birthdate'] ?? '';

        $age = $_POST['age'] ?? '';



        $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, middle_name = ?, name_extension = ?, gender = ?, birthdate = ?, age = ? WHERE id = ?");

        if ($stmt === false) {

            $error_msg = "Prepare failed: " . $conn->error;

        } else {

            $stmt->bind_param("ssssssssi", $firstname, $lastname, $email, $middle_name, $name_extension, $gender, $birthdate, $age, $user_id);

            if ($stmt->execute()) {

                $success_msg = "Profile updated successfully!";

            } else {

                $error_msg = "Failed to update profile: " . $stmt->error;

            }

            $stmt->close();

        }

    } 

    elseif (isset($_POST['action']) && $_POST['action'] == 'change_password') {

        $current_password = $_POST['current_password'] ?? '';

        $new_password = $_POST['new_password'] ?? '';

        $confirm_password = $_POST['confirm_password'] ?? '';



        if ($new_password !== $confirm_password) {

            $error_msg = "New passwords do not match!";

        } else if (strlen($new_password) < 6) {

            $error_msg = "Password must be at least 6 characters!";

        } else {

            // Verify current password

            $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");

            $stmt->bind_param("i", $user_id);

            $stmt->execute();

            $result = $stmt->get_result();

            $user_data = $result->fetch_assoc();

            $stmt->close();



            if (password_verify($current_password, $user_data['password'])) {

                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

                $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");

                $stmt->bind_param("si", $hashed_password, $user_id);

                if ($stmt->execute()) {

                    $success_msg = "Password changed successfully!";

                } else {

                    $error_msg = "Failed to change password.";

                }

                $stmt->close();

            } else {

                $error_msg = "Current password is incorrect!";

            }

        }

    }

    elseif (isset($_POST['action']) && $_POST['action'] == 'update_address') {

        $street = $_POST['street'] ?? '';

        $barangay = $_POST['barangay'] ?? '';

        $city = $_POST['city'] ?? '';

        $province = $_POST['province'] ?? '';

        $country = $_POST['country'] ?? '';

        $zip_code = $_POST['zip_code'] ?? '';



        $stmt = $conn->prepare("UPDATE users SET purok_street = ?, barangay = ?, municipality_city = ?, province = ?, country = ?, zip_code = ? WHERE id = ?");

        if ($stmt === false) {

            $error_msg = "Prepare failed: " . $conn->error;

        } else {

            $stmt->bind_param("ssssssi", $street, $barangay, $city, $province, $country, $zip_code, $user_id);

            if ($stmt->execute()) {

                $success_msg = "Address updated successfully!";

            } else {

                $error_msg = "Failed to update address: " . $stmt->error;

            }

            $stmt->close();

        }

    }

    elseif (isset($_POST['action']) && $_POST['action'] == 'add_payment_method') {

        $payment_type = $_POST['payment_type'] ?? 'card';

        $is_default = isset($_POST['is_default']) ? 1 : 0;

        

        // Initialize variables

        $card_type = $card_number = $cardholder_name = $expiry_month = $expiry_year = $cvv = $ewallet_provider = $ewallet_number = null;

        

        // Validate based on payment type

        if ($payment_type == 'card') {

            $card_type = $_POST['card_type'] ?? '';

            $card_number = $_POST['card_number'] ?? '';

            $cardholder_name = $_POST['cardholder_name'] ?? '';

            $expiry_month = $_POST['expiry_month'] ?? '';

            $expiry_year = $_POST['expiry_year'] ?? '';

            $cvv = $_POST['cvv'] ?? '';



            // Validate card number (basic validation)

            if (!preg_match('/^\d{13,19}$/', str_replace(' ', '', $card_number))) {

                $error_msg = "Invalid card number. Please enter a valid card number.";

            }

            // Validate CVV

            elseif (!preg_match('/^\d{3,4}$/', $cvv)) {

                $error_msg = "Invalid CVV. Please enter a valid CVV.";

            }

            // Validate expiry date

            elseif ($expiry_month < 1 || $expiry_month > 12 || $expiry_year < date('Y') || ($expiry_year == date('Y') && $expiry_month < date('m'))) {

                $error_msg = "Invalid expiry date. Please enter a valid future date.";

            }

        }

        elseif ($payment_type == 'ewallet') {

            $ewallet_provider = $_POST['ewallet_provider'] ?? '';

            $ewallet_number = $_POST['ewallet_number'] ?? '';

            

            // Validate e-wallet number (basic validation for mobile number)

            if (!preg_match('/^\d{10,11}$/', $ewallet_number)) {

                $error_msg = "Invalid mobile number. Please enter a valid 10-11 digit mobile number.";

            }

        }

        elseif ($payment_type == 'cod') {

            // COD doesn't require additional validation

            $card_type = 'Cash on Delivery';

        }

        

        if (empty($error_msg)) {

            // Check if payment_methods table exists, create if not

            $table_check = $conn->query("SHOW TABLES LIKE 'payment_methods'");

            if ($table_check->num_rows == 0) {

                // Create the table with updated schema

                $create_table_sql = "CREATE TABLE IF NOT EXISTS `payment_methods` (

                    `id` int(11) NOT NULL AUTO_INCREMENT,

                    `user_id` int(11) NOT NULL,

                    `payment_type` enum('card','ewallet','cod') NOT NULL DEFAULT 'card',

                    `card_type` varchar(50) DEFAULT NULL,

                    `card_number` varchar(20) DEFAULT NULL,

                    `cardholder_name` varchar(100) DEFAULT NULL,

                    `expiry_month` varchar(2) DEFAULT NULL,

                    `expiry_year` varchar(4) DEFAULT NULL,

                    `cvv` varchar(4) DEFAULT NULL,

                    `ewallet_provider` varchar(50) DEFAULT NULL,

                    `ewallet_number` varchar(50) DEFAULT NULL,

                    `is_default` tinyint(1) DEFAULT 0,

                    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,

                    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                    PRIMARY KEY (`id`),

                    KEY `user_id` (`user_id`)

                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

                $conn->query($create_table_sql);

            } else {

                // Check if new columns exist, add them if not

                $column_check = $conn->query("SHOW COLUMNS FROM payment_methods LIKE 'payment_type'");

                if ($column_check->num_rows == 0) {

                    // Add new columns for updated schema

                    $conn->query("ALTER TABLE payment_methods ADD COLUMN payment_type ENUM('card', 'ewallet', 'cod') NOT NULL DEFAULT 'card' AFTER card_type");

                    $conn->query("ALTER TABLE payment_methods ADD COLUMN ewallet_provider VARCHAR(50) NULL AFTER payment_type");

                    $conn->query("ALTER TABLE payment_methods ADD COLUMN ewallet_number VARCHAR(50) NULL AFTER ewallet_provider");

                    $conn->query("ALTER TABLE payment_methods MODIFY COLUMN card_number VARCHAR(20) NULL");

                    $conn->query("ALTER TABLE payment_methods MODIFY COLUMN cardholder_name VARCHAR(100) NULL");

                    $conn->query("ALTER TABLE payment_methods MODIFY COLUMN expiry_month VARCHAR(2) NULL");

                    $conn->query("ALTER TABLE payment_methods MODIFY COLUMN expiry_year VARCHAR(4) NULL");

                    $conn->query("ALTER TABLE payment_methods MODIFY COLUMN cvv VARCHAR(4) NULL");

                    $conn->query("UPDATE payment_methods SET payment_type = 'card' WHERE payment_type IS NULL OR payment_type = ''");

                }

            }



            // If setting as default, unset other default payment methods

            if ($is_default) {

                $unset_default = $conn->prepare("UPDATE payment_methods SET is_default = 0 WHERE user_id = ?");

                if ($unset_default) {

                    $unset_default->bind_param("i", $user_id);

                    $unset_default->execute();

                    $unset_default->close();

                }

            }



            // Prepare data for insertion

            $display_number = null;

            if ($payment_type == 'card') {

                // Mask card number (show only last 4 digits)

                $display_number = '**** **** **** ' . substr(str_replace(' ', '', $card_number), -4);

            } elseif ($payment_type == 'ewallet') {

                // Mask e-wallet number (show only last 4 digits)

                $display_number = '*** *** *** ' . substr($ewallet_number, -4);

            }



            $stmt = $conn->prepare("INSERT INTO payment_methods (user_id, payment_type, card_type, card_number, cardholder_name, expiry_month, expiry_year, cvv, ewallet_provider, ewallet_number, is_default) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

            if ($stmt === false) {

                $error_msg = "Prepare failed: " . $conn->error;

            } else {

                $stmt->bind_param("isssssssssi", $user_id, $payment_type, $card_type, $display_number, $cardholder_name, $expiry_month, $expiry_year, $cvv, $ewallet_provider, $display_number, $is_default);

                if ($stmt->execute()) {

                    $success_msg = "Payment method added successfully!";

                } else {

                    $error_msg = "Failed to add payment method: " . $stmt->error;

                }

                $stmt->close();

            }

        }

    }

    elseif (isset($_POST['action']) && $_POST['action'] == 'delete_payment_method') {

        $payment_method_id = $_POST['payment_method_id'] ?? 0;



        if ($payment_method_id > 0) {

            $stmt = $conn->prepare("DELETE FROM payment_methods WHERE id = ? AND user_id = ?");

            if ($stmt === false) {

                $error_msg = "Prepare failed: " . $conn->error;

            } else {

                $stmt->bind_param("ii", $payment_method_id, $user_id);

                if ($stmt->execute()) {

                    $success_msg = "Payment method deleted successfully!";

                } else {

                    $error_msg = "Failed to delete payment method: " . $stmt->error;

                }

                $stmt->close();

            }

        } else {

            $error_msg = "Invalid payment method ID.";

        }

    }

    elseif (isset($_POST['action']) && $_POST['action'] == 'set_default_payment') {

        $payment_method_id = $_POST['payment_method_id'] ?? 0;



        if ($payment_method_id > 0) {

            // Unset all default payment methods for this user

            $unset_default = $conn->prepare("UPDATE payment_methods SET is_default = 0 WHERE user_id = ?");

            if ($unset_default) {

                $unset_default->bind_param("i", $user_id);

                $unset_default->execute();

                $unset_default->close();

            }



            // Set the selected payment method as default

            $set_default = $conn->prepare("UPDATE payment_methods SET is_default = 1 WHERE id = ? AND user_id = ?");

            if ($set_default === false) {

                $error_msg = "Prepare failed: " . $conn->error;

            } else {

                $set_default->bind_param("ii", $payment_method_id, $user_id);

                if ($set_default->execute()) {

                    $success_msg = "Default payment method updated successfully!";

                } else {

                    $error_msg = "Failed to update default payment method: " . $set_default->error;

                }

                $set_default->close();

            }

        } else {

            $error_msg = "Invalid payment method ID.";

        }

    }

    elseif (isset($_POST['action']) && $_POST['action'] == 'cancel_order') {

        $order_id = $_POST['order_id'] ?? 0;

        

        if ($order_id > 0) {

            // Check if order belongs to user and is in pending status

            $check_stmt = $conn->prepare("SELECT id FROM orders WHERE id = ? AND user_id = ? AND status = 'pending'");

            if ($check_stmt) {

                $check_stmt->bind_param("ii", $order_id, $user_id);

                $check_stmt->execute();

                $check_result = $check_stmt->get_result();

                

                if ($check_result->num_rows > 0) {

                    // Update order status to cancelled and set cancelled_at timestamp

                    $update_stmt = $conn->prepare("UPDATE orders SET status = 'cancelled', cancelled_at = NOW() WHERE id = ? AND user_id = ?");

                    if ($update_stmt) {

                        $update_stmt->bind_param("ii", $order_id, $user_id);

                        if ($update_stmt->execute()) {

                            // Get order items to restore stock immediately

                            $items_stmt = $conn->prepare("

                                SELECT product_id, quantity 

                                FROM order_items 

                                WHERE order_id = ?

                            ");

                            $items_stmt->bind_param("i", $order_id);

                            $items_stmt->execute();

                            $items_result = $items_stmt->get_result();

                            

                            // Restore stock for each item

                            while ($item = $items_result->fetch_assoc()) {

                                $update_stock = $conn->prepare("

                                    UPDATE products 

                                    SET stock = stock + ? 

                                    WHERE id = ?

                                ");

                                $update_stock->bind_param("ii", $item['quantity'], $item['product_id']);

                                $update_stock->execute();

                                $update_stock->close();

                            }

                            $items_stmt->close();

                            

                            $success_msg = "Order cancelled successfully! Stock has been restored.";

                        } else {

                            $error_msg = "Failed to cancel order.";

                        }

                        $update_stmt->close();

                    } else {

                        $error_msg = "Database error: " . $conn->error;

                    }

                } else {

                    $error_msg = "Order not found or cannot be cancelled.";

                }

                $check_stmt->close();

            } else {

                $error_msg = "Database error: " . $conn->error;

            }

        } else {

            $error_msg = "Invalid order ID.";

        }

    }

}



// Fetch user's payment methods

$payment_methods = [];

$payment_methods_check = $conn->query("SHOW TABLES LIKE 'payment_methods'");

if ($payment_methods_check->num_rows > 0) {

    // Check if new columns exist

    $column_check = $conn->query("SHOW COLUMNS FROM payment_methods LIKE 'payment_type'");

    if ($column_check->num_rows > 0) {

        // New schema - include all fields

        $payment_stmt = $conn->prepare("SELECT id, payment_type, card_type, card_number, cardholder_name, expiry_month, expiry_year, ewallet_provider, ewallet_number, is_default, created_at FROM payment_methods WHERE user_id = ? ORDER BY is_default DESC, created_at DESC");

    } else {

        // Old schema - use existing fields

        $payment_stmt = $conn->prepare("SELECT id, card_type, card_number, cardholder_name, expiry_month, expiry_year, is_default, created_at FROM payment_methods WHERE user_id = ? ORDER BY is_default DESC, created_at DESC");

    }

    if ($payment_stmt) {

        $payment_stmt->bind_param("i", $user_id);

        $payment_stmt->execute();

        $payment_result = $payment_stmt->get_result();

        while ($payment = $payment_result->fetch_assoc()) {

            // Add payment_type if not present (backward compatibility)

            if (!isset($payment['payment_type'])) {

                $payment['payment_type'] = 'card';

            }

            $payment_methods[] = $payment;

        }

        $payment_stmt->close();

    }

}



// Fetch user data

$stmt = $conn->prepare("SELECT first_name, last_name, email, username, profile_pic, purok_street, barangay, municipality_city, province, country, zip_code, middle_name, name_extension, gender, birthdate, age FROM users WHERE id = ?");

if ($stmt === false) {

    $error_msg = "Prepare failed: " . $conn->error;

} else {

    $stmt->bind_param("i", $user_id);

    $stmt->execute();

    $result = $stmt->get_result();

    $user = $result->fetch_assoc();

    $stmt->close();

    

    // Check if is_seller column exists and get seller status

    $user['is_seller'] = 0; // Default to not seller

    $seller_check = $conn->query("SHOW COLUMNS FROM users LIKE 'is_seller'");

    if ($seller_check->num_rows > 0) {

        $seller_stmt = $conn->prepare("SELECT is_seller FROM users WHERE id = ?");

        if ($seller_stmt) {

            $seller_stmt->bind_param("i", $user_id);

            $seller_stmt->execute();

            $seller_result = $seller_stmt->get_result();

            if ($seller_data = $seller_result->fetch_assoc()) {

                $user['is_seller'] = $seller_data['is_seller'];

            }

            $seller_stmt->close();

        }

    }

}



// Fetch user orders

$user_orders = [];



// Create orders table if it doesn't exist

$conn->query("CREATE TABLE IF NOT EXISTS orders (

    id INT AUTO_INCREMENT PRIMARY KEY,

    user_id INT NOT NULL,

    total_amount DECIMAL(10,2) NOT NULL,

    shipping_address TEXT,

    payment_method VARCHAR(50),

    status VARCHAR(20) DEFAULT 'pending',

    order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id)

)");



// Add created_at column if it doesn't exist (for backward compatibility)

$conn->query("ALTER TABLE orders ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");



// Create order_items table if it doesn't exist

$conn->query("CREATE TABLE IF NOT EXISTS order_items (

    id INT AUTO_INCREMENT PRIMARY KEY,

    order_id INT NOT NULL,

    product_id INT NOT NULL,

    quantity INT NOT NULL,

    price DECIMAL(10,2) NOT NULL,

    FOREIGN KEY (order_id) REFERENCES orders(id),

    FOREIGN KEY (product_id) REFERENCES products(id)

)");



// Fetch orders with seller order details

$orders_stmt = $conn->prepare("

    SELECT o.id, o.order_date, o.total_amount, o.status, o.payment_method, o.cancelled_at,

           CASE 

               WHEN o.created_at IS NOT NULL THEN o.created_at 

               ELSE o.order_date 

           END as display_date,

           so.id as seller_order_id, so.status as seller_status, so.order_id as seller_order_ref,

           p.name as product_name, p.image_url as product_image, so.quantity,

           u.first_name as seller_first_name, u.last_name as seller_last_name

    FROM orders o

    LEFT JOIN seller_orders so ON o.user_id = so.customer_id AND o.order_date = so.order_date

    LEFT JOIN products p ON so.product_id = p.id

    LEFT JOIN users u ON so.seller_id = u.id

    WHERE o.user_id = ?

    ORDER BY o.order_date DESC

");

if ($orders_stmt) {

    $orders_stmt->bind_param("i", $user_id);

    $orders_stmt->execute();

    $orders_result = $orders_stmt->get_result();

    

    while ($row = $orders_result->fetch_assoc()) {

        $order_id = $row['id'];

        if (!isset($user_orders[$order_id])) {

            $user_orders[$order_id] = [

                'id' => $row['id'],

                'order_date' => $row['order_date'],

                'display_date' => $row['display_date'],

                'total_amount' => $row['total_amount'],

                'status' => $row['status'],

                'payment_method' => $row['payment_method'],

                'cancelled_at' => $row['cancelled_at'],

                'items' => []

            ];

        }

        if ($row['seller_order_id']) {

            $user_orders[$order_id]['items'][] = [

                'seller_order_id' => $row['seller_order_id'],

                'seller_status' => $row['seller_status'],

                'seller_order_ref' => $row['seller_order_ref'],

                'product_name' => $row['product_name'],

                'product_image' => $row['product_image'],

                'quantity' => $row['quantity'],

                'seller_name' => $row['seller_first_name'] . ' ' . $row['seller_last_name']

            ];

        }

    }

    $orders_stmt->close();

} else {

    $error_msg = "Error fetching orders: " . $conn->error;

}



// Group orders by date

$orders_by_date = [];

foreach ($user_orders as $order) {

    $date_key = date('F j, Y', strtotime($order['order_date']));

    if (!isset($orders_by_date[$date_key])) {

        $orders_by_date[$date_key] = [];

    }

    $orders_by_date[$date_key][] = $order;

}



// Get seller statistics if user is a seller

$seller_stats = [

    'total_products' => 0,

    'total_orders' => 0,

    'total_revenue' => 0,

    'avg_rating' => 0

];



if (($user['is_seller'] ?? 0) == 1) {

    // Total products

    $products_stmt = $conn->prepare("SELECT COUNT(*) as count FROM products WHERE seller_id = ?");

    $products_stmt->bind_param("i", $user_id);

    $products_stmt->execute();

    $seller_stats['total_products'] = $products_stmt->get_result()->fetch_assoc()['count'] ?? 0;

    $products_stmt->close();



    // Total orders

    $orders_stmt = $conn->prepare("SELECT COUNT(*) as count FROM seller_orders WHERE seller_id = ?");

    $orders_stmt->bind_param("i", $user_id);

    $orders_stmt->execute();

    $seller_stats['total_orders'] = $orders_stmt->get_result()->fetch_assoc()['count'] ?? 0;

    $orders_stmt->close();



    // Total revenue (from delivered orders)

    $revenue_stmt = $conn->prepare("SELECT SUM(total_amount) as total FROM seller_orders WHERE seller_id = ? AND status = 'delivered'");

    $revenue_stmt->bind_param("i", $user_id);

    $revenue_stmt->execute();

    $seller_stats['total_revenue'] = $revenue_stmt->get_result()->fetch_assoc()['total'] ?? 0;

    $revenue_stmt->close();

}



// Handle seller request

if (isset($_POST['action']) && $_POST['action'] == 'request_seller') {

    // Check if is_seller column exists

    $seller_check = $conn->query("SHOW COLUMNS FROM users LIKE 'is_seller'");

    if ($seller_check->num_rows > 0) {

        $stmt = $conn->prepare("UPDATE users SET is_seller = 1, seller_requested_at = NOW(), seller_approved_at = NOW() WHERE id = ?");

        if ($stmt === false) {

            $error_msg = "Prepare failed: " . $conn->error;

        } else {

            $stmt->bind_param("i", $user_id);

            if ($stmt->execute()) {

                // Destroy session and logout user

                session_destroy();

                // Redirect to login page with success message

                header("Location: login.php?seller_enabled=1");

                exit();

            } else {

                $error_msg = "Failed to submit seller request: " . $stmt->error;

            }

            $stmt->close();

        }

    } else {

        $error_msg = "Seller feature is not available yet. Please contact administrator to enable seller functionality.";

    }

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

    <link rel="stylesheet" href="../css/settings.css">

</head>

<style>
.custom-modal{
    display:none;
    position:fixed;
    top:0;
    left:0;
    width:100%;
    height:100%;
    background:rgba(0,0,0,0.5);
    justify-content:center;
    align-items:center;
    z-index:9999;
}

.custom-modal-content{
    background:#fff;
    width:400px;
    max-width:90%;
    padding:30px;
    border-radius:15px;
    text-align:center;
    animation:popup 0.3s ease;
    box-shadow:0 10px 30px rgba(0,0,0,0.2);
}

.modal-icon{
    width:70px;
    height:70px;
    background:#2d5016;
    color:#fff;
    border-radius:50%;
    display:flex;
    align-items:center;
    justify-content:center;
    margin:0 auto 15px;
    font-size:30px;
}

.custom-modal-content h2{
    margin-bottom:10px;
    color:#2d5016;
    font-size:24px;
}

.custom-modal-content p{
    color:#666;
    margin-bottom:25px;
    line-height:1.6;
    font-size:15px;
}

.modal-buttons{
    display:flex;
    justify-content:center;
    gap:12px;
}

.cancel-btn,
.confirm-btn{
    border:none;
    padding:12px 25px;
    border-radius:8px;
    cursor:pointer;
    font-size:14px;
    font-weight:600;
    transition:0.3s;
}

.cancel-btn{
    background:#ddd;
    color:#333;
}

.cancel-btn:hover{
    background:#c5c5c5;
}

.confirm-btn{
    background:#2d5016;
    color:white;
}

.confirm-btn:hover{
    background:#1e3009;
}

@keyframes popup{
    from{
        transform:scale(0.8);
        opacity:0;
    }

    to{
        transform:scale(1);
        opacity:1;
    }
}

.custom-modal{
    display:none;
    position:fixed;
    top:0;
    left:0;
    width:100%;
    height:100%;
    background:rgba(0,0,0,0.5);
    justify-content:center;
    align-items:center;
    z-index:9999;
}

.custom-modal-content{
    background:#fff;
    width:400px;
    max-width:90%;
    padding:30px;
    border-radius:15px;
    text-align:center;
    animation:popup 0.3s ease;
    box-shadow:0 10px 30px rgba(0,0,0,0.2);
}

.modal-icon{
    width:70px;
    height:70px;
    border-radius:50%;
    display:flex;
    align-items:center;
    justify-content:center;
    margin:0 auto 15px;
    font-size:30px;
    color:white;
}

.remove-icon{
    background:#dc3545;
}

.custom-modal-content h2{
    margin-bottom:10px;
    color:#333;
    font-size:24px;
}

.custom-modal-content p{
    color:#666;
    margin-bottom:25px;
    line-height:1.6;
    font-size:15px;
}

.modal-buttons{
    display:flex;
    justify-content:center;
    gap:12px;
}

.cancel-btn,
.remove-btn{
    border:none;
    padding:12px 25px;
    border-radius:8px;
    cursor:pointer;
    font-size:14px;
    font-weight:600;
    transition:0.3s;
}

.cancel-btn{
    background:#ddd;
    color:#333;
}

.cancel-btn:hover{
    background:#c5c5c5;
}

.remove-btn{
    background:#dc3545;
    color:white;
}

.remove-btn:hover{
    background:#bb2d3b;
}

@keyframes popup{
    from{
        transform:scale(0.8);
        opacity:0;
    }

    to{
        transform:scale(1);
        opacity:1;
    }
}
</style>


<body>

    <div class="page-wrapper">



        <!-- Main Header -->
        <?php include '_header.php'; ?>



        <!-- Page Content -->

        <div class="page-content">

            <div class="account-container">

                <!-- Sidebar Menu -->

                <aside class="account-sidebar">

                    <ul class="account-menu">

                        <li><a href="#personal" class="active" onclick="showSection('personal'); return false;"><i class="fas fa-user"></i> Personal Information</a></li>

                        <li><a href="#orders" onclick="showSection('orders'); return false;"><i class="fas fa-shopping-cart"></i> My Orders</a></li>

                        <li><a href="#addresses" onclick="showSection('addresses'); return false;"><i class="fas fa-map-marker-alt"></i> Manage Address</a></li>

                        <li><a href="#security" onclick="showSection('security'); return false;"><i class="fas fa-lock"></i> Password Manager</a></li>

                        <li><a href="#seller" onclick="showSection('seller'); return false;"><i class="fas fa-store"></i> Seller Account</a></li>

                    </ul>

                </aside>



                <!-- Main Content -->

                <main class="account-main">

                    <?php if ($success_msg): ?>

                        <div style="background: #e8f5e9; border-left: 4px solid #2d5016; padding: 12px 15px; margin-bottom: 20px; border-radius: 4px; color: #333; font-size: 13px;">

                            <i class="fas fa-check-circle"></i> <?php echo $success_msg; ?>

                        </div>

                    <?php endif; ?>



                    <?php if ($error_msg): ?>

                        <div style="background: #ffebee; border-left: 4px solid #e74c3c; padding: 12px 15px; margin-bottom: 20px; border-radius: 4px; color: #333; font-size: 13px;">

                            <i class="fas fa-exclamation-circle"></i> <?php echo $error_msg; ?>

                        </div>

                    <?php endif; ?>



                    <!-- Profile Header -->

                    <div class="account-header">

                        <div class="profile-avatar">

                            <div class="avatar-image">

                                <?php if (!empty($user['profile_pic']) && file_exists($upload_dir . $user['profile_pic'])): ?>

                                    <img src="<?php echo htmlspecialchars($upload_dir . $user['profile_pic']); ?>" alt="Profile Picture">

                                <?php else: ?>

                                    <i class="fas fa-user"></i>

                                <?php endif; ?>

                            </div>

                            <button class="avatar-edit" type="button" onclick="document.getElementById('profile-pic-input').click();" title="Edit Profile Picture">

                                <i class="fas fa-camera"></i>

                            </button>

                            

                            <!-- Hidden file input -->

                            <form id="profile-pic-form" method="POST" enctype="multipart/form-data" style="display: none;">

                                <input type="hidden" name="action" value="upload_profile_pic">

                                <input type="file" id="profile-pic-input" name="profile_pic" accept="image/*" onchange="document.getElementById('profile-pic-form').submit();">

                            </form>

                        </div>

                        <div class="profile-info">

                            <h2><?php echo htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['middle_name'] ?? '') . ' ' . ($user['last_name'] ?? '')); ?></h2>

                            <p><?php echo htmlspecialchars($user['email'] ?? ''); ?></p>

                            <div class="profile-badges">

                                <span class="profile-badge"><i class="fas fa-check"></i> Verified Member</span>

                                <span class="profile-badge"><i class="fas fa-star"></i> 5.0 Rating</span>

                                <?php if (($user['is_seller'] ?? 0) == 1): ?>

                                    <span class="profile-badge" style="background: #fff3cd; color: #856404;"><i class="fas fa-store"></i> Seller</span>

                                <?php endif; ?>

                            </div>

                        </div>

                    </div>



                    <!-- Personal Information Section -->

                    <section class="account-section" id="personal-section">

                        <h3 class="section-title"><i class="fas fa-user-circle"></i> Personal Information</h3>

                        

                        <form method="POST" action="">

                            <input type="hidden" name="action" value="update_profile">

                            

                            <div class="form-row">

                                <div class="form-group">

                                    <label>First Name <span class="required">*</span></label>

                                    <input type="text" name="firstname" value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>" required>

                                </div>

                                <div class="form-group">

                                    <label>Middle Name</label>

                                    <input type="text" name="middle_name" value="<?php echo htmlspecialchars($user['middle_name'] ?? ''); ?>">

                                </div>

                            </div>



                            <div class="form-row">

                                <div class="form-group">

                                    <label>Last Name <span class="required">*</span></label>

                                    <input type="text" name="lastname" value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>" required>

                                </div>

                                <div class="form-group">

                                    <label>Name Extension</label>

                                    <input type="text" name="name_extension" value="<?php echo htmlspecialchars($user['name_extension'] ?? ''); ?>" placeholder="e.g., Jr., Sr., III">

                                </div>

                            </div>



                            <div class="form-row">

                                <div class="form-group">

                                    <label>Email <span class="required">*</span></label>

                                    <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>

                                </div>

                                <div class="form-group">

                                    <label>Gender</label>

                                    <select name="gender">

                                        <option value="">Select Gender</option>

                                        <option value="Male" <?php echo ($user['gender'] ?? '') == 'Male' ? 'selected' : ''; ?>>Male</option>

                                        <option value="Female" <?php echo ($user['gender'] ?? '') == 'Female' ? 'selected' : ''; ?>>Female</option>

                                        <option value="Other" <?php echo ($user['gender'] ?? '') == 'Other' ? 'selected' : ''; ?>>Other</option>

                                    </select>

                                </div>

                            </div>



                            <div class="form-row">

                                <div class="form-group">

                                    <label>Birthdate</label>

                                    <input type="date" name="birthdate" value="<?php echo htmlspecialchars($user['birthdate'] ?? ''); ?>" onchange="calculateAge(this.value)">

                                </div>

                                <div class="form-group">

                                    <label>Age</label>

                                    <input type="number" name="age" value="<?php echo htmlspecialchars($user['age'] ?? ''); ?>" min="0" max="150" readonly style="background: #f5f5f5; cursor: not-allowed;">

                                </div>

                            </div>



                            <div class="form-row full">

                                <div class="form-group">

                                    <label>Username</label>

                                    <input type="text" value="<?php echo htmlspecialchars($user['username']); ?>" readonly style="background: #f5f5f5; cursor: not-allowed;">

                                </div>

                            </div>



                            <div class="form-actions">

                                <button type="submit" class="btn-primary">

                                    <i class="fas fa-save"></i> Update Changes

                                </button>

                                <button type="reset" class="btn-secondary">

                                    <i class="fas fa-redo"></i> Reset

                                </button>

                            </div>

                        </form>

                    </section>



                    <!-- Orders Section -->

                    <section class="account-section" id="orders-section" style="display: none;">

                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">

                            <h3 class="section-title" style="margin: 0;"><i class="fas fa-shopping-cart"></i> Orders (<?php echo count($user_orders); ?>)</h3>

                        </div>



                        <!-- Track Order Form -->

                        <div style="background: linear-gradient(135deg, #2d5016 0%, #4a7c2e 100%); padding: 30px; border-radius: 12px; margin-bottom: 30px;">

                            <h4 style="color: white; font-size: 20px; margin-bottom: 20px; font-weight: 600;"><i class="fas fa-truck"></i> Track Your Order</h4>

                            <form method="GET" action="track_order.php" style="display: grid; grid-template-columns: 1fr 1fr auto; gap: 15px; align-items: end;">

                                <div>

                                    <label style="color: rgba(255,255,255,0.9); font-size: 13px; font-weight: 600; margin-bottom: 8px; display: block;">Order ID</label>

                                    <input type="text" name="order_id" placeholder="Enter Order ID" required style="width: 100%; padding: 12px 15px; border: none; border-radius: 6px; font-size: 14px; background: rgba(255,255,255,0.95);">

                                </div>

                                <div>

                                    <label style="color: rgba(255,255,255,0.9); font-size: 13px; font-weight: 600; margin-bottom: 8px; display: block;">Billing Email</label>

                                    <input type="email" name="email" placeholder="Enter Email" required style="width: 100%; padding: 12px 15px; border: none; border-radius: 6px; font-size: 14px; background: rgba(255,255,255,0.95);">

                                </div>

                                <button type="submit" style="background: #f4c430; color: #333; border: none; padding: 12px 30px; border-radius: 6px; font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.3s; height: 45px;">

                                    <i class="fas fa-search"></i> Track Order

                                </button>

                            </form>

                        </div>

                        

                        <?php if (count($orders_by_date) > 0): ?>

                            <?php foreach ($orders_by_date as $date => $orders): ?>

                                <div style="margin-bottom: 30px;">

                                    <h4 style="font-size: 16px; color: #333; margin-bottom: 15px; font-weight: 600;">

                                        <i class="fas fa-calendar-alt" style="color: #2d5016; margin-right: 8px;"></i>

                                        <?php echo $date; ?>

                                    </h4>

                                    <?php foreach ($orders as $order): ?>

                                <?php

                                // Use the orders.status directly which is synced with seller_orders

                                $overall_status = $order['status'] ?? 'pending';

                                

                                // Generate order ID format

                                $order_id_display = '#ORD' . str_pad($order['id'], 6, '0', STR_PAD_LEFT);

                                

                                // Calculate estimated delivery date (7 days from order date)

                                $order_date = strtotime($order['display_date']);

                                $estimated_delivery = date('d F Y', strtotime('+7 days', $order_date));

                                $delivered_date = $overall_status == 'delivered' ? date('d F Y', strtotime('+5 days', $order_date)) : '';

                                ?>

                                

                                <div style="background: white; border-radius: 12px; overflow: hidden; margin-bottom: 20px; box-shadow: 0 2px 12px rgba(0,0,0,0.08);">

                                    <!-- Order Header -->

                                    <div style="background: #f8f9fa; padding: 20px; border-bottom: 1px solid #e9ecef;">

                                        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px;">

                                            <div>

                                                <div style="font-size: 12px; color: #666; margin-bottom: 5px;">Order ID</div>

                                                <div style="font-weight: 700; color: #2d5016; font-size: 16px;"><?php echo $order_id_display; ?></div>

                                            </div>

                                            <div>

                                                <div style="font-size: 12px; color: #666; margin-bottom: 5px;">Total Payment</div>

                                                <div style="font-weight: 700; color: #2d5016; font-size: 16px;">₱<?php echo number_format($order['total_amount'], 2); ?></div>

                                            </div>

                                            <div>

                                                <div style="font-size: 12px; color: #666; margin-bottom: 5px;">Payment Method</div>

                                                <div style="font-weight: 700; color: #2d5016; font-size: 16px;"><?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($order['payment_method']))); ?></div>

                                            </div>

                                            <div>

                                                <div style="font-size: 12px; color: #666; margin-bottom: 5px;">

                                                    <?php echo $overall_status == 'delivered' ? 'Delivered Date' : 'Estimated Delivery'; ?>

                                                </div>

                                                <div style="font-weight: 700; color: #2d5016; font-size: 16px;">

                                                    <?php echo $overall_status == 'delivered' ? $delivered_date : $estimated_delivery; ?>

                                                </div>

                                            </div>

                                        </div>

                                    </div>

                                    

                                    <!-- Order Items -->

                                    <div style="padding: 20px;">

                                        <?php if (!empty($order['items'])): ?>

                                            <?php foreach ($order['items'] as $item): ?>

                                                <div style="display: flex; align-items: center; padding: 15px 0; border-bottom: 1px solid #f0f0f0;">

                                                    <div style="width: 80px; height: 80px; border-radius: 8px; overflow: hidden; margin-right: 20px; flex-shrink: 0; border: 1px solid #e9ecef;">

                                                        <img src="<?php echo !empty($item['product_image']) ? '../' . htmlspecialchars($item['product_image']) : 'https://images.unsplash.com/photo-1586023492125-27b2c045efd7?ixlib=rb-4.0.3&auto=format&fit=crop&w=100&q=80'; ?>" alt="<?php echo htmlspecialchars($item['product_name']); ?>" style="width: 100%; height: 100%; object-fit: cover;">

                                                    </div>

                                                    <div style="flex: 1;">

                                                        <div style="font-weight: 600; color: #333; margin-bottom: 5px; font-size: 15px;"><?php echo htmlspecialchars($item['product_name']); ?></div>

                                                        <div style="font-size: 13px; color: #666;">Color: As shown</div>

                                                        <div style="font-size: 13px; color: #666;">Quantity: <?php echo $item['quantity']; ?></div>

                                                    </div>

                                                </div>

                                            <?php endforeach; ?>

                                        <?php else: ?>

                                            <div style="color: #666; padding: 10px 0;">No items in this order</div>

                                        <?php endif; ?>

                                    </div>

                                    

                                    <!-- Order Status and Actions -->

                                    <div style="padding: 20px; border-top: 1px solid #e9ecef; background: #fafafa;">

                                        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">

                                            <div>

                                                <?php if ($overall_status == 'cancelled'): ?>

                                                    <span style="display: inline-block; padding: 8px 16px; border-radius: 20px; font-size: 13px; font-weight: 600; background: #f8d7da; color: #721c24;">

                                                        Cancelled

                                                    </span>

                                                    <div style="margin-top: 8px; font-size: 13px; color: #666;">Your Order has been Cancelled</div>

                                                <?php elseif ($overall_status == 'delivered'): ?>

                                                    <span style="display: inline-block; padding: 8px 16px; border-radius: 20px; font-size: 13px; font-weight: 600; background: #d4edda; color: #155724;">

                                                        Delivered

                                                    </span>

                                                    <div style="margin-top: 8px; font-size: 13px; color: #666;">Your Order has been Delivered</div>

                                                <?php else: ?>

                                                    <span style="display: inline-block; padding: 8px 16px; border-radius: 20px; font-size: 13px; font-weight: 600; background: #fff3cd; color: #856404;">

                                                        Accepted

                                                    </span>

                                                    <div style="margin-top: 8px; font-size: 13px; color: #666;">Your Order has been Accepted</div>

                                                <?php endif; ?>

                                            </div>

                                            <div style="display: flex; gap: 10px;">

                                                <?php if ($overall_status == 'delivered'): ?>

                                                    <a href="write_review.php?order_id=<?php echo $order['id']; ?>" style="background: #2d5016; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 13px; transition: background 0.3s; text-decoration: none; display: inline-flex; align-items: center; gap: 5px;" onmouseover="this.style.background='#1e3009'" onmouseout="this.style.background='#2d5016'">

                                                        <i class="fas fa-star"></i> Add Review

                                                    </a>

                                                <?php else: ?>

                                                    <a href="track_order.php?order_id=<?php echo $order['id']; ?>&email=<?php echo urlencode($user['email'] ?? ''); ?>" style="background: #2d5016; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 13px; transition: background 0.3s; text-decoration: none; display: inline-flex; align-items: center; gap: 5px;" onmouseover="this.style.background='#1e3009'" onmouseout="this.style.background='#2d5016'">

                                                        <i class="fas fa-truck"></i> Track Order

                                                    </a>

                                                <?php endif; ?>

                                                <a href="invoice.php?order_id=<?php echo $order['id']; ?>" target="_blank" style="background: white; color: #333; border: 1px solid #ddd; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 13px; transition: all 0.3s; text-decoration: none; display: inline-flex; align-items: center; gap: 5px;" onmouseover="this.style.background='#f8f9fa'" onmouseout="this.style.background='white'">

                                                    <i class="fas fa-file-invoice"></i> Invoice

                                                </a>

                                                <?php 

                                                // Check if order can be cancelled

                                                $can_cancel = ($order['status'] == 'pending' || $overall_status == 'pending');

                                                if ($can_cancel && !empty($order['items'])) {

                                                    foreach ($order['items'] as $item) {

                                                        if (in_array($item['seller_status'], ['processing', 'shipped', 'delivered'])) {

                                                            $can_cancel = false;

                                                            break;

                                                        }

                                                    }

                                                }

                                                ?>

                                                <?php if ($can_cancel): ?>

                                                    <form method="POST" action="" style="display: inline;">

                                                        <input type="hidden" name="action" value="cancel_order">

                                                        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">

                                                        <button type="submit" onclick="return confirm('Are you sure you want to cancel this order?');" style="background: #dc3545; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 13px; transition: background 0.3s;" onmouseover="this.style.background='#c82333'" onmouseout="this.style.background='#dc3545'">

                                                            Cancel Order

                                                        </button>

                                                    </form>

                                                <?php endif; ?>

                                            </div>

                                        </div>

                                    </div>

                                </div>

                                    <?php endforeach; ?>

                                </div>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <div style="text-align: center; padding: 60px 20px; background: white; border-radius: 12px;">

                                <i class="fas fa-inbox" style="font-size: 60px; color: #ddd; display: block; margin-bottom: 20px;"></i>

                                <h2 style="color: #999; margin-bottom: 10px;">No orders yet</h2>

                                <p style="color: #ccc; margin-bottom: 20px;">Start shopping to place your first order</p>

                                <a href="shop.php" class="btn-primary">

                                    <i class="fas fa-shopping-cart"></i> Shop Now

                                </a>

                            </div>

                        <?php endif; ?>

                    </section>



                    <!-- Addresses Section -->

                    <section class="account-section" id="addresses-section" style="display: none;">

                        <h3 class="section-title"><i class="fas fa-map-marker-alt"></i> Manage Addresses</h3>

                        

                        <form method="POST" action="">

                            <input type="hidden" name="action" value="update_address">

                            

                            <div class="form-row full">

                                <div class="form-group">

                                    <label>Street Address <span class="required">*</span></label>

                                    <input type="text" name="street" value="<?php echo htmlspecialchars($user['purok_street'] ?? ''); ?>" required>

                                </div>

                            </div>



                            <div class="form-row">

                                <div class="form-group">

                                    <label>Barangay/District <span class="required">*</span></label>

                                    <input type="text" name="barangay" value="<?php echo htmlspecialchars($user['barangay'] ?? ''); ?>" required>

                                </div>

                                <div class="form-group">

                                    <label>City/Municipality <span class="required">*</span></label>

                                    <input type="text" name="city" value="<?php echo htmlspecialchars($user['municipality_city'] ?? ''); ?>" required>

                                </div>

                            </div>



                            <div class="form-row">

                                <div class="form-group">

                                    <label>Province <span class="required">*</span></label>

                                    <input type="text" name="province" value="<?php echo htmlspecialchars($user['province'] ?? ''); ?>" required>

                                </div>

                                <div class="form-group">

                                    <label>Country <span class="required">*</span></label>

                                    <input type="text" name="country" value="<?php echo htmlspecialchars($user['country'] ?? ''); ?>" required>

                                </div>

                            </div>



                            <div class="form-row">

                                <div class="form-group">

                                    <label>ZIP Code <span class="required">*</span></label>

                                    <input type="text" name="zip_code" value="<?php echo htmlspecialchars($user['zip_code'] ?? ''); ?>" required maxlength="10" pattern="[0-9]{4,10}" title="Please enter a valid ZIP code">

                                </div>

                            </div>



                            <div class="form-actions">

                                <button type="submit" class="btn-primary">

                                    <i class="fas fa-save"></i> Update Address

                                </button>

                                <button type="reset" class="btn-secondary">

                                    <i class="fas fa-redo"></i> Reset

                                </button>

                            </div>

                        </form>

                    </section>


                    <!-- Security Section -->

                    <section class="account-section" id="security-section" style="display: none;">

                        <h3 class="section-title"><i class="fas fa-lock"></i> Password Manager</h3>

                        

                        <form method="POST" action="">

                            <input type="hidden" name="action" value="change_password">

                            

                            <div class="form-row full">

                                <div class="form-group">

                                    <label>Current Password <span class="required">*</span></label>

                                    <input type="password" name="current_password" required>

                                </div>

                            </div>



                            <div class="form-row">

                                <div class="form-group">

                                    <label>New Password <span class="required">*</span></label>

                                    <input type="password" name="new_password" required>

                                </div>

                                <div class="form-group">

                                    <label>Confirm Password <span class="required">*</span></label>

                                    <input type="password" name="confirm_password" required>

                                </div>

                            </div>



                            <div class="form-actions">

                                <button type="submit" class="btn-primary">

                                    <i class="fas fa-key"></i> Update Password

                                </button>

                            </div>

                        </form>

                    </section>



                    <!-- Seller Account Section -->

                    <section class="account-section" id="seller-section" style="display: none;">

                        <h3 class="section-title"><i class="fas fa-store"></i> Seller Account</h3>

                        

                        <?php if (($user['is_seller'] ?? 0) == 1): ?>

                            <!-- Seller Features -->

                            <div class="seller-features">

                                <div class="info-box">

                                    <h4><i class="fas fa-check-circle" style="color: #28a745;"></i> Seller Account Active</h4>

                                    <p>Your seller account is active! You can now access all seller features.</p>

                                </div>

                                

                                <div class="seller-dashboard">

                                    <h4>Seller Dashboard</h4>

                                    <div class="seller-actions">

                                        <a href="upload_product.php" class="btn-primary">

                                            <i class="fas fa-plus"></i> Upload New Product

                                        </a>

                                        <a href="manage_products.php" class="btn-secondary">

                                            <i class="fas fa-box"></i> Manage Products

                                        </a>

                                        <a href="seller_orders.php" class="btn-secondary">

                                            <i class="fas fa-shopping-cart"></i> View Orders

                                        </a>

                                        <!-- Remove Seller Form -->
<form method="POST" id="removeSellerForm" style="display:inline;">

    <input type="hidden" name="action" value="remove_seller_status">

    <button type="submit"
        class="btn-danger"
        style="background:#dc3545;
               color:white;
               border:none;
               padding:15px 35px;
               border-radius:5px;
               cursor:pointer;
               font-weight:600;
               transition:0.3s;
               text-decoration:none;
               display:inline-block;">

        <i class="fas fa-user-times"></i> Remove Seller Status

    </button>

</form>

<!-- Remove Seller Modal -->
<div id="removeSellerModal" class="custom-modal">

    <div class="custom-modal-content">

        <div class="modal-icon remove-icon">
            <i class="fas fa-user-times"></i>
        </div>

        <h2>Remove Seller Status</h2>

        <p>
            Are you sure you want to remove your seller account?
        </p>

        <div class="modal-buttons">

            <button type="button"
                    class="cancel-btn"
                    onclick="closeRemoveSellerModal()">

                Cancel

            </button>

            <button type="button"
                    class="remove-btn"
                    onclick="submitRemoveSellerForm()">

                Yes, Remove

            </button>

        </div>

    </div>

</div>

                                    </div>

                                </div>

                                

                                <div class="seller-stats">

                                    <h4>Quick Stats</h4>

                                    <div class="stats-grid">

                                        <div class="stat-card">

                                            <i class="fas fa-box"></i>

                                            <span>Total Products</span>

                                            <strong><?php echo $seller_stats['total_products']; ?></strong>

                                        </div>

                                        <div class="stat-card">

                                            <i class="fas fa-shopping-cart"></i>

                                            <span>Total Orders</span>

                                            <strong><?php echo $seller_stats['total_orders']; ?></strong>

                                        </div>

                                        <div class="stat-card">

                                            <i class="fas fa-peso-sign"></i>

                                            <span>Total Revenue</span>

                                            <strong>₱<?php echo number_format($seller_stats['total_revenue'], 2); ?></strong>

                                        </div>

                                        <div class="stat-card">

                                            <i class="fas fa-star"></i>

                                            <span>Avg Rating</span>

                                            <strong><?php echo number_format($seller_stats['avg_rating'], 1); ?></strong>

                                        </div>

                                    </div>

                                </div>

                            </div>

                        <?php else: ?>

                            <!-- Not a seller yet -->

                            <div class="seller-request">

                                <div class="info-box">

                                    <h4><i class="fas fa-store"></i> Become a Seller</h4>

                                    <p>Start selling your products on ReBuy and reach thousands of customers. As a seller, you'll get access to:</p>

                                    <ul>

                                        <li><i class="fas fa-check" style="color: #28a745;"></i> Upload and manage products</li>

                                        <li><i class="fas fa-check" style="color: #28a745;"></i> Access seller dashboard</li>

                                        <li><i class="fas fa-check" style="color: #28a745;"></i> Track orders and sales</li>

                                        <li><i class="fas fa-check" style="color: #28a745;"></i> View analytics and reports</li>

                                        <li><i class="fas fa-check" style="color: #28a745;"></i> Manage customer reviews</li>

                                    </ul>

                                </div>

                                

                                <!-- Seller Enable Form -->
<form method="POST" action="" id="sellerForm">

    <input type="hidden" name="action" value="request_seller">

    <div class="form-actions">

        <button type="submit" class="btn-primary">

            <i class="fas fa-store"></i> Enable Seller Account

        </button>

    </div>

</form>

<!-- Custom Modal -->
<div id="sellerModal" class="custom-modal">

    <div class="custom-modal-content">

        <div class="modal-icon">
            <i class="fas fa-store"></i>
        </div>

        <h2>Enable Seller Account</h2>

        <p>
            Are you sure you want to become a seller?<br>
            You can access seller features immediately.
        </p>

        <div class="modal-buttons">

            <button type="button" class="cancel-btn" onclick="closeSellerModal()">
                Cancel
            </button>

            <button type="button" class="confirm-btn" onclick="submitSellerForm()">
                Yes, Enable
            </button>

        </div>

    </div>

</div>

                            </div>

                        <?php endif; ?>

                    </section>

                </main>

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

        const removeSellerForm = document.getElementById('removeSellerForm');

removeSellerForm.addEventListener('submit', function(e){
    e.preventDefault();

    document.getElementById('removeSellerModal').style.display = 'flex';
});

function closeRemoveSellerModal(){
    document.getElementById('removeSellerModal').style.display = 'none';
}

function submitRemoveSellerForm(){
    removeSellerForm.submit();
}

window.addEventListener('click', function(e){

    const modal = document.getElementById('removeSellerModal');

    if(e.target === modal){
        closeRemoveSellerModal();
    }

});

        const sellerForm = document.getElementById('sellerForm');

sellerForm.addEventListener('submit', function(e){
    e.preventDefault();
    document.getElementById('sellerModal').style.display = 'flex';
});

function closeSellerModal(){
    document.getElementById('sellerModal').style.display = 'none';
}

function submitSellerForm(){
    sellerForm.submit();
}

window.addEventListener('click', function(e){
    const modal = document.getElementById('sellerModal');

    if(e.target === modal){
        closeSellerModal();
    }
});

        // Section switching functionality

        function showSection(sectionName) {

            // Hide all sections

            const sections = document.querySelectorAll('.account-section');

            sections.forEach(section => {

                section.style.display = 'none';

            });

            

            // Remove active class from all menu items

            const menuItems = document.querySelectorAll('.account-menu a');

            menuItems.forEach(item => {

                item.classList.remove('active');

            });

            

            // Show selected section

            const targetSection = document.getElementById(sectionName + '-section');

            if (targetSection) {

                targetSection.style.display = 'block';

            }

            

            // Add active class to clicked menu item

            const targetMenuItem = document.querySelector(`.account-menu a[href="#${sectionName}"]`);

            if (targetMenuItem) {

                targetMenuItem.classList.add('active');

            }

        }

        

        // Initialize with personal section visible

        document.addEventListener('DOMContentLoaded', function() {

            showSection('personal');

        });

        

        // Toggle add payment form

        function toggleAddPaymentForm() {

            const form = document.getElementById('add-payment-form');

            if (form.style.display === 'none') {

                form.style.display = 'block';

            } else {

                form.style.display = 'none';

            }

        }

        

        // Format card number input

        document.addEventListener('DOMContentLoaded', function() {

            const cardNumberInput = document.querySelector('input[name="card_number"]');

            if (cardNumberInput) {

                cardNumberInput.addEventListener('input', function(e) {

                    let value = e.target.value.replace(/\s/g, '');

                    let formattedValue = value.match(/.{1,4}/g)?.join(' ') || value;

                    e.target.value = formattedValue;

                });

            }

        });

        

        // Toggle payment type fields

        function togglePaymentFields() {

            const paymentType = document.getElementById('payment_type').value;

            const cardFields = document.getElementById('card-fields');

            const ewalletFields = document.getElementById('ewallet-fields');

            const codFields = document.getElementById('cod-fields');

            

            // Hide all fields

            cardFields.style.display = 'none';

            ewalletFields.style.display = 'none';

            codFields.style.display = 'none';

            

            // Show relevant fields based on payment type

            switch(paymentType) {

                case 'card':

                    cardFields.style.display = 'block';

                    break;

                case 'ewallet':

                    ewalletFields.style.display = 'block';

                    break;

                case 'cod':

                    codFields.style.display = 'block';

                    break;

            }

        }

        

        // Order management functions

        function viewOrderDetails(orderId) {

            // Create a modal to show order details

            const modal = document.createElement('div');

            modal.style.cssText = `

                position: fixed;

                top: 0;

                left: 0;

                width: 100%;

                height: 100%;

                background: rgba(0,0,0,0.5);

                display: flex;

                align-items: center;

                justify-content: center;

                z-index: 1000;

            `;

            

            modal.innerHTML = `

                <div style="background: white; padding: 30px; border-radius: 8px; max-width: 500px; width: 90%; max-height: 80vh; overflow-y: auto;">

                    <h3 style="margin-bottom: 20px; color: #2d5016;">Order Details</h3>

                    <p style="color: #666; margin-bottom: 20px;">Order #${String(orderId).padStart(6, '0')}</p>

                    <p style="color: #666; margin-bottom: 20px;">Detailed order information will be available here.</p>

                    <button onclick="this.closest('div').parentElement.remove()" style="background: #2d5016; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer;">Close</button>

                </div>

            `;

            

            document.body.appendChild(modal);

            modal.addEventListener('click', function(e) {

                if (e.target === modal) {

                    modal.remove();

                }

            });

        }

        

        function cancelOrder(orderId) {

            if (confirm('Are you sure you want to cancel this order? This action cannot be undone.')) {

                // Create form to cancel order

                const form = document.createElement('form');

                form.method = 'POST';

                form.style.display = 'none';

                

                const actionInput = document.createElement('input');

                actionInput.type = 'hidden';

                actionInput.name = 'action';

                actionInput.value = 'cancel_order';

                

                const orderIdInput = document.createElement('input');

                orderIdInput.type = 'hidden';

                orderIdInput.name = 'order_id';

                orderIdInput.value = orderId;

                

                form.appendChild(actionInput);

                form.appendChild(orderIdInput);

                document.body.appendChild(form);

                form.submit();

            }

        }

    </script>

</body>

</html>

