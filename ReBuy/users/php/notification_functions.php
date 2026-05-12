<?php
// Notification functions

// Create notifications table if it doesn't exist
function ensureNotificationsTable() {
    global $conn;
    $sql = "CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        type ENUM('promo', 'message', 'order', 'system', 'wishlist', 'event') DEFAULT 'system',
        is_read BOOLEAN DEFAULT FALSE,
        is_archived BOOLEAN DEFAULT FALSE,
        sender_id INT DEFAULT NULL,
        redirect_url VARCHAR(500) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    $result = $conn->query($sql);

    // Add missing columns for existing tables
    if ($result) {
        $check_column = $conn->query("SHOW COLUMNS FROM notifications LIKE 'is_archived'");
        if ($check_column->num_rows == 0) {
            $conn->query("ALTER TABLE notifications ADD COLUMN is_archived BOOLEAN DEFAULT FALSE AFTER is_read");
        }

        $check_sender = $conn->query("SHOW COLUMNS FROM notifications LIKE 'sender_id'");
        if ($check_sender->num_rows == 0) {
            $conn->query("ALTER TABLE notifications ADD COLUMN sender_id INT DEFAULT NULL AFTER is_archived");
        }

        $check_redirect = $conn->query("SHOW COLUMNS FROM notifications LIKE 'redirect_url'");
        if ($check_redirect->num_rows == 0) {
            $conn->query("ALTER TABLE notifications ADD COLUMN redirect_url VARCHAR(500) DEFAULT NULL AFTER sender_id");
        }

        // Add event type to ENUM if it doesn't exist
        $check_type = $conn->query("SHOW COLUMNS FROM notifications LIKE 'type'");
        if ($check_type->num_rows > 0) {
            $type_info = $check_type->fetch_assoc();
            if (strpos($type_info['Type'], "'event'") === false) {
                $conn->query("ALTER TABLE notifications MODIFY COLUMN type ENUM('promo', 'message', 'order', 'system', 'wishlist', 'event') DEFAULT 'system'");
            }
        }
    }

    return $result;
}

function createNotification($user_id, $title, $message, $type = 'system', $sender_id = null, $redirect_url = null) {
    global $conn;

    // Ensure table exists
    ensureNotificationsTable();

    $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type, sender_id, redirect_url) VALUES (?, ?, ?, ?, ?, ?)");
    if ($stmt === false) {
        error_log("createNotification failed: prepare statement error - " . $conn->error);
        return false;
    }

    // Build type string dynamically based on which parameters are null
    $types = 'i'; // user_id is always integer
    $params = [$user_id];

    $types .= 's'; // title is always string
    $params[] = $title;

    $types .= 's'; // message is always string
    $params[] = $message;

    $types .= 's'; // type is always string
    $params[] = $type;

    if ($sender_id === null) {
        $types .= 's'; // Use string for null
        $params[] = $sender_id;
    } else {
        $types .= 'i'; // Use integer for non-null
        $params[] = $sender_id;
    }

    if ($redirect_url === null) {
        $types .= 's'; // Use string for null
        $params[] = $redirect_url;
    } else {
        $types .= 's'; // redirect_url is always string
        $params[] = $redirect_url;
    }

    $stmt->bind_param($types, ...$params);

    $result = $stmt->execute();
    if (!$result) {
        error_log("createNotification failed: execute error - " . $stmt->error);
        error_log("Types: $types, Params: " . print_r($params, true));
    }
    $stmt->close();
    return $result;
}

function getUserNotifications($user_id, $limit = 20, $unread_only = false, $archived_only = false, $filter_types = null) {
    global $conn;

    // Ensure table exists
    ensureNotificationsTable();

    $sql = "SELECT * FROM notifications WHERE user_id = ?";
    $params = [$user_id];
    $types = "i";

    if ($unread_only) {
        $sql .= " AND is_read = FALSE";
    }

    if ($archived_only) {
        $sql .= " AND is_archived = TRUE";
    } else {
        $sql .= " AND is_archived = FALSE";
    }

    if ($filter_types !== null && is_array($filter_types) && !empty($filter_types)) {
        $placeholders = str_repeat('?,', count($filter_types) - 1) . '?';
        $sql .= " AND type IN ($placeholders)";
        $params = array_merge($params, $filter_types);
        $types .= str_repeat('s', count($filter_types));
    }

    $sql .= " ORDER BY created_at DESC LIMIT ?";

    $params[] = $limit;
    $types .= "i";

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        return [];
    }
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $notifications = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $notifications;
}

function getUnreadNotificationCount($user_id) {
    global $conn;
    
    // Ensure table exists
    ensureNotificationsTable();
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = FALSE");
    if ($stmt === false) {
        return 0;
    }
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $count = $result->fetch_assoc()['count'];
    $stmt->close();
    return $count;
}

