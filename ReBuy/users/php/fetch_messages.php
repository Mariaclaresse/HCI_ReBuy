<?php
session_start();
include 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit();
}

$user_id = $_SESSION['user_id'];
$other_user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$last_message_id = isset($_GET['last_id']) ? intval($_GET['last_id']) : 0;

if ($other_user_id <= 0) {
    echo json_encode(['error' => 'Invalid user ID']);
    exit();
}

// Check if messages table exists
$table_check = $conn->query("SHOW TABLES LIKE 'messages'");
if (!$table_check || $table_check->num_rows == 0) {
    echo json_encode(['error' => 'Messages table not found']);
    exit();
}

// Fetch new messages
$stmt = $conn->prepare("
    SELECT m.*, 
           u.username as sender_username,
           u.first_name as sender_first_name,
           u.last_name as sender_last_name
    FROM messages m
    JOIN users u ON m.sender_id = u.id
    WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))
    AND m.id > ?
    ORDER BY m.created_at ASC
");
$stmt->bind_param("iiiii", $user_id, $other_user_id, $other_user_id, $user_id, $last_message_id);
$stmt->execute();
$result = $stmt->get_result();

$messages = [];
while ($row = $result->fetch_assoc()) {
    $messages[] = [
        'id' => $row['id'],
        'sender_id' => $row['sender_id'],
        'message' => htmlspecialchars($row['message']),
        'image_url' => $row['image_url'],
        'is_read' => $row['is_read'],
        'created_at' => date('H:i', strtotime($row['created_at'])),
        'is_sent' => $row['sender_id'] == $user_id
    ];
}

$stmt->close();

// Mark received messages as read
$update_stmt = $conn->prepare("UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND is_read = 0");
$update_stmt->bind_param("ii", $other_user_id, $user_id);
$update_stmt->execute();
$update_stmt->close();

echo json_encode(['messages' => $messages]);
?>
