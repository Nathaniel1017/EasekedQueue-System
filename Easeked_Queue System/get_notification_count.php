<?php
require_once 'config.php';
requireLogin();

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'];

$conn = getDBConnection();
if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

$stmt = $conn->prepare("SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = ? AND is_read = FALSE");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$count = $result->fetch_assoc()['unread_count'];

$stmt->close();
$conn->close();

echo json_encode(['success' => true, 'unread_count' => $count]);
?>