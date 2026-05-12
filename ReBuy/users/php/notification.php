<?php
session_start();
include 'db.php';
include 'notification_functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'mark_read':
                if (isset($_POST['notification_id'])) {
                    markNotificationAsRead($_POST['notification_id'], $user_id);
                }
                break;
            case 'mark_all_read':
                markAllNotificationsAsRead($user_id);
                break;
            case 'delete':
                if (isset($_POST['notification_id'])) {
                    deleteNotification($_POST['notification_id'], $user_id);
                }
                break;
            case 'archive':
                if (isset($_POST['notification_id'])) {
                    archiveNotification($_POST['notification_id'], $user_id);
                }
                break;
            case 'unarchive':
                if (isset($_POST['notification_id'])) {
                    unarchiveNotification($_POST['notification_id'], $user_id);
                }
                break;
        }
        header("Location: notification.php?tab=" . ($_GET['tab'] ?? 'all'));
        exit();
    }
}

// Get current tab
$current_tab = $_GET['tab'] ?? 'all';

// Get notifications based on tab (show all types)
$notifications = [];
switch ($current_tab) {
    case 'unread':
        $notifications = getUserNotifications($user_id, 50, true, false, null);
        break;
    case 'read':
        $notifications = getUserNotifications($user_id, 50, false, false, null);
        // Filter to show only read notifications
        $notifications = array_filter($notifications, function($n) { return $n['is_read']; });
        break;
    case 'archive':
        $notifications = getUserNotifications($user_id, 50, false, true, null);
        break;
    default: // all
        $notifications = getUserNotifications($user_id, 50, false, false, null);
        break;
}

