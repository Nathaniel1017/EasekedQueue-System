<?php
require_once 'config.php';

// Debug: Check session and user before requireAdmin
error_log("Admin Dashboard: Session user_id = " . ($_SESSION['user_id'] ?? 'not set'));
$user = getCurrentUser();
error_log("Admin Dashboard: getCurrentUser returned: " . ($user ? json_encode($user) : 'null'));

requireAdmin(); // Allow both admin and super_admin

$page_title = 'Admin Dashboard';
include 'header.php';

// Additional check after header is loaded
$user = getCurrentUser();
if (!$user || !in_array(trim($user['role']), ['admin', 'super_admin'])) {
    error_log("Admin Dashboard: Access denied for user " . ($user ? $user['id'] : 'null') . " with role " . ($user ? $user['role'] : 'null'));
    header("Location: index.php");
    exit();
}

$user = getCurrentUser();

// Debug logging
error_log("Admin Dashboard Access - User ID: " . $user['id'] . ", Role: " . $user['role'] . ", Dept ID: " . ($user['department_id'] ?? 'NULL'));

// Get statistics
$conn = getDBConnection();
if (!$conn) {
    die("Database connection failed. Please check your database configuration.");
}

// Build department filter for regular admins
$dept_filter = "";
$dept_params = [];
$dept_types = "";

if ($user['role'] === 'admin') {
    $dept_filter = " AND department_id = ?";
    $dept_params = [$user['department_id']];
    $dept_types = "i";
    error_log("Admin Dashboard - Building dept_filter with department_id: " . $user['department_id']);
}

// Total appointments
$query = "SELECT COUNT(*) as total FROM appointments WHERE 1=1" . $dept_filter;
$stmt = $conn->prepare($query);
if (!$stmt) {
    die("Failed to prepare statement: " . $conn->error);
}
if (!empty($dept_params)) {
    $stmt->bind_param($dept_types, ...$dept_params);
}
$stmt->execute();
$total_appointments = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// Pending appointments
$query = "SELECT COUNT(*) as pending FROM appointments WHERE status = 'pending' AND 1=1" . $dept_filter;
$stmt = $conn->prepare($query);
if (!$stmt) {
    die("Failed to prepare statement: " . $conn->error);
}

if (!empty($dept_params)) {
    $stmt->bind_param($dept_types, ...$dept_params);
}
$stmt->execute();
$pending_appointments = $stmt->get_result()->fetch_assoc()['pending'];
$stmt->close();


// Today's appointments
$today = date('Y-m-d');
$query = "SELECT COUNT(*) as today FROM appointments WHERE appointment_date = ? AND 1=1" . $dept_filter;
$stmt = $conn->prepare($query);
$params = array_merge([$today], $dept_params);
$types = "s" . $dept_types;
$stmt->bind_param($types, ...$params);
$stmt->execute();
$today_appointments = $stmt->get_result()->fetch_assoc()['today'];
$stmt->close();

// Total users (always global for all admins)
$result = $conn->query("SELECT COUNT(*) as users FROM users WHERE role = 'citizen'");
$total_users = $result->fetch_assoc()['users'];

// Recent appointments (last 10)
$query = "
    SELECT a.*, d.name as department_name, u.full_name as user_name
    FROM appointments a
    JOIN departments d ON a.department_id = d.id
    JOIN users u ON a.user_id = u.id
";
if ($user['role'] === 'admin') {
    $query .= " WHERE a.department_id = ?";
}
$query .= " ORDER BY a.appointment_date DESC, a.appointment_time DESC LIMIT 10";

error_log("Admin Dashboard - Query: " . $query);
if ($user['role'] === 'admin') {
    error_log("Admin Dashboard - Binding department_id: " . $user['department_id']);
}

$stmt = $conn->prepare($query);
if ($user['role'] === 'admin') {
    $stmt->bind_param("i", $user['department_id']);
}
$stmt->execute();

// Log query result
$result = $stmt->get_result();
error_log("Admin Dashboard - Recent appointments query returned " . $result->num_rows . " rows");
$recent_appointments = $result->fetch_all(MYSQLI_ASSOC);
error_log("Admin Dashboard - Appointments data: " . json_encode($recent_appointments));
$stmt->close();

