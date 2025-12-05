<?php
require_once 'config.php';
requireLogin();
// Removed requireAdmin() to allow citizens to view their own appointments

if (!isset($_GET['id'])) {
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Appointment ID required']);
        exit;
    } else {
        die('Appointment ID required');
    }
}

$appointment_id = (int)$_GET['id'];

$user = getCurrentUser();
$conn = getDBConnection();

// Build query based on user role
$query = "
    SELECT a.*, d.name as department_name, u.full_name as user_name
    FROM appointments a
    JOIN departments d ON a.department_id = d.id
    JOIN users u ON a.user_id = u.id
    WHERE a.id = ?
";

$params = [$appointment_id];
$types = "i";

if ($user['role'] === 'admin') {
    // Department admin can only view appointments in their department
    $query .= " AND a.department_id = ?";
    $params[] = $user['department_id'];
    $types .= "i";
} elseif ($user['role'] === 'citizen') {
    // Citizens can only view their own appointments
    $query .= " AND a.user_id = ?";
    $params[] = $_SESSION['user_id'];
    $types .= "i";
}
// Super admin can view all appointments (no additional filter)

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$appointment = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

if (!$appointment) {
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Appointment not found']);
        exit;
    } else {
        die('Appointment not found');
    }
}

// Check if this is an AJAX request
$is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if ($is_ajax) {
    // Return JSON data with real-time creation checking
    header('Content-Type: application/json');
    
    // Calculate creation recency (in seconds)
    $created_at = strtotime($appointment['created_at']);
    $current_time = time();
    $seconds_since_creation = $current_time - $created_at;
    $is_just_created = $seconds_since_creation < 60; // Less than 1 minute
    $is_recently_created = $seconds_since_creation < 300; // Less than 5 minutes
    
    // Format creation time display
    if ($is_just_created) {
        $creation_display = 'Just now';
        $creation_status = 'just-created';
    } elseif ($seconds_since_creation < 60) {
        $creation_display = 'Less than a minute ago';
        $creation_status = 'just-created';
    } elseif ($seconds_since_creation < 3600) {
        $minutes = floor($seconds_since_creation / 60);
        $creation_display = $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
        $creation_status = 'recently-created';
    } else {
        $creation_display = date('F j, Y \a\t g:i A', $created_at);
        $creation_status = 'standard';
    }
    
    $response = [
        'appointment' => $appointment,
        'can_edit_priority' => in_array($user['role'], ['admin', 'super_admin']),
        'user_role' => $user['role'],
        'is_just_created' => $is_just_created,
        'is_recently_created' => $is_recently_created,
        'creation_display' => $creation_display,
        'creation_status' => $creation_status,
        'seconds_since_creation' => $seconds_since_creation
    ];
    echo json_encode($response);
    exit;
}
?>

<div class="row">
    <div class="col-md-6">
        <h6>Appointment Information</h6>
        <p><strong>ID:</strong> #<?php echo $appointment['id']; ?></p>
        <p><strong>Department:</strong> <?php echo htmlspecialchars($appointment['department_name']); ?></p>
        <p><strong>Date & Time:</strong> <?php echo date('F j, Y \a\t g:i A', strtotime($appointment['appointment_date'] . ' ' . $appointment['appointment_time'])); ?></p>
        <p><strong>Status:</strong> 
            <span class="badge bg-<?php
                echo $appointment['status'] === 'confirmed' ? 'success' :
                     ($appointment['status'] === 'pending' ? 'warning' :
                     ($appointment['status'] === 'declined' ? 'danger' :
                     ($appointment['status'] === 'completed' ? 'success' :
                     ($appointment['status'] === 'unattended' ? 'warning' : 'secondary'))));
            ?>">
                <?php echo ucfirst($appointment['status']); ?>
            </span>
        </p>
    </div>
    <div class="col-md-6">
        <h6>Personal Information</h6>
        <p><strong>Appointment For:</strong> <?php echo $appointment['appointment_for'] === 'me' ? 'Myself' : 'Someone Else'; ?></p>
        <?php if ($appointment['appointment_for'] === 'someone_else'): ?>
            <p><strong>Name:</strong> <?php echo htmlspecialchars($appointment['recipient_name']); ?></p>
            <p><strong>Phone:</strong> <?php echo htmlspecialchars($appointment['recipient_phone']); ?></p>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($appointment['recipient_email']); ?></p>
        <?php endif; ?>
        <p><strong>Purpose:</strong> <?php
            // If agenda is "Other", show the custom description, otherwise show the selected agenda
            if ($appointment['agenda'] === 'Other') {
                echo 'Other - ' . htmlspecialchars($appointment['custom_agenda'] ?? 'Not specified');
            } else {
                echo htmlspecialchars($appointment['agenda']);
            }
        ?></p>
        <p><strong>PWD:</strong> <?php echo $appointment['is_pwd'] ? 'Yes' : 'No'; ?></p>
        <?php if ($appointment['pwd_proof']): ?>
            <p><strong>PWD Proof:</strong> <a href="<?php echo htmlspecialchars($appointment['pwd_proof']); ?>" target="_blank">View Proof</a></p>
        <?php endif; ?>
        <p><strong>Priority:</strong>
            <?php if (in_array($user['role'], ['admin', 'super_admin'])): ?>
                <select class="form-select form-select-sm d-inline-block w-auto ms-2" onchange="updatePriority(<?php echo $appointment['id']; ?>, this.value)">
                    <option value="normal" <?php echo $appointment['priority_status'] === 'normal' ? 'selected' : ''; ?>>Normal</option>
                    <option value="priority" <?php echo $appointment['priority_status'] === 'priority' ? 'selected' : ''; ?>>Priority</option>
                </select>
            <?php else: ?>
                <?php echo ucfirst($appointment['priority_status']); ?>
            <?php endif; ?>
        </p>
        <?php if ($appointment['uploaded_file_path']): ?>
            <p><strong>Uploaded File:</strong> <a href="<?php echo htmlspecialchars($appointment['uploaded_file_path']); ?>" target="_blank">View File</a></p>
        <?php endif; ?>
        <?php if ($appointment['notes']): ?>
            <p><strong>Notes:</strong> <?php echo htmlspecialchars($appointment['notes']); ?></p>
        <?php endif; ?>
        <?php if ($appointment['status'] === 'declined' && $appointment['decline_reason']): ?>
            <p><strong>Reason for Decline:</strong> <?php echo htmlspecialchars($appointment['decline_reason']); ?></p>
        <?php endif; ?>
    </div>
</div>
<p><strong>Created:</strong> <?php echo date('F j, Y \a\t g:i A', strtotime($appointment['created_at'])); ?></p>