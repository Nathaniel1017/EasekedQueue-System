<?php
require_once 'config.php';
requireAdmin();

$page_title = 'Manage Appointments';
include 'header.php';

$user = getCurrentUser();

// Handle filters
$status_filter = $_GET['status'] ?? '';
$date_filter = $_GET['date'] ?? '';
$department_filter = $_GET['department'] ?? '';

// Build query
$query = "
    SELECT a.*, d.name as department_name, u.full_name as user_name, u.email as user_email
    FROM appointments a 
    JOIN departments d ON a.department_id = d.id 
    JOIN users u ON a.user_id = u.id 
";

$conditions = [];
$params = [];
$types = '';

if ($user['role'] === 'admin') {
    // Department admin can only see their department's appointments
    $conditions[] = "a.department_id = ?";
    $params[] = $user['department_id'];
    $types .= 'i';
}

if (!empty($status_filter)) {
    $conditions[] = "a.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if (!empty($date_filter)) {
    $conditions[] = "a.appointment_date = ?";
    $params[] = $date_filter;
    $types .= 's';
}

if (!empty($department_filter) && $user['role'] === 'super_admin') {
    $conditions[] = "a.department_id = ?";
    $params[] = $department_filter;
    $types .= 'i';
}

if (!empty($conditions)) {
    $query .= " WHERE " . implode(" AND ", $conditions);
}

$query .= " ORDER BY a.appointment_date DESC, a.appointment_time DESC";

$conn = getDBConnection();
$stmt = $conn->prepare($query);
$conn = getDBConnection();
$stmt = $conn->prepare($query);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$appointments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get departments for filter dropdown
$departments = [];
if ($user['role'] === 'super_admin') {
    $result = $conn->query("SELECT id, name FROM departments ORDER BY name");
    while ($row = $result->fetch_assoc()) {
        $departments[] = $row;
    }
}

$conn->close();
?>

<style>
.manage-header {
    color: var(--primary-color);
    font-size: 2rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
}

.filter-card {
    border-radius: 12px;
    box-shadow: var(--card-shadow);
    border: none;
    margin-bottom: 2rem;
    background-color: white;
    overflow: hidden;
}

.filter-card .card-header {
    background-color: var(--light-bg);
    border-bottom: 1px solid var(--border-color);
    padding: 1.5rem 2rem;
    margin: 0;
}

.filter-card .card-header h5 {
    color: var(--primary-color);
    font-weight: 700;
    margin: 0;
    font-size: 1.1rem;
}

.filter-card .card-body {
    padding: 2rem;
}

.filter-card .form-select {
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%230066cc' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2 5l6 6 6-6'/%3e%3c/svg%3e") !important;
    background-repeat: no-repeat !important;
    background-position: right 0.75rem center !important;
    background-size: 1rem !important;
    padding-right: 2.5rem !important;
}

.status-badge-manage {
    padding: 0.35rem 0.75rem;
    border-radius: 6px;
    font-size: 0.85rem;
    font-weight: 600;
}

.status-pending-badge {
    background-color: rgba(255, 193, 7, 0.1);
    color: var(--warning-color);
}

.status-confirmed-badge {
    background-color: rgba(0, 102, 204, 0.1);
    color: var(--primary-color);
}

.status-completed-badge {
    background-color: rgba(6, 214, 160, 0.1);
    color: var(--success-color);
}

.status-declined-badge {
    background-color: rgba(239, 71, 111, 0.1);
    color: var(--danger-color);
}

.appointments-card {
    border-radius: 12px;
    box-shadow: var(--card-shadow);
    border: none;
    overflow: hidden;
}

.appointments-table {
    border-collapse: collapse;
    width: 100%;
    margin-bottom: 0;
}

.appointments-table thead th {
    background-color: var(--light-bg);
    color: var(--text-primary);
    font-weight: 600;
    padding: 1rem;
    border-bottom: 2px solid var(--border-color);
    text-align: left;
}

.appointments-table tbody td {
    padding: 1rem;
    border-bottom: 1px solid var(--border-color);
    vertical-align: middle;
}

.appointments-table tbody tr {
    transition: all 0.2s ease;
}

.appointments-table tbody tr:active {
    background-color: var(--light-bg);
}

.action-button {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.4rem 0.8rem;
    border-radius: 6px;
    font-size: 0.8rem;
    font-weight: 600;
    cursor: pointer;
    border: 1px solid var(--border-color);
    background-color: white;
    color: var(--primary-color);
    margin-right: 0.3rem;
    transition: all 0.3s ease;
    text-decoration: none;
}