function markNotificationAsRead($notification_id, $user_id) {
    global $conn;
    
    // Ensure table exists
    ensureNotificationsTable();
    
    $stmt = $conn->prepare("UPDATE notifications SET is_read = TRUE WHERE id = ? AND user_id = ?");
    if ($stmt === false) {
        return false;
    }
    $stmt->bind_param("ii", $notification_id, $user_id);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

function markAllNotificationsAsRead($user_id) {
    global $conn;
    
    // Ensure table exists
    ensureNotificationsTable();
    
    $stmt = $conn->prepare("UPDATE notifications SET is_read = TRUE WHERE user_id = ?");
    if ($stmt === false) {
        return false;
    }
    $stmt->bind_param("i", $user_id);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

function deleteNotification($notification_id, $user_id) {
    global $conn;

    // Ensure table exists
    ensureNotificationsTable();

    $stmt = $conn->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
    if ($stmt === false) {
        return false;
    }
    $stmt->bind_param("ii", $notification_id, $user_id);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

function archiveNotification($notification_id, $user_id) {
    global $conn;

    // Ensure table exists
    ensureNotificationsTable();

    $stmt = $conn->prepare("UPDATE notifications SET is_archived = TRUE WHERE id = ? AND user_id = ?");
    if ($stmt === false) {
        return false;
    }
    $stmt->bind_param("ii", $notification_id, $user_id);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

function unarchiveNotification($notification_id, $user_id) {
    global $conn;

    // Ensure table exists
    ensureNotificationsTable();

    $stmt = $conn->prepare("UPDATE notifications SET is_archived = FALSE WHERE id = ? AND user_id = ?");
    if ($stmt === false) {
        return false;
    }
    $stmt->bind_param("ii", $notification_id, $user_id);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

function getNotificationIcon($type) {
    $icons = [
        'promo' => 'fas fa-tag',
        'message' => 'fas fa-envelope',
        'order' => 'fas fa-shopping-cart',
        'system' => 'fas fa-info-circle',
        'wishlist' => 'fas fa-heart',
        'event' => 'fas fa-calendar-alt'
    ];
    return $icons[$type] ?? 'fas fa-bell';
}

function getNotificationColor($type) {
    $colors = [
        'promo' => '#ff6b6b',
        'message' => '#4ecdc4',
        'order' => '#45b7d1',
        'system' => '#96ceb4',
        'wishlist' => '#ff6b9d',
        'event' => '#9b59b6'
    ];
    return $colors[$type] ?? '#96ceb4';
}

function formatNotificationTime($created_at) {
    $time = strtotime($created_at);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return 'Just now';
    } elseif ($diff < 3600) {
        return floor($diff / 60) . ' minutes ago';
    } elseif ($diff < 86400) {
        return floor($diff / 3600) . ' hours ago';
    } elseif ($diff < 604800) {
        return floor($diff / 86400) . ' days ago';
    } else {
        return date('M j, Y', $time);
    }
}

// Auto-create notifications for various events
function notifyOrderStatusChange($user_id, $order_id, $status) {
    $title = '📦 Order Update';
    $message = "Your order #{$order_id} status has been updated to: {$status}";
    createNotification($user_id, $title, $message, 'order');
}

function notifyNewMessage($user_id, $sender_id, $sender_name, $message_preview) {
    $title = '💬 New Message';
    $message = "You have a new message from {$sender_name}: " . substr($message_preview, 0, 50) . "...";
    $redirect_url = "message.php?sender_id={$sender_id}";
    createNotification($user_id, $title, $message, 'message', $sender_id, $redirect_url);
}

function notifyMessageSent($sender_id, $receiver_name, $message_preview) {
    $title = '✅ Message Sent';
    $message = "Your message to {$receiver_name} has been sent: " . substr($message_preview, 0, 50) . "...";
    $redirect_url = "message.php?sender_id={$receiver_name}";
    createNotification($sender_id, $title, $message, 'message', null, $redirect_url);
}

function notifyPromo($user_id, $promo_title, $promo_description) {
    $title = '🎉 ' . $promo_title;
    $message = $promo_description;
    createNotification($user_id, $title, $message, 'promo');
}

function notifyWishlistItemAvailable($user_id, $product_name) {
    $title = '❤️ Wishlist Item Available';
    $message = "{$product_name} from your wishlist is now back in stock!";
    createNotification($user_id, $title, $message, 'wishlist');
}

function notifySellerEvent($seller_id, $event_title, $event_description, $redirect_url = null) {
    $title = '📅 ' . $event_title;
    $message = $event_description;
    createNotification($seller_id, $title, $message, 'event', null, $redirect_url);
}

function notifySellerOrderStatus($seller_id, $order_id, $status, $proof_of_delivery = null) {
    $title = '📦 Order Status Update';
    $message = "Order #{$order_id} status has been updated to: {$status}";
    if ($proof_of_delivery) {
        $message .= ". Proof of delivery uploaded.";
    }
    createNotification($seller_id, $title, $message, 'order');
}

function notifyUserOrderStatus($user_id, $order_id, $status) {
    $title = '📦 Your Order Status Update';
    $message = "Your order #{$order_id} status has been updated to: {$status}";

    // Add specific message based on status
    switch($status) {
        case 'processing':
            $message .= ". Your order is being processed by the seller.";
            break;
        case 'shipped':
            $message .= ". Your order has been shipped and is on its way!";
            break;
        case 'delivered':
            $message .= ". Your order has been delivered successfully!";
            break;
        case 'cancelled':
            $message .= ". Your order has been cancelled.";
            break;
    }

    return createNotification($user_id, $title, $message, 'order', null, 'orders.php');
}
?>
