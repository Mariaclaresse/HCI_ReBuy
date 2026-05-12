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

// Fetch order details
$stmt = $conn->prepare("
    SELECT o.*, u.first_name, u.last_name, u.email, u.purok_street, u.barangay, u.municipality_city, u.province, u.country, u.zip_code
    FROM orders o
    JOIN users u ON o.user_id = u.id
    WHERE o.id = ? AND o.user_id = ?
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

// Fetch order items with seller order status
$items_stmt = $conn->prepare("
    SELECT oi.*, p.name as product_name, p.image_url as product_image, 
           u.first_name as seller_first_name, u.last_name as seller_last_name,
           so.status as seller_status, so.id as seller_order_id
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    JOIN users u ON p.seller_id = u.id
    LEFT JOIN seller_orders so ON so.customer_id = oi.order_id AND so.product_id = oi.product_id
    WHERE oi.order_id = ?
");
$items_stmt->bind_param("i", $order_id);
$items_stmt->execute();
$items_result = $items_stmt->get_result();
$items = [];
while ($item = $items_result->fetch_assoc()) {
    $items[] = $item;
}
$items_stmt->close();

// Use the orders.status directly which is synced with seller_orders
$overall_status = $order['status'] ?? 'pending';

// Generate invoice number
$invoice_number = 'INV-' . date('Y') . '-' . str_pad($order_id, 6, '0', STR_PAD_LEFT);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ReBuy</title>
    <link rel="icon" type="image/x-icon" href="../../assets/logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f0f0;
            padding: 20px;
        }

        .invoice-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .invoice-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 2px solid #2d5016;
        }

        .invoice-logo {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .invoice-logo i {
            font-size: 40px;
            color: #2d5016;
        }

        .invoice-logo span {
            font-size: 28px;
            font-weight: 700;
            color: #2d5016;
        }

        .invoice-title {
            text-align: right;
        }

        .invoice-title h1 {
            font-size: 24px;
            color: #333;
            margin-bottom: 5px;
        }

        .invoice-title p {
            color: #666;
            font-size: 14px;
        }

        .invoice-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 40px;
        }

        .info-block h3 {
            font-size: 14px;
            color: #666;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .info-block p {
            color: #333;
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 5px;
        }

        .info-block strong {
            font-weight: 600;
        }

        .invoice-table {
            width: 100%;
            margin-bottom: 30px;
            border-collapse: collapse;
        }

        .invoice-table th {
            background: #2d5016;
            color: white;
            padding: 12px;
            text-align: left;
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .invoice-table td {
            padding: 12px;
            border-bottom: 1px solid #e9ecef;
            font-size: 14px;
        }

        .invoice-table tr:last-child td {
            border-bottom: none;
        }

        .invoice-table .product-cell {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .invoice-table .product-image {
            width: 50px;
            height: 50px;
            border-radius: 4px;
            object-fit: cover;
            border: 1px solid #e9ecef;
        }

        .invoice-table .text-right {
            text-align: right;
        }

        .invoice-table .text-center {
            text-align: center;
        }

        .invoice-summary {
            margin-left: auto;
            width: 300px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #e9ecef;
        }

        .summary-row:last-child {
            border-bottom: none;
            border-top: 2px solid #2d5016;
            margin-top: 10px;
            padding-top: 15px;
        }

        .summary-row span:first-child {
            color: #666;
            font-size: 14px;
        }

        .summary-row span:last-child {
            font-weight: 600;
            font-size: 14px;
            color: #333;
        }

        .summary-row.total span:last-child {
            font-size: 18px;
            color: #2d5016;
        }

        .invoice-footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
            text-align: center;
        }

        .invoice-footer p {
            color: #666;
            font-size: 13px;
            margin-bottom: 5px;
        }

        .invoice-footer .thank-you {
            font-size: 16px;
            font-weight: 600;
            color: #2d5016;
            margin-bottom: 15px;
        }

        .print-btn {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #2d5016;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            transition: background 0.3s;
        }

        .print-btn:hover {
            background: #1e3009;
        }

        .back-btn {
            position: fixed;
            top: 20px;
            right: 180px;
            background: white;
            color: #333;
            border: 1px solid #ddd;
            padding: 12px 24px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: all 0.3s;
            text-decoration: none;
        }

        .back-btn:hover {
            background: #f8f9fa;
        }

        @media print {
            body {
                background: white;
                padding: 0;
            }

            .invoice-container {
                box-shadow: none;
                padding: 20px;
            }

            .print-btn,
            .back-btn {
                display: none;
            }
        }

        @media (max-width: 768px) {
            .invoice-container {
                padding: 20px;
            }

            .invoice-header {
                flex-direction: column;
                text-align: center;
                gap: 20px;
            }

            .invoice-title {
                text-align: center;
            }

            .invoice-info {
                grid-template-columns: 1fr;
            }

            .invoice-summary {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <a href="settings.php#orders" class="back-btn">
        <i class="fas fa-arrow-left"></i> Back
    </a>
    <button class="print-btn" onclick="window.print()">
        <i class="fas fa-print"></i> Print Invoice
    </button>

    <div class="invoice-container">
        <!-- Invoice Header -->
        <div class="invoice-header">
            <div class="invoice-logo">
                <i class="fas fa-shopping-bag"></i>
                <span>ReBuy</span>
            </div>
            <div class="invoice-title">
                <h1>INVOICE</h1>
                <p>#<?php echo $invoice_number; ?></p>
            </div>
        </div>

        <!-- Invoice Info -->
        <div class="invoice-info">
            <div class="info-block">
                <h3>Bill To</h3>
                <p><strong><?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></strong></p>
                <p><?php echo htmlspecialchars($order['email']); ?></p>
                <p><?php echo htmlspecialchars($order['purok_street'] ?? ''); ?></p>
                <p><?php echo htmlspecialchars($order['barangay'] ?? ''); ?></p>
                <p><?php echo htmlspecialchars($order['municipality_city'] ?? ''); ?></p>
                <p><?php echo htmlspecialchars($order['province'] ?? ''); ?></p>
                <p><?php echo htmlspecialchars($order['country'] ?? ''); ?></p>
                <p><?php echo htmlspecialchars($order['zip_code'] ?? ''); ?></p>
            </div>
            <div class="info-block">
                <h3>Invoice Details</h3>
                <p><strong>Order ID:</strong> #<?php echo str_pad($order_id, 6, '0', STR_PAD_LEFT); ?></p>
                <p><strong>Date:</strong> <?php echo date('F d, Y', strtotime($order['display_date'] ?? $order['order_date'])); ?></p>
                <p><strong>Payment Method:</strong> <?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($order['payment_method']))); ?></p>
                <p><strong>Status:</strong> 
                    <span style="color: <?php echo $overall_status == 'delivered' ? '#2d5016' : ($overall_status == 'cancelled' ? '#dc3545' : '#856404'); ?>; font-weight: 600;">
                        <?php echo ucfirst(htmlspecialchars($overall_status)); ?>
                    </span>
                </p>
            </div>
        </div>

        <!-- Invoice Items -->
        <table class="invoice-table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th class="text-center">Qty</th>
                    <th class="text-center">Price</th>
                    <th class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td>
                            <div class="product-cell">
                                <img src="<?php echo !empty($item['product_image']) ? '../' . htmlspecialchars($item['product_image']) : 'https://images.unsplash.com/photo-1586023492125-27b2c045efd7?ixlib=rb-4.0.3&auto=format&fit=crop&w=100&q=80'; ?>" alt="<?php echo htmlspecialchars($item['product_name']); ?>" class="product-image">
                                <div>
                                    <div style="font-weight: 600;"><?php echo htmlspecialchars($item['product_name']); ?></div>
                                    <div style="font-size: 12px; color: #666;">Seller: <?php echo htmlspecialchars($item['seller_first_name'] . ' ' . $item['seller_last_name']); ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="text-center"><?php echo $item['quantity']; ?></td>
                        <td class="text-center">₱<?php echo number_format($item['price'], 2); ?></td>
                        <td class="text-right">₱<?php echo number_format($item['price'] * $item['quantity'], 2); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Invoice Summary -->
        <div class="invoice-summary">
            <div class="summary-row">
                <span>Subtotal</span>
                <span>₱<?php echo number_format($order['total_amount'], 2); ?></span>
            </div>
            <div class="summary-row">
                <span>Shipping</span>
                <span>Free</span>
            </div>
            <div class="summary-row">
                <span>Tax</span>
                <span>₱0.00</span>
            </div>
            <div class="summary-row total">
                <span>Total</span>
                <span>₱<?php echo number_format($order['total_amount'], 2); ?></span>
            </div>
        </div>

        <!-- Invoice Footer -->
        <div class="invoice-footer">
            <p class="thank-you">Thank you for your order!</p>
            <p>If you have any questions, please contact us at rebuy@gmail.com</p>
            <p>+639813446215 | T. Curato St. Cabadbaran City Agusan Del Norte, Philippines, 8600</p>
        </div>
    </div>
</body>
</html>
