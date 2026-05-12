
<?php
session_start();
header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Not logged in'
    ]);
    exit();
}

require_once 'db.php';
require_once 'notification_functions.php';

$user_id = $_SESSION['user_id'];

$order_id = $_POST['order_id'] ?? '';
$new_status = $_POST['status'] ?? '';

// Validate inputs
if (empty($order_id) || empty($new_status)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid parameters'
    ]);
    exit();
}

// Validate status
$valid_statuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];

if (!in_array($new_status, $valid_statuses)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid status'
    ]);
    exit();
}

// Check if seller
$seller_check = $conn->query("SHOW COLUMNS FROM users LIKE 'is_seller'");

if ($seller_check->num_rows > 0) {

    $stmt = $conn->prepare("
        SELECT is_seller
        FROM users
        WHERE id = ?
    ");

    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    $stmt->close();

    if ($user['is_seller'] != 1) {

        echo json_encode([
            'success' => false,
            'message' => 'Not a seller'
        ]);

        exit();
    }

} else {

    echo json_encode([
        'success' => false,
        'message' => 'Seller system unavailable'
    ]);

    exit();
}

// Add missing columns if not exists
$conn->query("
    ALTER TABLE seller_orders
    ADD COLUMN IF NOT EXISTS main_order_id INT NULL
");

$conn->query("
    ALTER TABLE orders
    ADD COLUMN IF NOT EXISTS accepted_at TIMESTAMP NULL
");

$conn->query("
    ALTER TABLE orders
    ADD COLUMN IF NOT EXISTS processing_at TIMESTAMP NULL
");

$conn->query("
    ALTER TABLE orders
    ADD COLUMN IF NOT EXISTS shipped_at TIMESTAMP NULL
");

$conn->query("
    ALTER TABLE orders
    ADD COLUMN IF NOT EXISTS delivered_at TIMESTAMP NULL
");

$conn->query("
    ALTER TABLE orders
    ADD COLUMN IF NOT EXISTS cancelled_at TIMESTAMP NULL
");

// Get seller order
$check_stmt = $conn->prepare("
    SELECT
        so.id,
        so.status,
        so.product_id,
        so.customer_id,
        so.quantity,
        so.main_order_id,
        so.order_id AS seller_order_ref
    FROM seller_orders so
    WHERE so.id = ?
    AND so.seller_id = ?
");

$check_stmt->bind_param("ii", $order_id, $user_id);

$check_stmt->execute();

$check_result = $check_stmt->get_result();

if ($check_result->num_rows === 0) {

    echo json_encode([
        'success' => false,
        'message' => 'Order not found'
    ]);

    exit();
}

$order = $check_result->fetch_assoc();

$check_stmt->close();

// Get actual orders.id
$order_numeric_id = $order['main_order_id'];

if (empty($order_numeric_id)) {

    echo json_encode([
        'success' => false,
        'message' => 'main_order_id is missing'
    ]);

    exit();
}

// Validate transition
$current_status = $order['status'];

$valid_transitions = [
    'pending' => ['processing', 'cancelled'],
    'processing' => ['shipped', 'cancelled'],
    'shipped' => ['delivered'],
    'delivered' => [],
    'cancelled' => []
];

if (!in_array($new_status, $valid_transitions[$current_status])) {

    echo json_encode([
        'success' => false,
        'message' => 'Invalid status transition'
    ]);

    exit();
}

// Start transaction
$conn->begin_transaction();

try {

    // Update seller_orders status
    $update_stmt = $conn->prepare("
        UPDATE seller_orders
        SET status = ?
        WHERE id = ?
        AND seller_id = ?
    ");

    $update_stmt->bind_param(
        "sii",
        $new_status,
        $order_id,
        $user_id
    );

    $update_stmt->execute();

    $update_stmt->close();

    // Timestamp column
    $timestamp_column = '';

    switch ($new_status) {

        case 'processing':
            $timestamp_column = 'processing_at';
            break;

        case 'shipped':
            $timestamp_column = 'shipped_at';
            break;

        case 'delivered':
            $timestamp_column = 'delivered_at';
            break;

        case 'cancelled':
            $timestamp_column = 'cancelled_at';
            break;
    }

    // Update timestamp in orders table
    if (!empty($timestamp_column)) {

        $orders_update = $conn->prepare("
            UPDATE orders
            SET
                status = ?,
                $timestamp_column = NOW()
            WHERE id = ?
        ");

        $orders_update->bind_param(
            "si",
            $new_status,
            $order_numeric_id
        );

        $orders_update->execute();

        $orders_update->close();
    }

    // Set accepted_at once
    if ($current_status == 'pending' && $new_status != 'cancelled') {

        $accepted_update = $conn->prepare("
            UPDATE orders
            SET accepted_at = NOW()
            WHERE id = ?
        ");

        $accepted_update->bind_param(
            "i",
            $order_numeric_id
        );

        $accepted_update->execute();

        $accepted_update->close();
    }

    // Get all seller order statuses
    $seller_orders_stmt = $conn->prepare("
        SELECT status
        FROM seller_orders
        WHERE main_order_id = ?
    ");

    $seller_orders_stmt->bind_param(
        "i",
        $order_numeric_id
    );

    $seller_orders_stmt->execute();

    $seller_result = $seller_orders_stmt->get_result();

    $has_cancelled = false;
    $has_processing = false;
    $has_shipped = false;

    $all_delivered = ($seller_result->num_rows > 0);

    while ($so = $seller_result->fetch_assoc()) {

        if ($so['status'] == 'cancelled') {
            $has_cancelled = true;
        }

        if ($so['status'] == 'processing') {
            $has_processing = true;
        }

        if ($so['status'] == 'shipped') {
            $has_shipped = true;
        }

        if ($so['status'] != 'delivered') {
            $all_delivered = false;
        }
    }

    $seller_orders_stmt->close();

    // Determine overall status
    $overall_status = $new_status;

    if ($has_cancelled) {

        $overall_status = 'cancelled';

    }
    elseif ($all_delivered) {

        $overall_status = 'delivered';

    }
    elseif ($has_shipped) {

        $overall_status = 'shipped';

    }
    elseif ($has_processing) {

        $overall_status = 'processing';

    }
    else {

        $overall_status = 'pending';

    }

    // Update overall order status
    $status_update = $conn->prepare("
        UPDATE orders
        SET status = ?
        WHERE id = ?
    ");

    $status_update->bind_param(
        "si",
        $overall_status,
        $order_numeric_id
    );

    $status_update->execute();

    $status_update->close();

    // Restore stock if cancelled
    if ($new_status == 'cancelled') {

        $restore_stmt = $conn->prepare("
            UPDATE products
            SET stock_quantity = stock_quantity + ?
            WHERE id = ?
        ");

        $restore_stmt->bind_param(
            "ii",
            $order['quantity'],
            $order['product_id']
        );

        $restore_stmt->execute();

        $restore_stmt->close();
    }

    // Commit
    $conn->commit();

    // Send notification to customer
    notifyUserOrderStatus($order['customer_id'], $order['seller_order_ref'], $new_status);

    echo json_encode([
        'success' => true,
        'message' => 'Order status updated successfully'
    ]);

} catch (Exception $e) {

    $conn->rollback();

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?>
