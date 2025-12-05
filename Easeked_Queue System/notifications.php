<?php
require_once 'config.php';
requireLogin();

$page_title = 'Notifications';
include 'header.php';

$user = getCurrentUser();

$notifications = [];

// Get notifications for the current user
$conn = getDBConnection();
$stmt = $conn->prepare("
    SELECT n.*, a.appointment_date, a.appointment_time, d.name as department_name
    FROM notifications n
    JOIN appointments a ON n.appointment_id = a.id
    JOIN departments d ON a.department_id = d.id
    WHERE n.user_id = ?
    ORDER BY n.created_at DESC
    LIMIT 50
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $notifications[] = [
        'id' => $row['appointment_id'], // appointment id
        'message' => $row['message'],
        'department' => $row['department_name'],
        'date' => $row['appointment_date'],
        'time' => $row['appointment_time'],
        'notification_date' => $row['created_at'],
        'type' => $row['for_admin_id'] ? 'admin_action' : 'new_appointment', // determine type based on for_admin_id
        'is_read' => $row['is_read']
    ];
}

$stmt->close();
$conn->close();
?>

<div class="row">
    <div class="col-12">
        <h2>Notifications</h2>
        <p class="lead">Stay updated on your appointment status.</p>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="mb-0">Recent Notifications</h4>
                <?php if (!empty($notifications)): ?>
                    <button class="btn btn-sm btn-outline-primary" onclick="markAllAsRead()">
                        <i class="fas fa-check-double me-2"></i>Mark All as Read
                    </button>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (empty($notifications)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-bell-slash fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No notifications yet.</p>
                        <p class="text-muted">You'll receive notifications when your appointment status changes.</p>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($notifications as $notification): ?>
                            <div class="list-group-item <?php echo !$notification['is_read'] ? 'bg-light' : ''; ?>">
                                <div class="d-flex w-100 justify-content-between">
                                    <div>
                                        <h6 class="mb-1 <?php echo !$notification['is_read'] ? 'fw-bold' : ''; ?>">
                                            <i class="fas fa-<?php
                                                echo $notification['type'] === 'admin_action' ? 'user-shield text-primary' : 'bell text-info';
                                            ?> me-2"></i>
                                            <?php echo htmlspecialchars($notification['message']); ?>
                                        </h6>
                                        <p class="mb-1 text-muted">
                                            Department: <?php echo htmlspecialchars($notification['department']); ?><br>
                                            Appointment: <?php echo date('F j, Y \a\t g:i A', strtotime($notification['date'] . ' ' . $notification['time'])); ?>
                                        </p>
                                    </div>
                                    <small class="text-muted">
                                        <?php echo date('M j, Y g:i A', strtotime($notification['notification_date'])); ?>
                                    </small>
                                </div>
                                <div class="mt-2">
                                    <a href="get_appointment_details.php?id=<?php echo $notification['id']; ?>"
                                       class="btn btn-sm btn-outline-primary"
                                       onclick="viewAppointment(<?php echo $notification['id']; ?>); return false;">
                                        <i class="fas fa-eye"></i> View Details
                                    </a>
                                    <?php if (!$notification['is_read']): ?>
                                        <button class="btn btn-sm btn-outline-secondary ms-2" onclick="markAsRead(<?php echo $notification['id']; ?>)">
                                            <i class="fas fa-check"></i> Mark Read
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Appointment Details Modal -->
<div class="modal fade" id="appointmentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Appointment Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="appointmentDetails">
                <!-- Details will be loaded here -->
            </div>
        </div>
    </div>
</div>

<script>
function viewAppointment(appointmentId) {
    fetch('get_appointment_details.php?id=' + appointmentId)
        .then(response => response.text())
        .then(data => {
            document.getElementById('appointmentDetails').innerHTML = data;
            new bootstrap.Modal(document.getElementById('appointmentModal')).show();
        });
}

function markAsRead(appointmentId) {
    fetch('mark_notification_read.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'appointment_id=' + appointmentId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Failed to mark notification as read: ' + data.message);
        }
    });
}

function markAllAsRead() {
    if (confirm('Are you sure you want to mark all notifications as read?')) {
        fetch('mark_all_notifications_read.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Failed to mark all notifications as read: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while marking notifications as read.');
        });
    }
}
</script>

<?php include 'footer.php'; ?>