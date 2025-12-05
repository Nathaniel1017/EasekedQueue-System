<?php
require_once 'config.php';
requireAdmin();

$page_title = 'Manage Users';
include 'header.php';

$user = getCurrentUser();

// Check if only super_admin can access
if ($user['role'] !== 'super_admin') {
    header("Location: admin_dashboard.php");
    exit();
}

// Handle filters
$role_filter = $_GET['role'] ?? '';
$search_filter = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';

// Build query
$query = "SELECT users.*, departments.name AS department_name FROM users LEFT JOIN departments ON users.department_id = departments.id";

$conditions = [];
$params = [];
$types = '';

// Always exclude super_admin from display (only show citizen and admin)
$conditions[] = "role IN ('citizen', 'admin')";

if (!empty($role_filter) && in_array($role_filter, ['citizen', 'admin'])) {
    $conditions[] = "role = ?";
    $params[] = $role_filter;
    $types .= 's';
}

if (!empty($search_filter)) {
    $conditions[] = "(full_name LIKE ? OR email LIKE ? OR phone_number LIKE ?)";
    $search_param = '%' . $search_filter . '%';
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'sss';
}

if (!empty($status_filter) && in_array($status_filter, ['active', 'inactive'])) {
    $conditions[] = "is_active = ?";
    $params[] = ($status_filter === 'active' ? 1 : 0);
    $types .= 'i';
}

if (!empty($conditions)) {
    $query .= " WHERE " . implode(" AND ", $conditions);
}

$query .= " ORDER BY created_at DESC";

$conn = getDBConnection();
$stmt = $conn->prepare($query);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get departments for admin user assignment
$dept_query = "SELECT id, name FROM departments ORDER BY name";
$dept_stmt = $conn->prepare($dept_query);
$dept_stmt->execute();
$departments = $dept_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$dept_stmt->close();

// Handle user status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $user_id = $_POST['user_id'] ?? null;

    if (!$user_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'User ID is required']);
        exit();
    }

    $conn = getDBConnection();

    if ($action === 'toggle_status') {
        // Get current status
        $stmt = $conn->prepare("SELECT is_active FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($result) {
            $new_active_status = $result['is_active'] ? 0 : 1;
            $stmt = $conn->prepare("UPDATE users SET is_active = ? WHERE id = ?");
            $stmt->bind_param("ii", $new_active_status, $user_id);
            if ($stmt->execute()) {
                $new_status = $new_active_status ? 'active' : 'inactive';
                http_response_code(200);
                echo json_encode(['success' => true, 'message' => 'User status updated', 'new_status' => $new_status]);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to update user status']);
            }
            $stmt->close();
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'User not found']);
        }
    } elseif ($action === 'delete') {
        // Delete user
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role != 'super_admin'");
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute()) {
            http_response_code(200);
            echo json_encode(['success' => true, 'message' => 'User deleted successfully']);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to delete user']);
        }
        $stmt->close();
    } elseif ($action === 'reset_password') {
        // Reset password to default
        $default_password = 'Password123!';
        $hashed_password = password_hash($default_password, PASSWORD_BCRYPT);
        $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->bind_param("si", $hashed_password, $user_id);
        if ($stmt->execute()) {
            http_response_code(200);
            echo json_encode(['success' => true, 'message' => 'Password reset to default']);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to reset password']);
        }
        $stmt->close();
    } elseif ($action === 'update_department') {
        // Update user's department assignment
        $department_id = $_POST['department_id'] ?? null;
        
        // Verify user is admin
        $stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$result) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'User not found']);
        } elseif ($result['role'] !== 'admin') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Only admin users can be assigned departments']);
        } else {
            // Update department
            $stmt = $conn->prepare("UPDATE users SET department_id = ? WHERE id = ?");
            $stmt->bind_param("ii", $department_id, $user_id);
            if ($stmt->execute()) {
                http_response_code(200);
                echo json_encode(['success' => true, 'message' => 'Department assigned successfully']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to assign department']);
            }
            $stmt->close();
        }
    }

    $conn->close();
    exit();
}

$conn->close();
?>

<style>
.users-header {
    color: var(--primary-color);
    font-size: 2rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
}

.filter-panel {
    border-radius: 12px;
    box-shadow: var(--card-shadow);
    border: none;
    margin-bottom: 2rem;
    background-color: white;
    overflow: hidden;
}

