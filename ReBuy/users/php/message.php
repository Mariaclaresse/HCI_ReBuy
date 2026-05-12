<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$selected_user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

// Update current user's last_seen timestamp
$conn->query("UPDATE users SET last_seen = NOW() WHERE id = $user_id");

// Handle heartbeat request to update last_seen via AJAX
if (isset($_POST['action']) && $_POST['action'] == 'heartbeat') {
    $conn->query("UPDATE users SET last_seen = NOW() WHERE id = $user_id");
    echo json_encode(['success' => true]);
    exit();
}

// Check if messages table exists
$table_check = $conn->query("SHOW TABLES LIKE 'messages'");
$table_exists = ($table_check && $table_check->num_rows > 0);

// Handle message sending via AJAX
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'send_message') {
    header('Content-Type: application/json');
    
    $message = trim($_POST['message'] ?? '');
    $receiver_id = intval($_POST['receiver_id'] ?? 0);
    $image_url = null;
    $upload_error = null;
    
    // Handle image upload
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $filename = $_FILES['image']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            $upload_dir = '../uploads/messages/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $new_filename = uniqid() . '.' . $ext;
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
                $image_url = 'uploads/messages/' . $new_filename;
            } else {
                $upload_error = 'Failed to move uploaded file';
            }
        } else {
            $upload_error = 'Invalid file type';
        }
    } elseif (isset($_FILES['image']) && $_FILES['image']['error'] != 0) {
        $upload_error = 'Upload error code: ' . $_FILES['image']['error'];
    }
    
    if ($receiver_id > 0 && (!empty($message) || $image_url) && $table_exists) {
        // Check if image_url column exists
        $column_check = $conn->query("SHOW COLUMNS FROM messages LIKE 'image_url'");
        $has_image_column = ($column_check && $column_check->num_rows > 0);
        
        if ($has_image_column) {
            $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, message, image_url) VALUES (?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("iiss", $user_id, $receiver_id, $message, $image_url);
                $stmt->execute();
                $message_id = $stmt->insert_id;
                $stmt->close();
                
                echo json_encode([
                    'success' => true,
                    'message_id' => $message_id,
                    'message' => $message ? htmlspecialchars($message) : '',
                    'image_url' => $image_url,
                    'created_at' => date('H:i')
                ]);
                exit();
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'image_url column does not exist in messages table. Please run the SQL migration.']);
            exit();
        }
    }
    
    $error_msg = 'Failed to send message';
    if ($upload_error) {
        $error_msg .= ' - ' . $upload_error;
    }
    if (!$table_exists) {
        $error_msg .= ' - Messages table does not exist';
    }
    
    echo json_encode(['success' => false, 'error' => $error_msg]);
    exit();
}

// Mark messages as read when viewing a conversation
if ($selected_user_id > 0 && $table_exists) {
    $stmt = $conn->prepare("UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND is_read = 0");
    if ($stmt) {
        $stmt->bind_param("ii", $selected_user_id, $user_id);
        $stmt->execute();
        $stmt->close();
    }
}

// Get all conversations (unique users the current user has messaged with)
$conversations = null;
if ($table_exists) {
    // Check if is_seller column exists
    $seller_check = $conn->query("SHOW COLUMNS FROM users LIKE 'is_seller'");
    $has_seller_column = ($seller_check && $seller_check->num_rows > 0);

    // Check if last_seen column exists
    $last_seen_check = $conn->query("SHOW COLUMNS FROM users LIKE 'last_seen'");
    $has_last_seen = ($last_seen_check && $last_seen_check->num_rows > 0);

    $conversations_query = "
        SELECT DISTINCT
            CASE
                WHEN sender_id = ? THEN receiver_id
                ELSE sender_id
            END as other_user_id,
            u.username,
            u.first_name,
            u.last_name,
            u.profile_pic"
            . ($has_seller_column ? ", u.is_seller" : "")
            . ($has_last_seen ? ", u.last_seen" : "") . ",
            (
                SELECT message
                FROM messages
                WHERE (sender_id = ? AND receiver_id = other_user_id)
                   OR (sender_id = other_user_id AND receiver_id = ?)
                ORDER BY created_at DESC
                LIMIT 1
            ) as last_message,
            (
                SELECT created_at
                FROM messages
                WHERE (sender_id = ? AND receiver_id = other_user_id)
                   OR (sender_id = other_user_id AND receiver_id = ?)
                ORDER BY created_at DESC
                LIMIT 1
            ) as last_message_time,
            (
                SELECT COUNT(*)
                FROM messages
                WHERE sender_id = other_user_id AND receiver_id = ? AND is_read = 0
            ) as unread_count
        FROM messages
        JOIN users u ON (
            CASE
                WHEN sender_id = ? THEN receiver_id
                ELSE sender_id
            END = u.id
        )
        WHERE sender_id = ? OR receiver_id = ?
        ORDER BY last_message_time DESC
    ";
    $stmt = $conn->prepare($conversations_query);
    if ($stmt) {
        $stmt->bind_param("iiiiiiiii", $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id);
        $stmt->execute();
        $conversations = $stmt->get_result();
        $stmt->close();
    }
}