.action-button:active {
    background-color: var(--light-bg);
    box-shadow: var(--card-shadow);
    transform: translateY(-1px);
}

.action-button.approve {
    color: var(--success-color);
    border-color: var(--success-color);
}

.action-button.approve:active {
    background-color: rgba(6, 214, 160, 0.1);
}

.action-button.decline {
    color: var(--danger-color);
    border-color: var(--danger-color);
}

.action-button.decline:active {
    background-color: rgba(239, 71, 111, 0.1);
}

.empty-state-manage {
    text-align: center;
    padding: 3rem 2rem;
    color: var(--text-secondary);
}

.empty-state-manage i {
    font-size: 3rem;
    color: var(--border-color);
    display: block;
    margin-bottom: 1rem;
}

@media (max-width: 768px) {
    .manage-header {
        font-size: 1.5rem;
    }

    .action-button {
        padding: 0.35rem 0.6rem;
        font-size: 0.75rem;
        margin-right: 0.25rem;
    }
}
</style>

<div class="row mb-4">
    <div class="col-12">
        <h1 class="manage-header">
            <i class="fas fa-list-check me-2"></i>Manage Appointments
        </h1>
        <p style="color: var(--text-secondary); font-size: 1.1rem;">Review and manage all appointment requests</p>
    </div>
</div>

<!-- Filters -->
<div class="filter-card card">
    <div class="card-header">
        <h5><i class="fas fa-sliders-h me-2"></i>Filter Appointments</h5>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
            <label for="status" class="form-label">Status</label>
            <select class="form-select" id="status" name="status">
                <option value="">All Statuses</option>
                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                <option value="confirmed" <?php echo $status_filter === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                <option value="declined" <?php echo $status_filter === 'declined' ? 'selected' : ''; ?>>Declined</option>
                <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                <option value="unattended" <?php echo $status_filter === 'unattended' ? 'selected' : ''; ?>>Unattended</option>
            </select>
        </div>
        <div class="col-md-3">
            <label for="date" class="form-label">Date</label>
            <input type="date" class="form-control" id="date" name="date" value="<?php echo htmlspecialchars($date_filter); ?>" style="border-radius: 8px; border: 1px solid var(--border-color);">
        </div>
        <?php if ($user['role'] === 'super_admin'): ?>
            <div class="col-md-3">
                <label for="department" class="form-label">Department</label>
                <select class="form-select" id="department" name="department">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?php echo $dept['id']; ?>" <?php echo $department_filter == $dept['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($dept['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
            <div class="col-md-3 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-primary" style="border-radius: 8px; padding: 0.75rem 1.5rem;">
                    <i class="fas fa-filter me-2"></i>Filter
                </button>
                <a href="manage_appointments.php" class="btn btn-outline-primary" style="border-radius: 8px; padding: 0.75rem 1.5rem; text-decoration: none;">Clear</a>
            </div>
        </form>
    </div>
</div>

