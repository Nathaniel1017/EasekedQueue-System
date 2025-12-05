<?php
require_once 'config.php';
requireRole('super_admin');

$type = $_GET['type'] ?? 'appointments';
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');

$conn = getDBConnection();

if (!$conn) {
    die("Database connection failed.");
}

if ($type === 'appointments') {
    // Export appointments data
    $stmt = $conn->prepare("
        SELECT
            a.id,
            u.full_name as user_name,
            u.email as user_email,
            u.phone_number as user_phone,
            d.name as department_name,
            a.appointment_date,
            a.appointment_time,
            a.agenda,
            a.appointment_for,
            a.recipient_name,
            a.recipient_phone,
            a.recipient_email,
            a.status,
            a.uploaded_file_path,
            a.notes,
            a.created_at
        FROM appointments a
        JOIN users u ON a.user_id = u.id
        JOIN departments d ON a.department_id = d.id
        WHERE DATE(a.created_at) BETWEEN ? AND ?
        ORDER BY a.created_at DESC
    ");
    if ($stmt === false) {
        die("Database query preparation failed: " . $conn->error);
    }
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result === false) {
        die("Query execution failed: " . $stmt->error);
    }

    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="appointments_' . $start_date . '_to_' . $end_date . '.csv"');

    // Output CSV headers
    $output = fopen('php://output', 'w');
    fputcsv($output, [
        'Appointment ID',
        'User Name',
        'User Email',
        'User Phone',
        'Department',
        'Appointment Date',
        'Appointment Time',
        'Purpose',
        'For',
        'Recipient Name',
        'Recipient Phone',
        'Recipient Email',
        'Status',
        'File Uploaded',
        'Notes',
        'Created At'
    ]);

    // Output data
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $row['id'],
            $row['user_name'],
            $row['user_email'],
            $row['user_phone'],
            $row['department_name'],
            $row['appointment_date'],
            $row['appointment_time'],
            $row['agenda'],
            $row['appointment_for'],
            $row['recipient_name'],
            $row['recipient_phone'],
            $row['recipient_email'],
            $row['status'],
            $row['uploaded_file_path'] ? 'Yes' : 'No',
            $row['notes'],
            $row['created_at']
        ]);
    }

    fclose($output);
    $stmt->close();

} elseif ($type === 'users') {
    // Export users data
    $stmt = $conn->prepare("
        SELECT
            id,
            full_name,
            email,
            phone_number,
            role,
            department_id,
            is_active,
            created_at
        FROM users
        WHERE DATE(created_at) BETWEEN ? AND ?
        ORDER BY created_at DESC
    ");
    if ($stmt === false) {
        die("Database query preparation failed: " . $conn->error);
    }
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result === false) {
        die("Query execution failed: " . $stmt->error);
    }

    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="users_' . $start_date . '_to_' . $end_date . '.csv"');

    // Output CSV headers
    $output = fopen('php://output', 'w');
    fputcsv($output, [
        'User ID',
        'Full Name',
        'Email',
        'Phone Number',
        'Role',
        'Department ID',
        'Active',
        'Created At'
    ]);

    // Output data
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $row['id'],
            $row['full_name'],
            $row['email'],
            $row['phone_number'],
            $row['role'],
            $row['department_id'],
            $row['is_active'] ? 'Yes' : 'No',
            $row['created_at']
        ]);
    }

    fclose($output);
    $stmt->close();
}

$conn->close();
exit();
?>