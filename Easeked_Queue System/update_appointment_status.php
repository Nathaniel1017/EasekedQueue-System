<?php
require_once 'config.php';
requireAdmin();

header('Content-Type: application/json');

if (!isset($_POST['appointment_id'])) {
    echo json_encode(['success' => false, 'message' => 'Appointment ID required']);
    exit();
}

$appointment_id = (int)$_POST['appointment_id'];
$status = isset($_POST['status']) ? $_POST['status'] : null;
$priority_status = isset($_POST['priority_status']) ? $_POST['priority_status'] : null;
$decline_reason = isset($_POST['decline_reason']) ? trim($_POST['decline_reason']) : null;

if ($status && !in_array($status, ['pending', 'confirmed', 'declined', 'completed', 'cancelled', 'unattended'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit();
}

if ($priority_status && !in_array($priority_status, ['normal', 'priority'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid priority status']);
    exit();
}

if (!$status && !$priority_status) {
    echo json_encode(['success' => false, 'message' => 'Status or priority status required']);
    exit();
}

$conn = getDBConnection();

// Check if admin has permission to manage this appointment
$user = getCurrentUser();
if ($user['role'] === 'admin') {
    // Department admin can only manage appointments in their department
    $stmt = $conn->prepare("SELECT department_id FROM appointments WHERE id = ?");
    // Department admin can only manage appointments in their department
    $stmt = $conn->prepare("SELECT department_id FROM appointments WHERE id = ?");
    $stmt->bind_param("i", $appointment_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Appointment not found']);
        exit();
    }

    $appointment_dept = $result->fetch_assoc()['department_id'];
    if ($appointment_dept != $user['department_id']) {
        echo json_encode(['success' => false, 'message' => 'You do not have permission to manage this appointment']);
        exit();
    }
    $stmt->close();
}

// Check for time slot conflict if confirming an appointment
if ($status === 'confirmed') {
    // Get appointment details
    $check_stmt = $conn->prepare("SELECT appointment_date, appointment_time, department_id FROM appointments WHERE id = ?");
    $check_stmt->bind_param("i", $appointment_id);
    $check_stmt->execute();
    $appointment_data = $check_stmt->get_result()->fetch_assoc();
    $check_stmt->close();

    if ($appointment_data) {
        // Check if another confirmed appointment exists at the same date, time, and department
        $conflict_stmt = $conn->prepare("SELECT COUNT(*) as count FROM appointments WHERE appointment_date = ? AND appointment_time = ? AND department_id = ? AND status = 'confirmed' AND id != ?");
        $conflict_stmt->bind_param("ssii", $appointment_data['appointment_date'], $appointment_data['appointment_time'], $appointment_data['department_id'], $appointment_id);
        $conflict_stmt->execute();
        $conflict_result = $conflict_stmt->get_result();
        $conflict_count = $conflict_result->fetch_assoc()['count'];
        $conflict_stmt->close();

        if ($conflict_count > 0) {
            echo json_encode(['success' => false, 'message' => 'This time slot is already taken. Cannot confirm this appointment.']);
            $conn->close();
            exit();
        }
    }
}

// Update appointment status or priority
$set_parts = [];
$params = [];
$types = '';

if ($status) {
    $set_parts[] = "status = ?";
    $params[] = $status;
    $types .= 's';
}

if ($priority_status) {
    $set_parts[] = "priority_status = ?";
    $params[] = $priority_status;
    $types .= 's';
}

if ($status === 'declined' && !empty($decline_reason)) {
    $set_parts[] = "decline_reason = ?";
    $params[] = $decline_reason;
    $types .= 's';
}

$set_parts[] = "updated_at = CURRENT_TIMESTAMP";
$params[] = $appointment_id;
$types .= 'i';

$query = "UPDATE appointments SET " . implode(", ", $set_parts) . " WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);

if ($stmt->execute()) {
    if ($status) {
        // Create notification for status change
        notifyStatusChange($appointment_id, $status, $user['id'], $decline_reason);

        $status_messages = [
            'confirmed' => 'Appointment confirmed!',
            'declined' => 'Appointment declined!',
            'completed' => 'Appointment marked as completed!',
            'cancelled' => 'Appointment cancelled!',
            'unattended' => 'Appointment marked as unattended!',
            'pending' => 'Appointment status reset to pending!'
        ];
        $message = $status_messages[$status] ?? 'Appointment status updated successfully';
    } elseif ($priority_status) {
        $message = 'Appointment priority updated to ' . ucfirst($priority_status) . '!';
    } else {
        $message = 'Appointment updated successfully';
    }
    echo json_encode(['success' => true, 'message' => $message]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update appointment status']);
}

$stmt->close();
$conn->close();
?>