.filter-panel .card-header {
    background-color: var(--light-bg);
    border-bottom: 1px solid var(--border-color);
    padding: 1.5rem 2rem;
    margin: 0;
}

.filter-panel .card-header h5 {
    color: var(--primary-color);
    font-weight: 700;
    margin: 0;
    font-size: 1.1rem;
}

.filter-panel .card-body {
    padding: 2rem;
}

.filter-panel .form-select {
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%230066cc' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2 5l6 6 6-6'/%3e%3c/svg%3e") !important;
    background-repeat: no-repeat !important;
    background-position: right 0.75rem center !important;
    background-size: 1rem !important;
    padding-right: 2.5rem !important;
}

.users-table-card {
    border-radius: 12px;
    box-shadow: var(--card-shadow);
    border: none;
    overflow: hidden;
}

.users-table {
    border-collapse: collapse;
    width: 100%;
    margin-bottom: 0;
}

.users-table thead th {
    background-color: var(--light-bg);
    color: var(--text-primary);
    font-weight: 600;
    padding: 1rem;
    border-bottom: 2px solid var(--border-color);
    text-align: left;
}

.users-table tbody td {
    padding: 1rem;
    border-bottom: 1px solid var(--border-color);
    vertical-align: middle;
}

.users-table tbody tr {
    transition: all 0.2s ease;
}

.users-table tbody tr:active {
    background-color: var(--light-bg);
}

.role-badge {
    padding: 0.35rem 0.75rem;
    border-radius: 6px;
    font-size: 0.85rem;
    font-weight: 600;
}

.role-admin {
    background-color: rgba(255, 193, 7, 0.1);
    color: var(--warning-color);
}

.role-citizen {
    background-color: rgba(0, 102, 204, 0.1);
    color: var(--primary-color);
}

.status-badge-users {
    padding: 0.35rem 0.75rem;
    border-radius: 6px;
    font-size: 0.85rem;
    font-weight: 600;
}

.status-active {
    background-color: rgba(6, 214, 160, 0.1);
    color: var(--success-color);
}

.status-inactive {
    background-color: rgba(239, 71, 111, 0.1);
    color: var(--danger-color);
}

.user-action-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    padding: 0.35rem 0.6rem;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 600;
    cursor: pointer;
    border: 1px solid var(--border-color);
    background-color: white;
    color: var(--primary-color);
    transition: all 0.3s ease;
    text-decoration: none;
    margin-right: 0;
    margin-bottom: 0;
}

.user-action-button:active {
    background-color: var(--light-bg);
    box-shadow: var(--card-shadow);
    transform: translateY(-1px);
}

.user-action-button.danger {
    color: var(--danger-color);
    border-color: var(--danger-color);
}

.user-action-button.danger:active {
    background-color: rgba(239, 71, 111, 0.1);
}

.user-actions-container {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.5rem;
    max-width: 300px;
}

.empty-state-users {
    text-align: center;
    padding: 3rem 2rem;
    color: var(--text-secondary);
}

.empty-state-users i {
    font-size: 3rem;
    color: var(--border-color);
    display: block;
    margin-bottom: 1rem;
}

@media (max-width: 768px) {
    .users-header {
        font-size: 1.5rem;
    }

    .user-action-button {
        padding: 0.3rem 0.5rem;
        font-size: 0.7rem;
    }
}
</style>

<div class="row mb-4">
    <div class="col-12">
        <h1 class="users-header">
            <i class="fas fa-users-cog me-2"></i>Manage Users
        </h1>
        <p style="color: var(--text-secondary); font-size: 1.1rem;">View and manage registered users in the system</p>
    </div>
</div>