// Get messages for selected conversation
$messages = [];
$selected_user = null;
if ($selected_user_id > 0) {
    // Check if is_seller column exists
    $seller_check = $conn->query("SHOW COLUMNS FROM users LIKE 'is_seller'");
    $has_seller_column = ($seller_check && $seller_check->num_rows > 0);

    // Check if last_seen column exists
    $last_seen_check = $conn->query("SHOW COLUMNS FROM users LIKE 'last_seen'");
    $has_last_seen = ($last_seen_check && $last_seen_check->num_rows > 0);

    // Get selected user info
    $select_columns = "id, username, first_name, last_name, profile_pic";
    if ($has_seller_column) {
        $select_columns .= ", is_seller";
    }
    if ($has_last_seen) {
        $select_columns .= ", last_seen";
    }

    $user_stmt = $conn->prepare("SELECT $select_columns FROM users WHERE id = ?");
    $user_stmt->bind_param("i", $selected_user_id);
    $user_stmt->execute();
    $selected_user = $user_stmt->get_result()->fetch_assoc();
    $user_stmt->close();
    
    // Get conversation messages
    $msg_stmt = $conn->prepare("
        SELECT m.*, 
               u.username as sender_username,
               u.first_name as sender_first_name,
               u.last_name as sender_last_name
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)
        ORDER BY created_at ASC
    ");
    $msg_stmt->bind_param("iiii", $user_id, $selected_user_id, $selected_user_id, $user_id);
    $msg_stmt->execute();
    $messages = $msg_stmt->get_result();
    $msg_stmt->close();
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
        .messages-container {
            display: flex;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            height: 600px;
            overflow: hidden;
        }
        .conversations-list {
            width: 350px;
            border-right: 1px solid #eee;
            overflow-y: auto;
            background: #f8f9fa;
        }
        .conversation-item {
            padding: 15px;
            border-bottom: 1px solid #eee;
            cursor: pointer;
            transition: background 0.2s;
        }
        .conversation-item:hover {
            background: #e9ecef;
        }
        .conversation-item.active {
            background: #2d5016;
            color: white;
        }
        .conversation-item.active .last-message {
            color: rgba(255,255,255,0.8);
        }
        .conversation-item.active .message-time {
            color: rgba(255,255,255,0.7);
        }
        .conversation-header {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
        }
        .user-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: #ddd;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
            font-weight: bold;
            color: #555;
            overflow: hidden;
        }
        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .user-info {
            flex: 1;
        }
        .user-name {
            font-weight: 600;
            font-size: 14px;
        }
        .last-message {
            font-size: 13px;
            color: #666;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .message-time {
            font-size: 11px;
            color: #999;
            margin-top: 4px;
        }
        .unread-badge {
            background: #dc3545;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: bold;
        }
        .chat-area {
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        .chat-header {
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
            background: #f8f9fa;
            display: flex;
            align-items: center;
        }
        .chat-header .user-avatar {
            width: 40px;
            height: 40px;
        }
        .chat-header .user-name {
            font-weight: 600;
            font-size: 16px;
        }
        .messages-list {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            background: #fff;
        }
        .message-bubble {
            max-width: 70%;
            margin-bottom: 15px;
            padding: 12px 16px;
            border-radius: 18px;
            position: relative;
        }
        .message-bubble.sent {
            background: #2d5016;
            color: white;
            margin-left: auto;
            border-bottom-right-radius: 4px;
        }
        .message-bubble.received {
            background: #e9ecef;
            color: #333;
            border-bottom-left-radius: 4px;
        }
        .message-text {
            word-wrap: break-word;
        }
        .message-time {
            font-size: 11px;
            margin-top: 5px;
            opacity: 0.7;
        }
        .message-input-area {
            padding: 15px 20px;
            border-top: 1px solid #eee;
            background: #f8f9fa;
        }
        .message-form {
            display: flex;
            gap: 10px;
        }
        .message-input {
            flex: 1;
            padding: 12px 16px;
            border: 1px solid #ddd;
            border-radius: 25px;
            font-size: 14px;
            outline: none;
        }
        .message-input:focus {
            border-color: #2d5016;
        }
        .send-btn {
            background: #2d5016;
            color: white;
            border: none;
            width: 45px;
            height: 45px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s;
        }
        .send-btn:hover {
            background: #1e3009;
        }
        .no-conversation {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #999;
        }
        .no-conversation i {
            font-size: 60px;
            margin-bottom: 20px;
        }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }
        .empty-state i {
            font-size: 60px;
            margin-bottom: 20px;
            color: #ddd;
        }
        .back-btn {
            display: none;
            background: none;
            border: none;
            font-size: 18px;
            margin-right: 10px;
            cursor: pointer;
        }
        @media (max-width: 768px) {
            .messages-container {
                height: calc(100vh - 200px);
            }
            .conversations-list {
                width: 100%;
            }
            .chat-area {
                display: none;
            }
            .chat-area.active {
                display: flex;
                width: 100%;
            }
            .conversations-list.hidden {
                display: none;
            }
            .back-btn {
                display: block;
            }
        }
    </style>
