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

$stmt = $conn->prepare("
    SELECT n.message, n.appointment_id, n.created_at, n.is_read
    FROM notifications n
    WHERE n.user_id = ?
    ORDER BY n.created_at DESC
    LIMIT 10
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$notifications = [];
while ($row = $result->fetch_assoc()) {
    $notifications[] = [
        'message' => $row['message'],
        'appointment_id' => $row['appointment_id'],
        'created_at' => $row['created_at'],
        'is_read' => $row['is_read']
    ];
}

$stmt->close();
$conn->close();

echo json_encode(['success' => true, 'notifications' => $notifications]);
?>