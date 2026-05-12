<?php
session_start();
include 'db.php';

header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

$response = [
    'logged_in' => isset($_SESSION['user_id']),
    'user_id' => $_SESSION['user_id'] ?? null,
    'db_connection' => $conn ? 'connected' : 'failed',
    'session_data' => $_SESSION
];

echo json_encode($response, JSON_PRETTY_PRINT);
?>