<!-- Filters -->
<div class="filter-panel card">
    <div class="card-header">
        <h5><i class="fas fa-sliders-h me-2"></i>Filter Users</h5>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
            <label for="search" class="form-label">Search Users</label>
            <input type="text" class="form-control" id="search" name="search" 
                placeholder="Name, email, or phone..." value="<?php echo htmlspecialchars($search_filter); ?>" style="border-radius: 8px; border: 1px solid var(--border-color);">
        </div>
        <div class="col-md-2">
            <label for="role" class="form-label">Role</label>
            <select class="form-select" id="role" name="role">
                <option value="">All Roles</option>
                <option value="citizen" <?php echo $role_filter === 'citizen' ? 'selected' : ''; ?>>Citizen</option>
                <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Admin</option>
            </select>
        </div>
        <div class="col-md-2">
            <label for="status" class="form-label">Status</label>
            <select class="form-select" id="status" name="status">
                <option value="">All Status</option>
                <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
            </select>
        </div>
        <div class="col-md-4 d-flex align-items-end gap-2">
            <button type="submit" class="btn btn-primary" style="border-radius: 8px; padding: 0.75rem 1.5rem;">
                <i class="fas fa-filter me-2"></i>Filter
            </button>
            <a href="manage_users.php" class="btn btn-outline-primary" style="border-radius: 8px; padding: 0.75rem 1.5rem; text-decoration: none;">Clear</a>
        </div>
        </form>
    </div>
</div>

