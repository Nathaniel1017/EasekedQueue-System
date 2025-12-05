<?php
require_once 'config.php';
requireLogin();

$page_title = 'My Dashboard';
include 'header.php';

$user = getCurrentUser();

$upcoming_filter = $_GET['upcoming_status'] ?? 'All';
$past_filter = $_GET['past_status'] ?? 'All';

// Get current date and time in correct timezone (Asia/Bangkok)
$current_date = date('Y-m-d');
$current_time = date('H:i:s');

// Get user's upcoming appointments
$conn = getDBConnection();
$user = getCurrentUser();

// Get count of active upcoming appointments (confirmed only)
if ($user['role'] === 'citizen') {
    $count_stmt = $conn->prepare("
        SELECT COUNT(*) as active_count
        FROM appointments a
        WHERE a.user_id = ? AND (a.appointment_date > ? OR (a.appointment_date = ? AND a.appointment_time >= ?)) AND a.status = 'confirmed'
    ");
    $count_stmt->bind_param("isss", $_SESSION['user_id'], $current_date, $current_date, $current_time);
} elseif ($user['role'] === 'admin') {
    $count_stmt = $conn->prepare("
        SELECT COUNT(*) as active_count
        FROM appointments a
        WHERE a.department_id = ? AND (a.appointment_date > ? OR (a.appointment_date = ? AND a.appointment_time >= ?)) AND a.status = 'confirmed'
    ");
    $count_stmt->bind_param("isss", $user['department_id'], $current_date, $current_date, $current_time);
} else {
    // super_admin
    $count_stmt = $conn->prepare("
        SELECT COUNT(*) as active_count
        FROM appointments a
        WHERE (a.appointment_date > ? OR (a.appointment_date = ? AND a.appointment_time >= ?)) AND a.status = 'confirmed'
    ");
    $count_stmt->bind_param("sss", $current_date, $current_date, $current_time);
}
$count_stmt->execute();
$active_count_result = $count_stmt->get_result()->fetch_assoc();
$active_upcoming_count = $active_count_result['active_count'];
$count_stmt->close();
if ($user['role'] === 'citizen') {
    $stmt = $conn->prepare("
        SELECT a.*, d.name as department_name
        FROM appointments a
        JOIN departments d ON a.department_id = d.id
        WHERE a.user_id = ? AND (a.appointment_date > ? OR (a.appointment_date = ? AND a.appointment_time >= ?)) AND (a.status = ? OR ? = 'All')
        ORDER BY a.appointment_date ASC, a.appointment_time ASC
    ");
    $stmt->bind_param("isssss", $_SESSION['user_id'], $current_date, $current_date, $current_time, $upcoming_filter, $upcoming_filter);
} elseif ($user['role'] === 'admin') {
    $stmt = $conn->prepare("
        SELECT a.*, d.name as department_name
        FROM appointments a
        JOIN departments d ON a.department_id = d.id
        WHERE a.department_id = ? AND (a.appointment_date > ? OR (a.appointment_date = ? AND a.appointment_time >= ?)) AND (a.status = ? OR ? = 'All')
        ORDER BY a.appointment_date ASC, a.appointment_time ASC
    ");
    $stmt->bind_param("isssss", $user['department_id'], $current_date, $current_date, $current_time, $upcoming_filter, $upcoming_filter);
} else {
    // super_admin
    $stmt = $conn->prepare("
        SELECT a.*, d.name as department_name
        FROM appointments a
        JOIN departments d ON a.department_id = d.id
        WHERE (a.appointment_date > ? OR (a.appointment_date = ? AND a.appointment_time >= ?)) AND (a.status = ? OR ? = 'All')
        ORDER BY a.appointment_date ASC, a.appointment_time ASC
    ");
    $stmt->bind_param("sssss", $current_date, $current_date, $current_time, $upcoming_filter, $upcoming_filter);
}
$stmt->execute();
$upcoming_appointments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get user's past appointments
if ($user['role'] === 'citizen') {
    $stmt = $conn->prepare("
        SELECT a.*, d.name as department_name
        FROM appointments a
        JOIN departments d ON a.department_id = d.id
        WHERE a.user_id = ? AND (a.appointment_date < ? OR (a.appointment_date = ? AND a.appointment_time < ?)) AND (a.status = ? OR ? = 'All')
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
    ");
    $stmt->bind_param("isssss", $_SESSION['user_id'], $current_date, $current_date, $current_time, $past_filter, $past_filter);
} elseif ($user['role'] === 'admin') {
    $stmt = $conn->prepare("
        SELECT a.*, d.name as department_name
        FROM appointments a
        JOIN departments d ON a.department_id = d.id
        WHERE a.department_id = ? AND (a.appointment_date < ? OR (a.appointment_date = ? AND a.appointment_time < ?)) AND (a.status = ? OR ? = 'All')
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
    ");
    $stmt->bind_param("isssss", $user['department_id'], $current_date, $current_date, $current_time, $past_filter, $past_filter);
} else {
    // super_admin
    $stmt = $conn->prepare("
        SELECT a.*, d.name as department_name
        FROM appointments a
        JOIN departments d ON a.department_id = d.id
        WHERE (a.appointment_date < ? OR (a.appointment_date = ? AND a.appointment_time < ?)) AND (a.status = ? OR ? = 'All')
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
    ");
    $stmt->bind_param("sssss", $current_date, $current_date, $current_time, $past_filter, $past_filter);
}
$stmt->execute();
$past_appointments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();

?>

<style>
    .dashboard-header {
        margin-bottom: 3rem;
    }

    .dashboard-header h1 {
        color: var(--primary-color);
        font-weight: 700;
        font-size: 2rem;
        margin-bottom: 0.5rem;
    }

    .dashboard-header p {
        color: var(--text-secondary);
        font-size: 1.05rem;
    }

    .stat-card {
        border: none;
        box-shadow: var(--card-shadow);
        transition: all 0.3s ease;
        text-align: center;
        background: white;
        border-radius: 12px;
        padding: 2rem;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
    }

    .stat-card:active {
        box-shadow: var(--card-shadow-hover);
        transform: translateY(-4px);
    }

    .stat-icon {
        font-size: 2.5rem;
        color: var(--primary-color);
        margin-bottom: 1rem;
        width: 60px;
        height: 60px;
        background: linear-gradient(135deg, rgba(0, 102, 204, 0.1), rgba(0, 180, 216, 0.1));
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .stat-card.active .stat-icon {
        color: var(--success-color);
        background: linear-gradient(135deg, rgba(6, 214, 160, 0.1), rgba(6, 214, 160, 0.05));
    }

    .stat-card.history .stat-icon {
        color: var(--secondary-color);
        background: linear-gradient(135deg, rgba(0, 180, 216, 0.1), rgba(0, 180, 216, 0.05));
    }

    .stat-label {
        color: var(--text-primary);
        font-weight: 600;
        margin-bottom: 0.75rem;
        font-size: 1rem;
    }

    .stat-value {
        font-size: 2.5rem;
        font-weight: 700;
        background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }

    .stat-card.active .stat-value {
        background: linear-gradient(135deg, var(--success-color), var(--primary-color));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }

    .stat-card.history .stat-value {
        background: linear-gradient(135deg, var(--secondary-color), var(--primary-color));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }

    .appointment-section {
        margin-bottom: 3rem;
    }

    .section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
        gap: 2rem;
    }

    .section-title {
        color: var(--primary-color);
        font-weight: 700;
        font-size: 1.5rem;
        margin-bottom: 0;
    }

    .filter-group {
        display: flex;
        gap: 1rem;
        align-items: center;
    }

    .filter-group label {
        color: var(--text-primary);
        font-weight: 500;
        margin-bottom: 0;
    }

    .appointment-table {
        overflow-x: auto;
    }

    .table {
        margin-bottom: 0;
    }

    .table thead th {
        background-color: var(--light-bg);
        border: none;
        border-bottom: 2px solid var(--border-color);
        padding: 1.25rem 1rem;
        font-weight: 600;
        color: var(--text-primary);
        font-size: 0.95rem;
    }

    .table tbody td {
        border-color: var(--border-color);
        padding: 1.25rem 1rem;
        color: var(--text-primary);
        vertical-align: middle;
    }

    .table tbody tr {
        transition: all 0.2s ease;
        border-bottom: 1px solid var(--border-color);
    }

    .table tbody tr:active {
        background-color: var(--light-bg);
    }

    .appointment-row-actions {
        display: flex;
        gap: 0.5rem;
    }

    .btn-view, .btn-cancel {
        padding: 0.5rem 1rem;
        font-size: 0.85rem;
        border-radius: 6px;
        border: 1px solid var(--border-color);
        background-color: white;
        color: var(--primary-color);
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }

    .btn-view:active {
        background-color: var(--primary-color);
        color: white;
        box-shadow: var(--card-shadow);
    }

    .btn-cancel:active {
        background-color: var(--danger-color);
        color: white;
        box-shadow: var(--card-shadow);
    }

    .empty-state {
        text-align: center;
        padding: 3rem 2rem;
        color: var(--text-secondary);
    }

    .empty-state i {
        font-size: 3rem;
        color: var(--border-color);
        margin-bottom: 1rem;
        display: block;
    }

    .badge {
        padding: 0.5rem 0.75rem;
        font-size: 0.85rem;
        font-weight: 600;
        border-radius: 6px;
    }

    .modal-content {
        border: none;
        box-shadow: var(--card-shadow-hover);
    }

    .modal-header {
        border-bottom: 1px solid var(--border-color);
        background-color: var(--light-bg);
    }

    .modal-title {
        color: var(--primary-color);
        font-weight: 700;
    }

    @media (max-width: 768px) {
        .section-header {
            flex-direction: column;
            align-items: flex-start;
        }

        .filter-group {
            width: 100%;
        }

        .filter-group select {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%230066cc' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2 5l6 6 6-6'/%3e%3c/svg%3e") !important;
            background-repeat: no-repeat !important;
            background-position: right 0.5rem center !important;
            background-size: 0.875rem !important;
            padding-right: 2rem !important;
            min-width: 180px;
            max-width: 250px;
        }

        .appointment-row-actions {
            flex-direction: column;
        }

        .btn-view, .btn-cancel {
            width: 100%;
            justify-content: center;
        }
    }
</style>

<div class="dashboard-header">
    <h1><i class="fas fa-chart-line"></i> Welcome back, <?php echo htmlspecialchars($user['full_name']); ?>!</h1>
    <p>Manage your appointments and stay updated with your bookings</p>
</div>

<div class="row mb-5">
    <div class="col-md-4 mb-4">
        <div class="stat-card card">
            <div class="stat-icon">
                <i class="fas fa-calendar-plus"></i>
            </div>
            <p class="stat-label">Book New Appointment</p>
            <a href="book_appointment.php" class="btn btn-primary btn-custom" style="min-width: 150px;">
                <i class="fas fa-plus"></i> Book Now
            </a>
        </div>
    </div>
    <div class="col-md-4 mb-4">
        <div class="stat-card card active">
            <div class="stat-icon">
                <i class="fas fa-calendar-check"></i>
            </div>
            <p class="stat-label">Upcoming Appointments</p>
            <div class="stat-value"><?php echo $active_upcoming_count; ?></div>
        </div>
    </div>
    <div class="col-md-4 mb-4">
        <div class="stat-card card history">
            <div class="stat-icon">
                <i class="fas fa-history"></i>
            </div>
            <p class="stat-label">Past Appointments</p>
            <div class="stat-value"><?php echo count($past_appointments); ?></div>
        </div>
    </div>
</div>

<div class="appointment-section">
    <div class="section-header">
        <h3 class="section-title">
            <i class="fas fa-calendar-alt"></i> Upcoming Appointments
        </h3>
        <form method="GET" class="filter-group">
            <input type="hidden" name="past_status" value="<?php echo htmlspecialchars($past_filter); ?>">
            <label for="upcoming_status">Filter by Status:</label>
            <select name="upcoming_status" id="upcoming_status" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="All" <?php echo $upcoming_filter === 'All' ? 'selected' : ''; ?>>All</option>
                <option value="pending" <?php echo $upcoming_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                <option value="confirmed" <?php echo $upcoming_filter === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                <option value="declined" <?php echo $upcoming_filter === 'declined' ? 'selected' : ''; ?>>Declined</option>
                <option value="cancelled" <?php echo $upcoming_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
            </select>
        </form>
    </div>

    <div class="card">
        <div class="card-body">
            <?php if (empty($upcoming_appointments)): ?>
                <div class="empty-state">
                    <i class="fas fa-calendar-times"></i>
                    <h5>No Upcoming Appointments</h5>
                    <p>You don't have any upcoming appointments scheduled.</p>
                    <a href="book_appointment.php" class="btn btn-primary btn-custom">
                        <i class="fas fa-calendar-plus"></i> Book Your First Appointment
                    </a>
                </div>
            <?php else: ?>
                <div class="appointment-table">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Service</th>
                                <th>Date & Time</th>
                                <th>Purpose</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($upcoming_appointments as $appointment): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($appointment['department_name']); ?></strong>
                                    </td>
                                    <td>
                                        <i class="fas fa-calendar"></i> <?php echo date('M j, Y', strtotime($appointment['appointment_date'])); ?>
                                        <br>
                                        <i class="fas fa-clock"></i> <?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($appointment['agenda']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo $appointment['status'] === 'confirmed' ? 'success' : 
                                                 ($appointment['status'] === 'pending' ? 'warning' : 
                                                 ($appointment['status'] === 'declined' ? 'danger' : 'secondary')); 
                                        ?>">
                                            <?php echo ucfirst($appointment['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="appointment-row-actions">
                                            <button class="btn-view" onclick="viewAppointment(<?php echo $appointment['id']; ?>)">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                            <?php if ($appointment['status'] === 'pending' || $appointment['status'] === 'confirmed'): ?>
                                                <button class="btn-cancel" onclick="cancelAppointment(<?php echo $appointment['id']; ?>)">
                                                    <i class="fas fa-times"></i> Cancel
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="appointment-section">
    <div class="section-header">
        <h3 class="section-title">
            <i class="fas fa-history"></i> Appointment History
        </h3>
        <form method="GET" class="filter-group">
            <input type="hidden" name="upcoming_status" value="<?php echo htmlspecialchars($upcoming_filter); ?>">
            <label for="past_status">Filter by Status:</label>
            <select name="past_status" id="past_status" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="All" <?php echo $past_filter === 'All' ? 'selected' : ''; ?>>All</option>
                <option value="declined" <?php echo $past_filter === 'declined' ? 'selected' : ''; ?>>Declined</option>
                <option value="completed" <?php echo $past_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                <option value="cancelled" <?php echo $past_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                <option value="unattended" <?php echo $past_filter === 'unattended' ? 'selected' : ''; ?>>Unattended</option>
            </select>
        </form>
    </div>

    <div class="card">
        <div class="card-body">
            <?php if (empty($past_appointments)): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h5>No Past Appointments</h5>
                    <p>You don't have any past appointments to display.</p>
                </div>
            <?php else: ?>
                <div class="appointment-table">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Service</th>
                                <th>Date & Time</th>
                                <th>Purpose</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($past_appointments as $appointment): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($appointment['department_name']); ?></strong>
                                    </td>
                                    <td>
                                        <i class="fas fa-calendar"></i> <?php echo date('M j, Y', strtotime($appointment['appointment_date'])); ?>
                                        <br>
                                        <i class="fas fa-clock"></i> <?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($appointment['agenda']); ?>
                                        <?php if ($appointment['status'] === 'declined' && !empty($appointment['decline_reason'])): ?>
                                            <br><small style="color: var(--danger-color);"><i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($appointment['decline_reason']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php
                                            echo $appointment['status'] === 'completed' ? 'success' :
                                                 ($appointment['status'] === 'cancelled' ? 'secondary' :
                                                 ($appointment['status'] === 'unattended' ? 'warning' : 'danger'));
                                        ?>">
                                            <?php echo ucfirst($appointment['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn-view" onclick="viewAppointment(<?php echo $appointment['id']; ?>)">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Appointment Details Modal -->
<div class="modal fade" id="appointmentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-info-circle"></i> Appointment Details</h5>
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
    // Load appointment details via AJAX
    fetch('get_appointment_details.php?id=' + appointmentId, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                alert(data.error);
                return;
            }
            const appointment = data.appointment;
            const canEditPriority = data.can_edit_priority;
            const isJustCreated = data.is_just_created;
            const isRecentlyCreated = data.is_recently_created;
            const creationDisplay = data.creation_display;
            const creationStatus = data.creation_status;

            // Build status badge
            let statusClass = 'secondary';
            if (appointment.status === 'confirmed' || appointment.status === 'completed') {
                statusClass = 'success';
            } else if (appointment.status === 'pending') {
                statusClass = 'warning';
            } else if (appointment.status === 'declined') {
                statusClass = 'danger';
            } else if (appointment.status === 'unattended') {
                statusClass = 'warning';
            }

            // Build creation status badge
            let creationBadgeHTML = '';
            if (isRecentlyCreated) {
                creationBadgeHTML = '<span class="badge bg-success ms-2">✓ Just Created</span>';
            }

            // Build HTML
            let html = `
                <div class="row">
                    <div class="col-md-6">
                        <h6 style="color: var(--primary-color); font-weight: 600; margin-bottom: 1rem;">Appointment Information</h6>
                        <p><strong>ID:</strong> #${appointment.id}</p>
                        <p><strong>Service:</strong> ${appointment.department_name}</p>
                        <p><strong>Date & Time:</strong> ${new Date(appointment.appointment_date + ' ' + appointment.appointment_time).toLocaleString()}</p>
                        <p><strong>Status:</strong>
                            <span class="badge bg-${statusClass}">${appointment.status.charAt(0).toUpperCase() + appointment.status.slice(1)}</span>
                        </p>
                    </div>
                    <div class="col-md-6">
                        <h6 style="color: var(--primary-color); font-weight: 600; margin-bottom: 1rem;">Personal Information</h6>
                        <p><strong>Appointment For:</strong> ${appointment.appointment_for === 'me' ? 'Myself' : 'Someone Else'}</p>
                        ${appointment.appointment_for === 'someone_else' ? `
                            <p><strong>Name:</strong> ${appointment.recipient_name}</p>
                            <p><strong>Phone:</strong> ${appointment.recipient_phone}</p>
                            <p><strong>Email:</strong> ${appointment.recipient_email}</p>
                        ` : ''}
                        <p><strong>Purpose:</strong> ${appointment.agenda === 'Other' ? 'Other - ' + (appointment.custom_agenda || 'Not specified') : appointment.agenda}</p>
                        <p><strong>PWD:</strong> ${appointment.is_pwd ? 'Yes' : 'No'}</p>
                        ${appointment.pwd_proof ? `<p><strong>PWD Proof:</strong> <a href="${appointment.pwd_proof}" target="_blank">View Proof</a></p>` : ''}
                        <p><strong>Priority:</strong>
                            ${canEditPriority ? `
                                <select class="form-select form-select-sm d-inline-block w-auto ms-2" onchange="updatePriority(${appointment.id}, this.value)">
                                    <option value="normal" ${appointment.priority_status === 'normal' ? 'selected' : ''}>Normal</option>
                                    <option value="priority" ${appointment.priority_status === 'priority' ? 'selected' : ''}>Priority</option>
                                </select>
                            ` : appointment.priority_status.charAt(0).toUpperCase() + appointment.priority_status.slice(1)}
                        </p>
                        ${appointment.uploaded_file_path ? `<p><strong>Uploaded File:</strong> <a href="${appointment.uploaded_file_path}" target="_blank">View File</a></p>` : ''}
                        ${appointment.notes ? `<p><strong>Notes:</strong> ${appointment.notes}</p>` : ''}
                        ${appointment.status === 'declined' && appointment.decline_reason ? `<p><strong>Reason for Decline:</strong> ${appointment.decline_reason}</p>` : ''}
                    </div>
                </div>
                <hr style="margin: 1rem 0;">
                <p><strong>Created:</strong> ${creationDisplay} ${creationBadgeHTML}
                </p>
            `;

            document.getElementById('appointmentDetails').innerHTML = html;
            new bootstrap.Modal(document.getElementById('appointmentModal')).show();
        })
        .catch(error => {
            console.error('Error loading appointment details:', error);
            alert('Failed to load appointment details.');
        });
}

function cancelAppointment(appointmentId) {
    if (confirm('Are you sure you want to cancel this appointment?')) {
        fetch('cancel_appointment.php', {
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
                alert('Failed to cancel appointment: ' + data.message);
            }
        });
    }
}
</script>

<?php include 'footer.php'; ?>