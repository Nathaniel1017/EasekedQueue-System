<?php
require_once 'config.php';
requireLogin();

header('Content-Type: application/json');

if (!isset($_POST['appointment_id'])) {
    echo json_encode(['success' => false, 'message' => 'Appointment ID required']);
    exit();
}

$appointment_id = (int)$_POST['appointment_id'];
$user_id = $_SESSION['user_id'];

$conn = getDBConnection();

$stmt = $conn->prepare("UPDATE notifications SET is_read = TRUE WHERE user_id = ? AND appointment_id = ?");
$stmt->bind_param("ii", $user_id, $appointment_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Notification marked as read']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to mark notification as read']);
}

$stmt->close();
$conn->close();
?>