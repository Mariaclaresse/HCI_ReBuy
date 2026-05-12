<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$error = "";
$success = "";

// Check if this is a Buy Now request
$buy_now_product_id = $_GET['product_id'] ?? 0;
$buy_now_quantity = $_GET['quantity'] ?? 1;

if ($buy_now_product_id > 0) {
    // For Buy Now, add product to cart temporarily for checkout
    $stmt = $conn->prepare("SELECT id, name, price, stock_quantity, seller_id FROM products WHERE id = ? AND status = 'active'");
    $stmt->bind_param("i", $buy_now_product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $product = $result->fetch_assoc();
    $stmt->close();

    if (!$product) {
        header("Location: shop.php");
        exit();
    }

    if ($product['stock_quantity'] < $buy_now_quantity) {
        $error = "Not enough stock available";
    } else {
        // Clear cart first
        $clear_stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
        $clear_stmt->bind_param("i", $user_id);
        $clear_stmt->execute();
        $clear_stmt->close();

        // Add product to cart
        $add_stmt = $conn->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)");
        $add_stmt->bind_param("iii", $user_id, $buy_now_product_id, $buy_now_quantity);
        $add_stmt->execute();
        $add_stmt->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $shipping_address = $_POST['shipping_address'] ?? '';
    $payment_type = $_POST['payment_type'] ?? '';
    $payment_method = $_POST['payment_method'] ?? '';

    // Validate based on payment type
    $validation_error = '';
    if ($payment_type == 'card') {
        $card_type = $_POST['card_type'] ?? '';
        $card_number = $_POST['card_number'] ?? '';
        $cardholder_name = $_POST['cardholder_name'] ?? '';
        $expiry_month = $_POST['expiry_month'] ?? '';
        $expiry_year = $_POST['expiry_year'] ?? '';
        $cvv = $_POST['cvv'] ?? '';
        
        if (empty($card_type) || empty($card_number) || empty($cardholder_name) || 
            empty($expiry_month) || empty($expiry_year) || empty($cvv)) {
            $validation_error = "All card fields are required";
        }
        // Validate card number (basic validation)
        elseif (!preg_match('/^\d{13,19}$/', str_replace(' ', '', $card_number))) {
            $validation_error = "Invalid card number. Please enter a valid card number.";
        }
        // Validate CVV
        elseif (!preg_match('/^\d{3,4}$/', $cvv)) {
            $validation_error = "Invalid CVV. Please enter a valid CVV.";
        }
        // Validate expiry date
        elseif ($expiry_month < 1 || $expiry_month > 12 || $expiry_year < date('Y') || 
                ($expiry_year == date('Y') && $expiry_month < date('m'))) {
            $validation_error = "Invalid expiry date. Please enter a valid future date.";
        }
    }
    elseif ($payment_type == 'ewallet') {
        $ewallet_provider = $_POST['ewallet_provider'] ?? '';
        $ewallet_number = $_POST['ewallet_number'] ?? '';
        
        if (empty($ewallet_provider) || empty($ewallet_number)) {
            $validation_error = "All e-wallet fields are required";
        }
        // Validate e-wallet number (basic validation for mobile number)
        elseif (!preg_match('/^\d{10,11}$/', $ewallet_number)) {
            $validation_error = "Invalid mobile number. Please enter a valid 10-11 digit mobile number.";
        }
    }
    elseif ($payment_type == 'cod') {
        // COD doesn't require additional validation
    }
    else {
        $validation_error = "Please select a payment method";
    }

    if (empty($shipping_address) || !empty($validation_error)) {
        $error = !empty($validation_error) ? $validation_error : "All fields are required";
    } else {
        // Get cart total
        $stmt = $conn->prepare("
            SELECT SUM(c.quantity * p.price) as total 
            FROM cart c 
            JOIN products p ON c.product_id = p.id 
            WHERE c.user_id = ?
        ");
        if ($stmt === false) {
            $error = "Database error: " . $conn->error;
        } else {
            $stmt->bind_param("i", $user_id);
            if (!$stmt->execute()) {
                $error = "Error calculating cart total: " . $stmt->error;
            } else {
                $total_result = $stmt->get_result()->fetch_assoc();
                $total_amount = $total_result['total'] ?? 0;
                $stmt->close();
            }
        }

        if ($total_amount <= 0) {
            $error = "Cart is empty";
        } else {
            // Create order
            $stmt = $conn->prepare("
                INSERT INTO orders (user_id, total_amount, shipping_address, payment_method, status) 
                VALUES (?, ?, ?, ?, 'pending')
            ");
            $stmt->bind_param("idss", $user_id, $total_amount, $shipping_address, $payment_method);
            
            if ($stmt->execute()) {
                $order_id = $stmt->insert_id;
                $stmt->close();

                // Move cart items to order_items and create seller_orders
                $stmt = $conn->prepare("
                    INSERT INTO order_items (order_id, product_id, quantity, price)
                    SELECT ?, product_id, quantity, (SELECT price FROM products WHERE id = product_id)
                    FROM cart WHERE user_id = ?
                ");
                $stmt->bind_param("ii", $order_id, $user_id);
                $stmt->execute();
                $stmt->close();

                // Create seller_orders entries for each product
                $cart_items_stmt = $conn->prepare("
                    SELECT c.product_id, c.quantity, p.price, p.seller_id
                    FROM cart c
                    JOIN products p ON c.product_id = p.id
                    WHERE c.user_id = ?
                ");
                $cart_items_stmt->bind_param("i", $user_id);
                $cart_items_stmt->execute();
                $cart_items_result = $cart_items_stmt->get_result();

                while ($cart_item = $cart_items_result->fetch_assoc()) {
                    $seller_order_id = 'ORD' . time() . rand(1000, 9999);
                    $price_per_item = $cart_item['price'];
                    $quantity = $cart_item['quantity'];
                    $total_amount = $price_per_item * $quantity;
                    $seller_id = $cart_item['seller_id'];
                    $product_id = $cart_item['product_id'];

                    $seller_order_stmt = $conn->prepare("
                        INSERT INTO seller_orders (order_id, seller_id, customer_id, product_id, quantity, price_per_item, total_amount, status, main_order_id)
                        VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?)
                    ");
                    $seller_order_stmt->bind_param("siiidddi", $seller_order_id, $seller_id, $user_id, $product_id, $quantity, $price_per_item, $total_amount, $order_id);
                    $seller_order_stmt->execute();
                    $seller_order_stmt->close();

                    // Reduce product stock
                    $stock_stmt = $conn->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?");
                    $stock_stmt->bind_param("ii", $quantity, $product_id);
                    $stock_stmt->execute();
                    $stock_stmt->close();
                }
                $cart_items_stmt->close();

                // Clear cart
                $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $stmt->close();

                $_SESSION['success'] = "Order placed successfully!";
                header("Location: orders.php");
                exit();
            } else {
                $error = "Error creating order: " . $stmt->error;
                $stmt->close();
            }
        }
    }
}

// Get user address info
$stmt = $conn->prepare("
    SELECT purok_street, barangay, municipality_city, province, country, zip_code 
    FROM users WHERE id = ?
");
if ($stmt === false) {
    die("Error preparing user address query: " . $conn->error);
}
$stmt->bind_param("i", $user_id);
if (!$stmt->execute()) {
    die("Error executing user address query: " . $stmt->error);
}
$user_address = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get cart items and total
$stmt = $conn->prepare("
    SELECT c.id as cart_id, c.product_id, c.quantity, p.name, p.price, p.image_url
    FROM cart c 
    JOIN products p ON c.product_id = p.id 
    WHERE c.user_id = ?
");
if ($stmt === false) {
    die("Error preparing cart query: " . $conn->error);
}
$stmt->bind_param("i", $user_id);
if (!$stmt->execute()) {
    die("Error executing cart query: " . $stmt->error);
}
$result = $stmt->get_result();
$cart_items = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Calculate cart totals
$total_amount = 0;
$item_count = 0;
foreach ($cart_items as $item) {
    $total_amount += $item['quantity'] * $item['price'];
    $item_count += $item['quantity'];
}

$cart_info = [
    'total' => $total_amount,
    'item_count' => $item_count
];
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
    <style>
    *{
        box-sizing: border-box;
    }

    body{
        background: #fcfbf9;
        font-family: 'Segoe UI', sans-serif;
        color: #222;
    }

    .cart-section{
    max-width: 1250px;
    margin: 0 auto;
    padding: 40px 20px;
}

.cart-container{
    width: 100%;
    display: flex;
    justify-content: center;
}

.checkout-layout{
    width: 100%;
    max-width: 1500px;
    margin: 0 auto;
}

    .cart-section h1{
        font-size: 38px;
        margin-bottom: 25px;
        font-weight: 700;
        color: #1f2937;
    }

    .checkout-layout{
        display: flex;
        gap: 25px;
        align-items: flex-start;
    }

    .cart-items-column{
        flex: 1;
    }

    .order-summary-column{
        width: 380px;
        position: sticky;
        top: 20px;
    }

    .checkout-section{
        background: rgba(255,255,255,0.85);
        backdrop-filter: blur(15px);
        border: 1px solid rgba(255,255,255,0.3);
        border-radius: 24px;
        overflow: hidden;
        margin-bottom: 25px;
        box-shadow:
            0 10px 30px rgba(0,0,0,0.05),
            0 2px 10px rgba(0,0,0,0.03);
        transition: 0.3s ease;
    }

    .checkout-section:hover{
        transform: translateY(-2px);
    }

    .section-header{
        padding: 22px 28px;
        border-bottom: 1px solid #ececec;
        background: linear-gradient(
            to right,
            rgba(255,255,255,0.8),
            rgba(248,250,252,0.8)
        );
    }

    .section-header h3{
        margin: 0;
        font-size: 18px;
        display: flex;
        align-items: center;
        gap: 12px;
        color: #111827;
        font-weight: 700;
    }

    .section-header i{
        width: 38px;
        height: 38px;
        border-radius: 12px;
        background: #2d5016;
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
    }

    .section-content{
        padding: 28px;
    }

    .cart-table{
        width: 100%;
        border-collapse: collapse;
    }

    .cart-table th{
        background: #f8fafc;
        color: #374151;
        padding: 16px;
        font-size: 14px;
        font-weight: 700;
        border-bottom: 1px solid #e5e7eb;
    }

    .cart-table td{
        padding: 18px 16px;
        border-bottom: 1px solid #f1f5f9;
        vertical-align: middle;
        font-size: 14px;
    }

    .product-name{
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .product-name img{
        width: 75px !important;
        height: 75px !important;
        border-radius: 18px !important;
        object-fit: cover;
        border: 2px solid #f1f5f9;
    }

    .product-name span{
        font-weight: 600;
        color: #1f2937;
    }

    .form-group{
        margin-bottom: 22px;
    }

    .form-group label{
        display: block;
        margin-bottom: 8px;
        font-size: 14px;
        font-weight: 600;
        color: #374151;
    }

    .form-group input,
    .form-group select,
    .form-group textarea{
        width: 100%;
        padding: 15px 16px;
        border-radius: 16px;
        border: 1px solid #dbe3ec;
        background: #fff;
        font-size: 14px;
        transition: all 0.3s ease;
    }

    .form-group textarea{
        min-height: 120px;
        resize: vertical;
    }

    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus{
        outline: none;
        border-color: #2d5016;
        box-shadow: 0 0 0 5px rgba(45,80,22,0.08);
    }

    #card-fields,
    #ewallet-fields,
    #cod-info{
        margin-top: 20px;
        padding: 25px;
        border-radius: 20px;
        background: #f8fafc;
        border: 1px solid #e5e7eb;
    }

    #cod-info{
        background: linear-gradient(
            135deg,
            #e8f5e9,
            #f0fff4
        );
        border: 1px solid #b7e4c7;
    }

    .cart-summary{
        background: rgba(255,255,255,0.9);
        backdrop-filter: blur(15px);
        border-radius: 28px;
        padding: 30px;
        border: 1px solid rgba(255,255,255,0.4);
        box-shadow:
            0 10px 35px rgba(0,0,0,0.06),
            0 2px 10px rgba(0,0,0,0.03);
    }

    .cart-summary h3{
        margin-bottom: 25px;
        font-size: 24px;
        color: #111827;
    }

    .summary-row{
        display: flex;
        justify-content: space-between;
        margin-bottom: 18px;
        font-size: 15px;
        color: #4b5563;
    }

    .summary-row.total{
        margin-top: 20px;
        padding-top: 20px;
        border-top: 2px dashed #d1d5db;
        font-size: 20px;
        font-weight: 700;
        color: #111827;
    }

    .btn-checkout {
         width: 100%;
        display: block;
        text-align: center;
        margin-top: 14px;
        padding: 15px;
        border-radius: 18px;
         text-decoration: none;
        font-weight: 600;
    transition: transform 0.2s ease, box-shadow 0.2s ease, filter 0.2s ease;
}

.btn-checkout:hover {
    transform: translateY(-2px);
    filter: brightness(1.05);
    box-shadow: 0 12px 22px rgba(45, 80, 22, 0.25);
}

.btn-checkout:active {
    transform: translateY(0);
}

    .btn-checkout:hover{
        transform: translateY(-3px);
        box-shadow: 0 15px 25px rgba(45,80,22,0.25);
    }

    .btn-continue-shopping {
         width: 100%;
        display: block;
        text-align: center;
        margin-top: 14px;
        padding: 14px;
        border-radius: 18px;
         text-decoration: none;
        font-weight: 600;
    transition: transform 0.2s ease, box-shadow 0.2s ease, filter 0.2s ease;
}

.btn-continue-shopping:hover {
    transform: translateY(-2px);
    filter: brightness(1.05);
    box-shadow: 0 12px 22px rgba(45, 80, 22, 0.25);
}

.btn-continue-shopping:active {
    transform: translateY(0);
}

    .btn-continue-shopping:hover{
        transform: translateY(-3px);
        box-shadow: 0 15px 25px rgba(45,80,22,0.25);
    }

    .btn-continue-shopping:hover{
        background: #132711;
        color: white;
        border-color: #132711;
    }
    

    .alert{
        padding: 16px 20px;
        border-radius: 16px;
        margin-bottom: 25px;
        font-weight: 500;
    }

    .alert-error{
        background: #fee2e2;
        color: #b91c1c;
        border: 1px solid #fecaca;
    }

    @media(max-width: 1100px){

        .checkout-layout{
            flex-direction: column;
        }

        .order-summary-column{
            width: 100%;
            position: static;
        }
    }

    @media(max-width: 768px){

        .cart-section h1{
            font-size: 28px;
        }

        .section-content{
            padding: 20px;
        }

        .cart-table{
            display: block;
            overflow-x: auto;
        }

        .product-name{
            min-width: 220px;
        }

        #card-fields > div,
        #ewallet-fields > div{
            grid-template-columns: 1fr !important;
        }
    }
</style>
</head>
<body>

   <?php include '_header.php'; ?>

    <div class="page-container">

            <section class="cart-section">
                <h1>Checkout</h1>
                <?php if (!empty($error)): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <div class="cart-container">
                    <form method="POST">
                        <div class="checkout-layout">
                            <div class="cart-items-column">
                            <!-- Cart Items Container -->
                            <div class="checkout-section">
                                <div class="section-header">
                                    <h3><i class="fas fa-shopping-cart"></i> Order Items</h3>
                                </div>
                                <div class="section-content">
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
                                            <?php if (!empty($cart_items)): ?>
                                                <?php foreach ($cart_items as $item): ?>
                                                    <tr>
                                                        <td class="product-name">
                                                            <img src="<?php echo !empty($item['image_url']) ? '../' . htmlspecialchars($item['image_url']) : 'https://images.unsplash.com/photo-1586023492125-27b2c045efd7?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=400&q=80'; ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" style="width: 60px; height: 60px; object-fit: cover; border-radius: 4px;">
                                                            <span><?php echo htmlspecialchars($item['name']); ?></span>
                                                        </td>
                                                        <td>$<?php echo number_format($item['price'], 2); ?></td>
                                                        <td><?php echo $item['quantity']; ?></td>
                                                        <td>$<?php echo number_format($item['quantity'] * $item['price'], 2); ?></td>
                                                        <td>
                                                            <span style="color: #666; font-size: 13px;">Processing</span>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="5" style="text-align: center; padding: 40px; color: #999;">
                                                        <i class="fas fa-shopping-cart" style="font-size: 40px; display: block; margin-bottom: 15px;"></i>
                                                        Your cart is empty
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Shipping Information Container -->
                            <div class="checkout-section">
                                <div class="section-header">
                                    <h3><i class="fas fa-truck"></i> Shipping Information</h3>
                                </div>
                                <div class="section-content">
                                <div class="form-group" style="margin-bottom: 15px;">
                                    <label style="display: block; margin-bottom: 5px; font-size: 13px; color: #666;">Delivery Address *</label>
                                    <textarea name="shipping_address" required style="width: 100%; padding: 10px; border: 1px solid #e0e0e0; border-radius: 4px; min-height: 80px; resize: vertical;" placeholder="Enter your complete delivery address"><?php 
$address = '';
if ($user_address) {
    $address = $user_address['purok_street'] . ', ' . $user_address['barangay'] . ', ' . 
               $user_address['municipality_city'] . ', ' . $user_address['province'] . ', ' . 
               $user_address['country'] . ' ' . $user_address['zip_code'];
}
echo htmlspecialchars($address);
?></textarea>
                                </div>
                                </div>
                            </div>

                            <!-- Payment Method Container -->
                            <div class="checkout-section">
                                <div class="section-header">
                                    <h3><i class="fas fa-credit-card"></i> Payment Method</h3>
                                </div>
                                <div class="section-content">
                                <div class="form-group" style="margin-bottom: 15px;">
                                    <label style="display: block; margin-bottom: 5px; font-size: 13px; color: #666;">Payment Method *</label>
                                    <select name="payment_type" id="payment_type" required onchange="togglePaymentFields()" style="width: 100%; padding: 10px; border: 1px solid #e0e0e0; border-radius: 4px;">
                                        <option value="">Select Payment Type</option>
                                        <option value="card">Credit/Debit Card</option>
                                        <option value="ewallet">E-Wallet</option>
                                        <option value="cod">Cash on Delivery</option>
                                    </select>
                                </div>

                                <!-- Credit/Debit Card Fields -->
                                <div id="card-fields" style="display: none; margin-bottom: 15px;">
                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                                        <div>
                                            <label style="display: block; margin-bottom: 5px; font-size: 13px; color: #666;">Card Type *</label>
                                            <select name="card_type" required style="width: 100%; padding: 10px; border: 1px solid #e0e0e0; border-radius: 4px;">
                                                <option value="">Select Card Type</option>
                                                <option value="Visa">Visa</option>
                                                <option value="Mastercard">Mastercard</option>
                                                <option value="American Express">American Express</option>
                                                <option value="Discover">Discover</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label style="display: block; margin-bottom: 5px; font-size: 13px; color: #666;">Card Number *</label>
                                            <input type="text" name="card_number" placeholder="1234 5678 9012 3456" maxlength="19" pattern="[0-9]{13,19}" title="Please enter a valid card number" required style="width: 100%; padding: 10px; border: 1px solid #e0e0e0; border-radius: 4px;">
                                        </div>
                                    </div>

                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                                        <div>
                                            <label style="display: block; margin-bottom: 5px; font-size: 13px; color: #666;">Cardholder Name *</label>
                                            <input type="text" name="cardholder_name" placeholder="John Doe" required style="width: 100%; padding: 10px; border: 1px solid #e0e0e0; border-radius: 4px;">
                                        </div>
                                        <div>
                                            <label style="display: block; margin-bottom: 5px; font-size: 13px; color: #666;">CVV *</label>
                                            <input type="text" name="cvv" placeholder="123" maxlength="4" pattern="[0-9]{3,4}" title="Please enter a valid CVV" required style="width: 100%; padding: 10px; border: 1px solid #e0e0e0; border-radius: 4px;">
                                        </div>
                                    </div>

                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                        <div>
                                            <label style="display: block; margin-bottom: 5px; font-size: 13px; color: #666;">Expiry Month *</label>
                                            <select name="expiry_month" required style="width: 100%; padding: 10px; border: 1px solid #e0e0e0; border-radius: 4px;">
                                                <option value="">Month</option>
                                                <?php for($m = 1; $m <= 12; $m++): ?>
                                                    <option value="<?php echo $m; ?>"><?php echo str_pad($m, 2, '0', STR_PAD_LEFT); ?></option>
                                                <?php endfor; ?>
                                            </select>
                                        </div>
                                        <div>
                                            <label style="display: block; margin-bottom: 5px; font-size: 13px; color: #666;">Expiry Year *</label>
                                            <select name="expiry_year" required style="width: 100%; padding: 10px; border: 1px solid #e0e0e0; border-radius: 4px;">
                                                <option value="">Year</option>
                                                <?php for($y = date('Y'); $y <= date('Y') + 10; $y++): ?>
                                                    <option value="<?php echo $y; ?>"><?php echo $y; ?></option>
                                                <?php endfor; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <!-- E-Wallet Fields -->
                                <div id="ewallet-fields" style="display: none; margin-bottom: 15px;">
                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                        <div>
                                            <label style="display: block; margin-bottom: 5px; font-size: 13px; color: #666;">E-Wallet Provider *</label>
                                            <select name="ewallet_provider" style="width: 100%; padding: 10px; border: 1px solid #e0e0e0; border-radius: 4px;">
                                                <option value="">Select Provider</option>
                                                <option value="GCash">GCash</option>
                                                <option value="PayMaya">PayMaya</option>
                                                <option value="PayPal">PayPal</option>
                                                <option value="Coins.ph">Coins.ph</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label style="display: block; margin-bottom: 5px; font-size: 13px; color: #666;">Mobile Number *</label>
                                            <input type="text" name="ewallet_number" placeholder="09123456789" maxlength="11" pattern="[0-9]{10,11}" title="Please enter a valid mobile number" style="width: 100%; padding: 10px; border: 1px solid #e0e0e0; border-radius: 4px;">
                                        </div>
                                    </div>
                                </div>

                                <!-- Cash on Delivery Info -->
                                <div id="cod-info" style="display: none; margin-bottom: 15px;">
                                    <div style="padding: 15px; background: #e8f5e9; border: 1px solid #c8e6c9; border-radius: 4px;">
                                        <h4 style="margin: 0 0 10px 0; color: #2d5016; font-size: 14px;">
                                            <i class="fas fa-money-bill-wave"></i> Cash on Delivery
                                        </h4>
                                        <p style="margin: 0; font-size: 13px; color: #555;">
                                            Pay when you receive your order. Available nationwide.
                                        </p>
                                    </div>
                                </div>

                                <!-- Hidden payment_method field to maintain compatibility -->
                                <input type="hidden" name="payment_method" id="payment_method" value="">
                                </div>
                            </div>
                            </div>

                            <div class="order-summary-column">
                                <div class="cart-summary">
                                    <h3>Order Summary</h3>
                                    <div class="summary-row">
                                        <span>Subtotal:</span>
                                        <span>$<?php echo number_format($cart_info['total'] ?? 0, 2); ?></span>
                                    </div>
                                    <div class="summary-row">
                                        <span>Shipping:</span>
                                        <span>$0.00</span>
                                    </div>
                                    <div class="summary-row">
                                        <span>Tax:</span>
                                        <span>$0.00</span>
                                    </div>
                                    <div class="summary-row total">
                                        <span>Total:</span>
                                        <span>$<?php echo number_format($cart_info['total'] ?? 0, 2); ?></span>
                                    </div>
                                    <button type="submit" class="btn-checkout">Place Order</button>
                                    <a href="shop.php" class="btn-continue-shopping">Continue Shopping</a>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </section>
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
    
        function togglePaymentFields() {
            const paymentType = document.getElementById('payment_type').value;
            const cardFields = document.getElementById('card-fields');
            const ewalletFields = document.getElementById('ewallet-fields');
            const codInfo = document.getElementById('cod-info');
            const paymentMethod = document.getElementById('payment_method');
            
            // Hide all fields first
            cardFields.style.display = 'none';
            ewalletFields.style.display = 'none';
            codInfo.style.display = 'none';
            
            // Reset required attributes
            const cardInputs = cardFields.querySelectorAll('input, select');
            const ewalletInputs = ewalletFields.querySelectorAll('input, select');
            
            cardInputs.forEach(input => input.removeAttribute('required'));
            ewalletInputs.forEach(input => input.removeAttribute('required'));
            
            // Show relevant fields based on payment type
            switch(paymentType) {
                case 'card':
                    cardFields.style.display = 'block';
                    cardInputs.forEach(input => input.setAttribute('required', 'required'));
                    paymentMethod.value = 'credit_card';
                    break;
                case 'ewallet':
                    ewalletFields.style.display = 'block';
                    ewalletInputs.forEach(input => input.setAttribute('required', 'required'));
                    paymentMethod.value = 'ewallet';
                    break;
                case 'cod':
                    codInfo.style.display = 'block';
                    paymentMethod.value = 'cash_on_delivery';
                    break;
                default:
                    paymentMethod.value = '';
            }
        }
        
        // Format card number as user types
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
    </script>
</body>
</html>