</head>
<body>
    <div class="page-wrapper">
        <?php include '_header.php'; ?>

        <!-- Page Content -->
        <div class="page-content" style="max-width: 1200px; margin: 0 auto; padding: 40px;">

            <div class="messages-container">
                <!-- Conversations List -->
                <div class="conversations-list" id="conversationsList">
                    <?php if (!$table_exists): ?>
                        <div class="empty-state">
                            <i class="fas fa-database"></i>
                            <h3>Messages not set up</h3>
                            <p>Please run the SQL migration to enable messaging.</p>
                        </div>
                    <?php elseif ($conversations && $conversations->num_rows > 0): ?>
                        <?php while ($conv = $conversations->fetch_assoc()): ?>
                            <?php
                                $initials = strtoupper(substr($conv['first_name'], 0, 1) . substr($conv['last_name'], 0, 1));
                                $time = strtotime($conv['last_message_time']);
                                $time_str = date('M d, H:i', $time);
                                if (date('Y-m-d') == date('Y-m-d', $time)) {
                                    $time_str = date('H:i', $time);
                                }
                            ?>
                            <div class="conversation-item <?php echo $selected_user_id == $conv['other_user_id'] ? 'active' : ''; ?>" 
                                 onclick="selectConversation(<?php echo $conv['other_user_id']; ?>)">
                                <div class="conversation-header">
                                    <div class="user-avatar" style="position: relative;">
                                        <?php
                                            if (!empty($conv['profile_pic'])):
                                                $pic = $conv['profile_pic'];
                                                // Handle both possible database formats
                                                if (strpos($pic, 'uploads/') === 0) {
                                                    $img_src = '../' . $pic;
                                                    $file_check = __DIR__ . '/../' . $pic;
                                                } else {
                                                    $img_src = '../uploads/profile_pics/' . $pic;
                                                    $file_check = __DIR__ . '/../uploads/profile_pics/' . $pic;
                                                }
                                                if (file_exists($file_check)): ?>
                                            <img src="<?php echo htmlspecialchars($img_src); ?>" alt="Avatar">
                                        <?php else: ?>
                                            <?php echo $initials; ?>
                                        <?php endif; ?>
                                        <?php else: ?>
                                            <?php echo $initials; ?>
                                        <?php endif; ?>
                                        <?php
                                            // Online status indicator
                                            $is_online = false;
                                            if (isset($conv['last_seen']) && $conv['last_seen']) {
                                                $last_seen_time = strtotime($conv['last_seen']);
                                                $is_online = (time() - $last_seen_time) < 300; // Online if last seen within 5 minutes
                                            }
                                        ?>
                                        <?php if ($is_online): ?>
                                            <span style="position: absolute; bottom: 2px; right: 2px; width: 12px; height: 12px; background: #27ae60; border: 2px solid white; border-radius: 50%;"></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="user-info">
                                        <div class="user-name">
                                            <?php echo htmlspecialchars($conv['first_name'] . ' ' . $conv['last_name']); ?>
                                            <?php if (isset($conv['is_seller']) && $conv['is_seller'] == 1): ?>
                                                <span style="display: inline-flex; align-items: center; gap: 3px; margin-left: 6px; font-size: 10px; background: #2d5016; color: white; padding: 1px 6px; border-radius: 8px;">
                                                    <i class="fas fa-store" style="font-size: 8px;"></i> Seller
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="last-message"><?php echo htmlspecialchars(substr($conv['last_message'], 0, 50)); ?></div>
                                    </div>
                                    <?php if ($conv['unread_count'] > 0): ?>
                                        <div class="unread-badge"><?php echo $conv['unread_count']; ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="message-time"><?php echo $time_str; ?></div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <h3>No conversations yet</h3>
                            <p>Start a conversation with a seller or buyer!</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Chat Area -->
                <div class="chat-area <?php echo $selected_user_id > 0 ? 'active' : ''; ?>" id="chatArea">
                    <?php if ($selected_user_id > 0 && $selected_user): ?>
                        <!-- Chat Header -->
                        <div class="chat-header">
                            <button class="back-btn" onclick="goBack()">
                                <i class="fas fa-arrow-left"></i>
                            </button>
                            <div class="user-avatar" style="position: relative;">
                                <?php
                                    if (!empty($selected_user['profile_pic'])):
                                        $pic = $selected_user['profile_pic'];
                                        // Handle both possible database formats
                                        if (strpos($pic, 'uploads/') === 0) {
                                            $img_src = '../' . $pic;
                                            $file_check = __DIR__ . '/../' . $pic;
                                        } else {
                                            $img_src = '../uploads/profile_pics/' . $pic;
                                            $file_check = __DIR__ . '/../uploads/profile_pics/' . $pic;
                                        }
                                        if (file_exists($file_check)): ?>
                                    <img src="<?php echo htmlspecialchars($img_src); ?>" alt="Avatar">
                                <?php else: ?>
                                    <?php echo strtoupper(substr($selected_user['first_name'], 0, 1) . substr($selected_user['last_name'], 0, 1)); ?>
                                <?php endif; ?>
                                <?php else: ?>
                                    <?php echo strtoupper(substr($selected_user['first_name'], 0, 1) . substr($selected_user['last_name'], 0, 1)); ?>
                                <?php endif; ?>
                                <?php
                                    // Online status indicator
                                    $is_online = false;
                                    if (isset($selected_user['last_seen']) && $selected_user['last_seen']) {
                                        $last_seen_time = strtotime($selected_user['last_seen']);
                                        $is_online = (time() - $last_seen_time) < 300; // Online if last seen within 5 minutes
                                    }
                                ?>
                                <?php if ($is_online): ?>
                                    <span style="position: absolute; bottom: 2px; right: 2px; width: 12px; height: 12px; background: #27ae60; border: 2px solid white; border-radius: 50%;"></span>
                                <?php endif; ?>
                            </div>
                            <div class="user-name">
                                <?php echo htmlspecialchars($selected_user['first_name'] . ' ' . $selected_user['last_name']); ?>
                                <?php if (isset($selected_user['is_seller']) && $selected_user['is_seller'] == 1): ?>
                                    <span style="display: inline-flex; align-items: center; gap: 4px; margin-left: 8px; font-size: 11px; background: #2d5016; color: white; padding: 2px 8px; border-radius: 10px;">
                                        <i class="fas fa-store" style="font-size: 9px;"></i> Seller
                                    </span>
                                <?php endif; ?>
                                <?php if (isset($selected_user['last_seen']) && $selected_user['last_seen']): ?>
                                    <span style="display: block; font-size: 11px; color: <?php echo $is_online ? '#27ae60' : '#999'; ?>; margin-top: 2px;">
                                        <?php echo $is_online ? 'Online' : 'Last seen ' . date('M d, H:i', strtotime($selected_user['last_seen'])); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Messages List -->
                        <div class="messages-list" id="messagesList">
                            <?php if ($messages->num_rows > 0): ?>
                                <?php while ($msg = $messages->fetch_assoc()): ?>
                                    <?php
                                        $is_sent = $msg['sender_id'] == $user_id;
                                        $msg_time = strtotime($msg['created_at']);
                                        $msg_time_str = date('H:i', $msg_time);
                                    ?>
                                    <div class="message-bubble <?php echo $is_sent ? 'sent' : 'received'; ?>">
                                        <?php if (!empty($msg['image_url'])): ?>
                                            <img src="../<?php echo htmlspecialchars($msg['image_url']); ?>" style="max-width: 100%; max-height: 200px; border-radius: 8px; margin-bottom: 8px; display: block;">
                                        <?php endif; ?>
                                        <?php if (!empty($msg['message'])): ?>
                                            <div class="message-text"><?php echo htmlspecialchars($msg['message']); ?></div>
                                        <?php endif; ?>
                                        <div class="message-time"><?php echo $msg_time_str; ?></div>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="no-conversation">
                                    <i class="fas fa-comments"></i>
                                    <p>No messages yet. Start the conversation!</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Message Input -->
                        <div class="message-input-area">
                            <form class="message-form" id="messageForm" enctype="multipart/form-data">
                                <input type="hidden" name="receiver_id" value="<?php echo $selected_user_id; ?>">
                                <input type="hidden" name="action" value="send_message">
                                <input type="text" name="message" class="message-input" id="messageInput" placeholder="Type a message..." autocomplete="off">
                                <label for="imageInput" class="image-btn" style="cursor: pointer; display: flex; align-items: center; justify-content: center; width: 45px; height: 45px; border: 2px solid #ddd; border-radius: 50%; transition: all 0.2s;">
                                    <i class="fas fa-image" style="color: #666;"></i>
                                </label>
                                <input type="file" id="imageInput" name="image" accept="image/*" style="display: none;">
                                <button type="submit" class="send-btn" id="sendBtn">
                                    <i class="fas fa-paper-plane"></i>
                                </button>
                            </form>
                            <div id="imagePreview" style="display: none; margin-top: 10px; position: relative;">
                                <img id="previewImg" style="max-width: 100px; max-height: 100px; border-radius: 8px; object-fit: cover;">
                                <button type="button" id="removeImage" style="position: absolute; top: -5px; right: -5px; background: #dc3545; color: white; border: none; border-radius: 50%; width: 20px; height: 20px; cursor: pointer; font-size: 12px;">×</button>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="no-conversation">
                            <i class="fas fa-comments"></i>
                            <p>Select a conversation to start messaging</p>
                        </div>
                    <?php endif; ?>
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
    </div>

    <script>
        // User dropdown menu
        document.querySelector('.user-menu button').addEventListener('click', function() {
            document.querySelector('.user-dropdown').classList.toggle('active');
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const userMenu = document.querySelector('.user-menu');
            if (!userMenu.contains(event.target)) {
                document.querySelector('.user-dropdown').classList.remove('active');
            }
        });

        // Select conversation
        function selectConversation(userId) {
            window.location.href = 'message.php?user_id=' + userId;
        }

        // Go back to conversations list (mobile)
        function goBack() {
            document.getElementById('chatArea').classList.remove('active');
            document.getElementById('conversationsList').classList.remove('hidden');
        }

        // Scroll to bottom of messages
        function scrollToBottom() {
            const messagesList = document.getElementById('messagesList');
            if (messagesList) {
                messagesList.scrollTop = messagesList.scrollHeight;
            }
        }

        window.addEventListener('load', function() {
            scrollToBottom();
        });

        // Heartbeat to keep online status updated
        function sendHeartbeat() {
            const formData = new FormData();
            formData.append('action', 'heartbeat');
            fetch('message.php', {
                method: 'POST',
                body: formData
            }).catch(error => console.error('Heartbeat error:', error));
        }

        // Send heartbeat every 2 minutes to update online status
        setInterval(sendHeartbeat, 120000);

        // Mobile: show chat area when conversation is selected
        <?php if ($selected_user_id > 0): ?>
            document.addEventListener('DOMContentLoaded', function() {
                if (window.innerWidth <= 768) {
                    document.getElementById('conversationsList').classList.add('hidden');
                    document.getElementById('chatArea').classList.add('active');
                }
            });
        <?php endif; ?>

        // Real-time messaging
        <?php if ($selected_user_id > 0): ?>
            let lastMessageId = <?php 
                $last_id = 0;
                if ($messages && $messages->num_rows > 0) {
                    $messages->data_seek($messages->num_rows - 1);
                    $last_msg = $messages->fetch_assoc();
                    $last_id = $last_msg['id'];
                }
                echo $last_id;
            ?>;
            const currentUserId = <?php echo $user_id; ?>;
            const otherUserId = <?php echo $selected_user_id; ?>;
            let isPolling = true;

            function fetchNewMessages() {
                if (!isPolling) return;

                fetch('fetch_messages.php?user_id=' + otherUserId + '&last_id=' + lastMessageId)
                    .then(response => response.json())
                    .then(data => {
                        if (data.messages && data.messages.length > 0) {
                            const messagesList = document.getElementById('messagesList');
                            
                            // Remove "no messages" placeholder if exists
                            const noConversation = messagesList.querySelector('.no-conversation');
                            if (noConversation) {
                                noConversation.remove();
                            }

                            data.messages.forEach(msg => {
                                const bubble = document.createElement('div');
                                bubble.className = 'message-bubble ' + (msg.is_sent ? 'sent' : 'received');
                                
                                let content = '';
                                if (msg.image_url) {
                                    content += `<img src="../${msg.image_url}" style="max-width: 100%; max-height: 200px; border-radius: 8px; margin-bottom: 8px; display: block;">`;
                                }
                                if (msg.message) {
                                    content += `<div class="message-text">${msg.message}</div>`;
                                }
                                content += `<div class="message-time">${msg.created_at}</div>`;
                                
                                bubble.innerHTML = content;
                                messagesList.appendChild(bubble);
                                lastMessageId = msg.id;
                            });

                            scrollToBottom();
                        }
                    })
                    .catch(error => console.error('Error fetching messages:', error));
            }

            // Handle image preview
            const imageInput = document.getElementById('imageInput');
            const imagePreview = document.getElementById('imagePreview');
            const previewImg = document.getElementById('previewImg');
            const removeImageBtn = document.getElementById('removeImage');
            let selectedImage = null;

            imageInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        previewImg.src = e.target.result;
                        imagePreview.style.display = 'block';
                        selectedImage = file;
                    };
                    reader.readAsDataURL(file);
                }
            });

            removeImageBtn.addEventListener('click', function() {
                imageInput.value = '';
                imagePreview.style.display = 'none';
                selectedImage = null;
            });

            // Handle message sending via AJAX
            document.getElementById('messageForm').addEventListener('submit', function(e) {
                e.preventDefault();
                
                const messageInput = document.getElementById('messageInput');
                const message = messageInput.value.trim();
                const sendBtn = document.getElementById('sendBtn');
                
                if (!message && !selectedImage) return;
                
                // Disable button while sending
                sendBtn.disabled = true;
                sendBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                
                const formData = new FormData();
                formData.append('action', 'send_message');
                formData.append('message', message);
                formData.append('receiver_id', otherUserId);
                if (selectedImage) {
                    formData.append('image', selectedImage);
                }
                
                fetch('message.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Clear input and image
                        messageInput.value = '';
                        imageInput.value = '';
                        imagePreview.style.display = 'none';
                        selectedImage = null;
                        
                        // Add message to chat immediately
                        const messagesList = document.getElementById('messagesList');
                        const noConversation = messagesList.querySelector('.no-conversation');
                        if (noConversation) {
                            noConversation.remove();
                        }
                        
                        const bubble = document.createElement('div');
                        bubble.className = 'message-bubble sent';
                        
                        let content = '';
                        if (data.image_url) {
                            content += `<img src="../${data.image_url}" style="max-width: 100%; max-height: 200px; border-radius: 8px; margin-bottom: 8px; display: block;">`;
                        }
                        if (data.message) {
                            content += `<div class="message-text">${data.message}</div>`;
                        }
                        content += `<div class="message-time">${data.created_at}</div>`;
                        
                        bubble.innerHTML = content;
                        messagesList.appendChild(bubble);
                        lastMessageId = data.message_id;
                        
                        scrollToBottom();
                    } else {
                        alert('Error: ' + (data.error || 'Failed to send message'));
                    }
                })
                .catch(error => {
                    console.error('Error sending message:', error);
                    alert('Error sending message. Please try again.');
                })
                .finally(() => {
                    sendBtn.disabled = false;
                    sendBtn.innerHTML = '<i class="fas fa-paper-plane"></i>';
                    messageInput.focus();
                });
            });

            // Poll for new messages every 500ms for instant updates
            setInterval(fetchNewMessages, 500);

            // Stop polling when leaving the page
            window.addEventListener('beforeunload', function() {
                isPolling = false;
            });
        <?php endif; ?>
    </script>
</body>
</html>