$unread_count = getUnreadNotificationCount($user_id);
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
    <link rel="stylesheet" href="../css/notification.css">
    <style>
        .notification-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        .notification-actions {
            display: flex;
            gap: 10px;
        }
        .btn-small {
            padding: 8px 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        .btn-mark-read {
            background: #28a745;
            color: white;
        }
        .btn-mark-read:hover {
            background: #218838;
        }
        .notification-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            border-bottom: 2px solid #eee;
            padding-bottom: 10px;
        }
        .tab-btn {
            padding: 10px 20px;
            border: none;
            background: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            color: #666;
            border-radius: 5px;
            transition: all 0.3s ease;
            position: relative;
        }
        .tab-btn:hover {
            background: #f8f9fa;
            color: #2d5016;
        }
        .tab-btn.active {
            background: #2d5016;
            color: white;
        }
        .tab-btn .badge {
            background: #dc3545;
            color: white;
            border-radius: 50%;
            padding: 2px 8px;
            font-size: 12px;
            margin-left: 5px;
        }
        .notification-item.unread {
            background: linear-gradient(135deg, #e8f5e8 0%, #f0f8f0 100%);
            border-left-color: #28a745;
        }
        .notification-item.unread .notification-content h4 {
            font-weight: 700;
        }
        .notification-actions-item {
            display: flex;
            gap: 8px;
            margin-top: 8px;
        }
        .btn-action {
            padding: 4px 8px;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.3s ease;
        }
        .btn-read {
            background: #007bff;
            color: white;
        }
        .btn-archive {
            background: #6c757d;
            color: white;
        }
        .btn-unarchive {
            background: #17a2b8;
            color: white;
        }
        .btn-delete {
            background: #dc3545;
            color: white;
        }
        .btn-action:hover {
            opacity: 0.8;
        }
        .notification-badge {
            background: #dc3545;
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 12px;
            margin-left: 5px;
        }
        .notification-item.clickable {
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .notification-item.clickable:hover {
            background: linear-gradient(135deg, #e3f2fd 0%, #f3f8ff 100%);
            transform: translateX(5px);
            box-shadow: 0 4px 15px rgba(0, 123, 255, 0.15);
        }
        .notification-item.clickable:active {
            transform: translateX(3px);
        }
        .notification-item.archived {
            opacity: 0.7;
            background: #f8f9fa;
        }
        
    </style>
</head>
<body>
    <div class="page-wrapper">

    <?php include '_header.php'; ?>

        <!-- Page Content -->
        <div class="page-content" style="max-width: 1200px; margin: 0 auto; padding: 40px;">
            <div class="notification-header">
                <h1 style="font-size: 28px; color: #333; margin: 0;"><i class="fas fa-bell"></i> Notifications
                    <?php if ($unread_count > 0): ?>
                        <span class="notification-badge"><?php echo $unread_count; ?></span>
                    <?php endif; ?>
                </h1>
                <?php if ($unread_count > 0): ?>
                    <div class="notification-actions">
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="mark_all_read">
                            <button type="submit" class="btn-small btn-mark-read">Mark All as Read</button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Notification Tabs -->
            <div class="notification-tabs">
                <button class="tab-btn <?php echo $current_tab === 'all' ? 'active' : ''; ?>" onclick="switchTab('all')">
                    All
                </button>
                <button class="tab-btn <?php echo $current_tab === 'unread' ? 'active' : ''; ?>" onclick="switchTab('unread')">
                    Unread
                    <?php if ($unread_count > 0): ?>
                        <span class="badge"><?php echo $unread_count; ?></span>
                    <?php endif; ?>
                </button>
                <button class="tab-btn <?php echo $current_tab === 'read' ? 'active' : ''; ?>" onclick="switchTab('read')">
                    Read
                </button>
                <button class="tab-btn <?php echo $current_tab === 'archive' ? 'active' : ''; ?>" onclick="switchTab('archive')">
                    Archive
                </button>
            </div>

            <?php if (!empty($notifications)): ?>
                <div class="notification-list">
                    <?php foreach ($notifications as $notification): ?>
                        <?php 
                        $is_clickable = ($notification['type'] == 'message' && !empty($notification['redirect_url']));
                        $click_target = $is_clickable ? $notification['redirect_url'] : '#';
                        ?>
                        <div class="notification-item <?php echo !$notification['is_read'] ? 'unread' : ''; ?> <?php echo $notification['is_archived'] ? 'archived' : ''; ?> <?php echo $is_clickable ? 'clickable' : ''; ?>"
                             <?php if ($is_clickable): ?>onclick="window.location.href='<?php echo htmlspecialchars($click_target); ?>'"<?php endif; ?>>
                            <i class="<?php echo getNotificationIcon($notification['type']); ?>" style="color: <?php echo getNotificationColor($notification['type']); ?>;"></i>
                            <div class="notification-content">
                                <h4>
                                    <?php echo htmlspecialchars($notification['title']); ?>
                                    <?php if ($is_clickable): ?>
                                        <small style="color: #007bff; font-weight: normal;">(Click to reply)</small>
                                    <?php endif; ?>
                                </h4>
                                <p><?php echo htmlspecialchars($notification['message']); ?></p>
                                <div class="notification-time">
                                    <i class="far fa-clock"></i> <?php echo formatNotificationTime($notification['created_at']); ?>
                                    <?php if (!$notification['is_read']): ?>
                                        <span style="color: #28a745; font-weight: 600; margin-left: 10px;">• New</span>
                                    <?php endif; ?>
                                    <?php if ($notification['is_archived']): ?>
                                        <span style="color: #6c757d; font-weight: 600; margin-left: 10px;">• Archived</span>
                                    <?php endif; ?>
                                </div>
                                <div class="notification-actions-item" onclick="event.stopPropagation();">
                                    <?php if (!$notification['is_read']): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="mark_read">
                                            <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                            <button type="submit" class="btn-action btn-read">Mark as Read</button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($current_tab !== 'archive'): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="archive">
                                            <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                            <button type="submit" class="btn-action btn-archive">Archive</button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="unarchive">
                                            <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                            <button type="submit" class="btn-action btn-unarchive">Unarchive</button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                        <button type="submit" class="btn-action btn-delete" onclick="return confirm('Delete this notification?');">Delete</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-notification">
                    <i class="fas fa-bell-slash"></i>
                    <h3>No notifications</h3>
                    <p>You don't have any notifications at this time. Stay tuned for updates!</p>
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
                    </select>
                </div>
            </div>
        </footer>
    </div>

    <script src="../js/notification.js"></script>
    <script>
        // Tab switching functionality
        function switchTab(tab) {
            window.location.href = 'notification.php?tab=' + tab;
        }

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

        // Real-time notification polling
        let pollingInterval;
        function startPolling() {
            pollingInterval = setInterval(function() {
                fetch('notification_ajax.php?action=check_updates')
                    .then(response => response.json())
                    .then(data => {
                        if (data.has_updates) {
                            // Reload page to show new notifications
                            location.reload();
                        }
                    })
                    .catch(error => console.error('Polling error:', error));
            }, 10000); // Poll every 10 seconds
        }

        // Start polling when page loads
        document.addEventListener('DOMContentLoaded', function() {
            startPolling();
        });

        // Stop polling when leaving page
        window.addEventListener('beforeunload', function() {
            if (pollingInterval) {
                clearInterval(pollingInterval);
            }
        });
    </script>
</body>
</html>