<!-- Users Table -->
<div class="users-table-card">
    <div style="padding: 2rem; border-bottom: 1px solid var(--border-color);">
        <h4 style="color: var(--primary-color); font-weight: 700; margin-bottom: 0;">
            <i class="fas fa-user-friends me-2"></i>Users (<?php echo count($users); ?>)
        </h4>
    </div>
    <div style="padding: 2rem; overflow-x: auto;">
        <?php if (empty($users)): ?>
            <div class="empty-state-users">
                <i class="fas fa-inbox"></i>
                <p>No users found matching the criteria.</p>
            </div>
        <?php else: ?>
            <table class="users-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Role</th>
                        <th>Department</th>
                        <th>Status</th>
                        <th>Registered</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $display_user): ?>
                        <tr>
                            <td><strong>#<?php echo $display_user['id']; ?></strong></td>
                            <td><?php echo htmlspecialchars($display_user['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($display_user['email']); ?></td>
                            <td><?php echo htmlspecialchars($display_user['phone_number'] ?? 'N/A'); ?></td>
                            <td>
                                <span class="role-badge role-<?php echo $display_user['role'] === 'admin' ? 'admin' : 'citizen'; ?>">
                                    <?php echo ucfirst($display_user['role']); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($display_user['department_name'] ?? 'N/A'); ?></td>
                            <td>
                                <span class="status-badge-users status-<?php echo $display_user['is_active'] ? 'active' : 'inactive'; ?>">
                                    <?php echo $display_user['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td><?php echo date('M j, Y', strtotime($display_user['created_at'])); ?></td>
                            <td>
                                <div class="user-actions-container">
                                    <button class="user-action-button" 
                                        onclick="viewUser(<?php echo $display_user['id']; ?>, '<?php echo htmlspecialchars($display_user['full_name']); ?>')"
                                        title="View Details">
                                        <i class="fas fa-eye"></i>View
                                    </button>
                                    <?php if ($display_user['role'] === 'admin'): ?>
                                        <button class="user-action-button" 
                                            onclick="manageDepartment(<?php echo $display_user['id']; ?>, '<?php echo htmlspecialchars($display_user['full_name']); ?>', <?php echo $display_user['department_id'] ?? 'null'; ?>)"
                                            title="Manage Department">
                                            <i class="fas fa-building"></i>Dept
                                        </button>
                                    <?php endif; ?>
                                    <button class="user-action-button" 
                                        onclick="toggleUserStatus(<?php echo $display_user['id']; ?>, <?php echo $display_user['is_active'] ? '1' : '0'; ?>)"
                                        title="Toggle Status">
                                        <i class="fas fa-<?php echo $display_user['is_active'] ? 'ban' : 'check'; ?>"></i>
                                        <?php echo $display_user['is_active'] ? 'Deact' : 'Activ'; ?>
                                    </button>
                                    <button class="user-action-button" 
                                        onclick="resetUserPassword(<?php echo $display_user['id']; ?>, '<?php echo htmlspecialchars($display_user['full_name']); ?>')"
                                        title="Reset Password">
                                        <i class="fas fa-key"></i>Reset
                                    </button>
                                    <button class="user-action-button danger" 
                                        onclick="deleteUser(<?php echo $display_user['id']; ?>, '<?php echo htmlspecialchars($display_user['full_name']); ?>')"
                                        title="Delete User">
                                        <i class="fas fa-trash"></i>Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<!-- User Details Modal -->
<div class="modal fade" id="userModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" style="border: none; border-radius: 12px; box-shadow: var(--card-shadow-hover);">
            <div class="modal-header" style="background-color: var(--light-bg); border-bottom: 1px solid var(--border-color); border-radius: 12px 12px 0 0;">
                <h5 class="modal-title" style="color: var(--primary-color); font-weight: 700;">
                    <i class="fas fa-user-circle me-2"></i>User Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="userDetails" style="padding: 2rem;">
                <!-- Details will be loaded here -->
            </div>
        </div>
    </div>
</div>

<!-- Manage Department Modal -->
<div class="modal fade" id="departmentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content" style="border: none; border-radius: 12px; box-shadow: var(--card-shadow-hover);">
            <div class="modal-header" style="background-color: var(--light-bg); border-bottom: 1px solid var(--border-color); border-radius: 12px 12px 0 0;">
                <h5 class="modal-title" style="color: var(--primary-color); font-weight: 700;">
                    <i class="fas fa-building me-2"></i>Assign Department
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="padding: 2rem;">
                <form id="deptForm">
                    <input type="hidden" id="deptUserId" value="">
                    <div class="mb-3">
                        <label for="deptSelect" class="form-label">Select Department</label>
                        <select class="form-select" id="deptSelect" required>
                            <option value="">-- Select a Department --</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo $dept['id']; ?>"><?php echo htmlspecialchars($dept['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div style="background-color: var(--light-bg); border-left: 4px solid var(--primary-color); padding: 1rem; border-radius: 6px;">
                        <small style="color: var(--text-secondary);"><i class="fas fa-info-circle me-2"></i>This admin user will only see appointments for the selected department.</small>
                    </div>
                </form>
            </div>
            <div class="modal-footer" style="border-top: 1px solid var(--border-color); padding: 1.5rem;">
                <button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveDepartment()" style="border-radius: 8px; padding: 0.75rem 1.5rem;">Assign Department</button>
            </div>
        </div>
    </div>
</div>

<script>
function viewUser(userId, userName) {
    const modal = new bootstrap.Modal(document.getElementById('userModal'));
    document.getElementById('userDetails').innerHTML = '<div style="text-align: center; padding: 2rem;"><p><strong>User ID:</strong> #' + userId + '</p><p><strong>Name:</strong> ' + userName + '</p><p style="margin-top: 2rem; color: var(--text-secondary);">Use other actions to manage this user</p></div>';
    modal.show();
}

function manageDepartment(userId, userName, currentDepartment) {
    document.getElementById('deptUserId').value = userId;
    if (currentDepartment) {
        document.getElementById('deptSelect').value = currentDepartment;
    }
    new bootstrap.Modal(document.getElementById('departmentModal')).show();
}

function saveDepartment() {
    const userId = document.getElementById('deptUserId').value;
    const deptId = document.getElementById('deptSelect').value;
    
    if (!deptId) {
        alert('Please select a department');
        return;
    }
    
    fetch('manage_users.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=update_department&user_id=' + userId + '&department_id=' + deptId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Department assigned successfully');
            bootstrap.Modal.getInstance(document.getElementById('departmentModal')).hide();
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        alert('Error assigning department');
        console.error(error);
    });
}

function toggleUserStatus(userId, currentStatus) {
    const isActive = currentStatus === 1;
    const action = isActive ? 'deactivate' : 'activate';
    
    if (confirm('Are you sure you want to ' + action + ' this user?')) {
        fetch('manage_users.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=toggle_status&user_id=' + userId
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('User ' + action + 'd successfully');
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            alert('Error updating user status');
            console.error(error);
        });
    }
}

function resetUserPassword(userId, userName) {
    if (confirm('Reset password for ' + userName + ' to default (Password123!)?\n\nThe user will need to change this password on next login.')) {
        fetch('manage_users.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=reset_password&user_id=' + userId
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Password reset to default successfully');
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            alert('Error resetting password');
            console.error(error);
        });
    }
}

function deleteUser(userId, userName) {
    if (confirm('Are you sure you want to delete ' + userName + '? This action cannot be undone.')) {
        fetch('manage_users.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=delete&user_id=' + userId
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('User deleted successfully');
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            alert('Error deleting user');
            console.error(error);
        });
    }
}
</script>

<?php include 'footer.php'; ?>
