<?php
require_once 'config.php';
requireLogin();

header('Content-Type: application/json');

if (!isset($_POST['appointment_id'])) {
    echo json_encode(['success' => false, 'message' => 'Appointment ID required']);
    exit();
}

$appointment_id = (int)$_POST['appointment_id'];

$conn = getDBConnection();

// Check if appointment belongs to user and can be cancelled
$stmt = $conn->prepare("SELECT status, appointment_date, appointment_time FROM appointments WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $appointment_id, $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Appointment not found']);
    exit();
}

$appointment = $result->fetch_assoc();

// Check if appointment can be cancelled (not too close to appointment time)
$current_datetime = new DateTime();
$appointment_datetime = new DateTime($appointment['appointment_date'] . ' ' . $appointment['appointment_time']);
$interval = $current_datetime->diff($appointment_datetime);

if ($interval->days === 0 && $interval->h < 2) {
    echo json_encode(['success' => false, 'message' => 'Cannot cancel appointment less than 2 hours before']);
    exit();
}

if ($appointment['status'] !== 'pending' && $appointment['status'] !== 'confirmed') {
    echo json_encode(['success' => false, 'message' => 'Cannot cancel this appointment']);
    exit();
}

// Update appointment status to cancelled
$stmt = $conn->prepare("UPDATE appointments SET status = 'cancelled' WHERE id = ?");
$stmt->bind_param("i", $appointment_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Appointment cancelled successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to cancel appointment']);
}

$stmt->close();
$conn->close();
?>