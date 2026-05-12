<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Check if user has confirmed the action (via GET parameter)
if (!isset($_GET['confirm']) || $_GET['confirm'] !== 'yes') {
    $_SESSION['error'] = "Please confirm you want to clear your entire wishlist.";
    header("Location: wishlist.php");
    exit();
}

// Count items before deletion for feedback
$count_stmt = $conn->prepare("SELECT COUNT(*) as item_count FROM wishlist WHERE user_id = ?");
$count_stmt->bind_param("i", $user_id);
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$item_count = $count_result->fetch_assoc()['item_count'];
$count_stmt->close();

if ($item_count == 0) {
    $_SESSION['error'] = "Your wishlist is already empty.";
    header("Location: wishlist.php");
    exit();
}

// Delete all wishlist items for the user
$stmt = $conn->prepare("DELETE FROM wishlist WHERE user_id = ?");
$stmt->bind_param("i", $user_id);

if ($stmt->execute()) {
    $_SESSION['success'] = "Successfully cleared {$item_count} item(s) from your wishlist!";
} else {
    $_SESSION['error'] = "Failed to clear wishlist. Please try again.";
}

$stmt->close();
header("Location: wishlist.php");
exit();
?>