// Appointments by department (for admin's department if not super_admin)
if ($user['role'] === 'admin') {
    $stmt = $conn->prepare("
        SELECT d.name, COUNT(*) as count
        FROM appointments a
        JOIN departments d ON a.department_id = d.id
        WHERE a.department_id = ?
        GROUP BY d.id, d.name
        ORDER BY count DESC
    ");
    $stmt->bind_param("i", $user['department_id']);
} else {
    $stmt = $conn->prepare("
        SELECT d.name, COUNT(*) as count
        FROM appointments a
        JOIN departments d ON a.department_id = d.id
        GROUP BY d.id, d.name
        ORDER BY count DESC
        LIMIT 10
    ");
}
$stmt->execute();
$appointments_by_dept = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

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

.dashboard-header {
    color: var(--primary-color);
    font-size: 2rem;
    font-weight: 700;
    margin-bottom: 1rem;
}

.stat-card {
    padding: 2rem;
    border-radius: 12px;
    background: white;
    border: none;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
    transition: all 0.3s ease;
    text-align: center;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    position: relative;
    overflow: visible;
}

.stat-card:hover {
    box-shadow: 0 8px 16px rgba(0, 0, 0, 0.12);
    transform: translateY(-2px);
}



.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1rem;
    font-size: 2rem;
    background: linear-gradient(135deg, rgba(0, 102, 204, 0.08), rgba(0, 180, 216, 0.08));
    color: var(--primary-color);
}

.stat-card.pending .stat-icon {
    background: linear-gradient(135deg, rgba(255, 193, 7, 0.08), rgba(255, 200, 0, 0.08));
    color: var(--warning-color);
}

.stat-card.completed .stat-icon {
    background: linear-gradient(135deg, rgba(6, 214, 160, 0.08), rgba(5, 184, 129, 0.08));
    color: var(--success-color);
}

.stat-card.today .stat-icon {
    background: linear-gradient(135deg, rgba(0, 180, 216, 0.08), rgba(0, 153, 184, 0.08));
    color: var(--secondary-color);
}

.stat-card.active .stat-icon {
    background: linear-gradient(135deg, rgba(6, 214, 160, 0.08), rgba(5, 184, 129, 0.08));
    color: var(--success-color);
}

.stat-value {
    font-size: 2.5rem;
    font-weight: 700;
    color: var(--primary-color);
    margin: 0.5rem 0;
    line-height: 1;
}

.stat-card.pending .stat-value {
    color: var(--warning-color);
}

.stat-card.completed .stat-value {
    color: var(--success-color);
}

.stat-card.today .stat-value {
    color: var(--secondary-color);
}

.stat-card.active .stat-value {
    color: var(--success-color);
}

.stat-label {
    color: var(--text-primary);
    font-size: 1rem;
    font-weight: 600;
    margin: 0.75rem 0 0 0;
    letter-spacing: -0.3px;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid var(--border-color);
}

.section-header h4 {
    color: var(--primary-color);
    font-weight: 700;
    margin: 0;
}

.appointment-table {
    border-collapse: collapse;
    width: 100%;
}

.appointment-table thead th {
    background-color: var(--light-bg);
    color: var(--text-primary);
    font-weight: 600;
    padding: 1rem;
    border-bottom: 2px solid var(--border-color);
    text-align: left;
}

.appointment-table tbody td {
    padding: 1rem;
    border-bottom: 1px solid var(--border-color);
    vertical-align: middle;
}

.appointment-table tbody tr {
    transition: all 0.2s ease;
}

.appointment-table tbody tr:active {
    background-color: var(--light-bg);
    box-shadow: inset 0 0 0 2px var(--primary-color);
}

.btn-view, .btn-cancel, .btn-approve, .btn-decline {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border-radius: 6px;
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    border: 1px solid var(--border-color);
    background-color: white;
    color: var(--primary-color);
    margin-right: 0.5rem;
    text-decoration: none;
}

.btn-view:active, .btn-cancel:active {
    background-color: var(--light-bg);
    box-shadow: var(--card-shadow);
}

.btn-approve {
    color: var(--success-color);
    border-color: var(--success-color);
}

.btn-approve:active {
    background-color: rgba(6, 214, 160, 0.1);
    box-shadow: var(--card-shadow);
}

.btn-decline {
    color: var(--danger-color);
    border-color: var(--danger-color);
}

.btn-decline:active {
    background-color: rgba(239, 71, 111, 0.1);
    box-shadow: var(--card-shadow);
}

.status-badge {
    padding: 0.35rem 0.75rem;
    border-radius: 6px;
    font-size: 0.85rem;
    font-weight: 600;
}

.status-pending {
    background-color: rgba(255, 193, 7, 0.1);
    color: var(--warning-color);
}

.status-confirmed {
    background-color: rgba(0, 102, 204, 0.1);
    color: var(--primary-color);
}

.status-completed {
    background-color: rgba(6, 214, 160, 0.1);
    color: var(--success-color);
}

.status-declined {
    background-color: rgba(239, 71, 111, 0.1);
    color: var(--danger-color);
}

.list-group-item {
    padding: 1rem;
    border: 1px solid var(--border-color);
    border-left: 3px solid var(--primary-color);
    background-color: white;
    transition: all 0.3s ease;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.list-group-item:active {
    background-color: var(--light-bg);
    box-shadow: var(--card-shadow);
}

.dept-badge {
    background-color: var(--primary-color);
    color: white;
    padding: 0.35rem 0.75rem;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 600;
}

.action-buttons {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

@media (max-width: 768px) {
    .stat-card {
        padding: 1.5rem;
    }

    .stat-value {
        font-size: 2rem;
    }

    .action-buttons {
        flex-direction: column;
    }

    .btn-view, .btn-cancel, .btn-approve, .btn-decline {
        width: 100%;
        justify-content: center;
        margin-right: 0;
        margin-bottom: 0.5rem;
    }
}
</style>

<div class="dashboard-header">
    <h1><i class="fas fa-chart-bar"></i><?php echo $user['role'] === 'super_admin' ? 'Super Admin Dashboard' : 'Admin Dashboard'; ?></h1>
    <p>Monitor and manage appointments across your system</p>
</div>

<div class="row mb-5">
    <div class="col-md-4 mb-4">
        <div class="stat-card confirmed">
            <div class="stat-icon"><i class="fas fa-calendar"></i></div>
            <p class="stat-label">Total Appointments</p>
            <div class="stat-value"><?php echo $total_appointments; ?></div>
        </div>
    </div>
    <div class="col-md-4 mb-4">
        <div class="stat-card pending">
            <div class="stat-icon"><i class="fas fa-exclamation-circle"></i></div>
            <p class="stat-label">Pending Approval</p>
            <div class="stat-value"><?php echo $pending_appointments; ?></div>
        </div>
    </div>
    <div class="col-md-4 mb-4">
        <div class="stat-card completed">
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            <p class="stat-label">Completed</p>
            <div class="stat-value"><?php
                // Get completed appointments count
                $conn2 = getDBConnection();
                $query = "SELECT COUNT(*) as completed FROM appointments WHERE status = 'completed' AND 1=1" . $dept_filter;
                $stmt = $conn2->prepare($query);
                if (!empty($dept_params)) {
                    $stmt->bind_param($dept_types, ...$dept_params);
                }
                $stmt->execute();
                $completed_count = $stmt->get_result()->fetch_assoc()['completed'];
                $stmt->close();
                $conn2->close();
                echo $completed_count;
            ?></div>
        </div>
    </div>
    <div class="col-md-4 mb-4">
        <div class="stat-card today">
            <div class="stat-icon"><i class="fas fa-calendar-day"></i></div>
            <p class="stat-label">Today's Appointments</p>
            <div class="stat-value"><?php echo $today_appointments; ?></div>
        </div>
    </div>
    <div class="col-md-4 mb-4">
        <div class="stat-card active">
            <div class="stat-icon"><i class="fas fa-check"></i></div>
            <p class="stat-label">Confirmed</p>
            <div class="stat-value"><?php
                // Get confirmed appointments count
                $conn3 = getDBConnection();
                $query = "SELECT COUNT(*) as confirmed FROM appointments WHERE status = 'confirmed' AND 1=1" . $dept_filter;
                $stmt = $conn3->prepare($query);
                if (!empty($dept_params)) {
                    $stmt->bind_param($dept_types, ...$dept_params);
                }
                $stmt->execute();
                $confirmed_count = $stmt->get_result()->fetch_assoc()['confirmed'];
                $stmt->close();
                $conn3->close();
                echo $confirmed_count;
            ?></div>
        </div>
    </div>
    <div class="col-md-4 mb-4">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-users"></i></div>
            <p class="stat-label">Registered Users</p>
            <div class="stat-value"><?php echo $total_users; ?></div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Recent Appointments -->
    <div class="col-lg-8 mb-4">
        <div class="card" style="border-radius: 12px; box-shadow: var(--card-shadow);">
            <div style="padding: 2rem; border-bottom: 1px solid var(--border-color);">
                <div class="section-header" style="border-bottom: none; margin-bottom: 0; padding-bottom: 0;">
                    <h4 style="margin-bottom: 0;"><i class="fas fa-list me-2"></i>Recent Appointments</h4>
                </div>
            </div>
            <div style="padding: 2rem;">
                <?php if (empty($recent_appointments)): ?>
                    <div style="text-align: center; padding: 3rem 2rem; color: var(--text-secondary);">
                        <i class="fas fa-inbox" style="font-size: 3rem; color: var(--border-color); display: block; margin-bottom: 1rem;"></i>
                        <p>No appointments found.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="appointment-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>User</th>
                                    <th>Department</th>
                                    <th>Date & Time</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_appointments as $appointment): ?>
                                    <tr>
                                        <td><strong>#<?php echo $appointment['id']; ?></strong></td>
                                        <td><?php echo htmlspecialchars($appointment['user_name']); ?></td>
                                        <td><?php echo htmlspecialchars($appointment['department_name']); ?></td>
                                        <td><?php echo date('M j, Y g:i A', strtotime($appointment['appointment_date'] . ' ' . $appointment['appointment_time'])); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php
                                                echo $appointment['status'] === 'confirmed' ? 'confirmed' :
                                                     ($appointment['status'] === 'pending' ? 'pending' :
                                                     ($appointment['status'] === 'declined' ? 'declined' :
                                                     ($appointment['status'] === 'completed' ? 'completed' :
                                                     ($appointment['status'] === 'unattended' ? 'pending' : 'pending'))));
                                            ?>">
                                                <?php echo ucfirst($appointment['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="btn-view" onclick="viewAppointment(<?php echo $appointment['id']; ?>)">
                                                    <i class="fas fa-eye"></i>View
                                                </button>
                                                <?php if ($appointment['status'] === 'pending'): ?>
                                                    <button class="btn-approve" onclick="updateStatus(<?php echo $appointment['id']; ?>, 'confirmed')">
                                                        <i class="fas fa-check"></i>Approve
                                                    </button>
                                                    <button class="btn-decline" onclick="declineAppointment(<?php echo $appointment['id']; ?>)">
                                                        <i class="fas fa-times"></i>Decline
                                                    </button>
                                                <?php endif; ?>
                                                <?php if ($appointment['status'] === 'confirmed'): ?>
                                                    <button class="btn-approve" onclick="updateStatus(<?php echo $appointment['id']; ?>, 'completed')">
                                                        <i class="fas fa-check-double"></i>Complete
                                                    </button>
                                                    <?php
                                                    $appointment_datetime = strtotime($appointment['appointment_date'] . ' ' . $appointment['appointment_time']);
                                                    $current_datetime = time();
                                                    if ($appointment_datetime < $current_datetime):
                                                    ?>
                                                    <button class="btn-decline" onclick="updateStatus(<?php echo $appointment['id']; ?>, 'unattended')">
                                                        <i class="fas fa-user-times"></i>Unattended
                                                    </button>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div style="margin-top: 1.5rem;">
                        <a href="manage_appointments.php" class="btn btn-primary" style="border-radius: 8px; padding: 0.75rem 1.5rem;">
                            <i class="fas fa-arrow-right me-2"></i>Manage All Appointments
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Appointments by Department -->
    <div class="col-lg-4 mb-4">
        <div class="card" style="border-radius: 12px; box-shadow: var(--card-shadow); margin-bottom: 1.5rem;">
            <div style="padding: 2rem; border-bottom: 1px solid var(--border-color);">
                <h4 style="color: var(--primary-color); font-weight: 700; margin-bottom: 0;">
                    <i class="fas fa-chart-pie me-2"></i>By Department
                </h4>
            </div>
            <div style="padding: 2rem;">
                <?php if (empty($appointments_by_dept)): ?>
                    <div style="text-align: center; padding: 2rem 1rem; color: var(--text-secondary);">
                        <p>No data available.</p>
                    </div>
                <?php else: ?>
                    <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                        <?php foreach ($appointments_by_dept as $dept): ?>
                            <div class="list-group-item">
                                <span><?php echo htmlspecialchars($dept['name']); ?></span>
                                <span class="dept-badge"><?php echo $dept['count']; ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="card" style="border-radius: 12px; box-shadow: var(--card-shadow);">
            <div style="padding: 2rem; border-bottom: 1px solid var(--border-color);">
                <h4 style="color: var(--primary-color); font-weight: 700; margin-bottom: 0;">
                    <i class="fas fa-bolt me-2"></i>Quick Actions
                </h4>
            </div>
            <div style="padding: 2rem;">
                <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                    <a href="manage_appointments.php" class="btn btn-outline-primary" style="display: flex; align-items: center; justify-content: center; gap: 0.5rem; padding: 0.75rem; border-radius: 8px; text-decoration: none;">
                        <i class="fas fa-calendar-alt"></i>Manage Appointments
                    </a>
                    <?php if ($user['role'] === 'super_admin'): ?>
                        <a href="manage_users.php" class="btn btn-outline-primary" style="display: flex; align-items: center; justify-content: center; gap: 0.5rem; padding: 0.75rem; border-radius: 8px; text-decoration: none;">
                            <i class="fas fa-users"></i>Manage Users
                        </a>
                        <a href="reports.php" class="btn btn-outline-primary" style="display: flex; align-items: center; justify-content: center; gap: 0.5rem; padding: 0.75rem; border-radius: 8px; text-decoration: none;">
                            <i class="fas fa-chart-bar"></i>View Reports
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
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

            // Build HTML
            let html = `
                <div class="row">
                    <div class="col-md-6">
                        <h6>Appointment Information</h6>
                        <p><strong>ID:</strong> #${appointment.id}</p>
                        <p><strong>Department:</strong> ${appointment.department_name}</p>
                        <p><strong>Date & Time:</strong> ${new Date(appointment.appointment_date + ' ' + appointment.appointment_time).toLocaleString()}</p>
                        <p><strong>Status:</strong>
                            <span class="badge bg-${statusClass}">${appointment.status.charAt(0).toUpperCase() + appointment.status.slice(1)}</span>
                        </p>
                    </div>
                    <div class="col-md-6">
                        <h6>Personal Information</h6>
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
                <p><strong>Created:</strong> ${new Date(appointment.created_at).toLocaleString()}</p>
            `;

            document.getElementById('appointmentDetails').innerHTML = html;
            new bootstrap.Modal(document.getElementById('appointmentModal')).show();
        })
        .catch(error => {
            console.error('Error loading appointment details:', error);
            alert('Failed to load appointment details.');
        });
}

function updateStatus(appointmentId, status) {
    const action = status === 'confirmed' ? 'approve' : status === 'completed' ? 'mark as completed' : 'update';
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

function declineAppointment(appointmentId) {
    document.getElementById('declineAppointmentId').value = appointmentId;
    document.getElementById('declineReason').value = '';
    new bootstrap.Modal(document.getElementById('declineModal')).show();
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
</script>

<?php include 'footer.php'; ?>