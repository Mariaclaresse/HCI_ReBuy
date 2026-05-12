<?php
session_start();
include 'db.php';

// Update user's last_seen to indicate they are offline (set to 10 minutes ago)
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $offline_time = date('Y-m-d H:i:s', strtotime('-10 minutes'));
    $conn->query("UPDATE users SET last_seen = '$offline_time' WHERE id = $user_id");
}

// Destroy the session
session_destroy();

// Clear all session variables
$_SESSION = array();

// Redirect to login page
header("Location: login.php");
exit();
?>