<!-- Appointments Table -->
<div class="appointments-card">
    <div style="padding: 2rem; border-bottom: 1px solid var(--border-color);">
        <h4 style="color: var(--primary-color); font-weight: 700; margin-bottom: 0;">
            <i class="fas fa-calendar me-2"></i>Appointments (<?php echo count($appointments); ?>)
        </h4>
    </div>
    <div style="padding: 2rem; overflow-x: auto;">
        <?php if (empty($appointments)): ?>
            <div class="empty-state-manage">
                <i class="fas fa-inbox"></i>
                <p>No appointments found matching the criteria.</p>
            </div>
        <?php else: ?>
            <table class="appointments-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>User</th>
                        <th>Department</th>
                        <th>Date & Time</th>
                        <th>Purpose</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($appointments as $appointment): ?>
                        <tr>
                            <td><strong>#<?php echo $appointment['id']; ?></strong></td>
                            <td>
                                <div><?php echo htmlspecialchars($appointment['user_name']); ?></div>
                                <small style="color: var(--text-secondary);"><?php echo htmlspecialchars($appointment['user_email']); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($appointment['department_name']); ?></td>
                            <td><?php echo date('M j, Y g:i A', strtotime($appointment['appointment_date'] . ' ' . $appointment['appointment_time'])); ?></td>
                            <td><?php echo htmlspecialchars($appointment['agenda']); ?></td>
                            <td>
                                <span class="status-badge-manage status-<?php
                                    echo $appointment['status'] === 'confirmed' ? 'confirmed' :
                                         ($appointment['status'] === 'pending' ? 'pending' :
                                         ($appointment['status'] === 'declined' ? 'declined' :
                                         ($appointment['status'] === 'completed' ? 'completed' :
                                         ($appointment['status'] === 'unattended' ? 'pending' : 'pending'))));
                                ?>-badge">
                                    <?php echo ucfirst($appointment['status']); ?>
                                </span>
                            </td>
                            <td>
                                <div style="display: flex; gap: 0.3rem; flex-wrap: wrap;">
                                    <button class="action-button" onclick="viewAppointment(<?php echo $appointment['id']; ?>)" title="View Details">
                                        <i class="fas fa-eye"></i>View
                                    </button>
                                    <?php if ($appointment['status'] === 'pending'): ?>
                                        <button class="action-button approve" onclick="updateStatus(<?php echo $appointment['id']; ?>, 'confirmed')" title="Approve">
                                            <i class="fas fa-check"></i>Approve
                                        </button>
                                        <button class="action-button decline" onclick="declineAppointment(<?php echo $appointment['id']; ?>)" title="Decline">
                                            <i class="fas fa-times"></i>Decline
                                        </button>
                                    <?php elseif ($appointment['status'] === 'confirmed'): ?>
                                        <button class="action-button approve" onclick="updateStatus(<?php echo $appointment['id']; ?>, 'completed')" title="Mark Complete">
                                            <i class="fas fa-check-circle"></i>Complete
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<!-- Appointment Details Modal -->
<div class="modal fade" id="appointmentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" style="border: none; border-radius: 12px; box-shadow: var(--card-shadow-hover);">
            <div class="modal-header" style="background-color: var(--light-bg); border-bottom: 1px solid var(--border-color); border-radius: 12px 12px 0 0;">
                <h5 class="modal-title" style="color: var(--primary-color); font-weight: 700;">
                    <i class="fas fa-info-circle me-2"></i>Appointment Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="appointmentDetails" style="padding: 2rem;">
                <!-- Details will be loaded here -->
            </div>
        </div>
    </div>
</div>

<!-- Decline Reason Modal -->
<div class="modal fade" id="declineModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content" style="border: none; border-radius: 12px; box-shadow: var(--card-shadow-hover);">
            <div class="modal-header" style="background-color: var(--light-bg); border-bottom: 1px solid var(--border-color); border-radius: 12px 12px 0 0;">
                <h5 class="modal-title" style="color: var(--primary-color); font-weight: 700;">
                    <i class="fas fa-times-circle me-2"></i>Decline Appointment
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="padding: 2rem;">
                <form id="declineForm">
                    <div class="mb-3">
                        <label for="declineReason" class="form-label">Reason for Decline (Optional)</label>
                        <textarea class="form-control" id="declineReason" name="decline_reason" rows="4" placeholder="Please provide a reason for declining this appointment..." style="border-radius: 8px; border: 1px solid var(--border-color);"></textarea>
                    </div>
                    <input type="hidden" id="declineAppointmentId" name="appointment_id">
                    <input type="hidden" name="status" value="declined">
                </form>
            </div>
            <div class="modal-footer" style="border-top: 1px solid var(--border-color); padding: 1.5rem;">
                <button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" onclick="submitDecline()" style="border-radius: 8px; padding: 0.75rem 1.5rem;">Decline Appointment</button>
            </div>
        </div>
    </div>
</div>

<script>
function viewAppointment(appointmentId) {
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

function declineAppointment(appointmentId) {
    document.getElementById('declineAppointmentId').value = appointmentId;
    document.getElementById('declineReason').value = '';
    new bootstrap.Modal(document.getElementById('declineModal')).show();
}

function submitDecline() {
    const form = document.getElementById('declineForm');
    const formData = new FormData(form);

    fetch('update_appointment_status.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('declineModal')).hide();
            location.reload();
        } else {
            alert('Failed to decline appointment: ' + data.message);
        }
    });
}

function updateStatus(appointmentId, status) {
    const action = status === 'confirmed' ? 'approve' :
                   status === 'declined' ? 'decline' :
                   status === 'completed' ? 'mark as completed' : 'update';

    if (confirm(`Are you sure you want to ${action} this appointment?`)) {
        fetch('update_appointment_status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'appointment_id=' + appointmentId + '&status=' + status
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Failed to update appointment status: ' + data.message);
            }
        });
    }
}

function updatePriority(appointmentId, priority) {
    if (confirm(`Are you sure you want to set this appointment as ${priority}?`)) {
        fetch('update_appointment_status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'appointment_id=' + appointmentId + '&priority_status=' + priority
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Reload the modal content
                viewAppointment(appointmentId);
            } else {
                alert('Failed to update priority: ' + data.message);
            }
        });
    }
}
</script>

<?php include 'footer.php'; ?>